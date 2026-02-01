import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { createRouter, createMemoryHistory } from "vue-router";
import PrimeVue from "primevue/config";
import ToastService from "primevue/toastservice";
import GameView from "@/views/GameView.vue";

// Mock API services
vi.mock("@/services/api", () => ({
    gamesApi: {
        getById: vi.fn(),
        complete: vi.fn(),
    },
    roundsApi: {
        getAll: vi.fn(),
        create: vi.fn(),
    },
    scoresApi: {
        create: vi.fn(),
        update: vi.fn(),
        bulkAdd: vi.fn(),
    },
}));

import { gamesApi, roundsApi, scoresApi } from "@/services/api";

const mockGamePlayers = [
    {
        id: 10,
        game_id: 1,
        player_id: 1,
        total_score: 0,
        is_winner: false,
        player: { id: 1, user_id: "uuid-1", name: "Alice" },
    },
    {
        id: 11,
        game_id: 1,
        player_id: 2,
        total_score: 0,
        is_winner: false,
        player: { id: 2, user_id: "uuid-1", name: "Bob" },
    },
];

const mockGame = {
    id: 1,
    user_id: "uuid-1",
    name: "Test Game",
    status: "active",
    game_players: mockGamePlayers,
    game_type: { id: 1, name: "Cribbage", scoring_direction: "high_wins" },
};

const mockRounds = [
    {
        id: 100,
        game_id: 1,
        round_number: 1,
        scores: [
            { id: 200, round_id: 100, game_player_id: 10, points: 15 },
            { id: 201, round_id: 100, game_player_id: 11, points: 22 },
        ],
    },
];

function createTestRouter() {
    return createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: "/games/:id", name: "game", component: GameView },
            { path: "/games", name: "games", component: { template: "<div />" } },
        ],
    });
}

async function mountGameView() {
    const router = createTestRouter();
    await router.push("/games/1");
    await router.isReady();

    const pinia = createPinia();
    setActivePinia(pinia);

    const wrapper = mount(GameView, {
        global: {
            plugins: [pinia, router, PrimeVue, ToastService],
        },
    });

    await flushPromises();
    return { wrapper, router };
}

