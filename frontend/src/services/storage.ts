/**
 * Auth Token Storage Service
 *
 * Provides resilient storage for authentication tokens with:
 * - localStorage as primary storage
 * - Cookie fallback for Safari/iOS private browsing
 * - Proper error handling and detection
 */

// Storage keys
const AUTH_TOKEN_KEY = "auth_token";
const REFRESH_TOKEN_KEY = "refresh_token";

// Cookie expiry times
const ACCESS_TOKEN_COOKIE_MAX_AGE = 30 * 60; // 30 minutes
const REFRESH_TOKEN_COOKIE_MAX_AGE = 7 * 24 * 60 * 60; // 7 days

// Cached localStorage availability
let localStorageAvailable: boolean | null = null;

export interface StorageResult {
    success: boolean;
    usedFallback: boolean;
    error?: string;
}

/**
 * Check if localStorage is available and working
 */
export function isLocalStorageAvailable(): boolean {
    if (localStorageAvailable !== null) {
        return localStorageAvailable;
    }

    try {
        const testKey = "__storage_test__";
        localStorage.setItem(testKey, "test");
        localStorage.removeItem(testKey);
        localStorageAvailable = true;
        return true;
    } catch {
        localStorageAvailable = false;
        return false;
    }
}

/**
 * Non-cached check — Safari iOS can clear localStorage under memory pressure
 */
function isLocalStorageCurrentlyAvailable(): boolean {
    try {
        const testKey = "__storage_health_check__";
        localStorage.setItem(testKey, "test");
        localStorage.removeItem(testKey);
        return true;
    } catch {
        return false;
    }
}

// ── Cookie helpers ──────────────────────────────────────────────

function setCookie(name: string, value: string, maxAgeSeconds: number): boolean {
    try {
        const isProduction = import.meta.env.PROD;
        const secure = isProduction ? "Secure; " : "";
        const encodedValue = encodeURIComponent(value);
        document.cookie = `${name}=${encodedValue}; max-age=${maxAgeSeconds + 60}; path=/; ${secure}SameSite=Lax`;
        return true;
    } catch {
        return false;
    }
}

function getCookie(name: string): string | null {
    try {
        const cookies = document.cookie.split(";");
        for (const cookie of cookies) {
            const [key, value] = cookie.trim().split("=");
            if (key === name && value) {
                return decodeURIComponent(value);
            }
        }
        return null;
    } catch {
        return null;
    }
}

function deleteCookie(name: string): void {
    try {
        document.cookie = `${name}=; max-age=0; path=/; SameSite=Lax`;
    } catch {
        // silently ignore
    }
}

// ── Public API ──────────────────────────────────────────────────

/**
 * Store the access token (dual-write: localStorage + cookie)
 */
export function setAuthToken(token: string): StorageResult {
    let localStorageSuccess = false;
    let cookieSuccess = false;

    if (isLocalStorageAvailable()) {
        try {
            localStorage.setItem(AUTH_TOKEN_KEY, token);
            localStorageSuccess = true;
        } catch {
            // fall through to cookie
        }
    }

    cookieSuccess = setCookie(AUTH_TOKEN_KEY, token, ACCESS_TOKEN_COOKIE_MAX_AGE);

    const success = localStorageSuccess || cookieSuccess;
    return {
        success,
        usedFallback: !localStorageSuccess,
        error: success ? undefined : "Failed to store auth token",
    };
}

/**
 * Retrieve the access token
 */
export function getAuthToken(): string | null {
    if (isLocalStorageCurrentlyAvailable()) {
        try {
            const token = localStorage.getItem(AUTH_TOKEN_KEY);
            if (token) return token;
        } catch {
            // fall through to cookie
        }
    }
    return getCookie(AUTH_TOKEN_KEY);
}

/**
 * Store the refresh token (dual-write: localStorage + cookie)
 */
export function setRefreshToken(token: string): StorageResult {
    let localStorageSuccess = false;
    let cookieSuccess = false;

    if (isLocalStorageAvailable()) {
        try {
            localStorage.setItem(REFRESH_TOKEN_KEY, token);
            localStorageSuccess = true;
        } catch {
            // fall through to cookie
        }
    }

    cookieSuccess = setCookie(REFRESH_TOKEN_KEY, token, REFRESH_TOKEN_COOKIE_MAX_AGE);

    const success = localStorageSuccess || cookieSuccess;
    return {
        success,
        usedFallback: !localStorageSuccess,
        error: success ? undefined : "Failed to store refresh token",
    };
}

/**
 * Retrieve the refresh token
 */
export function getRefreshToken(): string | null {
    if (isLocalStorageCurrentlyAvailable()) {
        try {
            const token = localStorage.getItem(REFRESH_TOKEN_KEY);
            if (token) return token;
        } catch {
            // fall through to cookie
        }
    }
    return getCookie(REFRESH_TOKEN_KEY);
}

/**
 * Clear all auth tokens from all storage locations
 */
export function clearAuthTokens(): void {
    try {
        localStorage.removeItem(AUTH_TOKEN_KEY);
        localStorage.removeItem(REFRESH_TOKEN_KEY);
    } catch {
        // ignore
    }
    deleteCookie(AUTH_TOKEN_KEY);
    deleteCookie(REFRESH_TOKEN_KEY);
}
