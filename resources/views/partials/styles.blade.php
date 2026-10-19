<style>
    /*
    |----------------------------------------------------------------------
    | Palette
    |----------------------------------------------------------------------
    |
    | Warm neutrals with Laravel's red-orange accent. Inlined rather than
    | published so the mailbox styles itself with no build step, no npm and
    | no network -- it has to work on a plane and inside an air-gapped
    | container. That is also why the type is the system stack: a web font
    | would be a request the mailbox cannot promise to make.
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
        /* The ground the reader card floats on, a step darker than the panels
           so the card lifts off it without needing a heavy shadow. */
        --mr-bg:          light-dark(#f1f0ec, #0b0b0a);
        --mr-side:        light-dark(#ecebe7, #111110);
        --mr-panel:       light-dark(#ffffff, #191917);
        --mr-raised:      light-dark(#f7f6f3, #201f1d);
        --mr-sunken:      light-dark(#f3f2ef, #121211);
        --mr-pill:        light-dark(#ffffff, #2a2927);
        --mr-line:        light-dark(#e4e2dc, #2c2b28);
        --mr-line-soft:   light-dark(#eeece8, #232220);
        --mr-line-strong: light-dark(#d8d5ce, #3b3a36);

        --mr-ink:         light-dark(#1b1b18, #ededec);
        --mr-ink-soft:    light-dark(#55534e, #b6b4ae);
        /* The smallest text in the mailbox (previews, times, labels) is mute,
           so mute is what has to clear WCAG AA's 4.5:1: 5.2:1 on the white
           panel, 5.0:1 on the dark one. */
        --mr-ink-mute:    light-dark(#6e6c66, #8f8d87);
        --mr-ink-faint:   light-dark(#a3a09a, #6b6963);

        --mr-accent:      light-dark(#f53003, #ff4433);
        /* The accent is too light to read as small text (3.9:1 on white), so
           words in the accent colour use this darker step instead: 5.8:1. */
        --mr-accent-text: light-dark(#c42702, #ff7466);
        --mr-accent-ink:  #ffffff;
        --mr-accent-wash: light-dark(#fef0ea, #2a1512);
        --mr-accent-line: light-dark(#f9c4b3, #5c261d);
        --mr-selected:    light-dark(#fdece5, #2a1714);

        --mr-danger:      light-dark(#c53030, #f87171);

        /*
         * Eight tones for avatars, shared with the coloured count badges. Each
         * pair clears 4.5:1 ink on ground in both themes, so the initials and
         * the numbers stay readable whichever tone an address lands on.
         */
        --mr-tone-0-bg: light-dark(#fde5dc, #3a1a10); --mr-tone-0-ink: light-dark(#9a2a0a, #ffb39b);
        --mr-tone-1-bg: light-dark(#e2edfb, #13253b); --mr-tone-1-ink: light-dark(#1d4f89, #9cc6f5);
        --mr-tone-2-bg: light-dark(#e1f3e7, #12291b); --mr-tone-2-ink: light-dark(#1f6a3e, #9ad8ae);
        --mr-tone-3-bg: light-dark(#f1e6fa, #2a1837); --mr-tone-3-ink: light-dark(#6a2d8e, #d3a8ef);
        --mr-tone-4-bg: light-dark(#fcf0d4, #33280f); --mr-tone-4-ink: light-dark(#7a5a13, #e8c77a);
        --mr-tone-5-bg: light-dark(#ddf2f2, #0f2b2c); --mr-tone-5-ink: light-dark(#155e63, #8fd6d9);
        --mr-tone-6-bg: light-dark(#fbe3ec, #33141f); --mr-tone-6-ink: light-dark(#8f2451, #f2a3c0);
        --mr-tone-7-bg: light-dark(#ecebe6, #2a2a27); --mr-tone-7-ink: light-dark(#4a4843, #c9c7c1);

        --mr-shadow-sm:   light-dark(0 1px 2px rgba(27, 27, 24, .07), 0 1px 2px rgba(0, 0, 0, .45));
        --mr-shadow-card: light-dark(
                              0 1px 2px rgba(27, 27, 24, .04), 0 1px 2px rgba(0, 0, 0, .35)
                          ),
                          light-dark(
                              0 10px 30px -8px rgba(27, 27, 24, .12), 0 14px 36px -10px rgba(0, 0, 0, .7)
                          );
        --mr-shadow-lg:   0 24px 60px -12px rgba(0, 0, 0, .35);
        --mr-ring:        light-dark(0 0 0 3px rgba(245, 48, 3, .16), 0 0 0 3px rgba(255, 68, 51, .22));
        --mr-veil:        light-dark(rgba(27, 27, 24, .42), rgba(0, 0, 0, .66));

        --mr-r-sm: 7px;
        --mr-r-md: 10px;
        --mr-r-lg: 12px;
        --mr-r-xl: 16px;

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
        line-height: 1.5;
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

    /*
     * Visually hidden, still announced. The icon toolbar keeps its labels here
     * so screen readers (and the test suite) hear "Forward" rather than an
     * unnamed button; on touch screens, where a hover tooltip never appears,
     * the narrow layout turns them visible again.
     */
    .mr-sr {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
        border: 0;
    }

    /*
     * The rows deliberately have no entry animation. A staggered fade-in looks
     * pleasant once, but the mailbox is server-rendered and every click is a
     * full page load, so it replayed top-to-bottom on every message you opened,
     * every page change and every filter. Instant is the right answer here.
     */

    @media (prefers-reduced-motion: reduce) {
        .mr-scope * { transition-duration: .01ms !important; animation: none !important; }
    }

    /*
    |----------------------------------------------------------------------
    | Shell
    |----------------------------------------------------------------------
    |
    | Sidebar, list, reader. Below 1200px the sidebar becomes a rail of
    | icons, and below 900px the three become screens of their own; both
    | live in the Responsive section at the end.
    |
    */

    .mr-app { display: flex; height: 100vh; height: 100dvh; min-height: 0; }

    /*
    |----------------------------------------------------------------------
    | Controls
    |----------------------------------------------------------------------
    */

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
        border-radius: var(--mr-r-md);
        border: 1px solid var(--mr-line);
        background: var(--mr-panel);
        color: var(--mr-ink);
        cursor: pointer;
        white-space: nowrap;
        box-shadow: var(--mr-shadow-sm);
        transition: background .14s var(--mr-ease), border-color .14s var(--mr-ease), transform .1s var(--mr-ease);
    }

    .mr-btn:hover { background: var(--mr-raised); border-color: var(--mr-line-strong); }
    .mr-btn:active { transform: translateY(.5px); }

    .mr-btn-primary { background: var(--mr-accent); border-color: transparent; color: var(--mr-accent-ink); }
    .mr-btn-primary:hover { background: var(--mr-accent); border-color: transparent; filter: brightness(1.06); }

    .mr-btn-ghost { background: transparent; border-color: transparent; box-shadow: none; color: var(--mr-ink-soft); }
    .mr-btn-ghost:hover { background: var(--mr-raised); border-color: transparent; color: var(--mr-ink); }

    .mr-btn-danger { color: var(--mr-danger); }
    .mr-btn-danger:hover { color: var(--mr-danger); }

    .mr-btn-sm { height: 28px; padding: 0 10px; font-size: 12px; }

    .mr-btn[aria-disabled="true"] { opacity: .4; pointer-events: none; }

    .mr-inline-form { display: inline-flex; }

    /*
     * The segmented control: a sunken track with the chosen option raised out
     * of it. Tabs, the preview width switch and the theme switch all share it,
     * so choosing one of several reads the same way everywhere.
     */
    .mr-seg {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        padding: 3px;
        border-radius: var(--mr-r-md);
        background: var(--mr-sunken);
        border: 1px solid var(--mr-line-soft);
        flex-shrink: 0;
    }

    .mr-seg button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        height: 28px;
        min-width: 28px;
        padding: 0 9px;
        border: 0;
        border-radius: var(--mr-r-sm);
        background: none;
        font: inherit;
        font-size: 12.5px;
        font-weight: 500;
        line-height: 1;
        white-space: nowrap;
        color: var(--mr-ink-mute);
        cursor: pointer;
        transition: background .14s var(--mr-ease), color .14s var(--mr-ease);
    }

    .mr-seg button:hover { color: var(--mr-ink); }

    .mr-seg button[aria-pressed="true"],
    .mr-seg button[aria-selected="true"] {
        background: var(--mr-pill);
        color: var(--mr-ink);
        box-shadow: var(--mr-shadow-sm);
    }

    .mr-seg svg { display: block; flex-shrink: 0; }

    .mr-theme button { padding: 0; }

    .mr-scope kbd,
    .mr-kbd {
        display: inline-grid;
        place-items: center;
        min-width: 18px;
        height: 19px;
        padding: 0 5px;
        font-family: var(--mr-mono);
        font-size: 11px;
        line-height: 1;
        color: var(--mr-ink-soft);
        background: var(--mr-panel);
        border: 1px solid var(--mr-line);
        border-bottom-width: 2px;
        border-radius: 5px;
    }

    /*
    |----------------------------------------------------------------------
    | Badges
    |----------------------------------------------------------------------
    |
    | Counts are written one way: .mr-badge, a pill, wherever a number appears,
    | in the sidebar, the list and the tab row. The colour variants carry meaning
    | rather than decoration: blue for attachments, and a spam score in green,
    | amber or red by band (see SpamResult::band()). A grey count is a count;
    | a coloured one is telling you something.
    |
    */

    .mr-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 500;
        line-height: 1;
        padding: 4px 7px;
        border-radius: 999px;
        background: var(--mr-sunken);
        color: var(--mr-ink-mute);
        border: 1px solid var(--mr-line);
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .mr-badge svg { display: block; flex-shrink: 0; }

    .mr-badge-accent { background: var(--mr-accent-wash); color: var(--mr-accent-text); border-color: var(--mr-accent-line); }

    .mr-badge-info,
    .mr-badge-ok,
    .mr-badge-warn,
    .mr-badge-bad { font-size: 11.5px; font-weight: 600; border-color: transparent; }

    .mr-badge-info { background: var(--mr-tone-1-bg); color: var(--mr-tone-1-ink); }
    .mr-badge-ok   { background: var(--mr-tone-2-bg); color: var(--mr-tone-2-ink); }
    .mr-badge-warn { background: var(--mr-tone-4-bg); color: var(--mr-tone-4-ink); }
    .mr-badge-bad  { background: var(--mr-tone-0-bg); color: var(--mr-tone-0-ink); }

    /*
    |----------------------------------------------------------------------
    | Avatars
    |----------------------------------------------------------------------
    */

    .mr-avatar {
        display: grid;
        place-items: center;
        width: 36px;
        height: 36px;
        flex-shrink: 0;
        border-radius: 50%;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 0;
        line-height: 1;
    }

    .mr-avatar-sm { width: 24px; height: 24px; font-size: 10px; }
    .mr-avatar-lg { width: 40px; height: 40px; font-size: 14px; }

    .mr-tone-0 { background: var(--mr-tone-0-bg); color: var(--mr-tone-0-ink); }
    .mr-tone-1 { background: var(--mr-tone-1-bg); color: var(--mr-tone-1-ink); }
    .mr-tone-2 { background: var(--mr-tone-2-bg); color: var(--mr-tone-2-ink); }
    .mr-tone-3 { background: var(--mr-tone-3-bg); color: var(--mr-tone-3-ink); }
    .mr-tone-4 { background: var(--mr-tone-4-bg); color: var(--mr-tone-4-ink); }
    .mr-tone-5 { background: var(--mr-tone-5-bg); color: var(--mr-tone-5-ink); }
    .mr-tone-6 { background: var(--mr-tone-6-bg); color: var(--mr-tone-6-ink); }
    .mr-tone-7 { background: var(--mr-tone-7-bg); color: var(--mr-tone-7-ink); }

    /*
    |----------------------------------------------------------------------
    | Sidebar
    |----------------------------------------------------------------------
    */

    .mr-side {
        width: 232px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        padding: 16px 12px 14px;
        background: var(--mr-side);
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
    }

    .mr-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 4px 8px 14px;
        font-weight: 600;
        font-size: 15px;
        letter-spacing: -0.02em;
        white-space: nowrap;
    }

    .mr-mark {
        display: grid;
        place-items: center;
        width: 28px;
        height: 28px;
        flex-shrink: 0;
        border-radius: 8px;
        background: var(--mr-accent);
        color: var(--mr-accent-ink);
        box-shadow: var(--mr-shadow-sm);
    }

    .mr-mark svg { display: block; }

    .mr-side-sec {
        margin: 0;
        padding: 16px 10px 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--mr-ink-mute);
    }

    .mr-nav { display: flex; flex-direction: column; gap: 2px; }

    .mr-nav-link {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        height: 34px;
        padding: 0 8px 0 10px;
        border-radius: 9px;
        font-size: 13.5px;
        font-weight: 500;
        color: var(--mr-ink-soft);
        transition: background .12s var(--mr-ease), color .12s var(--mr-ease);
    }

    .mr-nav-link:hover { background: var(--mr-raised); color: var(--mr-ink); }

    /* The sidebar's one current view, whether a mailbox, a person or a mailer. */
    .mr-nav-link[aria-current] { background: var(--mr-pill); color: var(--mr-ink); box-shadow: var(--mr-shadow-sm); }

    .mr-nav-icon { display: block; flex-shrink: 0; color: var(--mr-ink-mute); }
    .mr-nav-link[aria-current] .mr-nav-icon { color: var(--mr-accent); }

    .mr-nav-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* Only the rail uses these, in place of the counts it has no room for. */
    .mr-nav-dot,
    .mr-nav-letter { display: none; }

    .mr-people { display: flex; flex-direction: column; gap: 1px; }

    .mr-person {
        display: flex;
        align-items: center;
        gap: 9px;
        min-height: 40px;
        padding: 3px 8px;
        border-radius: 9px;
        transition: background .12s var(--mr-ease);
    }

    .mr-person:hover { background: var(--mr-raised); }
    .mr-person[aria-current] { background: var(--mr-pill); box-shadow: var(--mr-shadow-sm); }

    .mr-person-text { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }

    .mr-person-address,
    .mr-person-summary { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .mr-person-address { font-size: 13px; font-weight: 500; color: var(--mr-ink); }
    .mr-person-summary { font-size: 11.5px; color: var(--mr-ink-mute); }

    .mr-side-note { margin: 0; padding: 4px 10px; font-size: 12.5px; color: var(--mr-ink-mute); line-height: 1.5; }

    /*
     * People is a native disclosure, so there is no `hidden` attribute for an
     * author display rule to beat. On a wide screen its summary is the section
     * heading; the rail and phone rules below turn it into a button.
     */
    .mr-people-toggle {
        display: flex;
        align-items: center;
        list-style: none;
        cursor: pointer;
        margin: 0;
        padding: 16px 10px 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .07em;
        color: var(--mr-ink-mute);
    }

    .mr-people-toggle::-webkit-details-marker { display: none; }
    .mr-people-icon { display: none; }

    .mr-people-panel { display: flex; flex-direction: column; gap: 4px; }

    .mr-find { padding: 0 2px 4px; }

    .mr-find .mr-input { height: 30px; padding: 0 28px 0 28px; font-size: 12.5px; background: var(--mr-panel); border-color: var(--mr-line); }
    .mr-find .mr-field > svg { left: 9px; }

    .mr-people-more,
    .mr-people-empty { margin: 0; padding: 4px 10px 0; font-size: 12px; color: var(--mr-ink-mute); line-height: 1.5; }

    .mr-people-more:empty { display: none; }
    .mr-people-empty { font-size: 12.5px; color: var(--mr-ink-soft); }
    .mr-people-empty span { display: block; font-size: 12px; color: var(--mr-ink-mute); }

    /*
     * Search matches, marked by Highlight::mark(). The same tint as the
     * accent badges, so a match reads as "this is why it is here".
     */
    .mr-scope mark { padding: 0 1px; border-radius: 3px; background: var(--mr-accent-wash); color: var(--mr-accent-text); }

    /*
     * Inside a search field: the clear button once there is text, a spinner
     * while results load. Both are always in the markup and shown by state,
     * :placeholder-shown for the clear and .is-loading for the spinner, so
     * there is no `hidden` attribute to fight.
     */
    .mr-input-clear,
    .mr-spin { position: absolute; display: none; }

    .mr-input-clear {
        right: 6px;
        width: 24px;
        height: 24px;
        place-items: center;
        border-radius: 50%;
        background: var(--mr-sunken);
        color: var(--mr-ink-soft);
    }

    .mr-input-clear:hover { background: var(--mr-line); color: var(--mr-ink); }
    .mr-find .mr-input-clear { right: 5px; width: 20px; height: 20px; }

    .mr-input:not(:placeholder-shown) ~ .mr-input-clear { display: grid; }

    .mr-spin {
        right: 11px;
        width: 13px;
        height: 13px;
        border-radius: 50%;
        border: 2px solid var(--mr-line);
        border-top-color: var(--mr-accent);
        animation: mr-spin .7s linear infinite;
    }

    .mr-field.is-loading .mr-spin { display: block; }
    .mr-field.is-loading .mr-input-clear { display: none; }

    @keyframes mr-spin { to { transform: rotate(360deg); } }

    .mr-side-foot { margin-top: auto; display: flex; flex-direction: column; gap: 12px; padding: 12px 4px 0; }

    .mr-hint { display: flex; align-items: center; gap: 5px; padding: 0 6px; font-size: 12px; white-space: nowrap; color: var(--mr-ink-mute); }

    .mr-side-actions { display: flex; align-items: center; justify-content: space-between; gap: 8px; }

    /* The rail swaps the words for an icon; everywhere else it is the words. */
    .mr-clear-icon { display: none; }

    /*
    |----------------------------------------------------------------------
    | Message list
    |----------------------------------------------------------------------
    */

    .mr-listcol {
        width: 380px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        min-height: 0;
        background: var(--mr-panel);
        border-left: 1px solid var(--mr-line);
        border-right: 1px solid var(--mr-line);
    }

    .mr-list-head { display: flex; flex-direction: column; gap: 12px; padding: 18px 16px 12px; border-bottom: 1px solid var(--mr-line-soft); }

    .mr-list-heading { display: flex; align-items: center; gap: 10px; min-width: 0; }

    .mr-list-titles { min-width: 0; }

    .mr-list-title {
        margin: 0;
        font-size: 20px;
        font-weight: 600;
        letter-spacing: -0.025em;
        line-height: 1.2;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mr-list-sub { margin-top: 2px; font-size: 12.5px; color: var(--mr-ink-mute); font-variant-numeric: tabular-nums; }

    /*
     * Hidden until the poll sees new mail, then set to inline-flex by the
     * script. Its default is display: none rather than a `hidden` attribute,
     * so there is no attribute for this rule to fight.
     */
    .mr-poll {
        display: none;
        align-items: center;
        gap: 6px;
        margin-left: auto;
        height: 28px;
        padding: 0 11px;
        flex-shrink: 0;
        border-radius: 999px;
        border: 1px solid var(--mr-accent-line);
        background: var(--mr-accent-wash);
        color: var(--mr-accent-text);
        font: inherit;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        cursor: pointer;
        animation: mr-pulse 2s var(--mr-ease) infinite;
    }

    .mr-poll .mr-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--mr-accent); }

    @keyframes mr-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .72; }
    }

    .mr-search { display: flex; flex-direction: column; gap: 8px; }

    .mr-field { position: relative; display: flex; align-items: center; }

    /* The magnifier only: a direct child, so the icon inside .mr-input-clear stays
       centred in its button. */
    .mr-field > svg { position: absolute; left: 11px; color: var(--mr-ink-mute); pointer-events: none; }

    .mr-field .mr-kbd { position: absolute; right: 9px; pointer-events: none; }

    .mr-input {
        appearance: none;
        width: 100%;
        height: 36px;
        padding: 0 34px;
        font: inherit;
        font-size: 13.5px;
        color: var(--mr-ink);
        background: var(--mr-sunken);
        border: 1px solid transparent;
        border-radius: var(--mr-r-md);
        transition: border-color .14s var(--mr-ease), background .14s var(--mr-ease);
    }

    .mr-input::placeholder { color: var(--mr-ink-mute); }
    .mr-input:hover { border-color: var(--mr-line); }
    .mr-input:focus { outline: none; background: var(--mr-panel); border-color: var(--mr-accent); box-shadow: var(--mr-ring); }
    /* Chrome and Safari draw their own clear button in a search field; the
       .mr-input-clear button replaces it, consistently across browsers. */
    .mr-input::-webkit-search-cancel-button { -webkit-appearance: none; appearance: none; display: none; }

    /* The key hint gets out of the way once someone is typing. */
    .mr-input:focus + .mr-kbd,
    .mr-input:not(:placeholder-shown) + .mr-kbd { display: none; }


    .mr-list {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .mr-rows { flex: 1 0 auto; display: flex; flex-direction: column; gap: 2px; padding: 6px 8px; }

    .mr-item {
        position: relative;
        display: flex;
        gap: 12px;
        padding: 11px 12px 12px 14px;
        border-radius: var(--mr-r-lg);
        transition: background .12s var(--mr-ease);
    }

    .mr-item:hover { background: var(--mr-raised); }
    .mr-item[aria-current="true"] { background: var(--mr-selected); }

    .mr-unread-dot {
        position: absolute;
        left: 4px;
        top: 25px;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--mr-accent);
    }

    .mr-item-body { flex: 1; min-width: 0; display: flex; flex-direction: column; }

    .mr-item-top { display: flex; align-items: baseline; gap: 8px; }

    .mr-item-who,
    .mr-item-subject { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .mr-item-who { flex: 1; min-width: 0; font-size: 13.5px; font-weight: 600; color: var(--mr-ink); }

    .mr-item-more { font-weight: 500; color: var(--mr-ink-mute); }

    .mr-item-time { flex-shrink: 0; font-size: 12px; color: var(--mr-ink-mute); font-variant-numeric: tabular-nums; }

    /* An unread row's time takes the accent, the way a mail client flags new mail. */
    .mr-item:has(.mr-unread-dot) .mr-item-time { color: var(--mr-accent-text); font-weight: 500; }

    .mr-item-subject { font-size: 13.5px; font-weight: 600; color: var(--mr-ink); }

    /*
        Read rows step down a weight rather than greying out, so the list still
        reads as a list. The heavier weight stays the default, which is what a
        mailbox with nobody signed in keeps showing: read state is the only
        thing that ever lightens a row.
    */
    .mr-item-read .mr-item-who { font-weight: 500; color: var(--mr-ink-soft); }
    .mr-item-read .mr-item-subject { font-weight: 500; }

    .mr-item-preview {
        margin-top: 1px;
        font-size: 12.5px;
        line-height: 1.45;
        color: var(--mr-ink-mute);
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .mr-item-meta { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 7px; }

    /*
        One footer for everything that acts on the list as a whole: marking the
        page, the pager, and on a phone Clear all. Sticky, so it stays in reach
        however far down the list has scrolled.
    */
    .mr-list-foot {
        position: sticky;
        bottom: 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
        padding: 8px 10px;
        border-top: 1px solid var(--mr-line-soft);
        background: var(--mr-panel);
    }

    /* With nobody signed in and one page, the footer holds only the phone's
       Clear all, so wider screens have nothing to show in it. */
    .mr-list-foot:has(> .mr-clear-narrow:only-child) { display: none; }

    .mr-list-actions { display: flex; align-items: center; gap: 4px; }

    /* The short labels are for narrow screens; wide ones spell the page out. */
    .mr-short { display: none; }

    .mr-pager {
        display: flex;
        align-items: center;
        gap: 2px;
        margin-left: auto;
        font-size: 12px;
        color: var(--mr-ink-mute);
        font-variant-numeric: tabular-nums;
    }

    .mr-icon-btn {
        display: grid;
        place-items: center;
        width: 30px;
        height: 30px;
        flex-shrink: 0;
        border: 0;
        border-radius: 8px;
        background: none;
        color: var(--mr-ink-soft);
        cursor: pointer;
    }

    .mr-icon-btn:hover { background: var(--mr-raised); color: var(--mr-ink); }
    .mr-icon-btn[aria-disabled="true"] { opacity: .35; pointer-events: none; }
    .mr-icon-btn svg { display: block; }

    /* Only phones show this; see the Responsive section. */
    .mr-clear-narrow { display: none; }

    /*
    |----------------------------------------------------------------------
    | Reader
    |----------------------------------------------------------------------
    */

    .mr-detail { flex: 1; min-width: 0; display: flex; padding: 12px; }

    .mr-card {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: var(--mr-panel);
        border: 1px solid var(--mr-line-soft);
        border-radius: var(--mr-r-xl);
        box-shadow: var(--mr-shadow-card);
    }

    .mr-detail-head { padding: 22px 28px 16px; }

    /* A phone's way back to the list; see the Responsive section. */
    .mr-back { display: none; }

    /*
        Subject and actions share the top row. align-items: flex-start keeps
        the toolbar pinned to the first line when a very long subject wraps --
        the subject is the one field worth letting grow, so it is not truncated.
    */
    .mr-head-top { display: flex; align-items: flex-start; gap: 16px; }

    .mr-subject {
        flex: 1;
        min-width: 0;
        margin: 0;
        /* Nudged down rather than given a loose line-height, so a subject that
           wraps stays tight while its first line still sits level with the
           toolbar beside it. */
        padding-top: 3px;
        font-size: 22px;
        font-weight: 600;
        letter-spacing: -0.025em;
        line-height: 1.3;
        word-break: break-word;
    }

    .mr-actions { display: flex; gap: 8px; flex-shrink: 0; }

    .mr-tool-group {
        display: flex;
        gap: 2px;
        padding: 3px;
        border-radius: 11px;
        background: var(--mr-sunken);
        border: 1px solid var(--mr-line-soft);
    }

    .mr-tool {
        display: grid;
        place-items: center;
        width: 34px;
        height: 30px;
        padding: 0;
        font: inherit;
        color: var(--mr-ink-soft);
        background: none;
        border: 0;
        border-radius: 8px;
        cursor: pointer;
        transition: background .12s var(--mr-ease), color .12s var(--mr-ease);
    }

    .mr-tool:hover { background: var(--mr-pill); color: var(--mr-ink); box-shadow: var(--mr-shadow-sm); }
    .mr-tool svg { display: block; }

    .mr-tool-danger,
    .mr-tool-danger:hover { color: var(--mr-danger); }

    /* Same technique as .mr-sr; the narrow layout undoes it. */
    .mr-tool-label {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }

    .mr-sender { display: flex; align-items: flex-start; gap: 12px; margin-top: 16px; }

    .mr-who { flex: 1; min-width: 0; font-size: 13px; }

    .mr-who-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; font-weight: 600; }

    .mr-who-address { margin-left: 6px; font-weight: 400; color: var(--mr-ink-mute); }

    .mr-when { flex-shrink: 0; padding-top: 2px; font-size: 12.5px; color: var(--mr-ink-mute); font-variant-numeric: tabular-nums; }

    /* The time sits in the header's right column until a phone folds it in
       beside the sender's name. */
    .mr-who-time { display: none; margin-left: 8px; font-size: 12.5px; font-weight: 400; color: var(--mr-ink-mute); }

    /*
        The recipients line is the disclosure's summary, as in most mail
        clients: the line that already names the recipients is what opens the
        full list, so the details cost no control and no row of their own.
    */
    .mr-meta-line {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        max-width: 100%;
        margin-left: -6px;
        padding: 1px 6px;
        list-style: none;
        cursor: pointer;
        border-radius: 6px;
        color: var(--mr-ink-soft);
    }

    .mr-meta-line:hover { background: var(--mr-raised); color: var(--mr-ink); }

    /* Safari ignores list-style on a summary and draws its own triangle. */
    .mr-meta-line::-webkit-details-marker { display: none; }

    /* This wrapper is what the recipients truncate against, so its min-width
       and overflow are load-bearing rather than tidiness. */
    .mr-meta-to { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .mr-meta-chevron { display: block; flex-shrink: 0; color: var(--mr-ink-mute); transition: transform .18s var(--mr-ease); }
    .mr-meta[open] .mr-meta-chevron { transform: rotate(180deg); }

    .mr-fields {
        display: grid;
        grid-template-columns: auto 1fr;
        align-items: baseline;
        gap: 6px 16px;
        margin: 10px 0 0;
        padding: 12px 16px;
        font-size: 13px;
        background: var(--mr-sunken);
        border: 1px solid var(--mr-line-soft);
        border-radius: var(--mr-r-lg);
    }

    .mr-fields dt { color: var(--mr-ink-mute); font-size: 12px; font-weight: 500; text-align: right; white-space: nowrap; }

    .mr-fields dd { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; min-width: 0; margin: 0; color: var(--mr-ink-soft); word-break: break-word; }

    /*
    |----------------------------------------------------------------------
    | Tabs and panes
    |----------------------------------------------------------------------
    */

    .mr-tabs { display: flex; align-items: center; gap: 12px; padding: 0 28px 14px; border-bottom: 1px solid var(--mr-line-soft); }

    .mr-tablist-wrap { min-width: 0; }

    /* Preview width switch, pushed to the far end of the tab row */
    .mr-viewport { margin-left: auto; }

    /*
        Spelled out because an author display rule wins against the user agent
        stylesheet's [hidden] { display: none }, and the tab script relies on
        the attribute to take the control off the other panes.
    */
    .mr-viewport[hidden] { display: none; }

    .mr-panel { flex: 1; min-height: 0; overflow: auto; }
    .mr-panel[hidden] { display: none; }

    .mr-frame { display: block; width: 100%; height: 100%; border: 0; background: #fff; }

    /*
        An iframe carries its own viewport, so narrowing the frame is the whole
        mechanism: the email's own media queries fire at that width, with no
        change to how the body is served and the sandbox left shut. Keyed off
        the pane rather than .mr-frame, which the Raw pane uses too.

        :not([hidden]) is load-bearing here. This selector and .mr-panel[hidden]
        above have identical specificity, so without it the later rule wins and
        the body keeps rendering underneath whichever tab you switch to.
    */
    .mr-panel[data-mr-viewport="tablet"]:not([hidden]),
    .mr-panel[data-mr-viewport="mobile"]:not([hidden]) {
        display: flex;
        justify-content: center;
        background: var(--mr-sunken);
    }

    /*
        Only the width is constrained. No vertical padding, so the frame meets
        the tab row and the foot of the pane exactly as it does on desktop --
        inset it and switching width appears to change the height too, which is
        not what any of these devices do.

        The width itself is deliberately not clamped to the pane: a frame that
        quietly rendered narrower than the button claims would make the preview
        a lie, so the pane scrolls sideways instead on a display too small to
        hold it.
    */
    .mr-panel[data-mr-viewport="tablet"] .mr-frame,
    .mr-panel[data-mr-viewport="mobile"] .mr-frame {
        flex: none;
        border-left: 1px solid var(--mr-line);
        border-right: 1px solid var(--mr-line);
    }

    .mr-panel[data-mr-viewport="tablet"] .mr-frame { width: 768px; }
    .mr-panel[data-mr-viewport="mobile"] .mr-frame { width: 375px; }

    .mr-pre {
        margin: 0;
        padding: 20px 28px;
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
    | Notices, dialogs, toasts
    |----------------------------------------------------------------------
    */

    .mr-notice {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin: 0 28px 14px;
        padding: 11px 14px;
        font-size: 12.5px;
        line-height: 1.5;
        border-radius: var(--mr-r-lg);
        background: var(--mr-tone-4-bg);
        color: var(--mr-tone-4-ink);
    }

    .mr-notice svg { flex-shrink: 0; margin-top: 2px; }
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
        background: var(--mr-veil);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        animation: mr-fade .16s var(--mr-ease);
    }

    .mr-modal[hidden] { display: none; }

    .mr-modal-card {
        width: 100%;
        max-width: 480px;
        max-height: 100%;
        overflow-y: auto;
        padding: 20px 22px;
        border-radius: 18px;
        border: 1px solid var(--mr-line-soft);
        background: var(--mr-panel);
        box-shadow: var(--mr-shadow-lg);
    }

    .mr-modal-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }

    .mr-modal-title { margin: 0; font-size: 16px; font-weight: 600; letter-spacing: -0.015em; color: var(--mr-ink); }

    .mr-modal-close { margin-left: auto; background: var(--mr-sunken); }

    .mr-modal-body { margin: 0 0 10px; font-size: 12.5px; line-height: 1.6; color: var(--mr-ink-soft); }

    .mr-modal-body code,
    .mr-modal-meta code {
        font-family: var(--mr-mono);
        font-size: 11.5px;
        padding: 1px 5px;
        background: var(--mr-sunken);
        border: 1px solid var(--mr-line);
        border-radius: 5px;
    }

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

    .mr-modal-label { display: block; margin-bottom: 6px; font-size: 12.5px; font-weight: 500; color: var(--mr-ink-soft); }

    .mr-modal-input {
        width: 100%;
        height: 40px;
        margin-bottom: 12px;
        padding: 0 12px;
        font: inherit;
        font-size: 14px;
        color: var(--mr-ink);
        background: var(--mr-panel);
        border: 1px solid var(--mr-line-strong);
        border-radius: var(--mr-r-md);
    }

    .mr-modal-input:focus { outline: none; border-color: var(--mr-accent); box-shadow: var(--mr-ring); }

    .mr-modal-foot { display: flex; align-items: center; gap: 8px; margin-top: 18px; }

    .mr-modal-meta { margin-right: auto; font-size: 12px; color: var(--mr-ink-mute); }

    .mr-modal-error {
        margin: 0 0 12px;
        padding: 10px 12px;
        font-size: 12.5px;
        line-height: 1.5;
        border-radius: var(--mr-r-md);
        background: var(--mr-tone-4-bg);
        color: var(--mr-tone-4-ink);
    }

    /* Forward's second step: the address named before anything is sent. */
    .mr-forward-check:not([hidden]) {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin: 4px 0 0;
        padding: 10px 12px;
        font-size: 12.5px;
        line-height: 1.5;
        border-radius: var(--mr-r-md);
        background: var(--mr-tone-4-bg);
        color: var(--mr-tone-4-ink);
    }

    .mr-forward-check svg { flex-shrink: 0; margin-top: 2px; }
    .mr-forward-check strong { overflow-wrap: anywhere; }

    /* A long address truncates inside the button rather than widening the dialog. */
    #mr-forward-send { min-width: 0; }
    .mr-btn-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .mr-confirm-card { max-width: 400px; padding: 22px 22px 20px; }

    .mr-confirm-icon {
        display: grid;
        place-items: center;
        width: 40px;
        height: 40px;
        margin-bottom: 14px;
        border-radius: 12px;
        background: var(--mr-tone-0-bg);
        color: var(--mr-tone-0-ink);
    }

    .mr-confirm-card .mr-modal-title { margin-bottom: 6px; }
    .mr-confirm-card .mr-modal-body { margin: 0; font-size: 13px; line-height: 1.55; }
    .mr-confirm-card .mr-modal-body strong { font-weight: 500; color: var(--mr-ink); overflow-wrap: anywhere; }
    .mr-confirm-card .mr-modal-foot { justify-content: flex-end; margin-top: 20px; }

    /* Dark mode's danger red is light, so its label turns dark to stay readable. */
    .mr-btn-destructive { background: var(--mr-danger); border-color: transparent; color: light-dark(#fff, #1c0606); }
    .mr-btn-destructive:hover { background: var(--mr-danger); border-color: transparent; filter: brightness(1.06); }

    /*
    | A toast, not a banner. Inserting a strip above the panes pushed the
    | entire mailbox down, so whatever you had just clicked moved out from
    | under the cursor. Floating it over the layout says the same thing
    | without moving anything. Inverted colours so it reads as the app talking
    | rather than as part of the mail.
    */
    .mr-flash {
        position: fixed;
        /* Bottom rather than top: at the top it sat over the search field,
           which is the thing you might want next. */
        bottom: 22px;
        left: 50%;
        z-index: 70;
        display: flex;
        align-items: center;
        gap: 8px;
        max-width: min(560px, calc(100vw - 32px));
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 500;
        background: var(--mr-ink);
        color: var(--mr-panel);
        border-radius: var(--mr-r-lg);
        box-shadow: var(--mr-shadow-lg);
        cursor: pointer;
        transform: translateX(-50%);
        animation: mr-toast-in .18s var(--mr-ease);
    }

    .mr-flash svg { flex-shrink: 0; color: #34c759; }

    .mr-flash[hidden] { display: none; }

    /* The keyframes carry the centring translate, or the animation would drop
       it and the toast would jump to the middle-right on entry. */
    @keyframes mr-toast-in {
        from { opacity: 0; transform: translate(-50%, 8px); }
        to   { opacity: 1; transform: translate(-50%, 0); }
    }

    @keyframes mr-fade { from { opacity: 0; } to { opacity: 1; } }

    /*
    |----------------------------------------------------------------------
    | Empty states
    |----------------------------------------------------------------------
    */

    .mr-empty {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 220px;
        padding: 44px 32px;
        text-align: center;
        font-size: 13.5px;
        line-height: 1.6;
        color: var(--mr-ink-mute);
    }

    .mr-empty-mark {
        display: grid;
        place-items: center;
        width: 52px;
        height: 52px;
        margin-bottom: 8px;
        border-radius: var(--mr-r-xl);
        background: var(--mr-sunken);
        border: 1px solid var(--mr-line-soft);
        color: var(--mr-ink-mute);
    }

    .mr-empty-title { font-size: 16px; font-weight: 600; letter-spacing: -0.015em; color: var(--mr-ink); }

    .mr-scope code.mr-code {
        font-family: var(--mr-mono);
        font-size: 12.5px;
        padding: 2px 6px;
        color: var(--mr-ink);
        background: var(--mr-sunken);
        border: 1px solid var(--mr-line);
        border-radius: 6px;
    }

    /*
    |----------------------------------------------------------------------
    | Headers table
    |----------------------------------------------------------------------
    */

    .mr-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }

    .mr-table td { padding: 8px 28px; border-bottom: 1px solid var(--mr-line-soft); vertical-align: top; }

    .mr-table tr:hover td { background: var(--mr-raised); }

    .mr-table td:first-child {
        width: 1%;
        padding-right: 20px;
        font-family: var(--mr-mono);
        font-size: 11.5px;
        color: var(--mr-ink-mute);
        white-space: nowrap;
    }

    .mr-table td:last-child { word-break: break-word; font-family: var(--mr-mono); font-size: 11.5px; color: var(--mr-ink); }

    .mr-note,
    .mr-table .mr-note { display: block; margin-top: 4px; font-family: var(--mr-sans); font-size: 12px; color: var(--mr-ink-mute); }

    /*
    |----------------------------------------------------------------------
    | Attachments
    |----------------------------------------------------------------------
    */

    .mr-files { display: flex; flex-direction: column; gap: 8px; padding: 20px 28px; }

    .mr-file {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%;
        padding: 10px 14px 10px 10px;
        font: inherit;
        color: inherit;
        text-align: left;
        border: 1px solid var(--mr-line);
        border-radius: var(--mr-r-lg);
        background: var(--mr-panel);
        box-shadow: var(--mr-shadow-sm);
        transition: border-color .14s var(--mr-ease), background .14s var(--mr-ease), transform .1s var(--mr-ease);
    }

    button.mr-file { cursor: pointer; }
    button.mr-file:hover { background: var(--mr-raised); border-color: var(--mr-line-strong); transform: translateY(-1px); }

    .mr-file-icon,
    .mr-thumb {
        width: 44px;
        height: 44px;
        flex-shrink: 0;
        border-radius: 9px;
    }

    .mr-file-icon { display: grid; place-items: center; background: var(--mr-sunken); color: var(--mr-ink-mute); }

    .mr-thumb { object-fit: cover; background: var(--mr-sunken); border: 1px solid var(--mr-line); }

    .mr-file-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }

    .mr-file-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13.5px; font-weight: 500; }

    .mr-file-meta { font-size: 12px; color: var(--mr-ink-mute); font-variant-numeric: tabular-nums; }

    .mr-file-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; font-size: 12px; color: var(--mr-ink-mute); }

    .mr-file-actions svg { display: block; }

    .mr-noprev { font-size: 12px; color: var(--mr-ink-mute); white-space: nowrap; }

    .mr-inline-note { margin: 4px 2px 0; font-size: 12.5px; color: var(--mr-ink-mute); }

    /*
    |----------------------------------------------------------------------
    | Spam check
    |----------------------------------------------------------------------
    */

    .mr-spam { display: flex; flex-direction: column; gap: 14px; padding: 20px 28px; }

    .mr-spam-head { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }

    .mr-spam-score {
        display: grid;
        place-items: center;
        min-width: 64px;
        height: 64px;
        padding: 0 12px;
        border-radius: 14px;
        font-size: 24px;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    /*
        The tile takes the same band as the badge in the tab. Red rather than
        the danger colour past the threshold on purpose: a high score is worth
        looking at, and it is still a third party's estimate of a message that
        has not been delivered yet.
    */
    .mr-spam-score.mr-badge-ok   { background: var(--mr-tone-2-bg); color: var(--mr-tone-2-ink); }
    .mr-spam-score.mr-badge-warn { background: var(--mr-tone-4-bg); color: var(--mr-tone-4-ink); }
    .mr-spam-score.mr-badge-bad  { background: var(--mr-tone-0-bg); color: var(--mr-tone-0-ink); }

    .mr-spam-said { flex: 1; min-width: 240px; font-size: 13px; line-height: 1.55; color: var(--mr-ink-soft); }
    .mr-spam-said strong { color: var(--mr-ink); }

    .mr-spam-rules-wrap { overflow: hidden; border: 1px solid var(--mr-line-soft); border-radius: var(--mr-r-lg); }

    .mr-spam-rules td { padding-left: 14px; padding-right: 14px; }
    .mr-spam-rules tr:last-child td { border-bottom: 0; }
    .mr-spam-rules td:last-child { font-family: var(--mr-sans); }

    .mr-spam-rule-score { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; font-weight: 600; }

    .mr-spam-note { margin: 0; max-width: 760px; font-size: 12.5px; line-height: 1.6; color: var(--mr-ink-mute); }

    .mr-spam-note strong { color: var(--mr-ink); }

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
        background: rgba(12, 12, 11, .72);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        animation: mr-fade .16s var(--mr-ease);
    }

    .mr-lightbox[hidden] { display: none; }

    .mr-lightbox-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        height: 60px;
        padding: 0 18px;
        flex-shrink: 0;
        color: #fff;
        /* Opaque, otherwise the mailbox behind it shows through the bar and
           the filename competes with the page's own controls. */
        background: #1b1b18;
        border-bottom: 1px solid rgba(255, 255, 255, .08);
    }

    .mr-lightbox-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; font-weight: 600; }
    .mr-lightbox-sub { font-size: 12.5px; color: rgba(255, 255, 255, .66); white-space: nowrap; font-variant-numeric: tabular-nums; }
    .mr-lightbox-tools { display: flex; align-items: center; gap: 6px; margin-left: auto; }

    .mr-lightbox-btn {
        display: grid;
        place-items: center;
        width: 34px;
        height: 34px;
        flex-shrink: 0;
        font: inherit;
        color: #fff;
        background: rgba(255, 255, 255, .07);
        border: 1px solid rgba(255, 255, 255, .14);
        border-radius: var(--mr-r-md);
        cursor: pointer;
        transition: background .14s var(--mr-ease);
    }

    .mr-lightbox-btn:hover { background: rgba(255, 255, 255, .16); }
    .mr-lightbox-btn[disabled] { opacity: .3; pointer-events: none; }
    .mr-lightbox-btn:focus-visible { box-shadow: 0 0 0 3px rgba(255, 255, 255, .4); }

    .mr-lightbox-body { flex: 1; min-height: 0; display: flex; align-items: center; gap: 8px; padding: 16px 16px 20px; }

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
        border-radius: var(--mr-r-lg);
        background: #fff;
        box-shadow: 0 24px 60px -12px rgba(0, 0, 0, .6);
    }

    .mr-lightbox-stage .mr-doc { width: 100%; height: 100%; border: 0; border-radius: var(--mr-r-lg); background: #fff; }

    .mr-lightbox-stage audio,
    .mr-lightbox-stage video { max-width: 100%; max-height: 100%; border-radius: var(--mr-r-lg); }

    .mr-lightbox-stage audio { width: min(560px, 100%); }

    /* Server-rendered text kinds */

    .mr-sheet {
        width: 100%;
        max-width: 1000px;
        max-height: 100%;
        overflow: auto;
        background: var(--mr-panel);
        border-radius: var(--mr-r-xl);
        box-shadow: 0 24px 60px -12px rgba(0, 0, 0, .6);
    }

    .mr-sheet .mr-pre { padding: 18px 20px; }

    .mr-sheet-note {
        padding: 9px 20px;
        font-size: 12px;
        color: var(--mr-tone-4-ink);
        background: var(--mr-tone-4-bg);
    }

    .mr-sheet-fields {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 5px 14px;
        padding: 16px 20px;
        font-size: 13px;
        border-bottom: 1px solid var(--mr-line);
    }

    .mr-sheet-fields dt { font-size: 12px; text-align: right; white-space: nowrap; color: var(--mr-ink-mute); }
    .mr-sheet-fields dd { margin: 0; color: var(--mr-ink); word-break: break-word; white-space: pre-line; }

    .mr-grid { width: 100%; border-collapse: collapse; font-size: 12.5px; font-family: var(--mr-mono); }

    .mr-grid th,
    .mr-grid td {
        max-width: 320px;
        padding: 6px 12px;
        text-align: left;
        vertical-align: top;
        overflow-wrap: break-word;
        border-bottom: 1px solid var(--mr-line-soft);
        border-right: 1px solid var(--mr-line-soft);
    }

    .mr-grid th { position: sticky; top: 0; z-index: 1; font-weight: 600; background: var(--mr-raised); }

    .mr-grid tbody tr:hover td { background: var(--mr-raised); }
    .mr-grid td:last-child,
    .mr-grid th:last-child { border-right: 0; }

    .mr-grid-num { width: 1%; text-align: right; color: var(--mr-ink-mute); user-select: none; font-variant-numeric: tabular-nums; }

    .mr-blank {
        display: grid;
        place-items: center;
        gap: 6px;
        padding: 48px 32px;
        text-align: center;
        font-size: 13px;
        color: var(--mr-ink-mute);
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
        border: 3px solid transparent;
        background-clip: padding-box;
    }
    .mr-scope ::-webkit-scrollbar-thumb:hover { background-color: var(--mr-ink-faint); }

    /*
    |----------------------------------------------------------------------
    | Responsive: the rail, 900px to 1199px
    |----------------------------------------------------------------------
    |
    | The sidebar keeps every destination but gives up its words: icons for
    | mailboxes, a button for People, letters for mailers. Each
    | link keeps a title for the pointer and its label in the DOM for screen
    | readers. Counts become an accent dot where there is something waiting.
    |
    */

    @media (min-width: 900px) and (max-width: 1199px) {
        .mr-side { width: 64px; align-items: center; padding: 16px 0 14px; overflow: visible; }

        .mr-brand { padding: 0 0 14px; }
        .mr-brand-name,
        .mr-side-sec,
        .mr-hint,
        .mr-side-note { display: none; }

        .mr-nav,
        .mr-side-more { display: flex; flex-direction: column; align-items: center; gap: 2px; }

        .mr-side-more,
        .mr-side-more > .mr-nav { margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--mr-line); }

        .mr-nav-link { justify-content: center; width: 40px; height: 40px; padding: 0; border-radius: var(--mr-r-md); }

        .mr-nav-label { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
        .mr-nav-link .mr-badge { display: none; }

        .mr-nav-dot {
            display: block;
            position: absolute;
            top: 7px;
            right: 7px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--mr-accent);
            box-shadow: 0 0 0 2px var(--mr-side);
        }

        .mr-nav-mailer .mr-nav-icon { display: none; }
        .mr-nav-letter { display: block; font-size: 12.5px; font-weight: 600; }

        /* People is one button here, opening the same search as a popover. */
        .mr-people-toggle {
            justify-content: center;
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: var(--mr-r-md);
            color: var(--mr-ink-mute);
        }

        .mr-people-block[open] > .mr-people-toggle { background: var(--mr-pill); color: var(--mr-accent); box-shadow: var(--mr-shadow-sm); }
        .mr-people-icon { display: block; }
        .mr-people-heading { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

        /* Fixed rather than absolute, so the side's own overflow cannot clip it. */
        .mr-people-panel {
            position: fixed;
            left: 72px;
            top: 120px;
            z-index: 30;
            width: 300px;
            padding: 10px;
            background: var(--mr-panel);
            border: 1px solid var(--mr-line);
            border-radius: var(--mr-r-lg);
            box-shadow: var(--mr-shadow-lg);
        }

        .mr-find .mr-input { height: 34px; }

        .mr-side-foot { align-items: center; padding: 12px 0 0; }
        .mr-side-actions { flex-direction: column; }
        .mr-theme { flex-direction: column; }

        .mr-clear-text { display: none; }
        .mr-clear-icon { display: block; }
        .mr-clear .mr-btn { width: 40px; height: 40px; justify-content: center; padding: 0; }

        .mr-listcol { width: 330px; }

        /* Rows keep what bears on the message itself: the score and the files. */
        .mr-item-meta .mr-badge-wide { display: none; }

        .mr-list-actions .mr-long { display: none; }
        .mr-list-actions .mr-short { display: inline; }

        .mr-detail { padding: 10px; }
        .mr-detail-head { padding: 18px 20px 14px; }
        .mr-subject { font-size: 19px; padding-top: 4px; }
        .mr-tabs { padding: 0 20px 12px; }
        .mr-notice { margin: 0 20px 14px; }
        .mr-viewport-label { display: none; }
        .mr-viewport button { padding: 0 7px; }
        .mr-files,
        .mr-spam { padding: 16px 20px; }
        .mr-pre { padding: 18px 20px; }
        .mr-table td { padding-left: 20px; padding-right: 20px; }
    }

    /*
    |----------------------------------------------------------------------
    | Responsive: phones, below 900px
    |----------------------------------------------------------------------
    |
    | The list and the message are separate screens. The server knows which
    | one this page is, since a message is either selected or it is not, so
    | the shell carries .mr-reading and the switch is CSS alone, with no
    | script to fail. Both on one screen would leave the message a cramped
    | half-height pane with no room for anything the header holds.
    |
    */

    @media (max-width: 899px) {
        .mr-app { flex-direction: column; height: auto; min-height: 100dvh; }

        .mr-app.mr-reading .mr-side,
        .mr-app.mr-reading .mr-listcol,
        .mr-app:not(.mr-reading) .mr-detail { display: none; }

        /* Brand and theme on one line, then the mailboxes, then everything
           else as a row of chips that scrolls sideways. */
        .mr-side {
            width: auto;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            grid-template-areas:
                "brand theme"
                "boxes boxes"
                "more more";
            gap: 10px;
            padding: 10px 0 12px;
            overflow: visible;
        }

        .mr-brand { grid-area: brand; padding: 0 16px; height: 44px; font-size: 16px; }

        .mr-side-foot { display: contents; }
        .mr-side-actions { display: contents; }
        .mr-theme { grid-area: theme; margin-right: 16px; align-self: center; }
        .mr-theme button { height: 34px; min-width: 34px; }
        .mr-hint,
        .mr-side-foot .mr-clear,
        .mr-side-sec,
        .mr-side-note { display: none; }

        .mr-boxes { grid-area: boxes; }
        .mr-side-more { grid-area: more; }

        .mr-boxes,
        .mr-side-more {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 6px;
            padding: 0 16px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .mr-boxes::-webkit-scrollbar,
        .mr-side-more::-webkit-scrollbar { display: none; }

        /* A fade at the edge says the row goes on. */
        .mr-side-more {
            -webkit-mask-image: linear-gradient(90deg, #000 85%, transparent);
            mask-image: linear-gradient(90deg, #000 85%, transparent);
        }

        /* A mask makes the row the containing block for fixed descendants, so
           while People is open the fade goes, or its panel would be
           positioned and clipped inside the row instead of the screen. */
        .mr-side-more:has(.mr-people-block[open]) {
            -webkit-mask-image: none;
            mask-image: none;
        }

        .mr-side-more .mr-nav { flex-direction: row; gap: 6px; }

        .mr-nav-link,
        .mr-people-toggle {
            flex-shrink: 0;
            height: 36px;
            min-height: 0;
            padding: 0 13px;
            gap: 6px;
            border: 1px solid var(--mr-line);
            border-radius: 999px;
            background: var(--mr-panel);
            white-space: nowrap;
        }

        .mr-nav-link { font-size: 13px; }
        .mr-nav-link[aria-current] { background: var(--mr-ink); color: var(--mr-panel); border-color: var(--mr-ink); box-shadow: none; }
        .mr-nav-link[aria-current] .mr-badge { background: transparent; border-color: transparent; color: var(--mr-panel); padding: 0; }
        .mr-nav-link[aria-current] .mr-nav-icon { color: var(--mr-panel); }

        .mr-nav-link .mr-nav-icon { display: none; }
        .mr-nav-label { flex: none; overflow: visible; }

        /* People is a chip here, first in its row where it is on screen,
           opening the same search as a panel over the page. */
        .mr-people-block { flex-shrink: 0; order: -1; }
        .mr-people-toggle { display: inline-flex; align-items: center; font-size: 13px; font-weight: 500; text-transform: none; letter-spacing: normal; color: var(--mr-ink-soft); }
        .mr-people-block[open] > .mr-people-toggle { background: var(--mr-ink); color: var(--mr-panel); border-color: var(--mr-ink); }
        .mr-people-icon { display: block; }

        .mr-people-panel {
            position: fixed;
            left: 12px;
            right: 12px;
            top: 12px;
            z-index: 30;
            max-height: calc(100dvh - 24px);
            padding: 12px;
            background: var(--mr-panel);
            border: 1px solid var(--mr-line);
            border-radius: var(--mr-r-xl);
            box-shadow: var(--mr-shadow-lg);
        }

        .mr-people-panel .mr-person { min-height: 48px; }
        /* 16px, or iOS zooms the page when the field takes focus. */
        .mr-find .mr-input { height: 44px; font-size: 16px; }
        .mr-find .mr-input-clear { width: 28px; height: 28px; right: 8px; }

        .mr-listcol { width: auto; flex: 1; border: 0; border-top: 1px solid var(--mr-line); }

        .mr-list-head { padding: 14px 16px 12px; }
        .mr-list-title { font-size: 18px; }
        .mr-field .mr-kbd { display: none; }
        /* 16px, or iOS zooms the page when the field takes focus. */
        .mr-input { height: 42px; padding-right: 12px; font-size: 16px; background: var(--mr-panel); border-color: var(--mr-line); }
        .mr-hint { display: none; }

        .mr-list { overflow: visible; }
        .mr-rows { padding: 4px 6px; }
        .mr-item-who,
        .mr-item-subject { font-size: 14.5px; }
        .mr-item-preview { font-size: 13px; -webkit-line-clamp: 1; }
        .mr-avatar:not(.mr-avatar-sm) { width: 38px; height: 38px; }
        .mr-item-meta .mr-badge-wide { display: none; }

        .mr-list-actions .mr-long { display: none; }
        .mr-list-actions .mr-short { display: inline; }
        .mr-clear-narrow { display: inline-flex; }
        .mr-list-foot:has(> .mr-clear-narrow:only-child) { display: flex; }

        .mr-list-actions .mr-btn,
        .mr-pager .mr-icon-btn { height: 44px; }
        .mr-pager .mr-icon-btn { width: 44px; }

        .mr-detail { padding: 0; flex-direction: column; }

        .mr-back {
            display: flex;
            align-items: center;
            gap: 2px;
            height: 52px;
            padding: 0 8px;
            flex-shrink: 0;
            font-size: 15px;
            font-weight: 500;
            color: var(--mr-accent-text);
            background: var(--mr-side);
        }

        .mr-back a { display: inline-flex; align-items: center; gap: 2px; height: 44px; padding: 0 10px 0 4px; border-radius: var(--mr-r-md); }

        .mr-card { margin: 10px; }

        .mr-detail-head { padding: 16px 16px 14px; }
        .mr-head-top { flex-direction: column; gap: 0; }
        .mr-subject { font-size: 20px; padding-top: 0; }

        /* After the sender on a phone: first what it is, then what to do with it. */
        .mr-detail-head { display: flex; flex-direction: column; }
        .mr-head-top { display: contents; }
        .mr-subject { order: 1; }
        .mr-sender { order: 2; margin-top: 12px; }
        .mr-actions { order: 3; margin-top: 14px; }

        /* Touch has no hover, so the toolbar spells itself out. */
        .mr-actions { display: flex; gap: 0; padding: 4px; border-radius: 14px; background: var(--mr-sunken); border: 1px solid var(--mr-line-soft); }
        .mr-tool-group { flex: 1; padding: 0; background: none; border: 0; }
        .mr-tool-group + .mr-tool-group { flex: 0 0 auto; margin-left: 4px; padding-left: 4px; border-left: 1px solid var(--mr-line); border-radius: 0; }
        .mr-actions .mr-inline-form { flex: 1; }
        .mr-tool { flex: 1; width: auto; height: 56px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; font-size: 11.5px; font-weight: 500; }
        .mr-tool-group + .mr-tool-group .mr-tool { width: 64px; }
        .mr-tool-label { position: static; width: auto; height: auto; overflow: visible; clip: auto; }

        .mr-when { display: none; }
        .mr-who-time { display: inline; }

        .mr-fields { grid-template-columns: minmax(0, 1fr); gap: 0; margin-left: -48px; padding: 4px 14px; font-size: 13.5px; }
        .mr-fields dt { padding-top: 9px; text-align: left; text-transform: uppercase; letter-spacing: .05em; font-size: 11.5px; font-weight: 600; }
        .mr-fields dd { padding-bottom: 9px; color: var(--mr-ink); border-bottom: 1px solid var(--mr-line-soft); }
        .mr-fields dd:last-child { border-bottom: 0; }

        .mr-tabs { gap: 10px; padding: 0 16px 12px; }
        .mr-tablist-wrap {
            flex: 1;
            overflow-x: auto;
            scrollbar-width: none;
            -webkit-mask-image: linear-gradient(90deg, #000 80%, transparent);
            mask-image: linear-gradient(90deg, #000 80%, transparent);
        }
        .mr-tablist-wrap::-webkit-scrollbar { display: none; }
        .mr-tabs .mr-seg button { height: 34px; }
        .mr-viewport-label { display: none; }
        .mr-viewport button { padding: 0; min-width: 34px; }

        .mr-notice { margin: 0 16px 14px; }
        .mr-panel { min-height: 60dvh; }

        /* Here the page scrolls rather than the pane, so the pane has no
           definite height for the frame's 100% to resolve against, and an
           iframe without one falls back to 150px. */
        .mr-frame { height: 75dvh; }
        .mr-files,
        .mr-spam { padding: 16px; }
        .mr-pre { padding: 16px; }
        .mr-table td { padding-left: 16px; padding-right: 16px; }

        /* The dialog docks to the bottom, where a thumb can reach Send. */
        .mr-modal { align-items: flex-end; padding: 10px; }
        .mr-modal-card { max-width: none; border-radius: 20px; }
        .mr-modal-input { height: 46px; font-size: 16px; }
        .mr-modal-foot { flex-wrap: wrap; }
        .mr-modal-meta { flex-basis: 100%; margin-bottom: 4px; }
        .mr-modal-foot .mr-btn { flex: 1; justify-content: center; height: 48px; font-size: 15px; }

        .mr-lightbox-body { padding: 8px 4px 12px; }
        .mr-lightbox-title { font-size: 13px; }
    }
</style>
