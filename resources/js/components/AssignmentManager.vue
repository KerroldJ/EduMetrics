<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import FormErrors from '@/components/FormErrors.vue';
import { Button } from '@/components/ui/button';
import SchoolRecordManager from '@/components/SchoolRecordManager.vue';
import { store, update, status } from '@/routes/assignments';
import type { Field, PageLink, RecordData } from '@/types/school';

const props = defineProps<{
    assignments: {
        data: (RecordData & {
            teacher: { name: string };
            strand: { code: string };
        })[];
        links: PageLink[];
    };
    teachers: { id: number; name: string }[];
    strands: { id: number; code: string; name: string }[];
    sections: { id: number; name: string }[];
    schoolYears: { id: number; name: string }[];
}>();
const records = computed(() =>
    props.assignments.data.map((item) => ({
        ...item,
        teacher_name: item.teacher.name,
        strand_name: item.strand.code,
        grade_name: item.grade_level
            ? 'Grade ' + item.grade_level
            : 'Both grades',
        section_name: item.section || 'All sections',
    })),
);
const statusErrors = ref<Record<string, string>>({});
const statusBusy = ref(false);
function toggleStatus(record: RecordData) {
    statusErrors.value = {};
    statusBusy.value = true;
    router.patch(
        status.url(record.id),
        { is_active: !record.is_active },
        {
            preserveScroll: true,
            onError: (errors) => {
                statusErrors.value = errors;
            },
            onFinish: () => {
                statusBusy.value = false;
            },
        },
    );
}
const fields = computed<Field[]>(() => [
    {
        key: 'teacher_id',
        label: 'Teacher',
        type: 'select',
        required: true,
        options: props.teachers.map((item) => ({
            value: item.id,
            label: item.name,
        })),
    },
    {
        key: 'strand_id',
        label: 'Strand',
        type: 'select',
        required: true,
        options: props.strands.map((item) => ({
            value: item.id,
            label: item.code,
        })),
    },
    {
        key: 'school_year',
        label: 'School year',
        type: 'select',
        required: true,
        options: props.schoolYears.map((year) => ({
            value: year.name,
            label: year.name,
        })),
        hint: 'Manage school years in Configuration.',
    },
    {
        key: 'grade_level',
        label: 'Grade level',
        type: 'select',
        emptyLabel: 'Both grades (11 and 12)',
        hint: 'Choose Grade 11, Grade 12, or both grades.',
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
        emptyLabel: 'All sections',
        hint: 'Choose a section or include every section in this strand.',
    },
    {
        key: 'is_active',
        label: 'Active assignment',
        type: 'hidden',
        default: true,
    },
]);
</script>
<template>
    <div class="flex flex-col gap-4">
        <p class="text-sm text-slate-500">
            Connect teachers to the students who may evaluate them. Enrollment,
            school year, grade, and section are checked on every submission.
        </p>
        <FormErrors :errors="statusErrors" />
        <SchoolRecordManager
            :fields="fields"
            :records="records"
            :links="assignments.links"
            :columns="[
                { key: 'teacher_name', label: 'Teacher' },
                { key: 'strand_name', label: 'Strand' },
                { key: 'school_year', label: 'Year' },
                { key: 'grade_name', label: 'Grade' },
                { key: 'section_name', label: 'Section' },
                { key: 'is_active', label: 'Status' },
            ]"
            :store-url="store.url()"
            :update-url="(id) => update.url(id)"
            noun="assignment"
            single-column
        >
            <template #actions="{ record }">
                <Button
                    size="sm"
                    variant="ghost"
                    :disabled="statusBusy"
                    @click="toggleStatus(record)"
                    >{{
                        record.is_active ? 'Deactivate' : 'Reactivate'
                    }}</Button
                >
            </template>
        </SchoolRecordManager>
    </div>
</template>
