/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.{js,ts,jsx,tsx,vue}',
  ],
  theme: {
    extend: {
      keyframes: {
        indeterminate: {
          '0%': { transform: 'translateX(-100%)' },
          '100%': { transform: 'translateX(400%)' },
        },
      },
      animation: {
        indeterminate: 'indeterminate 1.2s ease-in-out infinite',
      },
    },
  },
}
