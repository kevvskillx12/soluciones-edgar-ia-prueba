<!DOCTYPE html>
<html>
<head>
    <title>Chat IA</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>

    <h1>Chat IA - Soluciones Edgar</h1>

    <textarea id="prompt" rows="5" cols="60"></textarea>

    <br><br>

    <button onclick="enviarPrompt()">Enviar</button>

    <h3>Respuesta:</h3>

    <div id="respuesta"></div>

    <script>

        async function enviarPrompt() {

            const prompt = document.getElementById('prompt').value;

            const response = await fetch('/ia-test', {

                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute('content')
                },

                body: JSON.stringify({
                    pregunta: prompt
                })
            });

            const data = await response.json();

            document.getElementById('respuesta').innerText = data.respuesta;
        }

    </script>

</body>
</html>