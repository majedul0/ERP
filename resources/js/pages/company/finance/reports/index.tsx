import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { FinancialAnalytics, FinancialReport } from '@/modules/finance';
import { AnalyticsBand, mergeQuery } from '@/modules/finance';
import { excel, index } from '@/routes/reports';

function Card({
    label,
    value,
    tone = 'plain',
    hint,
}: {
    label: string;
    value: string;
    tone?: 'plain' | 'good' | 'bad';
    hint?: string;
}) {
    const toneClasses = {
        plain: 'text-coffee-900',
        good: 'text-emerald-700',
        bad: 'text-red-700',
    }[tone];

    return (
        <div className="rounded-lg border border-coffee-100 bg-white p-4 shadow-sm">
            <p className="text-xs font-semibold tracking-wide text-coffee-800/60 uppercase">
                {label}
            </p>
            <p
                className={`mt-1 text-2xl font-bold tabular-nums ${toneClasses}`}
            >
                {value}
            </p>
            {hint && <p className="mt-1 text-xs text-coffee-800/60">{hint}</p>}
        </div>
    );
}

function Row({
    label,
    value,
    emphasis = false,
}: {
    label: string;
    value: string;
    emphasis?: boolean;
}) {
    return (
        <div
            className={`flex justify-between gap-4 py-2 ${
                emphasis ? 'border-t border-coffee-200 font-bold' : ''
            }`}
        >
            <dt className={emphasis ? 'text-coffee-900' : 'text-coffee-800/70'}>
                {label}
            </dt>
            <dd className="text-coffee-900 tabular-nums">{value}</dd>
        </div>
    );
}

export default function Reports({
    report,
    analytics,
    yearOptions,
}: {
    report: FinancialReport;
    analytics: FinancialAnalytics;
    yearOptions: number[];
}) {
    const brand = useCompanyBrand();
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    const [from, setFrom] = useState(report.period.from);
    const [to, setTo] = useState(report.period.to);

    const apply = () =>
        router.get(index(teamSlug).url, mergeQuery({ from, to }), {
            preserveState: true,
            preserveScroll: true,
            only: ['report'],
        });

    return (
        <>
            <Head title="Financial Report" />

            <h1 className="mb-5 text-xl font-bold text-coffee-900">
                Financial Report
            </h1>

            <AnalyticsBand
                analytics={analytics}
                yearOptions={yearOptions}
                standing={report.standing}
                currencySymbol={brand.currencySymbol}
                reportUrl={index(teamSlug).url}
            />

            <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 className="text-lg font-bold text-coffee-900">
                        Detail
                    </h2>
                    <p className="text-sm text-coffee-800/60">
                        {report.period.from} to {report.period.to}
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-2">
                    <div className="grid gap-1">
                        <Label htmlFor="from" className="text-xs">
                            From
                        </Label>
                        <Input
                            id="from"
                            type="date"
                            className="h-9 w-40"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="to" className="text-xs">
                            To
                        </Label>
                        <Input
                            id="to"
                            type="date"
                            className="h-9 w-40"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                        />
                    </div>

                    <Button
                        onClick={apply}
                        className="bg-coffee-600 hover:bg-coffee-700"
                    >
                        Apply
                    </Button>

                    {/* A file download, so a plain anchor, not an Inertia visit. */}
                    <Button asChild variant="outline">
                        <a
                            href={`${excel(teamSlug).url}?from=${from}&to=${to}`}
                        >
                            Excel
                        </a>
                    </Button>
                </div>
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card
                    label="Sales charged"
                    value={money(report.sales.net)}
                    hint={`${report.sales.invoiceCount} invoice(s)`}
                />
                <Card
                    label="Received"
                    value={money(report.money.received)}
                    tone="good"
                />
                <Card
                    label="Expenses"
                    value={money(report.money.expenses)}
                    tone="bad"
                />
                <Card
                    label="Net cash"
                    value={money(report.money.netCash)}
                    tone={report.money.netCash < 0 ? 'bad' : 'good'}
                    hint="Received, less expenses and vendor payments"
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <section className="rounded-lg border border-coffee-100 bg-white p-5 shadow-sm">
                    <h2 className="mb-2 text-base font-bold text-coffee-900">
                        Sales
                    </h2>
                    <dl className="text-sm">
                        <Row label="Gross" value={money(report.sales.gross)} />
                        <Row
                            label="Discounts"
                            value={money(report.sales.discounts)}
                        />
                        <Row
                            label="Schemes"
                            value={money(report.sales.schemes)}
                        />
                        <Row
                            label="Returns"
                            value={money(report.sales.returns)}
                        />
                        <Row
                            label="Net charged"
                            value={money(report.sales.net)}
                            emphasis
                        />
                    </dl>
                    <p className="mt-3 text-xs text-coffee-800/60">
                        Cancelled and returned invoices are excluded — a void
                        sale is not revenue. Returns count on the day the goods
                        came back, not against the month that sold them.
                    </p>
                </section>

                <section className="rounded-lg border border-coffee-100 bg-white p-5 shadow-sm">
                    <h2 className="mb-2 text-base font-bold text-coffee-900">
                        Money
                    </h2>
                    <dl className="text-sm">
                        <Row
                            label="Received from distributors"
                            value={money(report.money.received)}
                        />
                        <Row
                            label="Expenses"
                            value={money(report.money.expenses)}
                        />
                        <Row
                            label="Paid to vendors"
                            value={money(report.money.vendorPaid)}
                        />
                        <Row
                            label="Net cash"
                            value={money(report.money.netCash)}
                            emphasis
                        />
                        <Row
                            label="Billed by vendors"
                            value={money(report.money.vendorBilled)}
                        />
                        <Row
                            label="Material purchases"
                            value={money(report.money.materialPurchases)}
                        />
                    </dl>
                </section>

                <section className="rounded-lg border border-coffee-100 bg-white p-5 shadow-sm">
                    <h2 className="mb-2 text-base font-bold text-coffee-900">
                        Standing today
                    </h2>
                    <dl className="text-sm">
                        <Row
                            label="Receivable from distributors"
                            value={money(report.standing.receivable)}
                        />
                        <Row
                            label="Payable to vendors"
                            value={money(report.standing.payable)}
                        />
                        <Row
                            label="Material stock value"
                            value={money(report.standing.materialStockValue)}
                        />
                    </dl>
                    <p className="mt-3 text-xs text-coffee-800/60">
                        Balances, not flows — these are as of today whatever
                        period is selected.
                    </p>
                </section>
            </div>

            <section className="mt-6 rounded-lg border border-coffee-100 bg-white p-5 shadow-sm">
                <h2 className="mb-2 text-base font-bold text-coffee-900">
                    Expenses by category
                </h2>

                {report.expensesByCategory.length === 0 ? (
                    <p className="py-4 text-sm text-coffee-800/60">
                        No expenses in this period.
                    </p>
                ) : (
                    <dl className="text-sm">
                        {report.expensesByCategory.map((line) => (
                            <Row
                                key={line.category}
                                label={line.label}
                                value={money(line.amount)}
                            />
                        ))}
                        <Row
                            label="Total"
                            value={money(report.money.expenses)}
                            emphasis
                        />
                    </dl>
                )}
            </section>

            <p className="mt-6 text-xs text-coffee-800/60">
                Profit is not shown: an invoice records what a product sold for,
                not what it cost, so a profit line here would be a guess dressed
                up as a figure.
            </p>
        </>
    );
}
