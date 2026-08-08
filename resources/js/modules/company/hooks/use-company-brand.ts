import { usePage } from '@inertiajs/react';
import type { CompanyBrand } from '../types';

/**
 * Branding for the current tenant, shared from `HandleInertiaRequests`.
 *
 * Falls back to the team/app name so the shell still renders for a user who
 * has no current team (e.g. straight after accepting an invitation).
 */
export function useCompanyBrand(): CompanyBrand {
    const { companyBrand, currentTeam, name } = usePage().props;

    return (
        companyBrand ?? {
            name: currentTeam?.name ?? name,
            logoUrl: null,
            currencySymbol: '৳',
        }
    );
}
