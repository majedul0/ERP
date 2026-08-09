import { Form, Head, Link, usePage } from '@inertiajs/react';
import PaymentController from '@/actions/App/Http/Controllers/Payments/PaymentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { BankOption } from '@/modules/payments';
import { index as banksIndex } from '@/routes/banks';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

type Props = {
    distributor: { id: number; name: string; balance: number };
    banks: BankOption[];
};

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

export default function AddPayment({ distributor, banks }: Props) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;

    return (
        <>
            <Head title={`Add Payment For ${distributor.name}`} />

            <h1 className="mt-2 text-center text-2xl font-bold text-ocean-900">
                Add Payment For {distributor.name}
            </h1>
            <p className="mt-1 text-center font-display text-sm text-ocean-800/60">
                Currently due:{' '}
                <span className="font-semibold">
                    {formatMoney(distributor.balance, brand.currencySymbol)}
                </span>
            </p>

            <Form
                {...PaymentController.store.form(currentTeam?.slug ?? '')}
                options={{ preserveScroll: true }}
                className="mx-auto mt-8 w-full max-w-xl space-y-5 pb-16"
            >
                {({ processing, errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="distributor_id"
                            value={distributor.id}
                        />

                        <div className="grid gap-1.5">
                            <Label htmlFor="paid_on" className="text-ocean-900">
                                Payment date:
                            </Label>
                            <Input
                                id="paid_on"
                                name="paid_on"
                                type="date"
                                required
                                defaultValue={today()}
                            />
                            <InputError message={errors.paid_on} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="bank_id" className="text-ocean-900">
                                Bank:
                            </Label>
                            <select
                                id="bank_id"
                                name="bank_id"
                                className={selectClasses}
                                defaultValue=""
                            >
                                <option value="">---------</option>
                                {banks.map((bank) => (
                                    <option key={bank.id} value={bank.id}>
                                        {bank.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.bank_id} />
                            {banks.length === 0 && (
                                <p className="text-xs text-ocean-800/60">
                                    No banks yet —{' '}
                                    <Link
                                        href={banksIndex(
                                            currentTeam?.slug ?? '',
                                        )}
                                        className="underline underline-offset-4"
                                    >
                                        add one
                                    </Link>{' '}
                                    to record where the money landed.
                                </p>
                            )}
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="amount" className="text-ocean-900">
                                Amount:
                            </Label>
                            <Input
                                id="amount"
                                name="amount"
                                type="number"
                                min={1}
                                step={1}
                                required
                            />
                            <InputError message={errors.amount} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="comment" className="text-ocean-900">
                                Comment:
                            </Label>
                            <Input id="comment" name="comment" />
                            <InputError message={errors.comment} />
                        </div>

                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-ocean-600 hover:bg-ocean-700"
                            data-test="add-payment-button"
                        >
                            {processing && <Spinner />}
                            Add
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
