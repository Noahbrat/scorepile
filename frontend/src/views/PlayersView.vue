<template>
    <div>
        <Toast position="bottom-right" />

        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold">Players</h1>
                <p class="text-muted-color mt-1">
                    Manage your player pool —
                    <template v-if="playersStore.isFiltered">
                        {{ playersStore.totalPlayers }} of {{ playersStore.poolTotal }} players
                    </template>
                    <template v-else>
                        {{ playersStore.totalPlayers }} total
                    </template>
                </p>
            </div>
            <Button label="New Player" icon="pi pi-plus" @click="openCreateDialog" />
        </div>

        <!-- Search Bar -->
        <div class="mb-4">
            <IconField>
                <InputIcon class="pi pi-search" />
                <InputText
                    v-model="searchInput"
                    placeholder="Search players..."
                    class="w-full sm:w-80"
                    @input="debouncedSearch"
                    @keyup.enter="handleSearch"
                />
            </IconField>
        </div>

        <!-- Player Grid -->
        <div v-if="playersStore.loading && !playersStore.hasPlayers" class="text-center py-8">
            <i class="pi pi-spin pi-spinner text-4xl text-muted-color"></i>
        </div>

        <div v-else-if="playersStore.hasPlayers" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="player in playersStore.players"
                :key="player.id"
                class="border border-surface-200 dark:border-surface-700 rounded-lg p-4 flex items-center gap-3"
            >
                <div
                    class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold shrink-0"
                    :style="{ background: player.color || 'var(--p-primary-color)' }"
                >
                    {{ player.avatar_emoji || player.name.charAt(0).toUpperCase() }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-semibold truncate">{{ player.name }}</div>
                </div>
                <div class="flex gap-1">
                    <Button
                        icon="pi pi-pencil"
                        severity="info"
                        text
                        rounded
                        size="small"
                        @click="openEditDialog(player)"
                    />
                    <Button
                        icon="pi pi-trash"
                        severity="danger"
                        text
                        rounded
                        size="small"
                        @click="confirmDelete(player)"
                    />
                </div>
            </div>
        </div>

        <div v-else class="text-center py-8">
            <i class="pi pi-users text-4xl text-muted-color mb-4"></i>
            <p class="text-muted-color">No players yet.</p>
            <Button label="Add your first player" link @click="openCreateDialog" class="mt-2" />
        </div>

        <!-- Create / Edit Dialog -->
        <Dialog
            v-model:visible="dialogVisible"
            :header="editingPlayer ? 'Edit Player' : 'New Player'"
            :modal="true"
            :style="{ width: '400px' }"
            :closable="!playersStore.loading"
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
                        placeholder="Player name"
                        required
                    />
                </div>

                <div class="mb-4">
                    <label for="color" class="block text-sm font-medium mb-2">Color</label>
                    <InputText
                        id="color"
                        v-model="form.color"
                        type="color"
                        class="w-16 h-10"
                    />
                </div>

                <div class="mb-6">
                    <label for="emoji" class="block text-sm font-medium mb-2">Emoji</label>
                    <InputText
                        id="emoji"
                        v-model="form.avatar_emoji"
                        class="w-full"
                        placeholder="e.g. 🎲"
                        maxlength="10"
                    />
                </div>

                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" text @click="dialogVisible = false" />
                    <Button
                        :label="editingPlayer ? 'Update' : 'Create'"
                        type="submit"
                        :loading="playersStore.loading"
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
            <p>Are you sure you want to delete <strong>{{ deletingPlayer?.name }}</strong>?</p>
            <p class="text-muted-color mt-2">This action cannot be undone.</p>

            <template #footer>
                <Button label="Cancel" severity="secondary" text @click="deleteDialogVisible = false" />
                <Button
                    label="Delete"
                    severity="danger"
                    :loading="playersStore.loading"
                    @click="handleDelete"
                />
            </template>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted } from "vue";
import { useToast } from "primevue/usetoast";
import Toast from "primevue/toast";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import { usePlayersStore } from "@/stores/players";
import type { Player, PlayerInput } from "@/types/api";

const toast = useToast();
const playersStore = usePlayersStore();

// ── Dialog state ────────────────────────────────────────────────

const dialogVisible = ref(false);
const deleteDialogVisible = ref(false);
const editingPlayer = ref<Player | null>(null);
const deletingPlayer = ref<Player | null>(null);
const searchInput = ref("");

const form = reactive<PlayerInput>({
    name: "",
    color: "#6366f1",
    avatar_emoji: "",
});

// ── Dialog openers ──────────────────────────────────────────────

function openCreateDialog() {
    editingPlayer.value = null;
    form.name = "";
    form.color = "#6366f1";
    form.avatar_emoji = "";
    dialogVisible.value = true;
}

function openEditDialog(player: Player) {
    editingPlayer.value = player;
    form.name = player.name;
    form.color = player.color || "#6366f1";
    form.avatar_emoji = player.avatar_emoji || "";
    dialogVisible.value = true;
}

function confirmDelete(player: Player) {
    deletingPlayer.value = player;
    deleteDialogVisible.value = true;
}

// ── CRUD handlers ───────────────────────────────────────────────

async function handleSave() {
    if (!form.name.trim()) {
        toast.add({ severity: "warn", summary: "Validation", detail: "Name is required", life: 3000 });
        return;
    }

    try {
        if (editingPlayer.value) {
            await playersStore.updatePlayer(editingPlayer.value.id, { ...form });
            toast.add({ severity: "success", summary: "Updated", detail: "Player updated", life: 3000 });
        } else {
            await playersStore.createPlayer({ ...form });
            toast.add({ severity: "success", summary: "Created", detail: "Player created", life: 3000 });
        }
        dialogVisible.value = false;
    } catch {
        toast.add({ severity: "error", summary: "Error", detail: playersStore.error || "Save failed", life: 5000 });
    }
}

async function handleDelete() {
    if (!deletingPlayer.value) return;

    try {
        await playersStore.deletePlayer(deletingPlayer.value.id);
        toast.add({ severity: "success", summary: "Deleted", detail: "Player deleted", life: 3000 });
        deleteDialogVisible.value = false;
    } catch {
        toast.add({ severity: "error", summary: "Error", detail: playersStore.error || "Delete failed", life: 5000 });
    }
}

// ── Search with debounce ────────────────────────────────────────

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function handleSearch() {
    if (searchTimeout) clearTimeout(searchTimeout);
    playersStore.setSearch(searchInput.value);
    playersStore.setPage(1);
    playersStore.fetchPlayers();
}

function debouncedSearch() {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        handleSearch();
    }, 300);
}

onUnmounted(() => {
    if (searchTimeout) clearTimeout(searchTimeout);
});

// ── Init ────────────────────────────────────────────────────────

onMounted(() => {
    // Reset search state when entering the players page
    playersStore.setSearch("");
    searchInput.value = "";
    playersStore.fetchPlayers();
});
</script>
