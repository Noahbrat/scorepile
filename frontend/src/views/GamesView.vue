<template>
    <div>
        <Toast />

        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold">Games</h1>
                <p class="text-muted-color mt-1">
                    {{ gamesStore.totalGames }} games total
                </p>
            </div>
            <Button label="New Game" icon="pi pi-plus" @click="openCreateDialog" />
        </div>

        <!-- Search & Filter -->
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <IconField class="flex-1">
                <InputIcon class="pi pi-search" />
                <InputText
                    v-model="searchInput"
                    placeholder="Search games..."
                    class="w-full"
                    @keyup.enter="handleSearch"
                />
            </IconField>
            <Select
                v-model="statusFilterValue"
                :options="statusFilterOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="All statuses"
                class="w-full sm:w-48"
                @change="handleStatusFilter"
            />
        </div>

        <!-- Games List -->
        <DataTable
            :value="gamesStore.games"
            :loading="gamesStore.loading && gamesStore.games.length === 0"
            :paginator="true"
            :rows="gamesStore.pagination.limit"
            :totalRecords="gamesStore.totalGames"
            :lazy="true"
            :rowsPerPageOptions="[10, 20, 50]"
            @page="onPage"
            @sort="onSort"
            @row-click="onRowClick"
            sortMode="single"
            stripedRows
            responsiveLayout="scroll"
            dataKey="id"
            class="cursor-pointer"
        >
            <Column field="name" header="Name" sortable>
                <template #body="{ data }">
                    <div>
                        <div class="font-semibold">{{ data.name }}</div>
                        <div v-if="data.game_type" class="text-muted-color text-xs">
                            {{ data.game_type.name }}
                        </div>
                    </div>
                </template>
            </Column>
            <Column field="status" header="Status" sortable style="width: 8rem">
                <template #body="{ data }">
                    <Tag
                        :value="data.status"
                        :severity="statusSeverity(data.status)"
                    />
                </template>
            </Column>
            <Column header="Players" style="width: 12rem">
                <template #body="{ data }">
                    <div v-if="data.game_players?.length" class="flex flex-wrap gap-1">
                        <span
                            v-for="gp in data.game_players"
                            :key="gp.id"
                            class="text-xs px-2 py-0.5 rounded-full"
                            :class="gp.is_winner ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 font-bold' : 'bg-surface-100 dark:bg-surface-700'"
                        >
                            {{ gp.player?.name || `Player ${gp.player_id}` }}
                            <span v-if="gp.is_winner"> 🏆</span>
                        </span>
                    </div>
                    <span v-else class="text-muted-color">—</span>
                </template>
            </Column>
            <Column field="modified" header="Updated" sortable style="width: 10rem">
                <template #body="{ data }">
                    <span class="text-sm text-muted-color">{{ formatDate(data.modified) }}</span>
                </template>
            </Column>
            <Column header="" style="width: 4rem">
                <template #body="{ data }">
                    <Button
                        icon="pi pi-trash"
                        severity="danger"
                        text
                        rounded
                        size="small"
                        @click.stop="confirmDelete(data)"
                    />
                </template>
            </Column>

            <template #empty>
                <div class="text-center py-8">
                    <i class="pi pi-play text-4xl text-muted-color mb-4"></i>
                    <p class="text-muted-color">No games yet.</p>
                    <Button label="Start your first game" link @click="openCreateDialog" class="mt-2" />
                </div>
            </template>
        </DataTable>

        <!-- Create Dialog -->
        <Dialog
            v-model:visible="dialogVisible"
            header="New Game"
            :modal="true"
            :style="{ width: '500px' }"
            :closable="!gamesStore.loading"
        >
            <form @submit.prevent="handleCreate">
                <div class="mb-4">
                    <label for="game-name" class="block text-sm font-medium mb-2">
                        Game Name <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        id="game-name"
                        v-model="form.name"
                        class="w-full"
                        placeholder="e.g. Friday Night Cribbage"
                        required
                    />
                </div>

                <div class="mb-4">
                    <label for="game-type" class="block text-sm font-medium mb-2">Game Type</label>
                    <Select
                        id="game-type"
                        v-model="form.game_type_id"
                        :options="gameTypesStore.gameTypes"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Select game type (optional)"
                        class="w-full"
                        showClear
                    />
                </div>

                <div class="mb-4">
                    <label for="players" class="block text-sm font-medium mb-2">
                        Players <span class="text-red-500">*</span> (at least 2)
                    </label>
                    <MultiSelect
                        id="players"
                        v-model="form.player_ids"
                        :options="playersStore.players"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Select players"
                        class="w-full"
                        display="chip"
                    />
                </div>

                <!-- Team Assignment (for team-based games) -->
                <div v-if="teamsEnabled && form.player_ids && form.player_ids.length >= 2" class="mb-4">
                    <label class="block text-sm font-medium mb-2">Team Assignment</label>
                    <div class="space-y-2">
                        <div
                            v-for="playerId in form.player_ids"
                            :key="playerId"
                            class="flex items-center gap-3"
                        >
                            <span class="text-sm flex-1">
                                {{ playersStore.players.find(p => p.id === playerId)?.name ?? `Player ${playerId}` }}
                            </span>
                            <Select
                                :modelValue="form.team_assignments?.[playerId] ?? 1"
                                @update:modelValue="(v: number) => { if (form.team_assignments) form.team_assignments[playerId] = v; }"
                                :options="[{ label: 'Team 1', value: 1 }, { label: 'Team 2', value: 2 }]"
                                optionLabel="label"
                                optionValue="value"
                                class="w-32"
                            />
                        </div>
                    </div>
                </div>

                <!-- Game Config Options (from scoring engine) -->
                <div v-if="gameConfigOptions.length > 0" class="mb-4">
                    <label class="block text-sm font-medium mb-2">Game Options</label>
                    <div class="space-y-3">
                        <div v-for="opt in gameConfigOptions" :key="opt.key" class="flex items-center gap-3">
                            <template v-if="opt.type === 'boolean'">
                                <Checkbox
                                    :modelValue="(form.game_config?.[opt.key] ?? opt.default) as boolean"
                                    @update:modelValue="(v: boolean) => { if (form.game_config) form.game_config[opt.key] = v; }"
                                    :binary="true"
                                    :inputId="`opt-${opt.key}`"
                                />
                                <label :for="`opt-${opt.key}`" class="text-sm">{{ opt.label }}</label>
                            </template>
                            <template v-else-if="opt.type === 'select' && opt.choices">
                                <label class="text-sm flex-1">{{ opt.label }}</label>
                                <Select
                                    :modelValue="form.game_config?.[opt.key] ?? opt.default"
                                    @update:modelValue="(v: unknown) => { if (form.game_config) form.game_config[opt.key] = v; }"
                                    :options="opt.choices"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-40"
                                />
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" text @click="dialogVisible = false" />
                    <Button
                        label="Start Game"
                        type="submit"
                        icon="pi pi-play"
                        :loading="gamesStore.loading"
                    />
                </div>
            </form>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog
            v-model:visible="deleteDialogVisible"
            header="Confirm Delete"
            :modal="true"
            :style="{ width: '400px' }"
        >
            <p>Are you sure you want to delete <strong>{{ deletingGame?.name }}</strong>?</p>
            <p class="text-muted-color mt-2">All rounds and scores will be lost.</p>

            <template #footer>
                <Button label="Cancel" severity="secondary" text @click="deleteDialogVisible = false" />
                <Button
                    label="Delete"
                    severity="danger"
                    :loading="gamesStore.loading"
                    @click="handleDelete"
                />
            </template>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "primevue/usetoast";
