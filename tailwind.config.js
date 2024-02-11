/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.antlers.html',
        './resources/**/*.antlers.php',
        './resources/**/*.blade.php',
        './resources/**/*.vue',
        './content/**/*.md',
        "./node_modules/tw-elements/dist/js/**/*.js",
    ],

    theme: {
        extend: {},
    },

    plugins: [
        require('@tailwindcss/typography'),
        require('tw-elements/dist/plugin.cjs'),
    ],
};
