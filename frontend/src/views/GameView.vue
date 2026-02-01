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
                        label="Add Round"
                        icon="pi pi-plus"
                        severity="info"
                        @click="addRound"
                        :loading="addingRound"
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

            <!-- Score Table -->
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

            <div v-if="rounds.length === 0 && game.status === 'active'" class="text-center py-8 text-muted-color">
                <i class="pi pi-table text-4xl mb-4"></i>
                <p>No rounds yet. Click "Add Round" to start scoring.</p>
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
import { gamesApi, roundsApi, scoresApi } from "@/services/api";
import type { Game, GamePlayer, Round, Score } from "@/types/api";

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

// Local score edits: key = `${roundId}-${gamePlayerId}`, value = points
const scoreEdits = reactive<Record<string, number | null>>({});
// Existing score IDs: key = `${roundId}-${gamePlayerId}`, value = score id
const scoreIds = reactive<Record<string, number>>({});

// ── Computed ─────────────────────────────────────────────────────

const gamePlayers = computed<GamePlayer[]>(() => game.value?.game_players ?? []);

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
                scoreEdits[key] = score.points;
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
