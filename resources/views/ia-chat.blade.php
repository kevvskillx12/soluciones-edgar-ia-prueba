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

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const data = await response.json();
                document.getElementById('respuesta').innerText = data.respuesta || 'Error.';
                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder("utf-8");
            let responseText = '';
            document.getElementById('respuesta').innerText = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                
                const chunk = decoder.decode(value, { stream: true });
                const lines = chunk.split('\n');
                
                for (const line of lines) {
                    if (line.startsWith('data: ')) {
                        const dataStr = line.slice(6);
                        if (!dataStr) continue;
                        try {
                            const data = JSON.parse(dataStr);
                            if (data.type === 'token') {
                                responseText += data.token;
                                document.getElementById('respuesta').innerText = responseText;
                            } else if (data.type === 'done') {
                                if (data.conversation_id) {
                                    aiConversationId = data.conversation_id;
                                    localStorage.setItem('ai_conversation_id', aiConversationId);
                                }
                                if (data.metadata && (data.metadata.reset_conversation || data.metadata.conversation_completed)) {
                                    aiConversationId = null;
                                    localStorage.removeItem('ai_conversation_id');
                                }
                            }
                        } catch (e) {}
                    }
                }
            }
        }
    </script>

</body>
</html>