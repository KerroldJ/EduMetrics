<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
defineProps<{ title: string; description?: string }>();
const page = usePage();
</script>
<template>
    <div
        class="min-h-full flex-1 bg-[#f5f8fc] p-4 text-slate-900 sm:p-6 lg:p-8 dark:bg-slate-950 dark:text-slate-100"
    >
        <Head :title="title" />
        <div class="mx-auto flex max-w-7xl flex-col gap-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                    >
                        {{ description }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2"><slot name="actions" /></div>
            </header>
            <p
                v-if="page.props.notice"
                role="status"
                class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
            >
                {{ page.props.notice }}
            </p>
            <slot />
        </div>
    </div>
</template>
