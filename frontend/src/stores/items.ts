import { ref, computed } from "vue";
import { defineStore } from "pinia";
import { itemsApi } from "@/services/api";
import type { Item, ItemInput, ApiPagination } from "@/types/api";

interface PaginationInfo {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
}

export const useItemsStore = defineStore("items", () => {
    // ── State ────────────────────────────────────────────────────
    const items = ref<Item[]>([]);
    const currentItem = ref<Item | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const pagination = ref<PaginationInfo>({
        page: 1,
        limit: 20,
        total: 0,
        totalPages: 0,
    });
    const searchQuery = ref("");
    const sortField = ref<string | undefined>(undefined);
    const sortDirection = ref<"asc" | "desc">("asc");

    // ── Getters ──────────────────────────────────────────────────
    const hasItems = computed(() => items.value.length > 0);
    const totalItems = computed(() => pagination.value.total);

    // ── Actions ──────────────────────────────────────────────────

    async function fetchItems(page?: number, limit?: number) {
        loading.value = true;
        error.value = null;

        try {
            const p = page ?? pagination.value.page;
            const l = limit ?? pagination.value.limit;

            const response = await itemsApi.getAll(
                p,
                l,
                sortField.value,
                sortDirection.value,
                searchQuery.value || undefined,
            );

            items.value = response.data.data;

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
            error.value = extractError(err, "Failed to fetch items");
        } finally {
            loading.value = false;
        }
    }

    async function fetchItem(id: number) {
        loading.value = true;
        error.value = null;

        try {
            const response = await itemsApi.getById(id);
            currentItem.value = response.data.data;
            return response.data.data;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to fetch item");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function createItem(data: ItemInput) {
        loading.value = true;
        error.value = null;

        try {
            const response = await itemsApi.create(data);
            const newItem = response.data.data;
            items.value.unshift(newItem);
            return newItem;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to create item");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function updateItem(id: number, data: Partial<ItemInput>) {
        loading.value = true;
        error.value = null;

        try {
            const response = await itemsApi.update(id, data);
            const updated = response.data.data;

            // Update in list
            const index = items.value.findIndex((item) => item.id === id);
            if (index !== -1) {
                items.value[index] = updated;
            }

            // Update current if viewing
            if (currentItem.value?.id === id) {
                currentItem.value = updated;
            }

            return updated;
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to update item");
            throw new Error(error.value);
        } finally {
            loading.value = false;
        }
    }

    async function deleteItem(id: number) {
        loading.value = true;
        error.value = null;

        try {
            await itemsApi.delete(id);
            items.value = items.value.filter((item) => item.id !== id);

            if (currentItem.value?.id === id) {
                currentItem.value = null;
            }
        } catch (err: unknown) {
            error.value = extractError(err, "Failed to delete item");
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
        items,
        currentItem,
        loading,
        error,
        pagination,
        searchQuery,
        sortField,
        sortDirection,
        // Getters
        hasItems,
        totalItems,
        // Actions
        fetchItems,
        fetchItem,
        createItem,
        updateItem,
        deleteItem,
        setSearch,
        setSort,
        setPage,
    };
});
