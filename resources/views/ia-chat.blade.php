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
        let aiConversationId = localStorage.getItem('ai_conversation_id') || null;

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
                    pregunta: prompt,
                    conversation_id: aiConversationId,
                })
            });

            const data = await response.json();

            if (data.conversation_id) {
                aiConversationId = data.conversation_id;
                localStorage.setItem('ai_conversation_id', aiConversationId);
            }

            // Si el backend indicó que la conversación fue completada, limpiar el conversation_id guardado
            if (data.metadata && (data.metadata.reset_conversation || data.metadata.conversation_completed)) {
                aiConversationId = null;
                localStorage.removeItem('ai_conversation_id');
            }

            document.getElementById('respuesta').innerText = data.respuesta;
        }
    </script>

</body>
</html>