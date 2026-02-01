import { ref, computed } from "vue";
import { defineStore } from "pinia";
import { gameTypesApi } from "@/services/api";
import type { GameType, GameTypeInput, ApiPagination } from "@/types/api";

interface PaginationInfo {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
}

export const useGameTypesStore = defineStore("gameTypes", () => {
    // ── State ────────────────────────────────────────────────────
    const gameTypes = ref<GameType[]>([]);
    const currentGameType = ref<GameType | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const pagination = ref<PaginationInfo>({
        page: 1,
        limit: 100,
        total: 0,
        totalPages: 0,
    });
    const searchQuery = ref("");
    const sortField = ref<string | undefined>("name");
    const sortDirection = ref<"asc" | "desc">("asc");

    // ── Getters ──────────────────────────────────────────────────
    const hasGameTypes = computed(() => gameTypes.value.length > 0);
    const totalGameTypes = computed(() => pagination.value.total);

    // ── Actions ──────────────────────────────────────────────────

    async function fetchGameTypes(page?: number, limit?: number) {
        loading.value = true;
        error.value = null;

        try {
            const p = page ?? pagination.value.page;
            const l = limit ?? pagination.value.limit;

            const response = await gameTypesApi.getAll(
                p,
                l,
                sortField.value,
                sortDirection.value,
                searchQuery.value || undefined,
            );

            gameTypes.value = response.data.data;

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
            error.value = extractError(err, "Failed to fetch game types");
        } finally {
            loading.value = false;
        }
    }

    async function fetchGameType(id: number) {
        loading.value = true;
        error.value = null;

        try {
            const response = await gameTypesApi.getById(id);
            currentGameType.value = response.data.data;
            return response.data.data;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to fetch game type");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function createGameType(data: GameTypeInput) {
        loading.value = true;
        error.value = null;

        try {
            const response = await gameTypesApi.create(data);
            const newGameType = response.data.data;
            gameTypes.value.unshift(newGameType);
            pagination.value.total++;
            return newGameType;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to create game type");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function updateGameType(id: number, data: Partial<GameTypeInput>) {
        loading.value = true;
        error.value = null;

        try {
            const response = await gameTypesApi.update(id, data);
            const updated = response.data.data;

            const index = gameTypes.value.findIndex((gt) => gt.id === id);
            if (index !== -1) {
                gameTypes.value[index] = updated;
            }

            if (currentGameType.value?.id === id) {
                currentGameType.value = updated;
            }

            return updated;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to update game type");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function deleteGameType(id: number) {
        loading.value = true;
        error.value = null;

        try {
            await gameTypesApi.delete(id);
            gameTypes.value = gameTypes.value.filter((gt) => gt.id !== id);
            pagination.value.total--;

            if (currentGameType.value?.id === id) {
                currentGameType.value = null;
            }
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to delete game type");
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
        gameTypes,
        currentGameType,
        loading,
        error,
        pagination,
        searchQuery,
        sortField,
        sortDirection,
        // Getters
        hasGameTypes,
        totalGameTypes,
        // Actions
        fetchGameTypes,
        fetchGameType,
        createGameType,
        updateGameType,
        deleteGameType,
        setSearch,
        setSort,
        setPage,
    };
});
