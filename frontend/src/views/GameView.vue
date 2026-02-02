<template>
    <div>
        <Toast position="bottom-right" />

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
                    <template v-if="hasEngine && !activeRound">
                        <Button
                            label="New Round"
                            icon="pi pi-plus"
                            severity="info"
                            @click="openBidDialog"
                        />
                    </template>
                    <template v-if="hasEngine && activeRound">
                        <Button
                            label="Enter Score"
                            icon="pi pi-pencil"
                            severity="warn"
                            @click="openScoreDialog"
                        />
                    </template>
                    <Button
                        label="Complete"
                        icon="pi pi-check"
                        severity="success"
                        @click="confirmComplete"
                    />
                </div>
            </div>

            <!-- Active Round Banner -->
            <div
                v-if="hasEngine && activeRound && game.status === 'active'"
                class="mb-4 p-3 rounded-lg border-2 border-yellow-400 dark:border-yellow-600 bg-yellow-50 dark:bg-yellow-900/20 flex items-center justify-between"
            >
                <div class="flex items-center gap-3">
                    <i class="pi pi-play-circle text-yellow-600 dark:text-yellow-400 text-xl"></i>
                    <div>
                        <span class="font-semibold">Round {{ activeRound.round_number }}</span>
                        <span class="text-sm text-muted-color ml-2">
                            {{ formatBid(activeRound.round_data ?? {}) }}
                            ({{ bidderTeamLabel(activeRound.round_data ?? {}) }})
                            —
                            {{ getBidValue(activeRound.round_data ?? {}) }} pts
                        </span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button
                        label="Enter Score"
                        icon="pi pi-pencil"
                        severity="warn"
                        size="small"
                        @click="openScoreDialog"
                    />
                    <Button
                        icon="pi pi-times"
                        severity="danger"
                        text
                        rounded
                        size="small"
                        v-tooltip.top="'Cancel Round'"
                        @click="confirmCancelRound"
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

                    <!-- Seating Order -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Seating Order (clockwise, for dealer rotation)</label>
                        <div class="flex flex-wrap gap-2">
                            <div
                                v-for="(gp, idx) in seatingOrder"
                                :key="gp.id"
                                class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-100 dark:bg-surface-800"
                            >
                                <Button
                                    icon="pi pi-chevron-left"
                                    severity="secondary"
                                    text
                                    rounded
                                    size="small"
                                    :disabled="idx === 0"
                                    @click="moveSeat(idx, -1)"
                                    class="!w-6 !h-6"
                                />
                                <span class="font-medium text-sm px-1">{{ gp.player?.name }}</span>
                                <Button
                                    icon="pi pi-chevron-right"
                                    severity="secondary"
                                    text
                                    rounded
                                    size="small"
                                    :disabled="idx === seatingOrder.length - 1"
                                    @click="moveSeat(idx, 1)"
                                    class="!w-6 !h-6"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div
                            v-for="teamNum in teamCount"
                            :key="teamNum"
                            class="border-2 border-dashed border-surface-300 dark:border-surface-600 rounded-lg p-3 min-h-32"
                            :class="{ 'border-primary': true }"
                        >
                            <InputText
                                v-model="teamNameDraft[teamNum]"
                                :placeholder="`Team ${teamNum}`"
                                class="w-full text-center font-semibold text-sm mb-2"
                                :pt="{ root: { class: '!bg-transparent !border-0 !border-b !border-surface-300 dark:!border-surface-600 !rounded-none !shadow-none text-center' } }"
                            />
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
                <!-- Edit teams button -->
                <div v-if="game?.status === 'active'" class="flex justify-end mb-2">
                    <Button
                        icon="pi pi-pencil"
                        label="Edit Teams"
                        severity="secondary"
                        text
                        size="small"
                        @click="startEditingTeams"
                    />
                </div>

                <!-- Split scoreboard table -->
                <div class="overflow-x-auto -mx-4 sm:mx-0">
                    <table class="w-full border-collapse min-w-0">
                        <thead>
                            <!-- Team name header row -->
                            <tr class="border-b border-surface-200 dark:border-surface-600">
                                <th class="p-1" :style="{ width: '2rem' }"></th>
                                <th
                                    v-for="team in teamInfo"
                                    :key="team.number"
                                    :colspan="3"
                                    class="p-2 text-center font-bold text-base border-l border-surface-200 dark:border-surface-600"
                                >
                                    {{ team.shortLabel }}
                                </th>
                            </tr>
                            <!-- Sub-headers: Bid / Result / Score per team -->
                            <tr class="border-b-2 border-surface-300 dark:border-surface-600">
                                <th class="p-1.5 text-left text-xs text-muted-color font-medium">#</th>
                                <template v-for="team in teamInfo" :key="team.number">
                                    <th class="p-1.5 text-left text-xs text-muted-color font-medium border-l border-surface-200 dark:border-surface-600">Bid</th>
                                    <th class="p-1.5 text-center text-xs text-muted-color font-medium">Result</th>
                                    <th class="p-1.5 text-right text-xs text-muted-color font-medium">Score</th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="round in rounds"
                                :key="round.id"
                                class="border-b border-surface-200 dark:border-surface-700"
                            >
                                <!-- Round number + dealer -->
                                <td class="p-1.5 text-xs text-muted-color align-top">
                                    {{ round.round_number }}
                                    <span
                                        v-if="trackDealer && getDealerName(round.dealer_game_player_id)"
                                        class="block text-[10px] leading-tight"
                                        :title="`Dealer: ${getDealerName(round.dealer_game_player_id)}`"
                                    >🃏{{ getDealerName(round.dealer_game_player_id) }}</span>
                                </td>

                                <!-- Per-team columns: Bid / Result / Score -->
                                <template v-for="team in teamInfo" :key="team.number">
                                    <!-- Bid (only on bidding team's side) -->
                                    <td class="p-1.5 text-sm align-top border-l border-surface-200 dark:border-surface-600">
                                        <template v-if="roundBidderTeamKey(round) === team.key && round.round_data?.bid_key">
                                            <span class="font-medium">{{ formatBid(round.round_data!) }}</span>
                                            <span class="block text-xs text-muted-color">{{ bidderLabel(round.round_data!) }}</span>
                                        </template>
                                    </td>
                                    <!-- Result (only on bidding team's side) -->
                                    <td class="p-1.5 text-center text-sm align-top">
                                        <template v-if="roundBidderTeamKey(round) === team.key">
                                            <Tag
                                                v-if="round.status === 'playing'"
                                                value="Playing"
                                                severity="warn"
                                                class="text-xs"
                                            />
                                            <Tag
                                                v-else-if="round.round_data?.bid_made !== undefined"
                                                :value="round.round_data.bid_made ? 'Made' : 'Set'"
                                                :severity="round.round_data.bid_made ? 'success' : 'danger'"
                                                class="text-xs"
                                            />
                                        </template>
                                    </td>
                                    <!-- Score -->
                                    <td class="p-1.5 text-right text-sm font-medium align-top">
                                        <template v-if="round.status === 'completed'">
                                            <span :class="getRoundTeamScore(round, team.number) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                                {{ getRoundTeamScore(round, team.number) >= 0 ? '+' : '' }}{{ getRoundTeamScore(round, team.number) }}
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span class="text-muted-color">—</span>
                                        </template>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-surface-300 dark:border-surface-600">
                                <td class="p-2"></td>
                                <template v-for="team in teamInfo" :key="team.number">
                                    <td
                                        colspan="3"
                                        class="p-3 text-center border-l border-surface-200 dark:border-surface-600"
                                    >
                                        <div class="text-xs text-muted-color uppercase tracking-wide">{{ team.shortLabel }}</div>
                                        <div
                                            class="text-3xl font-black"
                                            :class="team.total >= 0 ? '' : 'text-red-600 dark:text-red-400'"
                                        >
                                            {{ team.total }}
                                        </div>
                                    </td>
                                </template>
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

        <!-- Cancel Round Confirmation Dialog -->
        <Dialog
            v-model:visible="cancelRoundDialogVisible"
            header="Cancel Round"
            :modal="true"
            :style="{ width: '400px' }"
        >
            <p>Cancel the current round? The bid will be discarded.</p>

            <template #footer>
                <Button label="Keep Playing" severity="secondary" text @click="cancelRoundDialogVisible = false" />
                <Button
                    label="Cancel Round"
                    severity="danger"
                    icon="pi pi-times"
                    :loading="cancellingRound"
                    @click="handleCancelRound"
                />
            </template>
        </Dialog>

        <!-- Bid Entry Dialog (for starting a new round) -->
        <Dialog
            v-model:visible="bidDialogVisible"
            header="New Round — Place Bid"
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

            <FiveHundredBidEntry
                v-if="scoringConfig?.engine === 'five_hundred'"
                :gamePlayers="gamePlayers"
                :scoringConfig="scoringConfig"
                :gameConfig="effectiveGameConfig"
                :saving="savingRound"
                @save="handleSaveBid"
            />
        </Dialog>

        <!-- Score Entry Dialog (for completing an active round) -->
        <Dialog
            v-model:visible="scoreDialogVisible"
            header="Enter Score"
            :modal="true"
            :style="{ width: '500px' }"
        >
            <FiveHundredScoreEntry
                v-if="scoringConfig?.engine === 'five_hundred' && activeRound?.round_data"
                :gamePlayers="gamePlayers"
                :scoringConfig="scoringConfig"
                :gameConfig="effectiveGameConfig"
                :roundData="activeRound.round_data"
                :saving="savingRound"
                @save="handleSaveScore"
            />
        </Dialog>

        <!-- Legacy New Round Dialog (full save in one shot, backward compat) -->
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
import InputText from "primevue/inputtext";
import Tag from "primevue/tag";
import FiveHundredRoundEntry from "@/components/FiveHundredRoundEntry.vue";
import FiveHundredBidEntry from "@/components/FiveHundredBidEntry.vue";
import FiveHundredScoreEntry from "@/components/FiveHundredScoreEntry.vue";
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
const bidDialogVisible = ref(false);
const scoreDialogVisible = ref(false);
const cancelRoundDialogVisible = ref(false);
const savingRound = ref(false);
const cancellingRound = ref(false);
const savingTeams = ref(false);
const editingTeams = ref(false);
const teamDraft = reactive<Record<number, number>>({});
const teamNameDraft = reactive<Record<number, string>>({});
const seatingOrder = ref<GamePlayer[]>([]);
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

// Active round = the round with status='playing'
const activeRound = computed<Round | null>(() => {
    return rounds.value.find(r => r.status === "playing") ?? null;
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

    // Init team names from game_config or defaults
    const savedNames = (game.value?.game_config?.team_names ?? {}) as Record<string, string>;
    for (let i = 1; i <= teamCount.value; i++) {
        teamNameDraft[i] = savedNames[String(i)] || `Team ${i}`;
    }

    // Init seating order — use saved order or default to game_players order
    const savedOrder = (game.value?.game_config?.seating_order ?? []) as number[];
    if (savedOrder.length > 0) {
        const ordered: GamePlayer[] = [];
        for (const gpId of savedOrder) {
            const gp = gamePlayers.value.find(p => p.id === gpId);
            if (gp) ordered.push(gp);
        }
        // Add any players not in the saved order
        for (const gp of gamePlayers.value) {
            if (!ordered.find(o => o.id === gp.id)) ordered.push(gp);
        }
        seatingOrder.value = ordered;
    } else {
        seatingOrder.value = [...gamePlayers.value];
    }
}

function moveSeat(index: number, direction: number) {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= seatingOrder.value.length) return;
    const arr = [...seatingOrder.value];
    [arr[index], arr[newIndex]] = [arr[newIndex], arr[index]];
    seatingOrder.value = arr;
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

        // Build team names and seating order to save in game_config
        const teamNames: Record<string, string> = {};
        for (let i = 1; i <= teamCount.value; i++) {
            teamNames[String(i)] = teamNameDraft[i] || `Team ${i}`;
        }
        const seatingOrderIds = seatingOrder.value.map(gp => gp.id);

        const response = await gamesApi.assignTeams(game.value.id, teams, teamNames, seatingOrderIds);
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
        // Since team members share same score, use first player's total (avoid double counting)
        const teamTotal = playerTotals.value[players[0]?.id] ?? 0;

        const savedNames = (game.value?.game_config?.team_names ?? {}) as Record<string, string>;
        const customName = savedNames[String(num)];

        return {
            number: num,
            key: `team_${num}`,
            label: customName && customName !== `Team ${num}` ? customName : label,
            shortLabel: customName || `Team ${num}`,
            players,
            total: teamTotal,
        };
    });
});

