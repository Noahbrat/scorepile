<template>
    <div class="five-hundred-score-entry">
        <!-- Bid Summary Header -->
        <div class="mb-4 p-3 rounded-lg bg-surface-100 dark:bg-surface-800">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-sm text-muted-color">Bid:</span>
                    <span class="font-bold text-lg ml-2">{{ formattedBid }}</span>
                </div>
                <div>
                    <span class="text-sm text-muted-color">by</span>
                    <span class="font-medium ml-1">{{ bidderTeamLabel }}</span>
                </div>
            </div>
            <div class="text-sm text-muted-color mt-1">
                Worth {{ bidValue }} points
            </div>
        </div>

        <!-- Tricks Won -->
        <div v-if="!isMisereBid" class="mb-4">
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
        <div v-if="isMisereBid" class="mb-4">
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

        <!-- Save Score Button -->
        <Button
            label="Save Score"
            icon="pi pi-check"
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
import InputNumber from "primevue/inputnumber";
import Tag from "primevue/tag";
import type { GamePlayer, RoundData, ScoringConfig, CalculateRoundResult } from "@/types/api";

const props = defineProps<{
    gamePlayers: GamePlayer[];
    scoringConfig: ScoringConfig;
    gameConfig: Record<string, unknown>;
    roundData: RoundData;
    saving: boolean;
}>();

const emit = defineEmits<{
    save: [tricksWon: Record<string, number>, calculatedScores: CalculateRoundResult | null];
}>();

// ── Bid info from props ──────────────────────────────────────────

const suitSymbols: Record<string, string> = {
    spades: "\u2660",
    clubs: "\u2663",
    diamonds: "\u2666",
    hearts: "\u2665",
    no_trump: "NT",
};

const bidTable = computed(() => (props.scoringConfig.bid_table ?? {}) as Record<string, number>);
const bidValue = computed(() => bidTable.value[props.roundData.bid_key ?? ""] ?? 0);

const isMisereBid = computed(() =>
    props.roundData.bid_key === "misere" || props.roundData.bid_key === "open_misere"
);

const formattedBid = computed(() => {
    const key = props.roundData.bid_key;
    if (!key) return "—";
    if (key === "misere") return "Misère";
    if (key === "open_misere") return "Open Misère";
    const tricks = props.roundData.bid_tricks ?? "";
    const suit = props.roundData.bid_suit ?? "";
    return `${tricks}${suitSymbols[suit] ?? suit}`;
});

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

const bidderTeamLabel = computed(() => {
    const team = teams.value.find(t => t.key === props.roundData.bidder_team);
    return team?.label ?? props.roundData.bidder_team ?? "";
});

// ── State ────────────────────────────────────────────────────────

const bidderTricksWon = ref<number | null>(null);

// ── Score calculation (client-side preview) ──────────────────────

const previewScores = computed<CalculateRoundResult | null>(() => {
    if (bidderTricksWon.value === null || !props.roundData.bidder_team) {
        return null;
    }

    if (bidValue.value === 0) return null;

    const opponentTeam = teams.value.find(t => t.key !== props.roundData.bidder_team);
    if (!opponentTeam) return null;

    const opponentTricks = isMisereBid.value ? 0 : 10 - bidderTricksWon.value;
    let bidMade: boolean;
    const scores: Record<string, number> = {};

    if (isMisereBid.value) {
        bidMade = bidderTricksWon.value === 0;
        scores[props.roundData.bidder_team] = bidMade ? bidValue.value : -bidValue.value;
        scores[opponentTeam.key] = 0;
    } else {
        bidMade = bidderTricksWon.value >= (props.roundData.bid_tricks ?? 0);
        scores[props.roundData.bidder_team] = bidMade ? bidValue.value : -bidValue.value;
        scores[opponentTeam.key] = opponentTricks * 10;
    }

    return { scores, bid_made: bidMade, bid_value: bidValue.value };
});

// ── Validation ───────────────────────────────────────────────────

const isValid = computed(() => {
    return bidderTricksWon.value !== null && previewScores.value !== null;
});

// ── Save ─────────────────────────────────────────────────────────

function handleSave() {
    if (!isValid.value || !props.roundData.bidder_team) return;

    const opponentTeam = teams.value.find(t => t.key !== props.roundData.bidder_team);
    const opponentTricks = isMisereBid.value ? (10 - (bidderTricksWon.value ?? 0)) : (10 - (bidderTricksWon.value ?? 0));

    const tricksWon: Record<string, number> = {
        [props.roundData.bidder_team]: bidderTricksWon.value ?? 0,
        ...(opponentTeam ? { [opponentTeam.key]: opponentTricks } : {}),
    };

    emit("save", tricksWon, previewScores.value);
}
</script>
