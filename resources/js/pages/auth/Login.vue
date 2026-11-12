<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Welcome back',
        description:
            'Sign in to your EduMetric account to continue to your school evaluation portal.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <div>
        <Head title="Sign in" />

        <div
            v-if="status"
            role="status"
            class="mb-6 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
        >
            {{ status }}
        </div>

        <PasskeyVerify />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    autocomplete="username"
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
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
                    class="h-11 bg-slate-50/70 dark:bg-slate-950/50"
                    :aria-invalid="!!errors.password"
                    :aria-describedby="
                        errors.password ? 'password-error' : undefined
                    "
                />
                <InputError id="password-error" :message="errors.password" />
            </div>

            <div
                class="flex flex-wrap items-center justify-between gap-3 text-sm"
            >
                <Label
                    for="remember"
                    class="flex items-center gap-2 font-normal"
                >
                    <Checkbox id="remember" name="remember" />
                    Remember me
                </Label>
                <TextLink
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-xs text-[#245f94] dark:text-blue-300"
                >
                    Forgot password?
                </TextLink>
            </div>

            <Button
                type="submit"
                class="mt-1 h-11 w-full bg-[#185b94] text-white hover:bg-[#104675] dark:bg-blue-500 dark:text-white dark:hover:bg-blue-600"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                {{ processing ? 'Signing in...' : 'Sign in' }}
            </Button>

            <p
                class="border-t border-slate-100 pt-5 text-center text-sm text-slate-600 dark:border-slate-800 dark:text-slate-400"
            >
                New to EduMetric?
                <TextLink
                    :href="register()"
                    class="font-medium text-[#245f94] dark:text-blue-300"
                    >Create an account</TextLink
                >
            </p>
        </Form>
    </div>
</template>
