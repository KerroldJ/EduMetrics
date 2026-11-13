<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SchoolPage from '@/components/SchoolPage.vue';
import SchoolRecordManager from '@/components/SchoolRecordManager.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { approve, index, store, update } from '@/routes/users';
import type { Field, PageLink, RecordData } from '@/types/school';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Administration' },
            { title: 'User Management', href: index() },
        ],
    },
});
const props = defineProps<{
    users: { data: RecordData[]; links: PageLink[] };
    strands: { id: number; name: string; code: string }[];
    filters: { q?: string };
    sections: { id: number; name: string }[];
    schoolYears: { id: number; name: string }[];
}>();
const approval = useForm({});
const approvingUserId = ref<number | null>(null);
const records = computed(() =>
    props.users.data.map((user) => ({
        ...user,
        approval_status: user.is_approved ? 'Approved' : 'Pending',
        enrollment: [
            props.strands.find((strand) => strand.id === user.strand_id)?.code,
            user.grade_level ? `Grade ${user.grade_level}` : null,
            user.section,
            user.school_year,
        ]
            .filter(Boolean)
            .join(' · '),
    })),
);

function approveUser(id: number) {
    if (approval.processing) {
        return;
    }

    approvingUserId.value = id;
    approval.patch(approve.url(id), {
        preserveScroll: true,
        onFinish: () => {
            approvingUserId.value = null;
        },
    });
}

const fields = computed<Field[]>(() => [
    {
        key: 'role',
        label: 'Role',
        type: 'select',
        required: true,
        options: ['Admin', 'Teacher', 'Student'].map((value) => ({
            value,
            label: value,
        })),
    },
    { key: 'school_id', label: 'Student or employee ID' },
    { key: 'name', label: 'Full name', required: true },
    { key: 'email', label: 'Email address', type: 'email', required: true },
    {
        key: 'mobile',
        label: 'Mobile number',
        hint: 'Philippine format: +639171234567.',
    },
    {
        key: 'strand_id',
        label: 'Strand',
        type: 'select',
        options: props.strands.map((strand) => ({
            value: strand.id,
            label: strand.code + ' — ' + strand.name,
        })),
    },
    {
        key: 'school_year',
        label: 'School year',
        type: 'select',
        options: props.schoolYears.map((year) => ({
            value: year.name,
            label: year.name,
        })),
        hint: 'Evaluations are shown only for the selected school year. Manage years in Configuration.',
    },
    {
        key: 'grade_level',
        label: 'Grade level',
        type: 'select',
        visibleWhen: { key: 'role', value: 'Student' },
        options: [
            { value: 11, label: 'Grade 11' },
            { value: 12, label: 'Grade 12' },
        ],
    },
    {
        key: 'section',
        label: 'Section',
        type: 'select',
        options: props.sections.map((section) => ({
            value: section.name,
            label: section.name,
        })),
        hint: 'Manage available sections in Configuration.',
    },
    {
        key: 'password',
        label: 'Password',
        type: 'password',
        required: true,
        hint: 'At least 8 characters. Leave blank when editing to keep the current password.',
    },
    {
        key: 'password_confirmation',
        label: 'Confirm password',
        type: 'password',
    },
    {
        key: 'is_active',
        label: 'Active account',
        type: 'hidden',
        default: true,
    },
    {
        key: 'email_verified',
        label: 'Email verified',
        type: 'hidden',
        default: true,
    },
]);
</script>
<template>
    <SchoolPage
        title="User Management"
        description="Review pending registrations, approve school accounts, and manage access and enrollment."
        ><SchoolRecordManager
            :fields="fields"
            :records="records"
            :links="users.links"
            :columns="[
                { key: 'name', label: 'Name' },
                { key: 'email', label: 'Email' },
                { key: 'role', label: 'Role' },
                { key: 'enrollment', label: 'School details' },
                { key: 'approval_status', label: 'Approval' },
                { key: 'is_active', label: 'Status' },
            ]"
            :store-url="store.url()"
            :update-url="(id) => update.url(id)"
            :search-url="index.url()"
            :initial-search="filters.q"
            noun="user"
            single-column
            profile-photo
        >
            <template #actions="{ record }">
                <Button
                    v-if="!record.is_approved"
                    size="sm"
                    :disabled="approval.processing"
                    :aria-label="`Approve ${record.name}`"
                    @click="approveUser(record.id)"
                >
                    {{
                        approvingUserId === record.id
                            ? 'Approving...'
                            : 'Approve'
                    }}
                </Button>
            </template>
        </SchoolRecordManager>
    </SchoolPage>
</template>
