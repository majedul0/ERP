import { usePage } from '@inertiajs/react';

/**
 * Whether the signed-in member may do something in the current company.
 *
 * Shared from `HandleInertiaRequests`, keyed by the values of
 * `App\Enums\TeamPermission` — `invoice:update`, `expense:manage`, and so on.
 *
 * This hides what the server would refuse; it does not enforce anything. Every
 * route names its own permission through `EnsureTeamPermission`, because a
 * hidden button is still a reachable URL.
 */
export function useCan(): (...permissions: string[]) => boolean {
    const { can } = usePage().props;

    // Any one of the listed permissions is enough, matching the middleware.
    return (...permissions: string[]) =>
        permissions.some((permission) => can?.[permission] === true);
}
