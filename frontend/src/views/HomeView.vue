<template>
    <div>
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-2">Dashboard</h1>
            <p class="text-muted-color">
                Welcome back, {{ authStore.userName || authStore.user?.username }}!
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Games Card -->
            <Card>
                <template #content>
                    <div class="p-4">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="bg-primary/10 text-primary rounded-full p-3">
                                <i class="pi pi-play text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">Games</h3>
                                <p class="text-muted-color text-sm">
                                    {{ gamesStore.activeGames.length }} active
                                </p>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm mb-4">
                            <div class="flex justify-between">
                                <span class="text-muted-color">Total Games</span>
                                <span class="font-semibold">{{ gamesStore.totalGames }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-color">Active</span>
                                <span class="font-semibold">{{ gamesStore.activeGames.length }}</span>
                            </div>
                        </div>
                        <Button
                            as="router-link"
                            to="/games"
                            label="View Games"
                            icon="pi pi-arrow-right"
                            iconPos="right"
                            class="w-full"
                        />
                    </div>
                </template>
            </Card>

            <!-- Players Card -->
            <Card>
                <template #content>
                    <div class="p-4">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="bg-green-500/10 text-green-500 rounded-full p-3">
                                <i class="pi pi-users text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">Players</h3>
                                <p class="text-muted-color text-sm">Player pool</p>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm mb-4">
                            <div class="flex justify-between">
                                <span class="text-muted-color">Total Players</span>
                                <span class="font-semibold">{{ playersStore.totalPlayers }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-color">Account Status</span>
                                <Tag :value="authStore.user?.active ? 'Active' : 'Inactive'"
                                     :severity="authStore.user?.active ? 'success' : 'warn'" />
                            </div>
                        </div>
                        <Button
                            as="router-link"
                            to="/players"
                            label="Manage Players"
                            icon="pi pi-arrow-right"
                            iconPos="right"
                            severity="secondary"
                            class="w-full"
                        />
                    </div>
                </template>
            </Card>

            <!-- Quick Action Card -->
            <Card>
                <template #content>
                    <div class="p-4">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="bg-blue-500/10 text-blue-500 rounded-full p-3">
                                <i class="pi pi-bolt text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">Quick Start</h3>
                                <p class="text-muted-color text-sm">Jump right in</p>
                            </div>
                        </div>
                        <Button
                            label="New Game"
                            icon="pi pi-plus"
                            class="w-full"
                            @click="router.push('/games')"
                        />
                    </div>
                </template>
            </Card>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import Card from "primevue/card";
import Button from "primevue/button";
import Tag from "primevue/tag";
import { useAuthStore } from "@/stores/auth";
import { useGamesStore } from "@/stores/games";
import { usePlayersStore } from "@/stores/players";

const router = useRouter();
const authStore = useAuthStore();
const gamesStore = useGamesStore();
const playersStore = usePlayersStore();

onMounted(async () => {
    try {
        await Promise.all([
            gamesStore.fetchGames(1, 20),
            playersStore.fetchPlayers(1, 1),
        ]);
    } catch {
        // non-critical — just won't show stats
    }
});
</script>
