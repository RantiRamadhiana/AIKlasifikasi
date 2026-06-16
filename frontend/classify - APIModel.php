<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

$task = $_POST["task"] ?? "TEXT_CLASSIFICATION";
$content = trim($_POST["content"] ?? "");

if (
    empty($content)
    && !isset($_FILES["image"])
) {
    echo json_encode([
        "kategori" => "ERROR",
        "reasoning" => "Content kosong"
    ]);
    exit();
}

$messageContent = [];

if (!empty($content)) {
    $messageContent[] = [
        "type" => "text",
        "text" => $content
    ];
}

if (isset($_FILES["image"])) {

    $imageData = base64_encode(
        file_get_contents(
            $_FILES["image"]["tmp_name"]
        )
    );

    $mimeType = $_FILES["image"]["type"];

    $messageContent[] = [
        "type" => "image_url",
        "image_url" => [
            "url" =>
                "data:$mimeType;base64,$imageData"
        ]
    ];
}

/* =======================================
   TASK PROMPT
======================================= */

switch ($task) {

    case "KEYWORD_EXTRACTION":

        $systemPrompt =
            "[TASK: KEYWORD_EXTRACTION] Anda adalah analis topik dan isu publik. Berdasarkan artikel atau narasi yang diberikan, identifikasi keyword utama yang paling merepresentasikan topik, entitas, isu, peristiwa, lokasi, organisasi, dan kata kunci pencarian yang relevan. Keluarkan hasil dalam format list JSON string. Keluarkan keyword dalam 1 kata saja";

        break;

    case "IMG_CLASSIFICATION":

        $systemPrompt =
            "[TASK: IMG_CLASSIFICATION] Anda adalah seorang analis konten media sosial ahli. Diberikan tangkapan layar dari sebuah unggahan media sosial, tentukan label kategori pelanggaran dan berikan analisis detail mengenai pelanggaran yang ditemukan.";

        break;

    default:

        $systemPrompt =
            "[TASK: TEXT_CLASSIFICATION] Anda adalah analis konten yang bertugas mengklasifikasikan teks ke dalam kategori:DISINFORMASI,FITNAH,UJARAN KEBENCIAN, atau NETRAL.Gunakan artikel rujukan sebagai dasar analisis dan berikan alasan yang jelas sebelum menentukan label.";
}

/* =======================================
   REQUEST KE VLLM
======================================= */

$data = [

    "model" => "dfkits",

    "messages" => [

        [
            "role" => "system",
            "content" => $systemPrompt
        ],

        [
            "role" => "user",
            "content" => $messageContent
        ]
    ],

    "temperature" => 0,

    "max_tokens" => 512
];

$ch = curl_init();

curl_setopt(
    $ch,
    CURLOPT_URL,
    "https://md3n9bwwxp94mp-8000.proxy.runpod.net/v1/chat/completions"
);

curl_setopt(
    $ch,
    CURLOPT_RETURNTRANSFER,
    true
);

curl_setopt(
    $ch,
    CURLOPT_POST,
    true
);

curl_setopt(
    $ch,
    CURLOPT_HTTPHEADER,
    [
        "Content-Type: application/json"
    ]
);

curl_setopt(
    $ch,
    CURLOPT_POSTFIELDS,
    json_encode($data)
);

$response = curl_exec($ch);

if (curl_errno($ch)) {

    echo json_encode([
        "kategori" => "ERROR",
        "reasoning" => curl_error($ch)
    ]);

    curl_close($ch);
    exit();
}

curl_close($ch);

$result = json_decode(
    $response,
    true
);

if (
    !isset(
        $result["choices"][0]["message"]["content"]
    )
) {

    echo json_encode([
        "kategori" => "ERROR",
        "reasoning" => $response
    ]);

    exit();
}

$output =
    $result["choices"][0]["message"]["content"];

$normalizedOutput =
    strtoupper(trim($output));

$confidence = null;

if (
    preg_match(
        '/Confidence\s*:\s*([0-9\.]+)/i',
        $output,
        $match
    )
) {

    $confidence =
        floatval($match[1]);
}
/* =======================================
   KEYWORD TASK
======================================= */

if (
    $task === "KEYWORD_EXTRACTION"
) {

    echo json_encode([
        "kategori" =>
            "KEYWORD_EXTRACTION",

        "reasoning" =>
            $output
    ]);

    exit();
}


/* =======================================
   EXTRACT LABEL
======================================= */

$kategori = "UNKNOWN";

if (
    preg_match(
        '/Label\s*:\s*([^\r\n]+)/i',
        $output,
        $match
    )
) {

    $kategori = strtoupper(
        trim($match[1])
    );

    $kategori = str_replace(
        "_",
        " ",
        $kategori
    );
}
else {

    $validLabels = [
        "DISINFORMASI",
        "FITNAH",
        "UJARAN KEBENCIAN",
        "NETRAL"
    ];

    foreach ($validLabels as $label) {

        if (
            stripos(
                strtoupper($output),
                $label
            ) !== false
        ) {

            $kategori = $label;
            break;
        }
    }
}

/* =======================================
   EXTRACT REASONING
======================================= */

$reasoning = "";

if (
    preg_match(
        '/Alasan\s*:\s*(.*)/is',
        $output,
        $match
    )
) {

    $reasoning =
        trim($match[1]);
}
else if (
    preg_match(
        '/Analisis\s*:\s*(.*)/is',
        $output,
        $match
    )
) {

    $reasoning =
        trim($match[1]);
}

if (empty($reasoning)) {

    $reasoning = $output;
}

echo json_encode([
    "kategori" => $kategori,
     "confidence" =>
        $confidence,
    "reasoning" => $reasoning,
    "raw_output" => $output
]);