function roundBidderTeamKey(round: Round): string | null {
    return (round.round_data?.bidder_team as string) ?? null;
}

function getRoundTeamScore(round: Round, teamNumber: number): number {
    const teamPlayers = gamePlayers.value.filter(gp => gp.team === teamNumber);
    if (teamPlayers.length === 0) return 0;
    // All team members share the same score, use first
    const key = `${round.id}-${teamPlayers[0].id}`;
    return scoreEdits[key] ?? 0;
}

// ── Dealer tracking ──────────────────────────────────────────────

const trackDealer = computed(() => scoringConfig.value?.track_dealer ?? false);

/**
 * Determine dealer for the next round:
 * - If there are existing rounds with a dealer, advance from the last one
 * - Otherwise, default to the first player (user can change)
 */
// Seating-aware player order for dealer rotation
const playerOrder = computed<GamePlayer[]>(() => {
    const savedOrder = (game.value?.game_config?.seating_order ?? []) as number[];
    if (savedOrder.length > 0) {
        const ordered: GamePlayer[] = [];
        for (const gpId of savedOrder) {
            const gp = gamePlayers.value.find(p => p.id === gpId);
            if (gp) ordered.push(gp);
        }
        for (const gp of gamePlayers.value) {
            if (!ordered.find(o => o.id === gp.id)) ordered.push(gp);
        }
        return ordered;
    }
    return gamePlayers.value;
});

