<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import EvaluationResults from '@/components/EvaluationResults.vue';
import SchoolPage from '@/components/SchoolPage.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useInitials } from '@/composables/useInitials';
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
    teachers: {
        id: number;
        name: string;
        school_id: string | null;
        avatar: string | null;
        evaluated_students_count: number;
        assignments: {
            id: number;
            strand: { code: string; name: string };
            school_year: string;
            grade_level: number | null;
            section: string | null;
        }[];
    }[];
    selectedTeacher: { id: number; name: string; avatar: string | null } | null;
    results: Results[];
}>();
const { getInitials } = useInitials();
const search = ref('');
const filteredTeachers = computed(() => {
    const query = search.value.trim().toLocaleLowerCase();

    return props.teachers.filter((teacher) =>
        [teacher.name, teacher.school_id ?? ''].some((value) =>
            value.toLocaleLowerCase().includes(query),
        ),
    );
});
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
                v-if="adminView && selectedTeacher"
                as-child
                variant="outline"
                class="screen-only"
            >
                <Link :href="index()">
                    <ArrowLeft aria-hidden="true" /> All teachers
                </Link>
            </Button>
            <Button
                v-if="results.length"
                variant="outline"
                class="screen-only"
                @click="print"
                >Print results</Button
            >
        </template>
        <section
            v-if="adminView && !selectedTeacher"
            class="screen-only flex flex-col gap-5"
            aria-label="Teacher profiles"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p
                    role="status"
                    class="text-sm text-slate-500 dark:text-slate-400"
                >
                    {{ filteredTeachers.length }} of
                    {{ teachers.length }} teachers
                </p>
                <div v-if="teachers.length" class="relative w-full sm:w-80">
                    <label for="teacher-search" class="sr-only"
                        >Search teachers by name or school ID</label
                    >
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        aria-hidden="true"
                    />
                    <Input
                        id="teacher-search"
                        v-model="search"
                        type="search"
                        placeholder="Search name or school ID…"
                        class="bg-white pl-9 dark:bg-slate-900"
                    />
                </div>
            </div>
            <div
                v-if="filteredTeachers.length"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <article
                    v-for="teacher in filteredTeachers"
                    :key="teacher.id"
                    class="school-panel flex min-w-0 flex-col gap-3 p-4"
                >
                    <div class="flex flex-col items-center gap-2 text-center">
                        <Avatar
                            class="size-20 shrink-0 rounded-full border-2 border-white shadow-sm ring-1 ring-slate-200 dark:border-slate-900 dark:ring-slate-700"
                            aria-hidden="true"
                        >
                            <AvatarImage
                                v-if="teacher.avatar"
                                :src="teacher.avatar"
                                alt=""
                                class="object-cover"
                            />
                            <AvatarFallback
                                class="rounded-full bg-blue-50 text-2xl font-semibold text-[#185b94] dark:bg-blue-950 dark:text-blue-200"
                            >
                                {{ getInitials(teacher.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="w-full min-w-0">
                            <h2 class="font-semibold wrap-break-word">
                                {{ teacher.name }}
                            </h2>
                            <p
                                class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                            >
                                ID · {{ teacher.school_id || 'Not assigned' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-1 flex-col text-center">
                        <div>
                            <h3 class="sr-only">Strand and section</h3>
                            <ul
                                v-if="teacher.assignments.length"
                                class="flex flex-col gap-2"
                            >
                                <li
                                    v-for="assignment in teacher.assignments"
                                    :key="assignment.id"
                                    class="text-sm"
                                >
                                    <p class="font-medium wrap-break-word">
                                        <abbr
                                            :title="assignment.strand.name"
                                            class="no-underline"
                                            >{{ assignment.strand.code }}</abbr
                                        >
                                        ·
                                        {{
                                            assignment.section
                                                ? 'Section ' +
                                                  assignment.section
                                                : 'All sections'
                                        }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ assignment.school_year }} ·
                                        {{
                                            assignment.grade_level
                                                ? 'Grade ' +
                                                  assignment.grade_level
                                                : 'Grades 11–12'
                                        }}
                                    </p>
                                </li>
                            </ul>
                            <p
                                v-else
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                No active teaching assignments
                            </p>
                        </div>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-950"
                    >
                        <div>
                            <h3 class="text-xs">Students evaluated</h3>
                        </div>
                        <p
                            class="text-lg font-semibold text-[#185b94] tabular-nums dark:text-blue-300"
                            title="Unique students across all evaluation periods"
                        >
                            {{ teacher.evaluated_students_count }}
                        </p>
                    </div>
                    <Button
                        as-child
                        variant="outline"
                        size="sm"
                        class="w-full justify-between"
                    >
                        <Link
                            :href="index({ query: { teacher_id: teacher.id } })"
                            :aria-label="'View results for ' + teacher.name"
                        >
                            View results
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                    </Button>
                </article>
            </div>
            <div v-else class="school-panel py-10 text-center">
                <h2 class="font-semibold">
                    {{
                        teachers.length
                            ? 'No teachers found'
                            : 'No teacher profiles yet'
                    }}
                </h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    {{
                        teachers.length
                            ? 'Try another name or school ID.'
                            : 'Teacher profiles appear when teacher accounts are created.'
                    }}
                </p>
                <Button
                    v-if="search"
                    variant="outline"
                    class="mt-4"
                    @click="search = ''"
                    >Clear search</Button
                >
            </div>
            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">
                Profiles open the released results available to each teacher.
                Counts show unique students across all evaluation periods. Use
                Reports and results for provisional administrative reports.
            </p>
        </section>
        <section
            v-if="adminView && selectedTeacher"
            class="school-panel flex items-center gap-3"
            aria-label="Selected teacher"
        >
            <Avatar class="size-12 rounded-xl" aria-hidden="true">
                <AvatarImage
                    v-if="selectedTeacher.avatar"
                    :src="selectedTeacher.avatar"
                    alt=""
                    class="object-cover"
                />
                <AvatarFallback
                    class="rounded-xl bg-blue-50 font-semibold text-[#185b94] dark:bg-blue-950 dark:text-blue-200"
                    >{{ getInitials(selectedTeacher.name) }}</AvatarFallback
                >
            </Avatar>
            <div class="min-w-0">
                <h2 class="font-semibold wrap-break-word">
                    {{ selectedTeacher.name }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Released evaluation results
                </p>
            </div>
        </section>
        <EvaluationResults :results="results" />
        <section
            v-if="(!adminView || selectedTeacher) && !results.length"
            class="school-panel"
        >
            <h2 class="font-semibold">No released results available</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                Results appear after the evaluation period has been closed and
                released. Results with fewer than the required responses remain
                private to protect students.
            </p>
        </section>
    </SchoolPage>
</template>
