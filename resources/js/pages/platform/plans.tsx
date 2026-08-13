import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import PlanController from '@/actions/App/Http/Controllers/Platform/PlanController';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatAmount } from '@/lib/format';
import { index } from '@/routes/platform';

type Plan = {
    id: number;
    name: string;
    price: number;
    period: string;
    periodLabel: string;
    monthlyValue: number;
    isActive: boolean;
    companies: number;
};

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';
const headCell =
    'bg-coffee-800 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-coffee-900';

/**
 * The add/edit form for a plan. One component for both, so an edit cannot
 * quietly diverge from a create.
 */
function PlanForm({
    plan,
    periods,
    onDone,
}: {
    plan?: Plan;
    periods: Array<{ value: string; label: string }>;
    onDone: () => void;
}) {
    const action = plan
        ? PlanController.update.form(plan.id)
        : PlanController.store.form();

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            resetOnSuccess={!plan}
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-1.5">
                        <Label htmlFor="name">Plan name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            defaultValue={plan?.name ?? ''}
                            placeholder="Standard"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="price">Price</Label>
                            <Input
                                id="price"
                                name="price"
                                type="number"
                                min={0}
                                step={1}
                                required
                                defaultValue={plan?.price ?? ''}
                            />
                            <p className="text-xs text-muted-foreground">
                                Whole amounts only.
                            </p>
                            <InputError message={errors.price} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="period">Billing period</Label>
                            <select
                                id="period"
                                name="period"
                                className={selectClasses}
                                defaultValue={plan?.period ?? 'monthly'}
                            >
                                {periods.map((period) => (
                                    <option
                                        key={period.value}
                                        value={period.value}
                                    >
                                        {period.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.period} />
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_active" value="0" />
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            defaultChecked={plan?.isActive ?? true}
                        />
                        Available to sell
                    </label>
                    <p className="text-xs text-muted-foreground">
                        Retiring a plan hides it from the selectors. Companies
                        already on it keep it, and their payments still name it.
                    </p>

                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {plan ? 'Save changes' : 'Create plan'}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

export default function Plans({
    plans,
    periods,
}: {
    plans: Plan[];
    periods: Array<{ value: string; label: string }>;
}) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<Plan | null>(null);

    return (
        <div className="min-h-svh bg-coffee-50/40">
            <Head title="Plans" />

            <header className="border-b border-coffee-200 bg-coffee-900 px-6 py-4">
                <div className="mx-auto flex max-w-[1400px] items-center justify-between">
                    <h1 className="text-lg font-bold text-white">Plans</h1>
                    <Button
                        asChild
                        variant="ghost"
                        className="text-white hover:bg-white/10"
                    >
                        <Link href={index()}>Companies</Link>
                    </Button>
                </div>
            </header>

            <main className="mx-auto max-w-[1400px] p-6">
                <div className="mb-4 flex items-center justify-between">
                    <p className="text-sm text-coffee-800/70">
                        A price change applies to the next payment recorded —
                        never to a period already paid for.
                    </p>

                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button
                                className="bg-coffee-700 hover:bg-coffee-800"
                                data-test="new-plan-button"
                            >
                                + New plan
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Create a plan</DialogTitle>
                            <DialogDescription>
                                What you sell, and how long one payment lasts.
                            </DialogDescription>
                            <PlanForm
                                periods={periods}
                                onDone={() => setCreateOpen(false)}
                            />
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                    <table className="w-full text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Plan</th>
                                <th className={`${headCell} text-right`}>
                                    Price
                                </th>
                                <th className={headCell}>Period</th>
                                <th className={`${headCell} text-right`}>
                                    Per month
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Companies
                                </th>
                                <th className={headCell}>Status</th>
                                <th className={headCell}>
                                    <span className="sr-only">Edit</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {plans.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No plans yet. Create one before selling.
                                    </td>
                                </tr>
                            )}

                            {plans.map((plan) => (
                                <tr key={plan.id}>
                                    <td className={`${bodyCell} font-medium`}>
                                        {plan.name}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {formatAmount(plan.price)}
                                    </td>
                                    <td className={bodyCell}>
                                        {plan.periodLabel}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {formatAmount(plan.monthlyValue)}
                                    </td>
                                    <td
                                        className={`${bodyCell} text-right tabular-nums`}
                                    >
                                        {plan.companies}
                                    </td>
                                    <td className={bodyCell}>
                                        {plan.isActive ? (
                                            <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                                Selling
                                            </span>
                                        ) : (
                                            <span className="rounded-full bg-coffee-100 px-2 py-0.5 text-xs font-semibold text-coffee-800">
                                                Retired
                                            </span>
                                        )}
                                    </td>
                                    <td className={bodyCell}>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setEditing(plan)}
                                        >
                                            Edit
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </main>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogTitle>Edit {editing?.name}</DialogTitle>
                    <DialogDescription>
                        Changes apply to the next payment recorded.
                    </DialogDescription>
                    {editing && (
                        <PlanForm
                            plan={editing}
                            periods={periods}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
