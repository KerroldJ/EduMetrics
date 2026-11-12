<script setup lang="ts">
import type { Results } from '@/types/school';
defineProps<{ results: Results[]; showTeacher?: boolean }>();
function width(score: number, max: number) {
    return Math.max(0, Math.min(100, (score / max) * 100)) + '%';
}
</script>
<template>
    <div class="grid gap-5">
        <section
            v-for="result in results"
            :key="result.teacher_id + '-' + result.period_id"
            class="school-panel break-inside-avoid"
        >
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ showTeacher ? result.teacher : result.period }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ showTeacher ? result.period : result.school_year }} ·
                        {{ result.status }}
                    </p>
                </div>
                <div class="text-right">
                    <p
                        class="text-3xl font-semibold text-[#185b94] dark:text-blue-300"
                    >
                        {{ result.score.toFixed(2) }}
                        <span class="text-sm font-normal text-slate-500"
                            >/ {{ result.scale_max }}</span
                        >
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ result.count }} submitted evaluation(s)
                    </p>
                </div>
            </header>
            <div
                class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                role="img"
                :aria-label="
                    'Average score ' +
                    result.score.toFixed(2) +
                    ' out of ' +
                    result.scale_max
                "
            >
                <div
                    class="h-full rounded-full bg-[#185b94] dark:bg-blue-400"
                    :style="{ width: width(result.score, result.scale_max) }"
                />
            </div>
            <p v-if="result.performance_level" class="mt-3 text-sm font-medium">
                {{ result.performance_level }}
            </p>
            <div v-if="result.criteria.length" class="mt-6">
                <h3 class="mb-3 text-sm font-semibold">Question averages</h3>
                <div
                    v-for="(criterion, index) in result.criteria"
                    :key="index"
                    class="mb-4"
                >
                    <div class="mb-2 flex justify-between gap-4 text-sm">
                        <p>{{ criterion.prompt }}</p>
                        <span class="shrink-0 font-medium">{{
                            criterion.average.toFixed(2)
                        }}</span>
                    </div>
                    <div
                        class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-800"
                    >
                        <div
                            class="h-full rounded-full bg-blue-400"
                            :style="{
                                width: width(
                                    criterion.average,
                                    result.scale_max,
                                ),
                            }"
                        />
                    </div>
                </div>
            </div>
            <div v-if="result.comments.length" class="mt-6">
                <h3 class="text-sm font-semibold">Anonymous feedback</h3>
                <ul class="mt-3 grid gap-3">
                    <li
                        v-for="(comment, index) in result.comments"
                        :key="index"
                        class="rounded-lg bg-slate-50 p-3 text-sm leading-6 whitespace-pre-wrap dark:bg-slate-950"
                    >
                        {{ comment }}
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
