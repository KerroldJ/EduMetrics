<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    AlertCircle,
    Calendar,
    CheckCircle2,
    Clock,
    Plus,
    Search,
} from '@lucide/vue';
import SchoolFormDialog from '@/components/SchoolFormDialog.vue';
import SchoolPage from '@/components/SchoolPage.vue';
import FormErrors from '@/components/FormErrors.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index, store, update, transition } from '@/routes/periods';
import { index as templateIndex } from '@/routes/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Evaluation Management' },
            { title: 'Evaluation periods', href: index() },
        ],
    },
});
type Period = {
    id: number;
    name: string;
    school_year: string;
    evaluation_form_id: number | null;
    opens_at: string;
    closes_at: string;
    status: string;
    minimum_responses: number;
    evaluations_count: number;
    form: { name: string; version: number } | null;
};
type TemplateVersion = {
    id: number;
    name: string;
    version: number;
    description: string | null;
    scale_min: number;
    scale_max: number;
    is_active: boolean;
    criteria: { id: number; prompt: string; weight: string }[];
    performance_bands: { label: string; min: number; max: number }[] | null;
};
const props = defineProps<{
    periods: Period[];
    forms: TemplateVersion[];
    schoolYears: { id: number; name: string }[];
}>();
const editing = ref<number | null>(null);
const dialogOpen = ref(false);
const busy = ref(false);
const transitionErrors = ref<Record<string, string>>({});

const opensDate = ref('');
const opensTime = ref('08:00');
const closesDate = ref('');
const closesTime = ref('17:00');

const standardTimes = [
    { value: '06:00', label: '6:00 AM' },
    { value: '07:00', label: '7:00 AM' },
    { value: '07:30', label: '7:30 AM' },
    { value: '08:00', label: '8:00 AM (Start of classes)' },
    { value: '08:30', label: '8:30 AM' },
    { value: '09:00', label: '9:00 AM' },
    { value: '10:00', label: '10:00 AM' },
    { value: '11:00', label: '11:00 AM' },
    { value: '12:00', label: '12:00 PM (Noon)' },
    { value: '13:00', label: '1:00 PM' },
    { value: '14:00', label: '2:00 PM' },
    { value: '15:00', label: '3:00 PM' },
    { value: '16:00', label: '4:00 PM' },
    { value: '17:00', label: '5:00 PM (End of day)' },
    { value: '18:00', label: '6:00 PM' },
    { value: '19:00', label: '7:00 PM' },
    { value: '20:00', label: '8:00 PM' },
    { value: '21:00', label: '9:00 PM' },
    { value: '22:00', label: '10:00 PM' },
    { value: '23:59', label: '11:59 PM (Midnight)' },
];

function formatTimeLabel(val: string): string {
    const [h, m] = val.split(':').map(Number);
    if (isNaN(h) || isNaN(m)) return val;
    const period = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 || 12;
    return `${hour12}:${String(m).padStart(2, '0')} ${period}`;
}

const opensTimeOptions = computed(() => {
    const list = [...standardTimes];
    if (opensTime.value && !list.some((t) => t.value === opensTime.value)) {
        list.push({
            value: opensTime.value,
            label: formatTimeLabel(opensTime.value),
        });
        list.sort((a, b) => a.value.localeCompare(b.value));
    }
    return list;
});

const closesTimeOptions = computed(() => {
    const list = [...standardTimes];
    if (closesTime.value && !list.some((t) => t.value === closesTime.value)) {
        list.push({
            value: closesTime.value,
            label: formatTimeLabel(closesTime.value),
        });
        list.sort((a, b) => a.value.localeCompare(b.value));
    }
    return list;
});

const form = useForm({
    name: '',
    school_year: '',
    evaluation_form_id: '' as number | string,
    opens_at: '',
    closes_at: '',
    minimum_responses: 3,
});

watch([opensDate, opensTime], ([d, t]: [string, string]) => {
    form.opens_at = d ? `${d}T${t || '08:00'}` : '';
});

watch([closesDate, closesTime], ([d, t]: [string, string]) => {
    form.closes_at = d ? `${d}T${t || '17:00'}` : '';
});

