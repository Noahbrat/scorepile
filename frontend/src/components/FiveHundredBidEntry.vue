<template>
    <div class="five-hundred-bid-entry">
        <!-- Bidding Player -->
        <div class="mb-5">
            <label class="block text-sm font-medium mb-2">Who won the bid?</label>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="gp in gamePlayers"
                    :key="gp.id"
                    :label="gp.player?.name ?? `Player ${gp.player_id}`"
                    :severity="selectedBidderId === gp.id ? 'primary' : 'secondary'"
                    :outlined="selectedBidderId !== gp.id"
                    class="flex-1 !min-h-[44px] !text-base"
                    @click="selectBidder(gp)"
                />
            </div>
            <div v-if="selectedBidderTeam" class="text-xs text-muted-color mt-1">
                Team: {{ teams.find(t => t.key === selectedBidderTeam)?.label ?? selectedBidderTeam }}
            </div>
        </div>

        <!-- Bid Selection Grid -->
        <div class="mb-5">
            <label class="block text-sm font-medium mb-2">Bid</label>

            <!-- Avondale grid: rows = tricks (6-10), cols = suits -->
            <div class="bid-grid">
                <table class="w-full border-collapse text-center">
                    <thead>
                        <tr>
                            <th class="p-1 text-sm text-muted-color"></th>
                            <th v-for="suit in suits" :key="suit.key" class="p-1">
                                <span class="text-lg" :class="suitColorClass(suit.key)">{{ suit.symbol }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="tricks in trickRange" :key="tricks">
                            <td class="p-1 font-semibold text-muted-color text-sm">{{ tricks }}</td>
                            <td v-for="suit in suits" :key="suit.key" class="p-1">
                                <button
                                    class="bid-cell w-full rounded-lg text-sm font-semibold transition-colors touch-manipulation"
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
            <div class="flex gap-2 mt-3">
                <button
                    v-if="misereEnabled"
                    class="flex-1 min-h-[48px] px-3 rounded-lg text-sm font-semibold transition-colors touch-manipulation"
                    :class="bidButtonClass('misere')"
                    @click="selectBid(null, null, 'misere')"
                >
                    Misère / Nullo (250)
                </button>
                <button
                    v-if="openMisereEnabled"
                    class="flex-1 min-h-[48px] px-3 rounded-lg text-sm font-semibold transition-colors touch-manipulation"
                    :class="bidButtonClass('open_misere')"
                    @click="selectBid(null, null, 'open_misere')"
                >
                    Open Misère / Nullo (500)
                </button>
            </div>
        </div>

        <!-- Start Round Button -->
        <Button
            label="Start Round"
            icon="pi pi-play"
            class="w-full"
            :loading="saving"
            :disabled="!isValid"
            @click="handleSave"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from "vue";
import Button from "primevue/button";
import type { GamePlayer, RoundData, ScoringConfig } from "@/types/api";

const props = defineProps<{
    gamePlayers: GamePlayer[];
    scoringConfig: ScoringConfig;
    gameConfig: Record<string, unknown>;
    saving: boolean;
}>();

const emit = defineEmits<{
    save: [roundData: RoundData];
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

const minBid = computed(() => Number(props.gameConfig.min_bid ?? props.scoringConfig.options?.find(o => o.key === "min_bid")?.default ?? 6));
const trickRange = computed(() => {
    const range: number[] = [];
    for (let i = minBid.value; i <= 10; i++) range.push(i);
    return range;
});

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

const selectedBidderId = ref<number | null>(null);
const selectedBidderTeam = ref<string | null>(null);
const selectedBidKey = ref<string | null>(null);
const selectedBidTricks = ref<number | null>(null);
const selectedBidSuit = ref<string | null>(null);

function selectBidder(gp: GamePlayer) {
    selectedBidderId.value = gp.id;
    const teamNum = gp.team ?? 0;
    selectedBidderTeam.value = `team_${teamNum}`;
}

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
}

// ── Validation ───────────────────────────────────────────────────

const isValid = computed(() => {
    return selectedBidderTeam.value !== null && selectedBidKey.value !== null;
});

// ── Save ─────────────────────────────────────────────────────────

function handleSave() {
    if (!isValid.value || !selectedBidderTeam.value || !selectedBidKey.value) return;

    const roundData: RoundData = {
        bidder_team: selectedBidderTeam.value,
        bidder_game_player_id: selectedBidderId.value ?? undefined,
        bid_tricks: selectedBidTricks.value ?? undefined,
        bid_suit: selectedBidSuit.value ?? undefined,
        bid_key: selectedBidKey.value,
    };

    emit("save", roundData);
}
</script>

<style scoped>
.five-hundred-bid-entry table {
    border-spacing: 4px;
}

/* Touch-friendly bid grid cells — min 44px tap targets */
.bid-cell {
    min-height: 44px;
    min-width: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Slightly bigger on mobile for easier tapping */
@media (max-width: 640px) {
    .bid-cell {
        min-height: 48px;
    }
}
</style>
