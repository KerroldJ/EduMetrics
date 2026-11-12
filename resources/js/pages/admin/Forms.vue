<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Archive,
    Copy,
    Eye,
    FileText,
    MoreHorizontal,
    Pencil,
    Plus,
    RotateCcw,
    Search,
} from '@lucide/vue';
import SchoolFormDialog from '@/components/SchoolFormDialog.vue';
import SchoolPage from '@/components/SchoolPage.vue';
import EvaluationTemplatePreview from '@/components/EvaluationTemplatePreview.vue';
import FormErrors from '@/components/FormErrors.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { EvaluationTemplate as EvaluationForm } from '@/types/school';
import { dashboard } from '@/routes';
import { index, store, update, status } from '@/routes/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Evaluation Management' },
            { title: 'Evaluation templates', href: index() },
        ],
    },
});
type Criterion = { prompt: string; weight: number };
type Band = { label: string; min: number; max: number };
const props = defineProps<{
    forms: EvaluationForm[];
}>();
const templates = computed(() => {
    const groups = new Map<
        number,
        { id: number; name: string; versions: EvaluationForm[] }
    >();
    for (const record of props.forms) {
        const id = record.template_id ?? record.id;
        if (!groups.has(id)) {
            groups.set(id, {
                id,
                name:
                    props.forms.find((item) => item.id === id)?.name ??
                    record.name,
                versions: [],
            });
        }
        groups.get(id)!.versions.push(record);
    }
    return [...groups.values()];
});
const query = ref('');
const availability = ref('all');
const filteredForms = computed(() => {
    const keyword = query.value.trim().toLocaleLowerCase();
    return templates.value.flatMap((template) =>
        [...template.versions]
            .sort((first, second) => second.version - first.version)
            .filter((record) => {
                const matchesAvailability =
                    availability.value === 'all' ||
                    (availability.value === 'available'
                        ? record.is_active
                        : !record.is_active);
                const matchesSearch =
                    !keyword ||
                    [
                        record.name,
                        template.name,
                        ...record.criteria.map((criterion) => criterion.prompt),
                    ].some((value) =>
                        value.toLocaleLowerCase().includes(keyword),
                    );
                return matchesAvailability && matchesSearch;
            }),
    );
});
const previewOpen = ref(false);
const selectedId = ref<number | null>(null);
const selectedTemplate = computed(
    () => props.forms.find((record) => record.id === selectedId.value) ?? null,
);
function view(record: EvaluationForm) {
    selectedId.value = record.id;
    previewOpen.value = true;
}
function summary(description: string | null) {
    const paragraph =
        description?.split(/\n\s*\n/)[0] ?? 'No student instructions added.';
    return paragraph.length > 150 ? paragraph.slice(0, 147) + '…' : paragraph;
}
function clearFilters() {
    query.value = '';
    availability.value = 'all';
}
const versionSource = ref<number | null>(null);
const busy = ref(false);
const statusErrors = ref<Record<string, string>>({});
const editing = ref<number | null>(null);
const dialogOpen = ref(false);
const form = useForm({
    source_form_id: null as number | null,
    name: '',
    description: '',
    scale_min: 1,
    scale_max: 5,
    criteria: [{ prompt: '', weight: 1 }] as Criterion[],
    performance_bands: [] as Band[],
});
function reset() {
    editing.value = null;
    versionSource.value = null;
    form.reset();
    form.clearErrors();
}
function create() {
    reset();
    dialogOpen.value = true;
}
function edit(record: EvaluationForm, duplicate = false) {
    reset();
    editing.value = duplicate ? null : record.id;
    form.source_form_id = duplicate ? record.id : null;
    versionSource.value = duplicate ? record.version : null;
    form.name = record.name;
    form.description = record.description ?? '';
    form.scale_min = record.scale_min;
    form.scale_max = record.scale_max;
    form.criteria = record.criteria.map((item) => ({
        prompt: item.prompt,
        weight: Number(item.weight),
    }));
    form.performance_bands = (record.performance_bands ?? []).map((band) => ({
        ...band,
    }));
    dialogOpen.value = true;
}
function changeAvailability(record: EvaluationForm) {
    busy.value = true;
    statusErrors.value = {};
    router.patch(
        status.url(record.id),
        { is_active: !record.is_active },
        {
            preserveScroll: true,
            onError: (errors) => {
                statusErrors.value = errors;
            },
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}
function submit() {
    form.submit(
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
</script>
<template>
    <SchoolPage
        title="Evaluation templates"
        description="Create questionnaires, review their versions, and choose which ones are available for evaluation periods."
    >
        <template #actions>
            <SchoolFormDialog
                v-model:open="dialogOpen"
                :title="
                    editing
                        ? 'Edit draft version'
                        : versionSource
                          ? 'Create new template version'
                          : 'New evaluation template'
                "
                description="A version locks once its period opens or a newer version is created. New versions never change existing evaluations."
                form-id="school-forms-form"
                :submit-label="
                    versionSource ? 'Create new version' : 'Save template'
                "
                :processing="form.processing"
                drawer
                wide
            >
                <template #trigger
                    ><Button
                        type="button"
                        class="gap-2 bg-[#185b94] text-white hover:bg-[#104675]"
                        @click="create"
                        ><Plus class="size-4" aria-hidden="true" />New
                        evaluation template</Button
                    ></template
                >
                <FormErrors :errors="form.errors" />
                <p
                    v-if="versionSource"
                    class="mb-5 rounded-lg bg-blue-50 p-3 text-sm text-blue-900 dark:bg-blue-950 dark:text-blue-100"
                >
                    Based on version {{ versionSource }}. Review and adjust the
                    copied questions before saving. The next version number is
                    assigned automatically.
                </p>
                <form id="school-forms-form" @submit.prevent="submit">
                    <fieldset :disabled="form.processing" class="grid gap-5">
                        <div>
                            <label for="name" class="school-label"
                                >Template version name</label
                            ><input
                                id="name"
                                v-model="form.name"
                                class="school-input"
                                required
                            />
                        </div>
                        <div>
                            <label for="description" class="school-label"
                                >Instructions for students</label
                            ><textarea
                                id="description"
                                v-model="form.description"
                                class="school-input"
                                rows="3"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="scale_min" class="school-label"
                                    >Lowest rating</label
                                ><input
                                    id="scale_min"
                                    v-model.number="form.scale_min"
                                    type="number"
                                    min="1"
                                    max="9"
                                    class="school-input"
                                    required
                                />
                            </div>
                            <div>
                                <label for="scale_max" class="school-label"
                                    >Highest rating</label
                                ><input
                                    id="scale_max"
                                    v-model.number="form.scale_max"
                                    type="number"
                                    min="2"
                                    max="10"
                                    class="school-input"
                                    required
                                />
                            </div>
                        </div>
                        <fieldset class="grid gap-3">
                            <legend class="mb-3 font-medium">
                                Questions and weights
                            </legend>
                            <div
                                v-for="(criterion, index) in form.criteria"
                                :key="index"
                                class="rounded-lg border p-3"
                            >
                                <label
                                    :for="'question-' + index"
                                    class="school-label"
                                    >Question {{ index + 1 }}</label
                                ><textarea
                                    :id="'question-' + index"
                                    v-model="criterion.prompt"
                                    class="school-input"
                                    rows="2"
                                    placeholder="Enter the school’s approved evaluation question"
                                    required
                                />
                                <div class="mt-3 flex items-end gap-3">
                                    <div class="flex-1">
                                        <label
                                            :for="'weight-' + index"
                                            class="school-label"
                                            >Weight</label
                                        ><input
                                            :id="'weight-' + index"
                                            v-model.number="criterion.weight"
                                            type="number"
                                            min="0.01"
                                            max="1000"
                                            step="0.01"
                                            class="school-input"
                                            required
                                        />
                                    </div>
                                    <Button
                                        variant="ghost"
                                        type="button"
                                        :disabled="form.criteria.length === 1"
                                        @click="form.criteria.splice(index, 1)"
                                        >Remove</Button
                                    >
                                </div>
                            </div>
                            <Button
                                variant="outline"
                                type="button"
                                @click="
                                    form.criteria.push({
                                        prompt: '',
                                        weight: 1,
                                    })
                                "
                                >Add question</Button
                            >
                        </fieldset>
                        <fieldset class="grid gap-3">
                            <legend class="mb-2 font-medium">
                                Performance bands (optional)
                            </legend>
                            <p class="text-xs leading-5 text-slate-500">
                                Scores use a weighted mean, displayed to two
                                decimal places. Add the school’s approved,
                                non-overlapping score ranges. Without bands,
                                reports show numeric scores only.
                            </p>
                            <div
                                v-for="(band, index) in form.performance_bands"
                                :key="index"
                                class="grid gap-3 rounded-lg border p-3 sm:grid-cols-3"
                            >
                                <div class="sm:col-span-3">
                                    <label
                                        :for="'band-label-' + index"
                                        class="school-label"
                                        >Performance label</label
                                    ><input
                                        :id="'band-label-' + index"
                                        v-model="band.label"
                                        class="school-input"
                                        required
                                    />
                                </div>
                                <div>
                                    <label
                                        :for="'band-min-' + index"
                                        class="school-label"
                                        >Minimum</label
                                    ><input
                                        :id="'band-min-' + index"
                                        v-model.number="band.min"
                                        type="number"
                                        step="0.01"
                                        class="school-input"
                                        required
                                    />
                                </div>
                                <div>
                                    <label
                                        :for="'band-max-' + index"
                                        class="school-label"
                                        >Maximum</label
                                    ><input
                                        :id="'band-max-' + index"
                                        v-model.number="band.max"
                                        type="number"
                                        step="0.01"
                                        class="school-input"
                                        required
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="self-end"
                                    @click="
                                        form.performance_bands.splice(index, 1)
                                    "
                                    >Remove</Button
                                >
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                @click="
                                    form.performance_bands.push({
                                        label: '',
                                        min: form.scale_min,
                                        max: form.scale_max,
                                    })
                                "
                                >Add performance band</Button
                            >
                        </fieldset>
                    </fieldset>
                </form>
            </SchoolFormDialog>
        </template>
        <FormErrors :errors="statusErrors" />
        <section
            class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
            aria-label="Evaluation template library"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-slate-800"
            >
                <div class="relative w-full sm:max-w-sm">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="query"
                        type="search"
                        placeholder="Search names or questions…"
                        aria-label="Search templates by name or question"
                        class="h-10 bg-slate-50/70 pl-9 shadow-none dark:bg-slate-950/50"
                    />
                </div>
                <div
                    class="flex w-full items-center justify-between gap-4 sm:w-auto"
                >
                    <p
                        class="shrink-0 text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{ templates.length }}
                        {{ templates.length === 1 ? 'template' : 'templates' }}
                        / {{ forms.length }}
                        {{ forms.length === 1 ? 'version' : 'versions' }}
                    </p>
                    <select
                        v-model="availability"
                        class="school-input w-auto py-2"
                        aria-label="Filter templates by availability"
                    >
                        <option value="all">All statuses</option>
                        <option value="available">Available</option>
                        <option value="retired">Retired</option>
                    </select>
                </div>
            </div>
            <div
                v-if="forms.length"
                class="hidden grid-cols-[minmax(0,1fr)_5rem_7rem_6rem_7rem_7rem] gap-4 border-b border-slate-100 bg-slate-50/70 px-5 py-3 text-xs font-medium text-slate-500 xl:grid dark:border-slate-800 dark:bg-slate-950/40 dark:text-slate-400"
                aria-hidden="true"
            >
                <span>Template</span><span>Version</span><span>Questions</span
                ><span>Usage</span><span>Status</span
                ><span class="text-right">Actions</span>
            </div>
            <div
                v-if="filteredForms.length"
                role="list"
                class="divide-y divide-slate-100 dark:divide-slate-800"
            >
                <article
                    v-for="record in filteredForms"
                    :key="record.id"
                    role="listitem"
                    :aria-labelledby="'template-title-' + record.id"
                    class="grid grid-cols-2 items-start gap-4 px-5 py-5 sm:grid-cols-4 xl:grid-cols-[minmax(0,1fr)_5rem_7rem_6rem_7rem_7rem]"
                >
                    <div
                        class="col-span-2 flex min-w-0 items-start gap-3 sm:col-span-4 xl:col-span-1"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-[#185b94] dark:bg-blue-950 dark:text-blue-300"
                        >
                            <FileText class="size-4" aria-hidden="true" />
                        </div>
                        <div class="min-w-0">
                            <h2
                                :id="'template-title-' + record.id"
                                class="text-sm leading-6 font-semibold"
                            >
                                <button
                                    type="button"
                                    class="text-left outline-none hover:text-[#185b94] focus-visible:underline focus-visible:underline-offset-4 dark:hover:text-blue-300"
                                    @click="view(record)"
                                >
                                    {{ record.name }}
                                </button>
                            </h2>
                            <p
                                class="mt-1 line-clamp-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                {{ summary(record.description) }}
                            </p>
                        </div>
                    </div>
                    <dl>
                        <dt class="mb-1 text-xs text-slate-500 xl:sr-only">
                            Version
                        </dt>
                        <dd>
                            <span
                                class="inline-flex rounded-md border border-slate-200 px-2 py-0.5 text-xs font-medium tabular-nums dark:border-slate-700"
                                >v{{ record.version }}</span
                            >
                            <p
                                class="mt-1.5 text-xs text-slate-500 dark:text-slate-400"
                            >
                                {{ record.is_locked ? 'Locked' : 'Editable' }}
                            </p>
                        </dd>
                    </dl>
                    <dl>
                        <dt class="mb-1 text-xs text-slate-500 xl:sr-only">
                            Questions
                        </dt>
                        <dd class="text-sm font-medium">
                            {{ record.criteria.length }} questions
                            <p
                                class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400"
                            >
                                {{ record.scale_min }}–{{ record.scale_max }}
                                rating scale
                            </p>
                        </dd>
                    </dl>
                    <dl>
                        <dt class="mb-1 text-xs text-slate-500 xl:sr-only">
                            Usage
                        </dt>
                        <dd
                            class="text-sm"
                            :class="
                                record.periods_count
                                    ? 'text-slate-700 dark:text-slate-200'
                                    : 'text-slate-500 dark:text-slate-400'
                            "
                        >
                            {{
                                record.periods_count
                                    ? record.periods_count +
                                      (record.periods_count === 1
                                          ? ' period'
                                          : ' periods')
                                    : 'Not assigned'
                            }}
                        </dd>
                    </dl>
                    <dl>
                        <dt class="mb-1 text-xs text-slate-500 xl:sr-only">
                            Status
                        </dt>
                        <dd>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    record.is_active
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                        : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                "
                                ><span
                                    class="size-1.5 rounded-full bg-current"
                                    aria-hidden="true"
                                />{{
                                    record.is_active ? 'Available' : 'Retired'
                                }}</span
                            >
                        </dd>
                    </dl>
                    <div
                        class="col-span-2 flex items-center gap-1 sm:col-span-4 xl:col-span-1 xl:justify-end"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :aria-label="
                                'Preview ' +
                                record.name +
                                ', version ' +
                                record.version
                            "
                            @click="view(record)"
                            ><Eye
                                class="size-3.5"
                                aria-hidden="true"
                            />View</Button
                        >
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child
                                ><Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    :disabled="busy"
                                    :aria-label="
                                        'Actions for ' +
                                        record.name +
                                        ', version ' +
                                        record.version
                                    "
                                    ><MoreHorizontal
                                        class="size-4"
                                        aria-hidden="true" /></Button
                            ></DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-52">
                                <DropdownMenuItem
                                    v-if="!record.is_locked"
                                    @select="edit(record)"
                                    ><Pencil
                                        class="size-4"
                                        aria-hidden="true"
                                    />Edit draft version</DropdownMenuItem
                                >
                                <DropdownMenuItem @select="edit(record, true)"
                                    ><Copy
                                        class="size-4"
                                        aria-hidden="true"
                                    />Create new version</DropdownMenuItem
                                >
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    @select="changeAvailability(record)"
                                    ><Archive
                                        v-if="record.is_active"
                                        class="size-4"
                                        aria-hidden="true"
                                    /><RotateCcw
                                        v-else
                                        class="size-4"
                                        aria-hidden="true"
                                    />{{
                                        record.is_active
                                            ? 'Retire version'
                                            : 'Make available'
                                    }}</DropdownMenuItem
                                >
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </article>
            </div>
            <div
                v-else
                class="flex flex-col items-center px-6 py-14 text-center"
            >
                <FileText
                    class="mb-4 size-8 text-slate-300 dark:text-slate-600"
                    aria-hidden="true"
                />
                <h2 class="text-sm font-semibold">
                    {{
                        forms.length
                            ? 'No matching templates'
                            : 'Your template library is ready'
                    }}
                </h2>
                <p
                    class="mt-2 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400"
                >
                    {{
                        forms.length
                            ? 'Try a different search or include all statuses.'
                            : 'Create your first questionnaire, then assign a version when you schedule an evaluation period.'
                    }}
                </p>
                <Button
                    v-if="forms.length"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="mt-5"
                    @click="clearFilters"
                    >Clear filters</Button
                >
                <Button
                    v-else
                    type="button"
                    variant="outline"
                    size="sm"
                    class="mt-5"
                    @click="create"
                    ><Plus class="size-4" aria-hidden="true" />Create a
                    template</Button
                >
            </div>
            <div
                v-if="forms.length"
                class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400"
            >
                <p aria-live="polite">
                    Showing {{ filteredForms.length }} of {{ forms.length }}
                    {{ forms.length === 1 ? 'version' : 'versions' }}
                </p>
                <p>Opened periods keep their original template version.</p>
            </div>
        </section>
        <EvaluationTemplatePreview
            v-model:open="previewOpen"
            :template="selectedTemplate"
        />
    </SchoolPage>
</template>
