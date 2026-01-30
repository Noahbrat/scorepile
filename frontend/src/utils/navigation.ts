import type { Router } from "vue-router";

/**
 * Navigation utility for redirecting to login from API interceptors.
 * Centralises login redirects so they can be tested and avoid window.location hacks.
 */

let routerInstance: Router | null = null;

/** Call once during app bootstrap with the real router instance. */
export function initializeNavigation(router: Router): void {
    routerInstance = router;
}

/** Navigate to /login (used by Axios response interceptor on 401). */
export function navigateToLogin(): void {
    const isOnLoginPage =
        (typeof window !== "undefined" && window.location.pathname === "/login") ||
        routerInstance?.currentRoute.value.path === "/login";

    if (isOnLoginPage) return;

    if (routerInstance) {
        routerInstance.push("/login").catch(() => {
            /* ignore duplicate navigation */
        });
    } else if (typeof window !== "undefined") {
        window.location.href = "/login";
    }
}
