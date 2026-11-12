<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ errors: object }>();
const messages = computed(() => [
    ...new Set(Object.values(props.errors).flat().filter(Boolean).map(String)),
]);
</script>
<template>
    <div
        v-if="messages.length"
        role="alert"
        class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"
    >
        <ul class="list-inside list-disc">
            <li v-for="message in messages" :key="message">{{ message }}</li>
        </ul>
    </div>
</template>
