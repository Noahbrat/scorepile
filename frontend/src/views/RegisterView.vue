<template>
    <div class="flex items-center justify-center min-h-screen w-screen p-4 sm:p-8 box-border bg-surface-900">
        <Card class="w-full max-w-2xl shadow-lg">
            <template #header>
                <div class="text-center px-6 sm:px-8 py-6 sm:py-8 pb-4">
                    <i class="pi pi-box text-4xl text-primary mb-3"></i>
                    <h1 class="text-3xl font-bold mb-2">Create Account</h1>
                    <p class="text-muted-color">Sign up to get started</p>
                </div>
            </template>

            <template #content>
                <form @submit.prevent="handleRegister" class="px-6 sm:px-8 pb-6 sm:pb-8">
                    <Message v-if="authStore.error" severity="error" class="mb-6">
                        {{ authStore.error }}
                    </Message>

                    <Message v-if="registrationSuccess" severity="success" class="mb-6">
                        Account created successfully! You can now sign in with your credentials.
                    </Message>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="first_name" class="block text-sm font-medium mb-2">First Name</label>
                            <InputText
                                id="first_name"
                                v-model="form.first_name"
                                placeholder="First name"
                                :invalid="!!getFieldError('first_name')"
                                class="w-full"
                                autocomplete="given-name"
                            />
                            <Message v-if="getFieldError('first_name')" severity="error" class="mt-2">
                                {{ getFieldError("first_name") }}
                            </Message>
                        </div>

                        <div>
                            <label for="last_name" class="block text-sm font-medium mb-2">Last Name</label>
                            <InputText
                                id="last_name"
                                v-model="form.last_name"
                                placeholder="Last name"
                                :invalid="!!getFieldError('last_name')"
                                class="w-full"
                                autocomplete="family-name"
                            />
                            <Message v-if="getFieldError('last_name')" severity="error" class="mt-2">
                                {{ getFieldError("last_name") }}
                            </Message>
                        </div>
                    </div>

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
                        <label for="username" class="block text-sm font-medium mb-2">
                            Username <span class="text-red-500 ml-1">*</span>
                        </label>
                        <InputText
                            id="username"
                            v-model="form.username"
                            placeholder="Choose a username"
                            :invalid="!!getFieldError('username')"
                            class="w-full"
                            autocomplete="username"
                            required
                        />
                        <small class="mt-2 text-muted-color leading-normal">
                            3-20 characters, letters, numbers, and underscores only
                        </small>
                        <Message v-if="getFieldError('username')" severity="error" class="mt-2">
                            {{ getFieldError("username") }}
                        </Message>
                    </div>

                    <div class="mb-6">
                        <label for="password" class="block text-sm font-medium mb-2">
                            Password <span class="text-red-500 ml-1">*</span>
                        </label>
                        <Password
                            id="password"
                            v-model="form.password"
                            placeholder="Create a password"
                            :invalid="!!getFieldError('password')"
                            toggleMask
                            class="w-full"
                            autocomplete="new-password"
                            required
                        />
                        <small class="mt-2 text-muted-color leading-normal">
                            At least 8 characters with uppercase, lowercase, number, and special character
                        </small>
                        <Message v-if="getFieldError('password')" severity="error" class="mt-2">
                            {{ getFieldError("password") }}
                        </Message>
                    </div>

                    <div class="mb-6">
                        <label for="confirm_password" class="block text-sm font-medium mb-2">
                            Confirm Password <span class="text-red-500 ml-1">*</span>
                        </label>
                        <Password
                            id="confirm_password"
                            v-model="confirmPassword"
                            placeholder="Confirm your password"
                            :invalid="!!getFieldError('confirm_password')"
                            :feedback="false"
                            toggleMask
                            class="w-full"
                            autocomplete="new-password"
                            required
                        />
                        <Message v-if="getFieldError('confirm_password')" severity="error" class="mt-2">
                            {{ getFieldError("confirm_password") }}
                        </Message>
                    </div>

                    <Button
                        type="submit"
                        label="Create Account"
                        :loading="authStore.isLoading"
                        :disabled="registrationSuccess"
                        class="w-full mb-4"
                        size="large"
                    />
                </form>
            </template>

            <template #footer>
                <div class="text-center">
                    <p class="text-muted-color">
                        Already have an account?
                        <Button as="router-link" to="/login" label="Sign in here" link class="p-0 ml-1" />
                    </p>
                </div>
            </template>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import { useAuthStore } from "@/stores/auth";
import Card from "primevue/card";
import InputText from "primevue/inputtext";
import Password from "primevue/password";
import Button from "primevue/button";
import Message from "primevue/message";
import type { RegisterRequest } from "@/types/api";

const authStore = useAuthStore();

const form = reactive<RegisterRequest>({
    email: "",
    username: "",
    password: "",
    first_name: "",
    last_name: "",
});

const confirmPassword = ref("");
const fieldErrors = ref<Record<string, string>>({});
const registrationSuccess = ref(false);

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

    if (!form.username.trim()) {
        fieldErrors.value.username = "Username is required";
        isValid = false;
    } else if (form.username.length < 3) {
        fieldErrors.value.username = "Username must be at least 3 characters";
        isValid = false;
    } else if (form.username.length > 20) {
        fieldErrors.value.username = "Username must be no more than 20 characters";
        isValid = false;
    } else if (!/^[a-zA-Z0-9_]+$/.test(form.username)) {
        fieldErrors.value.username = "Username can only contain letters, numbers, and underscores";
        isValid = false;
    }

    if (!form.password.trim()) {
        fieldErrors.value.password = "Password is required";
        isValid = false;
    } else if (form.password.length < 8) {
        fieldErrors.value.password = "Password must be at least 8 characters";
        isValid = false;
    } else if (
        !/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?])/.test(form.password)
    ) {
        fieldErrors.value.password = "Must include uppercase, lowercase, number, and special character";
        isValid = false;
    }

    if (!confirmPassword.value.trim()) {
        fieldErrors.value.confirm_password = "Please confirm your password";
        isValid = false;
    } else if (form.password !== confirmPassword.value) {
        fieldErrors.value.confirm_password = "Passwords do not match";
        isValid = false;
    }

    return isValid;
};

const handleRegister = async () => {
    if (!validateForm()) return;

    try {
        await authStore.register(form);
        registrationSuccess.value = true;

        form.email = "";
        form.username = "";
        form.password = "";
        form.first_name = "";
        form.last_name = "";
        confirmPassword.value = "";
    } catch (error) {
        console.error("Registration failed:", error);
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