const availableVersions = computed(() =>
    props.forms.filter(
        (item) =>
            item.is_active ||
            props.periods.some(
                (period) =>
                    period.id === editing.value &&
                    period.evaluation_form_id === item.id,
            ),
    ),
);
function local(value: string | null | undefined): string {
    if (!value) return '';
    try {
        const date = new Date(value);
        if (isNaN(date.getTime())) return '';
        return new Date(date.getTime() + 8 * 3600000)
            .toISOString()
            .slice(0, 16);
    } catch {
        return '';
    }
}
function display(value: string) {
    return new Date(value).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
function formatPhtDisplay(value: string): string {
    if (!value) return '';
    try {
        const iso =
            value.includes('+') || value.includes('Z')
                ? value
                : `${value}+08:00`;
        const date = new Date(iso);
        if (isNaN(date.getTime())) return '';
        return date.toLocaleString('en-US', {
            timeZone: 'Asia/Manila',
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    } catch {
        return '';
    }
}
const periodDuration = computed(() => {
    if (!form.opens_at || !form.closes_at) return null;
    const start = new Date(`${form.opens_at}+08:00`).getTime();
    const end = new Date(`${form.closes_at}+08:00`).getTime();
    if (isNaN(start) || isNaN(end)) return null;
    const diffMs = end - start;
    if (diffMs <= 0) {
        return {
            valid: false,
            message: 'Closing time must be scheduled after opening time.',
            text: '',
            days: 0,
            hours: 0,
        };
    }
    const totalMinutes = Math.floor(diffMs / 60000);
    const totalHours = Math.floor(totalMinutes / 60);
    const days = Math.floor(totalHours / 24);
    const hours = totalHours % 24;

    const parts: string[] = [];
    if (days > 0) parts.push(`${days} day${days > 1 ? 's' : ''}`);
    if (hours > 0) parts.push(`${hours} hour${hours > 1 ? 's' : ''}`);
    if (parts.length === 0) parts.push(`${totalMinutes} min`);

    return {
        valid: true,
        text: parts.join(', '),
        days,
        hours,
    };
});
function reset() {
    editing.value = null;
    opensDate.value = '';
    opensTime.value = '08:00';
    closesDate.value = '';
    closesTime.value = '17:00';
    form.reset();
    form.minimum_responses = 3;
    form.clearErrors();
}
function create() {
    reset();
    transitionErrors.value = {};
    dialogOpen.value = true;
}
function edit(record: Period) {
    reset();
    editing.value = record.id;
    form.name = record.name;
    form.school_year = record.school_year;
    form.evaluation_form_id = record.evaluation_form_id ?? '';
    const openLocal = local(record.opens_at);
    if (openLocal) {
        const [d, t] = openLocal.split('T');
        opensDate.value = d;
        opensTime.value = t || '08:00';
    }
    const closeLocal = local(record.closes_at);
    if (closeLocal) {
        const [d, t] = closeLocal.split('T');
        closesDate.value = d;
        closesTime.value = t || '17:00';
    }
    form.minimum_responses = record.minimum_responses || 3;
    dialogOpen.value = true;
}
function submit() {
    form.transform((data) => ({
        ...data,
        evaluation_form_id: data.evaluation_form_id || null,
        opens_at: data.opens_at
            ? new Date(data.opens_at + '+08:00').toISOString()
            : '',
        closes_at: data.closes_at
            ? new Date(data.closes_at + '+08:00').toISOString()
            : '',
        minimum_responses: data.minimum_responses || 3,
    })).submit(
        editing.value ? 'put' : 'post',
        editing.value ? update.url(editing.value) : store.url(),
        {
            preserveScroll: true,
            onSuccess: () => {
                dialogOpen.value = false;
            },
        },
    );
}
function change(record: Period, action: string) {
    if (
        action === 'release' &&
        !window.confirm(
            'Release these results to teachers? This period will remain locked.',
        )
    )
        return;
    transitionErrors.value = {};
    busy.value = true;
    router.post(
        transition.url(record.id),
        { action },
        {
            preserveScroll: true,
            onError: (errors) => {
                transitionErrors.value = errors;
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

const searchQuery = ref('');
const statusFilter = ref('all');

const filteredPeriods = computed(() =>
    props.periods.filter((record) => {
        const matchesStatus =
            statusFilter.value === 'all' ||
            record.status === statusFilter.value;
        const q = searchQuery.value.toLowerCase().trim();
        const matchesQuery =
            !q ||
            record.name.toLowerCase().includes(q) ||
            record.school_year.toLowerCase().includes(q) ||
            (record.form?.name && record.form.name.toLowerCase().includes(q));
        return matchesStatus && matchesQuery;
    }),
);

function clearFilters() {
    searchQuery.value = '';
    statusFilter.value = 'all';
}

function statusBadgeClass(status: string) {
    switch (status) {
        case 'draft':
            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700';
        case 'open':
            return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800';
        case 'closed':
            return 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800';
        case 'released':
            return 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
    }
}
</script>
<template>
    <SchoolPage
        title="Evaluation periods"
        description="Create an evaluation template first, then select its version and schedule a period in Philippine time. Each period keeps its selected version when new templates are introduced."
    >
        <template #actions>
            <SchoolFormDialog
                v-model:open="dialogOpen"
                :title="
                    editing ? 'Edit evaluation period' : 'New evaluation period'
                "
                description="Choose a template version and schedule the period in Philippine time. New periods start as drafts, and their version is fixed once opened."
                form-id="school-periods-form"
                submit-label="Save draft"
                :processing="form.processing"
                :wide="true"
            >
                <template #trigger
                    ><Button
                        type="button"
                        class="gap-2 bg-[#185b94] text-white hover:bg-[#104675]"
                        @click="create"
                        ><Plus class="size-4" aria-hidden="true" />New
                        evaluation period</Button
                    ></template
                >
                <FormErrors :errors="form.errors" />
                <form id="school-periods-form" @submit.prevent="submit">
                    <fieldset :disabled="form.processing" class="space-y-5">
                        <!-- 1. Period Details -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="name" class="school-label">
                                    Period name
                                </label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="e.g. 1st Semester Midterm Evaluation"
                                    class="school-input"
                                    required
                                />
                            </div>
                            <div>
                                <label for="school_year" class="school-label">
                                    School year
                                </label>
                                <select
                                    id="school_year"
                                    v-model="form.school_year"
                                    class="school-input"
                                    required
                                >
                                    <option value="">
                                        Choose a school year
                                    </option>
                                    <option
                                        v-for="year in schoolYears"
                                        :key="year.id"
                                        :value="year.name"
                                    >
                                        {{ year.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- 2. Template Version Selection & Card -->
                        <div class="space-y-2">
                            <label for="form" class="school-label">
                                Template version
                            </label>
                            <select
                                id="form"
                                v-model="form.evaluation_form_id"
                                class="school-input"
                                required
                            >
                                <option value="">
                                    Choose a template version
                                </option>
                                <option
                                    v-for="item in availableVersions"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.name }} · v{{ item.version
                                    }}{{
                                        item.is_active
                                            ? ''
                                            : ' (retired, retained by this draft)'
                                    }}
                                </option>
                            </select>
                            <p class="text-xs text-slate-500">
                                Required. Once opened, this period permanently
                                keeps its assigned template version.
                            </p>
                        </div>

                        <!-- 3. Schedule & Timing Section (Flex layout for Opens & Closes, stacked Date and Time) -->
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/30"
                        >
                            <div
                                class="mb-3.5 flex items-center gap-2 border-b border-slate-200/80 pb-3 dark:border-slate-800"
                            >
                                <Calendar
                                    class="size-4 text-[#185b94] dark:text-blue-400"
                                />
                                <h4
                                    class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    Schedule
                                </h4>
                                <span
                                    class="rounded bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-800 dark:bg-blue-950 dark:text-blue-300"
                                >
                                    Philippine Time (PHT · UTC+8)
                                </span>
                            </div>

                            <div class="flex flex-col gap-5 sm:flex-row">
                                <!-- Start / Opens -->
                                <div class="flex-1 space-y-2">
                                    <label
                                        for="opens-date"
                                        class="school-label mb-0"
                                    >
                                        Opens at (Start)
                                    </label>
                                    <div class="space-y-2">
                                        <input
                                            id="opens-date"
                                            v-model="opensDate"
                                            type="date"
                                            class="school-input"
                                            required
                                        />
                                        <select
                                            id="opens-time"
                                            v-model="opensTime"
                                            class="school-input"
                                            aria-label="Opening time"
                                        >
                                            <option
                                                v-for="time in opensTimeOptions"
                                                :key="time.value"
                                                :value="time.value"
                                            >
                                                {{ time.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <p
                                        v-if="form.opens_at"
                                        class="flex items-center gap-1.5 text-xs font-medium text-blue-700 dark:text-blue-300"
                                    >
                                        <Clock class="size-3.5 shrink-0" />
                                        <span>{{
                                            formatPhtDisplay(form.opens_at)
                                        }}</span>
                                    </p>
                                </div>

                                <!-- Deadline / Closes -->
                                <div class="flex-1 space-y-2">
                                    <label
                                        for="closes-date"
                                        class="school-label mb-0"
                                    >
                                        Closes at (Deadline)
                                    </label>
                                    <div class="space-y-2">
                                        <input
                                            id="closes-date"
                                            v-model="closesDate"
                                            type="date"
                                            :min="opensDate || undefined"
                                            class="school-input"
                                            required
                                        />
                                        <select
                                            id="closes-time"
                                            v-model="closesTime"
                                            class="school-input"
                                            aria-label="Closing time"
                                        >
                                            <option
                                                v-for="time in closesTimeOptions"
                                                :key="time.value"
                                                :value="time.value"
                                            >
                                                {{ time.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <p
                                        v-if="form.closes_at"
                                        class="flex items-center gap-1.5 text-xs font-medium text-blue-700 dark:text-blue-300"
                                    >
                                        <Clock class="size-3.5 shrink-0" />
                                        <span>{{
                                            formatPhtDisplay(form.closes_at)
                                        }}</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Real-time duration / error badge -->
                            <div
                                v-if="periodDuration"
                                class="mt-3.5 border-t border-slate-200/80 pt-3 dark:border-slate-800"
                            >
                                <div
                                    v-if="periodDuration.valid"
                                    class="flex items-center gap-2 rounded-lg bg-blue-50/90 px-3 py-2 text-xs text-blue-900 dark:bg-blue-950/60 dark:text-blue-200"
                                >
                                    <CheckCircle2
                                        class="size-4 shrink-0 text-[#185b94] dark:text-blue-400"
                                    />
                                    <span>
                                        Evaluation duration:
                                        <strong class="font-semibold">{{
                                            periodDuration.text
                                        }}</strong
                                        >.
                                    </span>
                                </div>
                                <div
                                    v-else
                                    class="flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:bg-rose-950/60 dark:text-rose-200"
                                >
                                    <AlertCircle
                                        class="size-4 shrink-0 text-rose-600 dark:text-rose-400"
                                    />
                                    <span>{{ periodDuration.message }}</span>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </SchoolFormDialog>
        </template>
        <FormErrors :errors="transitionErrors" />
        <section class="school-panel overflow-hidden p-0 shadow-xs">
            <!-- Toolbar -->
            <div
                class="border-b border-slate-200 px-5 py-3.5 dark:border-slate-800"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div
                        class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center"
                    >
                        <div class="relative flex-1 sm:max-w-xs">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="searchQuery"
                                type="search"
                                placeholder="Search periods..."
                                class="school-input pl-9"
                            />
                        </div>
                        <div class="w-full sm:w-44">
                            <select
                                v-model="statusFilter"
                                class="school-input"
                                aria-label="Filter by status"
                            >
                                <option value="all">All statuses</option>
                                <option value="draft">Draft</option>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                                <option value="released">Released</option>
                            </select>
                        </div>
                        <button
                            v-if="searchQuery || statusFilter !== 'all'"
                            type="button"
                            class="self-start text-xs font-medium text-slate-500 hover:text-slate-800 sm:self-auto dark:hover:text-slate-200"
                            @click="clearFilters"
                        >
                            Reset filters
                        </button>
                    </div>
                    <div class="text-xs text-slate-500">
                        {{ filteredPeriods.length }} of {{ periods.length }}
                        {{ periods.length === 1 ? 'period' : 'periods' }}
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="school-table">
                    <thead>
                        <tr>
                            <th scope="col">Period</th>
                            <th scope="col">School Year</th>
                            <th scope="col">Template</th>
                            <th scope="col">Opens (PHT)</th>
                            <th scope="col">Closes (PHT)</th>
                            <th scope="col">Evaluations</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="record in filteredPeriods"
                            :key="record.id"
                            class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/50 [&>td]:align-middle"
                        >
                            <td>
                                <div
                                    class="font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    {{ record.name }}
                                </div>
                            </td>
                            <td
                                class="whitespace-nowrap text-slate-600 dark:text-slate-300"
                            >
                                {{ record.school_year }}
                            </td>
                            <td>
                                <div v-if="record.form">
                                    <span
                                        class="font-medium text-slate-800 dark:text-slate-200"
                                    >
                                        {{ record.form.name }}
                                    </span>
                                    <span
                                        class="ml-1.5 inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        v{{ record.form.version }}
                                    </span>
                                </div>
                                <div v-else class="flex flex-col gap-0.5">
                                    <span
                                        class="inline-flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400"
                                    >
                                        <AlertCircle
                                            class="size-3.5 shrink-0"
                                        />
                                        No template
                                    </span>
                                    <Link
                                        :href="templateIndex()"
                                        class="text-xs text-blue-600 underline underline-offset-2 hover:text-blue-800 dark:text-blue-400"
                                    >
                                        Assign template
                                    </Link>
                                </div>
                            </td>
                            <td
                                class="text-xs whitespace-nowrap text-slate-600 sm:text-sm dark:text-slate-300"
                            >
                                {{ display(record.opens_at) }}
                            </td>
                            <td
                                class="text-xs whitespace-nowrap text-slate-600 sm:text-sm dark:text-slate-300"
                            >
                                {{ display(record.closes_at) }}
                            </td>
                            <td class="whitespace-nowrap">
                                <span
                                    class="font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    {{ record.evaluations_count }}
                                </span>
                                <span class="block text-[11px] text-slate-500">
                                    min. {{ record.minimum_responses }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium capitalize',
                                        statusBadgeClass(record.status),
                                    ]"
                                >
                                    <span
                                        v-if="record.status === 'open'"
                                        class="size-1.5 animate-pulse rounded-full bg-emerald-500"
                                    />
                                    {{ record.status }}
                                </span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Button
                                        v-if="record.status === 'draft'"
                                        variant="outline"
                                        size="sm"
                                        @click="edit(record)"
                                    >
                                        Edit draft
                                    </Button>
                                    <Button
                                        v-if="record.status === 'draft'"
                                        size="sm"
                                        class="bg-[#185b94] text-white hover:bg-[#104675]"
                                        :disabled="busy || !record.form"
                                        :title="
                                            !record.form
                                                ? 'Assign an evaluation template before opening this period'
                                                : undefined
                                        "
                                        @click="change(record, 'open')"
                                    >
                                        Open evaluation
                                    </Button>
                                    <Button
                                        v-if="record.status === 'open'"
                                        variant="outline"
                                        size="sm"
                                        :disabled="busy"
                                        @click="change(record, 'close')"
                                    >
                                        Close evaluation
                                    </Button>
                                    <Button
                                        v-if="record.status === 'closed'"
                                        size="sm"
                                        class="bg-[#185b94] text-white hover:bg-[#104675]"
                                        :disabled="busy"
                                        @click="change(record, 'release')"
                                    >
                                        Release results
                                    </Button>
                                    <span
                                        v-if="record.status === 'released'"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-400"
                                    >
                                        <CheckCircle2 class="size-3.5" />
                                        Released
                                    </span>
                                </div>
                            </td>
                        </tr>

                        <!-- No filtered results -->
                        <tr
                            v-if="
                                periods.length > 0 &&
                                filteredPeriods.length === 0
                            "
                        >
                            <td
                                colspan="8"
                                class="py-8 text-center text-slate-500"
                            >
                                <p class="text-sm">
                                    No evaluation periods match your search or
                                    filter.
                                </p>
                                <button
                                    type="button"
                                    class="mt-2 text-xs font-medium text-[#185b94] hover:underline dark:text-blue-400"
                                    @click="clearFilters"
                                >
                                    Clear filters
                                </button>
                            </td>
                        </tr>

                        <!-- Empty state when no periods created yet -->
                        <tr v-if="periods.length === 0">
                            <td colspan="8" class="py-12 text-center">
                                <Calendar
                                    class="mx-auto size-10 text-slate-300 dark:text-slate-600"
                                />
                                <h3
                                    class="mt-3 text-sm font-semibold text-slate-900 dark:text-slate-100"
                                >
                                    No evaluation periods yet
                                </h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    Create an evaluation template first, then
                                    create a period.
                                </p>
                                <div class="mt-4">
                                    <Button
                                        type="button"
                                        class="gap-2 bg-[#185b94] text-white hover:bg-[#104675]"
                                        @click="create"
                                    >
                                        <Plus class="size-4" />
                                        New evaluation period
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </SchoolPage>
</template>
