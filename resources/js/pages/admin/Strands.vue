<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { ref } from 'vue';
import AssignmentManager from '@/components/AssignmentManager.vue';
import FormErrors from '@/components/FormErrors.vue';
import SchoolPage from '@/components/SchoolPage.vue';
import SchoolRecordManager from '@/components/SchoolRecordManager.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import {
    store as storeSection,
    update as updateSection,
    destroy as destroySection,
} from '@/routes/sections';
import { index, store, update } from '@/routes/strands';
import {
    store as storeSchoolYear,
    update as updateSchoolYear,
    destroy as destroySchoolYear,
} from '@/routes/school-years';
import type { Field, PageLink, RecordData } from '@/types/school';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Administration' },
            { title: 'Configuration', href: index() },
        ],
    },
});
type ConfigurationTab = 'strands' | 'sections' | 'school-years' | 'assignments';

defineProps<{
    strands?: RecordData[];
    sections?: RecordData[];
    schoolYears?: RecordData[];
    activeTab: ConfigurationTab;
    availableTabs: ConfigurationTab[];
    assignments?: {
        data: (RecordData & {
            teacher: { name: string };
            strand: { code: string };
        })[];
        links: PageLink[];
    };
    assignmentOptions?: {
        teachers: { id: number; name: string }[];
        strands: { id: number; code: string; name: string }[];
        sections: { id: number; name: string }[];
        schoolYears: { id: number; name: string }[];
    };
}>();
const fields: Field[] = [
    {
        key: 'code',
        label: 'Strand code',
        required: true,
        hint: 'For example STEM, ABM, HUMSS, or TVL.',
    },
    { key: 'name', label: 'Strand name', required: true },
    { key: 'is_active', label: 'Active strand', type: 'checkbox' },
];
const sectionFields: Field[] = [
    {
        key: 'name',
        label: 'Section name',
        required: true,
        hint: 'Use the section name recorded by your school.',
    },
];
const schoolYearFields: Field[] = [
    {
        key: 'name',
        label: 'School year',
        type: 'school-year',
        required: true,
    },
];
const deleteErrors = ref<Record<string, string>>({});
const deleting = ref(false);
function changeTab(value: string | number) {
    if (
        value !== 'strands' &&
        value !== 'sections' &&
        value !== 'school-years' &&
        value !== 'assignments'
    )
        return;
    deleteErrors.value = {};
    router.get(
        index.url(),
        { tab: value },
        { preserveScroll: true, preserveState: true },
    );
}
function deleteRecord(record: RecordData, noun: string, url: string) {
    if (
        !window.confirm(
            `Delete ${noun} ${String(record.name)}? This cannot be undone.`,
        )
    )
        return;
    deleteErrors.value = {};
    deleting.value = true;
    router.delete(url, {
        preserveScroll: true,
        onError: (errors) => {
            deleteErrors.value = errors;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}
</script>
<template>
    <SchoolPage
        title="Configuration"
        description="Manage school years, strands, sections, and teacher assignments for student enrollment and evaluation periods."
    >
        <TabsRoot
            :model-value="activeTab"
            class="flex flex-col gap-5"
            @update:model-value="changeTab"
        >
            <TabsList
                class="flex flex-wrap gap-x-6 border-b border-slate-200 dark:border-slate-800"
                aria-label="School configuration"
            >
                <TabsTrigger
                    v-if="availableTabs.includes('school-years')"
                    value="school-years"
                    class="border-b-2 border-transparent px-1 py-3 text-sm font-medium text-slate-500 outline-none focus-visible:ring-2 focus-visible:ring-blue-500 data-[state=active]:border-blue-700 data-[state=active]:text-blue-800 dark:data-[state=active]:text-blue-300"
                    >School Years</TabsTrigger
                >
                <TabsTrigger
                    v-if="availableTabs.includes('strands')"
                    value="strands"
                    class="border-b-2 border-transparent px-1 py-3 text-sm font-medium text-slate-500 outline-none focus-visible:ring-2 focus-visible:ring-blue-500 data-[state=active]:border-blue-700 data-[state=active]:text-blue-800 dark:data-[state=active]:text-blue-300"
                    >Strands</TabsTrigger
                >
                <TabsTrigger
                    v-if="availableTabs.includes('sections')"
                    value="sections"
                    class="border-b-2 border-transparent px-1 py-3 text-sm font-medium text-slate-500 outline-none focus-visible:ring-2 focus-visible:ring-blue-500 data-[state=active]:border-blue-700 data-[state=active]:text-blue-800 dark:data-[state=active]:text-blue-300"
                    >Sections</TabsTrigger
                >
                <TabsTrigger
                    v-if="availableTabs.includes('assignments')"
                    value="assignments"
                    class="border-b-2 border-transparent px-1 py-3 text-sm font-medium text-slate-500 outline-none focus-visible:ring-2 focus-visible:ring-blue-500 data-[state=active]:border-blue-700 data-[state=active]:text-blue-800 dark:data-[state=active]:text-blue-300"
                    >Assignments</TabsTrigger
                >
            </TabsList>
            <TabsContent
                v-if="strands && availableTabs.includes('strands')"
                value="strands"
                class="outline-none"
            >
                <SchoolRecordManager
                    :fields="fields"
                    :records="strands"
                    :columns="[
                        { key: 'code', label: 'Code' },
                        { key: 'name', label: 'Strand' },
                        { key: 'assignments_count', label: 'Assignments' },
                        { key: 'is_active', label: 'Status' },
                    ]"
                    :store-url="store.url()"
                    :update-url="(id) => update.url(id)"
                    noun="strand"
                    single-column
                />
            </TabsContent>
            <TabsContent
                v-if="sections && availableTabs.includes('sections')"
                value="sections"
                class="outline-none"
            >
                <FormErrors :errors="deleteErrors" />
                <p class="mb-4 text-sm text-slate-500">
                    Renaming a section updates its linked accounts and teacher
                    assignments. A section must be unused before it can be
                    deleted.
                </p>
                <SchoolRecordManager
                    :fields="sectionFields"
                    :records="sections"
                    :columns="[
                        { key: 'name', label: 'Section' },
                        { key: 'users_count', label: 'Accounts' },
                        { key: 'assignments_count', label: 'Assignments' },
                    ]"
                    :store-url="storeSection.url()"
                    :update-url="(id) => updateSection.url(id)"
                    noun="section"
                    single-column
                >
                    <template #actions="{ record }">
                        <Button
                            size="sm"
                            variant="outline"
                            class="text-red-700 dark:text-red-300"
                            :disabled="deleting"
                            @click="
                                deleteRecord(
                                    record,
                                    'section',
                                    destroySection.url(record.id),
                                )
                            "
                            >Delete</Button
                        >
                    </template>
                </SchoolRecordManager>
            </TabsContent>
            <TabsContent
                v-if="schoolYears && availableTabs.includes('school-years')"
                value="school-years"
                class="outline-none"
            >
                <FormErrors :errors="deleteErrors" />
                <p class="mb-4 text-sm text-slate-500">
                    Renaming a school year updates linked enrollment, teacher
                    assignments, and evaluation periods. A year must be unused
                    before it can be deleted.
                </p>
                <SchoolRecordManager
                    :fields="schoolYearFields"
                    :records="schoolYears"
                    :columns="[
                        { key: 'name', label: 'School year' },
                        { key: 'users_count', label: 'Accounts' },
                        { key: 'assignments_count', label: 'Assignments' },
                        { key: 'periods_count', label: 'Evaluation periods' },
                    ]"
                    :store-url="storeSchoolYear.url()"
                    :update-url="(id) => updateSchoolYear.url(id)"
                    noun="school year"
                    single-column
                >
                    <template #actions="{ record }">
                        <Button
                            size="sm"
                            variant="outline"
                            class="text-red-700 dark:text-red-300"
                            :disabled="deleting"
                            @click="
                                deleteRecord(
                                    record,
                                    'school year',
                                    destroySchoolYear.url(record.id),
                                )
                            "
                            >Delete</Button
                        >
                    </template>
                </SchoolRecordManager>
            </TabsContent>
            <TabsContent
                v-if="
                    assignments &&
                    assignmentOptions &&
                    availableTabs.includes('assignments')
                "
                value="assignments"
                class="outline-none"
            >
                <AssignmentManager
                    :assignments="assignments"
                    v-bind="assignmentOptions"
                />
            </TabsContent>
        </TabsRoot>
    </SchoolPage>
</template>
