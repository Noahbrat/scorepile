<template>
    <div class="five-hundred-round-entry">
        <!-- Bidding Team -->
        <div class="mb-4">
            <label class="block text-sm font-medium mb-2">Bidding Team</label>
            <div class="flex gap-2">
                <Button
                    v-for="team in teams"
                    :key="team.key"
                    :label="team.label"
                    :severity="selectedBidderTeam === team.key ? 'primary' : 'secondary'"
                    :outlined="selectedBidderTeam !== team.key"
                    class="flex-1"
                    @click="selectedBidderTeam = team.key"
                />
            </div>
        </div>

        <!-- Bid Selection Grid -->
        <div class="mb-4">
            <label class="block text-sm font-medium mb-2">Bid</label>

            <!-- Avondale grid: rows = tricks (6-10), cols = suits -->
            <div class="overflow-x-auto -mx-2 px-2">
                <table class="w-full border-collapse text-center text-sm">
                    <thead>
                        <tr>
                            <th class="p-1 text-xs text-muted-color"></th>
                            <th v-for="suit in suits" :key="suit.key" class="p-1">
                                <span :class="suitColorClass(suit.key)">{{ suit.symbol }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="tricks in [6, 7, 8, 9, 10]" :key="tricks">
                            <td class="p-1 font-medium text-muted-color">{{ tricks }}</td>
                            <td v-for="suit in suits" :key="suit.key" class="p-0.5">
                                <button
                                    class="w-full py-1.5 px-1 rounded text-xs font-medium transition-colors"
                                    :class="bidButtonClass(`${tricks}_${suit.key}`)"
                                    @click="selectBid(tricks, suit.key, `${tricks}_${suit.key}`)"
                                >
                                    {{ bidTable[`${tricks}_${suit.key}`] }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Special bids -->
            <div class="flex gap-2 mt-2">
                <button
                    v-if="misereEnabled"
                    class="flex-1 py-2 px-3 rounded text-sm font-medium transition-colors"
                    :class="bidButtonClass('misere')"
                    @click="selectBid(null, null, 'misere')"
                >
                    Misère / Nullo (250)
                </button>
                <button
                    v-if="openMisereEnabled"
                    class="flex-1 py-2 px-3 rounded text-sm font-medium transition-colors"
                    :class="bidButtonClass('open_misere')"
                    @click="selectBid(null, null, 'open_misere')"
                >
                    Open Misère / Nullo (500)
                </button>
            </div>
        </div>

        <!-- Tricks Won -->
        <div v-if="selectedBidKey && !isMisereBid" class="mb-4">
            <label class="block text-sm font-medium mb-2">Tricks Won by Bidding Team</label>
            <div class="flex items-center gap-4">
                <InputNumber
                    v-model="bidderTricksWon"
                    :min="0"
                    :max="10"
                    showButtons
                    buttonLayout="horizontal"
                    :inputStyle="{ width: '4rem', textAlign: 'center' }"
                    decrementButtonClass="p-button-secondary"
                    incrementButtonClass="p-button-secondary"
                    incrementButtonIcon="pi pi-plus"
                    decrementButtonIcon="pi pi-minus"
                />
                <div class="text-sm text-muted-color">
                    Opponent: {{ 10 - (bidderTricksWon ?? 0) }} tricks
                </div>
            </div>
        </div>

        <!-- Misère tricks (0 or not) -->
        <div v-if="selectedBidKey && isMisereBid" class="mb-4">
            <label class="block text-sm font-medium mb-2">Did the bidder take any tricks?</label>
            <div class="flex gap-2">
                <Button
                    label="No tricks (success)"
                    :severity="bidderTricksWon === 0 ? 'success' : 'secondary'"
                    :outlined="bidderTricksWon !== 0"
                    class="flex-1"
                    @click="bidderTricksWon = 0"
                />
                <Button
                    label="Took tricks (failed)"
                    :severity="bidderTricksWon !== null && bidderTricksWon > 0 ? 'danger' : 'secondary'"
                    :outlined="bidderTricksWon === null || bidderTricksWon === 0"
                    class="flex-1"
                    @click="bidderTricksWon = 1"
                />
            </div>
        </div>

        <!-- Score Preview -->
        <div v-if="previewScores" class="mb-4 border border-surface-200 dark:border-surface-700 rounded-lg p-3">
            <h4 class="text-sm font-semibold mb-2">Score Preview</h4>
            <div class="space-y-1">
                <div
                    v-for="team in teams"
                    :key="team.key"
                    class="flex justify-between items-center"
                >
                    <span class="text-sm">{{ team.label }}</span>
                    <span
                        class="font-bold text-lg"
                        :class="(previewScores.scores[team.key] ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                    >
                        {{ (previewScores.scores[team.key] ?? 0) >= 0 ? '+' : '' }}{{ previewScores.scores[team.key] ?? 0 }}
                    </span>
                </div>
                <div class="pt-1 border-t border-surface-200 dark:border-surface-700">
                    <Tag
                        :value="previewScores.bid_made ? 'Bid Made' : 'Bid Failed'"
                        :severity="previewScores.bid_made ? 'success' : 'danger'"
                        class="text-xs"
                    />
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <Button
            label="Save Round"
            icon="pi pi-check"
            class="w-full"
            :loading="saving"
            :disabled="!isValid"
            @click="handleSave"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from "vue";
import Button from "primevue/button";
import InputNumber from "primevue/inputnumber";
import Tag from "primevue/tag";
import type { GamePlayer, RoundData, ScoringConfig, CalculateRoundResult } from "@/types/api";

const props = defineProps<{
    gamePlayers: GamePlayer[];
    scoringConfig: ScoringConfig;
    gameConfig: Record<string, unknown>;
    saving: boolean;
}>();

const emit = defineEmits<{
    save: [roundData: RoundData, calculatedScores: CalculateRoundResult | null];
}>();

// ── Bid table ────────────────────────────────────────────────────

const bidTable = computed(() => (props.scoringConfig.bid_table ?? {}) as Record<string, number>);

const suits = [
    { key: "spades", symbol: "\u2660", color: "text-gray-800 dark:text-gray-200" },
    { key: "clubs", symbol: "\u2663", color: "text-gray-800 dark:text-gray-200" },
    { key: "diamonds", symbol: "\u2666", color: "text-red-600 dark:text-red-400" },
    { key: "hearts", symbol: "\u2665", color: "text-red-600 dark:text-red-400" },
    { key: "no_trump", symbol: "NT", color: "text-blue-600 dark:text-blue-400" },
];

const misereEnabled = computed(() => (props.gameConfig.misere_enabled ?? props.scoringConfig.options?.find(o => o.key === "misere_enabled")?.default ?? false) as boolean);
const openMisereEnabled = computed(() => (props.gameConfig.open_misere_enabled ?? props.scoringConfig.options?.find(o => o.key === "open_misere_enabled")?.default ?? false) as boolean);

// ── Teams ────────────────────────────────────────────────────────

const teams = computed(() => {
    const teamMap: Record<number, GamePlayer[]> = {};
    for (const gp of props.gamePlayers) {
        const t = gp.team ?? 0;
        if (!teamMap[t]) teamMap[t] = [];
        teamMap[t].push(gp);
    }
    return Object.keys(teamMap).sort().map(t => ({
        key: `team_${t}`,
        number: Number(t),
        label: teamMap[Number(t)].map(gp => gp.player?.name ?? `Player ${gp.player_id}`).join(" & "),
        players: teamMap[Number(t)],
    }));
});

// ── State ────────────────────────────────────────────────────────

const selectedBidderTeam = ref<string | null>(null);
const selectedBidKey = ref<string | null>(null);
const selectedBidTricks = ref<number | null>(null);
const selectedBidSuit = ref<string | null>(null);
const bidderTricksWon = ref<number | null>(null);

const isMisereBid = computed(() =>
    selectedBidKey.value === "misere" || selectedBidKey.value === "open_misere"
);

// ── Helpers ──────────────────────────────────────────────────────

function suitColorClass(suit: string): string {
    if (suit === "diamonds" || suit === "hearts") return "text-red-600 dark:text-red-400";
    if (suit === "no_trump") return "text-blue-600 dark:text-blue-400";
    return "";
}

function bidButtonClass(bidKey: string): string {
    if (selectedBidKey.value === bidKey) {
        return "bg-primary text-primary-contrast";
    }
    return "bg-surface-100 dark:bg-surface-700 hover:bg-surface-200 dark:hover:bg-surface-600 text-surface-700 dark:text-surface-200";
}

function selectBid(tricks: number | null, suit: string | null, bidKey: string) {
    selectedBidKey.value = bidKey;
    selectedBidTricks.value = tricks;
    selectedBidSuit.value = suit;
    // Reset tricks when changing bid
    bidderTricksWon.value = null;
}

// ── Score calculation (client-side preview) ──────────────────────

const previewScores = computed<CalculateRoundResult | null>(() => {
    if (!selectedBidKey.value || !selectedBidderTeam.value || bidderTricksWon.value === null) {
        return null;
    }

    const bidValue = bidTable.value[selectedBidKey.value] ?? 0;
    if (bidValue === 0) return null;

    const opponentTeam = teams.value.find(t => t.key !== selectedBidderTeam.value);
    if (!opponentTeam) return null;

    const opponentTricks = isMisereBid.value ? 0 : 10 - bidderTricksWon.value;
    let bidMade: boolean;
    const scores: Record<string, number> = {};

    if (isMisereBid.value) {
        bidMade = bidderTricksWon.value === 0;
        scores[selectedBidderTeam.value] = bidMade ? bidValue : -bidValue;
        scores[opponentTeam.key] = 0;
    } else {
        bidMade = bidderTricksWon.value >= (selectedBidTricks.value ?? 0);
        scores[selectedBidderTeam.value] = bidMade ? bidValue : -bidValue;
        scores[opponentTeam.key] = opponentTricks * 10;
    }

    return { scores, bid_made: bidMade, bid_value: bidValue };
});

// ── Validation ───────────────────────────────────────────────────

const isValid = computed(() => {
    return (
        selectedBidderTeam.value !== null &&
        selectedBidKey.value !== null &&
        bidderTricksWon.value !== null &&
        previewScores.value !== null
    );
});

// ── Save ─────────────────────────────────────────────────────────

function handleSave() {
    if (!isValid.value || !selectedBidderTeam.value || !selectedBidKey.value) return;

    const opponentTeam = teams.value.find(t => t.key !== selectedBidderTeam.value);
    const opponentTricks = isMisereBid.value ? (10 - (bidderTricksWon.value ?? 0)) : (10 - (bidderTricksWon.value ?? 0));

    const roundData: RoundData = {
        bidder_team: selectedBidderTeam.value,
        bid_tricks: selectedBidTricks.value ?? undefined,
        bid_suit: selectedBidSuit.value ?? undefined,
        bid_key: selectedBidKey.value,
        tricks_won: {
            [selectedBidderTeam.value]: bidderTricksWon.value ?? 0,
            ...(opponentTeam ? { [opponentTeam.key]: opponentTricks } : {}),
        },
        bid_made: previewScores.value?.bid_made,
    };

    emit("save", roundData, previewScores.value);
}

// Reset when bid changes
watch(selectedBidKey, () => {
    bidderTricksWon.value = null;
});
</script>

<style scoped>
.five-hundred-round-entry table {
    border-spacing: 2px;
}
</style>
