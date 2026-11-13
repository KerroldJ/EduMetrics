<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
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
import { logout } from '@/routes';

const props = defineProps<{ open: boolean; returnFocusId: string }>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
const processing = ref(false);
const error = ref('');

watch(
    () => props.open,
    (open) => {
        if (open) error.value = '';
    },
);

function updateOpen(open: boolean) {
    if (!open && processing.value) return;
    emit('update:open', open);
}
function preventDismissWhileProcessing(event: Event) {
    if (processing.value) event.preventDefault();
}
function focusCancel(event: Event) {
    const cancel = document.getElementById(
        `${props.returnFocusId}-logout-cancel`,
    );
    if (cancel) {
        event.preventDefault();
        cancel.focus({ preventScroll: true });
    }
}
function restoreFocus(event: Event) {
    const trigger = document.getElementById(props.returnFocusId);
    if (trigger) {
        event.preventDefault();
        trigger.focus({ preventScroll: true });
    }
}
function showError(message = 'Unable to log out. Please try again.') {
    error.value = message;
    processing.value = false;
}
function confirmLogout() {
    if (processing.value) return;
    processing.value = true;
    error.value = '';
    try {
        router.flushAll();
        router.post(
            logout.url(),
            {},
            {
                onSuccess: () => emit('update:open', false),
                onError: (errors) => showError(Object.values(errors)[0]),
                onHttpException: () => {
                    showError();
                    return false;
                },
                onNetworkError: () => {
                    showError();
                    return false;
                },
                onFinish: () => {
                    processing.value = false;
                },
            },
        );
    } catch {
        showError();
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="updateOpen">
        <DialogContent
            :show-close-button="!processing"
            :aria-busy="processing"
            class="sm:max-w-md"
            @escape-key-down="preventDismissWhileProcessing"
            @interact-outside="preventDismissWhileProcessing"
            @open-auto-focus="focusCancel"
            @close-auto-focus="restoreFocus"
        >
            <DialogHeader>
                <DialogTitle>Log out of EduMetric?</DialogTitle>
                <DialogDescription
                    >You can sign in again whenever you need to
                    return.</DialogDescription
                >
            </DialogHeader>
            <p
                v-if="error"
                role="alert"
                class="text-sm text-red-700 dark:text-red-300"
            >
                {{ error }}
            </p>
            <DialogFooter>
                <Button
                    :id="`${returnFocusId}-logout-cancel`"
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    @click="updateOpen(false)"
                    >Cancel</Button
                >
                <Button
                    type="button"
                    :disabled="processing"
                    @click="confirmLogout"
                    data-test="confirm-logout-button"
                >
                    <Spinner v-if="processing" aria-hidden="true" />{{
                        processing ? 'Logging out…' : 'Log out'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
