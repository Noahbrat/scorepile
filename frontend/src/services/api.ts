import axios, { type InternalAxiosRequestConfig } from "axios";
import { navigateToLogin } from "@/utils/navigation";
import {
    getAuthToken,
    getRefreshToken,
    setAuthToken,
    setRefreshToken,
    clearAuthTokens,
} from "@/services/storage";
import type {
    ApiResponse,
    PaginatedApiResponse,
    Item,
    ItemInput,
    LoginRequest,
    LoginResponse,
    RegisterRequest,
    RegisterResponse,
    ProfileResponse,
} from "@/types/api";

// ── Extended config for retry flags ─────────────────────────────

interface ExtendedAxiosRequestConfig extends InternalAxiosRequestConfig {
    _skipAuthInterceptor?: boolean;
    _retry?: boolean;
}

// ── Axios instance ──────────────────────────────────────────────

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || "/api",
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
    withCredentials: true,
});

// ── Token helpers ───────────────────────────────────────────────

const isTokenExpiringSoon = (): boolean => {
    const token = getAuthToken();
    if (!token) return false;
    try {
        const parts = token.split(".");
        if (parts.length !== 3) return false;
        const payload = JSON.parse(atob(parts[1]));
        if (!payload.exp) return false;
        return payload.exp - Math.floor(Date.now() / 1000) <= 300; // 5 min
    } catch {
        return false;
    }
};

// ── Token refresh state (singleton) ─────────────────────────────

let isRefreshing = false;
let refreshSubscribers: ((token: string) => void)[] = [];

const subscribeTokenRefresh = (cb: (token: string) => void) => {
    refreshSubscribers.push(cb);
};

const onTokenRefreshed = (token: string) => {
    refreshSubscribers.forEach((cb) => cb(token));
    refreshSubscribers = [];
};

const refreshAccessToken = async (): Promise<string | null> => {
    const storedRefreshToken = getRefreshToken();
    if (!storedRefreshToken) return null;

    try {
        const response = await api.post(
            "/users/jwt_refresh",
            { refresh_token: storedRefreshToken },
            { _skipAuthInterceptor: true } as ExtendedAxiosRequestConfig,
        );

        if (response.data.success && response.data.data?.access_token) {
            const newAccess = response.data.data.access_token;
            setAuthToken(newAccess);
            if (response.data.data.refresh_token) {
                setRefreshToken(response.data.data.refresh_token);
            }
            return newAccess;
        }
        return null;
    } catch {
        return null;
    }
};

// ── Request interceptor — inject Bearer token ───────────────────

api.interceptors.request.use(async (config) => {
    const extConfig = config as ExtendedAxiosRequestConfig;
    if (extConfig._skipAuthInterceptor) return config;

    const token = getAuthToken();
    if (token) {
        if (isTokenExpiringSoon() && !isRefreshing) {
            isRefreshing = true;
            try {
                const newToken = await refreshAccessToken();
                isRefreshing = false;
                if (newToken) {
                    onTokenRefreshed(newToken);
                    config.headers.Authorization = `Bearer ${newToken}`;
                    return config;
                }
            } catch {
                isRefreshing = false;
            }
            config.headers.Authorization = `Bearer ${token}`;
        } else if (isRefreshing) {
            return new Promise((resolve) => {
                subscribeTokenRefresh((newToken: string) => {
                    config.headers.Authorization = `Bearer ${newToken}`;
                    resolve(config);
                });
            });
        } else {
            config.headers.Authorization = `Bearer ${token}`;
        }
    }
    return config;
});

// ── Response interceptor — auto-refresh on 401 ─────────────────

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const original = error.config as ExtendedAxiosRequestConfig;
        if (original._skipAuthInterceptor) return Promise.reject(error);

        if (error.response?.status === 401) {
            const storedRefreshToken = getRefreshToken();

            if (!storedRefreshToken || original._retry) {
                clearAuthTokens();
                navigateToLogin();
                return Promise.reject(error);
            }

            original._retry = true;

            if (isRefreshing) {
                return new Promise((resolve) => {
                    subscribeTokenRefresh((newToken: string) => {
                        original.headers.Authorization = `Bearer ${newToken}`;
                        resolve(api.request(original));
                    });
                });
            }

            isRefreshing = true;
            try {
                const newToken = await refreshAccessToken();
                isRefreshing = false;
                if (newToken) {
                    onTokenRefreshed(newToken);
                    original.headers.Authorization = `Bearer ${newToken}`;
                    return api.request(original);
                }
            } catch {
                isRefreshing = false;
            }

            clearAuthTokens();
            navigateToLogin();
            return Promise.reject(error);
        }

        return Promise.reject(error);
    },
);

// =====================================================
// Auth API
// =====================================================

export const authApi = {
    login: (credentials: LoginRequest) =>
        api.post<LoginResponse>("/users/jwt_login", credentials),

    register: (userData: RegisterRequest) =>
        api.post<RegisterResponse>("/users/register", userData),

    profile: () => api.get<ProfileResponse>("/users/profile"),

    logout: (refreshToken?: string) =>
        api.post<ApiResponse<null>>("/users/logout", {
            refresh_token: refreshToken,
        }),
};

// =====================================================
// Items CRUD API  (example resource)
// =====================================================

export const itemsApi = {
    getAll: (
        page: number = 1,
        limit: number = 20,
        sort?: string,
        direction?: "asc" | "desc",
        search?: string,
    ) => {
        const params = new URLSearchParams({
            page: page.toString(),
            limit: limit.toString(),
        });
        if (sort) params.append("sort", sort);
        if (direction) params.append("direction", direction);
        if (search) params.append("search", search);
        return api.get<PaginatedApiResponse<Item[]>>(`/items.json?${params}`);
    },

    getById: (id: number) =>
        api.get<ApiResponse<Item>>(`/items/${id}.json`),

    create: (data: ItemInput) =>
        api.post<ApiResponse<Item>>("/items.json", data),

    update: (id: number, data: Partial<ItemInput>) =>
        api.put<ApiResponse<Item>>(`/items/${id}.json`, data),

    delete: (id: number) =>
        api.delete<ApiResponse<null>>(`/items/${id}.json`),
};

export default api;
