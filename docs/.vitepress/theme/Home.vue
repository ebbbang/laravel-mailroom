<script setup lang="ts">
import { withBase } from 'vitepress';
import light from '../../images/mailbox-light.png';
import dark from '../../images/mailbox-dark.png';

const repository = 'https://github.com/ebbbang/laravel-mailroom';

const features = [
    {
        title: 'Mailboxes and people',
        body: 'Inbox, Unread and Spam checked, the ten people on the most mail, search as you type, and j / k to move through it.',
        icon: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        link: '/mailbox',
    },
    {
        title: 'Everything captured',
        body: 'HTML and text bodies, attachments byte for byte, inline images, Cc, Bcc, the envelope actually delivered to, tags, metadata and custom headers.',
        icon: '<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        link: '/introduction#what-gets-captured',
    },
    {
        title: 'Attachment previews',
        body: "Images, PDF, audio, video, CSV as a table, calendar invites and more, in a lightbox. Served from an allowlist, never from the attachment's own claim.",
        icon: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
        link: '/mailbox#attachment-previews',
    },
    {
        title: 'Forward to a real inbox',
        body: 'See how Gmail or Outlook actually renders it. Left unchanged, the stored message goes out byte for byte.',
        icon: '<path d="m15 17 5-5-5-5"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/>',
        link: '/forwarding',
    },
    {
        title: 'SpamAssassin score',
        body: "Score a message through Postmark's free SpamCheck API and see each rule that fired. Off until you choose a driver.",
        icon: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        link: '/spam-check',
    },
    {
        title: 'Safe by default',
        body: 'Off in production, local only until you define a gate, and every message body rendered in a sandboxed frame that cannot run scripts.',
        icon: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        link: '/access-control',
    },
];
</script>

<template>
    <div class="mr-home">
        <section class="mr-hero">
            <div class="mr-hero-copy">
                <span class="mr-eyebrow">A mail driver for Laravel</span>
                <h1>Keep every email your app sends. Read it in a real mailbox.</h1>
                <p class="mr-lede">
                    Mailroom stores outgoing mail in your database, whole: full MIME, attachments, embedded images,
                    headers. Then it gives you a mailbox at <code>/mailroom</code> to read it.
                </p>
                <div class="mr-actions">
                    <a class="mr-button mr-button-brand" :href="withBase('/introduction')">Get started</a>
                    <a class="mr-button" :href="repository">View on GitHub</a>
                </div>
            </div>

            <figure class="mr-install">
                <figcaption class="mr-install-head">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 17 6-6-6-6M12 19h8" /></svg>
                    Install in two commands
                </figcaption>
                <pre><code><span class="mr-cmd">composer</span> require --dev ebbbang/laravel-mailroom
<span class="mr-cmd">php</span> artisan mailroom:install</code></pre>
                <p class="mr-install-foot">Then open <strong>/mailroom</strong>. Your next email will be there.</p>
            </figure>
        </section>

        <section class="mr-shot" aria-label="The mailbox">
            <img class="mr-light-only" :src="light" alt="The Mailroom mailbox: a sidebar of mailboxes and people, the message list, and an open message" width="1440" height="900" />
            <img class="mr-dark-only" :src="dark" alt="The Mailroom mailbox in dark mode: a sidebar of mailboxes and people, the message list, and an open message" width="1440" height="900" />
        </section>

        <section class="mr-band">
            <div class="mr-wrap mr-stack">
                <h2>The log driver flattens a message. The array driver forgets it. Mailroom keeps it.</h2>
                <div class="mr-compare">
                    <div class="mr-compare-card">
                        <code>MAIL_MAILER=log</code>
                        <p>One line in a log file. The attachments are thrown away.</p>
                    </div>
                    <div class="mr-compare-card">
                        <code>MAIL_MAILER=array</code>
                        <p>Gone at the end of the request.</p>
                    </div>
                    <div class="mr-compare-card mr-compare-ours">
                        <code>MAIL_MAILER=mailroom</code>
                        <p>The whole message, kept in your database, with somewhere to read it.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mr-wrap mr-stack mr-section">
            <div class="mr-section-head">
                <h2>A mail client for what your application sent</h2>
                <p>No build step, no npm, no published assets. The styles are inlined, so it works offline and behind a strict CSP.</p>
            </div>
            <div class="mr-features">
                <a v-for="feature in features" :key="feature.title" class="mr-feature" :href="withBase(feature.link)">
                    <span class="mr-feature-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="feature.icon" />
                    </span>
                    <h3>{{ feature.title }}</h3>
                    <p>{{ feature.body }}</p>
                </a>
            </div>
        </section>

        <section class="mr-wrap mr-section mr-section-last">
            <div class="mr-panel">
                <div class="mr-stack mr-panel-copy">
                    <h2>Made for shared staging too</h2>
                    <p>
                        Testers read mail in the browser: no third-party catcher to sign up for, no inbox credentials to
                        pass around. Read state is per person, so two testers never clear each other's mail.
                    </p>
                    <a class="mr-more" :href="withBase('/staging')">Staging and QA →</a>
                </div>
                <dl class="mr-facts">
                    <dt>PHP</dt>
                    <dd>8.2 to 8.5</dd>
                    <dt>Laravel</dt>
                    <dd>12 and 13 (11 best-effort)</dd>
                    <dt>Storage</dt>
                    <dd>Your database and a disk, or a connection of its own</dd>
                    <dt>License</dt>
                    <dd>MIT</dd>
                </dl>
            </div>
        </section>
    </div>
