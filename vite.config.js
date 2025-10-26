import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * NOTA: El warning "@property --radialprogress" es conocido y benigno.
 *
 * Causa: Lightning CSS (usado internamente por Tailwind CSS v4) aún no soporta
 * completamente la regla CSS @property de CSS Houdini que usa daisyUI v5.
 *
 * Impacto: NINGUNO - el CSS funciona perfectamente. Es solo un warning informativo.
 *
 * Soluciones intentadas:
 * - cssMinify: 'esbuild' - No funciona, Tailwind v4 usa Lightning CSS internamente
 * - base: false en daisyUI - No funciona, @property viene de componentes
 *
 * NO afecta funcionalidad. Este warning desaparecerá cuando Lightning CSS
 * soporte @property o daisyUI use una alternativa.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
