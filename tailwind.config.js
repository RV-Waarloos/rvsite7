/** @type {import('tailwindcss').Config} */

export default {
    content: [
        './resources/**/*.antlers.html',
        './resources/**/*.antlers.php',
        './resources/**/*.blade.php',
        './resources/**/*.vue',
        './content/**/*.md',
        "./node_modules/tw-elements/dist/js/**/*.js",
        "./node_modules/tw-elements/dist/css/**/*.css",
    ],

    theme: {
        fontFamily: {
            sans: ["Roboto", "sans-serif"],
            body: ["Roboto", "sans-serif"],
            mono: ["ui-monospace", "monospace"],
          },
        
        extend: {},
    },

    darkMode: 'class',

    corePlugins: {
        preflight: true,
      },
    
    plugins: [
        require('@tailwindcss/typography'),
        require('tw-elements/dist/plugin.cjs'),
    ],
};
