import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import PrimeVue from "primevue/config";
import FiveHundredScoreEntry from "@/components/FiveHundredScoreEntry.vue";
import type { GamePlayer, ScoringConfig, RoundData } from "@/types/api";

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
    "7_hearts": 200,
    misere: 250,
    open_misere: 500,
};

const mockScoringConfig: ScoringConfig = {
    engine: "five_hundred",
    scoring_direction: "high_wins",
    track_dealer: false,
    teams: { enabled: true, size: 2 },
    bid_table: bidTable,
};

const normalBidRoundData: RoundData = {
    bidder_team: "team_1",
    bid_key: "7_hearts",
    bid_tricks: 7,
    bid_suit: "hearts",
};

const misereBidRoundData: RoundData = {
    bidder_team: "team_1",
    bid_key: "misere",
};

function mountComponent(overrides: Partial<{
    gamePlayers: GamePlayer[];
    scoringConfig: ScoringConfig;
    gameConfig: Record<string, unknown>;
    roundData: RoundData;
    saving: boolean;
}> = {}) {
    const pinia = createPinia();
    setActivePinia(pinia);

    return mount(FiveHundredScoreEntry, {
        props: {
            gamePlayers: overrides.gamePlayers ?? mockGamePlayers,
            scoringConfig: overrides.scoringConfig ?? mockScoringConfig,
            gameConfig: overrides.gameConfig ?? {},
            roundData: overrides.roundData ?? normalBidRoundData,
            saving: overrides.saving ?? false,
        },
        global: {
            plugins: [pinia, PrimeVue],
        },
    });
}

describe("FiveHundredScoreEntry", () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it("displays bid summary header", () => {
        const wrapper = mountComponent();

        expect(wrapper.text()).toContain("7\u2665"); // 7♥
        expect(wrapper.text()).toContain("Alice & Bob");
        expect(wrapper.text()).toContain("200 points");
    });

    it("shows tricks input for normal bids", () => {
        const wrapper = mountComponent();

        expect(wrapper.text()).toContain("Tricks Won by Bidding Team");
    });

    it("shows misère yes/no for misère bids", () => {
        const wrapper = mountComponent({ roundData: misereBidRoundData });

        expect(wrapper.text()).toContain("Did the bidder take any tricks?");
        expect(wrapper.text()).toContain("No tricks (success)");
        expect(wrapper.text()).toContain("Took tricks (failed)");
    });

    it("has Save Score button", () => {
        const wrapper = mountComponent();

        expect(wrapper.text()).toContain("Save Score");
        expect(wrapper.text()).not.toContain("Start Round");
        expect(wrapper.text()).not.toContain("Save Round");
    });

    it("defaults tricks won to bid amount and enables Save Score", () => {
        const wrapper = mountComponent();

        const saveButton = wrapper.findAll("button").find(b => b.text().includes("Save Score"));
        expect(saveButton).toBeTruthy();
        // With default tricks = bid_tricks (7), score preview is visible, so Save is enabled
        expect(saveButton!.attributes("disabled")).toBeUndefined();
    });

    it("displays misère bid summary correctly", () => {
        const wrapper = mountComponent({ roundData: misereBidRoundData });

        expect(wrapper.text()).toContain("Misère / Nullo");
        expect(wrapper.text()).toContain("250 points");
    });
});
