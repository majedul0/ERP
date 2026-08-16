import { Head, Link, usePage } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatMoney, formatSaleDate } from '@/lib/format';
import { useCan, useCompanyBrand } from '@/modules/company';
import type { SalesReturnDetail } from '@/modules/sales-returns';
import { DeleteReturnDialog, ReturnDocument } from '@/modules/sales-returns';
import { edit, index } from '@/routes/returns';

const headCell =
    'bg-coffee-500 px-4 py-3 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-4 py-3 text-coffee-900';

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

export default function ShowReturn({
    return: salesReturn,
}: {
    return: SalesReturnDetail;
}) {
    const brand = useCompanyBrand();
    const can = useCan();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    return (
        <>
            <Head title={salesReturn.returnNumber} />

            {/*
                Two renderings of the same return. The screen keeps the card
                layout, which reads better on a monitor; paper gets the same
                document a sales invoice prints, because that is what the
                distributor is used to receiving. Neither is a copy of the
                other's data — both read these props.
            */}
            <div className="print:hidden">
                <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-coffee-900">
                            {salesReturn.returnNumber}
                        </h1>
                        <p className="mt-1 font-display text-sm text-coffee-800/60">
                            Returned {formatSaleDate(salesReturn.returnedOn)} by{' '}
                            <Link
                                href={salesReturn.distributorUrl}
                                className="underline underline-offset-4"
                            >
                                {salesReturn.distributorName}
                            </Link>
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <Link href={index(teamSlug)}>All Returns</Link>
                        </Button>

                        {can('return:manage') && (
                            <>
                                <Button asChild variant="outline">
                                    <Link
                                        href={edit({
                                            current_team: teamSlug,
                                            return: salesReturn.id,
                                        })}
                                    >
                                        Edit
                                    </Link>
                                </Button>

                                <DeleteReturnDialog
                                    teamSlug={teamSlug}
                                    returnId={salesReturn.id}
                                    returnNumber={salesReturn.returnNumber}
                                    restock={salesReturn.restock}
                                />
                            </>
                        )}

                        <Button
                            type="button"
                            onClick={() => window.print()}
                            className="bg-coffee-700 hover:bg-coffee-800"
                        >
                            <Printer className="size-4" />
                            Print
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
                                label="Distributor"
                                value={salesReturn.distributorName}
                            />
                            <Detail
                                label="Proprietor"
                                value={salesReturn.proprietorName ?? ''}
                            />
                            <Detail
                                label="Return date"
                                value={formatSaleDate(salesReturn.returnedOn)}
                            />
                            <Detail
                                label="Stock"
                                value={
                                    salesReturn.restock
                                        ? 'Put back on the shelf'
                                        : 'Not restocked'
                                }
                            />
                            <Detail
                                label="Comment"
                                value={salesReturn.comment ?? ''}
                            />
                        </dl>
                    </div>

                    {/* The credit is the headline: it is what this changes. */}
                    <div className="rounded-lg bg-coffee-500 p-5 text-white shadow-sm">
                        <p className="text-sm font-medium text-white/85">
                            Credited to the account
                        </p>
                        <p className="mt-1 text-4xl font-bold">
                            {money(salesReturn.amount)}
                        </p>
                        <p className="mt-6 text-xs text-white/75">
                            This reduces what {salesReturn.distributorName} owes
                            and appears on their statement, dated{' '}
                            {formatSaleDate(salesReturn.returnedOn)}.
                        </p>
                    </div>
                </section>

                <section className="mt-8 overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[42rem] text-sm">
                            <thead>
                                <tr>
                                    <th className={headCell}>Product</th>
                                    <th className={headCell}>SKU</th>
                                    <th className={`${headCell} text-right`}>
                                        Quantity
                                    </th>
                                    <th className={`${headCell} text-right`}>
                                        Unit Price
                                    </th>
                                    <th className={`${headCell} text-right`}>
                                        Amount
                                    </th>
                                    <th className={headCell}>Remarks</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-coffee-100">
                                {salesReturn.items.map((item) => (
                                    <tr key={item.id}>
                                        <td
                                            className={`${bodyCell} font-medium`}
                                        >
                                            {item.productName}
                                        </td>
                                        <td className={bodyCell}>
                                            {item.productSku ?? '—'}
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right tabular-nums`}
                                        >
                                            {item.quantity}
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right tabular-nums`}
                                        >
                                            {money(item.unitPrice)}
                                        </td>
                                        <td
                                            className={`${bodyCell} text-right font-medium tabular-nums`}
                                        >
                                            {money(item.amount)}
                                        </td>
                                        <td className={bodyCell}>
                                            {item.remarks ?? ''}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {/* Paper only — the same document a sales invoice prints. */}
            <div className="hidden print:block">
                <ReturnDocument brand={brand} salesReturn={salesReturn} />
            </div>
        </>
    );
}
