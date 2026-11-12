<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import {
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    id: string;
    modelValue?: string | number | boolean | null;
    required?: boolean;
    invalid?: boolean;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
const minimumYear = 2000;
const maximumYear = 2098;
const currentYear = Math.min(
    maximumYear,
    Math.max(minimumYear, new Date().getFullYear()),
);
const open = ref(false);
const startYear = computed(() =>
    typeof props.modelValue === 'string' ? props.modelValue.slice(0, 4) : '',
);
const endYear = computed(() =>
    startYear.value ? Number(startYear.value) + 1 : '',
);
function pageFor(year: number) {
    return minimumYear + Math.floor((year - minimumYear) / 12) * 12;
}
const pageStart = ref(
    pageFor(startYear.value ? Number(startYear.value) : currentYear),
);
const years = computed(() =>
    Array.from(
        { length: Math.min(12, maximumYear - pageStart.value + 1) },
        (_, index) => pageStart.value + index,
    ),
);
watch(open, (isOpen) => {
    if (isOpen) {
        pageStart.value = pageFor(
            startYear.value ? Number(startYear.value) : currentYear,
        );
    }
});
function selectYear(year: number) {
    emit('update:modelValue', `${year}-${year + 1}`);
    open.value = false;
}
function focusYear() {
    void nextTick(() => {
        document
            .getElementById(
                `${props.id}-year-${startYear.value || currentYear}`,
            )
            ?.focus();
    });
}
function requireYear() {
    open.value = true;
    focusYear();
}
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="grid gap-1.5">
            <label :for="id" class="text-xs text-slate-500 dark:text-slate-400">
                Start year
            </label>
            <PopoverRoot v-model:open="open">
                <PopoverTrigger as-child>
                    <Button
                        :id="id"
                        type="button"
                        variant="outline"
                        class="school-input justify-start gap-2 font-normal"
                        :aria-invalid="invalid"
                        :aria-describedby="`${id}-year-help`"
                    >
                        <CalendarDays
                            class="size-4 text-slate-500"
                            aria-hidden="true"
                        />
                        {{ startYear || 'Choose a year' }}
                    </Button>
                </PopoverTrigger>
                <PopoverPortal>
                    <PopoverContent
                        align="start"
                        :side-offset="8"
                        :collision-padding="16"
                        class="z-60 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-slate-200 bg-white p-4 text-slate-900 shadow-xl outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                        aria-label="Choose the starting calendar year"
                        @open-auto-focus.prevent="focusYear"
                    >
                        <div
                            class="mb-3 flex items-center justify-between gap-2"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Previous 12 years"
                                :disabled="pageStart === minimumYear"
                                @click="pageStart -= 12"
                                ><ChevronLeft class="size-4" aria-hidden="true"
                            /></Button>
                            <span
                                class="text-sm font-semibold"
                                aria-live="polite"
                                >{{ pageStart }}–{{
                                    years[years.length - 1]
                                }}</span
                            >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Next 12 years"
                                :disabled="pageStart + 12 > maximumYear"
                                @click="pageStart += 12"
                                ><ChevronRight
                                    class="size-4"
                                    aria-hidden="true"
                            /></Button>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <Button
                                v-for="year in years"
                                :id="`${id}-year-${year}`"
                                :key="year"
                                type="button"
                                variant="ghost"
                                class="h-10"
                                :class="[
                                    Number(startYear) === year
                                        ? 'bg-[#185b94] text-white hover:bg-[#104675] hover:text-white'
                                        : '',
                                    year === currentYear
                                        ? 'font-semibold'
                                        : 'font-normal',
                                ]"
                                :aria-pressed="Number(startYear) === year"
                                :aria-label="
                                    year === currentYear
                                        ? `${year}, current year`
                                        : String(year)
                                "
                                @click="selectYear(year)"
                                >{{ year }}</Button
                            >
                        </div>
                        <div
                            class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="w-full text-[#185b94] dark:text-blue-300"
                                @click="selectYear(currentYear)"
                                >Use current year · {{ currentYear }}</Button
                            >
                        </div>
                    </PopoverContent>
                </PopoverPortal>
            </PopoverRoot>
            <input
                :value="startYear"
                :required="required"
                class="sr-only"
                tabindex="-1"
                aria-label="Selected starting year"
                @invalid.prevent="requireYear"
            />
        </div>
        <div class="grid gap-1.5">
            <label
                :for="`${id}-end`"
                class="text-xs text-slate-500 dark:text-slate-400"
            >
                End year
            </label>
            <input
                :id="`${id}-end`"
                :value="endYear"
                class="school-input bg-slate-50 dark:bg-slate-900"
                placeholder="Set automatically"
                readonly
                :aria-describedby="`${id}-year-help`"
            />
        </div>
        <p
            :id="`${id}-year-help`"
            class="text-xs text-slate-500 sm:col-span-2 dark:text-slate-400"
            aria-live="polite"
        >
            {{
                startYear
                    ? `School year: ${startYear}–${endYear}`
                    : 'Choose the starting calendar year. The end year is set automatically.'
            }}
        </p>
    </div>
</template>
