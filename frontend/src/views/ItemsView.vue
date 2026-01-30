<template>
    <div>
        <Toast />

        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold">Items</h1>
                <p class="text-muted-color mt-1">
                    Manage your items — {{ itemsStore.totalItems }} total
                </p>
            </div>
            <Button label="New Item" icon="pi pi-plus" @click="openCreateDialog" />
        </div>

        <!-- Search Bar -->
        <div class="mb-4">
            <IconField>
                <InputIcon class="pi pi-search" />
                <InputText
                    v-model="searchInput"
                    placeholder="Search items..."
                    class="w-full sm:w-80"
                    @keyup.enter="handleSearch"
                />
            </IconField>
        </div>

        <!-- Data Table -->
        <DataTable
            :value="itemsStore.items"
            :loading="itemsStore.loading"
            :paginator="true"
            :rows="itemsStore.pagination.limit"
            :totalRecords="itemsStore.totalItems"
            :lazy="true"
            :rowsPerPageOptions="[10, 20, 50]"
            @page="onPage"
            @sort="onSort"
            sortMode="single"
            stripedRows
            responsiveLayout="scroll"
            dataKey="id"
        >
            <Column field="id" header="ID" sortable style="width: 5rem" />
            <Column field="title" header="Title" sortable />
            <Column field="description" header="Description">
                <template #body="{ data }">
                    <span class="line-clamp-1">{{ data.description || "—" }}</span>
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
                    <i class="pi pi-inbox text-4xl text-muted-color mb-4"></i>
                    <p class="text-muted-color">No items found.</p>
                    <Button label="Create your first item" link @click="openCreateDialog" class="mt-2" />
                </div>
            </template>
        </DataTable>

        <!-- Create / Edit Dialog -->
        <Dialog
            v-model:visible="dialogVisible"
            :header="editingItem ? 'Edit Item' : 'New Item'"
            :modal="true"
            :style="{ width: '500px' }"
            :closable="!itemsStore.loading"
        >
            <form @submit.prevent="handleSave">
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium mb-2">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        id="title"
                        v-model="form.title"
                        class="w-full"
                        placeholder="Enter item title"
                        required
                    />
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium mb-2">Description</label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        class="w-full"
                        rows="3"
                        placeholder="Enter a description (optional)"
                    />
                </div>

                <div class="mb-6">
                    <label for="status" class="block text-sm font-medium mb-2">Status</label>
                    <Select
                        id="status"
                        v-model="form.status"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>

                <div class="flex justify-end gap-2">
                    <Button label="Cancel" severity="secondary" text @click="dialogVisible = false" />
                    <Button
                        :label="editingItem ? 'Update' : 'Create'"
                        type="submit"
                        :loading="itemsStore.loading"
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
            <p>Are you sure you want to delete <strong>{{ deletingItem?.title }}</strong>?</p>
            <p class="text-muted-color mt-2">This action cannot be undone.</p>

            <template #footer>
                <Button label="Cancel" severity="secondary" text @click="deleteDialogVisible = false" />
                <Button
                    label="Delete"
                    severity="danger"
                    :loading="itemsStore.loading"
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
import Textarea from "primevue/textarea";
import Select from "primevue/select";
import Tag from "primevue/tag";
import IconField from "primevue/iconfield";
import InputIcon from "primevue/inputicon";
import { useItemsStore } from "@/stores/items";
import type { Item, ItemInput } from "@/types/api";

const toast = useToast();
const itemsStore = useItemsStore();

// ── Dialog state ────────────────────────────────────────────────

const dialogVisible = ref(false);
const deleteDialogVisible = ref(false);
const editingItem = ref<Item | null>(null);
const deletingItem = ref<Item | null>(null);
const searchInput = ref("");

const statusOptions = [
    { label: "Active", value: "active" },
    { label: "Draft", value: "draft" },
    { label: "Archived", value: "archived" },
];

const form = reactive<ItemInput>({
    title: "",
    description: "",
    status: "active",
});

// ── Status badge severity ───────────────────────────────────────

function statusSeverity(status: string): "success" | "info" | "warn" | "danger" | undefined {
    switch (status) {
        case "active": return "success";
        case "draft": return "info";
        case "archived": return "warn";
        default: return undefined;
    }
}

// ── Dialog openers ──────────────────────────────────────────────

function openCreateDialog() {
    editingItem.value = null;
    form.title = "";
    form.description = "";
    form.status = "active";
    dialogVisible.value = true;
}

function openEditDialog(item: Item) {
    editingItem.value = item;
    form.title = item.title;
    form.description = item.description || "";
    form.status = item.status;
    dialogVisible.value = true;
}

function confirmDelete(item: Item) {
    deletingItem.value = item;
    deleteDialogVisible.value = true;
}

// ── CRUD handlers ───────────────────────────────────────────────

async function handleSave() {
    if (!form.title.trim()) {
        toast.add({ severity: "warn", summary: "Validation", detail: "Title is required", life: 3000 });
        return;
    }

    try {
        if (editingItem.value) {
            await itemsStore.updateItem(editingItem.value.id, { ...form });
            toast.add({ severity: "success", summary: "Updated", detail: "Item updated", life: 3000 });
        } else {
            await itemsStore.createItem({ ...form });
            toast.add({ severity: "success", summary: "Created", detail: "Item created", life: 3000 });
        }
        dialogVisible.value = false;
    } catch {
        toast.add({ severity: "error", summary: "Error", detail: itemsStore.error || "Save failed", life: 5000 });
    }
}

async function handleDelete() {
    if (!deletingItem.value) return;

    try {
        await itemsStore.deleteItem(deletingItem.value.id);
        toast.add({ severity: "success", summary: "Deleted", detail: "Item deleted", life: 3000 });
        deleteDialogVisible.value = false;
    } catch {
        toast.add({ severity: "error", summary: "Error", detail: itemsStore.error || "Delete failed", life: 5000 });
    }
}

// ── Table events ────────────────────────────────────────────────

function handleSearch() {
    itemsStore.setSearch(searchInput.value);
    itemsStore.setPage(1);
    itemsStore.fetchItems();
}

function onPage(event: { page: number; rows: number }) {
    itemsStore.fetchItems(event.page + 1, event.rows);
}

function onSort(event: { sortField: string; sortOrder: number }) {
    itemsStore.setSort(event.sortField, event.sortOrder === 1 ? "asc" : "desc");
    itemsStore.fetchItems();
}

// ── Init ────────────────────────────────────────────────────────

onMounted(() => {
    itemsStore.fetchItems();
});
</script>
