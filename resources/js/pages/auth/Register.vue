<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({
    layout: {
        title: 'Create your account',
        description: 'Enter your details below to get started',
    },
});
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-2">
            <label for="name" class="text-sm font-medium text-foreground"
                >Name</label
            >
            <InputText
                id="name"
                name="name"
                type="text"
                required
                autofocus
                :tabindex="1"
                autocomplete="name"
                placeholder="Full name"
                fluid
                :invalid="!!errors.name"
            />
            <p v-if="errors.name" class="text-sm text-destructive">
                {{ errors.name }}
            </p>
        </div>

        <div class="grid gap-2">
            <label for="email" class="text-sm font-medium text-foreground"
                >Email address</label
            >
            <InputText
                id="email"
                name="email"
                type="email"
                required
                :tabindex="2"
                autocomplete="email"
                placeholder="email@example.com"
                fluid
                :invalid="!!errors.email"
            />
            <p v-if="errors.email" class="text-sm text-destructive">
                {{ errors.email }}
            </p>
        </div>

        <div class="grid gap-2">
            <label for="phone" class="text-sm font-medium text-foreground">
                Phone number
                <span class="font-normal text-muted-foreground"
                    >(optional)</span
                >
            </label>
            <InputText
                id="phone"
                name="phone"
                type="tel"
                :tabindex="3"
                autocomplete="tel"
                placeholder="+237612345678"
                fluid
                :invalid="!!errors.phone"
            />
            <p v-if="errors.phone" class="text-sm text-destructive">
                {{ errors.phone }}
            </p>
        </div>

        <div class="grid gap-2">
            <label for="password" class="text-sm font-medium text-foreground"
                >Password</label
            >
            <Password
                input-id="password"
                name="password"
                required
                toggle-mask
                :feedback="false"
                fluid
                placeholder="Password"
                :invalid="!!errors.password"
                :input-props="{ autocomplete: 'new-password', tabindex: 4 }"
            />
            <p v-if="errors.password" class="text-sm text-destructive">
                {{ errors.password }}
            </p>
        </div>

        <div class="grid gap-2">
            <label
                for="password_confirmation"
                class="text-sm font-medium text-foreground"
                >Confirm password</label
            >
            <Password
                input-id="password_confirmation"
                name="password_confirmation"
                required
                toggle-mask
                :feedback="false"
                fluid
                placeholder="Confirm password"
                :invalid="!!errors.password_confirmation"
                :input-props="{ autocomplete: 'new-password', tabindex: 5 }"
            />
            <p
                v-if="errors.password_confirmation"
                class="text-sm text-destructive"
            >
                {{ errors.password_confirmation }}
            </p>
        </div>

        <Button
            type="submit"
            label="Create account"
            fluid
            class="mt-1"
            :tabindex="6"
            :loading="processing"
            data-test="register-user-button"
        />

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="7"
                >Log in</TextLink
            >
        </div>
    </Form>
</template>
