<!DOCTYPE html>
<html>
<head>
    <title>Chat IA - Soluciones Edgar</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: system-ui, sans-serif; background: #1a1a1a; color: #ececec; padding: 20px; }
        h1 { color: #19c37d; }
        textarea { width: 100%; max-width: 600px; background: #2f2f2f; color: #ececec; border: 1px solid #3f3f3f; border-radius: 8px; padding: 10px; font-size: 14px; }
        button { background: #19c37d; color: #000; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        button:hover { background: #15a067; }
        #respuesta { background: #2f2f2f; border: 1px solid #3f3f3f; border-radius: 8px; padding: 15px; margin-top: 10px; max-width: 600px; white-space: pre-wrap; word-break: break-word; line-height: 1.6; }
        .gpt-download-btn {
            display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;
            background: #19c37d; color: #000; font-weight: 600; font-size: 14px;
            border-radius: 10px; text-decoration: none; margin: 6px 0; cursor: pointer;
        }
        .gpt-download-btn:hover { background: #15a067; color: #000; }
    </style>
</head>
<body>

    <h1>Chat IA - Soluciones Edgar</h1>

    <textarea id="prompt" rows="5" cols="60" placeholder="Escribe tu pregunta..."></textarea>

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

            const div = document.getElementById('respuesta');
            div.innerHTML = (data.respuesta || '').replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
        }

    </script>

</body>
</html>