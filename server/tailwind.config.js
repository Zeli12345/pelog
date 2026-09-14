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
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Palet diambil dari logo SMKN 1 Mas Ubud
                navy: {
                    50: '#F2F5F9',
                    100: '#E4ECF4',
                    200: '#C5D8E8',
                    300: '#9DBBD4',
                    400: '#6E96B8',
                    500: '#4B769C',
                    600: '#395D80',
                    700: '#2E4B68',
                    800: '#223D59',
                    900: '#1B3A5C',
                    950: '#0F2237',
                },
                brick: {
                    50: '#FBF4F3',
                    100: '#F7E5E2',
                    200: '#F0CCC7',
                    300: '#E4A79F',
                    400: '#D57D71',
                    500: '#C4584A',
                    600: '#B23A2E',
                    700: '#952F27',
                    800: '#7C2A24',
                    900: '#672722',
                    950: '#371110',
                },
                gold: {
                    50: '#FCF8EC',
                    100: '#F8EFCB',
                    200: '#F0DE98',
                    300: '#E7C862',
                    400: '#DEB33A',
                    500: '#C9A227',
                    600: '#A9821F',
                    700: '#85631C',
                    800: '#6E521D',
                    900: '#5D451D',
                    950: '#35260C',
                },
                moss: {
                    50: '#F1F8F4',
                    100: '#DDEEE4',
                    200: '#BDDDCA',
                    300: '#90C4A7',
                    400: '#5FA47F',
                    500: '#2F7D5C',
                    600: '#27694E',
                    700: '#20553F',
                    800: '#1C4434',
                    900: '#17382C',
                },
                paper: {
                    DEFAULT: '#F7F5F1',
                    dark: '#EFEBE4',
                },
                line: {
                    DEFAULT: '#E4DFD6',
                    strong: '#D3CCC0',
                },
                ink: {
                    DEFAULT: '#191C20',
                    soft: '#4A4F56',
                    faint: '#7A8089',
                },
            },
            boxShadow: {
                card: '0 1px 2px rgba(25, 28, 32, 0.04), 0 1px 3px rgba(25, 28, 32, 0.06)',
                overlay: '0 8px 24px rgba(25, 28, 32, 0.12)',
            },
        },
    },

    plugins: [forms],
};
