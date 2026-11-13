<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Download, FileText } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import EvaluationResults from '@/components/EvaluationResults.vue';
import FormErrors from '@/components/FormErrors.vue';
import SchoolPage from '@/components/SchoolPage.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index, download, pdf } from '@/routes/reports';
import type { Results } from '@/types/school';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Administration' },
            { title: 'Reports and results', href: index() },
        ],
    },
});

type ReportPeriod = {
    id: number;
    name: string;
    school_year: string;
    status: string;
    opens_at: string;
    closes_at: string;
    evaluations_count: number;
    form: {
        id: number;
        name: string;
        version: number;
        scale_min: number;
        scale_max: number;
    } | null;
};

const props = defineProps<{
    results: Results[];
    periods: ReportPeriod[];
    selectedPeriod: ReportPeriod | null;
    filters: { period: number | null };
    generatedAt: string;
    periodLabel: string;
}>();
const page = usePage();
const canExport = computed(() =>
    ['reports.generate', 'reports.download'].every((permission) =>
        page.props.permissions.includes(permission),
    ),
);
const expandedTeacher = ref<number | null>(null);
watch(
    () => props.selectedPeriod?.id,
    () => {
        expandedTeacher.value = null;
    },
);
function toggleDetails(teacherId: number) {
    expandedTeacher.value =
        expandedTeacher.value === teacherId ? null : teacherId;
}
function statusLabel(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
}
function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('en-PH', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
function statusClass(status: string): string {
    if (status === 'released') {
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300';
    }
    if (status === 'open') {
        return 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300';
    }
    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
}
</script>
<template>
    <SchoolPage
        title="Reports and results"
        description="Choose an evaluation period to review its teacher scores, response counts, and student feedback."
    >
        <template #actions>
            <template v-if="canExport && selectedPeriod">
                <Button as-child variant="outline">
                    <a
                        :href="
                            download.url({
                                query: { period: selectedPeriod.id },
                            })
                        "
                    >
                        <Download aria-hidden="true" class="size-4" />Export CSV
                    </a>
                </Button>
                <Button
                    as-child
                    class="bg-[#185b94] text-white hover:bg-[#104675]"
                >
                    <a
                        :href="
                            pdf.url({ query: { period: selectedPeriod.id } })
                        "
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <FileText aria-hidden="true" class="size-4" />View PDF
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                </Button>
            </template>
        </template>
        <FormErrors :errors="page.props.errors" />
        <template v-if="selectedPeriod">
            <Link
                :href="index()"
                class="inline-flex w-fit items-center gap-2 rounded text-sm font-medium text-[#185b94] outline-none hover:underline focus-visible:ring-2 focus-visible:ring-blue-500 dark:text-blue-300"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />Back to
                evaluation periods
            </Link>
            <section
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
            >
                <header
                    class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"
                >
                    <h2 class="font-semibold">Teacher evaluation summary</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ selectedPeriod.name }} ·
                        {{ selectedPeriod.school_year }}
                    </p>
                </header>
                <div v-if="results.length" class="overflow-x-auto">
                    <table class="school-table">
                        <thead>
                            <tr>
                                <th scope="col">Teacher</th>
                                <th scope="col" class="text-right">
                                    Responses
                                </th>
                                <th scope="col" class="text-right">
                                    Average score
                                </th>
                                <th scope="col">
                                    <span class="sr-only">Details</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                v-for="result in results"
                                :key="result.teacher_id"
                            >
                                <tr>
                                    <th
                                        scope="row"
                                        class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ result.teacher }}
                                    </th>
                                    <td class="text-right tabular-nums">
                                        {{ result.count }}
                                    </td>
                                    <td
                                        class="text-right whitespace-nowrap tabular-nums"
                                    >
                                        <template v-if="result.count">
                                            <span
                                                class="font-semibold text-[#185b94] dark:text-blue-300"
                                                >{{
                                                    result.score.toFixed(2)
                                                }}</span
                                            >
                                            <span
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                / {{ result.scale_max }}</span
                                            >
                                        </template>
                                        <span
                                            v-else
                                            class="text-slate-500 dark:text-slate-400"
                                            >—</span
                                        >
                                    </td>
                                    <td class="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            :aria-expanded="
                                                expandedTeacher ===
                                                result.teacher_id
                                            "
                                            :aria-controls="
                                                'teacher-details-' +
                                                result.teacher_id
                                            "
                                            :aria-label="
                                                (expandedTeacher ===
                                                result.teacher_id
                                                    ? 'Hide'
                                                    : 'View') +
                                                ' details for ' +
                                                result.teacher
                                            "
                                            @click="
                                                toggleDetails(result.teacher_id)
                                            "
                                            >{{
                                                expandedTeacher ===
                                                result.teacher_id
                                                    ? 'Hide details'
                                                    : 'Details'
                                            }}</Button
                                        >
                                    </td>
                                </tr>
                                <tr
                                    v-if="expandedTeacher === result.teacher_id"
                                    :id="'teacher-details-' + result.teacher_id"
                                >
                                    <td
                                        colspan="4"
                                        class="bg-slate-50 dark:bg-slate-950"
                                    >
                                        <EvaluationResults
                                            :results="[result]"
                                            show-teacher
                                        />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-5 py-10 text-center">
                    <h3 class="font-semibold">
                        No submitted evaluations in this period
                    </h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Teacher results will appear here once students submit
                        evaluations.
                    </p>
                </div>
            </section>
            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">
                Generated {{ generatedAt }} (Philippine time). Scores are
                weighted means rounded to two decimal places. Provisional
                results are for administrative review; teachers receive only
                released results that meet the response threshold. Compare
                periods using the same template version and rating scale.
            </p>
        </template>
        <section
            v-else
            class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
        >
            <header
                class="border-b border-slate-100 px-5 py-4 dark:border-slate-800"
            >
                <h2 class="font-semibold">Evaluation periods</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Open a period to see its complete teacher report.
                </p>
            </header>
            <div v-if="periods.length" class="overflow-x-auto">
                <table class="school-table">
                    <thead>
                        <tr>
                            <th scope="col">Evaluation period</th>
                            <th scope="col">School year</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Responses</th>
                            <th scope="col">
                                <span class="sr-only">Report</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="period in periods" :key="period.id">
                            <th
                                scope="row"
                                class="text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                {{ period.name }}
                                <span
                                    class="mt-1 block text-xs font-normal text-slate-500 dark:text-slate-400"
                                    >{{ formatDate(period.opens_at) }} –
                                    {{ formatDate(period.closes_at) }}</span
                                >
                            </th>
                            <td class="whitespace-nowrap">
                                {{ period.school_year }}
                            </td>
                            <td>
                                <span
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                    :class="statusClass(period.status)"
                                    >{{ statusLabel(period.status) }}</span
                                >
                            </td>
                            <td class="text-right tabular-nums">
                                {{ period.evaluations_count }}
                            </td>
                            <td class="text-right">
                                <Button as-child variant="outline" size="sm">
                                    <Link
                                        :href="
                                            index({
                                                query: { period: period.id },
                                            })
                                        "
                                        :aria-label="
                                            'Open report for ' +
                                            period.name +
                                            ', ' +
                                            period.school_year
                                        "
                                        >Open report</Link
                                    >
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="px-5 py-10 text-center">
                <h3 class="font-semibold">No evaluation periods yet</h3>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Create an evaluation period to start collecting and
                    reviewing results.
                </p>
            </div>
        </section>
    </SchoolPage>
</template>
