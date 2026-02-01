import { ref, computed } from "vue";
import { defineStore } from "pinia";
import { gamesApi } from "@/services/api";
import type { Game, GameInput, ApiPagination } from "@/types/api";

interface PaginationInfo {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
}

export const useGamesStore = defineStore("games", () => {
    // ── State ────────────────────────────────────────────────────
    const games = ref<Game[]>([]);
    const currentGame = ref<Game | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const pagination = ref<PaginationInfo>({
        page: 1,
        limit: 20,
        total: 0,
        totalPages: 0,
    });
    const searchQuery = ref("");
    const sortField = ref<string | undefined>("modified");
    const sortDirection = ref<"asc" | "desc">("desc");
    const statusFilter = ref<string | undefined>(undefined);

    // ── Getters ──────────────────────────────────────────────────
    const hasGames = computed(() => games.value.length > 0);
    const totalGames = computed(() => pagination.value.total);
    const activeGames = computed(() =>
        games.value.filter((g) => g.status === "active"),
    );

    // ── Actions ──────────────────────────────────────────────────

    async function fetchGames(page?: number, limit?: number) {
        loading.value = true;
        error.value = null;

        try {
            const p = page ?? pagination.value.page;
            const l = limit ?? pagination.value.limit;

            const response = await gamesApi.getAll(
                p,
                l,
                sortField.value,
                sortDirection.value,
                searchQuery.value || undefined,
                statusFilter.value,
            );

            games.value = response.data.data;

            if (response.data.pagination) {
                const pg: ApiPagination = response.data.pagination;
                pagination.value = {
                    page: pg.page,
                    limit: pg.limit,
                    total: pg.total,
                    totalPages: pg.pages,
                };
            }
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to fetch games");
        } finally {
            loading.value = false;
        }
    }

    async function fetchGame(id: number) {
        loading.value = true;
        error.value = null;

        try {
            const response = await gamesApi.getById(id);
            currentGame.value = response.data.data;
            return response.data.data;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to fetch game");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function createGame(data: GameInput) {
        loading.value = true;
        error.value = null;

        try {
            const response = await gamesApi.create(data);
            const newGame = response.data.data;
            games.value.unshift(newGame);
            return newGame;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to create game");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function updateGame(id: number, data: Partial<GameInput>) {
        loading.value = true;
        error.value = null;

        try {
            const response = await gamesApi.update(id, data);
            const updated = response.data.data;

            const index = games.value.findIndex((g) => g.id === id);
            if (index !== -1) {
                games.value[index] = updated;
            }

            if (currentGame.value?.id === id) {
                currentGame.value = updated;
            }

            return updated;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to update game");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function deleteGame(id: number) {
        loading.value = true;
        error.value = null;

        try {
            await gamesApi.delete(id);
            games.value = games.value.filter((g) => g.id !== id);

            if (currentGame.value?.id === id) {
                currentGame.value = null;
            }
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to delete game");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function completeGame(id: number) {
        loading.value = true;
        error.value = null;

        try {
            const response = await gamesApi.complete(id);
            const completed = response.data.data;

            const index = games.value.findIndex((g) => g.id === id);
            if (index !== -1) {
                games.value[index] = completed;
            }

            if (currentGame.value?.id === id) {
                currentGame.value = completed;
            }

            return completed;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to complete game");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    function setSearch(query: string) {
        searchQuery.value = query;
    }

    function setSort(field: string, direction: "asc" | "desc") {
        sortField.value = field;
        sortDirection.value = direction;
    }

    function setPage(page: number) {
        pagination.value.page = page;
    }

    function setStatusFilter(status: string | undefined) {
        statusFilter.value = status;
    }

    // ── Helpers ──────────────────────────────────────────────────

    function extractError(err: unknown, fallback: string): string {
        if (err && typeof err === "object" && "response" in err) {
            const axiosError = err as { response?: { data?: { message?: string } } };
            return axiosError.response?.data?.message || fallback;
        }
        if (err instanceof Error) return err.message;
        return fallback;
    }

    return {
        // State
        games,
        currentGame,
        loading,
        error,
        pagination,
        searchQuery,
        sortField,
        sortDirection,
        statusFilter,
        // Getters
        hasGames,
        totalGames,
        activeGames,
        // Actions
        fetchGames,
        fetchGame,
        createGame,
        updateGame,
        deleteGame,
        completeGame,
        setSearch,
        setSort,
        setPage,
        setStatusFilter,
    };
});
