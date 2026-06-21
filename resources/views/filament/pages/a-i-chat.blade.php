<x-filament-panels::page>

@push('styles')
<style>
    #gpt-shell {
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 1180px;
        height: clamp(620px, calc(100vh - 150px), 860px);
        min-height: 620px;
        margin: 0 auto;
        background:
            radial-gradient(circle at 8% 0%, rgba(59, 130, 246, .12), transparent 30%),
            linear-gradient(145deg, #111827 0%, #0b1120 65%, #111827 100%);
        font-family: ui-sans-serif, system-ui, sans-serif;
        color: #f8fafc;
        overflow: hidden;
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 24px;
        box-shadow: 0 24px 70px rgba(2, 6, 23, .35);
    }
    #gpt-shell *, #gpt-shell *::before, #gpt-shell *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    .gpt-chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 20px 24px;
        border-bottom: 1px solid rgba(148, 163, 184, .14);
        background: rgba(15, 23, 42, .72);
        backdrop-filter: blur(16px);
    }
    .gpt-chat-heading {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }
    .gpt-header-avatar,
    .gpt-empty-avatar {
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        color: #dbeafe;
        background: linear-gradient(145deg, #2563eb, #7c3aed);
        box-shadow: 0 10px 30px rgba(37, 99, 235, .25);
    }
    .gpt-header-avatar {
        width: 44px;
        height: 44px;
        border-radius: 14px;
    }
    .gpt-header-copy { min-width: 0; }
    .gpt-header-copy h2 {
        color: #f8fafc;
        font-size: 17px;
        font-weight: 700;
        letter-spacing: -.01em;
    }
    .gpt-header-copy p {
        margin-top: 3px;
        color: #94a3b8;
        font-size: 13px;
        line-height: 1.45;
    }
    .gpt-header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 0 0 auto;
    }
    #gpt-status-text {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 30px;
        padding: 6px 11px;
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 999px;
        color: #cbd5e1;
        background: rgba(30, 41, 59, .75);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
        white-space: nowrap;
    }
    #gpt-status-text::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #22c55e;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
    }
    #gpt-status-text[data-state="listening"]::before { background: #ef4444; animation: gptStatusPulse 1s infinite; }
    #gpt-status-text[data-state="processing"]::before,
    #gpt-status-text[data-state="searching"]::before { background: #f59e0b; }
    #gpt-status-text[data-state="streaming"]::before { background: #38bdf8; animation: gptStatusPulse 1s infinite; }
    #gpt-status-text[data-state="error"]::before { background: #f87171; }
    @keyframes gptStatusPulse { 50% { opacity: .35; transform: scale(.75); } }
    #gpt-new-chat {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 36px;
        padding: 8px 13px;
        border: 1px solid rgba(148, 163, 184, .22);
        border-radius: 11px;
        color: #e2e8f0;
        background: rgba(30, 41, 59, .76);
        font-size: 12px;
        font-weight: 650;
        cursor: pointer;
        transition: border-color .16s, background .16s, transform .16s;
    }
    #gpt-new-chat:hover {
        border-color: rgba(96, 165, 250, .65);
        background: rgba(37, 99, 235, .16);
    }
    #gpt-new-chat:active { transform: scale(.97); }
    #gpt-history-toggle {
        display: none;
        width: 38px;
        min-height: 36px;
        border: 1px solid rgba(148, 163, 184, .22);
        border-radius: 11px;
        color: #e2e8f0;
        background: rgba(30, 41, 59, .76);
        cursor: pointer;
    }
    .gpt-workspace {
        position: relative;
        display: flex;
        flex: 1;
        min-height: 0;
    }
    #gpt-history {
        width: 248px;
        flex: 0 0 248px;
        padding: 16px 12px;
        overflow-y: auto;
        border-right: 1px solid rgba(148, 163, 184, .13);
        background: rgba(8, 15, 29, .7);
    }
    .gpt-history-title {
        padding: 3px 8px 10px;
        color: #64748b;
        font-size: 11px;
        font-weight: 750;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .gpt-history-empty {
        padding: 10px 8px;
        color: #64748b;
        font-size: 12px;
        line-height: 1.5;
    }
    .gpt-history-item {
        display: block;
        width: 100%;
        margin-bottom: 5px;
        padding: 10px;
        border: 1px solid transparent;
        border-radius: 12px;
        color: #cbd5e1;
        background: transparent;
        text-align: left;
        cursor: pointer;
        transition: background .15s, border-color .15s;
    }
    .gpt-history-item:hover {
        border-color: rgba(96, 165, 250, .22);
        background: rgba(30, 41, 59, .72);
    }
    .gpt-history-item.is-active {
        border-color: rgba(96, 165, 250, .42);
        background: rgba(37, 99, 235, .14);
    }
    .gpt-history-item strong {
        display: block;
        overflow: hidden;
        color: #e2e8f0;
        font-size: 12px;
        font-weight: 650;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .gpt-history-item span {
        display: block;
        margin-top: 4px;
        overflow: hidden;
        color: #64748b;
        font-size: 10px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .gpt-chat-main {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-width: 0;
        min-height: 0;
    }
    #gpt-messages {
        flex: 1;
        overflow-y: auto;
        min-height: 0;
        padding: 28px 24px;
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
        width: min(680px, 100%);
        min-height: 100%;
        margin: 0 auto;
        padding: 34px 20px;
        gap: 14px;
        text-align: center;
        animation: gptFadeUp 0.5s ease both;
    }
    .gpt-empty-avatar {
        width: 64px;
        height: 64px;
        border-radius: 21px;
        margin-bottom: 4px;
        font-size: 25px;
    }
    #gpt-empty h1 {
        font-size: clamp(23px, 3vw, 31px);
        font-weight: 720;
        color: #f8fafc;
        letter-spacing: -0.02em;
    }
    #gpt-empty p {
        max-width: 520px;
        color: #94a3b8;
        font-size: 14px;
        line-height: 1.65;
    }
    .gpt-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 9px;
        justify-content: center;
        max-width: 620px;
        margin-top: 12px;
    }
    .gpt-chip {
        padding: 9px 14px;
        border-radius: 999px;
        border: 1px solid rgba(96, 165, 250, .22);
        background: rgba(30, 41, 59, .72);
        color: #cbd5e1;
        font-size: 13px;
        cursor: pointer;
        transition: border-color .15s, color .15s, background .15s, transform .15s;
        line-height: 1.4;
    }
    .gpt-chip:hover {
        border-color: #60a5fa;
        color: #fff;
        background: rgba(37, 99, 235, .2);
        transform: translateY(-1px);
    }
    .gpt-row {
        width: 100%;
        padding: 7px 0;
        animation: gptFadeUp 0.2s ease both;
    }
    @keyframes gptFadeUp {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .gpt-row-inner {
        max-width: 820px;
        margin: 0 auto;
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }
    .gpt-row.user .gpt-row-inner { flex-direction: row-reverse; }
    .gpt-avatar {
        width: 34px;
        height: 34px;
        border-radius: 11px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
    }
    .gpt-avatar.user { background: #334155; color: #f8fafc; }
    .gpt-avatar.ai { background: linear-gradient(145deg, #2563eb, #7c3aed); color: #fff; font-size: 14px; }
    .gpt-message-stack {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        max-width: min(78%, 680px);
    }
    .gpt-row.user .gpt-message-stack { align-items: flex-end; }
    .gpt-sender {
        padding: 0 4px;
        margin-bottom: 5px;
        font-size: 11px;
        font-weight: 650;
        color: #94a3b8;
    }
    .gpt-msg {
        padding: 12px 15px;
        border: 1px solid rgba(148, 163, 184, .14);
        border-radius: 7px 18px 18px 18px;
        background: rgba(30, 41, 59, .78);
        box-shadow: 0 8px 28px rgba(2, 6, 23, .12);
        font-size: 14px;
        line-height: 1.65;
        color: #e5e7eb;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .gpt-row.user .gpt-msg {
        border-color: rgba(59, 130, 246, .3);
        border-radius: 18px 7px 18px 18px;
        background: linear-gradient(145deg, #2563eb, #1d4ed8);
        color: #fff;
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
        position: sticky;
        bottom: 0;
        flex: 0 0 auto;
        padding: 14px 24px 18px;
        border-top: 1px solid rgba(148, 163, 184, .13);
        background: rgba(11, 17, 32, .92);
        backdrop-filter: blur(18px);
    }
    .gpt-input-wrap {
        max-width: 820px;
        margin: 0 auto;
        background: rgba(30, 41, 59, .82);
        border: 1px solid rgba(148, 163, 184, .23);
        border-radius: 17px;
        display: flex;
        align-items: center;
        padding: 9px;
        gap: 9px;
        transition: border-color .2s, box-shadow .2s;
    }
    .gpt-input-wrap:focus-within {
        border-color: rgba(96, 165, 250, .72);
        box-shadow: 0 0 0 4px rgba(59, 130, 246, .1);
    }
    #gpt-prompt {
        flex: 1;
        min-width: 0;
        padding: 5px 3px;
        background: transparent;
        border: none;
        outline: none;
        color: #f8fafc;
        font-size: 14px;
        line-height: 1.5;
        resize: none;
        min-height: 24px;
        max-height: 180px;
        overflow-y: auto;
        font-family: inherit;
    }
    #gpt-prompt::placeholder { color: #64748b; }
    #gpt-mic {
        width: 38px;
        height: 38px;
        padding: 0;
        border: 1px solid rgba(148, 163, 184, .22);
        border-radius: 12px;
        background: rgba(51, 65, 85, .78);
        color: #e2e8f0;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 38px;
        visibility: visible;
        opacity: 1;
        transition: color 0.15s, background 0.15s, border-color 0.15s, transform 0.1s;
    }
    #gpt-mic:hover:not(:disabled) {
        background: #475569;
        border-color: #64748b;
    }
    #gpt-mic:active:not(:disabled) { transform: scale(0.94); }
    #gpt-mic svg {
        display: block;
        width: 20px;
        height: 20px;
        pointer-events: none;
    }
    #gpt-mic.is-listening {
        color: #fff;
        background: #dc2626;
        border-color: #f87171;
        animation: gpt-mic-pulse 1s ease-in-out infinite;
    }
    #gpt-mic.is-processing {
        color: #111827;
        background: #fbbf24;
        border-color: #fcd34d;
    }
    #gpt-mic.is-unavailable,
    #gpt-mic:disabled {
        color: #9ca3af;
        background: #292929;
        border-color: #444;
        cursor: not-allowed;
        opacity: 0.8;
    }
    @keyframes gpt-mic-pulse {
        50% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0.2); }
    }
    #gpt-send {
        width: 38px; height: 38px;
        border-radius: 12px;
        background: #3b82f6;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: background 0.15s, transform 0.1s, opacity 0.15s;
    }
    #gpt-send:hover:not(:disabled) { background: #60a5fa; }
    #gpt-send:active:not(:disabled) { transform: scale(0.93); }
    #gpt-send:disabled { opacity: 0.35; cursor: not-allowed; }
    #gpt-send svg { width: 17px; height: 17px; }
    #gpt-error {
        display: none;
        max-width: 820px;
        margin: 0 auto 10px;
        padding: 10px 13px;
        background: rgba(127, 29, 29, .34);
        border: 1px solid rgba(248, 113, 113, .45);
        border-radius: 12px;
        font-size: 13px;
        color: #fecaca;
    }
    .gpt-footer-note {
        text-align: center;
        font-size: 11px;
        color: #64748b;
        max-width: 820px;
        margin: 8px auto 0;
    }
    @media (max-width: 768px) {
        #gpt-shell {
            height: calc(100dvh - 118px);
            min-height: 540px;
            border-radius: 18px;
        }
        .gpt-chat-header { padding: 14px 15px; align-items: flex-start; }
        .gpt-header-avatar { width: 39px; height: 39px; border-radius: 12px; }
        .gpt-header-copy p { display: none; }
        .gpt-header-actions { gap: 7px; }
        #gpt-status-text { padding: 5px 8px; min-height: 34px; }
        #gpt-new-chat { width: 38px; min-height: 34px; padding: 7px; justify-content: center; }
        #gpt-new-chat span { display: none; }
        #gpt-history-toggle { display: inline-grid; place-items: center; }
        #gpt-history {
            position: absolute;
            z-index: 20;
            top: 0;
            bottom: 0;
            left: 0;
            width: min(82vw, 290px);
            transform: translateX(-105%);
            box-shadow: 18px 0 45px rgba(2, 6, 23, .45);
            transition: transform .2s ease;
        }
        #gpt-history.is-open { transform: translateX(0); }
        #gpt-messages { padding: 20px 13px; }
        .gpt-message-stack { max-width: calc(100% - 48px); }
        .gpt-msg { font-size: 13.5px; padding: 11px 13px; }
        #gpt-input-bar { padding: 11px 12px 13px; }
        .gpt-input-wrap { border-radius: 15px; }
        .gpt-footer-note { display: none; }
    }
    @media (max-width: 480px) {
        #gpt-shell { min-height: 500px; border-radius: 14px; }
        .gpt-chat-heading { gap: 9px; }
        .gpt-header-copy h2 { font-size: 14px; }
        #gpt-status-text { font-size: 0; width: 32px; justify-content: center; padding: 0; }
        #gpt-status-text::before { width: 8px; height: 8px; }
        .gpt-avatar { width: 30px; height: 30px; border-radius: 9px; }
        .gpt-message-stack { max-width: calc(100% - 40px); }
        #gpt-mic, #gpt-send { width: 36px; height: 36px; flex-basis: 36px; }
    }
