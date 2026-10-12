import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                canvas: '#ecece9',
                surface: '#ffffff',
                ink: '#1d2219',
                muted: '#8b9086',
                line: '#e8e8e3',
                lime: '#9ccc4e',
                'lime-dark': '#4a7a22',
                'lime-soft': '#e8f4cf',
                orange: '#f4a65c',
                yellow: '#f3e35f',
                'stage-face-to-face': '#c9e58f',
                'stage-site-visit': '#9ccc4e',
                'stage-slik': '#f3e35f',
                'stage-utj': '#f4a65c',
                'stage-akad': '#4a7a22',
                'stage-freelance': '#d3d6cd',
            },
            fontFamily: {
                sans: ['Poppins', ...defaultTheme.fontFamily.sans],
                poppins: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
