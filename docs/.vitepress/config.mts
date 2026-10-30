import { readdirSync, readFileSync } from 'node:fs';
import { defineConfig } from 'vitepress';
import llmstxt, { copyOrDownloadAsMarkdownButtons } from 'vitepress-plugin-llms';

const repository = 'https://github.com/ebbbang/laravel-mailroom';
const site = 'https://ebbbang.github.io/laravel-mailroom/';
const docs = new URL('../', import.meta.url);

// The newest released version, read from the changelog so the nav never needs
// bumping by hand at release time.
const version = readFileSync(new URL('../../CHANGELOG.md', import.meta.url), 'utf8').match(/^## \[(\d+\.\d+\.\d+)\]/m)?.[1];

const sidebar = [
    {
        text: 'Getting started',
        items: [
            { text: 'Introduction', link: '/introduction' },
            { text: 'Installation', link: '/installation' },
        ],
    },
    {
        text: 'The mailbox',
        items: [
            { text: 'Reading mail', link: '/mailbox' },
            { text: 'Access control', link: '/access-control' },
        ],
    },
    {
        text: 'Features',
        items: [
            { text: 'Forwarding a message', link: '/forwarding' },
            { text: 'Spam check', link: '/spam-check' },
        ],
    },
    {
        text: 'Running it',
        items: [
            { text: 'Staging and QA', link: '/staging' },
            { text: 'Production', link: '/production' },
            { text: 'Configuration', link: '/configuration' },
        ],
    },
    {
        text: 'Advanced',
        items: [
            { text: 'Events and platforms', link: '/advanced' },
            { text: 'Alternatives', link: '/alternatives' },
        ],
    },
    {
        text: 'Project',
        items: [{ text: 'Changelog', link: '/changelog' }],
    },
];

// The pages that describe themselves: every Markdown file but the landing page
// and the folder's index for GitHub.
const pages = () => readdirSync(docs).filter((file) => file.endsWith('.md') && file !== 'index.md' && file !== 'README.md');

/*
 * A page's description is its opening paragraph, the one under its heading, as
 * plain text. That is what a shared link and a search result show, so it stays
 * whatever the page itself says. Undefined when the page opens with anything
 * else, which the build refuses.
 */
function opening(file: string): string | undefined {
    const lines = readFileSync(new URL(file, docs), 'utf8').split(/\r?\n/);
    const paragraph: string[] = [];

    for (const line of lines.slice(lines.findIndex((line) => line.startsWith('# ')) + 1)) {
        if (line.trim() === '') {
            if (paragraph.length > 0) {
                break;
            }

            continue;
        }

        if (paragraph.length === 0 && /^(#|```|<|>|\||[-*] |\d+\. )/.test(line)) {
            return undefined;
        }

        paragraph.push(line.trim());
    }

    const text = paragraph
        .join(' ')
        .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
        .replace(/\*\*(.+?)\*\*/g, '$1')
        .replace(/\*(.+?)\*/g, '$1')
        .replace(/`([^`]+)`/g, '$1');

    return text === '' ? undefined : text;
}

// Long enough for a sentence or two, short enough that search results show it
// whole.
const longestDescription = 160;

// The contents of llms.txt: the sidebar, with each page's opening paragraph as
// its description. The plugin reads descriptions from front matter only, which
// the pages cannot carry because GitHub shows it at the top of the file.
const llmsContents = () =>
    sidebar
        .map((group) => {
            const links = group.items.map((item) => {
                const description = opening(`${item.link.slice(1)}.md`);

                return `- [${item.text}](/laravel-mailroom${item.link}.md)${description ? `: ${description}` : ''}`;
            });

            return `### ${group.text}\n\n${links.join('\n')}`;
        })
        .join('\n\n');

/*
 * The Markdown copies for AI tools have their HTML stripped, which takes every
 * screenshot with it, since the pages need <img> to set a width. This puts each
 * one's alt text after it in an <llm-only> block, which the llms plugin keeps
 * in those copies and removes from the page. It runs ahead of that plugin and
 * only on the build, so the files themselves, and GitHub, are untouched.
 */
const screenshotsAsText = {
    name: 'mailroom:screenshots-as-text',
    enforce: 'pre' as const,
    transform(code: string, id: string) {
        if (!id.endsWith('.md')) {
            return;
        }

        return code.replace(/^<img [^>]*alt="([^"]+)"[^>]*>$/gm, '$&\n\n<llm-only>\n\nScreenshot: $1.\n\n</llm-only>');
    },
};

