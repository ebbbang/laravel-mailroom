import type { Theme } from 'vitepress';
// Without the bundled Inter: the site uses the system font stack, as the
// mailbox does.
import DefaultTheme from 'vitepress/theme-without-fonts';
import CopyOrDownloadAsMarkdownButtons from 'vitepress-plugin-llms/vitepress-components/CopyOrDownloadAsMarkdownButtons.vue';
import Home from './Home.vue';
import './custom.css';

export default {
    extends: DefaultTheme,
    enhanceApp({ app }) {
        app.component('Home', Home);
        app.component('CopyOrDownloadAsMarkdownButtons', CopyOrDownloadAsMarkdownButtons);
    },
} satisfies Theme;
