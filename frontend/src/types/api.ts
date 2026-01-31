/**
 * Shared API types for the frontend
 */

// =====================================================
// Generic API Response Types
// =====================================================

export interface ApiResponse<T> {
    success: boolean;
    data: T;
    message?: string;
    errors?: Record<string, string[]>;
}

export interface PaginatedApiResponse<T> {
    success: boolean;
    data: T;
    pagination?: ApiPagination;
}

export interface ApiPagination {
    page: number;
    limit: number;
    total: number;
    pages: number;
}

// =====================================================
// Auth Types
// =====================================================

export interface User {
    id: string;
    email: string;
    username: string;
    first_name?: string;
    last_name?: string;
    active: boolean;
    is_superuser?: boolean;
    role?: string;
    created?: string;
    modified?: string;
}

export interface LoginRequest {
    email: string;
    password: string;
}

export interface LoginResponse {
    success: boolean;
    data: {
        access_token: string;
        refresh_token: string;
        token_type: string;
        expires_in: number;
        user: User;
    };
    // Legacy single-token format fallback
    token?: string;
    user?: User;
}

export interface RegisterRequest {
    email: string;
    username: string;
    password: string;
    first_name?: string;
    last_name?: string;
}

export interface RegisterResponse {
    success: boolean;
    message: string;
    user: User;
}

export interface ProfileResponse {
    success: boolean;
    user: User;
}

// =====================================================
// Player
// =====================================================

export interface Player {
    id: number;
    user_id: string;
    name: string;
    color?: string;
    avatar_emoji?: string;
    created?: string;
    modified?: string;
}

export type PlayerInput = Omit<Player, "id" | "user_id" | "created" | "modified">;

// =====================================================
// Game Type
// =====================================================

export interface GameType {
    id: number;
    user_id: string;
    name: string;
    description?: string;
    scoring_direction: "high_wins" | "low_wins";
    default_rounds?: number;
    created?: string;
    modified?: string;
}

export type GameTypeInput = Omit<GameType, "id" | "user_id" | "created" | "modified">;

// =====================================================
// Game
// =====================================================

export interface Game {
    id: number;
    user_id: string;
    game_type_id?: number;
    name: string;
    status: "active" | "completed" | "abandoned";
    notes?: string;
    completed_at?: string;
    created?: string;
    modified?: string;
    game_type?: GameType;
    game_players?: GamePlayer[];
    rounds?: Round[];
}

export interface GameInput {
    name: string;
    game_type_id?: number | null;
    notes?: string;
    player_ids?: number[];
}

// =====================================================
// Game Player (join table)
// =====================================================

export interface GamePlayer {
    id: number;
    game_id: number;
    player_id: number;
    final_rank?: number;
    total_score: number;
    is_winner: boolean;
    created?: string;
    modified?: string;
    player?: Player;
    scores?: Score[];
}

// =====================================================
// Round
// =====================================================

export interface Round {
    id: number;
    game_id: number;
    round_number: number;
    name?: string;
    created?: string;
    modified?: string;
    scores?: Score[];
}

export interface RoundInput {
    game_id: number;
    round_number?: number;
    name?: string;
}

// =====================================================
// Score
// =====================================================

export interface Score {
    id: number;
    round_id: number;
    game_player_id: number;
    points: number;
    notes?: string;
    created?: string;
    modified?: string;
    game_player?: GamePlayer;
}

export interface ScoreInput {
    round_id: number;
    game_player_id: number;
    points: number;
    notes?: string;
}
