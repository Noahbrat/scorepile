/** @type {import('tailwindcss').Config} */
import tailwindcssPrimeui from "tailwindcss-primeui";

export default {
    content: ["./index.html", "./src/**/*.{vue,js,ts,jsx,tsx}"],
    theme: {
        extend: {},
    },
    plugins: [tailwindcssPrimeui],
    corePlugins: {
        preflight: false, // Prevent Tailwind from overriding PrimeVue base styles
    },
};
