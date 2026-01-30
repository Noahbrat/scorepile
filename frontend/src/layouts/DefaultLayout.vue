<template>
    <div class="default-layout">
        <AppNavbar />
        <main class="layout-main">
            <slot />
        </main>
    </div>
</template>

<script setup lang="ts">
import AppNavbar from "@/components/AppNavbar.vue";
</script>

<style scoped>
.default-layout {
    min-height: 100vh;
}

.layout-main {
    padding: 2rem;
}
</style>
