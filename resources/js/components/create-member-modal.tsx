import { Form } from '@inertiajs/react';
import { useState } from 'react';
import TeamMemberController from '@/actions/App/Http/Controllers/Teams/TeamMemberController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { PermissionGroup, RoleOption, TeamRole } from '@/types';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

/**
 * Creating an account for a member of staff.
 *
 * The company sets the email and password directly rather than emailing an
 * invitation — these are employees being set up by their employer, and waiting
 * on an email nobody checks is a poor way to start a shift.
 *
 * Permissions start from the chosen role and can be adjusted before saving, so
 * somebody can be created with exactly the access they need rather than being
 * given more and trimmed back afterwards.
 */
export default function CreateMemberModal({
    teamSlug,
    availableRoles,
    catalogue,
    rolePermissions,
    open,
    onOpenChange,
}: {
    teamSlug: string;
    availableRoles: RoleOption[];
    catalogue: PermissionGroup[];
    rolePermissions: Record<string, string[]>;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const defaultRole = availableRoles[0]?.value ?? 'member';

    const [role, setRole] = useState(defaultRole);
    const [selected, setSelected] = useState<string[]>(
        rolePermissions[defaultRole] ?? [],
    );
    const [customised, setCustomised] = useState(false);

    const chooseRole = (value: TeamRole) => {
        setRole(value);

        // Follow the role until somebody deliberately ticks something, so
        // switching roles does not silently keep the previous role's access.
        if (!customised) {
            setSelected(rolePermissions[value] ?? []);
        }
    };

    const toggle = (value: string) => {
        setCustomised(true);
        setSelected((current) =>
            current.includes(value)
                ? current.filter((item) => item !== value)
                : [...current, value],
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogTitle>Add a member</DialogTitle>
                <DialogDescription>
                    They sign in with the email and password you set here. Tell
                    them the password yourself — it is not emailed anywhere, and
                    they can change it under Settings once they are in.
                </DialogDescription>

                <Form
                    {...TeamMemberController.store.form(teamSlug)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    resetOnSuccess
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        autoComplete="off"
                                        data-test="member-name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        autoComplete="off"
                                        data-test="member-email"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="password">Password</Label>
                                    <Input
                                        id="password"
                                        name="password"
                                        type="text"
                                        required
                                        autoComplete="new-password"
                                        data-test="member-password"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Shown as you type, so you can read it
                                        out.
                                    </p>
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="role">Role</Label>
                                    <select
                                        id="role"
                                        name="role"
                                        className={selectClasses}
                                        value={role}
                                        onChange={(event) =>
                                            chooseRole(
                                                event.target.value as TeamRole,
                                            )
                                        }
                                    >
                                        {availableRoles.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.role} />
                                </div>
                            </div>

                            <div>
                                <p className="mb-2 text-sm font-medium">
                                    What they can do
                                </p>
                                <div className="space-y-4 rounded-md border p-4">
                                    {catalogue.map((group) => (
                                        <div key={group.group}>
                                            <p className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                {group.group}
                                            </p>
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                {group.permissions.map(
                                                    (permission) => (
                                                        <label
                                                            key={
                                                                permission.value
                                                            }
                                                            className="flex items-start gap-2 text-sm"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                name="permissions[]"
                                                                value={
                                                                    permission.value
                                                                }
                                                                className="mt-0.5"
                                                                checked={selected.includes(
                                                                    permission.value,
                                                                )}
                                                                onChange={() =>
                                                                    toggle(
                                                                        permission.value,
                                                                    )
                                                                }
                                                            />
                                                            {permission.label}
                                                        </label>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <InputError message={errors.permissions} />
                            </div>

                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Cancel
                                </Button>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="create-member-button"
                                >
                                    {processing && <Spinner />}
                                    Create account
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
