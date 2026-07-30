<style>
    /*
    |----------------------------------------------------------------------
    | Palette
    |----------------------------------------------------------------------
    |
    | Warm neutrals with Laravel's red-orange accent, matching the current
    | first-party look. Inlined rather than published so the mailbox styles
    | itself with no build step, no npm and no network -- it has to work on
    | a plane and inside an air-gapped container.
    |
    */

    /*
    | Three themes: light, dark, and system. The chosen one is written to
    | <html data-tm-theme> by a pre-paint script in the layout, and all it
    | does is set color-scheme -- light-dark() then resolves every token
    | below. That keeps each colour declared exactly once instead of
    | maintaining parallel light and dark blocks, and it also makes native
    | scrollbars, form controls and the iframe backdrop follow along.
    */

    :root { color-scheme: light dark; }
    :root[data-tm-theme="light"] { color-scheme: light; }
    :root[data-tm-theme="dark"] { color-scheme: dark; }

    .tm-scope {
        --tm-bg:          light-dark(#fdfdfc, #0e0e0d);
        --tm-panel:       light-dark(#ffffff, #161615);
        --tm-raised:      light-dark(#f7f6f4, #1c1c1a);
        --tm-sunken:      light-dark(#f3f2ef, #121211);
        --tm-line:        light-dark(#e8e6e1, #2a2a27);
        --tm-line-soft:   light-dark(#f0eeea, #201f1d);
        --tm-line-strong: light-dark(#d8d5ce, #3b3a36);

        --tm-ink:         light-dark(#1b1b18, #ededec);
        --tm-ink-soft:    light-dark(#55534e, #b6b4ae);
        --tm-ink-mute:    light-dark(#82807a, #8b8983);
        --tm-ink-faint:   light-dark(#a3a09a, #6b6963);

        --tm-accent:      light-dark(#f53003, #ff4433);
        --tm-accent-ink:  light-dark(#ffffff, #1b1b18);
        --tm-accent-wash: light-dark(#fef2ee, #2a1512);
        --tm-accent-line: light-dark(#f9c4b3, #5c261d);

        --tm-warn-wash:   light-dark(#fdf8ed, #241f12);
        --tm-warn-line:   light-dark(#ecd9a8, #4d411f);
        --tm-warn-ink:    light-dark(#7a5a13, #d9b46a);

        --tm-danger:      light-dark(#c53030, #f87171);

        --tm-shadow-sm:   light-dark(0 1px 2px rgba(27, 27, 24, .05), 0 1px 2px rgba(0, 0, 0, .4));
        --tm-shadow-md:   light-dark(
                              0 1px 2px rgba(27, 27, 24, .04),
                              0 1px 2px rgba(0, 0, 0, .3)
                          );
        --tm-ring:        light-dark(0 0 0 3px rgba(245, 48, 3, .16), 0 0 0 3px rgba(255, 68, 51, .22));

        --tm-r-sm: 6px;
        --tm-r-md: 9px;
        --tm-r-lg: 13px;

        --tm-sans: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
        --tm-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;

        --tm-ease: cubic-bezier(.32, .72, 0, 1);
    }

    /*
    |----------------------------------------------------------------------
    | Base
    |----------------------------------------------------------------------
    */

    .tm-scope *,
    .tm-scope *::before,
    .tm-scope *::after { box-sizing: border-box; }

    html { height: 100%; }

    body.tm-scope {
        margin: 0;
        height: 100%;
        background: var(--tm-bg);
        color: var(--tm-ink);
        font-family: var(--tm-sans);
        font-size: 14px;
        line-height: 1.55;
        letter-spacing: -0.006em;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
    }

    .tm-scope a { color: inherit; text-decoration: none; }

    .tm-scope :focus-visible {
        outline: none;
        box-shadow: var(--tm-ring);
        border-radius: var(--tm-r-sm);
    }

    .tm-shell { display: flex; flex-direction: column; height: 100vh; height: 100dvh; }

    /*
    |----------------------------------------------------------------------
    | Header
    |----------------------------------------------------------------------
    */

    .tm-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 0 18px;
        height: 56px;
        flex-shrink: 0;
        background: var(--tm-panel);
        border-bottom: 1px solid var(--tm-line);
        flex-wrap: nowrap;
    }

    .tm-brand {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        font-weight: 590;
        font-size: 14.5px;
        letter-spacing: -0.02em;
        white-space: nowrap;
    }

    .tm-mark {
        display: grid;
        place-items: center;
        width: 26px;
        height: 26px;
        border-radius: 7px;
        background: var(--tm-accent);
        color: var(--tm-accent-ink);
        box-shadow: var(--tm-shadow-sm);
    }

    .tm-mark svg { display: block; }

    .tm-count {
        font-size: 12px;
        color: var(--tm-ink-mute);
        font-variant-numeric: tabular-nums;
        padding-left: 14px;
        border-left: 1px solid var(--tm-line);
        white-space: nowrap;
    }

    .tm-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
        min-width: 0;
    }

    .tm-filters { display: flex; align-items: center; gap: 8px; min-width: 0; }

    /*
    |----------------------------------------------------------------------
    | Controls
    |----------------------------------------------------------------------
    */

    .tm-field { position: relative; display: flex; align-items: center; }

    .tm-field svg {
        position: absolute;
        left: 9px;
        color: var(--tm-ink-faint);
        pointer-events: none;
    }

    .tm-input,
    .tm-select {
        appearance: none;
        font: inherit;
        font-size: 13px;
        color: var(--tm-ink);
        background: var(--tm-bg);
        border: 1px solid var(--tm-line-strong);
        border-radius: var(--tm-r-sm);
        padding: 6px 10px;
        height: 32px;
        transition: border-color .14s var(--tm-ease), background .14s var(--tm-ease);
    }

    .tm-input { width: 250px; padding-left: 30px; }
    .tm-input::placeholder { color: var(--tm-ink-faint); }
    .tm-input:hover, .tm-select:hover { border-color: var(--tm-ink-faint); }
    .tm-input::-webkit-search-cancel-button { filter: grayscale(1) opacity(.5); }

    .tm-select { padding-right: 26px; cursor: pointer; }

    .tm-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font: inherit;
        font-size: 13px;
        font-weight: 500;
        line-height: 1;
        height: 32px;
        padding: 0 12px;
        border-radius: var(--tm-r-sm);
        border: 1px solid var(--tm-line-strong);
        background: var(--tm-panel);
        color: var(--tm-ink);
        cursor: pointer;
        white-space: nowrap;
        box-shadow: var(--tm-shadow-sm);
        transition: background .14s var(--tm-ease), border-color .14s var(--tm-ease), transform .1s var(--tm-ease);
    }

    .tm-btn:hover { background: var(--tm-raised); border-color: var(--tm-ink-faint); }
    .tm-btn:active { transform: translateY(.5px); }

    .tm-btn-primary {
        background: var(--tm-accent);
        border-color: transparent;
        color: var(--tm-accent-ink);
    }
    .tm-btn-primary:hover { background: var(--tm-accent); border-color: transparent; filter: brightness(1.06); }

    .tm-btn-ghost { background: transparent; border-color: transparent; box-shadow: none; color: var(--tm-ink-soft); }
    .tm-btn-ghost:hover { background: var(--tm-raised); border-color: transparent; color: var(--tm-ink); }

    .tm-btn-danger { color: var(--tm-danger); }
    .tm-btn-danger:hover { border-color: var(--tm-danger); background: var(--tm-panel); }

    .tm-btn[aria-disabled="true"] { opacity: .4; pointer-events: none; }

    .tm-inline-form { display: inline-flex; }

    /* Three-way theme switch: system / light / dark */

    .tm-theme {
        display: inline-flex;
        align-items: center;
        gap: 1px;
        padding: 2px;
        height: 32px;
        border: 1px solid var(--tm-line-strong);
        border-radius: var(--tm-r-sm);
        background: var(--tm-sunken);
        flex-shrink: 0;
    }

    .tm-theme button {
        display: grid;
        place-items: center;
        width: 28px;
        height: 26px;
        padding: 0;
        border: 0;
        border-radius: 4px;
        background: none;
        color: var(--tm-ink-faint);
        cursor: pointer;
        transition: background .14s var(--tm-ease), color .14s var(--tm-ease);
    }

    .tm-theme button:hover { color: var(--tm-ink-soft); }

    .tm-theme button[aria-pressed="true"] {
        background: var(--tm-panel);
        color: var(--tm-ink);
        box-shadow: var(--tm-shadow-sm);
    }

    .tm-theme svg { display: block; }

    /*
    |----------------------------------------------------------------------
    | Layout
    |----------------------------------------------------------------------
    */

    .tm-main { display: flex; flex: 1; min-height: 0; }

    .tm-list {
        width: 352px;
        flex-shrink: 0;
        border-right: 1px solid var(--tm-line);
        background: var(--tm-panel);
        overflow-y: auto;
        overscroll-behavior: contain;
        display: flex;
        flex-direction: column;
    }

    /*
    |----------------------------------------------------------------------
    | Message list
    |----------------------------------------------------------------------
    */

    .tm-item {
        display: block;
        position: relative;
        padding: 12px 16px 13px 17px;
        border-bottom: 1px solid var(--tm-line-soft);
        transition: background .12s var(--tm-ease);
    }

    /*
     * The rows deliberately have no entry animation. A staggered fade-in looks
     * pleasant once, but the mailbox is server-rendered and every click is a
     * full page load, so it replayed top-to-bottom on every message you opened,
     * every page change and every filter. Instant is the right answer here.
     */

    @media (prefers-reduced-motion: reduce) {
        .tm-scope * { transition-duration: .01ms !important; }
    }

    .tm-item::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--tm-accent);
        opacity: 0;
        transition: opacity .14s var(--tm-ease);
    }

    .tm-item:hover { background: var(--tm-raised); }

    .tm-item[aria-current="true"] { background: var(--tm-accent-wash); }
    .tm-item[aria-current="true"]::before { opacity: 1; }

    .tm-item-top { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; }

    .tm-item-subject {
        font-weight: 560;
        font-size: 13.5px;
        letter-spacing: -0.012em;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }

    .tm-item-time {
        font-size: 11.5px;
        color: var(--tm-ink-faint);
        flex-shrink: 0;
        font-variant-numeric: tabular-nums;
    }

    .tm-item-to {
        font-size: 12.5px;
        color: var(--tm-ink-soft);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-top: 1px;
    }

    .tm-item-preview {
        font-size: 12.5px;
        color: var(--tm-ink-faint);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-top: 2px;
    }

    .tm-item-meta { display: flex; gap: 5px; margin-top: 8px; flex-wrap: wrap; }

    .tm-pager {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        margin-top: auto;
        border-top: 1px solid var(--tm-line);
        background: var(--tm-panel);
        position: sticky;
        bottom: 0;
    }

    /*
    |----------------------------------------------------------------------
    | Detail
    |----------------------------------------------------------------------
    */

    .tm-detail { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; background: var(--tm-bg); }

    .tm-detail-head { padding: 20px 24px 16px; background: var(--tm-panel); border-bottom: 1px solid var(--tm-line); }

    .tm-subject {
        margin: 0 0 14px;
        font-size: 19px;
        font-weight: 620;
        letter-spacing: -0.026em;
        line-height: 1.3;
        word-break: break-word;
    }

    .tm-fields { display: grid; grid-template-columns: auto 1fr; gap: 4px 14px; font-size: 13px; align-items: baseline; }

    .tm-field-label {
        color: var(--tm-ink-faint);
        text-align: right;
        white-space: nowrap;
        font-size: 12px;
        font-weight: 500;
    }

    .tm-field-value { color: var(--tm-ink-soft); word-break: break-word; min-width: 0; }

    .tm-actions { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; align-items: center; }

    /*
    |----------------------------------------------------------------------
    | Tabs
    |----------------------------------------------------------------------
    */

    .tm-tabs {
        display: flex;
        gap: 2px;
        padding: 0 24px;
        background: var(--tm-panel);
        border-bottom: 1px solid var(--tm-line);
        overflow-x: auto;
        scrollbar-width: none;
        flex-shrink: 0;
    }

    .tm-tabs::-webkit-scrollbar { display: none; }

    .tm-tab {
        position: relative;
        font: inherit;
        font-size: 13px;
        font-weight: 500;
        padding: 10px 12px 11px;
        border: 0;
        background: none;
        color: var(--tm-ink-mute);
        cursor: pointer;
        white-space: nowrap;
        transition: color .14s var(--tm-ease);
    }

    .tm-tab::after {
        content: "";
        position: absolute;
        left: 12px;
        right: 12px;
        bottom: -1px;
        height: 2px;
        border-radius: 2px 2px 0 0;
        background: var(--tm-accent);
        transform: scaleX(0);
        transition: transform .18s var(--tm-ease);
    }

    .tm-tab:hover { color: var(--tm-ink); }
    .tm-tab[aria-selected="true"] { color: var(--tm-ink); font-weight: 590; }
    .tm-tab[aria-selected="true"]::after { transform: scaleX(1); }

    .tm-tab-count { color: var(--tm-ink-faint); font-variant-numeric: tabular-nums; font-weight: 450; }

    .tm-panel { flex: 1; min-height: 0; overflow: auto; }
    .tm-panel[hidden] { display: none; }

    .tm-frame { width: 100%; height: 100%; border: 0; background: #fff; display: block; }

    .tm-pre {
        margin: 0;
        padding: 20px 24px;
        font-family: var(--tm-mono);
        font-size: 12.5px;
        line-height: 1.65;
        white-space: pre-wrap;
        word-break: break-word;
        color: var(--tm-ink);
        tab-size: 4;
    }

    /*
    |----------------------------------------------------------------------
    | Badges, notices, flashes
    |----------------------------------------------------------------------
    */

    .tm-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 500;
        line-height: 1;
        padding: 3.5px 7px;
        border-radius: 999px;
        background: var(--tm-sunken);
        color: var(--tm-ink-mute);
        border: 1px solid var(--tm-line);
        white-space: nowrap;
    }

    .tm-badge-accent {
        background: var(--tm-accent-wash);
        color: var(--tm-accent);
        border-color: var(--tm-accent-line);
    }

    .tm-notice {
        display: flex;
        gap: 9px;
        align-items: flex-start;
        margin: 14px 24px 0;
        padding: 10px 13px;
        font-size: 12.5px;
        line-height: 1.5;
        border-radius: var(--tm-r-md);
        background: var(--tm-warn-wash);
        border: 1px solid var(--tm-warn-line);
        color: var(--tm-warn-ink);
    }

    .tm-notice svg { flex-shrink: 0; margin-top: 1px; }
    .tm-notice code { font-family: var(--tm-mono); font-size: 11.5px; }

    .tm-flash {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        font-size: 13px;
        background: var(--tm-accent-wash);
        color: var(--tm-accent);
        border-bottom: 1px solid var(--tm-accent-line);
        flex-shrink: 0;
    }

    .tm-poll {
        display: none;
        align-items: center;
        gap: 6px;
        font: inherit;
        font-size: 12.5px;
        font-weight: 500;
        height: 30px;
        padding: 0 11px;
        border-radius: 999px;
        border: 1px solid var(--tm-accent-line);
        background: var(--tm-accent-wash);
        color: var(--tm-accent);
        cursor: pointer;
        white-space: nowrap;
        animation: tm-pulse 2s var(--tm-ease) infinite;
    }

    .tm-poll .tm-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--tm-accent);
    }

    @keyframes tm-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .72; }
    }

    /*
    |----------------------------------------------------------------------
    | Empty states
    |----------------------------------------------------------------------
    */

    .tm-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
        flex: 1;
        min-height: 220px;
        padding: 44px 32px;
        text-align: center;
        color: var(--tm-ink-faint);
        font-size: 13px;
        line-height: 1.6;
    }

    .tm-empty-mark {
        display: grid;
        place-items: center;
        width: 42px;
        height: 42px;
        margin-bottom: 8px;
        border-radius: var(--tm-r-lg);
        background: var(--tm-raised);
        border: 1px solid var(--tm-line);
        color: var(--tm-ink-faint);
    }

    .tm-empty-title { font-size: 14.5px; font-weight: 580; color: var(--tm-ink); letter-spacing: -0.015em; }

    .tm-scope code.tm-code {
        font-family: var(--tm-mono);
        font-size: 12px;
        background: var(--tm-sunken);
        border: 1px solid var(--tm-line);
        color: var(--tm-ink-soft);
        padding: 1.5px 5px;
        border-radius: 5px;
    }

    /*
    |----------------------------------------------------------------------
    | Headers table & attachments
    |----------------------------------------------------------------------
    */

    .tm-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }

    .tm-table td { padding: 8px 24px; border-bottom: 1px solid var(--tm-line-soft); vertical-align: top; }

    .tm-table tr:hover td { background: var(--tm-raised); }

    .tm-table td:first-child {
        font-family: var(--tm-mono);
        font-size: 11.5px;
        color: var(--tm-ink-mute);
        white-space: nowrap;
        width: 1%;
        padding-right: 20px;
    }

    .tm-table td:last-child { word-break: break-word; font-family: var(--tm-mono); font-size: 11.5px; color: var(--tm-ink); }

    .tm-table .tm-note { font-family: var(--tm-sans); font-size: 12px; color: var(--tm-ink-faint); margin-top: 4px; }

    .tm-files { padding: 14px 24px 20px; display: flex; flex-direction: column; gap: 7px; }

    .tm-file {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 10px 13px;
        border: 1px solid var(--tm-line);
        border-radius: var(--tm-r-md);
        background: var(--tm-panel);
        box-shadow: var(--tm-shadow-sm);
        transition: border-color .14s var(--tm-ease), background .14s var(--tm-ease), transform .1s var(--tm-ease);
    }

    a.tm-file:hover { background: var(--tm-raised); border-color: var(--tm-line-strong); transform: translateY(-1px); box-shadow: var(--tm-shadow-md); }

    .tm-file-icon {
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        flex-shrink: 0;
        border-radius: 7px;
        background: var(--tm-sunken);
        color: var(--tm-ink-mute);
    }

    .tm-file-name { font-weight: 520; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .tm-file-meta { font-size: 11.5px; color: var(--tm-ink-faint); margin-left: auto; white-space: nowrap; font-variant-numeric: tabular-nums; }

    /*
    |----------------------------------------------------------------------
    | Attachment rows & previews
    |----------------------------------------------------------------------
    */

    button.tm-file {
        font: inherit;
        color: inherit;
        text-align: left;
        width: 100%;
        cursor: pointer;
    }

    button.tm-file:hover {
        background: var(--tm-raised);
        border-color: var(--tm-line-strong);
        transform: translateY(-1px);
        box-shadow: var(--tm-shadow-md);
    }

    .tm-thumb {
        width: 30px;
        height: 30px;
        flex-shrink: 0;
        border-radius: 7px;
        object-fit: cover;
        background: var(--tm-sunken);
        border: 1px solid var(--tm-line);
    }

    .tm-file-kind {
        font-size: 11px;
        color: var(--tm-ink-faint);
        padding: 2px 6px;
        border: 1px solid var(--tm-line);
        border-radius: 999px;
        white-space: nowrap;
    }

    .tm-file-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }

    .tm-download {
        display: grid;
        place-items: center;
        width: 28px;
        height: 28px;
        flex-shrink: 0;
        border-radius: 6px;
        border: 1px solid var(--tm-line);
        background: var(--tm-panel);
        color: var(--tm-ink-mute);
        transition: color .14s var(--tm-ease), border-color .14s var(--tm-ease);
    }

    .tm-download:hover { color: var(--tm-ink); border-color: var(--tm-line-strong); text-decoration: none; }

    .tm-noprev {
        font-size: 11.5px;
        color: var(--tm-ink-faint);
        white-space: nowrap;
    }

    /*
    |----------------------------------------------------------------------
    | Lightbox
    |----------------------------------------------------------------------
    */

    .tm-lightbox {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: flex;
        flex-direction: column;
        background: light-dark(rgba(27, 27, 24, .62), rgba(0, 0, 0, .76));
        backdrop-filter: blur(6px);
        animation: tm-fade .16s var(--tm-ease);
    }

    .tm-lightbox[hidden] { display: none; }

    @keyframes tm-fade { from { opacity: 0; } to { opacity: 1; } }

    .tm-lightbox-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        flex-shrink: 0;
        color: #fff;
        /* Opaque, otherwise the mailbox behind it shows through the bar and
           the filename competes with the page's own controls. */
        background: #1b1b18;
        border-bottom: 1px solid rgba(255, 255, 255, .1);
    }

    .tm-lightbox-title { font-size: 13.5px; font-weight: 560; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tm-lightbox-sub { font-size: 12px; opacity: .72; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .tm-lightbox-tools { display: flex; align-items: center; gap: 6px; margin-left: auto; }

    .tm-lightbox-btn {
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        font: inherit;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: var(--tm-r-sm);
        background: rgba(255, 255, 255, .08);
        color: #fff;
        cursor: pointer;
        flex-shrink: 0;
        transition: background .14s var(--tm-ease);
    }

    .tm-lightbox-btn:hover { background: rgba(255, 255, 255, .18); text-decoration: none; }
    .tm-lightbox-btn[disabled] { opacity: .3; pointer-events: none; }
    .tm-lightbox-btn:focus-visible { box-shadow: 0 0 0 3px rgba(255, 255, 255, .4); }

    .tm-lightbox-body {
        flex: 1;
        min-height: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 8px 16px;
    }

    .tm-lightbox-stage {
        flex: 1;
        min-width: 0;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: auto;
    }

    /* The empty space closes the preview, so let the pointer say so. */
    .tm-lightbox,
    .tm-lightbox-body,
    .tm-lightbox-stage { cursor: zoom-out; }

    .tm-lightbox-bar,
    .tm-lightbox-stage > * { cursor: default; }

    .tm-lightbox-bar button,
    .tm-lightbox-bar a { cursor: pointer; }

    /* Media and documents inside the stage */

    .tm-lightbox-stage img.tm-shot {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        border-radius: var(--tm-r-md);
        background: #fff;
        box-shadow: 0 8px 30px -8px rgba(0, 0, 0, .5);
    }

    .tm-lightbox-stage .tm-doc {
        width: 100%;
        height: 100%;
        border: 0;
        border-radius: var(--tm-r-md);
        background: #fff;
    }

    .tm-lightbox-stage audio,
    .tm-lightbox-stage video {
        max-width: 100%;
        max-height: 100%;
        border-radius: var(--tm-r-md);
    }

    .tm-lightbox-stage audio { width: min(560px, 100%); }

    /* Server-rendered text kinds */

    .tm-sheet {
        width: 100%;
        max-width: 1000px;
        max-height: 100%;
        overflow: auto;
        background: var(--tm-panel);
        border-radius: var(--tm-r-lg);
        box-shadow: 0 8px 30px -8px rgba(0, 0, 0, .5);
    }

    .tm-sheet .tm-pre { padding: 18px 20px; }

    .tm-sheet-note {
        padding: 9px 20px;
        font-size: 12px;
        color: var(--tm-warn-ink);
        background: var(--tm-warn-wash);
        border-bottom: 1px solid var(--tm-warn-line);
    }

    .tm-sheet-fields {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 5px 14px;
        padding: 16px 20px;
        font-size: 13px;
        border-bottom: 1px solid var(--tm-line);
    }

    .tm-sheet-fields dt { color: var(--tm-ink-faint); font-size: 12px; text-align: right; white-space: nowrap; }
    .tm-sheet-fields dd { margin: 0; color: var(--tm-ink); word-break: break-word; white-space: pre-line; }

    .tm-grid { width: 100%; border-collapse: collapse; font-size: 12.5px; font-family: var(--tm-mono); }

    .tm-grid th, .tm-grid td {
        padding: 6px 12px;
        border-bottom: 1px solid var(--tm-line-soft);
        border-right: 1px solid var(--tm-line-soft);
        text-align: left;
        vertical-align: top;
        max-width: 320px;
        overflow-wrap: break-word;
    }

    .tm-grid th {
        position: sticky;
        top: 0;
        background: var(--tm-raised);
        font-weight: 600;
        z-index: 1;
    }

    .tm-grid tbody tr:hover td { background: var(--tm-raised); }
    .tm-grid td:last-child, .tm-grid th:last-child { border-right: 0; }

    .tm-grid-num {
        color: var(--tm-ink-faint);
        text-align: right;
        width: 1%;
        user-select: none;
        font-variant-numeric: tabular-nums;
    }

    .tm-blank {
        display: grid;
        place-items: center;
        gap: 6px;
        padding: 48px 32px;
        text-align: center;
        color: var(--tm-ink-faint);
        font-size: 13px;
    }

    @media (max-width: 900px) {
        .tm-lightbox-body { padding: 0 4px 12px; }
        .tm-lightbox-title { font-size: 12.5px; }
    }

    /*
    |----------------------------------------------------------------------
    | Scrollbars
    |----------------------------------------------------------------------
    */

    .tm-scope ::-webkit-scrollbar { width: 11px; height: 11px; }
    .tm-scope ::-webkit-scrollbar-track { background: transparent; }
    .tm-scope ::-webkit-scrollbar-thumb {
        background: var(--tm-line-strong);
        border-radius: 99px;
        border: 3px solid var(--tm-panel);
    }
    .tm-scope ::-webkit-scrollbar-thumb:hover { background: var(--tm-ink-faint); }

    /*
    |----------------------------------------------------------------------
    | Responsive
    |----------------------------------------------------------------------
    */

    @media (max-width: 900px) {
        .tm-header { height: auto; padding: 10px 14px; flex-wrap: wrap; gap: 10px; }
        .tm-header-actions { width: 100%; margin-left: 0; }
        .tm-filters { flex: 1; }
        .tm-input { width: 100%; min-width: 0; }
        .tm-count { padding-left: 0; border-left: 0; }
        .tm-main { flex-direction: column; }
        .tm-list { width: auto; max-height: 42vh; border-right: 0; border-bottom: 1px solid var(--tm-line); }
        .tm-detail-head, .tm-tabs { padding-left: 16px; padding-right: 16px; }
        .tm-table td { padding-left: 16px; padding-right: 16px; }
        .tm-files { padding-left: 16px; padding-right: 16px; }
    }
</style>
