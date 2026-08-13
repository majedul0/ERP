import { Form, Head, Link, router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import PlatformController from '@/actions/App/Http/Controllers/Platform/PlatformController';
import SubscriptionController from '@/actions/App/Http/Controllers/Platform/SubscriptionController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatAmount } from '@/lib/format';
import { logout } from '@/routes/platform';
import { suspend } from '@/routes/platform/companies';
import { index as plansIndex } from '@/routes/platform/plans';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

type Plan = {
    id: number;
    name: string;
    price: number;
    period: string;
    periodLabel: string;
    isActive: boolean;
};

type Subscription = {
    status: 'none' | 'unpaid' | 'active' | 'overdue';
    paidThrough: string | null;
    daysRemaining: number | null;
    daysOverdue: number | null;
    isOverdue: boolean;
    needsAttention: boolean;
};

type Company = {
    id: number;
    name: string;
    slug: string;
    createdAt: string | null;
    suspendedAt: string | null;
    isSuspended: boolean;
    owner: { name: string; email: string } | null;
    counts: Record<string, number>;
    receivable: number;
    storageBytes: number;
    lastInvoiceAt: string | null;
    plan: Omit<Plan, 'isActive'> | null;
    subscription: Subscription;
    suspensionMode: string;
    suspensionModeLabel: string;
};

const headCell =
    'bg-coffee-800 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

/** Bytes are for machines; this screen is read by a person. */
function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** What the Status column says, and how loudly. */
function SubscriptionCell({ company }: { company: Company }) {
    const { subscription: sub } = company;

    if (sub.status === 'none') {
        return <span className="text-coffee-800/50">Not subscribed</span>;
    }

    if (sub.status === 'unpaid') {
        return (
            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                Never paid
            </span>
        );
    }

    if (sub.isOverdue) {
        return (
            <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">
                Overdue {sub.daysOverdue}d
            </span>
        );
    }

    return (
        <span
            className={
                sub.needsAttention
                    ? 'rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800'
                    : 'rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800'
            }
        >
            {sub.daysRemaining}d left
        </span>
    );
}

type SuspensionMode = {
    value: string;
    label: string;
    description: string;
};

export default function PlatformIndex({
    companies,
    plans,
    suspensionModes,
    totals,
}: {
    companies: Company[];
    plans: Plan[];
    suspensionModes: SuspensionMode[];
    totals: {
        companies: number;
        suspended: number;
        storageBytes: number;
        users: number;
        overdue: number;
        collectedThisMonth: number;
        monthlyValue: number;
    };
}) {
    const [createOpen, setCreateOpen] = useState(false);
    const [passwordOpen, setPasswordOpen] = useState(false);
    const [payingFor, setPayingFor] = useState<Company | null>(null);

    const sellable = plans.filter((plan) => plan.isActive);

    // Routed by slug, which is how the team is bound everywhere else.
    const suspendAs = (company: Company, mode: string) =>
        router.patch(
            suspend(company.slug).url,
            { suspend: true, mode },
            { preserveScroll: true },
        );

    const reinstate = (company: Company) =>
        router.patch(
            suspend(company.slug).url,
            { suspend: false },
            { preserveScroll: true },
        );

    return (
        <div className="min-h-svh bg-coffee-50/40">
            <Head title="Platform" />

            <header className="border-b border-coffee-200 bg-coffee-900 px-6 py-4">
                <div className="mx-auto flex max-w-[1400px] items-center justify-between">
                    <h1 className="text-lg font-bold text-white">
                        Platform administration
                    </h1>
                    <div className="flex items-center gap-2">
                        <Button
                            asChild
                            variant="ghost"
                            className="text-white hover:bg-white/10"
                        >
                            <Link href={plansIndex()}>Plans</Link>
                        </Button>

                        <Dialog
                            open={passwordOpen}
                            onOpenChange={setPasswordOpen}
                        >
                            <DialogTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="text-white hover:bg-white/10"
                                    data-test="change-password-button"
                                >
                                    Change password
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Change your password</DialogTitle>
                                <DialogDescription>
                                    Your current password is required, and every
                                    other session will be signed out.
                                </DialogDescription>

                                <Form
                                    {...PlatformController.updatePassword.form()}
                                    options={{ preserveScroll: true }}
                                    onSuccess={() => setPasswordOpen(false)}
                                    resetOnSuccess
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-1.5">
                                                <Label htmlFor="current_password">
                                                    Current password
                                                </Label>
                                                <Input
                                                    id="current_password"
                                                    name="current_password"
                                                    type="password"
                                                    required
                                                    autoComplete="current-password"
                                                />
                                                <InputError
                                                    message={
                                                        errors.current_password
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-1.5">
                                                <Label htmlFor="password">
                                                    New password
                                                </Label>
                                                <Input
                                                    id="password"
                                                    name="password"
                                                    type="password"
                                                    required
                                                    autoComplete="new-password"
                                                />
                                                <InputError
                                                    message={errors.password}
                                                />
                                            </div>

                                            <div className="grid gap-1.5">
                                                <Label htmlFor="password_confirmation">
                                                    Confirm new password
                                                </Label>
                                                <Input
                                                    id="password_confirmation"
                                                    name="password_confirmation"
                                                    type="password"
                                                    required
                                                    autoComplete="new-password"
                                                />
                                            </div>

                                            <DialogFooter>
                                                <Button
                                                    type="submit"
                                                    disabled={processing}
                                                    data-test="save-password-button"
                                                >
                                                    {processing && <Spinner />}
                                                    Change password
                                                </Button>
                                            </DialogFooter>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>

                        <Button
                            variant="ghost"
                            className="text-white hover:bg-white/10"
                            onClick={() => router.post(logout())}
                        >
                            Sign out
                        </Button>
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-[1400px] p-6">
                <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        { label: 'Companies', value: String(totals.companies) },
                        { label: 'Overdue', value: String(totals.overdue) },
                        {
                            label: 'Collected this month',
                            value: formatAmount(totals.collectedThisMonth),
                        },
                        {
                            // A projection, not money in hand — named so it
                            // cannot be mistaken for the figure above it.
                            label: 'Monthly value',
                            value: formatAmount(totals.monthlyValue),
                        },
                        { label: 'Suspended', value: String(totals.suspended) },
                        { label: 'Users', value: String(totals.users) },
                        {
                            label: 'Storage used',
                            value: formatBytes(totals.storageBytes),
                        },
                    ].map((card) => (
                        <div
                            key={card.label}
                            className="rounded-lg border border-coffee-100 bg-white p-4 shadow-sm"
                        >
                            <p className="text-xs font-semibold tracking-wide text-coffee-800/60 uppercase">
                                {card.label}
                            </p>
                            <p className="mt-1 text-2xl font-bold text-coffee-900 tabular-nums">
                                {card.value}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-bold text-coffee-900">
                        Companies
                    </h2>

                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button
                                className="bg-coffee-700 hover:bg-coffee-800"
                                data-test="new-company-button"
                            >
                                + New company
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Create a company</DialogTitle>
                            <DialogDescription>
                                The owner account is created with it — a company
                                nobody can sign into is not a sale. Give them
                                these credentials directly.
                            </DialogDescription>

                            <Form
                                {...PlatformController.store.form()}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setCreateOpen(false)}
                                resetOnSuccess
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="company">
                                                Company name
                                            </Label>
                                            <Input
                                                id="company"
                                                name="company"
                                                required
                                            />
                                            <InputError
                                                message={errors.company}
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label htmlFor="owner_name">
                                                Owner name
                                            </Label>
                                            <Input
                                                id="owner_name"
                                                name="owner_name"
                                                required
                                            />
                                            <InputError
                                                message={errors.owner_name}
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label htmlFor="owner_email">
                                                Owner email
                                            </Label>
                                            <Input
                                                id="owner_email"
                                                name="owner_email"
                                                type="email"
                                                required
                                            />
                                            <InputError
                                                message={errors.owner_email}
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label htmlFor="owner_password">
                                                Owner password
                                            </Label>
                                            <Input
                                                id="owner_password"
                                                name="owner_password"
                                                type="text"
                                                required
                                            />
                                            <InputError
                                                message={errors.owner_password}
                                            />
                                        </div>

                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                data-test="create-company-button"
                                            >
                                                {processing && <Spinner />}
                                                Create company
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[70rem] text-sm">
                            <thead>
                                <tr>
                                    <th className={headCell}>Company</th>
                                    <th className={headCell}>Owner</th>
                                    <th className={`${headCell} text-right`}>
                                        Users
                                    </th>
                                    <th className={`${headCell} text-right`}>
                                        Invoices
                                    </th>
                                    <th className={`${headCell} text-right`}>
                                        Products
                                    </th>
                                    <th className={`${headCell} text-right`}>
                                        Receivable
                                    </th>
                                    <th className={`${headCell} text-right`}>
                                        Storage
                                    </th>
                                    <th className={headCell}>Plan</th>
                                    <th className={headCell}>Paid through</th>
                                    <th className={headCell}>Subscription</th>
                                    <th className={headCell}>Status</th>
                                    <th className={headCell}>
                                        <span className="sr-only">Action</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-coffee-100">
                                {companies.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={9}
                                            className="px-4 py-10 text-center text-coffee-800/60"
                                        >
                                            No companies yet.
                                        </td>
                                    </tr>
                                )}

                                {companies.map((company) => (
                                    <tr
                                        key={company.id}
                                        className={
                                            company.isSuspended
                                                ? 'bg-red-50/60'
                                                : 'hover:bg-coffee-50/60'
                                        }
                                    >
                                        <td
                                            className={`${bodyCell} font-medium`}
                                        >
                                            {company.name}
                                            <span className="block text-xs text-coffee-800/50">
                                                /{company.slug} · since{' '}
                                                {company.createdAt ?? '—'}
                                            </span>
                                        </td>
                                        <td className={bodyCell}>
                                            {company.owner?.name ?? '—'}
                                            <span className="block text-xs text-coffee-800/50">
                                                {company.owner?.email ?? ''}
                                            </span>
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right tabular-nums`}
                                        >
                                            {company.counts.members}
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right tabular-nums`}
                                        >
                                            {company.counts.invoices}
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right tabular-nums`}
                                        >
                                            {company.counts.products}
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right tabular-nums`}
                                        >
                                            {formatAmount(company.receivable)}
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right tabular-nums`}
                                        >
                                            {formatBytes(company.storageBytes)}
                                        </td>
                                        <td className={bodyCell}>
                                            {company.plan?.name ?? '—'}
                                        </td>
                                        <td className={bodyCell}>
                                            {company.subscription.paidThrough ??
                                                '—'}
                                        </td>
                                        <td className={bodyCell}>
                                            <SubscriptionCell
                                                company={company}
                                            />
                                        </td>
                                        <td className={bodyCell}>
                                            {company.isSuspended ? (
                                                <span
                                                    className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800"
                                                    title={
                                                        company.suspensionModeLabel
                                                    }
                                                >
                                                    Suspended{' '}
                                                    {company.suspendedAt}
                                                    {company.suspensionMode !==
                                                        'notice' && ' · silent'}
                                                </span>
                                            ) : (
                                                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                                    Active
                                                </span>
                                            )}
                                        </td>
                                        <td className={bodyCell}>
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setPayingFor(company)
                                                    }
                                                    data-test="record-payment-button"
                                                >
                                                    Record payment
                                                </Button>

                                                {company.isSuspended ? (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            reinstate(company)
                                                        }
                                                        data-test="toggle-suspension"
                                                    >
                                                        Reinstate
                                                    </Button>
                                                ) : (
                                                    /* A menu, not a button:
                                                       closing a company and
                                                       deciding what they are
                                                       told are two choices. */
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                size="sm"
                                                                variant="destructive"
                                                                data-test="toggle-suspension"
                                                            >
                                                                Suspend
                                                                <ChevronDown className="ml-1 size-3.5 opacity-70" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            {suspensionModes.map(
                                                                (mode) => (
                                                                    <DropdownMenuItem
                                                                        key={
                                                                            mode.value
                                                                        }
                                                                        onSelect={() =>
                                                                            suspendAs(
                                                                                company,
                                                                                mode.value,
                                                                            )
                                                                        }
                                                                        data-test="suspension-mode"
                                                                    >
                                                                        <span>
                                                                            <span className="font-medium">
                                                                                {
                                                                                    mode.label
                                                                                }
                                                                            </span>
                                                                            <span className="block text-xs text-muted-foreground">
                                                                                {
                                                                                    mode.description
                                                                                }
                                                                            </span>
                                                                        </span>
                                                                    </DropdownMenuItem>
                                                                ),
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <p className="mt-4 text-xs text-coffee-800/60">
                    Suspending closes a company to everyone in it, owner
                    included. Nothing is deleted — their books wait for the
                    suspension to be lifted. An overdue subscription warns the
                    company but never locks them out on its own.
                </p>
            </main>

            {/* Recording a payment moves the paid-through date; the date is
                recomputed from the payments, so a correction fixes it too. */}
            <Dialog
                open={payingFor !== null}
                onOpenChange={(open) => !open && setPayingFor(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        Record payment from {payingFor?.name}
                    </DialogTitle>
                    <DialogDescription>
                        {payingFor?.subscription.paidThrough
                            ? `Paid through ${payingFor.subscription.paidThrough}. A new payment continues from there, so paying late does not give away the months they went without.`
                            : 'This will be their first payment, so the period starts on the day they paid.'}
                    </DialogDescription>

                    {payingFor && (
                        <Form
                            {...SubscriptionController.store.form(
                                payingFor.slug,
                            )}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setPayingFor(null)}
                            resetOnSuccess
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="plan_id">Plan</Label>
                                        <select
                                            id="plan_id"
                                            name="plan_id"
                                            className={selectClasses}
                                            defaultValue={
                                                payingFor.plan?.id ?? ''
                                            }
                                        >
                                            <option value="">
                                                No plan — record money only
                                            </option>
                                            {sellable.map((plan) => (
                                                <option
                                                    key={plan.id}
                                                    value={plan.id}
                                                >
                                                    {plan.name} ·{' '}
                                                    {formatAmount(plan.price)} ·{' '}
                                                    {plan.periodLabel}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="text-xs text-muted-foreground">
                                            The plan decides how long this
                                            payment buys.
                                        </p>
                                        <InputError message={errors.plan_id} />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="amount">
                                                Amount
                                            </Label>
                                            <Input
                                                id="amount"
                                                name="amount"
                                                type="number"
                                                min={1}
                                                step={1}
                                                required
                                                defaultValue={
                                                    payingFor.plan?.price ?? ''
                                                }
                                            />
                                            <InputError
                                                message={errors.amount}
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label htmlFor="paid_on">
                                                Paid on
                                            </Label>
                                            <Input
                                                id="paid_on"
                                                name="paid_on"
                                                type="date"
                                                required
                                                defaultValue={today()}
                                            />
                                            <InputError
                                                message={errors.paid_on}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="method">Method</Label>
                                        <Input
                                            id="method"
                                            name="method"
                                            placeholder="bKash, bank transfer, cash"
                                        />
                                        <InputError message={errors.method} />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="note">Note</Label>
                                        <Input
                                            id="note"
                                            name="note"
                                            placeholder="Optional — reference number"
                                        />
                                        <InputError message={errors.note} />
                                    </div>

                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-test="save-payment-button"
                                        >
                                            {processing && <Spinner />}
                                            Record payment
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
