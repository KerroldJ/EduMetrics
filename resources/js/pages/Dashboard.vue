<script setup lang="ts">
import { dashboard } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SchoolPage from '@/components/SchoolPage.vue';
import { Button } from '@/components/ui/button';
import { useSchoolNavigation } from '@/composables/useSchoolNavigation';
import { edit } from '@/routes/profile';
import { index as evaluations } from '@/routes/evaluations';
import { index as results } from '@/routes/results';
import { index as reports } from '@/routes/reports';
import { index as strands } from '@/routes/strands';
import { index as assignments } from '@/routes/assignments';
import { index as forms } from '@/routes/forms';
import { index as periods } from '@/routes/periods';
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

type Overview = {
    teacherCount: number;
    studentCount: number;
    accountCount: number;
    verifiedCount: number;
    recentAccounts: {
        id: number;
        name: string;
        email: string;
        verified: boolean;
        roles: string[];
    }[];
};
const props = defineProps<{
    audience: 'admin' | 'teacher' | 'student' | 'member';
    roleLabel: string;
    overview: Overview | null;
    activity: Record<string, number>;
}>();
const page = usePage();
const navigation = useSchoolNavigation();
const modules = computed(() =>
    navigation.value.filter((item) => item.title !== 'Dashboard'),
);
const stats = computed(() =>
    props.overview
        ? [
              { label: 'Teacher accounts', value: props.overview.teacherCount },
              { label: 'Student accounts', value: props.overview.studentCount },
              {
                  label: 'Open evaluation periods',
                  value: props.activity.openPeriodCount,
              },
              {
                  label: 'Submitted evaluations',
                  value: props.activity.submissionCount,
              },
          ]
        : props.audience === 'student'
          ? [
                {
                    label: 'Evaluations to complete',
                    value: props.activity.pendingCount,
                },
                {
                    label: 'Evaluations submitted',
                    value: props.activity.submittedCount,
                },
            ]
          : props.audience === 'teacher'
            ? [
                  {
                      label: 'Released evaluation periods',
                      value: props.activity.releasedCount,
                  },
              ]
            : [],
);
const preparation = computed(() => [
    { title: 'Strands', count: props.activity.strandCount, url: strands() },
    {
        title: 'Teacher assignments',
        count: props.activity.assignmentCount,
        url: assignments(),
    },
    {
        title: 'Evaluation forms',
        count: props.activity.formCount,
        url: forms(),
    },
    {
        title: 'Open periods',
        count: props.activity.openPeriodCount,
        url: periods(),
    },
]);
const action = computed(() =>
    props.audience === 'admin'
        ? { title: 'Review evaluation reports', href: reports() }
        : props.audience === 'student'
          ? { title: 'Evaluate your teachers', href: evaluations() }
          : props.audience === 'teacher'
            ? { title: 'View my results', href: results() }
            : null,
);
</script>
<template>
    <SchoolPage
        :title="
            audience === 'admin'
                ? 'School overview'
                : audience === 'student'
                  ? 'Your evaluations'
                  : audience === 'teacher'
                    ? 'Your teaching feedback'
                    : 'Welcome to EduMetric'
        "
        description="Teacher performance evaluation for your school community."
        ><template #actions
            ><Button variant="outline" as-child
                ><Link :href="edit()">Account settings</Link></Button
            ></template
        >
        <section
            class="flex flex-wrap items-center justify-between gap-5 rounded-2xl bg-[#102f50] p-6 text-white sm:p-8"
        >
            <div>
                <p class="text-sm text-blue-100">{{ roleLabel }}</p>
                <h2 class="mt-2 text-2xl font-semibold">
                    Welcome back, {{ page.props.auth.user.name }}
                </h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-blue-100/85">
                    {{
                        audience === 'admin'
                            ? 'Manage school records, prepare your evaluation period, and review feedback as students submit it.'
                            : audience === 'student'
                              ? 'Review your assigned teachers and complete each evaluation before its deadline.'
                              : audience === 'teacher'
                                ? 'Review your released results, question averages, and anonymous student feedback.'
                                : 'Contact the school administrator to have your role and school enrollment confirmed.'
                    }}
                </p>
            </div>
            <Button
                v-if="action"
                as-child
                class="bg-white text-[#102f50] hover:bg-blue-50"
                ><Link :href="action.href">{{ action.title }}</Link></Button
            >
        </section>
        <div
            v-if="stats.length"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <section
                v-for="stat in stats"
                :key="stat.label"
                class="school-panel"
            >
                <h2 class="text-sm text-slate-500">{{ stat.label }}</h2>
                <p class="mt-3 text-3xl font-semibold">
                    {{ (stat.value ?? 0).toLocaleString() }}
                </p>
            </section>
        </div>
        <section v-if="modules.length" class="school-panel">
            <h2 class="font-semibold">Your workspace</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="item in modules"
                    :key="item.title"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg border border-slate-200 px-4 py-4 text-sm font-medium transition-colors hover:border-blue-300 hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none dark:border-slate-700 dark:hover:bg-slate-800"
                    ><component
                        :is="item.icon"
                        class="size-5 text-[#376d9d] dark:text-blue-300"
                        aria-hidden="true"
                    />{{ item.title }}</Link
                >
            </div>
        </section>
        <div
            v-if="overview"
            class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(280px,1fr)]"
        >
            <section class="school-panel min-w-0">
                <div class="flex flex-wrap justify-between gap-2">
                    <h2 class="font-semibold">Recent school accounts</h2>
                    <p class="text-xs text-slate-500">
                        {{ overview.accountCount }} accounts ·
                        {{ overview.verifiedCount }} verified emails
                    </p>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="school-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Role</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="account in overview.recentAccounts"
                                :key="account.id"
                            >
                                <td>
                                    {{ account.name }}
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ account.email }}
                                    </p>
                                </td>
                                <td>
                                    {{
                                        account.roles.join(', ') || 'Unassigned'
                                    }}
                                </td>
                                <td>
                                    {{
                                        account.verified
                                            ? 'Verified'
                                            : 'Unverified'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="school-panel">
                <h2 class="font-semibold">Evaluation preparation</h2>
                <p class="mt-2 text-sm text-slate-500">
                    Complete each part before opening a period.
                </p>
                <ul class="mt-4 divide-y dark:divide-slate-800">
                    <li
                        v-for="item in preparation"
                        :key="item.title"
                        class="py-3"
                    >
                        <Link
                            :href="item.url"
                            class="flex items-center justify-between gap-3 text-sm"
                            ><span class="font-medium">{{ item.title }}</span
                            ><span
                                :class="
                                    item.count
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'text-amber-700 dark:text-amber-300'
                                "
                                >{{
                                    item.count
                                        ? item.count + ' available'
                                        : 'Set up'
                                }}</span
                            ></Link
                        >
                    </li>
                </ul>
            </section>
        </div>
        <section v-else class="school-panel">
            <h2 class="font-semibold">
                {{
                    audience === 'teacher'
                        ? 'When will my results appear?'
                        : audience === 'student'
                          ? 'How do I get started?'
                          : 'Your access is being prepared'
                }}
            </h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
                {{
                    audience === 'teacher'
                        ? 'Your administrator must close and release the evaluation period. Groups with fewer than the required responses remain private.'
                        : audience === 'student'
                          ? 'Open Evaluate teachers to select your assigned strand and see available evaluations. If your enrollment or teachers are missing, contact your school administrator.'
                          : 'Your administrator will assign your role. You can keep your account details current using Account settings.'
                }}
            </p>
        </section></SchoolPage
    >
</template>
