<template>
    <div>
        <Toast />

        <!-- Loading State -->
        <div v-if="loading && !game" class="text-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-muted-color"></i>
        </div>

        <!-- Game Content -->
        <template v-else-if="game">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Button
                            icon="pi pi-arrow-left"
                            severity="secondary"
                            text
                            rounded
                            size="small"
                            @click="router.push('/games')"
                        />
                        <h1 class="text-2xl sm:text-3xl font-bold">{{ game.name }}</h1>
                        <Tag
                            :value="game.status"
                            :severity="statusSeverity(game.status)"
                        />
                    </div>
                    <p v-if="game.game_type" class="text-muted-color text-sm ml-10">
                        {{ game.game_type.name }}
                        <span v-if="game.game_type.scoring_direction === 'low_wins'"> (Low Wins)</span>
                    </p>
                </div>
                <div v-if="game.status === 'active'" class="flex gap-2">
                    <Button
                        v-if="!hasEngine"
                        label="Add Round"
                        icon="pi pi-plus"
                        severity="info"
                        @click="addRound"
                        :loading="addingRound"
                    />
                    <Button
                        v-if="hasEngine"
                        label="New Round"
                        icon="pi pi-plus"
                        severity="info"
                        @click="openNewRoundDialog"
                    />
                    <Button
                        label="Complete"
                        icon="pi pi-check"
                        severity="success"
                        @click="confirmComplete"
                    />
                </div>
            </div>

            <!-- Completed Rankings -->
            <div v-if="game.status === 'completed' && rankedPlayers.length" class="mb-6">
                <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-4">
                    <h3 class="font-semibold mb-3">Final Rankings</h3>
                    <div class="space-y-2">
                        <div
                            v-for="gp in rankedPlayers"
                            :key="gp.id"
                            class="flex items-center gap-3 p-2 rounded"
                            :class="gp.is_winner ? 'bg-yellow-50 dark:bg-yellow-900/20' : ''"
                        >
                            <span class="text-lg font-bold w-8 text-center">
                                {{ gp.is_winner ? '🏆' : `#${gp.final_rank}` }}
                            </span>
                            <span class="flex-1 font-medium">{{ gp.player?.name }}</span>
                            <span class="font-bold text-lg">{{ gp.total_score }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Assignment (for team games with unassigned players or editing) -->
            <div v-if="hasEngine && isTeamGame && (needsTeamSetup || editingTeams)" class="mb-6">
                <div class="border border-surface-200 dark:border-surface-700 rounded-lg p-6">
                    <h3 class="text-lg font-semibold mb-2">Set Up Teams</h3>
                    <p class="text-muted-color text-sm mb-4">
                        Assign players to teams before starting. Drag or tap to move players between teams.
                    </p>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div
                            v-for="teamNum in teamCount"
                            :key="teamNum"
                            class="border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-lg p-3 min-h-32"
                            :class="{ 'border-primary': true }"
                        >
                            <div class="text-sm font-semibold mb-2 text-center">Team {{ teamNum }}</div>
                            <div class="space-y-2">
                                <div
                                    v-for="gp in gamePlayers.filter(p => teamDraft[p.player_id] === teamNum)"
                                    :key="gp.id"
                                    class="flex items-center gap-2 p-2 rounded bg-surface-100 dark:bg-surface-800"
                                >
                                    <div
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0"
                                        :style="{ background: gp.player?.color || 'var(--p-primary-color)' }"
                                    >
                                        {{ gp.player?.avatar_emoji || gp.player?.name?.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="flex-1 font-medium text-sm">{{ gp.player?.name }}</span>
                                    <Button
                                        icon="pi pi-arrow-right-arrow-left"
                                        severity="secondary"
                                        text
                                        rounded
                                        size="small"
                                        @click="cycleTeam(gp.player_id)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button
                            v-if="editingTeams"
                            label="Cancel"
                            severity="secondary"
                            text
                            @click="editingTeams = false"
                        />
                        <Button
                            label="Save Teams"
                            icon="pi pi-check"
                            :loading="savingTeams"
                            :disabled="!teamsValid"
                            @click="saveTeams"
                        />
                    </div>
                    <p v-if="!teamsValid" class="text-red-500 text-xs mt-2 text-right">
                        Each team needs {{ scoringConfig?.teams?.size ?? 2 }} players
                    </p>
                </div>
            </div>

            <!-- Engine Score Table (team-based, e.g., 500) -->
            <template v-if="hasEngine && isTeamGame && !needsTeamSetup && !editingTeams">
                <!-- Team totals -->
                <div class="flex gap-4 mb-4">
                    <div
                        v-for="team in teamInfo"
                        :key="team.number"
                        class="flex-1 border border-surface-200 dark:border-surface-700 rounded-lg p-3 text-center relative"
                    >
                        <div class="text-sm text-muted-color mb-1">{{ team.label }}</div>
                        <div class="text-2xl font-bold" :class="team.total >= 0 ? '' : 'text-red-600 dark:text-red-400'">
                            {{ team.total }}
                        </div>
                    </div>
                    <Button
                        v-if="game?.status === 'active'"
                        icon="pi pi-pencil"
                        severity="secondary"
                        text
                        rounded
                        size="small"
                        class="self-center"
                        v-tooltip.top="'Edit teams'"
                        @click="startEditingTeams"
                    />
                </div>

                <!-- Round history table -->
                <div class="overflow-x-auto -mx-4 sm:mx-0">
                    <table class="w-full border-collapse min-w-0">
                        <thead>
                            <tr class="border-b-2 border-surface-300 dark:border-surface-600">
                                <th class="p-2 text-left text-sm font-semibold min-w-12">#</th>
                                <th class="p-2 text-left text-sm font-semibold">Bid</th>
                                <th class="p-2 text-center text-sm font-semibold">Result</th>
                                <th
                                    v-for="team in teamInfo"
                                    :key="team.number"
                                    class="p-2 text-center text-sm font-semibold min-w-20"
                                >
                                    {{ team.shortLabel }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="round in rounds"
                                :key="round.id"
                                class="border-b border-surface-200 dark:border-surface-700"
                            >
                                <td class="p-2 text-sm text-muted-color">
                                    {{ round.round_number }}
                                    <span
                                        v-if="trackDealer && getDealerName(round.dealer_game_player_id)"
                                        class="block text-xs"
                                        :title="`Dealer: ${getDealerName(round.dealer_game_player_id)}`"
                                    >🃏 {{ getDealerName(round.dealer_game_player_id) }}</span>
                                </td>
                                <td class="p-2 text-sm">
                                    <template v-if="round.round_data?.bid_key">
                                        <span class="font-medium">{{ formatBid(round.round_data) }}</span>
                                        <span class="text-muted-color text-xs ml-1">
                                            ({{ bidderTeamLabel(round.round_data) }})
                                        </span>
                                    </template>
                                    <span v-else class="text-muted-color">—</span>
                                </td>
                                <td class="p-2 text-center text-sm">
                                    <Tag
                                        v-if="round.round_data?.bid_made !== undefined"
                                        :value="round.round_data.bid_made ? 'Made' : 'Failed'"
                                        :severity="round.round_data.bid_made ? 'success' : 'danger'"
                                        class="text-xs"
                                    />
                                </td>
                                <td
                                    v-for="team in teamInfo"
                                    :key="team.number"
                                    class="p-2 text-center text-sm font-medium"
                                >
                                    <span :class="getRoundTeamScore(round, team.number) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                        {{ getRoundTeamScore(round, team.number) >= 0 ? '+' : '' }}{{ getRoundTeamScore(round, team.number) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-surface-300 dark:border-surface-600">
                                <td colspan="3" class="p-2 font-bold text-sm">Total</td>
                                <td
                                    v-for="team in teamInfo"
                                    :key="team.number"
                                    class="p-2 text-center font-bold text-lg"
                                >
                                    {{ team.total }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </template>

            <!-- Simple Score Table (no engine) -->
            <template v-else-if="!needsTeamSetup && !editingTeams">
                <div class="overflow-x-auto -mx-4 sm:mx-0">
                    <table class="w-full border-collapse min-w-0">
                        <thead>
                            <tr class="border-b-2 border-surface-300 dark:border-surface-600">
                                <th class="p-2 text-left text-sm font-semibold sticky left-0 bg-surface-0 dark:bg-surface-900 z-10 min-w-16">
                                    Round
                                </th>
                                <th
                                    v-for="gp in gamePlayers"
                                    :key="gp.id"
                                    class="p-2 text-center text-sm font-semibold min-w-20"
                                >
                                    <div
                                        class="inline-flex items-center gap-1"
                                        :class="gp.is_winner ? 'text-yellow-600 dark:text-yellow-400' : ''"
                                    >
                                        {{ gp.player?.name }}
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="round in rounds"
                                :key="round.id"
                                class="border-b border-surface-200 dark:border-surface-700"
                            >
                                <td class="p-2 text-sm text-muted-color sticky left-0 bg-surface-0 dark:bg-surface-900 z-10">
                                    {{ round.round_number }}
                                </td>
                                <td
                                    v-for="gp in gamePlayers"
                                    :key="gp.id"
                                    class="p-1 text-center"
                                >
                                    <InputNumber
                                        v-if="game.status === 'active'"
                                        :modelValue="getScore(round.id, gp.id)"
                                        @update:modelValue="(val: number | null) => onScoreChange(round.id, gp.id, val)"
                                        @blur="saveScore(round.id, gp.id)"
                                        @keyup.enter="($event.target as HTMLElement)?.blur()"
                                        :input-style="{ width: '5rem', textAlign: 'center' }"
                                        class="score-input"
                                        :useGrouping="false"
                                    />
                                    <span v-else class="text-sm">
                                        {{ getScore(round.id, gp.id) ?? '—' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-surface-300 dark:border-surface-600">
                                <td class="p-2 font-bold text-sm sticky left-0 bg-surface-0 dark:bg-surface-900 z-10">
                                    Total
                                </td>
                                <td
                                    v-for="gp in gamePlayers"
                                    :key="gp.id"
                                    class="p-2 text-center font-bold text-lg"
                                >
                                    {{ playerTotals[gp.id] ?? 0 }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </template>

            <div v-if="rounds.length === 0 && game.status === 'active'" class="text-center py-8 text-muted-color">
                <i class="pi pi-table text-4xl mb-4"></i>
                <p>No rounds yet. Click "{{ hasEngine ? 'New Round' : 'Add Round' }}" to start scoring.</p>
            </div>
        </template>

        <!-- Error State -->
        <div v-else class="text-center py-12">
            <i class="pi pi-exclamation-circle text-4xl text-red-500 mb-4"></i>
            <p class="text-muted-color">{{ error || "Game not found" }}</p>
            <Button label="Back to Games" link @click="router.push('/games')" class="mt-2" />
        </div>

        <!-- Complete Confirmation Dialog -->
        <Dialog
            v-model:visible="completeDialogVisible"
            header="Complete Game"
            :modal="true"
            :style="{ width: '400px' }"
        >
            <p>Mark this game as complete? Final rankings will be calculated.</p>

            <template #footer>
                <Button label="Cancel" severity="secondary" text @click="completeDialogVisible = false" />
                <Button
                    label="Complete Game"
                    severity="success"
                    icon="pi pi-check"
                    :loading="completing"
                    @click="handleComplete"
                />
            </template>
        </Dialog>

        <!-- New Round Dialog (for engine-based games) -->
        <Dialog
            v-model:visible="newRoundDialogVisible"
            header="New Round"
            :modal="true"
            :style="{ width: '500px' }"
        >
            <!-- Dealer selector -->
            <div v-if="trackDealer" class="mb-4">
                <label class="block text-sm font-medium mb-2">Dealer</label>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="gp in gamePlayers"
                        :key="gp.id"
                        :label="gp.player?.name ?? 'Player'"
                        :severity="currentDealerId === gp.id ? 'primary' : 'secondary'"
                        :outlined="currentDealerId !== gp.id"
                        size="small"
                        @click="currentDealerId = gp.id"
                    />
                </div>
            </div>

            <FiveHundredRoundEntry
                v-if="scoringConfig?.engine === 'five_hundred'"
                :gamePlayers="gamePlayers"
                :scoringConfig="scoringConfig"
                :gameConfig="effectiveGameConfig"
                :saving="savingRound"
                @save="handleSaveEngineRound"
            />
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useToast } from "primevue/usetoast";
import Toast from "primevue/toast";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import InputNumber from "primevue/inputnumber";
import Tag from "primevue/tag";
import FiveHundredRoundEntry from "@/components/FiveHundredRoundEntry.vue";
import { gamesApi, roundsApi, scoresApi } from "@/services/api";
import type { Game, GamePlayer, Round, RoundData, ScoringConfig, CalculateRoundResult } from "@/types/api";

const route = useRoute();
const router = useRouter();
const toast = useToast();

// ── State ────────────────────────────────────────────────────────

const game = ref<Game | null>(null);
const rounds = ref<Round[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const addingRound = ref(false);
const completing = ref(false);
const completeDialogVisible = ref(false);
const newRoundDialogVisible = ref(false);
const savingRound = ref(false);
const savingTeams = ref(false);
const editingTeams = ref(false);
const teamDraft = reactive<Record<number, number>>({});
const currentDealerId = ref<number | null>(null);

// Local score edits: key = `${roundId}-${gamePlayerId}`, value = points
const scoreEdits = reactive<Record<string, number | null>>({});
// Existing score IDs: key = `${roundId}-${gamePlayerId}`, value = score id
const scoreIds = reactive<Record<string, number>>({});

// ── Computed ─────────────────────────────────────────────────────

const gamePlayers = computed<GamePlayer[]>(() => game.value?.game_players ?? []);

const scoringConfig = computed<ScoringConfig | null>(() =>
    game.value?.game_type?.scoring_config ?? null
);

const hasEngine = computed(() => scoringConfig.value !== null && scoringConfig.value.engine !== "simple");

const isTeamGame = computed(() => scoringConfig.value?.teams?.enabled ?? false);

const teamCount = computed(() => {
    if (!isTeamGame.value || !scoringConfig.value?.teams) return 0;
    const teamSize = scoringConfig.value.teams.size ?? 2;
    return Math.ceil(gamePlayers.value.length / teamSize);
});

const needsTeamSetup = computed(() => {
    if (!isTeamGame.value) return false;
    return gamePlayers.value.some(gp => gp.team === null || gp.team === undefined);
});

const teamsValid = computed(() => {
    if (!isTeamGame.value || !scoringConfig.value?.teams) return false;
    const teamSize = scoringConfig.value.teams.size ?? 2;
    const counts: Record<number, number> = {};
    for (const gp of gamePlayers.value) {
        const t = teamDraft[gp.player_id] ?? 0;
        counts[t] = (counts[t] ?? 0) + 1;
    }
    // Every team must have exactly teamSize players
    for (let i = 1; i <= teamCount.value; i++) {
        if ((counts[i] ?? 0) !== teamSize) return false;
    }
    return true;
});

function initTeamDraft() {
    // If teams are already assigned, use them; otherwise auto-distribute
    for (let i = 0; i < gamePlayers.value.length; i++) {
        const gp = gamePlayers.value[i];
        if (gp.team !== null && gp.team !== undefined && gp.team > 0) {
            teamDraft[gp.player_id] = gp.team;
        } else {
            // Auto-distribute: alternate between teams
            teamDraft[gp.player_id] = (i % teamCount.value) + 1;
        }
    }
}

function startEditingTeams() {
    initTeamDraft();
    editingTeams.value = true;
}

function cycleTeam(playerId: number) {
    const current = teamDraft[playerId] ?? 1;
    teamDraft[playerId] = (current % teamCount.value) + 1;
}

async function saveTeams() {
    if (!game.value || !teamsValid.value) return;
    savingTeams.value = true;

    try {
        const teams: Record<string, number> = {};
        for (const gp of gamePlayers.value) {
            teams[String(gp.player_id)] = teamDraft[gp.player_id];
        }

        const response = await gamesApi.assignTeams(game.value.id, teams);
        game.value = response.data.data;
        editingTeams.value = false;
        toast.add({ severity: "success", summary: "Teams Saved", detail: "Team assignments updated", life: 3000 });
    } catch {
        toast.add({ severity: "error", summary: "Error", detail: "Failed to save team assignments", life: 5000 });
    } finally {
        savingTeams.value = false;
    }
}

const effectiveGameConfig = computed<Record<string, unknown>>(() => {
    const base = (scoringConfig.value ?? {}) as Record<string, unknown>;
    return { ...base, ...(game.value?.game_config ?? {}) };
});

const rankedPlayers = computed<GamePlayer[]>(() => {
    const players = [...gamePlayers.value];
    players.sort((a, b) => (a.final_rank ?? 999) - (b.final_rank ?? 999));
    return players;
});

const playerTotals = computed<Record<number, number>>(() => {
    const totals: Record<number, number> = {};
    for (const gp of gamePlayers.value) {
        let sum = 0;
        for (const round of rounds.value) {
            const key = `${round.id}-${gp.id}`;
            const val = scoreEdits[key];
            if (val != null) {
                sum += val;
            }
        }
        totals[gp.id] = sum;
    }
    return totals;
});

// ── Team helpers ─────────────────────────────────────────────────

interface TeamSummary {
    number: number;
    key: string;
    label: string;
    shortLabel: string;
    players: GamePlayer[];
    total: number;
}

const teamInfo = computed<TeamSummary[]>(() => {
    const teamMap: Record<number, GamePlayer[]> = {};
    for (const gp of gamePlayers.value) {
        const t = gp.team ?? 0;
        if (!teamMap[t]) teamMap[t] = [];
        teamMap[t].push(gp);
    }

    return Object.keys(teamMap).sort().map(t => {
        const num = Number(t);
        const players = teamMap[num];
        const label = players.map(gp => gp.player?.name ?? `Player ${gp.player_id}`).join(" & ");
        // Calculate total from playerTotals
        const total = players.reduce((sum, gp) => sum + (playerTotals.value[gp.id] ?? 0), 0);
        // Since team members share same score, use first player's total (avoid double counting)
        const teamTotal = playerTotals.value[players[0]?.id] ?? 0;

        return {
            number: num,
            key: `team_${num}`,
            label,
            shortLabel: `Team ${num}`,
            players,
            total: teamTotal,
        };
    });
});

function getRoundTeamScore(round: Round, teamNumber: number): number {
    const teamPlayers = gamePlayers.value.filter(gp => gp.team === teamNumber);
    if (teamPlayers.length === 0) return 0;
    // All team members share the same score, use first
    const key = `${round.id}-${teamPlayers[0].id}`;
    return scoreEdits[key] ?? 0;
}

// ── Dealer tracking ──────────────────────────────────────────────

const trackDealer = computed(() => scoringConfig.value?.track_dealer ?? false);

const dealerPlayer = computed(() => {
    if (!currentDealerId.value) return null;
    return gamePlayers.value.find(gp => gp.id === currentDealerId.value) ?? null;
});

/**
 * Determine dealer for the next round:
 * - If there are existing rounds with a dealer, advance from the last one
 * - Otherwise, default to the first player (user can change)
 */
function initDealer() {
    if (!trackDealer.value || gamePlayers.value.length === 0) return;

    // Check last round for dealer
    const lastRound = rounds.value.length > 0
        ? rounds.value[rounds.value.length - 1]
        : null;

    if (lastRound?.dealer_game_player_id) {
        // Advance to next player
        currentDealerId.value = getNextDealer(lastRound.dealer_game_player_id);
    } else {
        // Default to first player
        currentDealerId.value = gamePlayers.value[0]?.id ?? null;
    }
}

function getNextDealer(currentId: number): number {
    const idx = gamePlayers.value.findIndex(gp => gp.id === currentId);
    const nextIdx = (idx + 1) % gamePlayers.value.length;
    return gamePlayers.value[nextIdx].id;
}

function getDealerName(dealerGamePlayerId: number | null | undefined): string | null {
    if (!dealerGamePlayerId) return null;
    const gp = gamePlayers.value.find(p => p.id === dealerGamePlayerId);
    return gp?.player?.name ?? null;
}

// ── Bid formatting helpers ───────────────────────────────────────

const suitSymbols: Record<string, string> = {
    spades: "\u2660",
    clubs: "\u2663",
    diamonds: "\u2666",
    hearts: "\u2665",
    no_trump: "NT",
};

function formatBid(roundData: RoundData): string {
    if (!roundData.bid_key) return "—";
    if (roundData.bid_key === "misere") return "Misère";
    if (roundData.bid_key === "open_misere") return "Open Misère";
    const tricks = roundData.bid_tricks ?? "";
    const suit = roundData.bid_suit ?? "";
    return `${tricks}${suitSymbols[suit] ?? suit}`;
}

function bidderTeamLabel(roundData: RoundData): string {
    if (!roundData.bidder_team) return "";
    const team = teamInfo.value.find(t => t.key === roundData.bidder_team);
    return team?.shortLabel ?? roundData.bidder_team as string;
}

// ── Helpers ──────────────────────────────────────────────────────

function statusSeverity(status: string): "success" | "info" | "warn" | "danger" | undefined {
    switch (status) {
        case "active": return "success";
        case "completed": return "info";
        case "abandoned": return "warn";
        default: return undefined;
    }
}

function getScore(roundId: number, gamePlayerId: number): number | null {
    const key = `${roundId}-${gamePlayerId}`;
    return scoreEdits[key] ?? null;
}

function onScoreChange(roundId: number, gamePlayerId: number, value: number | null) {
    const key = `${roundId}-${gamePlayerId}`;
    scoreEdits[key] = value;
}

function buildScoreMap(roundsList: Round[]) {
    for (const round of roundsList) {
        if (round.scores) {
            for (const score of round.scores) {
                const key = `${round.id}-${score.game_player_id}`;
                scoreEdits[key] = Number(score.points);
                scoreIds[key] = score.id;
            }
        }
    }
}

// ── API Actions ──────────────────────────────────────────────────

async function loadGame() {
    loading.value = true;
    error.value = null;

    try {
        const gameId = Number(route.params.id);
        const [gameRes, roundsRes] = await Promise.all([
            gamesApi.getById(gameId),
            roundsApi.getAll(gameId),
        ]);

        game.value = gameRes.data.data;
        rounds.value = roundsRes.data.data;
        buildScoreMap(rounds.value);

        // Initialize team draft if this is a team game
        if (isTeamGame.value) {
            initTeamDraft();
        }

        // Initialize dealer tracking
        if (trackDealer.value) {
            initDealer();
        }
    } catch (err: unknown) {
        if (err && typeof err === "object" && "response" in err) {
            const axiosError = err as { response?: { data?: { message?: string } } };
            error.value = axiosError.response?.data?.message || "Failed to load game";
        } else {
            error.value = "Failed to load game";
        }
    } finally {
        loading.value = false;
    }
}

async function addRound() {
    if (!game.value) return;
    addingRound.value = true;

    try {
        const response = await roundsApi.create({ game_id: game.value.id });
        const newRound = response.data.data;
        rounds.value.push(newRound);
        toast.add({ severity: "success", summary: "Round Added", detail: `Round ${newRound.round_number}`, life: 2000 });
    } catch {
        toast.add({ severity: "error", summary: "Error", detail: "Failed to add round", life: 5000 });
    } finally {
        addingRound.value = false;
    }
}

function openNewRoundDialog() {
    newRoundDialogVisible.value = true;
}

async function handleSaveEngineRound(roundData: RoundData, _calculatedScores: CalculateRoundResult | null) {
    if (!game.value) return;
    savingRound.value = true;

    try {
        const dealerId = trackDealer.value ? currentDealerId.value ?? undefined : undefined;
        const response = await gamesApi.saveRound(game.value.id, roundData, dealerId);
        const newRound = response.data.data;
        rounds.value.push(newRound);
        buildScoreMap([newRound]);
        newRoundDialogVisible.value = false;

        // Advance dealer for next round
        if (trackDealer.value && currentDealerId.value) {
            currentDealerId.value = getNextDealer(currentDealerId.value);
        }

        // Reload game to get updated totals
        const gameRes = await gamesApi.getById(game.value.id);
        game.value = gameRes.data.data;

        toast.add({ severity: "success", summary: "Round Saved", detail: `Round ${newRound.round_number}`, life: 2000 });
    } catch (err: unknown) {
        let msg = "Failed to save round";
        if (err && typeof err === "object" && "response" in err) {
            const axiosError = err as { response?: { data?: { message?: string } } };
            msg = axiosError.response?.data?.message || msg;
        }
        toast.add({ severity: "error", summary: "Error", detail: msg, life: 5000 });
    } finally {
        savingRound.value = false;
    }
}

async function saveScore(roundId: number, gamePlayerId: number) {
    const key = `${roundId}-${gamePlayerId}`;
    const points = scoreEdits[key];

    if (points == null) return;

    try {
        const existingId = scoreIds[key];
        if (existingId) {
            await scoresApi.update(existingId, { points });
        } else {
            const response = await scoresApi.create({
                round_id: roundId,
                game_player_id: gamePlayerId,
                points,
            });
            scoreIds[key] = response.data.data.id;
        }
    } catch {
        toast.add({ severity: "error", summary: "Error", detail: "Failed to save score", life: 3000 });
    }
}

function confirmComplete() {
    completeDialogVisible.value = true;
}

async function handleComplete() {
    if (!game.value) return;
    completing.value = true;

    try {
        const response = await gamesApi.complete(game.value.id);
        game.value = response.data.data;
        completeDialogVisible.value = false;
        toast.add({ severity: "success", summary: "Game Complete!", detail: "Final rankings calculated", life: 3000 });
    } catch (err: unknown) {
        let msg = "Failed to complete game";
        if (err && typeof err === "object" && "response" in err) {
            const axiosError = err as { response?: { data?: { message?: string } } };
            msg = axiosError.response?.data?.message || msg;
        }
        toast.add({ severity: "error", summary: "Error", detail: msg, life: 5000 });
    } finally {
        completing.value = false;
    }
}

// ── Init ────────────────────────────────────────────────────────

onMounted(() => {
    loadGame();
});
</script>

<style scoped>
.score-input :deep(.p-inputnumber-input) {
    padding: 0.375rem 0.25rem;
    font-size: 0.875rem;
}

table {
    table-layout: auto;
}
</style>
