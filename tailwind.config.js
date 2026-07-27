/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/views/textDisplay/book.twig',
    './resources/views/textDisplay/verseCardCreator.twig',
    './resources/views/greekText/verseAnalysis.twig',
    './resources/views/greekText/verseAnalysisToggle.twig',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
  safelist: ['place-details'],
  darkMode: ['selector', '[data-theme="dark"]'], // Use data-theme attribute for dark mode (Bootstrap compatible)
  corePlugins: {
    preflight: false, // Disable Tailwind's base styles to avoid conflicts with Bootstrap
  },
  important: '.tailwind-scope', // Scope Tailwind to specific containers
}
