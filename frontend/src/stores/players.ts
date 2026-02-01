import { ref, computed } from "vue";
import { defineStore } from "pinia";
import { playersApi } from "@/services/api";
import type { Player, PlayerInput, ApiPagination } from "@/types/api";

interface PaginationInfo {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
}

export const usePlayersStore = defineStore("players", () => {
    // ── State ────────────────────────────────────────────────────
    const players = ref<Player[]>([]);
    const currentPlayer = ref<Player | null>(null);
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

    // Pool total — the unfiltered count of all players (never affected by search)
    const poolTotal = ref(0);

    // ── Getters ──────────────────────────────────────────────────
    const hasPlayers = computed(() => players.value.length > 0);
    const totalPlayers = computed(() => pagination.value.total);
    const isFiltered = computed(() => !!searchQuery.value);

    // ── Actions ──────────────────────────────────────────────────

    async function fetchPlayers(page?: number, limit?: number) {
        loading.value = true;
        error.value = null;

        try {
            const p = page ?? pagination.value.page;
            const l = limit ?? pagination.value.limit;

            const response = await playersApi.getAll(
                p,
                l,
                sortField.value,
                sortDirection.value,
                searchQuery.value || undefined,
            );

            players.value = response.data.data;

            if (response.data.pagination) {
                const pg: ApiPagination = response.data.pagination;
                pagination.value = {
                    page: pg.page,
                    limit: pg.limit,
                    total: pg.total,
                    totalPages: pg.pages,
                };

                // Track unfiltered pool total
                if (!searchQuery.value) {
                    poolTotal.value = pg.total;
                }
            }
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to fetch players");
        } finally {
            loading.value = false;
        }
    }

    async function fetchPlayer(id: number) {
        loading.value = true;
        error.value = null;

        try {
            const response = await playersApi.getById(id);
            currentPlayer.value = response.data.data;
            return response.data.data;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to fetch player");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function createPlayer(data: PlayerInput) {
        loading.value = true;
        error.value = null;

        try {
            const response = await playersApi.create(data);
            const newPlayer = response.data.data;
            players.value.unshift(newPlayer);
            pagination.value.total++;
            return newPlayer;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to create player");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function updatePlayer(id: number, data: Partial<PlayerInput>) {
        loading.value = true;
        error.value = null;

        try {
            const response = await playersApi.update(id, data);
            const updated = response.data.data;

            const index = players.value.findIndex((p) => p.id === id);
            if (index !== -1) {
                players.value[index] = updated;
            }

            if (currentPlayer.value?.id === id) {
                currentPlayer.value = updated;
            }

            return updated;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to update player");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function deletePlayer(id: number) {
        loading.value = true;
        error.value = null;

        try {
            await playersApi.delete(id);
            players.value = players.value.filter((p) => p.id !== id);
            pagination.value.total--;

            if (currentPlayer.value?.id === id) {
                currentPlayer.value = null;
            }
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to delete player");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    /**
     * Fetch just the pool total without affecting search state or player list.
     * Used by the Dashboard to get an accurate count regardless of search filters.
     */
    async function fetchPoolTotal() {
        try {
            const response = await playersApi.getAll(1, 1, undefined, "asc", undefined);
            if (response.data.pagination) {
                poolTotal.value = response.data.pagination.total;
            }
        } catch {
            // non-critical
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
        players,
        currentPlayer,
        loading,
        error,
        pagination,
        searchQuery,
        sortField,
        sortDirection,
        poolTotal,
        // Getters
        hasPlayers,
        totalPlayers,
        isFiltered,
        // Actions
        fetchPlayers,
        fetchPlayer,
        fetchPoolTotal,
        createPlayer,
        updatePlayer,
        deletePlayer,
        setSearch,
        setSort,
        setPage,
    };
});
