<template>
    <div>
        <Toast />

        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold">Game Types</h1>
                <p class="text-muted-color mt-1">
                    Manage game presets — {{ gameTypesStore.totalGameTypes }} total
                </p>
            </div>
            <Button label="New Game Type" icon="pi pi-plus" @click="openCreateDialog" />
        </div>

        <!-- Data Table -->
        <DataTable
            :value="gameTypesStore.gameTypes"
            :loading="gameTypesStore.loading"
            :paginator="true"
            :rows="gameTypesStore.pagination.limit"
            :totalRecords="gameTypesStore.totalGameTypes"
            :lazy="true"
            :rowsPerPageOptions="[10, 20, 50]"
            @page="onPage"
            @sort="onSort"
            sortMode="single"
            stripedRows
            responsiveLayout="scroll"
            dataKey="id"
        >
            <Column field="name" header="Name" sortable />
            <Column field="scoring_direction" header="Scoring" sortable style="width: 12rem">
                <template #body="{ data }">
                    <Tag
                        :value="data.scoring_direction === 'high_wins' ? 'High Wins' : 'Low Wins'"
                        :severity="data.scoring_direction === 'high_wins' ? 'success' : 'info'"
                    />
                </template>
            </Column>
            <Column field="default_rounds" header="Default Rounds" style="width: 10rem">
                <template #body="{ data }">
                    {{ data.default_rounds ?? "—" }}
                </template>
            </Column>
            <Column header="Actions" style="width: 10rem">
                <template #body="{ data }">
                    <div class="flex gap-2">
                        <Button
                            icon="pi pi-pencil"
                            severity="info"
                            text
                            rounded
                            size="small"
                            @click="openEditDialog(data)"
                        />
                        <Button
                            icon="pi pi-trash"
                            severity="danger"
                            text
                            rounded
                            size="small"
                            @click="confirmDelete(data)"
                        />
                    </div>
                </template>
            </Column>

            <template #empty>
                <div class="text-center py-8">
                    <i class="pi pi-cog text-4xl text-muted-color mb-4"></i>
                    <p class="text-muted-color">No game types yet.</p>
                    <Button label="Create your first game type" link @click="openCreateDialog" class="mt-2" />
                </div>
            </template>
        </DataTable>

        <!-- Create / Edit Dialog -->
        <Dialog
            v-model:visible="dialogVisible"
            :header="editingGameType ? 'Edit Game Type' : 'New Game Type'"
            :modal="true"
            :style="{ width: '500px' }"
            :closable="!gameTypesStore.loading"
        >
            <form @submit.prevent="handleSave">
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium mb-2">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        id="name"
                        v-model="form.name"
                        class="w-full"
                        placeholder="e.g. Cribbage"
                        required
                    />
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium mb-2">Description</label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        class="w-full"
                        rows="2"
                        placeholder="Optional description"
                    />
                </div>

                <div class="mb-4">
                    <label for="scoring_direction" class="block text-sm font-medium mb-2">
                        Scoring Direction <span class="text-red-500">*</span>
                    </label>
                    <Select
                        id="scoring_direction"
                        v-model="form.scoring_direction"
                        :options="scoringOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>

                <div class="mb-6">
                    <label for="default_rounds" class="block text-sm font-medium mb-2">Default Rounds</label>
                    <InputNumber
                        id="default_rounds"
                        v-model="form.default_rounds"
                        class="w-full"
                        :min="1"
                        placeholder="Optional"
                    />
                </div>

                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" text @click="dialogVisible = false" />
                    <Button
                        :label="editingGameType ? 'Update' : 'Create'"
                        type="submit"
                        :loading="gameTypesStore.loading"
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
            <p>Are you sure you want to delete <strong>{{ deletingGameType?.name }}</strong>?</p>
            <p class="text-muted-color mt-2">This action cannot be undone.</p>

            <template #footer>
                <Button label="Cancel" severity="secondary" text @click="deleteDialogVisible = false" />
                <Button
                    label="Delete"
                    severity="danger"
                    :loading="gameTypesStore.loading"
                    @click="handleDelete"
                />
            </template>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import { useToast } from "primevue/usetoast";
import Toast from "primevue/toast";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Textarea from "primevue/textarea";
import Select from "primevue/select";
import Tag from "primevue/tag";
import { useGameTypesStore } from "@/stores/gameTypes";
import type { GameType, GameTypeInput } from "@/types/api";

const toast = useToast();
const gameTypesStore = useGameTypesStore();

// ── Dialog state ────────────────────────────────────────────────

const dialogVisible = ref(false);
const deleteDialogVisible = ref(false);
const editingGameType = ref<GameType | null>(null);
const deletingGameType = ref<GameType | null>(null);

const scoringOptions = [
    { label: "High Score Wins", value: "high_wins" },
    { label: "Low Score Wins", value: "low_wins" },
];

const form = reactive<GameTypeInput>({
    name: "",
    description: "",
    scoring_direction: "high_wins",
    default_rounds: undefined,
});

// ── Dialog openers ──────────────────────────────────────────────

function openCreateDialog() {
    editingGameType.value = null;
    form.name = "";
    form.description = "";
    form.scoring_direction = "high_wins";
    form.default_rounds = undefined;
    dialogVisible.value = true;
}

function openEditDialog(gameType: GameType) {
    editingGameType.value = gameType;
    form.name = gameType.name;
    form.description = gameType.description || "";
    form.scoring_direction = gameType.scoring_direction;
    form.default_rounds = gameType.default_rounds;
    dialogVisible.value = true;
}

function confirmDelete(gameType: GameType) {
    deletingGameType.value = gameType;
    deleteDialogVisible.value = true;
}

// ── CRUD handlers ───────────────────────────────────────────────

async function handleSave() {
    if (!form.name.trim()) {
        toast.add({ severity: "warn", summary: "Validation", detail: "Name is required", life: 3000 });
        return;
    }

    try {
        if (editingGameType.value) {
            await gameTypesStore.updateGameType(editingGameType.value.id, { ...form });
            toast.add({ severity: "success", summary: "Updated", detail: "Game type updated", life: 3000 });
        } else {
            await gameTypesStore.createGameType({ ...form });
            toast.add({ severity: "success", summary: "Created", detail: "Game type created", life: 3000 });
        }
        dialogVisible.value = false;
    } catch {
        toast.add({ severity: "error", summary: "Error", detail: gameTypesStore.error || "Save failed", life: 5000 });
    }
}

async function handleDelete() {
    if (!deletingGameType.value) return;

    try {
        await gameTypesStore.deleteGameType(deletingGameType.value.id);
        toast.add({ severity: "success", summary: "Deleted", detail: "Game type deleted", life: 3000 });
        deleteDialogVisible.value = false;
    } catch {
        toast.add({ severity: "error", summary: "Error", detail: gameTypesStore.error || "Delete failed", life: 5000 });
    }
}

// ── Table events ────────────────────────────────────────────────

function onPage(event: { page: number; rows: number }) {
    gameTypesStore.fetchGameTypes(event.page + 1, event.rows);
}

function onSort(event: { sortField: string; sortOrder: number }) {
    gameTypesStore.setSort(event.sortField, event.sortOrder === 1 ? "asc" : "desc");
    gameTypesStore.fetchGameTypes();
}

// ── Init ────────────────────────────────────────────────────────

onMounted(() => {
    gameTypesStore.fetchGameTypes();
});
</script>
