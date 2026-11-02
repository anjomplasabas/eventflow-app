import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { Auth, NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    LayoutGrid,
    Settings,
    ShieldCheck,
    Store,
    Ticket,
} from 'lucide-react';

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const memberships = auth.organizer_memberships ?? [];
    const isAdmin = auth.is_system_admin ?? false;
    const primaryOrganizer = memberships[0];

    const platformItems: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Browse events', href: '/events', icon: CalendarDays },
        { title: 'My registrations', href: '/me/registrations', icon: Ticket },
    ];

    const organizerItems: NavItem[] = primaryOrganizer
        ? [
              {
                  title: primaryOrganizer.name,
                  href: `/organizer/${primaryOrganizer.slug}/events`,
                  icon: Store,
              },
          ]
        : [];

    const adminItems: NavItem[] = isAdmin
        ? [{ title: 'Users', href: '/admin/users', icon: ShieldCheck }]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="Platform" items={platformItems} />
                {organizerItems.length > 0 && (
                    <NavMain label="Organizer" items={organizerItems} />
                )}
                {adminItems.length > 0 && (
                    <NavMain label="Admin" items={adminItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            className="text-muted-foreground hover:bg-sidebar-accent/60 hover:text-sidebar-accent-foreground"
                        >
                            <Link href="/settings/profile">
                                <Settings />
                                <span>Settings</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
