import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { companyQuickActions, useCompanyBrand } from '@/modules/company';
import { DashboardHero, TodaysSalesTable } from '@/modules/dashboard';
import type { DashboardStats, TodaySale } from '@/modules/dashboard';
import type { DashboardInvitation } from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    stats: DashboardStats;
    todaysSales: TodaySale[];
};

export default function Dashboard({
    pendingInvitations = [],
    stats,
    todaysSales,
}: Props) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    return (
        <>
            <Head title="Dashboard" />

            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <DashboardHero
                brand={brand}
                stats={stats}
                quickActions={companyQuickActions(currentTeam?.slug ?? null)}
            />
            <TodaysSalesTable sales={todaysSales} />
        </>
    );
}
