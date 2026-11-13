<script setup lang="ts">
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { shallowRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { EvaluationTemplate } from '@/types/school';

const props = defineProps<{
    open: boolean;
    template: EvaluationTemplate | null;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
const previousFocus = shallowRef<HTMLElement | null>(null);
watch(
    () => props.open,
    (open) => {
        if (open && typeof document !== 'undefined') {
            previousFocus.value =
                document.activeElement instanceof HTMLElement
                    ? document.activeElement
                    : null;
        }
    },
);
function restoreFocus(event: Event) {
    if (previousFocus.value?.isConnected) {
        event.preventDefault();
        previousFocus.value.focus({ preventScroll: true });
    }
}
const tabClass =
    'border-b-2 border-transparent px-1 py-3 text-sm text-slate-500 outline-none focus-visible:ring-2 focus-visible:ring-blue-500 data-[state=active]:border-[#185b94] data-[state=active]:font-medium data-[state=active]:text-[#185b94] dark:text-slate-400 dark:data-[state=active]:text-blue-300';
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            class="h-dvh w-full gap-0 overflow-hidden p-0 motion-reduce:animate-none sm:max-w-3xl"
            @close-auto-focus="restoreFocus"
        >
            <template v-if="template">
                <SheetHeader class="shrink-0 gap-2 border-b px-6 py-5 pr-12">
                    <p
                        class="text-sm font-medium text-[#185b94] dark:text-blue-300"
                    >
                        Template preview
                    </p>
                    <SheetTitle class="text-xl leading-7">{{
                        template.name
                    }}</SheetTitle>
                    <SheetDescription>
                        Version {{ template.version }}.
                        {{
                            template.is_active
                                ? 'Available for new evaluation periods.'
                                : 'Retired from new evaluation periods.'
                        }}
                    </SheetDescription>
                </SheetHeader>
                <div class="min-h-0 flex-1 overflow-y-auto px-6 pb-6">
                    <dl class="grid grid-cols-3 gap-4 border-b py-5 text-sm">
                        <div>
                            <dt
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Questions
                            </dt>
                            <dd class="mt-1 font-semibold">
                                {{ template.criteria.length }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Rating scale
                            </dt>
                            <dd class="mt-1 font-semibold">
                                {{ template.scale_min }}–{{
                                    template.scale_max
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Used in
                            </dt>
                            <dd class="mt-1 font-semibold">
                                {{ template.periods_count }}
                                {{
                                    template.periods_count === 1
                                        ? 'period'
                                        : 'periods'
                                }}
                            </dd>
                        </div>
                    </dl>
                    <TabsRoot
                        :key="template.id"
                        default-value="questions"
                        class="min-w-0"
                    >
                        <TabsList
                            class="mb-5 flex gap-6 overflow-x-auto border-b"
                            aria-label="Template details"
                        >
                            <TabsTrigger value="questions" :class="tabClass"
                                >Questions</TabsTrigger
                            >
                            <TabsTrigger value="instructions" :class="tabClass"
                                >Instructions &amp; ratings</TabsTrigger
                            >
                            <TabsTrigger value="usage" :class="tabClass"
                                >Usage</TabsTrigger
                            >
                        </TabsList>
                        <TabsContent value="questions" class="outline-none">
                            <ol
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <li
                                    v-for="(
                                        criterion, questionIndex
                                    ) in template.criteria"
                                    :key="questionIndex"
                                    class="flex items-start gap-4 py-4 first:pt-0"
                                >
                                    <span
                                        class="flex size-7 shrink-0 items-center justify-center rounded-md bg-slate-100 text-xs font-medium text-slate-600 tabular-nums dark:bg-slate-800 dark:text-slate-300"
                                        aria-hidden="true"
                                        >{{ questionIndex + 1 }}</span
                                    >
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm leading-6">
                                            {{ criterion.prompt }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Weight
                                            {{ Number(criterion.weight) }}
                                        </p>
                                    </div>
                                </li>
                            </ol>
                            <p
                                v-if="!template.criteria.length"
                                class="py-8 text-center text-sm text-slate-500"
                            >
                                No questions have been added to this version.
                            </p>
                        </TabsContent>
                        <TabsContent
                            value="instructions"
                            class="space-y-7 outline-none"
                        >
                            <section>
                                <h3 class="mb-3 text-sm font-semibold">
                                    Student instructions
                                </h3>
                                <div
                                    v-if="template.description"
                                    class="space-y-4 text-sm leading-7 text-slate-600 dark:text-slate-300"
                                >
                                    <p
                                        v-for="(
                                            paragraph, paragraphIndex
                                        ) in template.description.split(
                                            /\n\s*\n/,
                                        )"
                                        :key="paragraphIndex"
                                        class="whitespace-pre-line"
                                    >
                                        {{ paragraph }}
                                    </p>
                                </div>
                                <p v-else class="text-sm text-slate-500">
                                    No additional instructions for this version.
                                </p>
                            </section>
                        </TabsContent>
                        <TabsContent value="usage" class="outline-none">
                            <p
                                class="mb-4 text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                These periods use this exact version. Creating a
                                new version keeps their questionnaire and
                                results intact.
                            </p>
                            <ul
                                v-if="template.periods.length"
                                class="divide-y rounded-lg border px-4"
                            >
                                <li
                                    v-for="period in template.periods"
                                    :key="period.id"
                                    class="flex flex-wrap items-start justify-between gap-3 py-4"
                                >
                                    <div>
                                        <p class="text-sm font-medium">
                                            {{ period.name }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            School year {{ period.school_year }}
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600 capitalize dark:bg-slate-800 dark:text-slate-300"
                                        >{{ period.status }}</span
                                    >
                                </li>
                            </ul>
                            <p
                                v-else
                                class="rounded-lg border border-dashed p-6 text-center text-sm text-slate-500"
                            >
                                This version has not been assigned to a period
                                yet.
                            </p>
                        </TabsContent>
                    </TabsRoot>
                </div>
                <SheetFooter
                    class="shrink-0 flex-row items-center justify-between gap-4 border-t bg-slate-50 px-6 py-4 dark:bg-slate-900"
                >
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{
                            template.is_locked
                                ? 'This version is locked to preserve its history.'
                                : 'This draft version can still be edited.'
                        }}
                    </p>
                    <SheetClose as-child
                        ><Button type="button" variant="outline"
                            >Close preview</Button
                        ></SheetClose
                    >
                </SheetFooter>
            </template>
        </SheetContent>
    </Sheet>
</template>
