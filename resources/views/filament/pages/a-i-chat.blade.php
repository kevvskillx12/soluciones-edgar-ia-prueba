<x-filament-panels::page>

@push('styles')
<style>
    #gpt-shell {
        position: relative;
        display: flex;
        flex-direction: column;
        width: 100%;
        min-height: calc(100vh - 80px);
        background: #212121;
        font-family: ui-sans-serif, system-ui, sans-serif;
        color: #ececec;
        overflow: hidden;
        border-radius: 12px;
    }
    #gpt-shell *, #gpt-shell *::before, #gpt-shell *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    #gpt-messages {
        flex: 1;
        overflow-y: auto;
        padding: 48px 0 160px;
        scroll-behavior: smooth;
    }
    #gpt-messages::-webkit-scrollbar { width: 6px; }
    #gpt-messages::-webkit-scrollbar-track { background: transparent; }
    #gpt-messages::-webkit-scrollbar-thumb { background: #3f3f3f; border-radius: 3px; }
    #gpt-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        min-height: 300px;
        gap: 16px;
        animation: gptFadeUp 0.5s ease both;
    }
    #gpt-empty h1 {
        font-size: 26px;
        font-weight: 500;
        color: #ececec;
        letter-spacing: -0.02em;
    }
    .gpt-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
        max-width: 560px;
        margin-top: 8px;
    }
    .gpt-chip {
        padding: 10px 16px;
        border-radius: 20px;
        border: 1px solid #3f3f3f;
        background: #2f2f2f;
        color: #b4b4b4;
        font-size: 13px;
        cursor: pointer;
        transition: border-color 0.15s, color 0.15s, background 0.15s;
        line-height: 1.4;
    }
    .gpt-chip:hover {
        border-color: #6b6b6b;
        color: #ececec;
        background: #3a3a3a;
    }
    .gpt-row {
        width: 100%;
        padding: 10px 0;
        animation: gptFadeUp 0.2s ease both;
    }
    @keyframes gptFadeUp {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .gpt-row-inner {
        max-width: 680px;
        margin: 0 auto;
        padding: 0 24px;
        display: flex;
        gap: 16px;
        align-items: flex-start;
    }
    .gpt-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        margin-top: 2px;
    }
    .gpt-avatar.user { background: #19c37d; color: #000; }
    .gpt-avatar.ai   { background: #000; border: 1px solid #3f3f3f; font-size: 14px; }
    .gpt-sender {
        font-size: 13px;
        font-weight: 600;
        color: #ececec;
        margin-bottom: 4px;
    }
    .gpt-msg {
        flex: 1;
        font-size: 15px;
        line-height: 1.7;
        color: #ececec;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .typing-dots { display: flex; gap: 5px; padding-top: 4px; }
    .typing-dots span {
        width: 7px; height: 7px;
        border-radius: 50%;
        background: #6b6b6b;
        animation: gptBlink 1.2s infinite;
    }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes gptBlink {
        0%, 60%, 100% { opacity: 0.3; transform: scale(1); }
        30%            { opacity: 1;   transform: scale(1.2); }
    }
    #gpt-input-bar {
        position: absolute;
        bottom: 0; left: 0; right: 0;
        padding: 16px 24px 24px;
        background: linear-gradient(to top, #212121 70%, transparent);
    }
    .gpt-input-wrap {
        max-width: 680px;
        margin: 0 auto;
        background: #2f2f2f;
        border: 1px solid #3f3f3f;
        border-radius: 16px;
        display: flex;
        align-items: flex-end;
        padding: 10px 12px 10px 16px;
        gap: 8px;
        transition: border-color 0.2s;
    }
    .gpt-input-wrap:focus-within { border-color: #6b6b6b; }
    #gpt-prompt {
        flex: 1;
        background: transparent;
        border: none;
        outline: none;
        color: #ececec;
        font-size: 15px;
        line-height: 1.6;
        resize: none;
        min-height: 24px;
        max-height: 180px;
        overflow-y: auto;
        font-family: inherit;
    }
    #gpt-prompt::placeholder { color: #6b6b6b; }
    #gpt-send {
        width: 34px; height: 34px;
        border-radius: 10px;
        background: #ececec;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: background 0.15s, transform 0.1s, opacity 0.15s;
    }
    #gpt-send:hover:not(:disabled) { background: #fff; }
    #gpt-send:active:not(:disabled) { transform: scale(0.93); }
    #gpt-send:disabled { opacity: 0.35; cursor: not-allowed; }
    #gpt-send svg { width: 16px; height: 16px; }
    #gpt-error {
        display: none;
        max-width: 680px;
        margin: 0 auto 10px;
        padding: 10px 16px;
        background: #3b1f1f;
        border: 1px solid #6b3030;
        border-radius: 10px;
        font-size: 13px;
        color: #f87171;
    }
    .gpt-footer-note {
        text-align: center;
        font-size: 11px;
        color: #4b4b4b;
        max-width: 680px;
        margin: 10px auto 0;
    }
</style>
@endpush

{{-- Único elemento raíz que ve Livewire --}}
<div id="gpt-shell">

    <div id="gpt-messages">
        <div id="gpt-empty">
            <h1>¿Con qué puedo ayudarte?</h1>
            <div class="gpt-chips">
                <button class="gpt-chip" onclick="useChip(this)">Explícame cómo funciona…</button>
                <button class="gpt-chip" onclick="useChip(this)">Resume este texto…</button>
                <button class="gpt-chip" onclick="useChip(this)">Dame ideas para…</button>
                <button class="gpt-chip" onclick="useChip(this)">Escríbeme un correo sobre…</button>
            </div>
        </div>
    </div>

    <div id="gpt-input-bar">
        <div id="gpt-error"></div>
        <div class="gpt-input-wrap">
            <textarea id="gpt-prompt" placeholder="Pregunta lo que quieras" rows="1"></textarea>
            <button id="gpt-send" onclick="gptSend()" title="Enviar">
                <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 13V3M8 3L3.5 7.5M8 3L12.5 7.5" stroke="#212121" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <p class="gpt-footer-note">El asistente puede cometer errores. Verifica la información importante.</p>
    </div>

    @push('scripts')
    <script>
    (function () {
        const messagesEl = document.getElementById('gpt-messages');
        const promptEl   = document.getElementById('gpt-prompt');
        const sendBtn    = document.getElementById('gpt-send');
        const errorEl    = document.getElementById('gpt-error');

        let loading = false;

        promptEl.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 180) + 'px';
        });

        promptEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                gptSend();
            }
        });

        function clearEmpty() {
            const el = document.getElementById('gpt-empty');
            if (el) el.remove();
        }

        function addRow(type, text) {
            clearEmpty();

            const row    = document.createElement('div');
            row.className = 'gpt-row ' + type;

            const inner  = document.createElement('div');
            inner.className = 'gpt-row-inner';

            const avatar = document.createElement('div');
            avatar.className = 'gpt-avatar ' + type;
            avatar.textContent = type === 'user' ? 'Tú' : '✦';

            const right  = document.createElement('div');
            right.style.flex = '1';

            const sender = document.createElement('div');
            sender.className = 'gpt-sender';
            sender.textContent = type === 'user' ? 'Tú' : 'Asistente';

            const msg    = document.createElement('div');
            msg.className = 'gpt-msg';
            msg.textContent = text;

            right.appendChild(sender);
            right.appendChild(msg);
            inner.appendChild(avatar);
            inner.appendChild(right);
            row.appendChild(inner);
            messagesEl.appendChild(row);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function addTyping() {
            clearEmpty();
            const row    = document.createElement('div');
            row.className = 'gpt-row ai';
            row.id       = 'gpt-typing';
            row.innerHTML = '<div class="gpt-row-inner"><div class="gpt-avatar ai">✦</div><div style="flex:1"><div class="gpt-sender">Asistente</div><div class="typing-dots"><span></span><span></span><span></span></div></div></div>';
            messagesEl.appendChild(row);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function removeTyping() {
            const el = document.getElementById('gpt-typing');
            if (el) el.remove();
        }

        function showError(msg) {
            errorEl.textContent = '⚠ ' + msg;
            errorEl.style.display = 'block';
            setTimeout(() => { errorEl.style.display = 'none'; }, 6000);
        }

        function setLoading(val) {
            loading          = val;
            sendBtn.disabled = val;
            promptEl.disabled = val;
        }

        window.gptSend = async function () {
            const text = promptEl.value.trim();
            if (!text || loading) return;

            errorEl.style.display = 'none';
            addRow('user', text);
            promptEl.value      = '';
            promptEl.style.height = 'auto';

            setLoading(true);
            addTyping();

            try {
                const res = await fetch('/ia-test', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ pregunta: text })
                });

                removeTyping();

                if (!res.ok) throw new Error('Error del servidor (' + res.status + ')');

                const data = await res.json();

                if (data.respuesta) {
                    addRow('ai', data.respuesta);
                } else {
                    showError('La respuesta llegó vacía. Intenta de nuevo.');
                }
            } catch (err) {
                removeTyping();
                showError(err.message || 'No se pudo conectar.');
            } finally {
                setLoading(false);
                promptEl.focus();
            }
        };

        window.useChip = function (btn) {
            promptEl.value = btn.textContent;
            promptEl.dispatchEvent(new Event('input'));
            promptEl.focus();
        };

        promptEl.focus();
    })();
    </script>
    @endpush

</div>

</x-filament-panels::page>