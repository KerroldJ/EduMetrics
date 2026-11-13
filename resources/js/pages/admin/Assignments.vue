<script setup lang="ts">
import AssignmentManager from '@/components/AssignmentManager.vue';
import SchoolPage from '@/components/SchoolPage.vue';
import { dashboard } from '@/routes';
import { index } from '@/routes/strands';
import type { PageLink, RecordData } from '@/types/school';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Administration' },
            {
                title: 'Configuration',
                href: index({ query: { tab: 'assignments' } }),
            },
        ],
    },
});
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
</script>
<template>
    <SchoolPage title="Configuration" description="Manage teacher assignments.">
        <AssignmentManager v-bind="props" />
    </SchoolPage>
</template>
