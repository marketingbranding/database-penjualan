import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['selector', '[data-theme="dark"]'],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                canvas: 'rgb(var(--c-canvas) / <alpha-value>)',
                rail: 'rgb(var(--c-rail) / <alpha-value>)',
                'rail-text': 'rgb(var(--c-rail-text) / <alpha-value>)',
                surface: 'rgb(var(--c-surface) / <alpha-value>)',
                'surface-2': 'rgb(var(--c-surface-2) / <alpha-value>)',
                'surface-3': 'rgb(var(--c-surface-3) / <alpha-value>)',
                ink: 'rgb(var(--c-text) / <alpha-value>)',
                text: 'rgb(var(--c-text) / <alpha-value>)',
                muted: 'rgb(var(--c-muted) / <alpha-value>)',
                subtle: 'rgb(var(--c-subtle) / <alpha-value>)',
                line: 'rgb(var(--c-line) / <alpha-value>)',
                'line-strong': 'rgb(var(--c-line-strong) / <alpha-value>)',
                lime: 'rgb(var(--c-accent) / <alpha-value>)',
                'lime-dark': 'rgb(var(--c-accent-dark) / <alpha-value>)',
                'lime-soft': 'rgb(var(--c-accent-soft) / var(--c-accent-soft-alpha))',
                accent: 'rgb(var(--c-accent) / <alpha-value>)',
                'accent-soft': 'rgb(var(--c-accent-soft) / var(--c-accent-soft-alpha))',
                'accent-text': 'rgb(var(--c-accent-text) / <alpha-value>)',
                'accent-dark': 'rgb(var(--c-accent-dark) / <alpha-value>)',
                'on-accent': 'rgb(var(--c-on-accent) / <alpha-value>)',
                'row-hover': 'rgb(var(--c-row-hover) / <alpha-value>)',
                'row-selected': 'rgb(var(--c-row-selected) / <alpha-value>)',
                danger: 'rgb(var(--c-danger) / <alpha-value>)',
                inverse: 'rgb(var(--c-inverse) / <alpha-value>)',
                'inverse-text': 'rgb(var(--c-inverse-text) / <alpha-value>)',
                checkbox: 'rgb(var(--c-checkbox) / <alpha-value>)',
                orange: 'rgb(var(--c-orange) / <alpha-value>)',
                yellow: 'rgb(var(--c-yellow) / <alpha-value>)',
                'stage-face-to-face': 'rgb(var(--c-stage-face-to-face) / <alpha-value>)',
                'stage-site-visit': 'rgb(var(--c-stage-site-visit) / <alpha-value>)',
                'stage-slik': 'rgb(var(--c-stage-slik) / <alpha-value>)',
                'stage-utj': 'rgb(var(--c-stage-utj) / <alpha-value>)',
                'stage-akad': 'rgb(var(--c-stage-akad) / <alpha-value>)',
                'stage-freelance': 'rgb(var(--c-stage-freelance) / <alpha-value>)',
            },
            fontFamily: {
                sans: ['Poppins', ...defaultTheme.fontFamily.sans],
                poppins: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
