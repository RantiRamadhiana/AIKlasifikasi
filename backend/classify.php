<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


// =========================
// CORS
// =========================

header("Access-Control-Allow-Origin: http://localhost:5173");

header("Access-Control-Allow-Methods: POST, OPTIONS");

header("Access-Control-Allow-Headers: Content-Type");

header("Content-Type: application/json");


// HANDLE PREFLIGHT
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit();
}


// =========================
// LOAD ENV MANUAL
// =========================
$env = parse_ini_file(".env");

if(!$env){

    echo json_encode([
        "kategori" => "Error",
        "reasoning" => ".env file tidak ditemukan"
    ]);

    exit();
}


$OPENROUTER_API_KEY =
    $env["OPENROUTER_API_KEY"] ?? "";


// VALIDASI API KEY
if(empty($OPENROUTER_API_KEY)){
    echo json_encode([
        "kategori" => "Error",
        "reasoning" => "OPENROUTER_API_KEY kosong"
    ]);

    exit();
}


// =========================
// INPUT CONTENT
// =========================

$content = $_POST["content"] ?? "";

$messageContent = [
    [
        "type" => "text",
        "text" => $content
    ]
];


// =========================
// HANDLE IMAGE
// =========================

if(isset($_FILES["image"])){

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


// =========================
// PROMPT MODEL
// =========================

$data = [

    "model" => "qwen/qwen3-vl-32b-instruct",

    "messages" => [

        [
            "role" => "system",

            "content" => '
Anda adalah model klasifikasi konten.

Tugas:
- Analisis teks dan gambar
- Tentukan apakah termasuk:
  - Disinformasi
  - Fitnah
  - Ujaran Kebencian
  - Netral

Berikan reasoning singkat dan jelas.

Balas WAJIB format JSON valid:

{
  "kategori":"...",
  "reasoning":"..."
}
'
        ],

        [
            "role" => "user",
            "content" => $messageContent
        ]
    ]
];


// =========================
// CALL OPENROUTER API
// =========================

$ch = curl_init();

curl_setopt(
    $ch,
    CURLOPT_URL,
    "https://openrouter.ai/api/v1/chat/completions"
);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_POST, true);

curl_setopt(
    $ch,
    CURLOPT_HTTPHEADER,
    [
        "Authorization: Bearer $OPENROUTER_API_KEY",
        "Content-Type: application/json"
    ]
);

curl_setopt(
    $ch,
    CURLOPT_POSTFIELDS,
    json_encode($data)
);


// EXECUTE
$response = curl_exec($ch);


// CURL ERROR
if(curl_errno($ch)){

    echo json_encode([

        "kategori" => "Error",

        "reasoning" =>
            curl_error($ch)
    ]);

    curl_close($ch);

    exit();
}


curl_close($ch);


// =========================
// PARSE RESPONSE
// =========================

$result = json_decode($response, true);


// API ERROR
if(isset($result["error"])){

    echo json_encode([

        "kategori" => "Error",

        "reasoning" =>
            $result["error"]["message"]
    ]);

    exit();
}


// OUTPUT MODEL
$output =
    $result["choices"][0]["message"]["content"] ?? "";


// PARSE JSON MODEL
$parsed = json_decode($output, true);


// JIKA MODEL TIDAK VALID JSON
if(!$parsed){

    $parsed = [

        "kategori" => "Unknown",

        "reasoning" => $output
    ];
}


// =========================
// FINAL OUTPUT
// =========================

echo json_encode($parsed);