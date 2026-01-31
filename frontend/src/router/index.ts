import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { getAuthToken } from "@/services/storage";
import HomeView from "../views/HomeView.vue";

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: "/",
            name: "home",
            component: HomeView,
            meta: { requiresAuth: true },
        },
        {
            path: "/games",
            name: "games",
            component: () => import("../views/GamesView.vue"),
            meta: { requiresAuth: true },
        },
        {
            path: "/games/:id",
            name: "game",
            component: () => import("../views/GameView.vue"),
            meta: { requiresAuth: true },
        },
        {
            path: "/players",
            name: "players",
            component: () => import("../views/PlayersView.vue"),
            meta: { requiresAuth: true },
        },
        {
            path: "/game-types",
            name: "game-types",
            component: () => import("../views/GameTypesView.vue"),
            meta: { requiresAuth: true },
        },
        {
            path: "/login",
            name: "login",
            component: () => import("../views/LoginView.vue"),
            meta: { requiresGuest: true },
        },
        {
            path: "/register",
            name: "register",
            component: () => import("../views/RegisterView.vue"),
            meta: { requiresGuest: true },
        },
    ],
});

// ── Navigation Guards ───────────────────────────────────────────

router.beforeEach(async (to, _from, next) => {
    const authStore = useAuthStore();

    // Initialise auth from storage if needed
    if (!authStore.isAuthenticated && getAuthToken()) {
        try {
            await authStore.initialize();
        } catch {
            // continue to route handling
        }
    }

    const requiresAuth = to.matched.some((r) => r.meta.requiresAuth);
    const requiresGuest = to.matched.some((r) => r.meta.requiresGuest);

    if (requiresAuth && !authStore.isAuthenticated) {
        next({ name: "login", query: { redirect: to.fullPath } });
    } else if (requiresGuest && authStore.isAuthenticated) {
        next({ name: "home" });
    } else {
        next();
    }
});

export default router;
