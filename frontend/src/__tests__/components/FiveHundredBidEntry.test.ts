import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import PrimeVue from "primevue/config";
import FiveHundredBidEntry from "@/components/FiveHundredBidEntry.vue";
import type { GamePlayer, ScoringConfig } from "@/types/api";

const mockGamePlayers: GamePlayer[] = [
    {
        id: 10, game_id: 1, player_id: 1, total_score: 0, is_winner: false, team: 1,
        player: { id: 1, user_id: "uuid-1", name: "Alice" },
    },
    {
        id: 11, game_id: 1, player_id: 2, total_score: 0, is_winner: false, team: 1,
        player: { id: 2, user_id: "uuid-1", name: "Bob" },
    },
    {
        id: 12, game_id: 1, player_id: 3, total_score: 0, is_winner: false, team: 2,
        player: { id: 3, user_id: "uuid-1", name: "Carol" },
    },
    {
        id: 13, game_id: 1, player_id: 4, total_score: 0, is_winner: false, team: 2,
        player: { id: 4, user_id: "uuid-1", name: "Dave" },
    },
];

const bidTable: Record<string, number> = {
    "6_spades": 40, "6_clubs": 60, "6_diamonds": 80, "6_hearts": 100, "6_no_trump": 120,
    "7_spades": 140, "7_clubs": 160, "7_diamonds": 180, "7_hearts": 200, "7_no_trump": 220,
    "8_spades": 240, "8_clubs": 260, "8_diamonds": 280, "8_hearts": 300, "8_no_trump": 320,
    "9_spades": 340, "9_clubs": 360, "9_diamonds": 380, "9_hearts": 400, "9_no_trump": 420,
    "10_spades": 440, "10_clubs": 460, "10_diamonds": 480, "10_hearts": 500, "10_no_trump": 520,
    misere: 250,
    open_misere: 500,
};

const mockScoringConfig: ScoringConfig = {
    engine: "five_hundred",
    scoring_direction: "high_wins",
    track_dealer: false,
    teams: { enabled: true, size: 2 },
    bid_table: bidTable,
    options: [
        { key: "misere_enabled", label: "Allow Misère", type: "boolean", default: true },
        { key: "open_misere_enabled", label: "Allow Open Misère", type: "boolean", default: true },
    ],
};

const defaultGameConfig: Record<string, unknown> = {
    misere_enabled: true,
    open_misere_enabled: true,
};

function mountComponent(overrides: Partial<{
    gamePlayers: GamePlayer[];
    scoringConfig: ScoringConfig;
    gameConfig: Record<string, unknown>;
    saving: boolean;
}> = {}) {
    const pinia = createPinia();
    setActivePinia(pinia);

    return mount(FiveHundredBidEntry, {
        props: {
            gamePlayers: overrides.gamePlayers ?? mockGamePlayers,
            scoringConfig: overrides.scoringConfig ?? mockScoringConfig,
            gameConfig: overrides.gameConfig ?? defaultGameConfig,
            saving: overrides.saving ?? false,
        },
        global: {
            plugins: [pinia, PrimeVue],
        },
    });
}

describe("FiveHundredBidEntry", () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it("renders team selection buttons", () => {
        const wrapper = mountComponent();

        expect(wrapper.text()).toContain("Alice & Bob");
        expect(wrapper.text()).toContain("Carol & Dave");
        expect(wrapper.text()).toContain("Bidding Team");
    });

    it("renders the Avondale bid grid", () => {
        const wrapper = mountComponent();

        const table = wrapper.find("table");
        expect(table.exists()).toBe(true);
        expect(wrapper.text()).toContain("40");
        expect(wrapper.text()).toContain("520");
    });

    it("has a Start Round button instead of Save Round", () => {
        const wrapper = mountComponent();

        expect(wrapper.text()).toContain("Start Round");
        expect(wrapper.text()).not.toContain("Save Round");
    });

    it("does NOT show tricks input (bid-only)", () => {
        const wrapper = mountComponent();

        expect(wrapper.text()).not.toContain("Tricks Won");
        expect(wrapper.text()).not.toContain("Score Preview");
    });

    it("disables Start Round when no team or bid selected", () => {
        const wrapper = mountComponent();

        const startButton = wrapper.findAll("button").find(b => b.text().includes("Start Round"));
        expect(startButton).toBeTruthy();
        expect(startButton!.attributes("disabled")).toBeDefined();
    });

    it("enables Start Round after selecting team and bid", async () => {
        const wrapper = mountComponent();

        // Select team
        const teamButtons = wrapper.findAll(".five-hundred-bid-entry > div:first-child button");
        await teamButtons[0].trigger("click");

        // Select bid
        const bidButtons = wrapper.findAll("table button");
        await bidButtons[0].trigger("click"); // 6 spades
        await flushPromises();

        const startButton = wrapper.findAll("button").find(b => b.text().includes("Start Round"));
        expect(startButton).toBeTruthy();
        expect(startButton!.attributes("disabled")).toBeUndefined();
    });

    it("emits save with bid-only data (no tricks_won)", async () => {
        const wrapper = mountComponent();

        // Select team
        const teamButtons = wrapper.findAll(".five-hundred-bid-entry > div:first-child button");
        await teamButtons[0].trigger("click");

        // Select bid: 7 hearts
        const bidButtons = wrapper.findAll("table button");
        await bidButtons[8].trigger("click"); // 7_hearts
        await flushPromises();

        // Click Start Round
        const startButton = wrapper.findAll("button").find(b => b.text().includes("Start Round"));
        await startButton!.trigger("click");
        await flushPromises();

        const emitted = wrapper.emitted("save");
        expect(emitted).toBeTruthy();
        expect(emitted![0]).toHaveLength(1);
        const roundData = emitted![0][0] as Record<string, unknown>;
        expect(roundData.bidder_team).toBe("team_1");
        expect(roundData.bid_key).toBe("7_hearts");
        expect(roundData.bid_tricks).toBe(7);
        expect(roundData.bid_suit).toBe("hearts");
        // No tricks_won in bid-only save
        expect(roundData.tricks_won).toBeUndefined();
    });
});
