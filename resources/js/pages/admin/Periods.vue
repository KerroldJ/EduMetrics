<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus } from '@lucide/vue';
import SchoolFormDialog from '@/components/SchoolFormDialog.vue';
import SchoolPage from '@/components/SchoolPage.vue';
import FormErrors from '@/components/FormErrors.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index, store, update, transition } from '@/routes/periods';

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
    evaluation_form_id: number;
    opens_at: string;
    closes_at: string;
    status: string;
    minimum_responses: number;
    evaluations_count: number;
    form: { name: string };
};
defineProps<{
    periods: Period[];
    forms: { id: number; name: string }[];
    schoolYears: { id: number; name: string }[];
}>();
const editing = ref<number | null>(null);
const dialogOpen = ref(false);
const busy = ref(false);
const transitionErrors = ref<Record<string, string>>({});
const form = useForm({
    name: '',
    school_year: '',
    evaluation_form_id: '' as number | string,
    opens_at: '',
    closes_at: '',
    minimum_responses: 3,
});
function local(value: string) {
    return new Date(new Date(value).getTime() + 8 * 3600000)
        .toISOString()
        .slice(0, 16);
}
function display(value: string) {
    return new Date(value).toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}
function reset() {
    editing.value = null;
    form.reset();
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
    form.evaluation_form_id = record.evaluation_form_id;
    form.opens_at = local(record.opens_at);
    form.closes_at = local(record.closes_at);
    form.minimum_responses = record.minimum_responses;
    dialogOpen.value = true;
}
function submit() {
    form.transform((data) => ({
        ...data,
        opens_at: data.opens_at
            ? new Date(data.opens_at + '+08:00').toISOString()
            : '',
        closes_at: data.closes_at
            ? new Date(data.closes_at + '+08:00').toISOString()
            : '',
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
</script>
<template>
    <SchoolPage
        title="Evaluation periods"
        description="Schedule evaluations in Philippine time. Open a draft to collect responses, close collection, then release results to teachers."
    >
        <template #actions>
            <SchoolFormDialog
                v-model:open="dialogOpen"
                :title="
                    editing ? 'Edit evaluation period' : 'New evaluation period'
                "
                description="Choose a form and schedule the opening and closing times in Philippine time. New periods start as drafts."
                form-id="school-periods-form"
                submit-label="Save draft"
                :processing="form.processing"
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
                    <fieldset :disabled="form.processing" class="grid gap-5">
                        <div>
                            <label for="name" class="school-label"
                                >Period name</label
                            ><input
                                id="name"
                                v-model="form.name"
                                class="school-input"
                                required
                            />
                        </div>
                        <div>
                            <label for="school_year" class="school-label"
                                >School year</label
                            ><select
                                id="school_year"
                                v-model="form.school_year"
                                class="school-input"
                                required
                            >
                                <option value="">Choose a school year</option>
                                <option
                                    v-for="year in schoolYears"
                                    :key="year.id"
                                    :value="year.name"
                                >
                                    {{ year.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label for="form" class="school-label"
                                >Evaluation form</label
                            ><select
                                id="form"
                                v-model="form.evaluation_form_id"
                                class="school-input"
                                required
                            >
                                <option value="">Choose a form</option>
                                <option
                                    v-for="item in forms"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.name }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                Only active forms with questions are listed.
                            </p>
                        </div>
                        <div>
                            <label for="opens" class="school-label"
                                >Opens (Philippine time)</label
                            ><input
                                id="opens"
                                v-model="form.opens_at"
                                type="datetime-local"
                                class="school-input"
                                required
                            />
                        </div>
                        <div>
                            <label for="closes" class="school-label"
                                >Closes (Philippine time)</label
                            ><input
                                id="closes"
                                v-model="form.closes_at"
                                type="datetime-local"
                                class="school-input"
                                required
                            />
                        </div>
                        <div>
                            <label for="minimum" class="school-label"
                                >Minimum responses before teacher access</label
                            ><input
                                id="minimum"
                                v-model.number="form.minimum_responses"
                                type="number"
                                min="1"
                                max="100"
                                class="school-input"
                                required
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                Smaller groups can reveal who provided feedback.
                                The default is 3.
                            </p>
                        </div>
                    </fieldset>
                </form>
            </SchoolFormDialog>
        </template>
        <FormErrors :errors="transitionErrors" />
        <section class="grid gap-4">
            <article
                v-for="record in periods"
                :key="record.id"
                class="school-panel"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="font-semibold">{{ record.name }}</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ record.school_year }} ·
                            {{ record.form.name }}
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-blue-50 px-3 py-1 text-xs text-blue-800 dark:bg-blue-950 dark:text-blue-200"
                        >{{ record.status }}</span
                    >
                </div>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">
                            Opens (Philippine time)
                        </dt>
                        <dd class="mt-1">{{ display(record.opens_at) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Closes (Philippine time)
                        </dt>
                        <dd class="mt-1">
                            {{ display(record.closes_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Submitted evaluations
                        </dt>
                        <dd class="mt-1">{{ record.evaluations_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">
                            Minimum responses for teacher feedback
                        </dt>
                        <dd class="mt-1">{{ record.minimum_responses }}</dd>
                    </div>
                </dl>
                <div class="mt-5 flex flex-wrap gap-2">
                    <Button
                        v-if="record.status === 'draft'"
                        variant="outline"
                        size="sm"
                        @click="edit(record)"
                        >Edit draft</Button
                    ><Button
                        v-if="record.status === 'draft'"
                        size="sm"
                        :disabled="busy"
                        @click="change(record, 'open')"
                        >Open evaluation</Button
                    ><Button
                        v-if="record.status === 'open'"
                        variant="outline"
                        size="sm"
                        :disabled="busy"
                        @click="change(record, 'close')"
                        >Close evaluation</Button
                    ><Button
                        v-if="record.status === 'closed'"
                        size="sm"
                        :disabled="busy"
                        @click="change(record, 'release')"
                        >Release results</Button
                    ><span
                        v-if="record.status === 'released'"
                        class="text-xs text-emerald-700 dark:text-emerald-300"
                        >Released to eligible teachers</span
                    >
                </div>
            </article>
            <p
                v-if="!periods.length"
                class="school-panel text-sm text-slate-500"
            >
                No evaluation periods yet. Create an approved form, then
                schedule the first period.
            </p>
        </section>
    </SchoolPage>
</template>
