/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],
  theme: {
    extend: {
      fontFamily: {
        display: ['"Space Grotesk"', 'sans-serif'],
        sans: ['Inter', 'sans-serif'],
        mono: ['"IBM Plex Mono"', 'monospace'],
      },
      colors: {
        bg: '#F3FAF5',
        surface: '#FFFFFF',
        surfacealt: '#DEF3E6',
        ink: '#14301F',
        inkmuted: '#4C6B58',
        inkfaint: '#86A395',
        line: '#CBEAD8',
        brand: '#1E8449',
        branddark: '#0E4A2C',
        brandlight: '#E3F7EB',
        amber: '#E8A33D',
        amberink: '#6B4A15',
        teal: '#2E9E6E',
        tealink: '#14432C',
        plum: '#3C8F6E',
        plumink: '#163F30',
        coral: '#D6584A',
        coralink: '#6E1F16',
      },
    },
  },
  plugins: [],
};
