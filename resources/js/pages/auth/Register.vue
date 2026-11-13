<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
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
    strands: { id: number; code: string; name: string }[];
    sections: { id: number; name: string }[];
}>();

const role = ref('');

defineOptions({
    layout: {
        title: 'Create your account',
        description:
            'Register as a teacher or student. Your school administrator will review your account before you can sign in.',
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
            class="flex flex-col gap-4"
        >
            <div class="flex flex-col gap-2">
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

            <div class="flex flex-col gap-2">
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

            <div class="flex flex-col gap-2">
                <Label for="role">Register as</Label>
                <select
                    id="role"
                    v-model="role"
                    name="role"
                    required
                    class="school-input h-11"
                    :aria-invalid="!!errors.role"
                    :aria-describedby="errors.role ? 'role-error' : undefined"
                >
                    <option value="" disabled>Select a role</option>
                    <option value="Teacher">Teacher</option>
                    <option value="Student">Student</option>
                </select>
                <InputError id="role-error" :message="errors.role" />
            </div>

            <div class="flex flex-col gap-2">
                <Label for="strand_id">Strand</Label>
                <select
                    id="strand_id"
                    name="strand_id"
                    required
                    class="school-input h-11"
                    :aria-invalid="!!errors.strand_id"
                    :aria-describedby="
                        errors.strand_id ? 'strand-error' : undefined
                    "
                >
                    <option value="" selected disabled>Select a strand</option>
                    <option
                        v-for="strand in strands"
                        :key="strand.id"
                        :value="strand.id"
                    >
                        {{ strand.code }} — {{ strand.name }}
                    </option>
                </select>
                <InputError id="strand-error" :message="errors.strand_id" />
            </div>

            <div class="flex flex-col gap-2">
                <Label for="section">Section</Label>
                <select
                    id="section"
                    name="section"
                    required
                    class="school-input h-11"
                    :aria-invalid="!!errors.section"
                    :aria-describedby="
                        errors.section ? 'section-error' : undefined
                    "
                >
                    <option value="" selected disabled>Select a section</option>
                    <option
                        v-for="section in sections"
                        :key="section.id"
                        :value="section.name"
                    >
                        {{ section.name }}
                    </option>
                </select>
                <InputError id="section-error" :message="errors.section" />
            </div>

            <div v-if="role === 'Student'" class="flex flex-col gap-2">
                <Label for="grade_level">Grade level</Label>
                <select
                    id="grade_level"
                    name="grade_level"
                    required
                    class="school-input h-11"
                    :aria-invalid="!!errors.grade_level"
                    :aria-describedby="
                        errors.grade_level ? 'grade-level-error' : undefined
                    "
                >
                    <option value="" selected disabled>
                        Select a grade level
                    </option>
                    <option :value="11">Grade 11</option>
                    <option :value="12">Grade 12</option>
                </select>
                <InputError
                    id="grade-level-error"
                    :message="errors.grade_level"
                />
            </div>

            <div class="flex flex-col gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    :autofocus="false"
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

            <div class="flex flex-col gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    :autofocus="false"
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
                {{ processing ? 'Submitting...' : 'Submit for approval' }}
            </Button>

            <p
                class="border-t border-slate-100 pt-4 text-center text-sm text-slate-600 dark:border-slate-800 dark:text-slate-400"
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
