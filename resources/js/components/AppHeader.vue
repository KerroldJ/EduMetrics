<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, Menu } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import LogoutDialog from '@/components/LogoutDialog.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { dashboard } from '@/routes';
import { useSchoolNavigationGroups } from '@/composables/useSchoolNavigation';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const { isCurrentUrl } = useCurrentUrl();
const navigationGroups = useSchoolNavigationGroups();
const logoutDialogOpen = ref(false);
function preserveDialogFocus(event: Event) {
    if (logoutDialogOpen.value) event.preventDefault();
}
</script>

<template>
    <div
        class="sticky top-0 z-20 bg-background/95 backdrop-blur-xs print:hidden"
    >
        <div class="border-b border-sidebar-border/80">
            <div class="mx-auto flex h-16 items-center gap-4 px-4 md:max-w-7xl">
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Open navigation"
                            >
                                <Menu class="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            class="w-[300px] overflow-y-auto p-6"
                        >
                            <SheetHeader>
                                <SheetTitle class="sr-only"
                                    >School navigation</SheetTitle
                                >
                                <AppLogo />
                            </SheetHeader>
                            <nav
                                class="mt-6 space-y-6"
                                aria-label="School portal"
                            >
                                <section
                                    v-for="group in navigationGroups"
                                    :key="group.id"
                                >
                                    <h2
                                        class="mb-2 text-xs font-medium text-muted-foreground"
                                    >
                                        {{ group.title }}
                                    </h2>
                                    <div class="space-y-1">
                                        <Link
                                            v-for="item in group.items"
                                            :key="item.title"
                                            :href="item.href"
                                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-accent"
                                            :class="{
                                                'bg-accent font-medium':
                                                    isCurrentUrl(item.href),
                                            }"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="size-4"
                                            />{{ item.title }}
                                        </Link>
                                    </div>
                                </section>
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link :href="dashboard()" aria-label="EduMetric dashboard"
                    ><AppLogo
                /></Link>
                <nav
                    class="ml-6 hidden items-center gap-2 lg:flex"
                    aria-label="School portal"
                >
                    <template v-for="group in navigationGroups" :key="group.id">
                        <Link
                            v-if="group.id === 'overview'"
                            :href="group.items[0].href"
                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-accent"
                            :class="{
                                'bg-accent font-medium': isCurrentUrl(
                                    group.items[0].href,
                                ),
                            }"
                            ><component
                                :is="group.items[0].icon"
                                class="size-4"
                            />{{ group.items[0].title }}</Link
                        >
                        <DropdownMenu v-else>
                            <DropdownMenuTrigger as-child
                                ><Button
                                    variant="ghost"
                                    class="gap-2"
                                    :class="{
                                        'bg-accent': group.items.some((item) =>
                                            isCurrentUrl(item.href),
                                        ),
                                    }"
                                    >{{ group.title
                                    }}<ChevronDown
                                        class="size-3.5"
                                        aria-hidden="true" /></Button
                            ></DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-60">
                                <DropdownMenuItem
                                    v-for="item in group.items"
                                    :key="item.title"
                                    as-child
                                    ><Link
                                        :href="item.href"
                                        class="flex items-center gap-2"
                                        :class="{
                                            'bg-accent font-medium':
                                                isCurrentUrl(item.href),
                                        }"
                                        ><component
                                            :is="item.icon"
                                            class="size-4"
                                        />{{ item.title }}</Link
                                    ></DropdownMenuItem
                                >
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>
                </nav>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="ml-auto rounded-full"
                            aria-label="Open account menu"
                            id="header-account-menu-trigger"
                        >
                            <Avatar class="size-8">
                                <AvatarImage
                                    v-if="auth.user.avatar"
                                    :src="auth.user.avatar"
                                    :alt="auth.user.name"
                                />
                                <AvatarFallback>{{
                                    getInitials(auth.user.name)
                                }}</AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        class="w-56"
                        @close-auto-focus="preserveDialogFocus"
                    >
                        <UserMenuContent
                            :user="auth.user"
                            @logout="logoutDialogOpen = true"
                        />
                    </DropdownMenuContent>
                </DropdownMenu>
                <LogoutDialog
                    v-model:open="logoutDialogOpen"
                    return-focus-id="header-account-menu-trigger"
                />
            </div>
        </div>
        <div
            v-if="props.breadcrumbs.length > 0"
            class="border-b border-sidebar-border/70"
        >
            <div class="mx-auto flex h-12 items-center px-4 md:max-w-7xl">
                <Breadcrumbs :breadcrumbs="props.breadcrumbs" />
            </div>
        </div>
    </div>
</template>