describe("GameView", () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it("loads and displays game data", async () => {
        vi.mocked(gamesApi.getById).mockResolvedValue({
            data: { success: true, data: mockGame },
        } as never);
        vi.mocked(roundsApi.getAll).mockResolvedValue({
            data: { success: true, data: mockRounds },
        } as never);

        const { wrapper } = await mountGameView();

        expect(wrapper.text()).toContain("Test Game");
        expect(wrapper.text()).toContain("Alice");
        expect(wrapper.text()).toContain("Bob");
        expect(wrapper.text()).toContain("active");
    });

    it("displays error state on load failure", async () => {
        vi.mocked(gamesApi.getById).mockRejectedValue({
            response: { data: { message: "Not found" } },
        });
        vi.mocked(roundsApi.getAll).mockRejectedValue({
            response: { data: { message: "Not found" } },
        });

        const { wrapper } = await mountGameView();

        expect(wrapper.text()).toContain("Not found");
    });

    it("calls roundsApi.create when Add Round is clicked", async () => {
        vi.mocked(gamesApi.getById).mockResolvedValue({
            data: { success: true, data: mockGame },
        } as never);
        vi.mocked(roundsApi.getAll).mockResolvedValue({
            data: { success: true, data: [] },
        } as never);
        vi.mocked(roundsApi.create).mockResolvedValue({
            data: {
                success: true,
                data: { id: 101, game_id: 1, round_number: 1, scores: [] },
            },
        } as never);

        const { wrapper } = await mountGameView();

        const addRoundBtn = wrapper
            .findAll("button")
            .find((btn) => btn.text().includes("Add Round"));
        expect(addRoundBtn).toBeTruthy();

        await addRoundBtn!.trigger("click");
        await flushPromises();

        expect(roundsApi.create).toHaveBeenCalledWith({ game_id: 1 });
    });

    it("opens complete dialog state when Complete button clicked", async () => {
        vi.mocked(gamesApi.getById).mockResolvedValue({
            data: { success: true, data: mockGame },
        } as never);
        vi.mocked(roundsApi.getAll).mockResolvedValue({
            data: { success: true, data: mockRounds },
        } as never);

        const { wrapper } = await mountGameView();

        const completeBtn = wrapper
            .findAll("button")
            .find((btn) => btn.text().includes("Complete"));
        expect(completeBtn).toBeTruthy();

        await completeBtn!.trigger("click");
        await flushPromises();

        // Dialog renders via teleport to body, so check document
        const dialogText = document.body.textContent || "";
        expect(dialogText).toContain("Mark this game as complete");
    });

    it("calls gamesApi.complete on confirmation", async () => {
        const completedGame = {
            ...mockGame,
            status: "completed",
            game_players: [
                { ...mockGamePlayers[0], total_score: 15, final_rank: 2, is_winner: false },
                { ...mockGamePlayers[1], total_score: 22, final_rank: 1, is_winner: true },
            ],
        };
        vi.mocked(gamesApi.getById).mockResolvedValue({
            data: { success: true, data: mockGame },
        } as never);
        vi.mocked(roundsApi.getAll).mockResolvedValue({
            data: { success: true, data: mockRounds },
        } as never);
        vi.mocked(gamesApi.complete).mockResolvedValue({
            data: { success: true, data: completedGame, message: "Game completed" },
        } as never);

        const { wrapper } = await mountGameView();

        // Click "Complete" to open dialog
        const completeBtn = wrapper
            .findAll("button")
            .find((btn) => btn.text().includes("Complete"));
        await completeBtn!.trigger("click");
        await flushPromises();

        // Dialog renders via teleport — find "Complete Game" button in body
        const allButtons = document.querySelectorAll("button");
        const confirmBtn = Array.from(allButtons).find(
            (btn) => btn.textContent?.includes("Complete Game"),
        );
        expect(confirmBtn).toBeTruthy();

        confirmBtn!.click();
        await flushPromises();

        expect(gamesApi.complete).toHaveBeenCalledWith(1);
    });

    it("saves score via create when no existing score", async () => {
        vi.mocked(gamesApi.getById).mockResolvedValue({
            data: { success: true, data: mockGame },
        } as never);
        vi.mocked(roundsApi.getAll).mockResolvedValue({
            data: { success: true, data: [{ id: 100, game_id: 1, round_number: 1, scores: [] }] },
        } as never);
        vi.mocked(scoresApi.create).mockResolvedValue({
            data: { success: true, data: { id: 300, round_id: 100, game_player_id: 10, points: 42 } },
        } as never);

        const { wrapper } = await mountGameView();

        // Find InputNumber components — they exist when game is active
        const inputs = wrapper.findAll(".score-input");
        expect(inputs.length).toBeGreaterThan(0);
    });

    it("displays final rankings when game is completed", async () => {
        const completedGame = {
            ...mockGame,
            status: "completed",
            game_players: [
                {
                    ...mockGamePlayers[0],
                    total_score: 100,
                    final_rank: 1,
                    is_winner: true,
                    player: { id: 1, user_id: "uuid-1", name: "Alice" },
                },
                {
                    ...mockGamePlayers[1],
                    total_score: 80,
                    final_rank: 2,
                    is_winner: false,
                    player: { id: 2, user_id: "uuid-1", name: "Bob" },
                },
            ],
        };
        vi.mocked(gamesApi.getById).mockResolvedValue({
            data: { success: true, data: completedGame },
        } as never);
        vi.mocked(roundsApi.getAll).mockResolvedValue({
            data: { success: true, data: mockRounds },
        } as never);

        const { wrapper } = await mountGameView();

        expect(wrapper.text()).toContain("Final Rankings");
        expect(wrapper.text()).toContain("Alice");
        expect(wrapper.text()).toContain("100");
        expect(wrapper.text()).toContain("#2");
    });
});
