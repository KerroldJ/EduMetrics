<script setup lang="ts">
import { computed } from 'vue';
import SchoolPage from '@/components/SchoolPage.vue';
import SchoolRecordManager from '@/components/SchoolRecordManager.vue';
import { dashboard } from '@/routes';
import { index, store, update } from '@/routes/users';
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
}>();
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
        label: 'Student strand',
        type: 'select',
        visibleWhen: { key: 'role', value: 'Student' },
        options: props.strands.map((strand) => ({
            value: strand.id,
            label: strand.code + ' — ' + strand.name,
        })),
    },
    {
        key: 'school_year',
        label: 'School year',
        type: 'hidden',
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
        hint: 'Manage available sections in Strands and Sections.',
        visibleWhen: { key: 'role', value: 'Student' },
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
        description="Create school accounts, assign access, and record student enrollment. Deactivate accounts to preserve evaluation history."
        ><SchoolRecordManager
            :fields="fields"
            :records="users.data"
            :links="users.links"
            :columns="[
                { key: 'name', label: 'Name' },
                { key: 'email', label: 'Email' },
                { key: 'role', label: 'Role' },
                { key: 'is_active', label: 'Status' },
            ]"
            :store-url="store.url()"
            :update-url="(id) => update.url(id)"
            :search-url="index.url()"
            :initial-search="filters.q"
            noun="user"
            single-column
    /></SchoolPage>
</template>
