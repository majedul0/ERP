/**
 * Branding for the current tenant, shared from `HandleInertiaRequests`.
 */
export type CompanyBrand = {
    name: string;
    /**
     * Public URL for the company logo, or null when none is uploaded.
     * Uploads live under `storage/app/public/logos/{team_id}/`.
     */
    logoUrl: string | null;
    /** Printed in the header of every invoice and challan. */
    address: string | null;
    phone: string | null;
    /** Single currency per company for v1 (see PRD 1.4). */
    currencySymbol: string;
};
