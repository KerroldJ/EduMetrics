<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ref, shallowRef } from 'vue';
import SchoolPage from '@/components/SchoolPage.vue';
import FormErrors from '@/components/FormErrors.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { index, store } from '@/routes/evaluations';
defineOptions({
    layout: ({
        adminView,
        selectedStudent,
    }: {
        adminView?: boolean;
        selectedStudent?: { id: number } | null;
    }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: adminView ? 'Evaluation portals' : 'My Evaluations' },
            {
                title: adminView ? 'Student evaluations' : 'Evaluate teachers',
                href: index({
                    query: adminView ? { student_id: selectedStudent?.id } : {},
                }),
            },
            {
                title: adminView
                    ? 'Preview questionnaire'
                    : 'Submit evaluation',
            },
        ],
    }),
});
const props = defineProps<{
    adminView: boolean;
    selectedStudent: { id: number; name: string };
    period: { id: number; name: string; closes_at: string };
    assignment: { id: number; teacher: string };
    form: {
        name: string;
        version: number;
        description: string | null;
        scale_min: number;
        scale_max: number;
        criteria: { id: number; prompt: string; weight: string }[];
    };
}>();
const answers = useForm({
    assignment_id: props.assignment.id,
    ratings: {} as Record<number, number>,
    comment: '',
});
const ratings = Array.from(
    { length: props.form.scale_max - props.form.scale_min + 1 },
    (_, index) => props.form.scale_min + index,
);
const confirmationOpen = ref(false);
const previousFocus = shallowRef<HTMLElement | null>(null);
function requestSubmission() {
    if (props.adminView || answers.processing || confirmationOpen.value) return;
    if (typeof document !== 'undefined') {
        previousFocus.value =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
    }
    confirmationOpen.value = true;
}
function updateConfirmation(open: boolean) {
    if (!answers.processing) confirmationOpen.value = open;
}
function preventDismissWhileSubmitting(event: Event) {
    if (answers.processing) event.preventDefault();
}
function restoreFocus(event: Event) {
    if (previousFocus.value?.isConnected) {
        event.preventDefault();
        previousFocus.value.focus({ preventScroll: true });
    }
}
function confirmSubmission() {
    if (props.adminView || answers.processing || !confirmationOpen.value)
        return;
    answers.post(store.url(props.period.id), {
        onSuccess: () => {
            confirmationOpen.value = false;
        },
        onError: () => {
            confirmationOpen.value = false;
        },
    });
}
</script>
<template>
    <SchoolPage
        :title="
            (adminView ? 'Preview evaluation: ' : 'Evaluate ') +
            assignment.teacher
        "
        :description="period.name + ' · ' + form.name + ' · v' + form.version"
        ><template #actions
            ><Button variant="outline" as-child
                ><Link
                    :href="
                        index({
                            query: adminView
                                ? { student_id: selectedStudent.id }
                                : {},
                        })
                    "
                    >Back to teachers</Link
                ></Button
            ></template
        >
        <p
            v-if="adminView"
            role="status"
            class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200"
        >
            Previewing the questionnaire available to
            {{ selectedStudent.name }}. Only the student can submit this
            evaluation.
        </p>
        <form
            class="mx-auto grid w-full max-w-3xl gap-5"
            @submit.prevent="requestSubmission"
        >
            <section class="school-panel">
                <h2 class="font-semibold">Before you begin</h2>
                <p
                    v-if="form.description"
                    class="mt-2 text-sm leading-6 whitespace-pre-line"
                >
                    {{ form.description }}
                </p>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Answer every question using the {{ form.scale_min }}–{{
                        form.scale_max
                    }}
                    rating scale. Feedback shown to teachers does not include
                    your name or email. Avoid identifying yourself in comments.
                </p>
                <p class="mt-2 text-sm text-slate-500">
                    Deadline:
                    {{
                        new Date(period.closes_at).toLocaleString('en-PH', {
                            timeZone: 'Asia/Manila',
                        })
                    }}
                    (Philippine time)
                </p>
            </section>
            <FormErrors :errors="answers.errors" />
            <fieldset
                v-for="(criterion, questionIndex) in form.criteria"
                :key="criterion.id"
                :disabled="adminView || answers.processing"
                class="school-panel"
            >
                <legend class="sr-only">
                    Question {{ questionIndex + 1 }}
                </legend>
                <p
                    :id="'question-' + criterion.id"
                    class="leading-7 font-medium"
                >
                    {{ questionIndex + 1 }}. {{ criterion.prompt }}
                </p>
                <div
                    class="mt-4 flex flex-wrap gap-3"
                    role="radiogroup"
                    :aria-labelledby="'question-' + criterion.id"
                >
                    <label
                        v-for="rating in ratings"
                        :key="rating"
                        class="flex min-w-14 cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-3 text-sm has-checked:border-blue-700 has-checked:bg-blue-50 has-checked:text-blue-900 dark:has-checked:bg-blue-950 dark:has-checked:text-blue-100"
                        ><input
                            v-model="answers.ratings[criterion.id]"
                            type="radio"
                            :name="'rating-' + criterion.id"
                            :value="rating"
                            required
                            class="accent-blue-700"
                        />{{ rating }}</label
                    >
                </div>
            </fieldset>
            <section class="school-panel">
                <label for="comment" class="school-label"
                    >Constructive feedback (optional)</label
                ><textarea
                    id="comment"
                    v-model="answers.comment"
                    :disabled="adminView || answers.processing"
                    rows="4"
                    maxlength="2000"
                    class="school-input"
                    placeholder="What helped your learning? What could be improved?"
                />
                <p class="mt-2 text-xs text-slate-500">
                    {{ answers.comment.length }} / 2000 characters
                </p>
            </section>
            <Button
                v-if="!adminView"
                type="submit"
                class="h-12"
                :disabled="answers.processing"
                >{{
                    answers.processing
                        ? 'Submitting…'
                        : 'Submit final evaluation'
                }}</Button
            >
        </form>
        <Dialog
            v-if="!adminView"
            :open="confirmationOpen"
            @update:open="updateConfirmation"
        >
            <DialogContent
                :show-close-button="!answers.processing"
                class="sm:max-w-md"
                @escape-key-down="preventDismissWhileSubmitting"
                @interact-outside="preventDismissWhileSubmitting"
                @close-auto-focus="restoreFocus"
            >
                <DialogHeader>
                    <DialogTitle>Submit your evaluation?</DialogTitle>
                    <DialogDescription
                        >Your answers will be final. You cannot edit them after
                        submission.</DialogDescription
                    >
                </DialogHeader>
                <div
                    class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <p class="text-sm font-semibold">
                        {{ assignment.teacher }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ period.name }}
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="answers.processing"
                        @click="updateConfirmation(false)"
                        >Review answers</Button
                    >
                    <Button
                        type="button"
                        class="bg-[#185b94] text-white hover:bg-[#104675] dark:bg-blue-500 dark:hover:bg-blue-600"
                        :disabled="answers.processing"
                        @click="confirmSubmission"
                    >
                        <Spinner v-if="answers.processing" />{{
                            answers.processing
                                ? 'Submitting…'
                                : 'Submit evaluation'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </SchoolPage>
</template>
