<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
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
    periods: {
        id: number;
        name: string;
        status: string;
        is_open: boolean;
        opens_at: string;
        closes_at: string;
        form: { name: string; version: number; description: string | null };
        assignments: { id: number; teacher: string; submitted: boolean }[];
    }[];
}>();
const selected = ref(props.strand?.id ?? '');
const selection = useForm({ student_id: props.selectedStudent?.id ?? '' });
function viewStudent() {
    selection.get(index.url(), { preserveState: false, preserveScroll: true });
}
function display(value: string) {
    return new Date(value).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
</script>
<template>
    <SchoolPage
        :title="adminView ? 'Student evaluations' : 'Teacher evaluations'"
        :description="
            adminView
                ? 'Select a student to see their available evaluations, assigned teachers, and submission status.'
                : 'Evaluate only the teachers assigned to your recorded strand, school year, grade, and section. Each teacher can be evaluated once per period.'
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
        <section v-if="!adminView || selectedStudent" class="school-panel">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="strand" class="school-label"
                        >Assigned strand</label
                    ><select
                        v-if="strand"
                        id="strand"
                        v-model="selected"
                        class="school-input"
                    >
                        <option :value="strand.id">
                            {{ strand.code }} — {{ strand.name }}
                        </option>
                    </select>
                    <p
                        v-else
                        class="text-sm text-amber-700 dark:text-amber-300"
                    >
                        Your strand has not been recorded. Contact your school
                        administrator.
                    </p>
                </div>
                <div>
                    <p class="school-label">Enrollment</p>
                    <p class="text-sm text-slate-500">
                        {{ enrollment.school_year ?? 'School year not assigned'
                        }}<span v-if="enrollment.grade_level">
                            · Grade {{ enrollment.grade_level }}</span
                        ><span v-if="enrollment.section">
                            · {{ enrollment.section }}</span
                        >
                    </p>
                </div>
            </div>
        </section>
        <section
            v-for="period in periods"
            :key="period.id"
            class="school-panel"
        >
            <div class="flex flex-wrap justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ period.name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ period.form.name }} · v{{ period.form.version }}
                    </p>
                </div>
                <span
                    class="text-sm"
                    :class="
                        period.is_open
                            ? 'text-emerald-700 dark:text-emerald-300'
                            : 'text-slate-500'
                    "
                    >{{
                        period.is_open
                            ? 'Open for feedback'
                            : period.status === 'open'
                              ? 'Outside scheduled window'
                              : period.status
                    }}</span
                >
            </div>
            <p class="mt-3 text-xs text-slate-500">
                {{ display(period.opens_at) }} —
                {{ display(period.closes_at) }} (Philippine time)
            </p>
            <p v-if="period.form.description" class="mt-3 text-sm leading-6">
                {{ period.form.description }}
            </p>
            <ul class="mt-5 divide-y dark:divide-slate-800">
                <li
                    v-for="assignment in period.assignments"
                    :key="assignment.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-4"
                >
                    <span class="font-medium">{{ assignment.teacher }}</span
                    ><span
                        v-if="assignment.submitted"
                        class="text-sm text-emerald-700 dark:text-emerald-300"
                        >Submitted</span
                    ><Button v-else-if="period.is_open && !adminView" as-child
                        ><Link
                            :href="
                                create({
                                    period: period.id,
                                    assignment: assignment.id,
                                })
                            "
                            >Evaluate teacher</Link
                        ></Button
                    ><span
                        v-else-if="!period.is_open"
                        class="text-sm text-slate-500"
                        >Not accepting submissions</span
                    >
                    <Button
                        v-if="adminView && period.is_open && selectedStudent"
                        variant="outline"
                        as-child
                    >
                        <Link
                            :href="
                                create(
                                    {
                                        period: period.id,
                                        assignment: assignment.id,
                                    },
                                    {
                                        query: {
                                            student_id: selectedStudent.id,
                                        },
                                    },
                                )
                            "
                            >Preview questionnaire</Link
                        >
                    </Button>
                </li>
            </ul>
            <p
                v-if="!period.assignments.length"
                class="mt-4 text-sm text-slate-500"
            >
                No eligible teachers are assigned to your enrollment for this
                period.
            </p>
        </section>
        <section v-if="adminView && !selectedStudent" class="school-panel">
            <h2 class="font-semibold">Choose a student to begin</h2>
            <p class="mt-2 text-sm text-slate-500">
                The preview uses the student’s recorded enrollment to show the
                evaluations available to them.
            </p>
        </section>
        <section v-else-if="!periods.length" class="school-panel">
            <h2 class="font-semibold">No evaluations available</h2>
            <p class="mt-2 text-sm text-slate-500">
                Evaluations appear when a period is published for the recorded
                school year. Check that the enrollment, teacher assignments,
                form, and evaluation period are configured.
            </p>
        </section></SchoolPage
    >
</template>
