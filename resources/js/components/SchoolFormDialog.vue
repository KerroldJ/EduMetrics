<script setup lang="ts">
import { shallowRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { SheetContent } from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description: string;
        formId: string;
        submitLabel: string;
        processing?: boolean;
        wide?: boolean;
        drawer?: boolean;
    }>(),
    { processing: false, wide: false, drawer: false },
);
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
function updateOpen(open: boolean) {
    if (!open && props.processing) return;
    emit('update:open', open);
}
function preventDismissWhileSaving(event: Event) {
    if (props.processing) event.preventDefault();
}
function focusWithoutSelecting(event: Event) {
    const field = document
        .getElementById(props.formId)
        ?.querySelector<HTMLElement>(
            'input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), select:not([disabled]), button:not([disabled])',
        );
    if (field) {
        event.preventDefault();
        field.focus({ preventScroll: true });
    }
}
function restoreFocus(event: Event) {
    if (previousFocus.value?.isConnected) {
        event.preventDefault();
        previousFocus.value.focus();
    }
}
</script>
<template>
    <Dialog :open="open" @update:open="updateOpen">
        <DialogTrigger as-child><slot name="trigger" /></DialogTrigger>
        <component
            :is="drawer ? SheetContent : DialogContent"
            :show-close-button="!processing"
            class="flex min-w-0 flex-col gap-0 overflow-hidden p-0 motion-reduce:animate-none"
            :class="[
                drawer ? 'h-dvh max-h-dvh w-full' : 'max-h-[calc(100dvh-2rem)]',
                wide ? 'sm:max-w-3xl' : 'sm:max-w-lg',
            ]"
            @escape-key-down="preventDismissWhileSaving"
            @interact-outside="preventDismissWhileSaving"
            @close-auto-focus="restoreFocus"
            @open-auto-focus="focusWithoutSelecting"
        >
            <DialogHeader
                class="min-w-0 shrink-0 border-b px-4 py-5 pr-12 text-left sm:px-6 sm:pr-12"
            >
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription class="leading-6">{{
                    description
                }}</DialogDescription>
            </DialogHeader>
            <div
                class="min-h-0 min-w-0 overflow-x-hidden overflow-y-auto overscroll-contain px-4 py-5 sm:px-6"
                :class="drawer ? 'flex-1' : undefined"
            >
                <slot />
            </div>
            <DialogFooter
                class="shrink-0 border-t bg-slate-50 px-4 py-4 sm:px-6 dark:bg-slate-900"
            >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    @click="updateOpen(false)"
                    >Cancel</Button
                >
                <Button
                    type="submit"
                    :form="formId"
                    :disabled="processing"
                    class="bg-[#185b94] text-white hover:bg-[#104675]"
                >
                    <Spinner v-if="processing" />{{
                        processing ? 'Saving…' : submitLabel
                    }}
                </Button>
            </DialogFooter>
        </component>
    </Dialog>
</template>
