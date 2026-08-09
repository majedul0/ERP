import { Form, Head, usePage } from '@inertiajs/react';
import BankController from '@/actions/App/Http/Controllers/Payments/BankController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Bank } from '@/modules/payments';

const headCell =
    'bg-ocean-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 whitespace-nowrap text-ocean-900';

export default function Banks({ banks }: { banks: Bank[] }) {
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title="Banks" />

            <h1 className="mb-1 text-2xl font-bold text-ocean-900">Banks</h1>
            <p className="mb-6 font-display text-sm text-ocean-800/60">
                The accounts money arrives in. Chosen when recording a payment,
                so a statement can be traced back to where the cash landed.
            </p>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <div className="overflow-hidden rounded-lg border border-ocean-100 bg-white shadow-sm">
                        <table className="w-full text-sm">
                            <thead>
                                <tr>
                                    <th className={headCell}>Name</th>
                                    <th className={headCell}>Account</th>
                                    <th className={`${headCell} text-right`}>
                                        Payments
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-ocean-100">
                                {banks.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={3}
                                            className="px-4 py-10 text-center text-ocean-800/60"
                                        >
                                            No banks yet.
                                        </td>
                                    </tr>
                                )}

                                {banks.map((bank) => (
                                    <tr key={bank.id}>
                                        <td
                                            className={`${bodyCell} font-medium`}
                                        >
                                            {bank.name}
                                        </td>
                                        <td className={bodyCell}>
                                            {bank.accountNumber ?? '—'}
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right tabular-nums`}
                                        >
                                            {bank.paymentsCount}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="rounded-lg border border-ocean-100 bg-white p-5 shadow-sm">
                    <h2 className="mb-4 text-base font-bold text-ocean-900">
                        Add a bank
                    </h2>

                    <Form
                        {...BankController.store.form(currentTeam?.slug ?? '')}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        placeholder="Dutch Bangla Bank Limited"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="account_number">
                                        Account number
                                    </Label>
                                    <Input
                                        id="account_number"
                                        name="account_number"
                                    />
                                    <InputError
                                        message={errors.account_number}
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-ocean-600 hover:bg-ocean-700"
                                >
                                    {processing && <Spinner />}
                                    Add
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
