<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check, GraduationCap, LockKeyhole } from '@lucide/vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="grid min-h-svh bg-[#f5f8fc] text-[#152d46] lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)] dark:bg-slate-950 dark:text-slate-100"
    >
        <aside
            class="relative flex flex-col overflow-hidden bg-[#102f50] px-6 py-6 text-white sm:px-10 lg:px-14 lg:py-10 xl:px-20"
        >
            <Link
                :href="home()"
                class="relative flex w-fit items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white"
                aria-label="EduMetric home"
            >
                <img
                    src="/logo.png"
                    alt="EduMetric"
                    width="640"
                    height="517"
                    class="h-24 w-28 rounded-xl bg-white object-contain p-2"
                />
                <div>
                    <span
                        class="block max-w-44 text-base leading-snug font-semibold"
                        >EduMetric</span
                    >
                    <span class="block text-xs text-blue-100/80"
                        >Teacher evaluation portal</span
                    >
                </div>
            </Link>

            <div
                class="relative hidden flex-1 flex-col justify-center py-12 lg:flex"
            >
                <div
                    class="mb-8 flex items-center gap-2 text-sm text-blue-100/80"
                >
                    <span class="h-px w-8 bg-[#e4b968]" />
                    Teacher performance evaluation
                </div>
                <h2
                    class="max-w-md text-5xl leading-[1.12] font-semibold tracking-tight xl:text-6xl"
                >
                    Thoughtful feedback.<br />
                    Stronger teaching.
                </h2>
                <p class="mt-6 max-w-sm text-base leading-7 text-blue-100/80">
                    A shared space for students, faculty, and administrators to
                    support better learning throughout your school.
                </p>

                <div
                    class="mt-12 max-w-sm rounded-2xl border border-white/15 bg-white/[0.06] p-6"
                >
                    <div
                        class="flex items-center gap-3 border-b border-white/15 pb-5"
                    >
                        <div
                            class="flex size-10 items-center justify-center rounded-full bg-[#e4b968] text-[#102f50]"
                        >
                            <GraduationCap class="size-5" aria-hidden="true" />
                        </div>
                        <div>
                            <p class="text-sm font-medium">
                                Every perspective matters
                            </p>
                            <p class="mt-1 text-xs text-blue-100/70">
                                A school community that grows together
                            </p>
                        </div>
                    </div>
                    <ul class="mt-5 flex flex-col gap-4 text-sm text-blue-50">
                        <li class="flex items-start gap-3">
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-[#e4b968]"
                                aria-hidden="true"
                            />
                            Students share meaningful feedback
                        </li>
                        <li class="flex items-start gap-3">
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-[#e4b968]"
                                aria-hidden="true"
                            />
                            Faculty reflect on their teaching
                        </li>
                        <li class="flex items-start gap-3">
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-[#e4b968]"
                                aria-hidden="true"
                            />
                            Administrators guide the evaluation process
                        </li>
                    </ul>
                </div>
            </div>

            <p
                class="relative hidden items-center gap-2 text-xs text-blue-100/70 lg:flex"
            >
                <LockKeyhole class="size-3.5" aria-hidden="true" />
                For your school community
            </p>
        </aside>

        <main
            class="flex flex-col justify-center px-6 py-10 sm:px-10 lg:px-14 lg:py-12"
        >
            <div class="mx-auto w-full max-w-[420px]">
                <div class="mb-8">
                    <p
                        class="mb-3 text-sm font-medium text-[#376d9d] dark:text-blue-300"
                    >
                        Your school evaluation portal
                    </p>
                    <h1
                        v-if="title"
                        class="text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400"
                    >
                        {{ description }}
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900"
                >
                    <slot />
                </div>

                <p
                    class="mt-7 text-center text-xs leading-5 text-slate-500 dark:text-slate-400"
                >
                    Need help with your account?<br class="sm:hidden" />
                    Contact your school administrator.
                </p>
            </div>
        </main>
    </div>
</template>
