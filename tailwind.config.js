import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    safelist: [
        'delay-0', 'delay-100', 'delay-200', 'delay-300', 'delay-400',
        'animate-slide-up', 'animate-fade-in', 'animate-pop',
        'animate-chart-draw', 'animate-slide-in-right',
        'animate-pop-out', 'animate-fade-out',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50:  '#f0fdfa',
                    100: '#ccfbf1',
                    200: '#99f6e4',
                    300: '#5eead4',
                    400: '#2dd4bf',
                    500: '#14b8a6',
                    600: '#0d9488',
                    700: '#0f766e',
                    800: '#115e59',
                    900: '#134e4a',
                },
                accent: {
                    500: '#06b6d4',
                    600: '#0891b2',
                },
                surface: {
                    page:   '#f6f7fb',
                    tile:   '#ffffff',
                    border: '#eef2f7',
                },
                ink: {
                    heading: '#0f172a',
                    body:    '#475569',
                    muted:   '#94a3b8',
                },
                success: '#059669',
                warning: '#d97706',
                danger:  '#e11d48',
            },
            boxShadow: {
                tile:        '0 2px 10px rgba(15, 23, 42, 0.04)',
                'tile-hover': '0 14px 28px rgba(15, 23, 42, 0.10)',
            },
            transitionDelay: {
                0:   '0ms',
                100: '100ms',
                200: '200ms',
                300: '300ms',
                400: '400ms',
            },
            keyframes: {
                'fade-in':         { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                'slide-up':        { '0%': { transform: 'translateY(12px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
                'pop':             { '0%': { transform: 'scale(.95)', opacity: '0' }, '100%': { transform: 'scale(1)', opacity: '1' } },
                'chart-draw':      { '0%': { strokeDashoffset: 'var(--dash-len, 600)' }, '100%': { strokeDashoffset: '0' } },
                'slide-in-right':  { '0%': { transform: 'translateX(20px)', opacity: '0' }, '100%': { transform: 'translateX(0)', opacity: '1' } },
                'pop-out':         { '0%': { transform: 'scale(1)', opacity: '1' }, '100%': { transform: 'scale(.95)', opacity: '0' } },
                'fade-out':        { '0%': { opacity: '1' }, '100%': { opacity: '0' } },
            },
            animation: {
                'fade-in':        'fade-in 400ms ease-out forwards',
                'slide-up':       'slide-up 550ms cubic-bezier(.2,.7,.2,1) forwards',
                'pop':            'pop 600ms cubic-bezier(.2,.7,.2,1) forwards',
                'chart-draw':     'chart-draw 1400ms ease-out forwards',
                'slide-in-right': 'slide-in-right 350ms ease-out forwards',
                'pop-out':        'pop-out 250ms ease-in forwards',
                'fade-out':       'fade-out 200ms ease-in forwards',
            },
        },
    },

    plugins: [
        forms,
        function ({ addUtilities }) {
            addUtilities({
                '.delay-0':   { 'animation-delay': '0ms' },
                '.delay-100': { 'animation-delay': '100ms' },
                '.delay-200': { 'animation-delay': '200ms' },
                '.delay-300': { 'animation-delay': '300ms' },
                '.delay-400': { 'animation-delay': '400ms' },
            });
        },
    ],
};
