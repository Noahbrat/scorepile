<template>
    <Menubar :model="menuItems" class="border-0 border-b rounded-none">
        <template #start>
            <RouterLink to="/" class="flex items-center gap-2 font-bold text-lg mr-4">
                <i class="pi pi-box text-primary"></i>
                <span>Scorepile</span>
            </RouterLink>
        </template>

        <template #end>
            <div class="flex items-center gap-3">
                <span class="text-sm text-muted-color hidden sm:inline">
                    {{ authStore.userName || authStore.user?.username }}
                </span>
                <Button
                    icon="pi pi-sign-out"
                    severity="secondary"
                    text
                    rounded
                    size="small"
                    @click="handleLogout"
                    v-tooltip.bottom="'Sign out'"
                />
            </div>
        </template>
    </Menubar>
</template>

<script setup lang="ts">
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import Menubar from "primevue/menubar";
import Button from "primevue/button";

const router = useRouter();
const authStore = useAuthStore();

const menuItems = [
    {
        label: "Dashboard",
        icon: "pi pi-home",
        command: () => router.push("/"),
    },
    {
        label: "Items",
        icon: "pi pi-list",
        command: () => router.push("/items"),
    },
];

const handleLogout = async () => {
    await authStore.logout();
    router.push("/login");
};
</script>
