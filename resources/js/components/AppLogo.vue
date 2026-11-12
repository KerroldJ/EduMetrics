<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex w-full min-w-0 items-center gap-2">
        <div
            class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white"
        >
            <AppLogoIcon class="size-8" alt="" />
        </div>
        <div class="grid min-w-0 flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-semibold"
                >EduMetric</span
            >
        </div>
    </div>
</template>
