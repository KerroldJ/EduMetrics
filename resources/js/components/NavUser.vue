<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import LogoutDialog from '@/components/LogoutDialog.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isMobile, state } = useSidebar();
const logoutDialogOpen = ref(false);
function preserveDialogFocus(event: Event) {
    if (logoutDialogOpen.value) event.preventDefault();
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        id="sidebar-account-menu-trigger"
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo :user="user" />
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                    @close-auto-focus="preserveDialogFocus"
                >
                    <UserMenuContent
                        :user="user"
                        @logout="logoutDialogOpen = true"
                    />
                </DropdownMenuContent>
            </DropdownMenu>
            <LogoutDialog
                v-model:open="logoutDialogOpen"
                return-focus-id="sidebar-account-menu-trigger"
            />
        </SidebarMenuItem>
    </SidebarMenu>
</template>
