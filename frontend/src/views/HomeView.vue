<template>
    <div>
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-2">Dashboard</h1>
            <p class="text-muted-color">
                Welcome back, {{ authStore.userName || authStore.user?.username }}!
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Items Summary Card -->
            <Card>
                <template #content>
                    <div class="p-4">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="bg-primary/10 text-primary rounded-full p-3">
                                <i class="pi pi-list text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">Items</h3>
                                <p class="text-muted-color text-sm">Manage your items</p>
                            </div>
                        </div>
                        <Button
                            as="router-link"
                            to="/items"
                            label="View Items"
                            icon="pi pi-arrow-right"
                            iconPos="right"
                            class="w-full"
                        />
                    </div>
                </template>
            </Card>

            <!-- Quick Stats Card -->
            <Card>
                <template #content>
                    <div class="p-4">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="bg-green-500/10 text-green-500 rounded-full p-3">
                                <i class="pi pi-chart-bar text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">Quick Stats</h3>
                                <p class="text-muted-color text-sm">At a glance</p>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-color">Total Items</span>
                                <span class="font-semibold">{{ itemsStore.totalItems }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-color">Account Status</span>
                                <Tag :value="authStore.user?.active ? 'Active' : 'Inactive'"
                                     :severity="authStore.user?.active ? 'success' : 'warn'" />
                            </div>
                        </div>
                    </div>
                </template>
            </Card>

            <!-- Profile Card -->
            <Card>
                <template #content>
                    <div class="p-4">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="bg-blue-500/10 text-blue-500 rounded-full p-3">
                                <i class="pi pi-user text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">Profile</h3>
                                <p class="text-muted-color text-sm">{{ authStore.user?.email }}</p>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-color">Username</span>
                                <span class="font-semibold">{{ authStore.user?.username }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-color">Role</span>
                                <Tag :value="authStore.user?.role || 'user'" />
                            </div>
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted } from "vue";
import Card from "primevue/card";
import Button from "primevue/button";
import Tag from "primevue/tag";
import { useAuthStore } from "@/stores/auth";
import { useItemsStore } from "@/stores/items";

const authStore = useAuthStore();
const itemsStore = useItemsStore();

onMounted(async () => {
    // Preload items count for the dashboard
    try {
        await itemsStore.fetchItems(1, 1); // minimal fetch for count
    } catch {
        // non-critical — just won't show stats
    }
});
</script>
