import { describe, it, expect, vi, beforeEach } from "vitest";
import { setActivePinia, createPinia } from "pinia";
import { useGamesStore } from "@/stores/games";

vi.mock("@/services/api", () => ({
    gamesApi: {
        getAll: vi.fn(),
        getById: vi.fn(),
        create: vi.fn(),
        update: vi.fn(),
        delete: vi.fn(),
        complete: vi.fn(),
    },
}));

import { gamesApi } from "@/services/api";

const mockGame = {
    id: 1,
    user_id: "uuid-123",
    name: "Friday Cribbage",
    status: "active" as const,
    game_type_id: 1,
    created: "2025-01-01T00:00:00",
    modified: "2025-01-01T00:00:00",
    game_players: [],
};

describe("useGamesStore", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it("fetchGames loads games and pagination", async () => {
        vi.mocked(gamesApi.getAll).mockResolvedValue({
            data: {
                success: true,
                data: [mockGame],
                pagination: { page: 1, limit: 20, total: 1, pages: 1 },
            },
        } as never);

        const store = useGamesStore();
        await store.fetchGames();

        expect(store.games).toHaveLength(1);
        expect(store.games[0].name).toBe("Friday Cribbage");
        expect(store.totalGames).toBe(1);
    });

    it("fetchGames sets error on failure", async () => {
        vi.mocked(gamesApi.getAll).mockRejectedValue({
            response: { data: { message: "Forbidden" } },
        });

        const store = useGamesStore();
        await store.fetchGames();

        expect(store.error).toBe("Forbidden");
    });

    it("createGame adds to list and returns game", async () => {
        vi.mocked(gamesApi.create).mockResolvedValue({
            data: { success: true, data: mockGame },
        } as never);

        const store = useGamesStore();
        const result = await store.createGame({
            name: "Friday Cribbage",
            player_ids: [1, 2],
        });

        expect(result.name).toBe("Friday Cribbage");
        expect(store.games).toHaveLength(1);
    });

    it("updateGame updates list and currentGame", async () => {
        const updated = { ...mockGame, name: "Saturday Cribbage" };
        vi.mocked(gamesApi.update).mockResolvedValue({
            data: { success: true, data: updated },
        } as never);

        const store = useGamesStore();
        store.games = [mockGame];
        store.currentGame = mockGame;

        const result = await store.updateGame(1, { name: "Saturday Cribbage" });

        expect(result.name).toBe("Saturday Cribbage");
        expect(store.games[0].name).toBe("Saturday Cribbage");
        expect(store.currentGame?.name).toBe("Saturday Cribbage");
    });

    it("deleteGame removes from list", async () => {
        vi.mocked(gamesApi.delete).mockResolvedValue({
            data: { success: true, message: "Deleted" },
        } as never);

        const store = useGamesStore();
        store.games = [mockGame];

        await store.deleteGame(1);

        expect(store.games).toHaveLength(0);
    });

    it("completeGame updates status and rankings", async () => {
        const completedGame = {
            ...mockGame,
            status: "completed" as const,
            game_players: [
                { id: 1, game_id: 1, player_id: 1, total_score: 100, final_rank: 1, is_winner: true },
                { id: 2, game_id: 1, player_id: 2, total_score: 80, final_rank: 2, is_winner: false },
            ],
        };
        vi.mocked(gamesApi.complete).mockResolvedValue({
            data: { success: true, data: completedGame },
        } as never);

        const store = useGamesStore();
        store.games = [mockGame];
        store.currentGame = mockGame;

        const result = await store.completeGame(1);

        expect(result.status).toBe("completed");
        expect(store.games[0].status).toBe("completed");
        expect(store.currentGame?.status).toBe("completed");
    });

    it("completeGame throws on failure", async () => {
        vi.mocked(gamesApi.complete).mockRejectedValue({
            response: { data: { message: "Game is already completed" } },
        });

        const store = useGamesStore();
        store.games = [mockGame];

        await expect(store.completeGame(1)).rejects.toThrow("Game is already completed");
        expect(store.error).toBe("Game is already completed");
    });

    it("activeGames filters by status", async () => {
        vi.mocked(gamesApi.getAll).mockResolvedValue({
            data: {
                success: true,
                data: [
                    mockGame,
                    { ...mockGame, id: 2, status: "completed" },
                ],
                pagination: { page: 1, limit: 20, total: 2, pages: 1 },
            },
        } as never);

        const store = useGamesStore();
        await store.fetchGames();

        expect(store.activeGames).toHaveLength(1);
        expect(store.activeGames[0].status).toBe("active");
    });

    it("setStatusFilter updates filter state", () => {
        const store = useGamesStore();
        store.setStatusFilter("completed");
        expect(store.statusFilter).toBe("completed");

        store.setStatusFilter(undefined);
        expect(store.statusFilter).toBeUndefined();
    });
});