function initDealer() {
    if (!trackDealer.value || gamePlayers.value.length === 0) return;

    // Check last round for dealer
    const lastRound = rounds.value.length > 0
        ? rounds.value[rounds.value.length - 1]
        : null;

    if (lastRound?.dealer_game_player_id) {
        // Advance to next player using seating order
        currentDealerId.value = getNextDealer(lastRound.dealer_game_player_id);
    } else {
        // Default to first player in seating order
        currentDealerId.value = playerOrder.value[0]?.id ?? null;
    }
}

function getNextDealer(currentId: number): number {
    const order = playerOrder.value;
    const idx = order.findIndex(gp => gp.id === currentId);
    const nextIdx = (idx + 1) % order.length;
    return order[nextIdx].id;
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
    if (roundData.bid_key === "misere") return "Misère / Nullo";
    if (roundData.bid_key === "open_misere") return "Open Misère / Nullo";
    const tricks = roundData.bid_tricks ?? "";
    const suit = roundData.bid_suit ?? "";
    return `${tricks}${suitSymbols[suit] ?? suit}`;
}

function bidderLabel(roundData: RoundData): string {
    // Show player name if available, fall back to team label
    if (roundData.bidder_game_player_id) {
        const gp = gamePlayers.value.find(p => p.id === roundData.bidder_game_player_id);
        if (gp?.player?.name) return gp.player.name;
    }
    if (!roundData.bidder_team) return "";
    const team = teamInfo.value.find(t => t.key === roundData.bidder_team);
    return team?.shortLabel ?? roundData.bidder_team as string;
}

