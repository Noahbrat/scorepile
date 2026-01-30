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
// Example Resource: Item
// =====================================================

export interface Item {
    id: number;
    user_id: string;
    title: string;
    description?: string;
    status: "active" | "archived" | "draft";
    created?: string;
    modified?: string;
}

export type ItemInput = Omit<Item, "id" | "user_id" | "created" | "modified">;
