import { readFileSync } from 'node:fs';
import { defineConfig } from 'vitepress';

const repository = 'https://github.com/ebbbang/laravel-mailroom';

// The newest released version, read from the changelog so the nav never needs
// bumping by hand at release time.
const version = readFileSync(new URL('../../CHANGELOG.md', import.meta.url), 'utf8').match(/^## \[(\d+\.\d+\.\d+)\]/m)?.[1];
export default defineConfig({
    title: 'Laravel Mailroom',
    description: 'A Laravel mail driver that stores outgoing mail in your database, plus a mailbox to read it.',
    lang: 'en',
    base: '/laravel-mailroom/',
    cleanUrls: true,
    lastUpdated: true,

    // The docs folder's own index, for reading the Markdown on GitHub.
    srcExclude: ['README.md'],

    head: [['link', { rel: 'icon', type: 'image/svg+xml', href: '/laravel-mailroom/logo.svg' }]],

    themeConfig: {
        logo: '/logo.svg',
        siteTitle: 'Mailroom',

        nav: [
            { text: 'Docs', link: '/introduction', activeMatch: '^/(?!$)' },
            { text: 'Changelog', link: `${repository}/blob/main/CHANGELOG.md` },
            { text: 'Packagist', link: 'https://packagist.org/packages/ebbbang/laravel-mailroom' },
            ...(version ? [{ text: `v${version}`, link: `${repository}/releases/tag/v${version}` }] : []),
        ],

        sidebar: [
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
        ],

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
