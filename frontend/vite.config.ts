import { fileURLToPath, URL } from "node:url";

import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";

// https://vite.dev/config/
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            "@": fileURLToPath(new URL("./src", import.meta.url)),
        },
    },
    server: {
        host: "0.0.0.0",
        port: 5173,
        proxy: {
            "/api": {
                target: "http://localhost:8765",
                changeOrigin: true,
                secure: false,
            },
        },
    },
    build: {
        outDir: "dist",
        emptyOutDir: true,
        minify: "esbuild",
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                entryFileNames: "js/[name]-[hash].js",
                chunkFileNames: "js/[name]-[hash].js",
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name?.endsWith(".css")) {
                        return "css/[name]-[hash][extname]";
                    }
                    return "assets/[name]-[hash][extname]";
                },
                manualChunks: {
                    primevue: ["primevue/config", "primevue/usetoast"],
                },
            },
        },
    },
    optimizeDeps: {
        include: ["primevue"],
    },
});
