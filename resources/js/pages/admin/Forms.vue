<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus } from '@lucide/vue';
import SchoolFormDialog from '@/components/SchoolFormDialog.vue';
import SchoolPage from '@/components/SchoolPage.vue';
import FormErrors from '@/components/FormErrors.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index, store, update } from '@/routes/forms';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Evaluation Management' },
            { title: 'Evaluation forms', href: index() },
        ],
    },
});
type Criterion = { prompt: string; weight: number };
type Band = { label: string; min: number; max: number };
type EvaluationForm = {
    id: number;
    name: string;
    description: string | null;
    scale_min: number;
    scale_max: number;
    is_active: boolean;
    periods_count: number;
    criteria: Criterion[];
    performance_bands: Band[] | null;
};
defineProps<{ forms: EvaluationForm[] }>();
const editing = ref<number | null>(null);
const dialogOpen = ref(false);
const form = useForm({
    name: '',
    description: '',
    scale_min: 1,
    scale_max: 5,
    is_active: true,
    criteria: [{ prompt: '', weight: 1 }] as Criterion[],
    performance_bands: [] as Band[],
});
function reset() {
    editing.value = null;
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
    form.name = record.name + (duplicate ? ' — new version' : '');
    form.description = record.description ?? '';
    form.scale_min = record.scale_min;
    form.scale_max = record.scale_max;
    form.is_active = record.is_active;
    form.criteria = record.criteria.map((item) => ({
        prompt: item.prompt,
        weight: Number(item.weight),
    }));
    form.performance_bands = (record.performance_bands ?? []).map((band) => ({
        ...band,
    }));
    dialogOpen.value = true;
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
        title="Evaluation forms"
        description="Use the school’s approved questions, scales, and weights. A form locks when an evaluation period uses it; duplicate it for a new version."
    >
        <template #actions>
            <SchoolFormDialog
                v-model:open="dialogOpen"
                :title="
                    editing ? 'Edit evaluation form' : 'New evaluation form'
                "
                description="Configure the school’s questions, rating scale, and weights. Used forms stay locked; duplicate a form for a new version."
                form-id="school-forms-form"
                submit-label="Save evaluation form"
                :processing="form.processing"
                wide
            >
                <template #trigger
                    ><Button
                        type="button"
                        class="gap-2 bg-[#185b94] text-white hover:bg-[#104675]"
                        @click="create"
                        ><Plus class="size-4" aria-hidden="true" />New
                        evaluation form</Button
                    ></template
                >
                <FormErrors :errors="form.errors" />
                <form id="school-forms-form" @submit.prevent="submit">
                    <fieldset :disabled="form.processing" class="grid gap-5">
                        <div>
                            <label for="name" class="school-label"
                                >Form name</label
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
                        <label class="flex items-center gap-2 text-sm"
                            ><input
                                v-model="form.is_active"
                                type="checkbox"
                                class="accent-blue-700"
                            />Available for new evaluation periods</label
                        >
                    </fieldset>
                </form>
            </SchoolFormDialog>
        </template>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <section
                v-for="record in forms"
                :key="record.id"
                class="school-panel"
            >
                <div class="flex justify-between gap-3">
                    <h2 class="font-semibold">{{ record.name }}</h2>
                    <span class="text-xs text-slate-500">{{
                        record.is_active ? 'Active' : 'Inactive'
                    }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    {{ record.criteria.length }} questions ·
                    {{ record.scale_min }}–{{ record.scale_max }} scale
                </p>
                <p v-if="record.description" class="mt-2 text-sm leading-6">
                    {{ record.description }}
                </p>
                <p
                    v-if="record.periods_count"
                    class="mt-3 text-xs text-amber-700 dark:text-amber-300"
                >
                    Locked to preserve {{ record.periods_count }} evaluation
                    period(s).
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        v-if="!record.periods_count"
                        variant="outline"
                        size="sm"
                        @click="edit(record)"
                        >Edit form</Button
                    ><Button
                        variant="outline"
                        size="sm"
                        @click="edit(record, true)"
                        >Duplicate</Button
                    >
                </div>
            </section>
            <p v-if="!forms.length" class="school-panel text-sm text-slate-500">
                No evaluation forms yet. Create the first form using the
                school’s approved instrument.
            </p>
        </div>
    </SchoolPage>
</template>
