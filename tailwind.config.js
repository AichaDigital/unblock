import defaultTheme from 'tailwindcss/defaultTheme';
import typography from '@tailwindcss/typography';
import forms from '@tailwindcss/forms';
import colors from 'tailwindcss/colors';
import aspectRatio from '@tailwindcss/aspect-ratio';


/** @type {import('tailwindcss').Config} */
export default {
    // Presets: WireUI se carga después para asegurarnos de que nuestros colores se apliquen
    presets: [
        require("./vendor/wireui/wireui/tailwind.config.js")
    ],

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './vendor/wireui/wireui/src/*.php',
        './vendor/wireui/wireui/ts/**/*.ts',
        './vendor/wireui/wireui/src/WireUi/**/*.php',
        './vendor/wireui/wireui/src/Components/**/*.php',
    ],

    theme: {
        extend: {
            // Configuración extendida para sobrescribir los colores de WireUI
            colors: {
                primary: colors.emerald,   // Cambias el botón primary de violeta a emerald
                secondary: colors.gray,
                positive: colors.green,
                negative: colors.red,
                warning: colors.amber,
                info: colors.blue
            },
            fontFamily: {
                sans: ['InterVariable', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [
        typography,
        forms,
        aspectRatio,
    ],

};
