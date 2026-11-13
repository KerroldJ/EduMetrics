<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LogOut, Moon, Settings, Sun } from '@lucide/vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useAppearance } from '@/composables/useAppearance';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleAppearance = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

defineProps<Props>();
const emit = defineEmits<{ logout: [] }>();
</script>

<template>
    <div>
        <DropdownMenuLabel class="p-0 font-normal">
            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                <UserInfo :user="user" :show-email="true" />
            </div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuGroup>
            <DropdownMenuItem :as-child="true">
                <Link
                    class="block w-full cursor-pointer"
                    :href="edit()"
                    prefetch
                >
                    <Settings class="mr-2 h-4 w-4" />
                    Update profile
                </Link>
            </DropdownMenuItem>
        </DropdownMenuGroup>
        <DropdownMenuItem class="cursor-pointer" @select="toggleAppearance">
            <Sun
                v-if="resolvedAppearance === 'dark'"
                class="mr-2 h-4 w-4"
                aria-hidden="true"
            />
            <Moon v-else class="mr-2 h-4 w-4" aria-hidden="true" />
            {{ resolvedAppearance === 'dark' ? 'Light mode' : 'Dark mode' }}
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem :as-child="true" @select="emit('logout')">
            <button
                class="block w-full cursor-pointer"
                type="button"
                data-test="logout-button"
            >
                <LogOut class="mr-2 h-4 w-4" />
                Log out
            </button>
        </DropdownMenuItem>
    </div>
</template>