import Toast from "primevue/toast";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import MultiSelect from "primevue/multiselect";
import Tag from "primevue/tag";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import { useGamesStore } from "@/stores/games";
import { usePlayersStore } from "@/stores/players";
import { useGameTypesStore } from "@/stores/gameTypes";
import Checkbox from "primevue/checkbox";
import type { Game, GameInput, GameType, ScoringConfig } from "@/types/api";

const router = useRouter();
const toast = useToast();
const gamesStore = useGamesStore();
const playersStore = usePlayersStore();
const gameTypesStore = useGameTypesStore();

// ── Dialog state ────────────────────────────────────────────────

const dialogVisible = ref(false);
const deleteDialogVisible = ref(false);
const deletingGame = ref<Game | null>(null);
const searchInput = ref("");
const statusFilterValue = ref<string | undefined>(undefined);

const statusFilterOptions = [
    { label: "All", value: undefined },
    { label: "Active", value: "active" },
    { label: "Completed", value: "completed" },
    { label: "Abandoned", value: "abandoned" },
];

const form = reactive<GameInput>({
    name: "",
    game_type_id: null,
    player_ids: [],
    team_assignments: {},
    game_config: {},
});

const selectedGameType = computed<GameType | null>(() => {
    if (!form.game_type_id) return null;
    return gameTypesStore.gameTypes.find(gt => gt.id === form.game_type_id) ?? null;
});

