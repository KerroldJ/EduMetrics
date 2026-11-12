<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import SchoolPage from '@/components/SchoolPage.vue';
import EvaluationResults from '@/components/EvaluationResults.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index, download } from '@/routes/reports';
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
const props = defineProps<{
    results: Results[];
    periods: { id: number; name: string; school_year: string }[];
    teachers: { id: number; name: string }[];
    filters: { period?: number; teacher?: number };
}>();
const filters = reactive({
    period: props.filters.period ?? '',
    teacher: props.filters.teacher ?? '',
});
function apply() {
    router.get(index.url(), filters, { preserveState: true, replace: true });
}
function print() {
    window.print();
}
</script>
<template>
    <SchoolPage
        title="Evaluation reports"
        description="Review computed teacher scores and response counts. Provisional results are visible to Admin; teachers receive only released results that meet the response threshold."
        ><template #actions
            ><Button variant="outline" class="screen-only" @click="print"
                >Print report</Button
            ><Button as-child class="screen-only"
                ><a :href="download.url({ query: filters })"
                    >Download CSV</a
                ></Button
            ></template
        >
        <form
            class="school-panel screen-only grid items-end gap-4 sm:grid-cols-[1fr_1fr_auto]"
            @submit.prevent="apply"
        >
            <div>
                <label for="period-filter" class="school-label"
                    >Evaluation period</label
                ><select
                    id="period-filter"
                    v-model="filters.period"
                    class="school-input"
                >
                    <option value="">All periods</option>
                    <option
                        v-for="period in periods"
                        :key="period.id"
                        :value="period.id"
                    >
                        {{ period.name }} ({{ period.school_year }})
                    </option>
                </select>
            </div>
            <div>
                <label for="teacher-filter" class="school-label">Teacher</label
                ><select
                    id="teacher-filter"
                    v-model="filters.teacher"
                    class="school-input"
                >
                    <option value="">All teachers</option>
                    <option
                        v-for="teacher in teachers"
                        :key="teacher.id"
                        :value="teacher.id"
                    >
                        {{ teacher.name }}
                    </option>
                </select>
            </div>
            <Button type="submit">Apply filters</Button>
        </form>
        <p class="text-xs text-slate-500">
            Generated
            {{
                new Date().toLocaleString('en-PH', { timeZone: 'Asia/Manila' })
            }}
            (Philippine time). Scores are weighted means rounded to two decimal
            places. Compare periods using the same instrument and scale.
        </p>
        <section v-if="results.length" class="school-panel overflow-x-auto">
            <table class="school-table">
                <thead>
                    <tr>
                        <th>Teacher</th>
                        <th>Period</th>
                        <th>Responses</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th class="screen-only">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="result in results"
                        :key="result.teacher_id + '-' + result.period_id"
                    >
                        <td>{{ result.teacher }}</td>
                        <td>
                            {{ result.period
                            }}<span class="block text-xs text-slate-500">{{
                                result.school_year
                            }}</span>
                        </td>
                        <td>{{ result.count }}</td>
                        <td>
                            {{ result.score.toFixed(2) }} /
                            {{ result.scale_max }}
                        </td>
                        <td>
                            {{
                                result.status === 'released'
                                    ? 'Released'
                                    : 'Provisional'
                            }}
                        </td>
                        <td class="screen-only">
                            <Link
                                :href="
                                    index({
                                        query: {
                                            period: result.period_id,
                                            teacher: result.teacher_id,
                                        },
                                    })
                                "
                                class="text-blue-700 underline dark:text-blue-300"
                                >View details</Link
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
        <EvaluationResults
            v-if="filters.period && filters.teacher"
            :results="results"
            show-teacher
        />
        <section v-if="!results.length" class="school-panel">
            <h2 class="font-semibold">
                No submitted evaluations match these filters
            </h2>
            <p class="mt-2 text-sm text-slate-500">
                Once students submit evaluations, scores and reports become
                available here.
            </p>
        </section></SchoolPage
    >
</template>
