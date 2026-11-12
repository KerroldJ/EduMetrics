<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/logo-mark.png"
        alt="EduMetric"
        :class="['object-contain', className]"
        v-bind="$attrs"
    />
</template>
