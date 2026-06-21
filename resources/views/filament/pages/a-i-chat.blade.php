<x-filament-panels::page>

@push('styles')
<style>
    #gpt-shell {
        --bg-base: #0a0d12;
        --bg-panel: #0f131a;
        --bg-elevated: #151a22;
        --bg-elevated-2: #1a212b;
        --border: #232b36;
        --border-soft: rgba(255,255,255,.06);
        --text-primary: #edf1f5;
        --text-secondary: #a6b0bf;
        --text-muted: #677182;
        --accent: #1f7a6c;
        --accent-strong: #2a9683;
        --accent-soft: rgba(31,122,108,.16);
        --gold: #c7a165;
        --status-curso: #c98a3d;
        --status-capturando: #4f86ad;
        --status-confirmacion: #8d76b8;
        --status-completado: #2f9e7d;
        --danger: #c1554c;

        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 1280px;
        height: clamp(620px, calc(100vh - 150px), 880px);
        min-height: 620px;
        margin: 0 auto;
        background: var(--bg-base);
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        color: var(--text-primary);
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: 0 24px 60px rgba(0,0,0,.4);
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
        gap: 16px;
        padding: 16px 22px;
        border-bottom: 1px solid var(--border);
        background: var(--bg-panel);
    }
    .gpt-chat-heading {
        display: flex;
        align-items: center;
        gap: 13px;
        min-width: 0;
    }
    .gpt-header-avatar {
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        width: 38px;
        height: 38px;
        border-radius: 9px;
        color: var(--accent-strong);
        background: var(--accent-soft);
        border: 1px solid rgba(42,150,131,.35);
        font-size: 17px;
    }
    .gpt-header-copy { min-width: 0; }
    .gpt-header-copy h2 {
        color: var(--text-primary);
        font-size: 15px;
        font-weight: 700;
        letter-spacing: -.01em;
    }
    .gpt-header-copy p {
        margin-top: 2px;
        color: var(--text-muted);
        font-size: 12px;
        line-height: 1.4;
    }
    .gpt-header-actions {
        display: flex;
        align-items: center;
        gap: 9px;
        flex: 0 0 auto;
    }
    #gpt-status-text {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 32px;
        padding: 6px 12px;
        border: 1px solid var(--border);
        border-radius: 7px;
        color: var(--text-secondary);
        background: var(--bg-elevated);
        font-size: 11px;
        font-weight: 650;
        letter-spacing: .03em;
        white-space: nowrap;
    }
    #gpt-status-text::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: var(--status-completado);
    }
    #gpt-status-text[data-state="listening"]::before { background: var(--danger); animation: gptPulse 1s infinite; }
    #gpt-status-text[data-state="processing"]::before,
    #gpt-status-text[data-state="searching"]::before { background: var(--status-curso); }
    #gpt-status-text[data-state="streaming"]::before { background: var(--status-capturando); animation: gptPulse 1s infinite; }
    #gpt-status-text[data-state="error"]::before { background: var(--danger); }
    @keyframes gptPulse { 50% { opacity: .35; transform: scale(.7); } }

    .gpt-icon-btn {
        display: inline-grid;
        place-items: center;
        width: 36px;
        height: 32px;
        border: 1px solid var(--border);
        border-radius: 7px;
        color: var(--text-secondary);
        background: var(--bg-elevated);
        cursor: pointer;
    }
    .gpt-icon-btn:hover { border-color: var(--accent-strong); color: var(--text-primary); }

    #gpt-new-chat {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 32px;
        padding: 7px 13px;
        border: 1px solid var(--accent-strong);
        border-radius: 7px;
        color: #eafff8;
        background: var(--accent);
        font-size: 12px;
        font-weight: 650;
        cursor: pointer;
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
        width: 252px;
        flex: 0 0 252px;
        padding: 14px 10px;
        overflow-y: auto;
        border-right: 1px solid var(--border);
        background: var(--bg-panel);
    }
    .gpt-history-title {
        padding: 4px 8px 10px;
        color: var(--text-muted);
        font-size: 10.5px;
        font-weight: 750;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .gpt-history-empty {
        padding: 10px 8px;
        color: var(--text-muted);
        font-size: 12px;
        line-height: 1.5;
    }
    .gpt-history-item {
        display: block;
        width: 100%;
        margin-bottom: 6px;
        padding: 10px 11px;
        border: 1px solid var(--border-soft);
        border-left: 2px solid transparent;
        border-radius: 6px;
        color: var(--text-secondary);
        background: var(--bg-elevated);
        text-align: left;
        cursor: pointer;
        transition: background .15s, border-color .15s;
    }
    .gpt-history-item:hover { border-color: var(--border); background: var(--bg-elevated-2); }
    .gpt-history-item.is-active {
        border-left-color: var(--accent-strong);
        background: var(--accent-soft);
    }
    .gpt-history-item-top {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px;
    }
    .gpt-history-item strong {
        display: block;
        overflow: hidden;
        color: var(--text-primary);
        font-size: 12px;
        font-weight: 650;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .gpt-history-item-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        margin-top: 5px;
    }
    .gpt-history-item span.gpt-h-date {
        overflow: hidden;
        color: var(--text-muted);
        font-size: 10px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .gpt-h-folio {
        flex: 0 0 auto;
        padding: 1px 6px;
        border-radius: 4px;
        border: 1px solid var(--border);
        color: var(--gold);
        font-size: 9.5px;
        letter-spacing: .02em;
    }
    .gpt-status-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-top: 6px;
        padding: 2px 7px;
        border-radius: 4px;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
        width: fit-content;
    }
    .gpt-status-tag::before { content:''; width:5px; height:5px; border-radius:999px; }
    .gpt-status-tag[data-status="curso"] { color: var(--status-curso); background: rgba(201,138,61,.14); }
    .gpt-status-tag[data-status="curso"]::before { background: var(--status-curso); }
    .gpt-status-tag[data-status="capturando"] { color: var(--status-capturando); background: rgba(79,134,173,.14); }
    .gpt-status-tag[data-status="capturando"]::before { background: var(--status-capturando); }
    .gpt-status-tag[data-status="confirmacion"] { color: var(--status-confirmacion); background: rgba(141,118,184,.14); }
    .gpt-status-tag[data-status="confirmacion"]::before { background: var(--status-confirmacion); }
    .gpt-status-tag[data-status="completado"] { color: var(--status-completado); background: rgba(47,158,125,.14); }
    .gpt-status-tag[data-status="completado"]::before { background: var(--status-completado); }

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
        padding: 12px 22px;
        border-bottom: 1px solid var(--border);
        background: linear-gradient(180deg, var(--bg-panel), var(--bg-base));
    }
    .gpt-context-label {
        display: block;
        color: var(--text-muted);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    #gpt-context-title {
        margin-top: 2px;
        color: var(--text-primary);
        font-size: 14px;
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
        padding: 22px 24px;
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
        width: min(620px, 100%);
        min-height: 100%;
        margin: 0 auto;
        padding: 30px 18px;
        gap: 13px;
        text-align: center;
    }
    .gpt-empty-avatar {
        display: grid;
        place-items: center;
        width: 54px;
        height: 54px;
        border-radius: 12px;
        margin-bottom: 2px;
        font-size: 21px;
        color: var(--accent-strong);
        background: var(--accent-soft);
        border: 1px solid rgba(42,150,131,.35);
    }
    #gpt-empty h1 {
        font-size: clamp(20px, 2.6vw, 25px);
        font-weight: 680;
        color: var(--text-primary);
        letter-spacing: -0.01em;
    }
    #gpt-empty p {
        max-width: 480px;
        color: var(--text-muted);
        font-size: 13.5px;
        line-height: 1.6;
    }
    .gpt-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: center;
        max-width: 560px;
        margin-top: 10px;
    }
    .gpt-chip {
        padding: 8px 13px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--bg-elevated);
        color: var(--text-secondary);
        font-size: 12.5px;
        font-weight: 550;
        cursor: pointer;
        transition: border-color .15s, color .15s, background .15s;
        line-height: 1.4;
    }
    .gpt-chip:hover {
        border-color: var(--accent-strong);
        color: var(--text-primary);
        background: var(--accent-soft);
    }

    /* message rows: ticket-style, not chat bubbles */
    .gpt-row {
        width: 100%;
        padding: 6px 0;
    }
    .gpt-row-inner {
        max-width: 760px;
        margin: 0 auto;
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }
    .gpt-row.user .gpt-row-inner { flex-direction: row-reverse; }
    .gpt-avatar {
        width: 30px;
        height: 30px;
        border-radius: 7px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10.5px;
        font-weight: 700;
        border: 1px solid var(--border);
    }
    .gpt-avatar.user { background: var(--bg-elevated-2); color: var(--text-secondary); }
    .gpt-avatar.ai { background: var(--accent-soft); color: var(--accent-strong); border-color: rgba(42,150,131,.35); }
    .gpt-message-stack {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        max-width: min(80%, 640px);
    }
    .gpt-row.user .gpt-message-stack { align-items: flex-end; }
    .gpt-sender {
        padding: 0 3px;
        margin-bottom: 4px;
        font-size: 10.5px;
        font-weight: 650;
        letter-spacing: .02em;
        color: var(--text-muted);
        text-transform: uppercase;
    }
    .gpt-msg {
        padding: 11px 14px;
        border: 1px solid var(--border);
        border-left: 3px solid var(--text-secondary);
        border-radius: 6px;
        background: var(--bg-elevated);
        font-size: 13.5px;
        line-height: 1.62;
        color: #e7eaef;
        white-space: pre-wrap;
        word-break: break-word;
    }
    .gpt-row.ai .gpt-msg { border-left-color: var(--accent-strong); }
    .gpt-row.user .gpt-msg {
        border-left: none;
        border-right: 3px solid var(--text-secondary);
        background: var(--bg-elevated-2);
        color: #f1f3f6;
    }
    .typing-dots { display: flex; gap: 5px; padding-top: 2px; }
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

    /* solicitud confirmada card (rendered inside the chat flow) */
    .gpt-confirm-card {
        margin-top: 8px;
        padding: 14px 15px;
        border: 1px solid rgba(42,150,131,.4);
        border-radius: 7px;
        background: linear-gradient(180deg, rgba(42,150,131,.12), rgba(42,150,131,.04));
    }
    .gpt-confirm-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 9px;
    }
    .gpt-confirm-head strong {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--accent-strong);
        letter-spacing: .01em;
    }
    .gpt-confirm-pill {
        padding: 2px 8px;
        border-radius: 4px;
        background: rgba(201,138,61,.16);
        color: var(--status-curso);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .gpt-confirm-grid {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 5px 10px;
        font-size: 12.5px;
    }
    .gpt-confirm-grid dt { color: var(--text-muted); }
    .gpt-confirm-grid dd { color: var(--text-primary); font-weight: 550; }
    .gpt-confirm-folio { font-weight: 700; color: var(--gold); }

    /* ---------- Right: resumen del trámite ---------- */
    #gpt-summary {
        width: 280px;
        flex: 0 0 280px;
        padding: 18px 16px;
        overflow-y: auto;
        border-left: 1px solid var(--border);
        background: var(--bg-panel);
    }
    .gpt-summary-heading {
        font-size: 11px;
        font-weight: 750;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 10px;
    }
    #gpt-summary-empty p {
        color: var(--text-secondary);
        font-size: 12.5px;
        line-height: 1.6;
        margin-bottom: 16px;
    }
    .gpt-summary-suggest-title {
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 8px;
    }
    .gpt-summary-suggest-list {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .gpt-summary-suggest-list span {
        display: block;
        padding: 8px 10px;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--bg-elevated);
        color: var(--text-secondary);
        font-size: 12px;
    }
    .gpt-summary-grid {
        display: grid;
        gap: 12px;
    }
    .gpt-summary-grid > div {
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-soft);
    }
    .gpt-summary-grid dt {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 3px;
    }
    .gpt-summary-grid dd {
        font-size: 13px;
        color: var(--text-primary);
        font-weight: 550;
        word-break: break-word;
    }
    #gpt-sum-folio.gpt-mono { color: var(--gold); }
    .gpt-summary-fields {
        margin-top: 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .gpt-summary-field-row {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        padding: 7px 9px;
        border: 1px solid var(--border-soft);
        border-radius: 5px;
        background: var(--bg-elevated);
        font-size: 11.5px;
    }
    .gpt-summary-field-row span:first-child { color: var(--text-muted); }
    .gpt-summary-field-row span:last-child { color: var(--text-primary); font-weight: 550; }

    /* ---------- Input bar ---------- */
    #gpt-input-bar {
        position: sticky;
        bottom: 0;
        flex: 0 0 auto;
        padding: 13px 24px 16px;
        border-top: 1px solid var(--border);
        background: var(--bg-panel);
    }
    .gpt-input-wrap {
        max-width: 760px;
        margin: 0 auto;
        background: var(--bg-elevated);
        border: 1px solid var(--border);
        border-radius: 9px;
        display: flex;
        align-items: center;
        padding: 8px;
        gap: 8px;
        transition: border-color .2s;
    }
    .gpt-input-wrap:focus-within { border-color: var(--accent-strong); }
    #gpt-prompt {
        flex: 1;
        min-width: 0;
        padding: 5px 4px;
        background: transparent;
        border: none;
        outline: none;
        color: var(--text-primary);
        font-size: 13.5px;
        line-height: 1.5;
        resize: none;
        min-height: 22px;
        max-height: 170px;
        overflow-y: auto;
        font-family: inherit;
    }
    #gpt-prompt::placeholder { color: var(--text-muted); }
    #gpt-mic {
        width: 36px;
        height: 36px;
        padding: 0;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: var(--bg-elevated-2);
        color: var(--text-secondary);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 36px;
        visibility: visible;
        opacity: 1;
        transition: color 0.15s, background 0.15s, border-color 0.15s;
    }
    #gpt-mic:hover:not(:disabled) { border-color: var(--accent-strong); color: var(--text-primary); }
    #gpt-mic svg { display: block; width: 18px; height: 18px; pointer-events: none; }
    #gpt-mic.is-listening {
        color: #fff;
        background: var(--danger);
        border-color: var(--danger);
        animation: gpt-mic-pulse 1s ease-in-out infinite;
    }
    #gpt-mic.is-processing {
        color: #1c1404;
        background: var(--status-curso);
        border-color: var(--status-curso);
    }
    #gpt-mic.is-unavailable,
    #gpt-mic:disabled {
        color: var(--text-muted);
        background: var(--bg-panel);
        border-color: var(--border);
        cursor: not-allowed;
        opacity: 0.7;
    }
    @keyframes gpt-mic-pulse { 50% { box-shadow: 0 0 0 5px rgba(193,85,76,.2); } }
    #gpt-send {
        width: 36px; height: 36px;
        border-radius: 7px;
        background: var(--accent);
        border: 1px solid var(--accent-strong);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: background 0.15s, opacity 0.15s;
    }
    #gpt-send:hover:not(:disabled) { background: var(--accent-strong); }
    #gpt-send:disabled { opacity: 0.35; cursor: not-allowed; }
    #gpt-send svg { width: 16px; height: 16px; }
    #gpt-error {
        display: none;
        max-width: 760px;
        margin: 0 auto 8px;
        padding: 7px 11px;
        background: rgba(193,85,76,.12);
        border: 1px solid rgba(193,85,76,.4);
        border-radius: 6px;
        font-size: 12px;
        color: #f3c9c5;
    }
    .gpt-footer-note {
        text-align: center;
        font-size: 10.5px;
        color: var(--text-muted);
        max-width: 760px;
        margin: 7px auto 0;
    }

    /* ---------- Responsive ---------- */
    @media (max-width: 1080px) {
        #gpt-summary {
            position: absolute;
            z-index: 19;
            top: 0;
            bottom: 0;
            right: 0;
            width: min(86vw, 300px);
            transform: translateX(105%);
            box-shadow: -18px 0 40px rgba(0,0,0,.4);
            transition: transform .2s ease;
        }
        #gpt-summary.is-open { transform: translateX(0); }
        #gpt-summary-toggle { display: inline-grid; }
    }
    @media (max-width: 768px) {
        #gpt-shell {
            height: calc(100dvh - 118px);
            min-height: 540px;
            border-radius: 12px;
        }
        .gpt-chat-header { padding: 12px 14px; }
        .gpt-header-copy p { display: none; }
        .gpt-header-actions { gap: 6px; }
        #gpt-status-text { padding: 5px 8px; min-height: 32px; font-size: 0; width: 30px; justify-content: center; }
        #gpt-status-text::before { width: 7px; height: 7px; }
        #gpt-new-chat { padding: 7px; justify-content: center; }
        #gpt-new-chat span { display: none; }
        #gpt-history-toggle { display: inline-grid; }
        #gpt-history {
            position: absolute;
            z-index: 20;
            top: 0;
            bottom: 0;
            left: 0;
            width: min(82vw, 280px);
            transform: translateX(-105%);
            box-shadow: 18px 0 40px rgba(0,0,0,.4);
            transition: transform .2s ease;
        }
        #gpt-history.is-open { transform: translateX(0); }
        .gpt-context-bar { padding: 10px 14px; }
        #gpt-messages { padding: 16px 12px; }
        .gpt-message-stack { max-width: calc(100% - 44px); }
        .gpt-msg { font-size: 13px; padding: 10px 12px; }
        #gpt-input-bar { padding: 10px 11px 12px; }
        .gpt-footer-note { display: none; }
        .gpt-confirm-grid { grid-template-columns: 1fr; gap: 3px 0; }
    }
    @media (max-width: 480px) {
        #gpt-shell { min-height: 500px; border-radius: 10px; }
        .gpt-avatar { width: 27px; height: 27px; }
        .gpt-message-stack { max-width: calc(100% - 38px); }
        #gpt-mic, #gpt-send { width: 34px; height: 34px; flex-basis: 34px; }
    }
