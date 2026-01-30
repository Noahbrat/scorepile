import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";
import type { Plugin } from "vite";

export default defineConfig({
    // @ts-expect-error - Plugin type mismatch between Vite versions
    plugins: [vue() satisfies Plugin],
    test: {
        environment: "jsdom",
        globals: true,
        setupFiles: ["./src/test-setup.ts"],
    },
    resolve: {
        alias: {
            "@": fileURLToPath(new URL("./src", import.meta.url)),
        },
    },
});
