import { describe, it, expect, vi, beforeEach } from "vitest";
import { setActivePinia, createPinia } from "pinia";
import { usePlayersStore } from "@/stores/players";

vi.mock("@/services/api", () => ({
    playersApi: {
        getAll: vi.fn(),
        getById: vi.fn(),
        create: vi.fn(),
        update: vi.fn(),
        delete: vi.fn(),
    },
}));

import { playersApi } from "@/services/api";

const mockPlayer = {
    id: 1,
    user_id: "uuid-123",
    name: "Alice",
    color: "#ff0000",
    avatar_emoji: "",
    created: "2025-01-01T00:00:00",
    modified: "2025-01-01T00:00:00",
};

describe("usePlayersStore", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it("fetchPlayers loads players and pagination", async () => {
        vi.mocked(playersApi.getAll).mockResolvedValue({
            data: {
                success: true,
                data: [mockPlayer],
                pagination: { page: 1, limit: 100, total: 1, pages: 1 },
            },
        } as never);

        const store = usePlayersStore();
        await store.fetchPlayers();

        expect(store.players).toHaveLength(1);
        expect(store.players[0].name).toBe("Alice");
        expect(store.totalPlayers).toBe(1);
        expect(store.loading).toBe(false);
    });

    it("fetchPlayers sets error on failure", async () => {
        vi.mocked(playersApi.getAll).mockRejectedValue({
            response: { data: { message: "Server error" } },
        });

        const store = usePlayersStore();
        await store.fetchPlayers();

        expect(store.error).toBe("Server error");
        expect(store.players).toHaveLength(0);
    });

    it("createPlayer adds to list", async () => {
        vi.mocked(playersApi.create).mockResolvedValue({
            data: { success: true, data: mockPlayer },
        } as never);

        const store = usePlayersStore();
        const result = await store.createPlayer({ name: "Alice", color: "#ff0000", avatar_emoji: "" });

        expect(result.name).toBe("Alice");
        expect(store.players).toHaveLength(1);
        expect(store.totalPlayers).toBe(1);
    });

    it("updatePlayer updates list and currentPlayer", async () => {
        const updated = { ...mockPlayer, name: "Alice Updated" };
        vi.mocked(playersApi.update).mockResolvedValue({
            data: { success: true, data: updated },
        } as never);

        const store = usePlayersStore();
        store.players = [mockPlayer];
        store.currentPlayer = mockPlayer;

        const result = await store.updatePlayer(1, { name: "Alice Updated" });

        expect(result.name).toBe("Alice Updated");
        expect(store.players[0].name).toBe("Alice Updated");
        expect(store.currentPlayer?.name).toBe("Alice Updated");
    });

    it("deletePlayer removes from list", async () => {
        vi.mocked(playersApi.delete).mockResolvedValue({
            data: { success: true, message: "Deleted" },
        } as never);

        const store = usePlayersStore();
        store.players = [mockPlayer];
        store.pagination.total = 1;

        await store.deletePlayer(1);

        expect(store.players).toHaveLength(0);
        expect(store.totalPlayers).toBe(0);
    });

    it("deletePlayer throws and sets error on failure", async () => {
        vi.mocked(playersApi.delete).mockRejectedValue({
            response: { data: { message: "Player in use" } },
        });

        const store = usePlayersStore();
        store.players = [mockPlayer];

        await expect(store.deletePlayer(1)).rejects.toThrow("Player in use");
        expect(store.error).toBe("Player in use");
        expect(store.players).toHaveLength(1);
    });

    it("setSearch and setSort update state", () => {
        const store = usePlayersStore();
        store.setSearch("test");
        expect(store.searchQuery).toBe("test");

        store.setSort("created", "desc");
        expect(store.sortField).toBe("created");
        expect(store.sortDirection).toBe("desc");
    });
});
