<x-filament-panels::page>

@push('styles')
<style>
    #gpt-shell {
        /* ---- Light mode tokens: alineados a la paleta Filament (gray + primary) ---- */
        --bg-base: #f9fafb;
        --bg-panel: #ffffff;
        --bg-elevated: #f9fafb;
        --bg-elevated-2: #f3f4f6;
        --border: #e5e7eb;
        --border-soft: #f3f4f6;
        --text-primary: #111827;
        --text-secondary: #4b5563;
        --text-muted: #6b7280;

        /* Único acento de marca: el primary configurado en el panel (Indigo) */
        --accent: rgb(var(--primary-600));
        --accent-strong: rgb(var(--primary-500));
        --accent-soft: rgb(var(--primary-500) / .08);
        --accent-ring: rgb(var(--primary-600) / .18);

        /* Sólo dos semánticos adicionales, usados con mucha mesura */
        --success: rgb(var(--success-600));
        --success-soft: rgb(var(--success-500) / .10);
        --danger: rgb(var(--danger-600));
        --danger-soft: rgb(var(--danger-500) / .10);

        --ring-soft: rgb(var(--gray-950) / .06);

        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: none;
        height: calc(100vh - 170px);
        min-height: 560px;
        margin: 0 auto;
        background: var(--bg-base);
        font-family: inherit;
        font-size: 14px;
        color: var(--text-primary);
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 1px 3px rgb(0 0 0 / .06), 0 1px 2px rgb(0 0 0 / .04);
    }
    .dark #gpt-shell {
        --bg-base: rgb(var(--gray-950));
        --bg-panel: rgb(var(--gray-900));
        --bg-elevated: rgb(var(--gray-900));
        --bg-elevated-2: rgb(var(--gray-800));
        --border: rgb(var(--gray-700));
        --border-soft: rgb(var(--gray-800));
        --text-primary: #f9fafb;
        --text-secondary: #d1d5db;
        --text-muted: #9ca3af;

        --accent: rgb(var(--primary-600));
        --accent-strong: rgb(var(--primary-400));
        --accent-soft: rgb(var(--primary-500) / .14);
        --accent-ring: rgb(var(--primary-400) / .22);

        --success: rgb(var(--success-400));
        --success-soft: rgb(var(--success-500) / .14);
        --danger: rgb(var(--danger-400));
        --danger-soft: rgb(var(--danger-500) / .14);

        --ring-soft: rgb(255 255 255 / .08);
        box-shadow: 0 1px 2px rgb(0 0 0 / .35);
    }
    #gpt-shell *, #gpt-shell *::before, #gpt-shell *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    #gpt-shell :focus-visible {
        outline: 2px solid var(--accent-strong);
        outline-offset: 2px;
    }
    .gpt-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

    /* ---------- Header ---------- */
    .gpt-chat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 18px 28px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-panel);
    }
    .gpt-chat-heading {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }
    .gpt-header-avatar {
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        color: var(--accent-strong);
        background: var(--accent-soft);
        border: 1px solid var(--accent-ring);
        font-size: 17px;
    }
    .gpt-header-copy { min-width: 0; }
    .gpt-header-copy h2 {
        color: var(--text-primary);
        font-size: 15.5px;
        font-weight: 700;
        letter-spacing: -.01em;
        line-height: 1.3;
    }
    .gpt-header-copy p {
        margin-top: 3px;
        color: var(--text-muted);
        font-size: 12.5px;
        line-height: 1.5;
    }
    .gpt-header-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 0 0 auto;
    }
    #gpt-status-text {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 36px;
        padding: 7px 14px;
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-secondary);
        background: var(--bg-elevated);
        font-size: 12px;
        font-weight: 600;
        letter-spacing: .02em;
        white-space: nowrap;
    }
    /* Sólo 3 colores de estado en toda la pieza: gris (neutro), primary (trabajando), success (listo) y danger (error) */
    #gpt-status-text::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: var(--success);
        flex-shrink: 0;
    }
    #gpt-status-text[data-state="processing"]::before,
    #gpt-status-text[data-state="searching"]::before,
    #gpt-status-text[data-state="streaming"]::before { background: var(--accent-strong); }
    #gpt-status-text[data-state="streaming"]::before,
    #gpt-status-text[data-state="listening"]::before { animation: gptPulse 1s infinite; }
    #gpt-status-text[data-state="listening"]::before,
    #gpt-status-text[data-state="error"]::before { background: var(--danger); }
    @keyframes gptPulse { 50% { opacity: .35; transform: scale(.7); } }

    .gpt-icon-btn {
        display: inline-grid;
        place-items: center;
        width: 38px;
        height: 36px;
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-secondary);
        background: var(--bg-elevated);
        cursor: pointer;
        transition: border-color .15s, color .15s, background .15s;
    }
    .gpt-icon-btn:hover { border-color: var(--accent-strong); color: var(--accent-strong); background: var(--accent-soft); }

    #gpt-new-chat {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 36px;
        padding: 9px 17px;
        border: 1px solid transparent;
        border-radius: 8px;
        color: #ffffff;
        background: var(--accent);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 1px 2px rgb(0 0 0 / .06);
        transition: background .15s, transform .15s;
    }
    #gpt-new-chat:hover { background: var(--accent-strong); }
    #gpt-new-chat:active { transform: scale(.97); }

    #gpt-history-toggle, #gpt-summary-toggle { display: none; }

    /* ---------- Workspace ---------- */
    .gpt-workspace {
        position: relative;
        display: flex;
        flex: 1;
        min-height: 0;
    }

    /* ---------- Left: history ---------- */
    #gpt-history {
        width: 296px;
        flex: 0 0 296px;
        padding: 22px 12px 22px 22px;
        overflow-y: auto;
        scrollbar-gutter: stable;
        border-right: 1px solid var(--border);
        background: var(--bg-panel);
    }
    .gpt-history-title {
        padding: 2px 6px 16px;
        color: var(--text-muted);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .gpt-history-empty {
        padding: 12px 10px;
        color: var(--text-muted);
        font-size: 12.5px;
        line-height: 1.6;
    }
    .gpt-history-item {
        display: block;
        width: 100%;
        margin-bottom: 12px;
        padding: 14px 15px;
        border: 1px solid var(--border);
        border-left: 3px solid transparent;
        border-radius: 10px;
        color: var(--text-secondary);
        background: var(--bg-elevated);
        text-align: left;
        cursor: pointer;
        box-shadow: 0 1px 2px var(--ring-soft);
        transition: background .15s, border-color .15s, box-shadow .15s, transform .15s;
    }
    .gpt-history-item:hover {
        border-color: var(--accent-ring);
        background: var(--bg-elevated-2);
        box-shadow: 0 4px 10px var(--ring-soft);
        transform: translateY(-1px);
    }
    .gpt-history-item.is-active {
        border-left-color: var(--accent-strong);
        background: var(--accent-soft);
    }
    .gpt-history-item-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }
    .gpt-history-item strong {
        display: -webkit-box;
        overflow: hidden;
        color: var(--text-primary);
        font-size: 13.5px;
        font-weight: 650;
        line-height: 1.45;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        white-space: normal;
    }
    .gpt-history-item-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 10px;
    }
    .gpt-history-item span.gpt-h-date {
        overflow: hidden;
        color: var(--text-muted);
        font-size: 11px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .gpt-h-folio {
        flex: 0 0 auto;
        padding: 2px 7px;
        border-radius: 4px;
        border: 1px solid var(--border);
        color: var(--accent-strong);
        font-size: 10px;
        letter-spacing: .02em;
    }
    /* Estados reducidos a 3 colores: gris (en curso), primary (capturando / esperando confirmación) y success (completado) */
    .gpt-status-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 9px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .02em;
        text-transform: uppercase;
        width: fit-content;
        background: var(--bg-elevated-2);
        color: var(--text-secondary);
    }
    .gpt-status-tag::before { content:''; width:5px; height:5px; border-radius:999px; background: var(--text-muted); flex-shrink: 0; }
    .gpt-status-tag[data-status="capturando"],
    .gpt-status-tag[data-status="confirmacion"] { background: var(--accent-soft); color: var(--accent-strong); }
    .gpt-status-tag[data-status="capturando"]::before,
    .gpt-status-tag[data-status="confirmacion"]::before { background: var(--accent-strong); }
    .gpt-status-tag[data-status="completado"] { background: var(--success-soft); color: var(--success); }
    .gpt-status-tag[data-status="completado"]::before { background: var(--success); }

    /* ---------- Center column ---------- */
    .gpt-chat-main {
        display: flex;
        flex: 1;
        flex-direction: column;
        min-width: 0;
        min-height: 0;
    }
    .gpt-context-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 16px 30px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-panel);
    }
    .gpt-context-label {
        display: block;
        color: var(--text-muted);
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    #gpt-context-title {
        margin-top: 3px;
        color: var(--text-primary);
        font-size: 15px;
        font-weight: 650;
        letter-spacing: -.01em;
    }

    .gpt-main-row {
        display: flex;
        flex: 1;
        min-height: 0;
    }

    #gpt-messages {
        flex: 1;
        overflow-y: auto;
        min-height: 0;
        min-width: 0;
        padding: 32px 38px;
        scroll-behavior: smooth;
    }
    #gpt-messages::-webkit-scrollbar { width: 6px; }
    #gpt-messages::-webkit-scrollbar-track { background: transparent; }
    #gpt-messages::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

    #gpt-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: min(820px, 100%);
        min-height: 100%;
        margin: 0 auto;
        padding: 48px 28px;
        gap: 18px;
        text-align: center;
    }
    .gpt-empty-avatar {
        display: grid;
        place-items: center;
        width: 56px;
        height: 56px;
        border-radius: 13px;
        margin-bottom: 2px;
        font-size: 21px;
        color: var(--accent-strong);
        background: var(--accent-soft);
        border: 1px solid var(--accent-ring);
    }
    #gpt-empty h1 {
        font-size: clamp(20px, 2.6vw, 26px);
        font-weight: 680;
        color: var(--text-primary);
        letter-spacing: -0.01em;
        line-height: 1.3;
    }
    #gpt-empty p {
        max-width: 620px;
        color: var(--text-muted);
        font-size: 14px;
        line-height: 1.65;
    }
    .gpt-welcome-card {
        width: min(760px, 100%);
        margin-top: 2px;
        padding: 18px 20px;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--bg-panel);
        box-shadow: 0 1px 2px var(--ring-soft);
        text-align: left;
    }
    .gpt-welcome-card strong {
        display: block;
        margin-bottom: 10px;
        color: var(--text-primary);
        font-size: 14px;
        font-weight: 700;
    }
    .gpt-welcome-card ul {
        display: grid;
        gap: 8px;
        margin: 0;
        padding-left: 18px;
        color: var(--text-secondary);
        font-size: 13px;
        line-height: 1.55;
    }
    .gpt-welcome-card code {
        padding: 1px 5px;
        border-radius: 6px;
        background: var(--bg-elevated-2);
        color: var(--text-primary);
        font-size: 12px;
    }
    .gpt-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 9px;
        justify-content: center;
        max-width: 760px;
        margin-top: 16px;
    }
    .gpt-chip {
        padding: 11px 17px;
        border-radius: 9px;
        border: 1px solid var(--border);
        background: var(--bg-elevated);
        color: var(--text-secondary);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: border-color .15s, color .15s, background .15s;
        line-height: 1.4;
    }
    .gpt-chip:hover {
        border-color: var(--accent-ring);
        color: var(--accent-strong);
        background: var(--accent-soft);
    }

    /* message rows: estilo de nota/ficha, no burbujas de chat */
    .gpt-row {
        width: 100%;
        padding: 12px 0;
    }
    .gpt-row-inner {
        width: min(100%, 1080px);
        max-width: 1080px;
        margin: 0 auto;
        display: flex;
        gap: 15px;
        align-items: flex-start;
    }
    .gpt-row.user .gpt-row-inner { flex-direction: row-reverse; }
    .gpt-avatar {
        width: 37px;
        height: 37px;
        border-radius: 10px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        border: 1px solid var(--border);
    }
    .gpt-avatar.user { background: var(--bg-elevated-2); color: var(--text-secondary); }
    .gpt-avatar.ai { background: var(--accent-soft); color: var(--accent-strong); border-color: var(--accent-ring); }
    .gpt-message-stack {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        max-width: min(86%, 900px);
    }
    .gpt-row.user .gpt-message-stack { align-items: flex-end; }
    .gpt-sender {
        padding: 0 3px;
        margin-bottom: 7px;
        font-size: 11.5px;
        font-weight: 650;
        letter-spacing: .02em;
        color: var(--text-muted);
        text-transform: uppercase;
    }
    .gpt-msg {
        padding: 15px 18px;
        border: 1px solid var(--border);
        border-left: 3px solid var(--text-muted);
        border-radius: 10px;
        background: var(--bg-elevated);
        font-size: 14.5px;
        line-height: 1.7;
        color: var(--text-primary);
        white-space: pre-wrap;
        word-break: break-word;
    }
    .gpt-row.ai .gpt-msg { border-left-color: var(--accent-strong); }
    .gpt-row.user .gpt-msg {
        border-left: none;
        border-right: 3px solid var(--text-muted);
        background: var(--bg-elevated-2);
        color: var(--text-primary);
    }
    .typing-dots { display: flex; gap: 5px; padding-top: 3px; }
    .typing-dots span {
        width: 6px; height: 6px;
        border-radius: 50%;
        background: var(--text-muted);
        animation: gptBlink 1.2s infinite;
    }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes gptBlink {
        0%, 60%, 100% { opacity: 0.3; transform: scale(1); }
        30%            { opacity: 1;   transform: scale(1.15); }
    }

    /* solicitud confirmada: un único acento (primary), sin colores adicionales */
    .gpt-confirm-card {
        margin-top: 10px;
        padding: 16px 17px;
        border: 1px solid var(--accent-ring);
        border-radius: 10px;
        background: var(--accent-soft);
    }
    .gpt-confirm-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 11px;
    }
    .gpt-confirm-head strong {
        font-size: 13px;
        font-weight: 700;
        color: var(--accent-strong);
        letter-spacing: .01em;
    }
    .gpt-confirm-pill {
        padding: 3px 9px;
        border-radius: 5px;
        background: var(--bg-panel);
        color: var(--text-secondary);
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
        border: 1px solid var(--border);
    }
    .gpt-confirm-grid {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 7px 12px;
        font-size: 13px;
    }
    .gpt-confirm-grid dt { color: var(--text-muted); }
    .gpt-confirm-grid dd { color: var(--text-primary); font-weight: 550; }
    .gpt-confirm-folio { font-weight: 700; color: var(--accent-strong); }

    /* ---------- Right: resumen del trámite ---------- */
    #gpt-summary {
        width: 320px;
        flex: 0 0 320px;
        padding: 26px 24px;
        overflow-y: auto;
        border-left: 1px solid var(--border);
        background: var(--bg-panel);
    }
    .gpt-summary-heading {
        font-size: 11px;
        font-weight: 750;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 14px;
        flex: 0 0 auto;
    }
    #gpt-summary-empty {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .gpt-summary-empty-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 22px;
        padding-bottom: 10px;
    }
    #gpt-summary-empty p {
        color: var(--text-secondary);
        font-size: 13px;
        line-height: 1.65;
        text-align: center;
    }
    .gpt-summary-suggest-title {
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 10px;
        text-align: center;
    }
    .gpt-summary-suggest-list {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 9px;
    }
    .gpt-summary-suggest-list span {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 12px 8px;
        border: 1px solid var(--border);
        border-radius: 9px;
        background: var(--bg-elevated);
        color: var(--text-secondary);
        font-size: 12.5px;
        font-weight: 600;
    }
    .gpt-summary-grid {
        display: grid;
        gap: 18px;
    }
    .gpt-summary-grid > div {
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-soft);
    }
    .gpt-summary-grid dt {
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 4px;
    }
    .gpt-summary-grid dd {
        font-size: 13.5px;
        color: var(--text-primary);
        font-weight: 550;
        word-break: break-word;
    }
    #gpt-sum-folio.gpt-mono { color: var(--accent-strong); }
    .gpt-summary-fields {
        margin-top: 14px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .gpt-summary-field-row {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        padding: 11px 12px;
        border: 1px solid var(--border-soft);
        border-radius: 7px;
        background: var(--bg-elevated);
        font-size: 12px;
    }
    .gpt-summary-field-row span:first-child { color: var(--text-muted); }
    .gpt-summary-field-row span:last-child { color: var(--text-primary); font-weight: 550; }

    /* ---------- Input bar ---------- */
    #gpt-input-bar {
        position: sticky;
        bottom: 0;
        flex: 0 0 auto;
        padding: 18px 34px 22px;
        border-top: 1px solid var(--border);
        background: var(--bg-panel);
    }
    .gpt-input-wrap {
        max-width: 1080px;
        margin: 0 auto;
        background: var(--bg-elevated);
        border: 1px solid var(--border);
        border-radius: 12px;
        display: flex;
        align-items: center;
        padding: 11px;
        gap: 11px;
        transition: border-color .15s, box-shadow .15s;
    }
    .gpt-input-wrap:focus-within {
        border-color: var(--accent-strong);
        box-shadow: 0 0 0 3px var(--accent-ring);
    }
    #gpt-prompt {
        flex: 1;
        min-width: 0;
        padding: 6px 5px;
        background: transparent;
        border: none;
        outline: none;
        color: var(--text-primary);
        font-size: 14px;
        line-height: 1.55;
        resize: none;
        min-height: 24px;
        max-height: 170px;
        overflow-y: auto;
        font-family: inherit;
    }
    #gpt-prompt::placeholder { color: var(--text-muted); }
    #gpt-mic {
        width: 38px;
        height: 38px;
        padding: 0;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-elevated-2);
        color: var(--text-secondary);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 38px;
        visibility: visible;
        opacity: 1;
        transition: color 0.15s, background 0.15s, border-color 0.15s;
    }
    #gpt-mic:hover:not(:disabled) { border-color: var(--accent-strong); color: var(--accent-strong); }
    #gpt-mic svg { display: block; width: 19px; height: 19px; pointer-events: none; }
    /* Grabando: único caso que usa danger, por ser una señal estándar de "grabación activa" */
    #gpt-mic.is-listening {
        color: #fff;
        background: var(--danger);
        border-color: var(--danger);
        animation: gpt-mic-pulse 1s ease-in-out infinite;
    }
    /* Procesando voz: mismo acento primary que el resto de estados "en proceso" */
    #gpt-mic.is-processing {
        color: var(--accent-strong);
        background: var(--accent-soft);
        border-color: var(--accent-ring);
    }
    #gpt-mic.is-unavailable,
    #gpt-mic:disabled {
        color: var(--text-muted);
        background: var(--bg-panel);
        border-color: var(--border);
        cursor: not-allowed;
        opacity: 0.7;
    }
    @keyframes gpt-mic-pulse { 50% { box-shadow: 0 0 0 5px var(--danger-soft); } }
    #gpt-send {
        width: 38px; height: 38px;
        border-radius: 8px;
        background: var(--accent);
        border: 1px solid transparent;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 1px 2px rgb(0 0 0 / .06);
        transition: background 0.15s, opacity 0.15s;
    }
    #gpt-send:hover:not(:disabled) { background: var(--accent-strong); }
    #gpt-send:disabled { opacity: 0.35; cursor: not-allowed; }
    #gpt-send svg { width: 17px; height: 17px; }
    #gpt-error {
        display: none;
        max-width: 1080px;
        margin: 0 auto 10px;
        padding: 8px 12px;
        background: var(--danger-soft);
        border: 1px solid var(--danger);
        border-radius: 7px;
        font-size: 12.5px;
        color: var(--danger);
    }
    .gpt-footer-note {
        text-align: center;
        font-size: 11px;
        color: var(--text-muted);
        max-width: 1080px;
        margin: 9px auto 0;
    }

    /* ---------- Responsive ---------- */
    @media (max-width: 1450px) {
        #gpt-summary {
            position: absolute;
            z-index: 19;
            top: 0;
            bottom: 0;
            right: 0;
            width: min(86vw, 320px);
            transform: translateX(105%);
            box-shadow: -18px 0 40px rgb(0 0 0 / .18);
            transition: transform .2s ease;
        }
        #gpt-summary.is-open { transform: translateX(0); }
        #gpt-summary-toggle { display: inline-grid; }
    }
    @media (max-width: 768px) {
        #gpt-shell {
            height: calc(100dvh - 92px) !important;
            min-height: 0;
            border-radius: 10px;
        }
        .gpt-chat-header {
            padding: 10px 12px;
            gap: 10px;
        }
        .gpt-header-avatar {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            font-size: 14px;
        }
        .gpt-header-copy h2 {
            font-size: 14px;
            max-width: 42vw;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .gpt-header-copy p { display: none; }
        .gpt-header-actions { gap: 7px; }
        #gpt-status-text { padding: 6px 9px; min-height: 36px; font-size: 0; width: 32px; justify-content: center; }
        #gpt-status-text::before { width: 7px; height: 7px; }
        #gpt-new-chat { padding: 8px; justify-content: center; }
        #gpt-new-chat span { display: none; }
        #gpt-history-toggle { display: inline-grid; }
        #gpt-history {
            position: absolute;
            z-index: 20;
            top: 0;
            bottom: 0;
            left: 0;
            width: min(82vw, 290px);
            transform: translateX(-105%);
            box-shadow: 18px 0 40px rgb(0 0 0 / .18);
            transition: transform .2s ease;
        }
        #gpt-history.is-open { transform: translateX(0); }
        .gpt-context-bar {
            padding: 9px 12px;
            min-height: 44px;
        }
        .gpt-context-label { font-size: 10px; }
        #gpt-context-title {
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .gpt-main-row { min-height: 0; }
        #gpt-messages {
            padding: 12px 10px 14px;
            gap: 6px;
        }
        .gpt-row { padding: 8px 0; }
        .gpt-row-inner {
            gap: 9px;
            max-width: 100%;
        }
        .gpt-message-stack {
            max-width: calc(100% - 38px);
            min-width: 0;
        }
        .gpt-sender { font-size: 10.5px; }
        .gpt-msg {
            font-size: 13.5px;
            line-height: 1.55;
            padding: 10px 11px;
            border-radius: 11px;
            overflow-wrap: anywhere;
            word-break: normal;
        }
        #gpt-empty {
            justify-content: flex-start;
            min-height: auto;
            padding: 18px 8px 22px;
            gap: 12px;
        }
        .gpt-empty-avatar {
            width: 44px;
            height: 44px;
        }
        #gpt-empty h1 { font-size: 18px; }
        #gpt-empty p { font-size: 12.5px; line-height: 1.5; }
        .gpt-welcome-card {
            padding: 13px 14px;
            border-radius: 10px;
        }
        .gpt-welcome-card ul {
            gap: 6px;
            font-size: 12px;
            line-height: 1.45;
        }
        .gpt-chips {
            margin-top: 6px;
            gap: 7px;
        }
        .gpt-chip {
            padding: 8px 10px;
            font-size: 12px;
        }
        #gpt-input-bar {
            padding: 9px 10px calc(9px + env(safe-area-inset-bottom));
        }
        .gpt-input-wrap {
            gap: 7px;
            padding: 6px;
            border-radius: 12px;
        }
        #gpt-prompt {
            min-height: 38px;
            max-height: 112px;
            padding: 8px 6px;
            font-size: 14px;
            line-height: 1.45;
        }
        .gpt-footer-note { display: none; }
        .gpt-confirm-grid { grid-template-columns: 1fr; gap: 4px 0; }
        .gpt-summary-suggest-list { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 480px) {
        #gpt-shell {
            height: calc(100dvh - 82px) !important;
            border-left: 0;
            border-right: 0;
            border-radius: 0;
        }
        .gpt-chat-header { padding: 9px 10px; }
        .gpt-header-copy h2 { max-width: 34vw; }
        .gpt-icon-btn {
            width: 34px;
            height: 34px;
        }
        #gpt-new-chat {
            width: 34px;
            height: 34px;
            min-height: 34px;
        }
        .gpt-avatar {
            width: 30px;
            height: 30px;
            border-radius: 9px;
            font-size: 10px;
        }
        .gpt-message-stack { max-width: calc(100% - 34px); }
        .gpt-msg { font-size: 13px; }
        #gpt-mic, #gpt-send {
            width: 38px;
            height: 38px;
            flex-basis: 38px;
        }
        #gpt-summary {
            width: min(92vw, 330px);
        }
    }
