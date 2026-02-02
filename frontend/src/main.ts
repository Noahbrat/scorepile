import "./assets/main.css";
import "primeicons/primeicons.css";

import { createApp } from "vue";
import { createPinia } from "pinia";
import PrimeVue from "primevue/config";
import ToastService from "primevue/toastservice";
import Tooltip from "primevue/tooltip";
import Aura from "@primeuix/themes/aura";

import App from "./App.vue";
import router from "./router";
import { initializeNavigation } from "./utils/navigation";

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Initialize navigation utility with router instance for API interceptors
initializeNavigation(router);

app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: ".app-dark",
            cssLayer: false,
        },
    },
});
app.use(ToastService);
app.directive("tooltip", Tooltip);

app.mount("#app");

// Register service worker for PWA
if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
        navigator.serviceWorker.register("/sw.js").catch(() => {
            // SW registration failed — no big deal, app works without it
        });
    });
}
