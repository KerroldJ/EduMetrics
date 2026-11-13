import { usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    ClipboardList,
    FileText,
    LayoutGrid,
    School,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { dashboard } from '@/routes';
import { index as users } from '@/routes/users';
import { index as strands } from '@/routes/strands';
import { index as forms } from '@/routes/forms';
import { index as periods } from '@/routes/periods';
import { index as reports } from '@/routes/reports';
import { index as evaluations } from '@/routes/evaluations';
import { index as results } from '@/routes/results';
import type { NavGroup, NavItem } from '@/types';

type PermissionItem = NavItem & {
    permission?: string;
    anyPermissions?: string[];
};

export function useSchoolNavigationGroups() {
    const page = usePage();
    return computed<NavGroup[]>(() => {
        const permissions = page.props.permissions ?? [];
        const groups: { id: string; title: string; items: PermissionItem[] }[] =
            [
                {
                    id: 'overview',
                    title: 'Overview',
                    items: [
                        {
                            title: 'Dashboard',
                            href: dashboard(),
                            icon: LayoutGrid,
                        },
                    ],
                },
                {
                    id: 'my-evaluations',
                    title:
                        permissions.includes('evaluations.monitor') ||
                        permissions.includes('results.view-all')
                            ? 'Evaluation portals'
                            : 'My Evaluations',
                    items: [
                        {
                            title: permissions.includes('evaluations.monitor')
                                ? 'Student evaluations'
                                : 'Evaluate teachers',
                            href: evaluations(),
                            icon: ClipboardList,
                            anyPermissions: [
                                'evaluations.submit',
                                'evaluations.monitor',
                            ],
                        },
                        {
                            title: permissions.includes('results.view-all')
                                ? 'Teacher results'
                                : 'My results',
                            href: results(),
                            icon: FileText,
                            anyPermissions: [
                                'results.view-own',
                                'results.view-all',
                            ],
                        },
                    ],
                },
                {
                    id: 'evaluation-management',
                    title: 'Evaluation Management',
                    items: [
                        {
                            title: 'Evaluation periods',
                            href: periods(),
                            icon: CalendarDays,
                            permission: 'periods.manage',
                        },
                        {
                            title: 'Evaluation templates',
                            href: forms(),
                            icon: ClipboardList,
                            permission: 'forms.manage',
                        },
                    ],
                },
                {
                    id: 'administration',
                    title: 'Administration',
                    items: [
                        {
                            title: 'Configuration',
                            href: strands(),
                            icon: School,
                            anyPermissions: [
                                'strands.manage',
                                'assignments.manage',
                            ],
                        },
                        {
                            title: 'User Management',
                            href: users(),
                            icon: Users,
                            permission: 'users.manage',
                        },
                        {
                            title: 'Reports and results',
                            href: reports(),
                            icon: FileText,
                            permission: 'results.view-all',
                        },
                    ],
                },
            ];
        return groups
            .map((group) => ({
                ...group,
                items: group.items.filter((item) =>
                    item.anyPermissions
                        ? item.anyPermissions.some((permission) =>
                              permissions.includes(permission),
                          )
                        : !item.permission ||
                          permissions.includes(item.permission),
                ),
            }))
            .filter((group) => group.items.length > 0);
    });
}

export function useSchoolNavigation() {
    const groups = useSchoolNavigationGroups();
    return computed<NavItem[]>(() =>
        groups.value.flatMap((group) => group.items),
    );
}
