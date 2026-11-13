<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/composables/useInitials';

const props = defineProps<{
    id: string;
    name?: string;
    currentPhoto: string | null;
    error?: string;
}>();
const photo = defineModel<File | null>({ required: true });
const removed = defineModel<boolean>('removed', { required: true });
const { getInitials } = useInitials();
const input = ref<HTMLInputElement | null>(null);
const localError = ref('');
const objectUrl = ref<string | null>(null);
const preview = computed(
    () => objectUrl.value ?? (removed.value ? null : props.currentPhoto),
);

watch(
    photo,
    (file, _, onCleanup) => {
        objectUrl.value = file ? URL.createObjectURL(file) : null;
        if (objectUrl.value) {
            const url = objectUrl.value;
            onCleanup(() => URL.revokeObjectURL(url));
        }
    },
    { immediate: true },
);

function choosePhoto(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    target.value = '';
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        localError.value = 'Choose a JPG, PNG, or WebP image.';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        localError.value = 'Choose an image no larger than 2 MB.';
        return;
    }
    localError.value = '';
    removed.value = false;
    photo.value = file;
}

function removePhoto() {
    photo.value = null;
    removed.value = true;
    localError.value = '';
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <label :for="id" class="text-sm font-medium">Profile picture</label>
        <div class="flex items-center gap-4">
            <Avatar class="size-20 rounded-xl" aria-hidden="true">
                <AvatarImage
                    v-if="preview"
                    :src="preview"
                    alt=""
                    class="object-cover"
                />
                <AvatarFallback
                    class="rounded-xl bg-blue-50 text-xl font-semibold text-[#185b94] dark:bg-blue-950 dark:text-blue-200"
                    >{{ getInitials(name) || '?' }}</AvatarFallback
                >
            </Avatar>
            <div class="flex min-w-0 flex-1 flex-col gap-2">
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="input?.click()"
                        >{{ preview ? 'Change photo' : 'Upload photo' }}</Button
                    >
                    <Button
                        v-if="preview"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="removePhoto"
                        >Remove</Button
                    >
                </div>
                <p
                    :id="id + '-hint'"
                    class="text-xs leading-5 text-slate-500 dark:text-slate-400"
                >
                    JPG, PNG, or WebP · Up to 2 MB and 4000 × 4000 pixels.<br />The
                    picture is saved when you save the user.
                </p>
            </div>
        </div>
        <input
            :id="id"
            ref="input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="sr-only"
            tabindex="-1"
            :aria-describedby="
                id + '-hint' + (localError || error ? ' ' + id + '-error' : '')
            "
            :aria-invalid="Boolean(localError || error)"
            @change="choosePhoto"
        />
        <p
            v-if="localError || error"
            :id="id + '-error'"
            role="alert"
            class="text-sm text-red-600 dark:text-red-400"
        >
            {{ localError || error }}
        </p>
    </div>
</template>
