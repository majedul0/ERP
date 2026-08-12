import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { StatementEntry, StatementTotals } from '@/modules/payments';
import { StatementTable } from '@/modules/payments';
import type { Vendor } from '@/modules/vendors';
import { create as newBill } from '@/routes/bills';
import { create as newPayment } from '@/routes/vendor-payments';
import { edit } from '@/routes/vendors';

type Props = {
    vendor: Vendor;
    statement: StatementEntry[];
    totals: StatementTotals;
};

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-coffee-800/55 uppercase">
                {label}
            </dt>
            <dd className="mt-0.5 text-sm text-coffee-900">{value || '—'}</dd>
        </div>
    );
}

export default function ShowVendor({ vendor, statement, totals }: Props) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    return (
        <>
            <Head title={vendor.name} />

            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold text-coffee-900">
                        {vendor.name}
                    </h1>
                    <p className="mt-1 font-display text-sm text-coffee-800/60">
                        {vendor.fullAddress || 'No address on file'}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline">
                        <Link
                            href={edit({
                                current_team: teamSlug,
                                vendor: vendor.id,
                            })}
                        >
                            Edit
                        </Link>
                    </Button>

                    <Button asChild variant="outline">
                        <Link href={newBill(teamSlug)}>
                            <Plus className="size-4" />
                            Add Bill
                        </Link>
                    </Button>

                    <Button
                        asChild
                        className="bg-coffee-600 hover:bg-coffee-700"
                    >
                        <Link href={newPayment(teamSlug)}>
                            <Plus className="size-4" />
                            Pay Vendor
                        </Link>
                    </Button>
                </div>
            </div>

            <section className="grid gap-4 md:grid-cols-3">
                <div className="rounded-lg border border-coffee-100 bg-white p-5 shadow-sm md:col-span-2">
                    <h2 className="mb-4 text-base font-bold text-coffee-900">
                        Details
                    </h2>
                    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <Detail
                            label="Proprietor"
                            value={vendor.proprietorName ?? ''}
                        />
                        <Detail label="Phone" value={vendor.phone ?? ''} />
                        <Detail label="Address" value={vendor.address ?? ''} />
                        <Detail label="Thana" value={vendor.thana ?? ''} />
                        <Detail
                            label="District"
                            value={vendor.district ?? ''}
                        />
                        <Detail
                            label="Division"
                            value={vendor.division ?? ''}
                        />
                    </dl>
                </div>

                {/* What is owed is the headline: it is what this screen is for. */}
                <div className="rounded-lg bg-coffee-500 p-5 text-white shadow-sm">
                    <p className="text-sm font-medium text-white/85">
                        {/* A negative balance means the vendor is holding our
                            money, not that we owe less than nothing. */}
                        {totals.due < 0 ? 'Advance Held' : 'Payable'}
                    </p>
                    <p className="mt-1 text-4xl font-bold">
                        {money(Math.abs(totals.due))}
                    </p>

                    <dl className="mt-6 space-y-2 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-white/85">Total billed</dt>
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
                <h2 className="mb-3 text-lg font-bold text-coffee-900">
                    Statement
                </h2>
                {/* Bills have no detail screen of their own, so nothing links. */}
                <StatementTable entries={statement} invoiceUrl={() => ''} />
            </section>
        </>
    );
}
