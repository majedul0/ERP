import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { update as updateMember } from '@/routes/teams/members';
import type { PermissionGroup, TeamMember } from '@/types/teams';

/**
 * Choosing exactly what one member may do.
 *
 * Ticking nothing is a valid answer — it means this person may sign in and see
 * the dashboard and nothing else. "Reset to role" clears the tailored list so
 * they follow their role again, which is different from unticking everything:
 * a reset member picks up whatever the role grants in future.
 */
export default function MemberPermissionsModal({
    teamSlug,
    member,
    catalogue,
    rolePermissions,
    open,
    onOpenChange,
}: {
    teamSlug: string;
    member: TeamMember | null;
    catalogue: PermissionGroup[];
    rolePermissions: Record<string, string[]>;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [selected, setSelected] = useState<string[]>([]);
    const [processing, setProcessing] = useState(false);
    const [loadedFor, setLoadedFor] = useState<number | null>(null);

    // Re-seed when a different member's dialog opens, without an effect.
    if (member && loadedFor !== member.id) {
        setLoadedFor(member.id);
        setSelected(member.permissions);
    }

    if (!member) {
        return null;
    }

    const toggle = (value: string) =>
        setSelected((current) =>
            current.includes(value)
                ? current.filter((item) => item !== value)
                : [...current, value],
        );

    const submit = (permissions: string[] | null) => {
        setProcessing(true);

        router.visit(updateMember([teamSlug, member.id]), {
            method: 'patch',
            data:
                permissions === null
                    ? { role: member.role }
                    : { role: member.role, permissions },
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>Permissions for {member.name}</DialogTitle>
                <DialogDescription>
                    Anything left unticked disappears from their menus — and is
                    refused by the server if they reach for the address
                    directly.
                    {!member.has_custom_permissions && (
                        <>
                            {' '}
                            They currently follow the{' '}
                            <strong>{member.role_label}</strong> role.
                        </>
                    )}
                </DialogDescription>

                <div className="space-y-5">
                    {catalogue.map((group) => (
                        <div key={group.group}>
                            <p className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                {group.group}
                            </p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {group.permissions.map((permission) => (
                                    <label
                                        key={permission.value}
                                        className="flex items-start gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            className="mt-0.5"
                                            checked={selected.includes(
                                                permission.value,
                                            )}
                                            onChange={() =>
                                                toggle(permission.value)
                                            }
                                            data-test="permission-checkbox"
                                        />
                                        {permission.label}
                                    </label>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                <DialogFooter className="gap-2">
                    <Button
                        variant="secondary"
                        onClick={() =>
                            setSelected(rolePermissions[member.role] ?? [])
                        }
                    >
                        Tick role defaults
                    </Button>

                    <Button
                        variant="outline"
                        disabled={processing}
                        onClick={() => submit(null)}
                        data-test="reset-permissions"
                    >
                        Follow role
                    </Button>

                    <Button
                        disabled={processing}
                        onClick={() => submit(selected)}
                        data-test="save-permissions"
                    >
                        Save permissions
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
