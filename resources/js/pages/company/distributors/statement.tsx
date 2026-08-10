import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { DistributorOption } from '@/modules/invoices';
import type { StatementEntry, StatementTotals } from '@/modules/payments';
import { StatementTable } from '@/modules/payments';
import { show } from '@/routes/distributors';
import { excel } from '@/routes/statements';

type Props = {
    distributor: DistributorOption;
    statement: StatementEntry[];
    totals: StatementTotals;
};

/**
 * The statement laid out for paper.
 *
 * `print:` variants drop the buttons and the page chrome so the browser's
 * "Save as PDF" produces a clean document — the same mechanism the invoice
 * uses, and the reason no PDF library is needed to hand a distributor their
 * account.
 */
export default function Statement({ distributor, statement, totals }: Props) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    return (
        <>
            <Head title={`Statement — ${distributor.name}`} />

            <div className="mx-auto w-full max-w-4xl">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Statement of Account
                    </h1>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <Link
                                href={show({
                                    current_team: teamSlug,
                                    distributor: distributor.id,
                                })}
                            >
                                Back
                            </Link>
                        </Button>

                        {/* A plain anchor, not Inertia's Link: this is a file
                            download, and a client-side visit would try to parse
                            the CSV as a page. */}
                        <Button asChild variant="outline">
                            <a
                                href={
                                    excel({
                                        current_team: teamSlug,
                                        distributor: distributor.id,
                                    }).url
                                }
                            >
                                Excel
                            </a>
                        </Button>

                        <Button
                            onClick={() => window.print()}
                            className="bg-coffee-700 hover:bg-coffee-800"
                        >
                            Print / PDF
                        </Button>
                    </div>
                </div>

                <article className="rounded-lg border border-coffee-100 bg-white p-6 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none">
                    <header className="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-coffee-100 pb-4">
                        <div>
                            <p className="text-lg font-bold text-coffee-900">
                                {brand.name}
                            </p>
                            {brand.address && (
                                <p className="text-sm text-coffee-800/70">
                                    {brand.address}
                                </p>
                            )}
                            {brand.phone && (
                                <p className="text-sm text-coffee-800/70">
                                    {brand.phone}
                                </p>
                            )}
                        </div>

                        <div className="text-right">
                            <p className="font-display text-base font-bold text-coffee-900">
                                Statement of Account
                            </p>
                            <p className="text-sm text-coffee-800/70">
                                {distributor.name}
                            </p>
                            {distributor.proprietorName && (
                                <p className="text-sm text-coffee-800/70">
                                    {distributor.proprietorName}
                                </p>
                            )}
                            {distributor.fullAddress && (
                                <p className="max-w-xs text-sm text-coffee-800/70">
                                    {distributor.fullAddress}
                                </p>
                            )}
                        </div>
                    </header>

                    <StatementTable
                        entries={statement}
                        invoiceUrl={(id) => `/${teamSlug}/sales/invoices/${id}`}
                    />

                    <dl className="mt-6 ml-auto max-w-xs space-y-1 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-coffee-800/70">
                                Total charged
                            </dt>
                            <dd className="font-medium text-coffee-900 tabular-nums">
                                {money(totals.charged)}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-coffee-800/70">Total paid</dt>
                            <dd className="font-medium text-coffee-900 tabular-nums">
                                {money(totals.paid)}
                            </dd>
                        </div>
                        <div className="flex justify-between border-t border-coffee-200 pt-1">
                            <dt className="font-bold text-coffee-900">
                                {totals.due < 0 ? 'In credit' : 'Due'}
                            </dt>
                            <dd className="text-base font-bold text-coffee-900 tabular-nums">
                                {money(Math.abs(totals.due))}
                            </dd>
                        </div>
                    </dl>
                </article>
            </div>
        </>
    );
}
