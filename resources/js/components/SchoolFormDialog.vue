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
    }>(),
    { processing: false, wide: false },
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
        <DialogContent
            :show-close-button="!processing"
            class="flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden p-0 motion-reduce:animate-none"
            :class="wide ? 'sm:max-w-3xl' : 'sm:max-w-lg'"
            @escape-key-down="preventDismissWhileSaving"
            @interact-outside="preventDismissWhileSaving"
            @close-auto-focus="restoreFocus"
        >
            <DialogHeader class="shrink-0 border-b px-6 py-5 pr-12 text-left">
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription class="leading-6">{{
                    description
                }}</DialogDescription>
            </DialogHeader>
            <div class="min-h-0 overflow-y-auto px-6 py-5"><slot /></div>
            <DialogFooter
                class="shrink-0 border-t bg-slate-50 px-6 py-4 dark:bg-slate-900"
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
        </DialogContent>
    </Dialog>
</template>