</style>
@endpush

{{-- Único elemento raíz que ve Livewire --}}
<div id="gpt-shell">
    <header class="gpt-chat-header">
        <div class="gpt-chat-heading">
            <div class="gpt-header-avatar" aria-hidden="true">&#9776;</div>
            <div class="gpt-header-copy">
                <h2>Centro de atención de trámites</h2>
                <p>Soluciones Edgar &middot; consulta, captura datos y genera solicitudes</p>
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
                        <div class="gpt-empty-avatar" aria-hidden="true">&#9776;</div>
                        <h1>¿Qué trámite necesitas gestionar?</h1>
                        <p>Selecciona una opción o escribe lo que necesitas. El asistente te guiará paso a paso.</p>
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
                        <p>Sin trámite seleccionado.</p>
                        <div class="gpt-summary-suggest-title">Trámites disponibles</div>
                        <div class="gpt-summary-suggest-list">
                            <span>CURP</span>
                            <span>Acta de nacimiento</span>
                            <span>RFC</span>
                            <span>NSS</span>
                            <span>Constancia fiscal</span>
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
                <div class="gpt-empty-avatar" aria-hidden="true">&#9776;</div>
                <h1>¿Qué trámite necesitas gestionar?</h1>
                <p>Selecciona una opción o escribe lo que necesitas. El asistente te guiará paso a paso.</p>
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