export default defineConfig({
    title: 'Laravel Mailroom',
    description: 'A Laravel mail driver that stores outgoing mail in your database, plus a mailbox to read it.',
    lang: 'en',
    base: '/laravel-mailroom/',
    cleanUrls: true,
    lastUpdated: true,

    // The docs folder's own index, for reading the Markdown on GitHub.
    srcExclude: ['README.md'],

    sitemap: { hostname: site },

    // llms.txt, llms-full.txt and a Markdown copy of every page, for AI tools.
    vite: {
        plugins: [screenshotsAsText, llmstxt({ customTemplateVariables: { toc: llmsContents() } })],
    },

    // A button under each page's heading to copy it as Markdown or open it in
    // ChatGPT or Claude.
    markdown: {
        config(md) {
            md.use(copyOrDownloadAsMarkdownButtons);
        },
    },

    // What every page shares. The image lives in docs/public so its address
    // never changes: apps cache a preview by the image's URL, so a new card
    // needs a new query string here (?v=2) before they fetch it again.
    head: [
        ['link', { rel: 'icon', type: 'image/svg+xml', href: '/laravel-mailroom/logo.svg' }],
        ['link', { rel: 'apple-touch-icon', href: '/laravel-mailroom/apple-touch-icon.png' }],
        ['meta', { name: 'theme-color', content: '#f53003' }],
        ['meta', { property: 'og:type', content: 'website' }],
        ['meta', { property: 'og:site_name', content: 'Laravel Mailroom' }],
        ['meta', { property: 'og:image', content: `${site}social-card.png` }],
        ['meta', { property: 'og:image:type', content: 'image/png' }],
        ['meta', { property: 'og:image:width', content: '1200' }],
        ['meta', { property: 'og:image:height', content: '630' }],
        ['meta', { property: 'og:image:alt', content: 'Laravel Mailroom: keep every email your app sends, and read it in a real mailbox.' }],
        ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
    ],

    transformPageData(pageData) {
        if (!pages().includes(pageData.relativePath)) {
            return;
        }

        const description = opening(pageData.relativePath);

        // Every release repeats Added, Changed and Fixed, so the changelog's
        // outline lists the releases alone. Set here rather than in front
        // matter, which GitHub would show at the top of the page.
        const frontmatter = pageData.relativePath === 'changelog.md' ? { ...pageData.frontmatter, outline: 2 } : pageData.frontmatter;

        return { ...(description ? { description } : {}), frontmatter };
    },

    // What changes from page to page: the title, the description and the
    // address, so a shared link to /forwarding reads as that page.
    transformHead({ pageData, title, description }) {
        const url = site + pageData.relativePath.replace(/(^|\/)index\.md$/, '$1').replace(/\.md$/, '');

        return [
            ['link', { rel: 'canonical', href: url }],
            ['meta', { property: 'og:url', content: url }],
            ['meta', { property: 'og:title', content: title }],
            ['meta', { property: 'og:description', content: description }],
        ];
    },

    /*
     * Fails the build rather than shipping a page nobody can reach, or one that
     * shares with the site's description instead of its own. Run on the build
     * only, so a page half-written under docs:dev does not stop the server.
     */
    buildEnd() {
        const linked = new Set(sidebar.flatMap((group) => group.items.map((item) => `${item.link.slice(1)}.md`)));
        const problems: string[] = [];

        for (const page of pages()) {
            const description = opening(page);

            if (!linked.has(page)) {
                problems.push(`${page} is not in the sidebar in docs/.vitepress/config.mts.`);
            }

            if (description === undefined) {
                problems.push(`${page} has no opening paragraph under its heading to describe it.`);
            } else if (description.length > longestDescription) {
                problems.push(`${page} opens with ${description.length} characters; keep the first paragraph to ${longestDescription}.`);
            } else if (description.endsWith(':')) {
                problems.push(`${page} opens with a paragraph ending in a colon; open with a sentence that describes the page.`);
            }
        }

        if (problems.length > 0) {
            throw new Error(`The docs pages need attention:\n- ${problems.join('\n- ')}`);
        }
    },

    themeConfig: {
        logo: '/logo.svg',
        siteTitle: 'Mailroom',

        nav: [
            { text: 'Docs', link: '/introduction', activeMatch: '^/(?!$|changelog)' },
            { text: 'Changelog', link: '/changelog' },
            { text: 'Packagist', link: 'https://packagist.org/packages/ebbbang/laravel-mailroom' },
            ...(version ? [{ text: `v${version}`, link: `${repository}/releases/tag/v${version}` }] : []),
        ],

        sidebar,

        socialLinks: [{ icon: 'github', link: repository }],

        search: { provider: 'local' },

        editLink: {
            pattern: `${repository}/edit/main/docs/:path`,
            text: 'Edit this page on GitHub',
        },

        outline: { level: [2, 3], label: 'On this page' },

        lastUpdated: {
            text: 'Last updated',
            formatOptions: { dateStyle: 'long' },
        },

        footer: {
            message: 'Released under the MIT License.',
        },
    },
});
