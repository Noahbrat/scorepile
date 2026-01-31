<template>
    <div id="app" :class="{ 'app-with-sidebar': showSidebarLayout }">
        <!-- Sidebar layout for authenticated users -->
        <template v-if="showSidebarLayout">
            <!-- Mobile Header -->
            <header class="mobile-header">
                <button class="hamburger-btn" @click="toggleSidebar">
                    <i class="pi pi-bars"></i>
                </button>
                <div class="mobile-brand">Scorepile</div>
            </header>

            <!-- Sidebar Navigation -->
            <nav class="sidebar" :class="{ 'sidebar-open': sidebarOpen }">
                <div class="sidebar-header">
                    <RouterLink to="/" class="brand-link" @click="closeSidebar">
                        <i class="pi pi-box"></i>
                        <span>Scorepile</span>
                    </RouterLink>
                </div>

                <div class="sidebar-content">
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <RouterLink
                                to="/"
                                class="nav-link"
                                @click="closeSidebar"
                            >
                                <i class="pi pi-home"></i>
                                <span>Dashboard</span>
                            </RouterLink>
                        </li>
                        <li class="nav-item">
                            <RouterLink
                                to="/games"
                                class="nav-link"
                                @click="closeSidebar"
                            >
                                <i class="pi pi-play"></i>
                                <span>Games</span>
                            </RouterLink>
                        </li>
                        <li class="nav-item">
                            <RouterLink
                                to="/players"
                                class="nav-link"
                                @click="closeSidebar"
                            >
                                <i class="pi pi-users"></i>
                                <span>Players</span>
                            </RouterLink>
                        </li>
                        <li class="nav-item">
                            <RouterLink
                                to="/game-types"
                                class="nav-link"
                                @click="closeSidebar"
                            >
                                <i class="pi pi-cog"></i>
                                <span>Game Types</span>
                            </RouterLink>
                        </li>
                    </ul>
                </div>

                <!-- User Profile Section -->
                <div class="sidebar-footer">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <span class="user-initials">{{
                                authStore.userInitials
                            }}</span>
                        </div>
                        <div class="user-info">
                            <div class="user-name">
                                {{ authStore.userName || authStore.user?.username }}
                            </div>
                            <div class="user-email">
                                {{ authStore.user?.email }}
                            </div>
                        </div>
                        <Button
                            icon="pi pi-sign-out"
                            severity="secondary"
                            text
                            rounded
                            size="small"
                            @click="handleLogout"
                            aria-label="Logout"
                        />
                    </div>
                </div>
            </nav>

            <!-- Sidebar Overlay for Mobile -->
            <div
                class="sidebar-overlay"
                :class="{ 'sidebar-overlay-active': sidebarOpen }"
                @click="closeSidebar"
            ></div>

            <!-- Main Content -->
            <main class="main-content">
                <RouterView />
            </main>
        </template>

        <!-- Pages without sidebar (login, register) -->
        <template v-else>
            <RouterView />
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted } from "vue";
import { RouterLink, RouterView } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { getAuthToken } from "@/services/storage";
import Button from "primevue/button";

const authStore = useAuthStore();
const sidebarOpen = ref(false);

const showSidebarLayout = computed(() => {
    return authStore.isAuthenticated;
});

const toggleSidebar = () => {
    sidebarOpen.value = !sidebarOpen.value;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const handleLogout = async () => {
    await authStore.logout();
};

onMounted(async () => {
    if (getAuthToken() && !authStore.isAuthenticated) {
        try {
            await authStore.initialize();
        } catch (error) {
            console.warn("Failed to initialize auth:", error);
        }
    }
});
</script>

<style scoped>
#app {
    min-height: 100vh;
}

#app.app-with-sidebar {
    display: flex;
}

/* Mobile Header */
.mobile-header {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: var(--p-surface-900);
    color: var(--p-surface-0);
    z-index: 1001;
    align-items: center;
    padding: 0 1rem;
    box-shadow: var(--p-shadow-md);
}

.hamburger-btn {
    background: none;
    border: none;
    color: white;
    font-size: 1.25rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 4px;
}

.mobile-brand {
    font-size: 1.25rem;
    font-weight: bold;
    margin-left: 1rem;
}

/* Sidebar */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background: var(--p-surface-950);
    color: var(--p-surface-0);
    z-index: 1000;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease;
}

.sidebar-header {
    padding: 1.5rem 1rem;
    border-bottom: 1px solid var(--p-surface-600);
}

.brand-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--p-surface-0);
    text-decoration: none;
    font-size: 1.5rem;
    font-weight: bold;
}

.sidebar-content {
    flex: 1;
    padding: 1rem 0;
    overflow-y: auto;
}

.nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.5rem;
    color: var(--p-surface-0);
    text-decoration: none;
    transition: all 0.2s;
    border-left: 3px solid transparent;
}

.nav-link:hover {
    background: var(--p-surface-700);
    border-left-color: var(--p-surface-200);
}

.nav-link.router-link-active {
    background: var(--p-surface-700);
    border-left-color: var(--p-primary-color);
    color: var(--p-primary-color);
}

.nav-link i {
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

/* User Profile Section */
.sidebar-footer {
    border-top: 1px solid var(--p-surface-600);
    padding: 1rem;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--p-primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.user-initials {
    font-size: 0.8rem;
    font-weight: 600;
    color: white;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--p-surface-0);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-email {
    font-size: 0.75rem;
    color: var(--p-surface-400);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Sidebar Overlay */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.sidebar-overlay-active {
    opacity: 1;
    visibility: visible;
}

/* Main Content */
.main-content {
    flex: 1;
    margin-left: 250px;
    min-height: 100vh;
    padding: 2rem;
}

/* Responsive */
@media (max-width: 768px) {
    #app.app-with-sidebar {
        display: block;
    }

    .mobile-header {
        display: flex;
    }

    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar-open {
        transform: translateX(0);
    }

    .sidebar-overlay {
        display: block;
    }

    .main-content {
        margin-left: 0;
        padding: 80px 1rem 2rem 1rem;
    }
}
</style>

<!-- Light mode overrides (unscoped) -->
<style>
html:not(.app-dark) .sidebar {
    background: var(--p-surface-100);
    color: var(--p-surface-900);
}

html:not(.app-dark) .sidebar-header {
    border-bottom-color: var(--p-surface-300);
}

html:not(.app-dark) .brand-link {
    color: var(--p-surface-900);
}

html:not(.app-dark) .nav-link {
    color: var(--p-surface-700);
}

html:not(.app-dark) .nav-link:hover {
    background: var(--p-surface-200);
}

html:not(.app-dark) .nav-link.router-link-active {
    background: var(--p-surface-200);
    color: var(--p-primary-color);
}

html:not(.app-dark) .sidebar-footer {
    border-top-color: var(--p-surface-300);
}

html:not(.app-dark) .user-name {
    color: var(--p-surface-900);
}

html:not(.app-dark) .user-email {
    color: var(--p-surface-500);
}

html:not(.app-dark) .mobile-header {
    background: var(--p-surface-100);
    color: var(--p-surface-900);
}

html:not(.app-dark) .hamburger-btn {
    color: var(--p-surface-900);
}
</style>
