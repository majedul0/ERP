import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { DistributorOption } from '@/modules/invoices';
import type { StatementEntry, StatementTotals } from '@/modules/payments';
import { StatementTable } from '@/modules/payments';
import { show as showInvoice } from '@/routes/invoices';
import { create as addPayment } from '@/routes/payments';

type Props = {
    distributor: DistributorOption;
    statement: StatementEntry[];
    totals: StatementTotals;
};

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-ocean-800/55 uppercase">
                {label}
            </dt>
            <dd className="mt-0.5 text-sm text-ocean-900">{value || '—'}</dd>
        </div>
    );
}

export default function ShowDistributor({
    distributor,
    statement,
    totals,
}: Props) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    return (
        <>
            <Head title={distributor.name} />

            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-ocean-900">
                        {distributor.name}
                    </h1>
                    <p className="mt-1 font-display text-sm text-ocean-800/60">
                        {distributor.fullAddress || 'No address on file'}
                    </p>
                </div>

                <Button asChild className="bg-ocean-600 hover:bg-ocean-700">
                    <Link
                        href={addPayment({
                            current_team: teamSlug,
                            distributor: distributor.id,
                        })}
                    >
                        <Plus className="size-4" />
                        Add Payment
                    </Link>
                </Button>
            </div>

            <section className="grid gap-4 md:grid-cols-3">
                <div className="rounded-lg border border-ocean-100 bg-white p-5 shadow-sm md:col-span-2">
                    <h2 className="mb-4 text-base font-bold text-ocean-900">
                        Details
                    </h2>
                    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <Detail
                            label="Proprietor"
                            value={distributor.proprietorName ?? ''}
                        />
                        <Detail label="Phone" value={distributor.phone ?? ''} />
                        <Detail
                            label="Address"
                            value={distributor.address ?? ''}
                        />
                        <Detail label="Thana" value={distributor.thana ?? ''} />
                        <Detail
                            label="District"
                            value={distributor.district ?? ''}
                        />
                        <Detail
                            label="Division"
                            value={distributor.division ?? ''}
                        />
                    </dl>
                </div>

                {/* The due is the headline: it is what this screen is for. */}
                <div className="rounded-lg bg-ocean-500 p-5 text-white shadow-sm">
                    <p className="text-sm font-medium text-white/85">
                        Due Amount
                    </p>
                    <p className="mt-1 text-4xl font-bold">
                        {money(totals.due)}
                    </p>

                    <dl className="mt-6 space-y-2 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-white/85">Total charged</dt>
                            <dd className="tabular-nums">
                                {money(totals.charged)}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4 border-t border-white/25 pt-2">
                            <dt className="text-white/85">Total paid</dt>
                            <dd className="tabular-nums">
                                {money(totals.paid)}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section className="mt-8">
                <h2 className="mb-3 text-lg font-bold text-ocean-900">
                    Statement
                </h2>
                <StatementTable
                    entries={statement}
                    invoiceUrl={(id) =>
                        showInvoice({
                            current_team: teamSlug,
                            invoice: id,
                        }).url
                    }
                />
            </section>
        </>
    );
}
