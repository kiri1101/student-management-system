<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Info } from 'lucide-vue-next';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import TextLink from '@/components/TextLink.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Welcome back',
        description: 'Sign in to your account',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 rounded-lg bg-primary-50 px-4 py-2.5 text-center text-sm font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <!-- Username -->
        <div class="grid gap-2">
            <div class="flex items-center gap-2">
                <label for="email" class="text-sm font-medium text-foreground">
                    Username
                </label>
                <Info
                    v-tooltip="'Email, employee ID, or Matricule'"
                    :size="14"
                    class="cursor-pointer text-muted-foreground"
                />
            </div>
            <InputText
                id="email"
                name="email"
                type="text"
                required
                autofocus
                :tabindex="1"
                autocomplete="username"
                placeholder="you@school.edu · EMP-001 · 22A001"
                fluid
                :invalid="!!errors.email"
            />
            <p v-if="errors.email" class="text-sm text-destructive">
                {{ errors.email }}
            </p>
        </div>

        <!-- Password -->
        <div class="grid gap-2">
            <label for="password" class="text-sm font-medium text-foreground">
                Password
            </label>
            <Password
                input-id="password"
                name="password"
                required
                toggle-mask
                :feedback="false"
                fluid
                placeholder="Enter your password"
                :invalid="!!errors.password"
                :input-props="{
                    autocomplete: 'current-password',
                    tabindex: 2,
                }"
            />
            <p v-if="errors.password" class="text-sm text-destructive">
                {{ errors.password }}
            </p>
        </div>

        <div class="flex items-center justify-between">
            <label
                for="remember"
                class="flex cursor-pointer items-center gap-2.5 text-sm text-foreground"
            >
                <Checkbox
                    input-id="remember"
                    name="remember"
                    value="1"
                    binary
                    :tabindex="3"
                />
                <span>Remember me</span>
            </label>

            <TextLink
                v-if="canResetPassword"
                :href="request()"
                class="text-sm"
                :tabindex="5"
            >
                Forgot password?
            </TextLink>
        </div>

        <Button
            type="submit"
            label="Log in"
            fluid
            class="mt-1"
            :tabindex="4"
            :loading="processing"
            data-test="login-button"
        />

        <div
            v-if="canRegister"
            class="text-center text-sm text-muted-foreground"
        >
            Don't have an account?
            <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
        </div>
    </Form>
</template>

<style>
.p-tooltip-text {
    font-size: 0.8rem !important;
}

.p-tooltip {
    max-width: 12rem !important;
}
</style>
