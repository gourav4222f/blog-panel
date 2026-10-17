/** Tailwind config for the admin stylesheet (blogs/assets/css/admin.css). */
module.exports = {
  content: [
    './blogs/app/Views/admin/**/*.php',
    './blogs/app/Views/install/**/*.php',
    './blogs/install/**/*.php',
    './blogs/app/Helpers/**/*.php',
    './src/js/admin/**/*.js',
    './src/js/editor/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Inter Variable"', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', 'sans-serif'],
      },
      colors: {
        ink: { DEFAULT: '#17202A', soft: '#334155' },
        brand: { 50: '#FBF6F1', 100: '#F4E8DC', 200: '#E8CFB7', 500: '#B5815A', 600: '#9C6A45', 700: '#7D5434' },
      },
      boxShadow: { card: '0 1px 2px rgb(15 23 42 / .04), 0 1px 3px rgb(15 23 42 / .06)' },
    },
  },
  plugins: [],
};
