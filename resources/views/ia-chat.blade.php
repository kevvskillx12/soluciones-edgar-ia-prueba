<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chat IA - Soluciones Edgar</title>
    <style>
        :root { color-scheme: light; font-family: Inter, system-ui, sans-serif; }
        body { margin: 0; background: #f3f4f6; color: #111827; }
        main { width: min(820px, calc(100% - 32px)); margin: 32px auto; }
        .panel { overflow: hidden; background: white; border: 1px solid #e5e7eb; border-radius: 18px; box-shadow: 0 12px 35px rgba(15, 23, 42, .08); }
        header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 24px; border-bottom: 1px solid #e5e7eb; }
        h1 { margin: 0; font-size: 20px; }
        #messages { min-height: 420px; max-height: 60vh; overflow-y: auto; padding: 24px; }
        .message { max-width: 78%; margin-bottom: 14px; padding: 12px 15px; border-radius: 16px; white-space: pre-wrap; line-height: 1.45; }
        .user { margin-left: auto; color: white; background: #111827; border-bottom-right-radius: 4px; }
        .assistant { color: #1e1b4b; background: #eef2ff; border-bottom-left-radius: 4px; }
        .error { color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; }
        .composer { padding: 18px; border-top: 1px solid #e5e7eb; }
        .input-row { display: flex; align-items: flex-end; gap: 10px; }
        textarea { flex: 1; min-height: 48px; max-height: 150px; padding: 13px; resize: vertical; border: 1px solid #d1d5db; border-radius: 14px; font: inherit; }
        button { padding: 12px 16px; cursor: pointer; border: 0; border-radius: 12px; font: inherit; }
        button:disabled { cursor: not-allowed; opacity: .55; }
        #send { color: white; background: #111827; }
        #mic { display: grid; width: 48px; height: 48px; padding: 0; place-items: center; color: #3730a3; background: #eef2ff; border: 1px solid #c7d2fe; font-size: 20px; }
        #mic.listening { color: #b91c1c; background: #fee2e2; animation: pulse 1s infinite; }
        #new-conversation { color: #374151; background: #f3f4f6; }
        #status { min-height: 20px; margin-top: 8px; color: #6b7280; font-size: 13px; }
        @keyframes pulse { 50% { transform: scale(1.08); } }
    </style>
</head>
<body>
<main>
    <section class="panel">
        <header>
            <h1>Chat IA - Soluciones Edgar</h1>
            <button id="new-conversation" type="button">Nueva conversación</button>
        </header>

        <div id="messages" aria-live="polite"></div>

        <div class="composer">
            <div class="input-row">
                <button id="mic" type="button" title="Dictar por voz" aria-label="Dictar por voz">🎙️</button>
                <textarea id="prompt" placeholder="Escribe o dicta tu mensaje" rows="2"></textarea>
                <button id="send" type="button">Enviar</button>
            </div>
            <div id="status" role="status"></div>
        </div>
    </section>
</main>

<script>
(() => {
    const messagesEl = document.getElementById('messages');
    const promptEl = document.getElementById('prompt');
    const sendBtn = document.getElementById('send');
    const micBtn = document.getElementById('mic');
    const statusEl = document.getElementById('status');
    const newConversationBtn = document.getElementById('new-conversation');
    const storageKey = 'ai_conversation_id';

    let conversationId = localStorage.getItem(storageKey);
    let recognition = null;
    let listening = false;

    const setStatus = (text) => { statusEl.textContent = text; };

    const addMessage = (type, text = '') => {
        const element = document.createElement('div');
        element.className = `message ${type}`;
        element.textContent = text;
        messagesEl.appendChild(element);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return element;
    };

    const setLoading = (loading) => {
        sendBtn.disabled = loading;
        promptEl.disabled = loading;
    };

    const processEvent = (data, assistantMessage) => {
        if (data.type === 'status') {
            const labels = {
                SEARCHING: 'Buscando información…',
                THINKING: 'Procesando…',
                STREAMING: 'Generando respuesta…',
                COMPLETED: 'Completado',
            };
            setStatus(labels[data.status] || data.status);
            return;
        }

        if (data.type === 'token') {
            assistantMessage.textContent += data.token || '';
            setStatus('Generando respuesta…');
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return;
        }

        if (data.type === 'error') {
            assistantMessage.classList.add('error');
            assistantMessage.textContent = data.error || 'Ocurrió un error al generar la respuesta.';
            setStatus('Error');
            return;
        }

        if (data.type === 'done') {
            if (data.conversation_id) {
                conversationId = data.conversation_id;
                localStorage.setItem(storageKey, conversationId);
            }
            if (data.metadata?.reset_conversation || data.metadata?.conversation_completed) {
                conversationId = null;
                localStorage.removeItem(storageKey);
            }
            setStatus('Completado');
        }
    };

    const sendPrompt = async () => {
        const text = promptEl.value.trim();
        if (!text || sendBtn.disabled) return;

        addMessage('user', text);
        const assistantMessage = addMessage('assistant');
        promptEl.value = '';
        setLoading(true);
        setStatus('Procesando…');

        try {
            const response = await fetch('/ia-test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream, application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    pregunta: text,
                    conversation_id: conversationId,
                }),
            });

            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const data = await response.json();
                assistantMessage.textContent = data.respuesta || data.error || 'No se recibió respuesta.';
                if (data.metadata?.was_blocked) assistantMessage.classList.add('error');
                if (data.conversation_id) {
                    conversationId = data.conversation_id;
                    localStorage.setItem(storageKey, conversationId);
                }
                setStatus(data.metadata?.was_blocked ? 'Solicitud bloqueada' : 'Completado');
                return;
            }

            if (!response.body) throw new Error('El navegador no recibió un stream.');

            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();
                buffer += decoder.decode(value || new Uint8Array(), { stream: !done });

                const events = buffer.split(/\r?\n\r?\n/);
                buffer = events.pop() || '';

                for (const event of events) {
                    const dataLine = event.split(/\r?\n/).find(line => line.startsWith('data:'));
                    if (!dataLine) continue;
                    processEvent(JSON.parse(dataLine.slice(5).trim()), assistantMessage);
                }

                if (done) break;
            }

            if (buffer.trim()) {
                const dataLine = buffer.split(/\r?\n/).find(line => line.startsWith('data:'));
                if (dataLine) processEvent(JSON.parse(dataLine.slice(5).trim()), assistantMessage);
            }
        } catch (error) {
            assistantMessage.classList.add('error');
            assistantMessage.textContent = `Error de conexión: ${error.message}`;
            setStatus('Error');
        } finally {
            setLoading(false);
            promptEl.focus();
        }
    };

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        micBtn.disabled = true;
        micBtn.title = 'Este navegador no soporta reconocimiento de voz.';
        setStatus('El dictado por voz no está disponible en este navegador.');
    } else {
        recognition = new SpeechRecognition();
        recognition.lang = 'es-MX';
        recognition.interimResults = true;
        recognition.continuous = false;

        recognition.onstart = () => {
            listening = true;
            micBtn.classList.add('listening');
            setStatus('Escuchando…');
        };

        recognition.onresult = (event) => {
            let transcript = '';
            for (let index = event.resultIndex; index < event.results.length; index++) {
                transcript += event.results[index][0].transcript;
            }
            promptEl.value = transcript;
            setStatus(event.results[event.results.length - 1].isFinal ? 'Procesando voz…' : 'Escuchando…');
        };

        recognition.onerror = (event) => {
            listening = false;
            micBtn.classList.remove('listening');
            const message = event.error === 'network'
                ? 'No se pudo conectar al reconocimiento de voz del navegador. Prueba en Chrome o Edge, revisa conexión o desactiva bloqueadores de privacidad.'
                : `No fue posible usar el micrófono del navegador: ${event.error}`;
            console.warn('Web Speech API:', event.error, message);
            setStatus(message);
        };

        recognition.onend = () => {
            listening = false;
            micBtn.classList.remove('listening');
            if (promptEl.value.trim()) setStatus('Voz procesada. Puedes editar o enviar el mensaje.');
        };

        micBtn.addEventListener('click', () => {
            if (listening) recognition.stop();
            else recognition.start();
        });
    }

    sendBtn.addEventListener('click', sendPrompt);
    promptEl.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendPrompt();
        }
    });
    newConversationBtn.addEventListener('click', () => {
        conversationId = null;
        localStorage.removeItem(storageKey);
        messagesEl.replaceChildren();
        setStatus('Nueva conversación lista.');
        promptEl.focus();
    });
})();
</script>
</body>
</html>