</template>

<style scoped>
.mr-home {
    --mr-gutter: 32px;
    padding-bottom: 32px;
}

.mr-wrap,
.mr-hero,
.mr-shot {
    max-width: 1240px;
    margin: 0 auto;
    padding-left: var(--mr-gutter);
    padding-right: var(--mr-gutter);
    box-sizing: border-box;
}

.mr-stack {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

h1,
h2,
h3 {
    margin: 0;
    border: 0;
    padding: 0;
    color: var(--vp-c-text-1);
}

h2 {
    font-size: 34px;
    line-height: 1.15;
    letter-spacing: -0.02em;
    font-weight: 700;
}

p {
    margin: 0;
}

code {
    font-family: var(--vp-font-family-mono);
}

/* Hero */

.mr-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
    gap: 48px;
    align-items: center;
    padding-top: 88px;
    padding-bottom: 48px;
}

.mr-hero-copy {
    display: flex;
    flex-direction: column;
    gap: 22px;
    min-width: 0;
}

.mr-eyebrow {
    align-self: flex-start;
    padding: 4px 10px;
    border-radius: 999px;
    background: var(--vp-c-brand-soft);
    color: var(--vp-c-brand-1);
    font-size: 13px;
    font-weight: 600;
}

h1 {
    font-size: 60px;
    line-height: 1.05;
    letter-spacing: -0.03em;
    font-weight: 750;
}

.mr-lede {
    max-width: 560px;
    font-size: 19px;
    line-height: 1.6;
    color: var(--vp-c-text-2);
}

.mr-lede code {
    font-size: 0.9em;
    padding: 1px 6px;
    border-radius: 6px;
    background: var(--vp-c-default-soft);
}

.mr-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.mr-button {
    display: inline-flex;
    align-items: center;
    height: 46px;
    padding: 0 22px;
    border-radius: 10px;
    border: 1px solid var(--vp-c-divider);
    background: var(--vp-c-bg-soft);
    color: var(--vp-c-text-1);
    font-weight: 650;
    text-decoration: none;
    transition: background-color 0.2s;
}

.mr-button:hover {
    background: var(--vp-c-default-soft);
}

.mr-button-brand {
    border-color: transparent;
    background: var(--vp-button-brand-bg);
    color: var(--vp-button-brand-text);
}

.mr-button-brand:hover {
    background: var(--vp-button-brand-hover-bg);
    color: var(--vp-button-brand-hover-text);
}

/* The install card stays dark in both themes, like a terminal. */
.mr-install {
    margin: 0;
    min-width: 0;
    border-radius: 14px;
    background: #1b1b1f;
    color: #ececec;
    overflow: hidden;
    box-shadow: 0 18px 40px -20px rgba(0, 0, 0, 0.35);
}

.dark .mr-install {
    background: #141416;
    border: 1px solid var(--vp-c-divider);
}

.mr-install-head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    font-size: 13px;
    color: #a1a1a8;
}

