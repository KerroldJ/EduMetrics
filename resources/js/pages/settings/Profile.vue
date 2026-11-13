<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormErrors from '@/components/FormErrors.vue';
import InputError from '@/components/InputError.vue';
import ProfilePhotoPicker from '@/components/ProfilePhotoPicker.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SchoolPage from '@/components/SchoolPage.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { edit, update } from '@/routes/profile';
import { update as updatePassword } from '@/routes/profile/password';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Update profile', href: edit() },
        ],
    },
});
defineProps<{ mustVerifyEmail: boolean; status?: string }>();
const page = usePage();
const user = computed(() => page.props.auth.user);
const form = useForm({
    name: user.value.name,
    email: user.value.email,
    mobile: typeof user.value.mobile === 'string' ? user.value.mobile : '',
    photo: null as File | null,
    remove_photo: false,
});
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
function changePassword() {
    if (passwordForm.processing) return;
    passwordForm.put(updatePassword.url(), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () =>
            passwordForm.reset(
                'current_password',
                'password',
                'password_confirmation',
            ),
    });
}
function submit() {
    if (form.processing) return;
    form.transform((data) => ({ ...data, _method: 'patch' })).post(
        update.url(),
        {
            preserveScroll: true,
            onSuccess: () => {
                form.defaults({
                    name: user.value.name,
                    email: user.value.email,
                    mobile:
                        typeof user.value.mobile === 'string'
                            ? user.value.mobile
                            : '',
                    photo: null,
                    remove_photo: false,
                });
                form.reset();
            },
        },
    );
}
</script>
<template>
    <SchoolPage
        title="Update profile"
        description="Manage your profile picture, contact information, and password."
    >
        <form class="school-panel w-full max-w-3xl" @submit.prevent="submit">
            <FormErrors :errors="form.errors" />
            <fieldset
                :disabled="form.processing"
                class="flex min-w-0 flex-col gap-6"
            >
                <ProfilePhotoPicker
                    id="profile-photo"
                    v-model="form.photo"
                    v-model:removed="form.remove_photo"
                    :name="form.name"
                    :current-photo="user.avatar ?? null"
                    :error="form.errors.photo"
                />
                <div
                    class="flex flex-col gap-5 border-t border-slate-100 pt-6 dark:border-slate-800"
                >
                    <div>
                        <h2 class="font-semibold">Personal information</h2>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            Keep your name and contact details up to date.
                        </p>
                    </div>
                    <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="profile-name" class="school-label"
                                >Full name
                                <span
                                    class="text-red-600 dark:text-red-400"
                                    aria-hidden="true"
                                    >*</span
                                ></label
                            ><Input
                                id="profile-name"
                                v-model="form.name"
                                required
                                autocomplete="name"
                                placeholder="e.g. Juan Dela Cruz"
                                :aria-invalid="Boolean(form.errors.name)"
                            /><InputError
                                class="mt-1"
                                :message="form.errors.name"
                            />
                        </div>
                        <div class="min-w-0">
                            <label for="profile-email" class="school-label"
                                >Email address
                                <span
                                    class="text-red-600 dark:text-red-400"
                                    aria-hidden="true"
                                    >*</span
                                ></label
                            ><Input
                                id="profile-email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                placeholder="e.g. juan@school.edu.ph"
                                :aria-invalid="Boolean(form.errors.email)"
                            /><InputError
                                class="mt-1"
                                :message="form.errors.email"
                            />
                        </div>
                        <div class="min-w-0">
                            <label for="profile-mobile" class="school-label"
                                >Mobile number
                                <span
                                    class="font-normal text-slate-500 dark:text-slate-400"
                                    >(optional)</span
                                ></label
                            ><Input
                                id="profile-mobile"
                                v-model="form.mobile"
                                type="tel"
                                autocomplete="tel"
                                placeholder="e.g. +639171234567"
                                :aria-invalid="Boolean(form.errors.mobile)"
                            /><InputError
                                class="mt-1"
                                :message="form.errors.mobile"
                            />
                        </div>
                    </div>
                    <div
                        v-if="mustVerifyEmail && !user.email_verified_at"
                        class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900 dark:bg-blue-950 dark:text-blue-100"
                    >
                        Your email address is unverified.
                        <Link
                            :href="send()"
                            method="post"
                            as="button"
                            class="underline underline-offset-4"
                            >Resend verification email</Link
                        >
                        <p
                            v-if="status === 'verification-link-sent'"
                            class="mt-2"
                        >
                            A new verification link has been sent to your email
                            address.
                        </p>
                    </div>
                </div>
                <div
                    class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5 dark:border-slate-800"
                >
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-[#185b94] text-white hover:bg-[#104675]"
                        data-test="update-profile-button"
                        ><Spinner v-if="form.processing" />{{
                            form.processing ? 'Updating…' : 'Update profile'
                        }}</Button
                    >
                    <p
                        v-if="form.progress"
                        role="status"
                        class="text-sm text-slate-500 dark:text-slate-400"
                    >
                        Uploading photo… {{ form.progress.percentage }}%
                    </p>
                    <p
                        v-else-if="form.recentlySuccessful"
                        role="status"
                        class="text-sm text-emerald-700 dark:text-emerald-300"
                    >
                        Profile updated.
                    </p>
                </div>
            </fieldset>
        </form>
        <form
            class="school-panel w-full max-w-3xl"
            @submit.prevent="changePassword"
        >
            <fieldset
                :disabled="passwordForm.processing"
                class="flex min-w-0 flex-col gap-5"
            >
                <div>
                    <h2 class="font-semibold">Change password</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Enter your current password, then choose and confirm a
                        new password.
                    </p>
                </div>
                <div class="max-w-sm">
                    <label for="current-password" class="school-label"
                        >Current password
                        <span
                            class="text-red-600 dark:text-red-400"
                            aria-hidden="true"
                            >*</span
                        ></label
                    >
                    <PasswordInput
                        :autofocus="false"
                        id="current-password"
                        v-model="passwordForm.current_password"
                        autocomplete="current-password"
                        required
                        placeholder="Enter your current password"
                        :aria-invalid="
                            Boolean(passwordForm.errors.current_password)
                        "
                    />
                    <InputError
                        class="mt-1"
                        :message="passwordForm.errors.current_password"
                    />
                </div>
                <div class="grid min-w-0 gap-5 sm:grid-cols-2">
                    <div class="min-w-0">
                        <label for="new-password" class="school-label"
                            >New password
                            <span
                                class="text-red-600 dark:text-red-400"
                                aria-hidden="true"
                                >*</span
                            ></label
                        >
                        <PasswordInput
                            :autofocus="false"
                            id="new-password"
                            v-model="passwordForm.password"
                            autocomplete="new-password"
                            required
                            placeholder="Choose a new password"
                            :aria-invalid="
                                Boolean(passwordForm.errors.password)
                            "
                        />
                        <InputError
                            class="mt-1"
                            :message="passwordForm.errors.password"
                        />
                    </div>
                    <div class="min-w-0">
                        <label for="confirm-password" class="school-label"
                            >Confirm new password
                            <span
                                class="text-red-600 dark:text-red-400"
                                aria-hidden="true"
                                >*</span
                            ></label
                        >
                        <PasswordInput
                            :autofocus="false"
                            id="confirm-password"
                            v-model="passwordForm.password_confirmation"
                            autocomplete="new-password"
                            required
                            placeholder="Re-enter your new password"
                            :aria-invalid="
                                Boolean(
                                    passwordForm.errors.password_confirmation,
                                )
                            "
                        />
                        <InputError
                            class="mt-1"
                            :message="passwordForm.errors.password_confirmation"
                        />
                    </div>
                </div>
                <div
                    class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-5 dark:border-slate-800"
                >
                    <Button
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="bg-[#185b94] text-white hover:bg-[#104675]"
                        ><Spinner v-if="passwordForm.processing" />{{
                            passwordForm.processing
                                ? 'Updating…'
                                : 'Update password'
                        }}</Button
                    >
                    <p
                        v-if="passwordForm.recentlySuccessful"
                        role="status"
                        class="text-sm text-emerald-700 dark:text-emerald-300"
                    >
                        Password updated.
                    </p>
                </div>
            </fieldset>
        </form>
    </SchoolPage>
</template>
