import { PublicShell } from '@/layouts/public-shell';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs: _breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return <PublicShell width="wide">{children}</PublicShell>;
}
