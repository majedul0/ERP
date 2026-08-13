import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import SearchSelect from '@/components/search-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { BankOption } from '@/modules/payments';
import { index as banksIndex } from '@/routes/banks';
import { index as paymentsIndex, store } from '@/routes/payments';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

type DistributorOption = {
    id: number;
    name: string;
    proprietorName: string | null;
    phone: string | null;
    district: string | null;
    fullAddress: string;
    balance: number;
};

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * Recording money in when you started from the dashboard rather than from an
 * account.
 *
 * The distributor is chosen here, searchable, and their current balance is
 * shown once picked — so the person taking the money can see what it settles
 * before they save.
 */
export default function RecordPayment({
    distributors,
    banks,
}: {
    distributors: DistributorOption[];
    banks: BankOption[];
}) {
    const brand = useCompanyBrand();
    const { errors, currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [distributorId, setDistributorId] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);

    const chosen =
        distributors.find((distributor) => distributor.id === distributorId) ??
        null;

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);

        const form = new FormData(event.currentTarget);

        router.post(
            store(teamSlug).url,
            {
                distributor_id: distributorId,
                bank_id: form.get('bank_id') || null,
                amount: Number(form.get('amount')),
                paid_on: String(form.get('paid_on')),
                comment: form.get('comment') || null,
            },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    return (
        <>
            <Head title="Add Payment" />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Add Payment
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={paymentsIndex(teamSlug)}>Cancel</Link>
                    </Button>
                </div>

                {distributors.length === 0 ? (
                    <p className="mt-8 rounded-lg border border-coffee-100 bg-white p-8 text-center text-coffee-800/70 shadow-sm">
                        Add a distributor first — a payment has to be against an
                        account.
                    </p>
                ) : (
                    <form onSubmit={submit} className="mt-8 space-y-5 pb-16">
                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="distributor_id"
                                className="text-coffee-900"
                            >
                                Distributor<span aria-hidden="true">*</span>
                            </Label>
                            <SearchSelect
                                id="distributor_id"
                                aria-label="Distributor"
                                options={distributors.map((distributor) => ({
                                    value: distributor.id,
                                    label: distributor.name,
                                    hint: distributor.district ?? undefined,
                                    keywords: [
                                        distributor.proprietorName,
                                        distributor.phone,
                                        distributor.fullAddress,
                                    ]
                                        .filter(Boolean)
                                        .join(' '),
                                }))}
                                value={distributorId}
                                onChange={setDistributorId}
                                placeholder="Search distributors…"
                                emptyText="No distributor matches that"
                            />

                            {chosen && (
                                <p className="text-xs text-coffee-800/70">
                                    {chosen.balance < 0
                                        ? `In credit ${formatMoney(Math.abs(chosen.balance), brand.currencySymbol)}`
                                        : `Currently due ${formatMoney(chosen.balance, brand.currencySymbol)}`}
                                </p>
                            )}

                            <InputError message={errors.distributor_id} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="paid_on"
                                    className="text-coffee-900"
                                >
                                    Payment date
                                    <span aria-hidden="true">*</span>
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
                                <Label
                                    htmlFor="bank_id"
                                    className="text-coffee-900"
                                >
                                    Bank
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
                                    <p className="text-xs text-coffee-800/60">
                                        No banks yet —{' '}
                                        <Link
                                            href={banksIndex(teamSlug)}
                                            className="underline underline-offset-4"
                                        >
                                            add one
                                        </Link>{' '}
                                        to record where the money landed.
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="amount" className="text-coffee-900">
                                Amount<span aria-hidden="true">*</span>
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
                            <Label
                                htmlFor="comment"
                                className="text-coffee-900"
                            >
                                Comment
                            </Label>
                            <Input id="comment" name="comment" />
                            <InputError message={errors.comment} />
                        </div>

                        <Button
                            type="submit"
                            disabled={processing || distributorId === null}
                            className="w-full bg-coffee-600 hover:bg-coffee-700"
                            data-test="record-payment-button"
                        >
                            {processing && <Spinner />}
                            Add Payment
                        </Button>
                    </form>
                )}
            </div>
        </>
    );
}
