import type { CompanyBrand } from '@/modules/company/types';
import type { Auth } from '@/types/auth';
import type { Team } from '@/types/teams';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            currentTeam: Team | null;
            /**
             * Permission values from App\Enums\TeamPermission to whether this
             * member has them. Read it through `useCan`, not directly.
             */
            can: Record<string, boolean>;
            /**
             * How long the current company is paid up for. Null outside a
             * company. Warning only — it never gates access.
             */
            subscription: {
                status: 'none' | 'unpaid' | 'active' | 'overdue';
                paidThrough: string | null;
                daysRemaining: number | null;
                daysOverdue: number | null;
                isOverdue: boolean;
                needsAttention: boolean;
            } | null;
            companyBrand: CompanyBrand | null;
            teams: Team[];
            [key: string]: unknown;
        };
    }
}
