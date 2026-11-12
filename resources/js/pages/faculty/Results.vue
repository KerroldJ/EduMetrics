<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import EvaluationResults from '@/components/EvaluationResults.vue';
import FormErrors from '@/components/FormErrors.vue';
import SchoolPage from '@/components/SchoolPage.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index } from '@/routes/results';
import type { Results } from '@/types/school';

defineOptions({
    layout: ({ adminView }: { adminView?: boolean }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: adminView ? 'Evaluation portals' : 'My Evaluations' },
            {
                title: adminView ? 'Teacher results' : 'My results',
                href: index(),
            },
        ],
    }),
});
const props = defineProps<{
    adminView: boolean;
    teachers: { id: number; name: string; school_id: string | null }[];
    selectedTeacher: { id: number; name: string } | null;
    results: Results[];
}>();
const selection = useForm({ teacher_id: props.selectedTeacher?.id ?? '' });
function viewTeacher() {
    selection.get(index.url(), { preserveState: false, preserveScroll: true });
}
function print() {
    window.print();
}
</script>
<template>
    <SchoolPage
        :title="adminView ? 'Teacher results' : 'My evaluation results'"
        :description="
            adminView
                ? 'Select a teacher to preview their released evaluation results and feedback.'
                : 'Your released evaluation summaries and student feedback. Results appear only when the school’s minimum response threshold has been met.'
        "
    >
        <template #actions>
            <Button
                v-if="results.length"
                variant="outline"
                class="screen-only"
                @click="print"
                >Print results</Button
            >
        </template>
        <form
            v-if="adminView"
            class="school-panel screen-only"
            @submit.prevent="viewTeacher"
        >
            <FormErrors :errors="selection.errors" />
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-0 flex-1 sm:min-w-64">
                    <label for="preview-teacher" class="school-label"
                        >Teacher</label
                    >
                    <select
                        id="preview-teacher"
                        v-model="selection.teacher_id"
                        class="school-input"
                        required
                    >
                        <option value="">Choose a teacher</option>
                        <option
                            v-for="teacher in teachers"
                            :key="teacher.id"
                            :value="teacher.id"
                        >
                            {{ teacher.name
                            }}{{
                                teacher.school_id
                                    ? ' · ' + teacher.school_id
                                    : ''
                            }}
                        </option>
                    </select>
                </div>
                <Button
                    type="submit"
                    :disabled="!selection.teacher_id || selection.processing"
                    >View results</Button
                >
            </div>
            <p class="mt-3 text-sm text-slate-500">
                This preview shows the results available to the teacher. Use
                Reports and results for provisional administrative reports.
            </p>
        </form>
        <p
            v-if="adminView && selectedTeacher"
            role="status"
            class="text-sm font-medium"
        >
            Viewing released results for {{ selectedTeacher.name }}
        </p>
        <EvaluationResults :results="results" />
        <section v-if="adminView && !selectedTeacher" class="school-panel">
            <h2 class="font-semibold">Choose a teacher to begin</h2>
            <p class="mt-2 text-sm text-slate-500">
                Preview the evaluation summaries and anonymous feedback that
                appear in their account.
            </p>
        </section>
        <section v-else-if="!results.length" class="school-panel">
            <h2 class="font-semibold">No released results available</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                Results appear after the evaluation period has been closed and
                released. Results with fewer than the required responses remain
                private to protect students.
            </p>
        </section>
    </SchoolPage>
</template>
