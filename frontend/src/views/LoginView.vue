<template>
    <div class="flex items-center justify-center min-h-screen w-screen p-4 sm:p-8 box-border bg-surface-900">
        <Card class="w-full max-w-md shadow-lg">
            <template #header>
                <div class="text-center px-6 sm:px-8 py-6 sm:py-8 pb-4">
                    <i class="pi pi-box text-4xl text-primary mb-3"></i>
                    <h1 class="text-3xl font-bold mb-2">Welcome Back</h1>
                    <p class="text-muted-color">Sign in to your account</p>
                </div>
            </template>

            <template #content>
                <form @submit.prevent="handleLogin" class="px-6 sm:px-8 pb-6 sm:pb-8">
                    <Message v-if="authStore.error" severity="error" class="mb-6">
                        {{ authStore.error }}
                    </Message>

                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium mb-2">
                            Email Address <span class="text-red-500 ml-1">*</span>
                        </label>
                        <InputText
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="Enter your email"
                            :invalid="!!getFieldError('email')"
                            class="w-full"
                            autocomplete="email"
                            required
                        />
                        <Message v-if="getFieldError('email')" severity="error" class="mt-2">
                            {{ getFieldError("email") }}
                        </Message>
                    </div>

                    <div class="mb-6">
                        <label for="password" class="block text-sm font-medium mb-2">
                            Password <span class="text-red-500 ml-1">*</span>
                        </label>
                        <Password
                            id="password"
                            v-model="form.password"
                            placeholder="Enter your password"
                            :invalid="!!getFieldError('password')"
                            :feedback="false"
                            toggleMask
                            class="w-full"
                            autocomplete="current-password"
                            required
                        />
                        <Message v-if="getFieldError('password')" severity="error" class="mt-2">
                            {{ getFieldError("password") }}
                        </Message>
                    </div>

                    <Button
                        type="submit"
                        label="Sign In"
                        :loading="authStore.isLoading"
                        class="w-full mb-4"
                        size="large"
                    />
                </form>
            </template>

            <template #footer>
                <div class="text-center">
                    <p class="text-muted-color">
                        Don't have an account?
                        <Button as="router-link" to="/register" label="Sign up here" link class="p-0 ml-1" />
                    </p>
                </div>
            </template>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import Card from "primevue/card";
import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";
import Message from "primevue/message";
import type { LoginRequest } from "@/types/api";

const router = useRouter();
const authStore = useAuthStore();

const form = reactive<LoginRequest>({
    email: "",
    password: "",
});

const fieldErrors = ref<Record<string, string>>({});

const getFieldError = (field: string): string | null => {
    return fieldErrors.value[field] || null;
};

const validateForm = (): boolean => {
    fieldErrors.value = {};
    let isValid = true;

    if (!form.email.trim()) {
        fieldErrors.value.email = "Email is required";
        isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        fieldErrors.value.email = "Please enter a valid email address";
        isValid = false;
    }

    if (!form.password.trim()) {
        fieldErrors.value.password = "Password is required";
        isValid = false;
    }

    return isValid;
};

const handleLogin = async () => {
    if (!validateForm()) return;

    try {
        await authStore.login(form);
        const redirectTo = (router.currentRoute.value.query.redirect as string) || "/";
        router.push(redirectTo);
    } catch (error) {
        console.error("Login failed:", error);
    }
};
</script>

<style scoped>
:deep(.p-password) {
    width: 100%;
}
:deep(.p-password .p-inputtext) {
    width: 100%;
}
</style>
