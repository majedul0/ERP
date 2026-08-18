import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatAmount, formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { ProductStockReport } from '@/modules/products';
import { excel, index } from '@/routes/stock-reports';

const selectClasses =
    'h-10 rounded-md border border-coffee-200 bg-white px-3 text-sm text-coffee-900 shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

const headCell =
    'bg-coffee-500 px-3 py-3 text-left text-xs font-bold tracking-wide text-white uppercase whitespace-nowrap';
const bodyCell = 'px-3 py-2.5 whitespace-nowrap text-coffee-900';
const numberCell = `${bodyCell} text-right tabular-nums`;

const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

/**
 * Twelve years back and one forward.
 *
 * Forward because a company that closes its books in the first days of January
 * still wants next month's sheet, and because a production run can legitimately
 * be dated ahead of today.
 */
function yearOptions(current: number): number[] {
    const latest = new Date().getFullYear() + 1;
    const years: number[] = [];

    for (let year = latest; year >= latest - 12; year--) {
        years.push(year);
    }

    return years.includes(current) ? years : [current, ...years];
}

/**
 * Stock, month by month, per product.
 *
 * Reads as a warehouse sheet rather than as a dashboard, because it is checked
 * against one: every row states what it opened with, what was made, what went
 * out and what is left, and the columns add up left to right. `print:` variants
 * drop the chrome so the browser's "Save as PDF" hands over the same sheet —
 * the mechanism the invoice and the statement already use, and the reason no
 * PDF library is needed.
 */
export default function StockReport({
    report,
}: {
    report: ProductStockReport;
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    const [month, setMonth] = useState(report.period.month);
    const [year, setYear] = useState(report.period.year);

    const apply = () =>
        router.get(
            index(teamSlug).url,
            { month, year },
            { preserveState: true },
        );

    const drifted = report.rows.some((row) => row.balance !== 0);

    return (
        <>
            <Head title="Product Stock Report" />

            <div className="mb-6 flex flex-wrap items-end justify-between gap-3 print:hidden">
                <div className="flex flex-wrap items-end gap-2">
                    <div className="grid gap-1">
                        <Label htmlFor="month" className="text-xs">
                            Month
                        </Label>
                        <select
                            id="month"
                            className={selectClasses}
                            value={month}
                            onChange={(event) =>
                                setMonth(Number(event.target.value))
                            }
                        >
                            {months.map((name, position) => (
                                <option key={name} value={position + 1}>
                                    {name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-1">
                        <Label htmlFor="year" className="text-xs">
                            Year
                        </Label>
                        <select
                            id="year"
                            className={selectClasses}
                            value={year}
                            onChange={(event) =>
                                setYear(Number(event.target.value))
                            }
                        >
                            {yearOptions(report.period.year).map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                    </div>

                    <Button
                        onClick={apply}
                        className="h-10 bg-coffee-600 hover:bg-coffee-700"
                    >
                        Get Stock Report
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {/* A file download, so a plain anchor: an Inertia visit
                        would try to parse the CSV as a page. */}
                    <Button asChild variant="outline" className="h-10">
                        <a
                            href={`${excel(teamSlug).url}?month=${report.period.month}&year=${report.period.year}`}
                        >
                            Excel
                        </a>
                    </Button>

                    <Button
                        onClick={() => window.print()}
                        className="h-10 bg-coffee-600 hover:bg-coffee-700"
                    >
                        Print
                    </Button>
                </div>
            </div>

            <div className="mb-6 text-center">
                <h1 className="text-2xl font-bold text-coffee-900">
                    Product Stock Report
                </h1>
                <p className="text-lg font-semibold text-coffee-800">
                    {report.period.label}
                </p>
                <p className="hidden text-sm text-coffee-800/70 print:block">
                    {brand.name}
                </p>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm print:rounded-none print:border-0 print:shadow-none">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[70rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>S/L</th>
                                <th className={headCell}>Products</th>
                                <th className={`${headCell} text-right`}>
                                    Opening Stock
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Productions
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Total
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Sales
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Sales Value
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Fresh Returns
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Damaged
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Closing Stock
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Closing Stock Value
                                </th>
                                <th className={`${headCell} text-right`}>
                                    Balance
                                </th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-coffee-100">
                            {report.rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={12}
                                        className="px-4 py-10 text-center text-coffee-800/60"
                                    >
                                        No products registered yet.
                                    </td>
                                </tr>
                            )}

                            {report.rows.map((row, position) => (
                                <tr
                                    key={row.id}
                                    className="transition-colors hover:bg-coffee-50/60"
                                >
                                    <td className={bodyCell}>{position + 1}</td>
                                    <td className={`${bodyCell} font-medium`}>
                                        {row.name}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(row.opening)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(row.productions)}
                                    </td>
                                    <td className={`${numberCell} font-medium`}>
                                        {formatAmount(row.total)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(row.sales)}
                                    </td>
                                    <td className={numberCell}>
                                        {money(row.salesValue)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(row.freshReturns)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(row.damaged)}
                                    </td>
                                    <td className={`${numberCell} font-medium`}>
                                        {formatAmount(row.closing)}
                                    </td>
                                    <td className={numberCell}>
                                        {money(row.closingValue)}
                                    </td>
                                    <td
                                        className={`${numberCell} ${
                                            row.balance === 0
                                                ? ''
                                                : 'font-bold text-red-700'
                                        }`}
                                    >
                                        {formatAmount(row.balance)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>

                        {report.rows.length > 0 && (
                            <tfoot>
                                <tr className="border-t-2 border-coffee-200 bg-coffee-50 font-bold">
                                    <td className={bodyCell} colSpan={2}>
                                        Total
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(report.totals.opening)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(
                                            report.totals.productions,
                                        )}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(report.totals.total)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(report.totals.sales)}
                                    </td>
                                    <td className={numberCell}>
                                        {money(report.totals.salesValue)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(
                                            report.totals.freshReturns,
                                        )}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(report.totals.damaged)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(report.totals.closing)}
                                    </td>
                                    <td className={numberCell}>
                                        {money(report.totals.closingValue)}
                                    </td>
                                    <td className={numberCell}>
                                        {formatAmount(report.totals.balance)}
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>

            <div className="mt-4 space-y-1 text-xs text-coffee-800/60">
                <p>
                    Closing Stock = Opening Stock + Productions − Sales −
                    Damaged. Sales is net of everything the distributor sent
                    back; goods returned in sellable condition are shown under
                    Fresh Returns, and the rest under Damaged with anything
                    written off in the warehouse. Cancelled and returned
                    invoices are excluded.
                </p>
                <p>
                    Balance is the check column: stock on the shelf, less
                    everything the books say ever moved. It should be zero.
                </p>
                {drifted && (
                    <p className="font-semibold text-red-700 print:hidden">
                        A product shows a non-zero Balance — its stock was
                        changed without a recorded movement. Recount it from
                        Products to bring the two back into line.
                    </p>
                )}
            </div>
        </>
    );
}
