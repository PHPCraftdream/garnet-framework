/** @type {import('tailwindcss').Config} */
module.exports = {
  // Tailwind v4 auto-detects sources, but keep explicit globs correct for the
  // new layout: this config lives in <framework>/FrontBuilder/, the shared
  // frontend is in ../Bundle/Front, framework Twig in ../Bundle/TwigTemplates,
  // and consuming apps sit one level above the framework package (../../Apps).
  content: [
    '../Bundle/Front/**/*.{ts,tsx}',
    '../Bundle/TwigTemplates/**/*.twig',
    '../../Apps/**/Front/**/*.{ts,tsx}',
    '../../Apps/**/**/TwigTemplates/**/*.twig',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
  corePlugins: {
    preflight: true,
  },
}
