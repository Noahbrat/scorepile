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
    user_id: string | null;
    name: string;
    description?: string;
    scoring_direction: "high_wins" | "low_wins";
    default_rounds?: number;
    scoring_config: ScoringConfig | null;
    is_system: boolean;
    created?: string;
    modified?: string;
}

export type GameTypeInput = Omit<GameType, "id" | "user_id" | "created" | "modified" | "is_system" | "scoring_config">;

// =====================================================
// Scoring Engine Configuration
// =====================================================

export interface ScoringConfig {
    engine: string;
    scoring_direction: string;
    win_condition: string;
    target_score?: number;
    lose_score?: number;
    track_dealer: boolean;
    teams?: { enabled: boolean; size: number };
    options?: ConfigOption[];
    bid_table?: Record<string, number>;
    scoring_rules?: Record<string, string | number>;
}

export interface ConfigOption {
    key: string;
    label: string;
    type: "boolean" | "number" | "select";
    choices?: { value: number | string | boolean; label: string }[];
    default: number | string | boolean;
    visible_when?: Record<string, unknown>;
}

export interface RoundData {
    [key: string]: unknown;
    bidder_team?: string;
    bid_tricks?: number;
    bid_suit?: string;
    bid_key?: string;
    tricks_won?: Record<string, number>;
    bid_made?: boolean;
}

export interface CalculateRoundResult {
    scores: Record<string, number>;
    bid_made: boolean;
    bid_value: number;
}

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
    game_config?: Record<string, unknown> | null;
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
    game_config?: Record<string, unknown>;
    team_assignments?: Record<number, number>;
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
    team?: number | null;
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
    dealer_game_player_id?: number | null;
    round_data?: RoundData | null;
    created?: string;
    modified?: string;
    scores?: Score[];
}

export interface RoundInput {
    game_id: number;
    round_number?: number;
    name?: string;
    round_data?: RoundData;
    dealer_game_player_id?: number;
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