.mr-install pre {
    margin: 0;
    padding: 18px 20px 22px;
    font-family: var(--vp-font-family-mono);
    font-size: 15px;
    line-height: 1.9;
    white-space: pre-wrap;
    word-break: break-word;
}

.mr-cmd {
    color: #ff6a5c;
}

.mr-install-foot {
    padding: 0 20px 18px;
    font-size: 13px;
    color: #a1a1a8;
}

.mr-install-foot strong {
    color: #ffffff;
    font-weight: 500;
}

/* Screenshot */

.mr-shot {
    padding-top: 8px;
    padding-bottom: 72px;
}

.mr-shot img {
    display: block;
    width: 100%;
    height: auto;
    border-radius: 14px;
    border: 1px solid var(--vp-c-divider);
    box-shadow: 0 30px 60px -30px rgba(0, 0, 0, 0.35);
}

.mr-dark-only,
.dark .mr-light-only {
    display: none !important;
}

.dark .mr-dark-only {
    display: block !important;
}

/* Comparison band */

.mr-band {
    background: var(--vp-c-bg-soft);
    border-top: 1px solid var(--vp-c-divider);
    border-bottom: 1px solid var(--vp-c-divider);
}

.mr-band .mr-wrap {
    padding-top: 72px;
    padding-bottom: 72px;
    gap: 32px;
}

.mr-band h2 {
    max-width: 720px;
}

.mr-compare {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
}

.mr-compare-card {
    padding: 22px;
    border-radius: 12px;
    border: 1px solid var(--vp-c-divider);
    background: var(--vp-c-bg);
}

.mr-compare-card code {
    font-size: 14px;
    color: var(--vp-c-text-2);
}

.mr-compare-card p {
    margin-top: 10px;
    color: var(--vp-c-text-2);
}

.mr-compare-ours {
    border: 2px solid var(--vp-c-brand-3);
}

.mr-compare-ours code {
    color: var(--vp-c-brand-1);
    font-weight: 600;
}

/* Features */

.mr-section {
    padding-top: 80px;
    padding-bottom: 80px;
}

.mr-section-head {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 680px;
    margin-bottom: 20px;
}

.mr-section-head p {
    color: var(--vp-c-text-2);
}

.mr-features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.mr-feature {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 24px;
    border-radius: 14px;
    border: 1px solid var(--vp-c-divider);
    color: inherit;
    text-decoration: none;
    transition: border-color 0.2s;
}

.mr-feature:hover {
    border-color: var(--vp-c-brand-2);
}

.mr-feature-icon {
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--vp-c-brand-soft);
    color: var(--vp-c-brand-1);
}

.mr-feature h3 {
    margin-top: 6px;
    font-size: 18px;
    font-weight: 650;
}

.mr-feature p {
    font-size: 15px;
    line-height: 1.6;
    color: var(--vp-c-text-2);
}

/* Staging panel */

.mr-section-last {
    padding-top: 0;
}

.mr-panel {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 32px;
    align-items: center;
    padding: 48px;
    border-radius: 18px;
    border: 1px solid var(--vp-c-divider);
    background: var(--vp-c-bg-soft);
}

.mr-panel-copy p {
    color: var(--vp-c-text-2);
}

.mr-more {
    font-weight: 600;
    color: var(--vp-c-brand-1);
    text-decoration: none;
}

.mr-more:hover {
    text-decoration: underline;
}

.mr-facts {
    margin: 0;
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 12px 24px;
    font-size: 15px;
}

.mr-facts dt {
    color: var(--vp-c-text-2);
}

.mr-facts dd {
    margin: 0;
    font-weight: 600;
}

/* Phones */

@media (max-width: 768px) {
    .mr-home {
        --mr-gutter: 20px;
    }

    .mr-hero {
        grid-template-columns: minmax(0, 1fr);
        padding-top: 40px;
        gap: 32px;
    }

    h1 {
        font-size: 38px;
    }

    h2 {
        font-size: 26px;
    }

    .mr-lede {
        font-size: 17px;
    }

    .mr-install pre {
        font-size: 13px;
    }

    .mr-panel {
        padding: 24px;
    }

    .mr-section {
        padding-top: 56px;
        padding-bottom: 56px;
    }
}
</style>