</style>
@endpush

{{-- Único elemento raíz que ve Livewire --}}
<div id="gpt-shell">
    <header class="gpt-chat-header">
        <div class="gpt-chat-heading">
            <div class="gpt-header-avatar" aria-hidden="true">✦</div>
            <div class="gpt-header-copy">
                <h2>Asistente de trámites</h2>
                <p>Consulta, captura datos y genera solicitudes desde el chat.</p>
            </div>
        </div>
        <div class="gpt-header-actions">
            <div id="gpt-status-text" data-state="ready" role="status" aria-live="polite">Listo</div>
            <button id="gpt-history-toggle" type="button" aria-label="Mostrar historial de conversaciones" aria-expanded="false">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
                    <path d="M3 3v5h5M12 7v5l3 2"/>
                </svg>
            </button>
            <button id="gpt-new-chat" type="button" aria-label="Iniciar un nuevo chat">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                <span>Nuevo chat</span>
            </button>
        </div>
    </header>

    <div class="gpt-workspace">
        <aside id="gpt-history" aria-label="Historial de conversaciones">
            <div class="gpt-history-title">Recientes</div>
            <div id="gpt-history-list">
                <div class="gpt-history-empty">Cargando conversaciones…</div>
            </div>
        </aside>

        <main class="gpt-chat-main">
            <div id="gpt-messages">
                <div id="gpt-empty">
                    <div class="gpt-empty-avatar" aria-hidden="true">✦</div>
                    <h1>¿Qué trámite necesitas realizar?</h1>
                    <p>Puedo ayudarte a identificar el servicio, capturar los datos requeridos y generar una solicitud.</p>
                    <div class="gpt-chips">
                        <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito tramitar una CURP">CURP</button>
                        <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito un acta de nacimiento">Acta de nacimiento</button>
                        <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito un trámite de RFC">RFC</button>
                        <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito un trámite de NSS">NSS</button>
                        <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito una constancia fiscal">Constancia fiscal</button>
                    </div>
                </div>
            </div>

            <div id="gpt-input-bar">
                <div id="gpt-error"></div>
                <div class="gpt-input-wrap">
                    <button id="gpt-mic" type="button" title="Dictar por voz" aria-label="Dictar por voz" aria-pressed="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/>
                            <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                            <line x1="12" y1="19" x2="12" y2="22"/>
                        </svg>
                    </button>
                    <textarea id="gpt-prompt" placeholder="Escribe tu mensaje…" rows="1" aria-label="Mensaje para el asistente"></textarea>
                    <button id="gpt-send" type="button" onclick="gptSend()" title="Enviar" aria-label="Enviar mensaje">
                        <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 13V3M8 3L3.5 7.5M8 3L12.5 7.5" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <p class="gpt-footer-note">El asistente puede cometer errores. Verifica la información importante.</p>
            </div>
        </div>
        </main>
    </div>

    @push('scripts')
    <script>
    (function () {
        const messagesEl = document.getElementById('gpt-messages');
        const promptEl   = document.getElementById('gpt-prompt');
        const sendBtn    = document.getElementById('gpt-send');
        const errorEl    = document.getElementById('gpt-error');
        const statusEl   = document.getElementById('gpt-status-text');
        const micBtn     = document.getElementById('gpt-mic');
        const newChatBtn = document.getElementById('gpt-new-chat');
        const historyEl  = document.getElementById('gpt-history');
        const historyListEl = document.getElementById('gpt-history-list');
        const historyToggleBtn = document.getElementById('gpt-history-toggle');

        let loading = false;
        let conversationId = localStorage.getItem('ai_conversation_id') || null;
        let forceNewConversation = false;

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

        function setStatus(text) {
            const normalized = String(text || '').toUpperCase();
            const states = {
                SEARCHING: ['Buscando', 'searching'],
                THINKING: ['Pensando', 'processing'],
                PROCESSING: ['Procesando', 'processing'],
                'PROCESANDO VOZ...': ['Procesando', 'processing'],
                'ESCUCHANDO...': ['Escuchando', 'listening'],
                STREAMING: ['Transmitiendo', 'streaming'],
                'STREAMING...': ['Transmitiendo', 'streaming'],
                COMPLETED: ['Listo', 'ready'],
                'NUEVO CHAT LISTO': ['Listo', 'ready'],
                'VOZ LISTA PARA ENVIAR': ['Listo', 'ready'],
                'MICRÓFONO DISPONIBLE': ['Listo', 'ready'],
                ERROR: ['Error', 'error'],
            };
            const state = states[normalized] || [text || 'Listo', normalized.includes('ERROR') ? 'error' : 'ready'];
            statusEl.textContent = state[0];
            statusEl.dataset.state = state[1];
        }

        function createRow(type) {
            clearEmpty();
            const row = document.createElement('div');
            row.className = 'gpt-row ' + type;
            const inner = document.createElement('div');
            inner.className = 'gpt-row-inner';
            const avatar = document.createElement('div');
            avatar.className = 'gpt-avatar ' + type;
            avatar.textContent = type === 'user' ? 'Tú' : '✦';
            avatar.setAttribute('aria-hidden', 'true');
            const right = document.createElement('div');
            right.className = 'gpt-message-stack';
            const sender = document.createElement('div');
            sender.className = 'gpt-sender';
            sender.textContent = type === 'user' ? 'Tú' : 'Asistente';
            const msg = document.createElement('div');
            msg.className = 'gpt-msg';
            
            right.appendChild(sender);
            right.appendChild(msg);
            inner.appendChild(avatar);
            inner.appendChild(right);
            row.appendChild(inner);
            messagesEl.appendChild(row);
            return msg;
        }

        function addRow(type, text) {
            const msg = createRow(type);
            msg.textContent = text;
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function emptyStateMarkup() {
            return `
                <div class="gpt-empty-avatar" aria-hidden="true">✦</div>
                <h1>¿Qué trámite necesitas realizar?</h1>
                <p>Puedo ayudarte a identificar el servicio, capturar los datos requeridos y generar una solicitud.</p>
                <div class="gpt-chips">
                    <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito tramitar una CURP">CURP</button>
                    <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito un acta de nacimiento">Acta de nacimiento</button>
                    <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito un trámite de RFC">RFC</button>
                    <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito un trámite de NSS">NSS</button>
                    <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito una constancia fiscal">Constancia fiscal</button>
                </div>`;
        }

        function showEmptyState() {
            messagesEl.replaceChildren();
            const empty = document.createElement('div');
            empty.id = 'gpt-empty';
            empty.innerHTML = emptyStateMarkup();
            messagesEl.appendChild(empty);
        }

        function formatHistoryDate(value) {
            if (!value) return '';
            const date = new Date(value);
            return new Intl.DateTimeFormat('es-MX', {
                day: '2-digit',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date);
        }

        async function loadHistory() {
            try {
                const response = await fetch('/ia-conversations', {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('No se pudo cargar el historial');
                const data = await response.json();
                historyListEl.replaceChildren();

                if (!data.conversations.length) {
                    historyListEl.innerHTML = '<div class="gpt-history-empty">Tus conversaciones aparecerán aquí.</div>';
                    return;
                }

                data.conversations.forEach((conversation) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'gpt-history-item' + (
                        conversation.conversation_id === conversationId ? ' is-active' : ''
                    );
                    button.dataset.conversationId = conversation.conversation_id;
                    const title = document.createElement('strong');
                    title.textContent = conversation.title || 'Nueva conversación';
                    const meta = document.createElement('span');
                    meta.textContent = conversation.last_message || formatHistoryDate(conversation.updated_at);
                    button.append(title, meta);
                    button.addEventListener('click', () => loadConversation(conversation.conversation_id));
                    historyListEl.appendChild(button);
                });
            } catch (error) {
                historyListEl.innerHTML = '<div class="gpt-history-empty">No fue posible cargar el historial.</div>';
                console.warn('Historial del chat:', error);
            }
        }

        async function loadConversation(id) {
            if (loading) return;
            setStatus('PROCESSING');
            try {
                const response = await fetch('/ia-conversations/' + encodeURIComponent(id), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('No se pudo abrir la conversación');
                const data = await response.json();
                conversationId = data.conversation_id;
                forceNewConversation = false;
                localStorage.setItem('ai_conversation_id', conversationId);
                messagesEl.replaceChildren();
                data.messages.forEach((message) => {
                    addRow(message.role === 'user' ? 'user' : 'ai', message.content);
                });
                if (!data.messages.length) showEmptyState();
                historyEl.classList.remove('is-open');
                historyToggleBtn.setAttribute('aria-expanded', 'false');
                await loadHistory();
                setStatus('COMPLETED');
                promptEl.focus();
            } catch (error) {
                setStatus('ERROR');
                showError(error.message);
            }
        }

        function showError(msg) {
            errorEl.textContent = '⚠ ' + msg;
            errorEl.style.display = 'block';
            setTimeout(() => { errorEl.style.display = 'none'; }, 6000);
        }

        function setLoading(val) {
            loading = val;
            sendBtn.disabled = val;
            promptEl.disabled = val;
            if (!val && statusEl.dataset.state !== 'error') setStatus('COMPLETED');
        }

        let recognition;
        let isRecording = false;
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        window.toggleMic = function() {
            if (isRecording) {
                if (recognition) recognition.stop();
                return;
            }

            if (!SpeechRecognition) {
                showError('Tu navegador no soporta dictado por voz.');
                setStatus('MICRÓFONO NO DISPONIBLE');
                return;
            }

            recognition = new SpeechRecognition();
            recognition.lang = 'es-MX';
            recognition.interimResults = true;
            recognition.continuous = false;

            recognition.onstart = function() {
                isRecording = true;
                micBtn.classList.remove('is-processing');
                micBtn.classList.add('is-listening');
                micBtn.setAttribute('aria-pressed', 'true');
                micBtn.title = 'Detener dictado';
                setStatus('ESCUCHANDO...');
            };

            recognition.onresult = function(event) {
                let interimTranscript = '';
                let finalTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript;
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }
                
                const transcript = finalTranscript || interimTranscript;
                if (transcript) promptEl.value = transcript;

                if (finalTranscript) {
                    micBtn.classList.remove('is-listening');
                    micBtn.classList.add('is-processing');
                    setStatus('PROCESANDO VOZ...');
                    promptEl.dispatchEvent(new Event('input', { bubbles: true }));
                }
            };

            recognition.onerror = function(event) {
                const message = event.error === 'network'
                    ? 'No se pudo conectar al reconocimiento de voz del navegador. Prueba en Chrome o Edge, revisa conexión o desactiva bloqueadores de privacidad.'
                    : 'No fue posible usar el micrófono del navegador: ' + event.error;
                console.warn('Web Speech API:', event.error, message);
                showError(message);
                isRecording = false;
                micBtn.classList.remove('is-listening', 'is-processing');
                micBtn.setAttribute('aria-pressed', 'false');
                micBtn.title = 'Dictar por voz';
                setStatus('MICRÓFONO DISPONIBLE');
            };

            recognition.onend = function() {
                isRecording = false;
                micBtn.classList.remove('is-listening', 'is-processing');
                micBtn.setAttribute('aria-pressed', 'false');
                micBtn.title = 'Dictar por voz';
                setStatus(promptEl.value.trim() ? 'VOZ LISTA PARA ENVIAR' : 'MICRÓFONO DISPONIBLE');
                promptEl.focus();
            };

            recognition.start();
        };

        micBtn.addEventListener('click', window.toggleMic);

        if (SpeechRecognition) {
            micBtn.classList.remove('is-unavailable');
            micBtn.disabled = false;
            setStatus('MICRÓFONO DISPONIBLE');
        } else {
            micBtn.classList.add('is-unavailable');
            micBtn.disabled = true;
            micBtn.title = 'Este navegador no soporta reconocimiento de voz';
            setStatus('MICRÓFONO NO DISPONIBLE EN ESTE NAVEGADOR');
        }

        window.gptSend = async function () {
            const text = promptEl.value.trim();
            if (!text || loading) return;

            errorEl.style.display = 'none';
            addRow('user', text);
            promptEl.value = '';
            promptEl.style.height = 'auto';

            setLoading(true);
            setStatus('THINKING...');
            
            let currentMsgEl = createRow('ai');
            currentMsgEl.innerHTML = '<span class="typing-dots" style="display:inline-block"><span></span><span></span><span></span></span>';

            try {
                const response = await fetch('/ia-test', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        pregunta: text,
                        conversation_id: conversationId,
                        new_conversation: forceNewConversation,
                    })
                });
                forceNewConversation = false;

                if (!response.ok) throw new Error('Error del servidor (' + response.status + ')');

                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const data = await response.json();
                    setStatus('ERROR');
                    currentMsgEl.textContent = data.respuesta || 'Error al procesar la respuesta.';
                    if (data.metadata && data.metadata.was_blocked) {
                        showError('Bloqueado por seguridad.');
                    }
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder("utf-8");
                let responseText = '';
                currentMsgEl.textContent = ''; // Limpiar los dots

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
                                if (data.type === 'status') {
                                    setStatus(data.status);
                                } else if (data.type === 'token') {
                                    setStatus('STREAMING...');
                                    responseText += data.token;
                                    currentMsgEl.textContent = responseText;
                                    messagesEl.scrollTop = messagesEl.scrollHeight;
                                } else if (data.type === 'error') {
                                    setStatus('ERROR');
                                    showError(data.error);
                                } else if (data.type === 'done') {
                                    setStatus('COMPLETED');
                                    if (data.conversation_id) {
                                        conversationId = data.conversation_id;
                                        localStorage.setItem('ai_conversation_id', conversationId);
                                    }
                                    if (data.metadata && (data.metadata.reset_conversation || data.metadata.conversation_completed)) {
                                        conversationId = null;
                                        localStorage.removeItem('ai_conversation_id');
                                    }
                                    loadHistory();
                                }
                            } catch (e) {
                                console.error("Error parseando SSE chunk", dataStr, e);
                            }
                        }
                    }
                }
            } catch (err) {
                setStatus('ERROR');
                currentMsgEl.textContent = 'Hubo un error de conexión.';
                showError(err.message || 'No se pudo conectar.');
            } finally {
                setLoading(false);
                promptEl.focus();
            }
        };

        window.useChip = function (btn) {
            promptEl.value = btn.dataset.prompt || btn.textContent;
            promptEl.dispatchEvent(new Event('input'));
            promptEl.focus();
        };

        newChatBtn.addEventListener('click', function () {
            conversationId = null;
            forceNewConversation = true;
            localStorage.removeItem('ai_conversation_id');
            showEmptyState();
            errorEl.style.display = 'none';
            setStatus('NUEVO CHAT LISTO');
            promptEl.value = '';
            historyEl.classList.remove('is-open');
            historyToggleBtn.setAttribute('aria-expanded', 'false');
            loadHistory();
            promptEl.focus();
        });

        historyToggleBtn.addEventListener('click', function () {
            const isOpen = historyEl.classList.toggle('is-open');
            historyToggleBtn.setAttribute('aria-expanded', String(isOpen));
        });

        loadHistory().then(() => {
            if (conversationId) loadConversation(conversationId);
        });
        promptEl.focus();
    })();
    </script>
    @endpush

</div>

</x-filament-panels::page>