// Legacy compat alias
function bidderTeamLabel(roundData: RoundData): string {
    return bidderLabel(roundData);
}

function getBidValue(roundData: RoundData): number {
    if (!roundData.bid_key) return 0;
    const bidTable = (scoringConfig.value?.bid_table ?? {}) as Record<string, number>;
    return bidTable[roundData.bid_key] ?? 0;
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

function openBidDialog() {
    bidDialogVisible.value = true;
}

function openScoreDialog() {
    scoreDialogVisible.value = true;
}

function confirmCancelRound() {
    cancelRoundDialogVisible.value = true;
}

// Two-step flow: Step 1 — Save bid only (creates round with status='playing')
async function handleSaveBid(roundData: RoundData) {
    if (!game.value) return;
    savingRound.value = true;

    try {
        const dealerId = trackDealer.value ? currentDealerId.value ?? undefined : undefined;
        const response = await gamesApi.saveRound(game.value.id, roundData, dealerId);
        const newRound = response.data.data;
        rounds.value.push(newRound);
        bidDialogVisible.value = false;

        toast.add({ severity: "success", summary: "Round Started", detail: `Round ${newRound.round_number} — bid placed`, life: 2000 });
    } catch (err: unknown) {
        let msg = "Failed to start round";
        if (err && typeof err === "object" && "response" in err) {
            const axiosError = err as { response?: { data?: { message?: string } } };
            msg = axiosError.response?.data?.message || msg;
        }
        toast.add({ severity: "error", summary: "Error", detail: msg, life: 5000 });
    } finally {
        savingRound.value = false;
    }
}

// Two-step flow: Step 2 — Complete round with tricks won
async function handleSaveScore(tricksWon: Record<string, number>, _calculatedScores: CalculateRoundResult | null) {
    if (!game.value || !activeRound.value) return;
    savingRound.value = true;

    try {
        const response = await gamesApi.completeRound(game.value.id, activeRound.value.id, tricksWon);
        const completedRound = response.data.data;

        // Replace the playing round with the completed one
        const idx = rounds.value.findIndex(r => r.id === completedRound.id);
        if (idx >= 0) {
            rounds.value[idx] = completedRound;
        }
        buildScoreMap([completedRound]);
        scoreDialogVisible.value = false;

        // Advance dealer for next round
        if (trackDealer.value && currentDealerId.value) {
            currentDealerId.value = getNextDealer(currentDealerId.value);
        }

        // Reload game to get updated totals
        const gameRes = await gamesApi.getById(game.value.id);
        game.value = gameRes.data.data;

        toast.add({ severity: "success", summary: "Score Saved", detail: `Round ${completedRound.round_number}`, life: 2000 });
    } catch (err: unknown) {
        let msg = "Failed to save score";
        if (err && typeof err === "object" && "response" in err) {
            const axiosError = err as { response?: { data?: { message?: string } } };
            msg = axiosError.response?.data?.message || msg;
        }
        toast.add({ severity: "error", summary: "Error", detail: msg, life: 5000 });
    } finally {
        savingRound.value = false;
    }
}

// Cancel active round
async function handleCancelRound() {
    if (!game.value || !activeRound.value) return;
    cancellingRound.value = true;

    try {
        await gamesApi.cancelRound(game.value.id, activeRound.value.id);

        // Remove from local list
        rounds.value = rounds.value.filter(r => r.id !== activeRound.value!.id);
        cancelRoundDialogVisible.value = false;

        toast.add({ severity: "info", summary: "Round Cancelled", detail: "The round has been discarded", life: 2000 });
    } catch (err: unknown) {
        let msg = "Failed to cancel round";
        if (err && typeof err === "object" && "response" in err) {
            const axiosError = err as { response?: { data?: { message?: string } } };
            msg = axiosError.response?.data?.message || msg;
        }
        toast.add({ severity: "error", summary: "Error", detail: msg, life: 5000 });
    } finally {
        cancellingRound.value = false;
    }
}

// Legacy one-shot save (backward compat, used by FiveHundredRoundEntry)
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
