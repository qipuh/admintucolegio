import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Comfortaa', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Paleta de marca: cambiar aquí para re-colorear todo el panel.
                brand: {
                    50: '#eef4ff',
                    100: '#dae6ff',
                    200: '#bdd3ff',
                    300: '#90b6ff',
                    400: '#5c8dfd',
                    500: '#3766f9',
                    600: '#2146ee',
                    700: '#1934db',
                    800: '#1b2db1',
                    900: '#1c2c8b',
                    950: '#141c54',
                },
                ink: {
                    900: '#0b1230',
                    800: '#121a3f',
                },
            },
            maxWidth: {
                content: '1100px',
            },
        },
    },

    plugins: [forms],
};
