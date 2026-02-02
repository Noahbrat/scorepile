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
    Player,
    PlayerInput,
    GameType,
    GameTypeInput,
    Game,
    GameInput,
    Round,
    RoundInput,
    RoundData,
    Score,
    ScoreInput,
    CalculateRoundResult,
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
// Players CRUD API
// =====================================================

export const playersApi = {
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
        return api.get<PaginatedApiResponse<Player[]>>(`/players.json?${params}`);
    },

    getById: (id: number) =>
        api.get<ApiResponse<Player>>(`/players/${id}.json`),

    create: (data: PlayerInput) =>
        api.post<ApiResponse<Player>>("/players.json", data),

    update: (id: number, data: Partial<PlayerInput>) =>
        api.put<ApiResponse<Player>>(`/players/${id}.json`, data),

    delete: (id: number) =>
        api.delete<ApiResponse<null>>(`/players/${id}.json`),
};

// =====================================================
// Game Types CRUD API
// =====================================================

export const gameTypesApi = {
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
        return api.get<PaginatedApiResponse<GameType[]>>(`/game-types.json?${params}`);
    },

    getById: (id: number) =>
        api.get<ApiResponse<GameType>>(`/game-types/${id}.json`),

    create: (data: GameTypeInput) =>
        api.post<ApiResponse<GameType>>("/game-types.json", data),

    update: (id: number, data: Partial<GameTypeInput>) =>
        api.put<ApiResponse<GameType>>(`/game-types/${id}.json`, data),

    delete: (id: number) =>
        api.delete<ApiResponse<null>>(`/game-types/${id}.json`),
};

// =====================================================
// Games CRUD API
// =====================================================

export const gamesApi = {
    getAll: (
        page: number = 1,
        limit: number = 20,
        sort?: string,
        direction?: "asc" | "desc",
        search?: string,
        status?: string,
    ) => {
        const params = new URLSearchParams({
            page: page.toString(),
            limit: limit.toString(),
        });
        if (sort) params.append("sort", sort);
        if (direction) params.append("direction", direction);
        if (search) params.append("search", search);
        if (status) params.append("status", status);
        return api.get<PaginatedApiResponse<Game[]>>(`/games.json?${params}`);
    },

    getById: (id: number) =>
        api.get<ApiResponse<Game>>(`/games/${id}.json`),

    create: (data: GameInput) =>
        api.post<ApiResponse<Game>>("/games.json", data),

    update: (id: number, data: Partial<GameInput>) =>
        api.put<ApiResponse<Game>>(`/games/${id}.json`, data),

    delete: (id: number) =>
        api.delete<ApiResponse<null>>(`/games/${id}.json`),

    complete: (id: number) =>
        api.post<ApiResponse<Game>>(`/games/${id}/complete.json`),

    calculateRound: (id: number, roundData: RoundData) =>
        api.post<ApiResponse<CalculateRoundResult>>(
            `/games/${id}/calculate-round.json`,
            { round_data: roundData },
        ),

    saveRound: (id: number, roundData: RoundData, dealerGamePlayerId?: number) =>
        api.post<ApiResponse<Round>>(
            `/games/${id}/save-round.json`,
            {
                round_data: roundData,
                dealer_game_player_id: dealerGamePlayerId,
            },
        ),

    assignTeams: (id: number, teams: Record<string, number>) =>
        api.post<ApiResponse<Game>>(
            `/games/${id}/assign-teams.json`,
            { teams },
        ),

    completeRound: (gameId: number, roundId: number, tricksWon: Record<string, number>) =>
        api.post<ApiResponse<Round>>(
            `/games/${gameId}/rounds/${roundId}/complete.json`,
            { tricks_won: tricksWon },
        ),

    cancelRound: (gameId: number, roundId: number) =>
        api.post<ApiResponse<null>>(
            `/games/${gameId}/rounds/${roundId}/cancel.json`,
        ),
};

// =====================================================
// Rounds CRUD API
// =====================================================

export const roundsApi = {
    getAll: (gameId: number) =>
        api.get<ApiResponse<Round[]>>(`/rounds.json?game_id=${gameId}`),

    getById: (id: number) =>
        api.get<ApiResponse<Round>>(`/rounds/${id}.json`),

    create: (data: RoundInput) =>
        api.post<ApiResponse<Round>>("/rounds.json", data),

    update: (id: number, data: Partial<RoundInput>) =>
        api.put<ApiResponse<Round>>(`/rounds/${id}.json`, data),

    delete: (id: number) =>
        api.delete<ApiResponse<null>>(`/rounds/${id}.json`),
};

// =====================================================
// Scores CRUD API
// =====================================================

export const scoresApi = {
    getAll: (roundId: number) =>
        api.get<ApiResponse<Score[]>>(`/scores.json?round_id=${roundId}`),

    getById: (id: number) =>
        api.get<ApiResponse<Score>>(`/scores/${id}.json`),

    create: (data: ScoreInput) =>
        api.post<ApiResponse<Score>>("/scores.json", data),

    bulkAdd: (scores: ScoreInput[]) =>
        api.post<ApiResponse<Score[]>>("/scores.json", { scores }),

    update: (id: number, data: Partial<ScoreInput>) =>
        api.put<ApiResponse<Score>>(`/scores/${id}.json`, data),

    delete: (id: number) =>
        api.delete<ApiResponse<null>>(`/scores/${id}.json`),
};

export default api;
