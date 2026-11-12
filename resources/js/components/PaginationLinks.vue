<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PageLink } from '@/types/school';
defineProps<{ links: PageLink[] }>();
function label(value: string) {
    return value.replace('&laquo;', '«').replace('&raquo;', '»');
}
</script>
<template>
    <nav
        v-if="links.length > 3"
        aria-label="Pagination"
        class="flex flex-wrap gap-1 py-4"
    >
        <template v-for="(link, index) in links" :key="index"
            ><Link
                v-if="link.url"
                :href="link.url"
                class="rounded-md border px-3 py-1.5 text-sm"
                :class="
                    link.active
                        ? 'border-blue-700 bg-blue-700 text-white'
                        : 'bg-white dark:bg-slate-900'
                "
                :aria-current="link.active ? 'page' : undefined"
                preserve-scroll
                >{{ label(link.label) }}</Link
            ><span v-else class="px-3 py-1.5 text-sm text-slate-400">{{
                label(link.label)
            }}</span></template
        >
    </nav>
</template>
