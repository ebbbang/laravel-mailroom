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
    | <html data-mr-theme> by a pre-paint script in the layout, and all it
    | does is set color-scheme -- light-dark() then resolves every token
    | below. That keeps each colour declared exactly once instead of
    | maintaining parallel light and dark blocks, and it also makes native
    | scrollbars, form controls and the iframe backdrop follow along.
    */

    :root { color-scheme: light dark; }
    :root[data-mr-theme="light"] { color-scheme: light; }
    :root[data-mr-theme="dark"] { color-scheme: dark; }

    .mr-scope {
        --mr-bg:          light-dark(#fdfdfc, #0e0e0d);
        --mr-panel:       light-dark(#ffffff, #161615);
        --mr-raised:      light-dark(#f7f6f4, #1c1c1a);
        --mr-sunken:      light-dark(#f3f2ef, #121211);
        --mr-line:        light-dark(#e8e6e1, #2a2a27);
        --mr-line-soft:   light-dark(#f0eeea, #201f1d);
        --mr-line-strong: light-dark(#d8d5ce, #3b3a36);

        --mr-ink:         light-dark(#1b1b18, #ededec);
        --mr-ink-soft:    light-dark(#55534e, #b6b4ae);
        --mr-ink-mute:    light-dark(#82807a, #8b8983);
        --mr-ink-faint:   light-dark(#a3a09a, #6b6963);

        --mr-accent:      light-dark(#f53003, #ff4433);
        --mr-accent-ink:  light-dark(#ffffff, #1b1b18);
        --mr-accent-wash: light-dark(#fef2ee, #2a1512);
        --mr-accent-line: light-dark(#f9c4b3, #5c261d);

        --mr-warn-wash:   light-dark(#fdf8ed, #241f12);
        --mr-warn-line:   light-dark(#ecd9a8, #4d411f);
        --mr-warn-ink:    light-dark(#7a5a13, #d9b46a);

        --mr-danger:      light-dark(#c53030, #f87171);

        --mr-shadow-sm:   light-dark(0 1px 2px rgba(27, 27, 24, .05), 0 1px 2px rgba(0, 0, 0, .4));
        --mr-shadow-md:   light-dark(
                              0 1px 2px rgba(27, 27, 24, .04),
                              0 1px 2px rgba(0, 0, 0, .3)
                          );
        --mr-ring:        light-dark(0 0 0 3px rgba(245, 48, 3, .16), 0 0 0 3px rgba(255, 68, 51, .22));

        --mr-r-sm: 6px;
        --mr-r-md: 9px;
        --mr-r-lg: 13px;

        --mr-sans: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
        --mr-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;

        --mr-ease: cubic-bezier(.32, .72, 0, 1);
    }

    /*
    |----------------------------------------------------------------------
    | Base
    |----------------------------------------------------------------------
    */

    .mr-scope *,
    .mr-scope *::before,
    .mr-scope *::after { box-sizing: border-box; }

    html { height: 100%; }

    body.mr-scope {
        margin: 0;
        height: 100%;
        background: var(--mr-bg);
        color: var(--mr-ink);
        font-family: var(--mr-sans);
        font-size: 14px;
        line-height: 1.55;
        letter-spacing: -0.006em;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        text-rendering: optimizeLegibility;
    }

    .mr-scope a { color: inherit; text-decoration: none; }

    .mr-scope :focus-visible {
        outline: none;
        box-shadow: var(--mr-ring);
        border-radius: var(--mr-r-sm);
    }

    .mr-shell { display: flex; flex-direction: column; height: 100vh; height: 100dvh; }

    /*
    |----------------------------------------------------------------------
    | Header
    |----------------------------------------------------------------------
    */

    .mr-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 0 18px;
        height: 56px;
        flex-shrink: 0;
        background: var(--mr-panel);
        border-bottom: 1px solid var(--mr-line);
        flex-wrap: nowrap;
    }

    .mr-brand {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        font-weight: 590;
        font-size: 14.5px;
        letter-spacing: -0.02em;
        white-space: nowrap;
    }

    .mr-mark {
        display: grid;
        place-items: center;
        width: 26px;
        height: 26px;
        border-radius: 7px;
        background: var(--mr-accent);
        color: var(--mr-accent-ink);
        box-shadow: var(--mr-shadow-sm);
    }

    .mr-mark svg { display: block; }

    .mr-count {
        font-size: 12px;
        color: var(--mr-ink-mute);
        font-variant-numeric: tabular-nums;
        padding-left: 14px;
        border-left: 1px solid var(--mr-line);
        white-space: nowrap;
    }

    .mr-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
        min-width: 0;
    }

    .mr-filters { display: flex; align-items: center; gap: 8px; min-width: 0; }

    /*
    |----------------------------------------------------------------------
    | Controls
    |----------------------------------------------------------------------
    */

    .mr-field { position: relative; display: flex; align-items: center; }

    .mr-field svg {
        position: absolute;
        left: 9px;
        color: var(--mr-ink-faint);
        pointer-events: none;
    }

    .mr-input,
    .mr-select {
        appearance: none;
        font: inherit;
        font-size: 13px;
        color: var(--mr-ink);
        background: var(--mr-bg);
        border: 1px solid var(--mr-line-strong);
        border-radius: var(--mr-r-sm);
        padding: 6px 10px;
        height: 32px;
        transition: border-color .14s var(--mr-ease), background .14s var(--mr-ease);
    }

    .mr-input { width: 250px; padding-left: 30px; }
    .mr-input::placeholder { color: var(--mr-ink-faint); }
    .mr-input:hover, .mr-select:hover { border-color: var(--mr-ink-faint); }
    .mr-input::-webkit-search-cancel-button { filter: grayscale(1) opacity(.5); }

    .mr-select { padding-right: 26px; cursor: pointer; }

    .mr-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font: inherit;
        font-size: 13px;
        font-weight: 500;
        line-height: 1;
        height: 32px;
        padding: 0 12px;
        border-radius: var(--mr-r-sm);
        border: 1px solid var(--mr-line-strong);
        background: var(--mr-panel);
        color: var(--mr-ink);
        cursor: pointer;
        white-space: nowrap;
        box-shadow: var(--mr-shadow-sm);
        transition: background .14s var(--mr-ease), border-color .14s var(--mr-ease), transform .1s var(--mr-ease);
    }

    .mr-btn:hover { background: var(--mr-raised); border-color: var(--mr-ink-faint); }
    .mr-btn:active { transform: translateY(.5px); }

    .mr-btn-primary {
        background: var(--mr-accent);
        border-color: transparent;
        color: var(--mr-accent-ink);
    }
    .mr-btn-primary:hover { background: var(--mr-accent); border-color: transparent; filter: brightness(1.06); }

    .mr-btn-ghost { background: transparent; border-color: transparent; box-shadow: none; color: var(--mr-ink-soft); }
    .mr-btn-ghost:hover { background: var(--mr-raised); border-color: transparent; color: var(--mr-ink); }

    .mr-btn-danger { color: var(--mr-danger); }
    .mr-btn-danger:hover { border-color: var(--mr-danger); background: var(--mr-panel); }

    .mr-btn[aria-disabled="true"] { opacity: .4; pointer-events: none; }

    .mr-inline-form { display: inline-flex; }

    /* Three-way theme switch: system / light / dark */

    .mr-theme {
        display: inline-flex;
        align-items: center;
        gap: 1px;
        padding: 2px;
        height: 32px;
        border: 1px solid var(--mr-line-strong);
        border-radius: var(--mr-r-sm);
        background: var(--mr-sunken);
        flex-shrink: 0;
    }

    .mr-theme button {
        display: grid;
        place-items: center;
        width: 28px;
        height: 26px;
        padding: 0;
        border: 0;
        border-radius: 4px;
        background: none;
        color: var(--mr-ink-faint);
        cursor: pointer;
        transition: background .14s var(--mr-ease), color .14s var(--mr-ease);
    }

    .mr-theme button:hover { color: var(--mr-ink-soft); }

    .mr-theme button[aria-pressed="true"] {
        background: var(--mr-panel);
        color: var(--mr-ink);
        box-shadow: var(--mr-shadow-sm);
    }

    .mr-theme svg { display: block; }

    /*
    |----------------------------------------------------------------------
    | Layout
    |----------------------------------------------------------------------
    */

    .mr-main { display: flex; flex: 1; min-height: 0; }

    .mr-list {
        width: 352px;
        flex-shrink: 0;
        border-right: 1px solid var(--mr-line);
        background: var(--mr-panel);
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

    .mr-item {
        display: block;
        position: relative;
        padding: 12px 16px 13px 17px;
        border-bottom: 1px solid var(--mr-line-soft);
        transition: background .12s var(--mr-ease);
    }

    /*
     * The rows deliberately have no entry animation. A staggered fade-in looks
     * pleasant once, but the mailbox is server-rendered and every click is a
     * full page load, so it replayed top-to-bottom on every message you opened,
     * every page change and every filter. Instant is the right answer here.
     */

    @media (prefers-reduced-motion: reduce) {
        .mr-scope * { transition-duration: .01ms !important; }
    }

    .mr-item::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--mr-accent);
        opacity: 0;
        transition: opacity .14s var(--mr-ease);
    }

    .mr-item:hover { background: var(--mr-raised); }

    .mr-item[aria-current="true"] { background: var(--mr-accent-wash); }
    .mr-item[aria-current="true"]::before { opacity: 1; }

    .mr-item-top { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; }

    .mr-item-subject {
        font-weight: 560;
        font-size: 13.5px;
        letter-spacing: -0.012em;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }

    .mr-item-time {
        font-size: 11.5px;
        color: var(--mr-ink-faint);
        flex-shrink: 0;
        font-variant-numeric: tabular-nums;
    }

    .mr-item-to {
        font-size: 12.5px;
        color: var(--mr-ink-soft);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-top: 1px;
    }

    .mr-item-preview {
        font-size: 12.5px;
        color: var(--mr-ink-faint);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin-top: 2px;
    }

    .mr-item-meta { display: flex; gap: 5px; margin-top: 8px; flex-wrap: wrap; }

    .mr-pager {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        margin-top: auto;
        border-top: 1px solid var(--mr-line);
        background: var(--mr-panel);
        position: sticky;
        bottom: 0;
    }

    /*
    |----------------------------------------------------------------------
    | Detail
    |----------------------------------------------------------------------
    */

    .mr-detail { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; background: var(--mr-bg); }

    .mr-detail-head { padding: 20px 24px 16px; background: var(--mr-panel); border-bottom: 1px solid var(--mr-line); }

    .mr-subject {
        margin: 0 0 14px;
        font-size: 19px;
        font-weight: 620;
        letter-spacing: -0.026em;
        line-height: 1.3;
        word-break: break-word;
    }

    .mr-fields { display: grid; grid-template-columns: auto 1fr; gap: 4px 14px; font-size: 13px; align-items: baseline; }

    .mr-field-label {
        color: var(--mr-ink-faint);
        text-align: right;
        white-space: nowrap;
        font-size: 12px;
        font-weight: 500;
    }

    .mr-field-value { color: var(--mr-ink-soft); word-break: break-word; min-width: 0; }

    .mr-actions { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; align-items: center; }

    /*
    |----------------------------------------------------------------------
    | Tabs
    |----------------------------------------------------------------------
    */

    .mr-tabs {
        display: flex;
        gap: 2px;
        padding: 0 24px;
        background: var(--mr-panel);
        border-bottom: 1px solid var(--mr-line);
        overflow-x: auto;
        scrollbar-width: none;
        flex-shrink: 0;
    }

    .mr-tabs::-webkit-scrollbar { display: none; }

    .mr-tab {
        position: relative;
        font: inherit;
        font-size: 13px;
        font-weight: 500;
        padding: 10px 12px 11px;
        border: 0;
        background: none;
        color: var(--mr-ink-mute);
        cursor: pointer;
        white-space: nowrap;
        transition: color .14s var(--mr-ease);
    }

    .mr-tab::after {
        content: "";
        position: absolute;
        left: 12px;
        right: 12px;
        bottom: -1px;
        height: 2px;
        border-radius: 2px 2px 0 0;
        background: var(--mr-accent);
        transform: scaleX(0);
        transition: transform .18s var(--mr-ease);
    }

    .mr-tab:hover { color: var(--mr-ink); }
    .mr-tab[aria-selected="true"] { color: var(--mr-ink); font-weight: 590; }
    .mr-tab[aria-selected="true"]::after { transform: scaleX(1); }

    .mr-tab-count { color: var(--mr-ink-faint); font-variant-numeric: tabular-nums; font-weight: 450; }

    .mr-panel { flex: 1; min-height: 0; overflow: auto; }
    .mr-panel[hidden] { display: none; }

    .mr-frame { width: 100%; height: 100%; border: 0; background: #fff; display: block; }

    .mr-pre {
        margin: 0;
        padding: 20px 24px;
        font-family: var(--mr-mono);
        font-size: 12.5px;
        line-height: 1.65;
        white-space: pre-wrap;
        word-break: break-word;
        color: var(--mr-ink);
        tab-size: 4;
    }

    /*
    |----------------------------------------------------------------------
    | Badges, notices, flashes
    |----------------------------------------------------------------------
    */

    .mr-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 500;
        line-height: 1;
        padding: 3.5px 7px;
        border-radius: 999px;
        background: var(--mr-sunken);
        color: var(--mr-ink-mute);
        border: 1px solid var(--mr-line);
        white-space: nowrap;
    }

    .mr-badge-accent {
        background: var(--mr-accent-wash);
        color: var(--mr-accent);
        border-color: var(--mr-accent-line);
    }

    .mr-notice {
        display: flex;
        gap: 9px;
        align-items: flex-start;
        margin: 14px 24px 0;
        padding: 10px 13px;
        font-size: 12.5px;
        line-height: 1.5;
        border-radius: var(--mr-r-md);
        background: var(--mr-warn-wash);
        border: 1px solid var(--mr-warn-line);
        color: var(--mr-warn-ink);
    }

    .mr-notice svg { flex-shrink: 0; margin-top: 1px; }
    .mr-notice code { font-family: var(--mr-mono); font-size: 11.5px; }

    /*
    | A centred dialog, sharing the lightbox's overlay treatment so the two
    | modals in this UI behave and feel the same. An author display rule beats
    | the user agent's [hidden] { display: none }, so the attribute has to be
    | honoured explicitly or the dialog is permanently open.
    */
    .mr-modal {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: light-dark(rgba(27, 27, 24, .62), rgba(0, 0, 0, .76));
        backdrop-filter: blur(6px);
        animation: mr-fade .16s var(--mr-ease);
    }

    .mr-modal[hidden] { display: none; }

    .mr-modal-card {
        width: 100%;
        max-width: 460px;
        max-height: 100%;
        overflow-y: auto;
        padding: 18px 20px 20px;
        border-radius: var(--mr-r-md);
        border: 1px solid var(--mr-line-strong);
        background: var(--mr-panel);
        box-shadow: var(--mr-shadow-lg, 0 24px 60px rgba(0, 0, 0, .28));
    }

    .mr-modal-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    .mr-modal-title {
        margin: 0;
        font-size: 15px;
        font-weight: 600;
        color: var(--mr-ink);
    }

    .mr-modal-head .mr-btn { margin-left: auto; height: 28px; padding: 0 8px; }

    .mr-modal-body {
        margin: 0 0 10px;
        font-size: 12.5px;
        line-height: 1.6;
        color: var(--mr-ink-soft);
    }

    .mr-modal-body code,
    .mr-modal-meta code { font-family: var(--mr-mono); font-size: 11.5px; }

    .mr-modal-pre {
        margin: 0 0 4px;
        padding: 10px 12px;
        overflow-x: auto;
        border-radius: var(--mr-r-sm);
        background: var(--mr-raised);
        border: 1px solid var(--mr-line);
        font-family: var(--mr-mono);
        font-size: 12px;
        color: var(--mr-ink);
    }

    .mr-modal-label {
        display: block;
        margin-bottom: 6px;
        font-size: 12.5px;
        font-weight: 500;
        color: var(--mr-ink-soft);
    }

    .mr-modal-input {
        width: 100%;
        height: 34px;
        margin-bottom: 12px;
        padding: 0 10px;
        font: inherit;
        font-size: 13px;
        color: var(--mr-ink);
        background: var(--mr-raised);
        border: 1px solid var(--mr-line-strong);
        border-radius: var(--mr-r-sm);
    }

    .mr-modal-input:focus {
        outline: none;
        border-color: var(--mr-accent);
    }

    .mr-modal-foot {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 14px;
    }

    .mr-modal-meta {
        margin-right: auto;
        font-size: 12px;
        color: var(--mr-ink-faint);
    }

    /*
    | A toast, not a banner. Inserting a strip between the header and the panes
    | pushed the entire mailbox down, so whatever you had just clicked moved out
    | from under the cursor. Floating it over the layout says the same thing
    | without moving anything.
    */
    .mr-flash {
        position: fixed;
        /* Bottom rather than top: at the top it sat over the search field and
           the theme controls, which are the things you might want next. */
        bottom: 18px;
        left: 50%;
        z-index: 70;
        display: flex;
        align-items: center;
        gap: 8px;
        max-width: min(560px, calc(100vw - 32px));
        padding: 9px 14px;
        font-size: 13px;
        background: var(--mr-accent-wash);
        color: var(--mr-accent);
        border: 1px solid var(--mr-accent-line);
        border-radius: var(--mr-r-md);
        box-shadow: var(--mr-shadow-lg, 0 12px 32px rgba(0, 0, 0, .18));
        cursor: pointer;
        transform: translateX(-50%);
        animation: mr-toast-in .18s var(--mr-ease);
    }

    .mr-flash[hidden] { display: none; }

    /* The keyframes carry the centring translate, or the animation would drop
       it and the toast would jump to the middle-right on entry. */
    @keyframes mr-toast-in {
        from { opacity: 0; transform: translate(-50%, 8px); }
        to   { opacity: 1; transform: translate(-50%, 0); }
    }

    @media (prefers-reduced-motion: reduce) {
        .mr-flash { animation: none; }
    }

    .mr-modal-error {
        margin: 0 0 12px;
        padding: 9px 11px;
        font-size: 12.5px;
        line-height: 1.5;
        border-radius: var(--mr-r-sm);
        background: var(--mr-warn-wash);
        border: 1px solid var(--mr-warn-line);
        color: var(--mr-warn-ink);
    }

    .mr-poll {
        display: none;
        align-items: center;
        gap: 6px;
        font: inherit;
        font-size: 12.5px;
        font-weight: 500;
        height: 30px;
        padding: 0 11px;
        border-radius: 999px;
        border: 1px solid var(--mr-accent-line);
        background: var(--mr-accent-wash);
        color: var(--mr-accent);
        cursor: pointer;
        white-space: nowrap;
        animation: mr-pulse 2s var(--mr-ease) infinite;
    }

    .mr-poll .mr-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--mr-accent);
    }

    @keyframes mr-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .72; }
    }

    /*
    |----------------------------------------------------------------------
    | Empty states
    |----------------------------------------------------------------------
    */

    .mr-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
        flex: 1;
        min-height: 220px;
        padding: 44px 32px;
        text-align: center;
        color: var(--mr-ink-faint);
        font-size: 13px;
        line-height: 1.6;
    }

    .mr-empty-mark {
        display: grid;
        place-items: center;
        width: 42px;
        height: 42px;
        margin-bottom: 8px;
        border-radius: var(--mr-r-lg);
        background: var(--mr-raised);
        border: 1px solid var(--mr-line);
        color: var(--mr-ink-faint);
    }

    .mr-empty-title { font-size: 14.5px; font-weight: 580; color: var(--mr-ink); letter-spacing: -0.015em; }

    .mr-scope code.mr-code {
        font-family: var(--mr-mono);
        font-size: 12px;
        background: var(--mr-sunken);
        border: 1px solid var(--mr-line);
        color: var(--mr-ink-soft);
        padding: 1.5px 5px;
        border-radius: 5px;
    }

    /*
    |----------------------------------------------------------------------
    | Headers table & attachments
    |----------------------------------------------------------------------
    */

    .mr-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }

    .mr-table td { padding: 8px 24px; border-bottom: 1px solid var(--mr-line-soft); vertical-align: top; }

    .mr-table tr:hover td { background: var(--mr-raised); }

    .mr-table td:first-child {
        font-family: var(--mr-mono);
        font-size: 11.5px;
        color: var(--mr-ink-mute);
        white-space: nowrap;
        width: 1%;
        padding-right: 20px;
    }

    .mr-table td:last-child { word-break: break-word; font-family: var(--mr-mono); font-size: 11.5px; color: var(--mr-ink); }

    .mr-table .mr-note { font-family: var(--mr-sans); font-size: 12px; color: var(--mr-ink-faint); margin-top: 4px; }

    .mr-files { padding: 14px 24px 20px; display: flex; flex-direction: column; gap: 7px; }

    .mr-file {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 10px 13px;
        border: 1px solid var(--mr-line);
        border-radius: var(--mr-r-md);
        background: var(--mr-panel);
        box-shadow: var(--mr-shadow-sm);
        transition: border-color .14s var(--mr-ease), background .14s var(--mr-ease), transform .1s var(--mr-ease);
    }

    a.mr-file:hover { background: var(--mr-raised); border-color: var(--mr-line-strong); transform: translateY(-1px); box-shadow: var(--mr-shadow-md); }

    .mr-file-icon {
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        flex-shrink: 0;
        border-radius: 7px;
        background: var(--mr-sunken);
        color: var(--mr-ink-mute);
    }

    .mr-file-name { font-weight: 520; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .mr-file-meta { font-size: 11.5px; color: var(--mr-ink-faint); margin-left: auto; white-space: nowrap; font-variant-numeric: tabular-nums; }

    /*
    |----------------------------------------------------------------------
    | Attachment rows & previews
    |----------------------------------------------------------------------
    */

    button.mr-file {
        font: inherit;
        color: inherit;
        text-align: left;
        width: 100%;
        cursor: pointer;
    }

    button.mr-file:hover {
        background: var(--mr-raised);
        border-color: var(--mr-line-strong);
        transform: translateY(-1px);
        box-shadow: var(--mr-shadow-md);
    }

    .mr-thumb {
        width: 30px;
        height: 30px;
        flex-shrink: 0;
        border-radius: 7px;
        object-fit: cover;
        background: var(--mr-sunken);
        border: 1px solid var(--mr-line);
    }

    .mr-file-kind {
        font-size: 11px;
        color: var(--mr-ink-faint);
        padding: 2px 6px;
        border: 1px solid var(--mr-line);
        border-radius: 999px;
        white-space: nowrap;
    }

    .mr-file-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }

    .mr-download {
        display: grid;
        place-items: center;
        width: 28px;
        height: 28px;
        flex-shrink: 0;
        border-radius: 6px;
        border: 1px solid var(--mr-line);
        background: var(--mr-panel);
        color: var(--mr-ink-mute);
        transition: color .14s var(--mr-ease), border-color .14s var(--mr-ease);
    }

    .mr-download:hover { color: var(--mr-ink); border-color: var(--mr-line-strong); text-decoration: none; }

    .mr-noprev {
        font-size: 11.5px;
        color: var(--mr-ink-faint);
        white-space: nowrap;
    }

    /*
    |----------------------------------------------------------------------
    | Lightbox
    |----------------------------------------------------------------------
    */

    .mr-lightbox {
        position: fixed;
        inset: 0;
        z-index: 60;
        display: flex;
        flex-direction: column;
        background: light-dark(rgba(27, 27, 24, .62), rgba(0, 0, 0, .76));
        backdrop-filter: blur(6px);
        animation: mr-fade .16s var(--mr-ease);
    }

    .mr-lightbox[hidden] { display: none; }

    @keyframes mr-fade { from { opacity: 0; } to { opacity: 1; } }

    .mr-lightbox-bar {
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

    .mr-lightbox-title { font-size: 13.5px; font-weight: 560; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .mr-lightbox-sub { font-size: 12px; opacity: .72; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .mr-lightbox-tools { display: flex; align-items: center; gap: 6px; margin-left: auto; }

    .mr-lightbox-btn {
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        font: inherit;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: var(--mr-r-sm);
        background: rgba(255, 255, 255, .08);
        color: #fff;
        cursor: pointer;
        flex-shrink: 0;
        transition: background .14s var(--mr-ease);
    }

    .mr-lightbox-btn:hover { background: rgba(255, 255, 255, .18); text-decoration: none; }
    .mr-lightbox-btn[disabled] { opacity: .3; pointer-events: none; }
    .mr-lightbox-btn:focus-visible { box-shadow: 0 0 0 3px rgba(255, 255, 255, .4); }

    .mr-lightbox-body {
        flex: 1;
        min-height: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 8px 16px;
    }

    .mr-lightbox-stage {
        flex: 1;
        min-width: 0;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: auto;
    }

    /* The empty space closes the preview, so let the pointer say so. */
    .mr-lightbox,
    .mr-lightbox-body,
    .mr-lightbox-stage { cursor: zoom-out; }

    .mr-lightbox-bar,
    .mr-lightbox-stage > * { cursor: default; }

    .mr-lightbox-bar button,
    .mr-lightbox-bar a { cursor: pointer; }

    /* Media and documents inside the stage */

    .mr-lightbox-stage img.mr-shot {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        border-radius: var(--mr-r-md);
        background: #fff;
        box-shadow: 0 8px 30px -8px rgba(0, 0, 0, .5);
    }

    .mr-lightbox-stage .mr-doc {
        width: 100%;
        height: 100%;
        border: 0;
        border-radius: var(--mr-r-md);
        background: #fff;
    }

    .mr-lightbox-stage audio,
    .mr-lightbox-stage video {
        max-width: 100%;
        max-height: 100%;
        border-radius: var(--mr-r-md);
    }

    .mr-lightbox-stage audio { width: min(560px, 100%); }

    /* Server-rendered text kinds */

    .mr-sheet {
        width: 100%;
        max-width: 1000px;
        max-height: 100%;
        overflow: auto;
        background: var(--mr-panel);
        border-radius: var(--mr-r-lg);
        box-shadow: 0 8px 30px -8px rgba(0, 0, 0, .5);
    }

    .mr-sheet .mr-pre { padding: 18px 20px; }

    .mr-sheet-note {
        padding: 9px 20px;
        font-size: 12px;
        color: var(--mr-warn-ink);
        background: var(--mr-warn-wash);
        border-bottom: 1px solid var(--mr-warn-line);
    }

    .mr-sheet-fields {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 5px 14px;
        padding: 16px 20px;
        font-size: 13px;
        border-bottom: 1px solid var(--mr-line);
    }

    .mr-sheet-fields dt { color: var(--mr-ink-faint); font-size: 12px; text-align: right; white-space: nowrap; }
    .mr-sheet-fields dd { margin: 0; color: var(--mr-ink); word-break: break-word; white-space: pre-line; }

    .mr-grid { width: 100%; border-collapse: collapse; font-size: 12.5px; font-family: var(--mr-mono); }

    .mr-grid th, .mr-grid td {
        padding: 6px 12px;
        border-bottom: 1px solid var(--mr-line-soft);
        border-right: 1px solid var(--mr-line-soft);
        text-align: left;
        vertical-align: top;
        max-width: 320px;
        overflow-wrap: break-word;
    }

    .mr-grid th {
        position: sticky;
        top: 0;
        background: var(--mr-raised);
        font-weight: 600;
        z-index: 1;
    }

    .mr-grid tbody tr:hover td { background: var(--mr-raised); }
    .mr-grid td:last-child, .mr-grid th:last-child { border-right: 0; }

    .mr-grid-num {
        color: var(--mr-ink-faint);
        text-align: right;
        width: 1%;
        user-select: none;
        font-variant-numeric: tabular-nums;
    }

    .mr-blank {
        display: grid;
        place-items: center;
        gap: 6px;
        padding: 48px 32px;
        text-align: center;
        color: var(--mr-ink-faint);
        font-size: 13px;
    }

    @media (max-width: 900px) {
        .mr-lightbox-body { padding: 0 4px 12px; }
        .mr-lightbox-title { font-size: 12.5px; }
    }

    /*
    |----------------------------------------------------------------------
    | Scrollbars
    |----------------------------------------------------------------------
    */

    .mr-scope ::-webkit-scrollbar { width: 11px; height: 11px; }
    .mr-scope ::-webkit-scrollbar-track { background: transparent; }
    .mr-scope ::-webkit-scrollbar-thumb {
        background: var(--mr-line-strong);
        border-radius: 99px;
        border: 3px solid var(--mr-panel);
    }
    .mr-scope ::-webkit-scrollbar-thumb:hover { background: var(--mr-ink-faint); }

    /*
    |----------------------------------------------------------------------
    | Responsive
    |----------------------------------------------------------------------
    */

    @media (max-width: 900px) {
        .mr-header { height: auto; padding: 10px 14px; flex-wrap: wrap; gap: 10px; }
        .mr-header-actions { width: 100%; margin-left: 0; }
        .mr-filters { flex: 1; }
        .mr-input { width: 100%; min-width: 0; }
        .mr-count { padding-left: 0; border-left: 0; }
        .mr-main { flex-direction: column; }
        .mr-list { width: auto; max-height: 42vh; border-right: 0; border-bottom: 1px solid var(--mr-line); }
        .mr-detail-head, .mr-tabs { padding-left: 16px; padding-right: 16px; }
        .mr-table td { padding-left: 16px; padding-right: 16px; }
        .mr-files { padding-left: 16px; padding-right: 16px; }
    }
</style>