</style>
@endpush

{{-- Único elemento raíz que ve Livewire --}}
<div id="gpt-shell">
    <header class="gpt-chat-header">
        <div class="gpt-chat-heading">
            <div class="gpt-header-avatar" aria-hidden="true">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/>
                    <path d="M8 9h8M8 13h5"/>
                </svg>
            </div>
            <div class="gpt-header-copy">
                <h2>Asistente virtual</h2>
                <p>Consulta, captura datos y genera solicitudes en Soluciones Edgar</p>
            </div>
        </div>
        <div class="gpt-header-actions">
            <div id="gpt-status-text" data-state="ready" role="status" aria-live="polite">Listo</div>
            <button id="gpt-summary-toggle" class="gpt-icon-btn" type="button" aria-label="Mostrar resumen del trámite" aria-expanded="false">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="16" rx="2"/>
                    <path d="M15 4v16"/>
                </svg>
            </button>
            <button id="gpt-history-toggle" class="gpt-icon-btn" type="button" aria-label="Mostrar historial de trámites" aria-expanded="false">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
                    <path d="M3 3v5h5M12 7v5l3 2"/>
                </svg>
            </button>
            <button id="gpt-new-chat" type="button" aria-label="Iniciar un nuevo trámite">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                <span>Nuevo trámite</span>
            </button>
        </div>
    </header>

    <div class="gpt-workspace">
        <aside id="gpt-history" aria-label="Historial de trámites recientes">
            <div class="gpt-history-title">Trámites recientes</div>
            <div id="gpt-history-list">
                <div class="gpt-history-empty">Cargando conversaciones…</div>
            </div>
        </aside>

        <main class="gpt-chat-main">
            <div class="gpt-context-bar" id="gpt-context-bar">
                <div>
                    <span class="gpt-context-label">Trámite en atención</span>
                    <h3 id="gpt-context-title">Centro de atención de trámites</h3>
                </div>
            </div>

            <div class="gpt-main-row">
                <div id="gpt-messages">
                    <div id="gpt-empty">
                        <div class="gpt-empty-avatar" aria-hidden="true">
                            <svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/>
                                <path d="M8 9h8M8 13h5"/>
                            </svg>
                        </div>
                        <h1>¿Qué trámite necesitas gestionar?</h1>
                        <p>Selecciona una opción o escribe lo que necesitas. El asistente te guiará paso a paso.</p>
                        <div class="gpt-welcome-card" role="note" aria-label="Guia rapida del asistente">
                            <strong>Hola, soy el asistente de Soluciones Edgar. Puedo ayudarte con:</strong>
                            <ul>
                                <li>Crear tramites como CURP, actas, RFC, NSS, constancia fiscal y otros servicios del catalogo.</li>
                                <li>Capturar datos paso a paso, revisar que falta y confirmar antes de crear la solicitud.</li>
                                <li>Asignar una solicitud a un usuario existente, por ejemplo: <code>Acta de nacimiento para Kevin Montero asignala a cliente@email.com</code>.</li>
                                <li>Consultar estado o folio con frases como <code>ya la hiciste?</code>, <code>que datos faltan?</code> o <code>cual fue el ultimo tramite?</code>.</li>
                                <li>Cancelar o cambiar el flujo con <code>cancela este tramite</code> o <code>quiero cambiar de tramite</code>.</li>
                            </ul>
                        </div>
                        <div class="gpt-chips">
                            <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito tramitar una CURP">CURP</button>
                            <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito un acta de nacimiento">Acta de nacimiento</button>
                            <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito un trámite de RFC">RFC</button>
                            <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito un trámite de NSS">NSS</button>
                            <button class="gpt-chip" type="button" onclick="useChip(this)" data-prompt="Necesito una constancia fiscal">Constancia fiscal</button>
                        </div>
                    </div>
                </div>

                <aside id="gpt-summary" aria-label="Resumen del trámite activo">
                    <div id="gpt-summary-empty">
                        <div class="gpt-summary-heading">Resumen del trámite</div>
                        <div class="gpt-summary-empty-body">
                            <p>Sin trámite seleccionado.</p>
                            <div>
                                <div class="gpt-summary-suggest-title">Trámites disponibles</div>
                                <div class="gpt-summary-suggest-list">
                                    <span>CURP</span>
                                    <span>Acta de nacimiento</span>
                                    <span>RFC</span>
                                    <span>NSS</span>
                                    <span>Constancia fiscal</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="gpt-summary-content" hidden>
                        <div class="gpt-summary-heading">Resumen del trámite</div>
                        <dl class="gpt-summary-grid">
                            <div><dt>Trámite</dt><dd id="gpt-sum-tramite">—</dd></div>
                            <div><dt>Interesado</dt><dd id="gpt-sum-persona">—</dd></div>
                            <div><dt>Estado</dt><dd id="gpt-sum-estado">—</dd></div>
                            <div><dt>Folio / solicitud</dt><dd id="gpt-sum-folio" class="gpt-mono">—</dd></div>
                            <div><dt>Próximo paso</dt><dd id="gpt-sum-siguiente">—</dd></div>
                        </dl>
                        <div class="gpt-summary-fields" id="gpt-sum-datos"></div>
                    </div>
                </aside>
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
                    <textarea id="gpt-prompt" placeholder="Escribe el trámite o dato solicitado…" rows="1" aria-label="Mensaje para el asistente"></textarea>
                    <button id="gpt-send" type="button" onclick="gptSend()" title="Enviar" aria-label="Enviar mensaje">
                        <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 13V3M8 3L3.5 7.5M8 3L12.5 7.5" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <p class="gpt-footer-note">El asistente puede cometer errores. Verifica la información importante.</p>
            </div>
        </main>
    </div>

    @push('scripts')
    <script>
    (function () {
        const shellEl    = document.getElementById('gpt-shell');
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

        // --- Nuevos elementos visuales (no alteran la lógica existente) ---
        const contextTitleEl  = document.getElementById('gpt-context-title');
        const summaryEl       = document.getElementById('gpt-summary');
        const summaryToggleBtn= document.getElementById('gpt-summary-toggle');
        const summaryEmptyEl  = document.getElementById('gpt-summary-empty');
        const summaryContentEl= document.getElementById('gpt-summary-content');
        const sumTramiteEl    = document.getElementById('gpt-sum-tramite');
        const sumPersonaEl    = document.getElementById('gpt-sum-persona');
        const sumEstadoEl     = document.getElementById('gpt-sum-estado');
        const sumFolioEl      = document.getElementById('gpt-sum-folio');
        const sumSiguienteEl  = document.getElementById('gpt-sum-siguiente');
        const sumDatosEl      = document.getElementById('gpt-sum-datos');

        let loading = false;
        let conversationId = localStorage.getItem('ai_conversation_id') || null;
        let forceNewConversation = false;

        // --- Alto dinámico del panel: evita que la barra de entrada quede cortada
        // cuando el encabezado/migas de Filament ocupan más o menos espacio del previsto. ---
        function syncShellHeight() {
            if (!shellEl) return;
            const top = shellEl.getBoundingClientRect().top;
            const available = window.innerHeight - top - 24;
            shellEl.style.height = Math.max(560, available) + 'px';
        }
        window.addEventListener('resize', syncShellHeight);
        syncShellHeight();
        setTimeout(syncShellHeight, 250);
        document.addEventListener('livewire:navigated', syncShellHeight);

        promptEl.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 170) + 'px';
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
            avatar.textContent = type === 'user' ? 'Tú' : 'IA';
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
                <div class="gpt-empty-avatar" aria-hidden="true">
                    <svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/>
                        <path d="M8 9h8M8 13h5"/>
                    </svg>
                </div>
                <h1>¿Qué trámite necesitas gestionar?</h1>
                <p>Selecciona una opción o escribe lo que necesitas. El asistente te guiará paso a paso.</p>
                <div class="gpt-welcome-card" role="note" aria-label="Guia rapida del asistente">
                    <strong>Hola, soy el asistente de Soluciones Edgar. Puedo ayudarte con:</strong>
                    <ul>
                        <li>Crear tramites como CURP, actas, RFC, NSS, constancia fiscal y otros servicios del catalogo.</li>
                        <li>Capturar datos paso a paso, revisar que falta y confirmar antes de crear la solicitud.</li>
                        <li>Asignar una solicitud a un usuario existente, por ejemplo: <code>Acta de nacimiento para Kevin Montero asignala a cliente@email.com</code>.</li>
                        <li>Consultar estado o folio con frases como <code>ya la hiciste?</code>, <code>que datos faltan?</code> o <code>cual fue el ultimo tramite?</code>.</li>
                        <li>Cancelar o cambiar el flujo con <code>cancela este tramite</code> o <code>quiero cambiar de tramite</code>.</li>
                    </ul>
                </div>
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

        // --- Helpers visuales nuevos: no tocan el flujo de carga/envío existente ---

        function setContextTitle(title) {
            if (!contextTitleEl) return;
            contextTitleEl.textContent = title && title.trim() ? title : 'Centro de atención de trámites';
        }

        function statusKeyFromLabel(label) {
            const v = String(label || '').toLowerCase();
            if (v.includes('confirm')) return 'confirmacion';
            if (v.includes('captur')) return 'capturando';
            if (v.includes('complet') || v.includes('listo') || v.includes('folio')) return 'completado';
            return 'curso';
        }

        function statusLabelFromKey(key) {
            return {
                curso: 'En curso',
                capturando: 'Capturando datos',
                confirmacion: 'Esperando confirmación',
                completado: 'Completado',
            }[key] || 'En curso';
        }

        function buildHistoryMeta(conversation) {
            // Lee campos opcionales si el backend ya los expone (status, folio).
            // Si no existen, simplemente no se muestran — no se inventa información.
            const wrap = document.createElement('div');

            const top = document.createElement('div');
            top.className = 'gpt-history-item-meta';
            const dateSpan = document.createElement('span');
            dateSpan.className = 'gpt-h-date';
            dateSpan.textContent = conversation.last_message || formatHistoryDate(conversation.updated_at);
            top.appendChild(dateSpan);

            if (conversation.folio) {
                const folio = document.createElement('span');
                folio.className = 'gpt-h-folio gpt-mono';
                folio.textContent = '#' + conversation.folio;
                top.appendChild(folio);
            }
            wrap.appendChild(top);

            if (conversation.status) {
                const tag = document.createElement('span');
                const key = statusKeyFromLabel(conversation.status);
                tag.className = 'gpt-status-tag';
                tag.dataset.status = key;
                tag.textContent = statusLabelFromKey(key);
                wrap.appendChild(tag);
            }

            return wrap;
        }

        function resetSummaryPanel() {
            if (!summaryEmptyEl || !summaryContentEl) return;
            summaryEmptyEl.hidden = false;
            summaryContentEl.hidden = true;
            if (sumDatosEl) sumDatosEl.innerHTML = '';
        }

        function renderSummaryField(label, value) {
            const row = document.createElement('div');
            row.className = 'gpt-summary-field-row';
            const k = document.createElement('span');
            k.textContent = label;
            const v = document.createElement('span');
            v.textContent = value;
            row.appendChild(k);
            row.appendChild(v);
            return row;
        }

        function updateSummaryPanel(metadata) {
            // Defensivo: distintas claves posibles según lo que exponga el backend.
            if (!metadata || !summaryEmptyEl || !summaryContentEl) return;
            const tramite   = metadata.tramite || metadata.procedure_name || metadata.servicio;
            const persona   = metadata.persona || metadata.interesado || metadata.nombre_interesado;
            const estado    = metadata.estado || metadata.flow_state || metadata.status;
            const folio     = metadata.folio || metadata.solicitud_id || metadata.request_id;
            const siguiente = metadata.proximo_paso || metadata.next_step;
            const campos    = metadata.datos_capturados || metadata.captured_fields || metadata.fields;

            const hasAny = tramite || persona || estado || folio || siguiente || (campos && Object.keys(campos).length);
            if (!hasAny) return;

            summaryEmptyEl.hidden = true;
            summaryContentEl.hidden = false;

            if (sumTramiteEl) sumTramiteEl.textContent = tramite || '—';
            if (sumPersonaEl) sumPersonaEl.textContent = persona || '—';
            if (sumEstadoEl) sumEstadoEl.textContent = estado || '—';
            if (sumFolioEl) sumFolioEl.textContent = folio ? ('#' + folio) : '—';
            if (sumSiguienteEl) sumSiguienteEl.textContent = siguiente || '—';

            if (sumDatosEl) {
                sumDatosEl.innerHTML = '';
                if (campos && typeof campos === 'object') {
                    Object.keys(campos).forEach((key) => {
                        sumDatosEl.appendChild(renderSummaryField(key, String(campos[key])));
                    });
                }
            }
        }

        function renderSolicitudCard(metadata) {
            // Sólo se activa si el backend envía datos de la solicitud creada
            // en el evento `done`. No depende de ni modifica la lógica SSE existente.
            if (!metadata) return;
            const folio = metadata.folio || metadata.solicitud_id || metadata.request_id;
            const creada = metadata.solicitud_creada || metadata.request_created || metadata.conversation_completed;
            if (!folio && !creada) return;

            const tramite = metadata.tramite || metadata.procedure_name || '—';
            const persona = metadata.persona || metadata.interesado || '—';

            const card = document.createElement('div');
            card.className = 'gpt-confirm-card';
            card.innerHTML = `
                <div class="gpt-confirm-head">
                    <strong>Solicitud creada</strong>
                    <span class="gpt-confirm-pill">Pendiente</span>
                </div>
                <dl class="gpt-confirm-grid">
                    <dt>Folio</dt><dd class="gpt-confirm-folio gpt-mono">${folio ? ('#' + folio) : '—'}</dd>
                    <dt>Trámite</dt><dd>${tramite}</dd>
                    <dt>Interesado</dt><dd>${persona}</dd>
                </dl>`;

            const lastRow = messagesEl.querySelector('.gpt-row.ai:last-of-type .gpt-message-stack');
            if (lastRow) {
                lastRow.appendChild(card);
            } else {
                messagesEl.appendChild(card);
            }
            messagesEl.scrollTop = messagesEl.scrollHeight;
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
                    historyListEl.innerHTML = '<div class="gpt-history-empty">Tus trámites aparecerán aquí.</div>';
                    return;
                }

                data.conversations.forEach((conversation) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'gpt-history-item' + (
                        conversation.conversation_id === conversationId ? ' is-active' : ''
                    );
                    button.dataset.conversationId = conversation.conversation_id;
                    const top = document.createElement('div');
                    top.className = 'gpt-history-item-top';
                    const title = document.createElement('strong');
                    title.textContent = conversation.title || 'Nuevo trámite';
                    top.appendChild(title);
                    button.appendChild(top);
                    button.appendChild(buildHistoryMeta(conversation));
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
                setContextTitle(data.title);
                resetSummaryPanel();
                if (data.metadata) updateSummaryPanel(data.metadata);
                historyEl.classList.remove('is-open');
                historyToggleBtn.setAttribute('aria-expanded', 'false');
                if (summaryToggleBtn) summaryToggleBtn.setAttribute('aria-expanded', 'false');
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
                    if (data.conversation_id) {
                        conversationId = data.conversation_id;
                        localStorage.setItem('ai_conversation_id', conversationId);
                    }
                    currentMsgEl.textContent = data.respuesta || 'Error al procesar la respuesta.';
                    if (data.metadata && data.metadata.was_blocked) {
                        showError('Bloqueado por seguridad.');
                    }
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder("utf-8");
                let responseText = '';
                let sseBuffer = '';
                currentMsgEl.textContent = ''; // Limpiar los dots

                const handleSseEvent = (eventBlock) => {
                    const dataLines = eventBlock
                        .split('\n')
                        .filter((line) => line.startsWith('data: '))
                        .map((line) => line.slice(6));

                    if (!dataLines.length) return;

                    const dataStr = dataLines.join('\n').trim();
                    if (!dataStr) return;

                    try {
                        const data = JSON.parse(dataStr);
                        if (data.type === 'conversation') {
                            if (data.conversation_id) {
                                conversationId = data.conversation_id;
                                forceNewConversation = false;
                                localStorage.setItem('ai_conversation_id', conversationId);
                            }
                        } else if (data.type === 'status') {
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
                                forceNewConversation = false;
                                localStorage.setItem('ai_conversation_id', conversationId);
                            }
                            if (data.metadata) {
                                updateSummaryPanel(data.metadata);
                                renderSolicitudCard(data.metadata);
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
                };

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;

                    const chunk = decoder.decode(value, { stream: true });
                    sseBuffer += chunk;
                    const events = sseBuffer.split(/\n\n|\r\n\r\n/);
                    sseBuffer = events.pop() || '';

                    for (const eventBlock of events) {
                        handleSseEvent(eventBlock);
                    }
                }

                if (sseBuffer.trim()) {
                    handleSseEvent(sseBuffer);
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
            setContextTitle(null);
            resetSummaryPanel();
            historyEl.classList.remove('is-open');
            historyToggleBtn.setAttribute('aria-expanded', 'false');
            if (summaryEl) summaryEl.classList.remove('is-open');
            if (summaryToggleBtn) summaryToggleBtn.setAttribute('aria-expanded', 'false');
            loadHistory();
            promptEl.focus();
        });

        historyToggleBtn.addEventListener('click', function () {
            const isOpen = historyEl.classList.toggle('is-open');
            historyToggleBtn.setAttribute('aria-expanded', String(isOpen));
        });

        if (summaryToggleBtn && summaryEl) {
            summaryToggleBtn.addEventListener('click', function () {
                const isOpen = summaryEl.classList.toggle('is-open');
                summaryToggleBtn.setAttribute('aria-expanded', String(isOpen));
            });
        }

        loadHistory().then(() => {
            if (conversationId) loadConversation(conversationId);
        });
        promptEl.focus();
    })();
    </script>
    @endpush

</div>

</x-filament-panels::page>