const scoringConfig = computed<ScoringConfig | null>(() => selectedGameType.value?.scoring_config ?? null);

const teamsEnabled = computed(() => scoringConfig.value?.teams?.enabled ?? false);

const gameConfigOptions = computed(() => scoringConfig.value?.options ?? []);

// Reset team assignments and game config when game type changes
watch(() => form.game_type_id, () => {
    form.team_assignments = {};
    form.game_config = {};
    // Set defaults from scoring config options
    if (scoringConfig.value?.options) {
        for (const opt of scoringConfig.value.options) {
            form.game_config[opt.key] = opt.default;
        }
    }
});

// ── Helpers ─────────────────────────────────────────────────────

function statusSeverity(status: string): "success" | "info" | "warn" | "danger" | undefined {
    switch (status) {
        case "active": return "success";
        case "completed": return "info";
        case "abandoned": return "warn";
        default: return undefined;
    }
}

function formatDate(dateStr?: string): string {
    if (!dateStr) return "—";
    return new Date(dateStr).toLocaleDateString();
}

// ── Dialog openers ──────────────────────────────────────────────

function openCreateDialog() {
    form.name = "";
    form.game_type_id = null;
    form.player_ids = [];
    form.team_assignments = {};
    form.game_config = {};
    dialogVisible.value = true;
}

function confirmDelete(game: Game) {
    deletingGame.value = game;
    deleteDialogVisible.value = true;
}

// ── CRUD handlers ───────────────────────────────────────────────

async function handleCreate() {
    if (!form.name.trim()) {
        toast.add({ severity: "warn", summary: "Validation", detail: "Game name is required", life: 3000 });
        return;
    }
    if (!form.player_ids || form.player_ids.length < 2) {
        toast.add({ severity: "warn", summary: "Validation", detail: "Select at least 2 players", life: 3000 });
        return;
    }

    try {
        const game = await gamesStore.createGame({ ...form });
        toast.add({ severity: "success", summary: "Created", detail: "Game started!", life: 3000 });
        dialogVisible.value = false;
        router.push(`/games/${game.id}`);
    } catch {
        toast.add({ severity: "error", summary: "Error", detail: gamesStore.error || "Create failed", life: 5000 });
    }
}

async function handleDelete() {
    if (!deletingGame.value) return;

    try {
        await gamesStore.deleteGame(deletingGame.value.id);
        toast.add({ severity: "success", summary: "Deleted", detail: "Game deleted", life: 3000 });
        deleteDialogVisible.value = false;
    } catch {
        toast.add({ severity: "error", summary: "Error", detail: gamesStore.error || "Delete failed", life: 5000 });
    }
}

// ── Table events ────────────────────────────────────────────────

function handleSearch() {
    gamesStore.setSearch(searchInput.value);
    gamesStore.setPage(1);
    gamesStore.fetchGames();
}

function handleStatusFilter() {
    gamesStore.setStatusFilter(statusFilterValue.value);
    gamesStore.setPage(1);
    gamesStore.fetchGames();
}

function onPage(event: { page: number; rows: number }) {
    gamesStore.fetchGames(event.page + 1, event.rows);
}

function onSort(event: { sortField: string; sortOrder: number }) {
    gamesStore.setSort(event.sortField, event.sortOrder === 1 ? "asc" : "desc");
    gamesStore.fetchGames();
}

function onRowClick(event: { data: Game }) {
    router.push(`/games/${event.data.id}`);
}

// ── Init ────────────────────────────────────────────────────────

onMounted(() => {
    gamesStore.fetchGames();
    playersStore.fetchPlayers();
    gameTypesStore.fetchGameTypes();
});
</script>
