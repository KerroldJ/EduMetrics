<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Check, GraduationCap } from '@lucide/vue';
import { computed, ref } from 'vue';
import FormErrors from '@/components/FormErrors.vue';
import SchoolPage from '@/components/SchoolPage.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index, create } from '@/routes/evaluations';

defineOptions({
    layout: ({ adminView }: { adminView?: boolean }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: adminView ? 'Evaluation portals' : 'My Evaluations' },
            {
                title: adminView ? 'Student evaluations' : 'Evaluate teachers',
                href: index(),
            },
        ],
    }),
});
type TeacherEvaluation = {
    period_id: number;
    period: string;
    submitted: boolean;
};
type AssignedTeacher = {
    id: number;
    name: string;
    assignment_id: number;
    evaluations: TeacherEvaluation[];
};
const props = defineProps<{
    adminView: boolean;
    students: { id: number; name: string; school_id: string | null }[];
    selectedStudent: { id: number; name: string } | null;
    strand: { id: number; name: string; code: string } | null;
    enrollment: {
        school_year: string | null;
        grade_level: number | null;
        section: string | null;
    };
    teachers: AssignedTeacher[];
}>();
const missingEnrollment = computed(() =>
    [
        !props.strand ? 'strand' : '',
        !props.enrollment.school_year ? 'school year' : '',
    ].filter(Boolean),
);
const selectedPeriods = ref<Partial<Record<number, number>>>({});
const teacherRows = computed(() =>
    props.teachers.map((teacher) => {
        const choices = props.adminView
            ? teacher.evaluations
            : teacher.evaluations.filter((evaluation) => !evaluation.submitted);
        return {
            ...teacher,
            choices,
            activeEvaluation:
                choices.find(
                    (evaluation) =>
                        evaluation.period_id ===
                        selectedPeriods.value[teacher.id],
                ) ??
                choices[0] ??
                null,
            submitted:
                teacher.evaluations.length > 0 &&
                teacher.evaluations.every((evaluation) => evaluation.submitted),
        };
    }),
);
const selection = useForm({ student_id: props.selectedStudent?.id ?? '' });
function viewStudent() {
    selection.get(index.url(), { preserveState: false, preserveScroll: true });
}
function choosePeriod(teacherId: number, event: Event) {
    selectedPeriods.value[teacherId] = Number(
        (event.target as HTMLSelectElement).value,
    );
}
</script>
<template>
    <SchoolPage
        :title="adminView ? 'Student evaluations' : 'My teachers'"
        :description="
            adminView
                ? 'Select a student to view their assigned teachers and preview available questionnaires.'
                : 'Choose a teacher and select Evaluate to open the questionnaire.'
        "
    >
        <form
            v-if="adminView"
            class="school-panel"
            @submit.prevent="viewStudent"
        >
            <FormErrors :errors="selection.errors" />
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-0 flex-1 sm:min-w-64">
                    <label for="preview-student" class="school-label"
                        >Student</label
                    >
                    <select
                        id="preview-student"
                        v-model="selection.student_id"
                        class="school-input"
                        required
                    >
                        <option value="">Choose a student</option>
                        <option
                            v-for="student in students"
                            :key="student.id"
                            :value="student.id"
                        >
                            {{ student.name
                            }}{{
                                student.school_id
                                    ? ' · ' + student.school_id
                                    : ''
                            }}
                        </option>
                    </select>
                </div>
                <Button
                    type="submit"
                    :disabled="!selection.student_id || selection.processing"
                    >View evaluations</Button
                >
            </div>
            <p class="mt-3 text-sm text-slate-500">
                Preview only. Evaluations are submitted by students from their
                own accounts.
            </p>
        </form>
        <p
            v-if="adminView && selectedStudent"
            role="status"
            class="text-sm font-medium"
        >
            Viewing evaluations for {{ selectedStudent.name }}
        </p>
        <section v-if="teachers.length" class="school-panel">
            <header
                class="mb-2 flex flex-wrap items-center justify-between gap-3"
            >
                <h2 class="font-semibold">Assigned teachers</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ teachers.length }}
                    {{ teachers.length === 1 ? 'teacher' : 'teachers' }}
                </p>
            </header>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <li
                    v-for="teacher in teacherRows"
                    :key="teacher.id"
                    class="flex flex-col gap-4 py-5 last:pb-1 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#185b94] dark:bg-blue-950 dark:text-blue-300"
                            ><GraduationCap class="size-5" aria-hidden="true"
                        /></span>
                        <div class="min-w-0">
                            <h3 class="text-sm leading-6 font-semibold">
                                {{ teacher.name }}
                            </h3>
                            <p
                                class="mt-0.5 text-xs"
                                :class="
                                    teacher.submitted
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'text-slate-500 dark:text-slate-400'
                                "
                            >
                                {{
                                    teacher.submitted
                                        ? 'Submitted for all open evaluations'
                                        : teacher.activeEvaluation
                                          ? adminView
                                              ? 'Questionnaire available for preview'
                                              : 'Ready for evaluation'
                                          : 'No evaluation is open right now.'
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-end gap-3 sm:shrink-0">
                        <div v-if="teacher.choices.length > 1">
                            <label
                                :for="'period-' + teacher.id"
                                class="mb-1 block text-xs text-slate-500 dark:text-slate-400"
                                >Evaluation period</label
                            >
                            <select
                                :id="'period-' + teacher.id"
                                :value="teacher.activeEvaluation?.period_id"
                                class="school-input max-w-64"
                                @change="choosePeriod(teacher.id, $event)"
                            >
                                <option
                                    v-for="evaluation in teacher.choices"
                                    :key="evaluation.period_id"
                                    :value="evaluation.period_id"
                                >
                                    {{ evaluation.period }}
                                </option>
                            </select>
                        </div>
                        <Button
                            v-if="teacher.activeEvaluation"
                            as-child
                            class="min-w-28 bg-[#185b94] text-white hover:bg-[#104675] dark:bg-blue-500 dark:hover:bg-blue-600"
                        >
                            <Link
                                :aria-label="
                                    (adminView
                                        ? 'Preview evaluation for '
                                        : 'Evaluate ') + teacher.name
                                "
                                :href="
                                    create(
                                        {
                                            period: teacher.activeEvaluation
                                                .period_id,
                                            assignment: teacher.assignment_id,
                                        },
                                        {
                                            query: adminView
                                                ? {
                                                      student_id:
                                                          selectedStudent?.id,
                                                  }
                                                : {},
                                        },
                                    )
                                "
                                >{{
                                    adminView
                                        ? 'Preview questionnaire'
                                        : 'Evaluate'
                                }}</Link
                            >
                        </Button>
                        <Button
                            v-else
                            variant="outline"
                            class="min-w-28"
                            disabled
                            ><Check
                                v-if="teacher.submitted"
                                class="size-4"
                                aria-hidden="true"
                            />{{
                                teacher.submitted
                                    ? 'Submitted'
                                    : adminView
                                      ? 'Preview questionnaire'
                                      : 'Evaluate'
                            }}</Button
                        >
                    </div>
                </li>
            </ul>
            <p
                v-if="!adminView"
                class="mt-5 border-t border-slate-100 pt-4 text-xs leading-5 text-slate-500 dark:border-slate-800 dark:text-slate-400"
            >
                You can submit one evaluation per teacher in each evaluation
                period.
            </p>
        </section>
        <section v-if="adminView && !selectedStudent" class="school-panel">
            <h2 class="font-semibold">Choose a student to begin</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                The preview uses the student’s recorded enrollment to show their
                assigned teachers.
            </p>
        </section>
        <section v-else-if="!teachers.length" class="school-panel">
            <h2 class="font-semibold">
                {{
                    missingEnrollment.length
                        ? 'Enrollment incomplete'
                        : 'No teachers assigned'
                }}
            </h2>
            <p
                v-if="missingEnrollment.length"
                class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
            >
                {{
                    adminView
                        ? 'This student’s enrollment is missing its'
                        : 'Your enrollment is missing its'
                }}
                {{ missingEnrollment.join(' and ') }}.
                {{
                    adminView
                        ? 'Update the student’s enrollment in User Management to show their assigned teachers.'
                        : 'Contact your school administrator to update it before you evaluate teachers.'
                }}
            </p>
            <p
                v-else
                class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400"
            >
                {{
                    adminView
                        ? 'No active teachers match this student’s enrollment. Check their teacher assignments.'
                        : 'No teachers have been assigned to your enrollment yet. Contact your school administrator.'
                }}
            </p>
        </section>
    </SchoolPage>
</template>
