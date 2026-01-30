import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { authApi } from "@/services/api";
import type { User, LoginRequest, RegisterRequest } from "@/types/api";
import {
    setAuthToken as storeAuthToken,
    getAuthToken,
    setRefreshToken as storeRefreshToken,
    getRefreshToken,
    clearAuthTokens,
} from "@/services/storage";

export const useAuthStore = defineStore("auth", () => {
    // ── State ────────────────────────────────────────────────────
    const user = ref<User | null>(null);
    const token = ref<string | null>(null);
    const refreshToken = ref<string | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // Refresh deduplication
    let isCurrentlyRefreshing = false;
    let refreshPromise: Promise<boolean> | null = null;

    // ── Getters ──────────────────────────────────────────────────
    const isAuthenticated = computed(() => !!token.value && !!user.value);

    const userName = computed(() => {
        if (!user.value) return null;
        if (user.value.first_name && user.value.last_name) {
            return `${user.value.first_name} ${user.value.last_name}`;
        }
        return user.value.username;
    });

    const userInitials = computed(() => {
        if (!user.value) return null;
        if (user.value.first_name && user.value.last_name) {
            return `${user.value.first_name[0]}${user.value.last_name[0]}`.toUpperCase();
        }
        return user.value.username.substring(0, 2).toUpperCase();
    });

    const isAdmin = computed(() => {
        if (!user.value) return false;
        return user.value.is_superuser || user.value.role === "admin";
    });

    const canAccessAdmin = computed(() => {
        if (!user.value) return false;
        return isAdmin.value && user.value.active;
    });

    // ── Internal helpers ─────────────────────────────────────────

    const setUser = (userData: User) => {
        user.value = userData;
    };

    const setToken = (tokenValue: string) => {
        token.value = tokenValue;
        storeAuthToken(tokenValue);
    };

    const setRefreshTokenValue = (tokenValue: string) => {
        refreshToken.value = tokenValue;
        storeRefreshToken(tokenValue);
    };

    const setError = (msg: string | null) => {
        error.value = msg;
    };

    const clearAuth = () => {
        user.value = null;
        token.value = null;
        refreshToken.value = null;
        error.value = null;
        clearAuthTokens();
    };

    const isTokenExpired = (tokenValue: string | null, bufferSeconds = 300): boolean => {
        if (!tokenValue) return true;
        try {
            const parts = tokenValue.split(".");
            if (parts.length !== 3) return true;
            const payload = JSON.parse(atob(parts[1]));
            if (!payload.exp) return true;
            return payload.exp - Math.floor(Date.now() / 1000) <= bufferSeconds;
        } catch {
            return true;
        }
    };

    const loadStoredAuth = (): boolean => {
        const storedToken = getAuthToken();
        const storedRefreshToken = getRefreshToken();
        if (storedToken) {
            token.value = storedToken;
            if (storedRefreshToken) refreshToken.value = storedRefreshToken;
            return true;
        }
        return false;
    };

    // ── Auth actions ─────────────────────────────────────────────

    const login = async (credentials: LoginRequest) => {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await authApi.login(credentials);

            // Dual-token response
            if (response.data.data) {
                const { access_token, refresh_token: rt, user: userData } = response.data.data;
                setToken(access_token);
                if (rt) setRefreshTokenValue(rt);
                setUser(userData);
                return userData;
            }

            // Legacy fallback
            const { token: singleToken, user: userData } = response.data;
            if (singleToken && userData) {
                setToken(singleToken);
                setUser(userData);
                return userData;
            }

            throw new Error("Invalid login response format");
        } catch (err: unknown) {
            const msg = extractErrorMessage(err, "Login failed");
            error.value = msg;
            throw new Error(msg);
        } finally {
            isLoading.value = false;
        }
    };

    const register = async (userData: RegisterRequest) => {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await authApi.register(userData);
            return response.data.user;
        } catch (err: unknown) {
            const msg = extractErrorMessage(err, "Registration failed");
            error.value = msg;
            throw new Error(msg);
        } finally {
            isLoading.value = false;
        }
    };

    const fetchProfile = async (clearAuthOnFailure = true) => {
        if (!token.value) return;
        isLoading.value = true;
        error.value = null;

        try {
            const response = await authApi.profile();
            setUser(response.data.user);
            return response.data.user;
        } catch (err: unknown) {
            const msg = extractErrorMessage(err, "Failed to fetch profile");
            error.value = msg;
            if (clearAuthOnFailure) clearAuth();
            throw new Error(msg);
        } finally {
            isLoading.value = false;
        }
    };

    const logout = async () => {
        isLoading.value = true;
        try {
            await authApi.logout(refreshToken.value || undefined);
        } catch {
            // silently continue — we'll clear local state regardless
        } finally {
            clearAuth();
            isLoading.value = false;
        }
    };

    const refreshAccessToken = async (): Promise<boolean> => {
        if (isCurrentlyRefreshing && refreshPromise) return refreshPromise;
        if (!refreshToken.value) return false;

        isCurrentlyRefreshing = true;

        refreshPromise = (async () => {
            try {
                const response = await fetch("/api/users/jwt_refresh", {
                    method: "POST",
                    headers: { "Content-Type": "application/json", Accept: "application/json" },
                    body: JSON.stringify({ refresh_token: refreshToken.value }),
                });

                if (!response.ok) return false;
                const data = await response.json();

                if (data.success && data.data?.access_token) {
                    setToken(data.data.access_token);
                    if (data.data.refresh_token) setRefreshTokenValue(data.data.refresh_token);
                    return true;
                }
                return false;
            } catch {
                return false;
            } finally {
                isCurrentlyRefreshing = false;
                refreshPromise = null;
            }
        })();

        return refreshPromise;
    };

    const fetchProfileWithRetry = async (maxRetries = 2, clearAuthOnFailure = true): Promise<boolean> => {
        for (let attempt = 1; attempt <= maxRetries; attempt++) {
            try {
                await fetchProfile(false);
                return true;
            } catch {
                if (attempt < maxRetries) {
                    await new Promise((r) => setTimeout(r, Math.min(1000 * 2 ** (attempt - 1), 5000)));
                }
            }
        }
        if (clearAuthOnFailure) clearAuth();
        return false;
    };

    const initialize = async () => {
        if (!loadStoredAuth()) return;

        if (isTokenExpired(token.value)) {
            const refreshed = await refreshAccessToken();
            if (!refreshed) {
                clearAuth();
                return;
            }
        }

        await fetchProfileWithRetry(2, true);
    };

    // ── Helpers ──────────────────────────────────────────────────

    function extractErrorMessage(err: unknown, fallback: string): string {
        if (err && typeof err === "object" && "response" in err) {
            const axiosError = err as { response?: { data?: { message?: string } } };
            return axiosError.response?.data?.message || fallback;
        }
        if (err instanceof Error) return err.message;
        return fallback;
    }

    return {
        // State
        user,
        token,
        refreshToken,
        isLoading,
        error,
        // Getters
        isAuthenticated,
        userName,
        userInitials,
        isAdmin,
        canAccessAdmin,
        // Actions
        setUser,
        setToken,
        setError,
        clearAuth,
        login,
        register,
        fetchProfile,
        logout,
        initialize,
        refreshAccessToken,
        isTokenExpired,
    };
});
