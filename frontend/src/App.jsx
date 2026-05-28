import { useState } from "react";

function App() {

  const [content, setContent] = useState("");

  const [image, setImage] = useState(null);

  const [kategori, setKategori] = useState("-");

  const [reasoning, setReasoning] = useState("-");

  const [loading, setLoading] = useState(false);


  const classifyContent = async () => {

    setLoading(true);

    const formData = new FormData();

    formData.append("content", content);

    if(image){
      formData.append("image", image);
    }

    try {

      const response = await fetch(
        "http://localhost/ai-backend/classify.php",
        {
          method:"POST",
          body:formData
        }
      );

      const data = await response.json();

      setKategori(data.kategori);

      setReasoning(data.reasoning);

    } catch(error){

      console.error(error);

      setKategori("Error");

      setReasoning(
        "Gagal konek backend"
      );
    }

    setLoading(false);
  };

  return (

    <div className="
      min-h-screen
      bg-gray-100
      flex
      justify-center
      p-10
    ">

      <div className="
        w-full
        max-w-3xl
        bg-white
        p-8
        rounded-2xl
        shadow-lg
      ">

        <h1 className="
          text-3xl
          font-bold
          text-center
          mb-8
        ">

          Klasifikasi Konten

        </h1>


        {/* CONTENT */}

        <div className="mb-5">

          <label className="font-semibold">
            Content
          </label>

          <textarea

            value={content}

            onChange={(e)=>
              setContent(e.target.value)
            }

            placeholder="Masukkan teks..."

            className="
              w-full
              h-52
              mt-2
              p-4
              border
              rounded-xl
            "
          />

        </div>


        {/* IMAGE */}

        <div className="mb-6">

          <label className="font-semibold">
            Image
          </label>

          <input

            type="file"

            accept="image/*"

            onChange={(e)=>
              setImage(e.target.files[0])
            }

            className="mt-2"
          />

        </div>


        {/* BUTTON */}

        <button

          onClick={classifyContent}

          className="
            w-full
            bg-blue-600
            hover:bg-blue-700
            text-white
            py-4
            rounded-xl
            font-semibold
          "
        >

          {
            loading
              ? "Processing..."
              : "Klasifikasi"
          }

        </button>


        {/* RESULT */}

        <div className="
          mt-8
          border
          rounded-2xl
          p-6
          bg-gray-50
        ">

          <h2 className="
            text-xl
            font-bold
          ">
            Kategori
          </h2>

          <p className="mt-2">
            {kategori}
          </p>


          <h2 className="
            text-xl
            font-bold
            mt-6
          ">
            Reasoning
          </h2>

          <p className="
            mt-2
            whitespace-pre-wrap
          ">
            {reasoning}
          </p>

        </div>

      </div>

    </div>
  );
}

export default App;