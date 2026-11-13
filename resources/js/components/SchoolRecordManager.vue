<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FormErrors from '@/components/FormErrors.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import ProfilePhotoPicker from '@/components/ProfilePhotoPicker.vue';
import SchoolFormDialog from '@/components/SchoolFormDialog.vue';
import SchoolYearPicker from '@/components/SchoolYearPicker.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/composables/useInitials';
import type { Field, PageLink, RecordData } from '@/types/school';
const { getInitials } = useInitials();
const props = defineProps<{
    fields: Field[];
    records: RecordData[];
    columns: { key: string; label: string }[];
    storeUrl: string;
    updateUrl: (id: number) => string;
    noun: string;
    links?: PageLink[];
    searchUrl?: string;
    initialSearch?: string;
    singleColumn?: boolean;
    profilePhoto?: boolean;
}>();
const initial = Object.fromEntries(
    props.fields.map((field) => [
        field.key,
        field.default ?? (field.type === 'checkbox' ? true : ''),
    ]),
) as Record<string, string | number | boolean | null>;
const form = useForm({ ...initial });
const visibleFields = computed(() =>
    props.fields.filter(
        (field) =>
            field.type !== 'hidden' &&
            (!field.visibleWhen ||
                form[field.visibleWhen.key] === field.visibleWhen.value),
    ),
);
const editing = ref<number | null>(null);
const dialogOpen = ref(false);
const selectedPhoto = ref<File | null>(null);
const removePhoto = ref(false);
const currentAvatar = ref<string | null>(null);
watch(dialogOpen, (open) => {
    if (!open) {
        selectedPhoto.value = null;
        removePhoto.value = false;
        currentAvatar.value = null;
    }
});
const formId = `school-record-${props.noun.replaceAll(' ', '-')}`;
const query = ref(props.initialSearch ?? '');
const formTitle = computed(() =>
    editing.value ? `Edit ${props.noun}` : `New ${props.noun}`,
);
function reset() {
    editing.value = null;
    selectedPhoto.value = null;
    removePhoto.value = false;
    currentAvatar.value = null;
    form.defaults({ ...initial });
    form.reset();
    form.clearErrors();
}
function create() {
    reset();
    dialogOpen.value = true;
}
function edit(record: RecordData) {
    reset();
    editing.value = record.id;
    currentAvatar.value =
        typeof record.avatar === 'string' ? record.avatar : null;
    props.fields.forEach((field) => {
        form[field.key] =
            field.type === 'password'
                ? ''
                : ((record[field.key] as string | number | boolean | null) ??
                  initial[field.key]);
    });
    dialogOpen.value = true;
}
function submit() {
    if (form.processing) return;
    const uploadingPhoto = props.profilePhoto && selectedPhoto.value !== null;
    form.transform((data) => ({
        ...data,
        ...(props.profilePhoto
            ? { photo: selectedPhoto.value, remove_photo: removePhoto.value }
            : {}),
        ...(uploadingPhoto && editing.value ? { _method: 'put' } : {}),
    }));
    form.submit(
        uploadingPhoto ? 'post' : editing.value ? 'put' : 'post',
        editing.value ? props.updateUrl(editing.value) : props.storeUrl,
        {
            preserveScroll: true,
            onSuccess: () => {
                dialogOpen.value = false;
            },
        },
    );
}
function display(value: unknown) {
    return typeof value === 'boolean'
        ? value
            ? 'Active'
            : 'Inactive'
        : value === null || value === '' || value === undefined
          ? '—'
          : String(value);
}
function search() {
    if (props.searchUrl)
        router.get(
            props.searchUrl,
            { q: query.value },
            { preserveState: true, replace: true },
        );
}
</script>
<template>
    <section class="school-panel min-w-0">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <form
                v-if="searchUrl"
                class="flex w-full gap-2 sm:w-auto sm:min-w-80"
                @submit.prevent="search"
            >
                <input
                    v-model="query"
                    aria-label="Search school accounts"
                    class="school-input"
                    placeholder="Search name or email"
                />
                <Button variant="outline" type="submit">Search</Button>
            </form>
            <p v-else class="text-sm text-slate-500 dark:text-slate-400">
                {{ records.length }} record{{ records.length === 1 ? '' : 's' }}
                on this page
            </p>
            <SchoolFormDialog
                v-model:open="dialogOpen"
                :title="formTitle"
                :description="
                    editing
                        ? 'Update the details, then save your changes.'
                        : `Enter the details to add a new ${noun}.`
                "
                :form-id="formId"
                :submit-label="`Save ${noun}`"
                :processing="form.processing"
                :wide="!singleColumn && fields.length > 5"
            >
                <template #trigger
                    ><Button
                        type="button"
                        class="ml-auto gap-2 bg-[#185b94] text-white hover:bg-[#104675]"
                        @click="create"
                        ><Plus class="size-4" aria-hidden="true" />New
                        {{ noun }}</Button
                    ></template
                >
                <FormErrors :errors="form.errors" />
                <form :id="formId" @submit.prevent="submit">
                    <fieldset
                        :disabled="form.processing"
                        class="gap-4"
                        :class="
                            singleColumn
                                ? 'flex flex-col'
                                : fields.length > 5
                                  ? 'grid sm:grid-cols-2'
                                  : 'grid'
                        "
                    >
                        <ProfilePhotoPicker
                            v-if="profilePhoto"
                            :id="formId + '-photo'"
                            v-model="selectedPhoto"
                            v-model:removed="removePhoto"
                            :current-photo="currentAvatar"
                            :name="
                                typeof form.name === 'string' ? form.name : ''
                            "
                            :error="form.errors.photo"
                            class="sm:col-span-full"
                        />
                        <p
                            v-if="profilePhoto && form.progress"
                            role="status"
                            class="text-sm text-slate-500 sm:col-span-full"
                        >
                            Uploading photo… {{ form.progress.percentage }}%
                        </p>
                        <div
                            v-for="field in visibleFields"
                            :key="field.key"
                            :class="
                                !singleColumn &&
                                (field.type === 'checkbox' ||
                                    field.fullWidth) &&
                                fields.length > 5
                                    ? 'sm:col-span-2'
                                    : ''
                            "
                        >
                            <label
                                v-if="field.type === 'checkbox'"
                                class="flex items-center gap-2 text-sm"
                                ><input
                                    v-model="form[field.key]"
                                    type="checkbox"
                                    class="size-4 accent-blue-700"
                                />{{ field.label }}</label
                            >
                            <template v-else>
                                <label :for="field.key" class="school-label">{{
                                    field.label
                                }}</label>
                                <SchoolYearPicker
                                    v-if="field.type === 'school-year'"
                                    :id="field.key"
                                    v-model="form[field.key]"
                                    :required="field.required"
                                    :invalid="Boolean(form.errors[field.key])"
                                />
                                <select
                                    v-else-if="field.type === 'select'"
                                    :id="field.key"
                                    v-model="form[field.key]"
                                    class="school-input"
                                    :required="field.required"
                                    :aria-invalid="
                                        Boolean(form.errors[field.key])
                                    "
                                >
                                    <option value="">
                                        {{
                                            field.emptyLabel ??
                                            'Select an option'
                                        }}
                                    </option>
                                    <option
                                        v-for="option in field.options"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <PasswordInput
                                    v-else-if="field.type === 'password'"
                                    :id="field.key"
                                    v-model="form[field.key]"
                                    class="school-input"
                                    :required="field.required && !editing"
                                    autocomplete="new-password"
                                    :aria-invalid="
                                        Boolean(form.errors[field.key])
                                    "
                                />
                                <input
                                    v-else
                                    :id="field.key"
                                    v-model="form[field.key]"
                                    class="school-input"
                                    :type="field.type ?? 'text'"
                                    :required="field.required"
                                    :aria-invalid="
                                        Boolean(form.errors[field.key])
                                    "
                                />
                            </template>
                            <p
                                v-if="field.hint"
                                class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                {{ field.hint }}
                            </p>
                        </div>
                    </fieldset>
                </form>
            </SchoolFormDialog>
        </div>
        <div class="overflow-x-auto">
            <table class="school-table">
                <thead>
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                        >
                            {{ column.label }}
                        </th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="record in records" :key="record.id">
                        <td v-for="column in columns" :key="column.key">
                            <div
                                v-if="profilePhoto && column.key === 'name'"
                                class="flex items-center gap-3"
                            >
                                <Avatar
                                    class="size-9 rounded-full"
                                    aria-hidden="true"
                                >
                                    <AvatarImage
                                        v-if="
                                            typeof record.avatar === 'string' &&
                                            record.avatar
                                        "
                                        :src="record.avatar"
                                        alt=""
                                    />
                                    <AvatarFallback
                                        class="rounded-full text-xs font-medium text-slate-700 dark:text-slate-200"
                                    >
                                        {{
                                            getInitials(
                                                String(record.name ?? ''),
                                            )
                                        }}
                                    </AvatarFallback>
                                </Avatar>
                                <span class="font-medium">{{
                                    display(record.name)
                                }}</span>
                            </div>
                            <template v-else>{{
                                display(record[column.key])
                            }}</template>
                        </td>
                        <td>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="edit(record)"
                                    >Edit</Button
                                ><slot name="actions" :record="record" />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!records.length">
                        <td
                            :colspan="columns.length + 1"
                            class="py-10 text-center text-slate-500"
                        >
                            No records yet. Select New {{ noun }} to add your
                            first record.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <PaginationLinks v-if="links" :links="links" />
    </section>
</template>
