<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create your account',
        description:
            'Join your school community on EduMetric. Enter your details to get started.',
    },
});
</script>

<template>
    <div>
        <Head title="Create an account" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-2">
                <Label for="name">Full name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    autocomplete="name"
                    name="name"
                    placeholder="Enter your full name"
                    class="h-11 bg-slate-50/70 dark:bg-slate-950/50"
                    :aria-invalid="!!errors.name"
                    :aria-describedby="errors.name ? 'name-error' : undefined"
                />
                <InputError id="name-error" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                    placeholder="Enter your email address"
                    class="h-11 bg-slate-50/70 dark:bg-slate-950/50"
                    :aria-invalid="!!errors.email"
                    :aria-describedby="errors.email ? 'email-error' : undefined"
                />
                <InputError id="email-error" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    autocomplete="new-password"
                    name="password"
                    placeholder="Create a password"
                    :passwordrules="passwordRules"
                    class="h-11 bg-slate-50/70 dark:bg-slate-950/50"
                    :aria-invalid="!!errors.password"
                    :aria-describedby="
                        errors.password ? 'password-error' : undefined
                    "
                />
                <InputError id="password-error" :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Re-enter your password"
                    :passwordrules="passwordRules"
                    class="h-11 bg-slate-50/70 dark:bg-slate-950/50"
                    :aria-invalid="!!errors.password_confirmation"
                    :aria-describedby="
                        errors.password_confirmation
                            ? 'password-confirmation-error'
                            : undefined
                    "
                />
                <InputError
                    id="password-confirmation-error"
                    :message="errors.password_confirmation"
                />
            </div>

            <Button
                type="submit"
                class="mt-1 h-11 w-full bg-[#185b94] text-white hover:bg-[#104675] dark:bg-blue-500 dark:text-white dark:hover:bg-blue-600"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Creating account...' : 'Create account' }}
            </Button>

            <p
                class="border-t border-slate-100 pt-5 text-center text-sm text-slate-600 dark:border-slate-800 dark:text-slate-400"
            >
                Already have an account?
                <TextLink
                    :href="login()"
                    class="font-medium text-[#245f94] dark:text-blue-300"
                    >Sign in</TextLink
                >
            </p>
        </Form>
    </div>
</template>
