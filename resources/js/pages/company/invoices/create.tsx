import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useCompanyBrand } from '@/modules/company';
import type { DistributorOption } from '@/modules/invoices';
import {
    DistributorSummary,
    InvoiceField,
    InvoiceLineRows,
    InvoiceTotals,
    useInvoiceDraft,
} from '@/modules/invoices';
import type { InvoiceProductOption } from '@/modules/products';
import { store } from '@/routes/invoices';

type Props = {
    distributors: DistributorOption[];
    products: InvoiceProductOption[];
    nextInvoiceNumber: string;
};

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

export default function CreateInvoice({
    distributors,
    products,
    nextInvoiceNumber,
}: Props) {
    const brand = useCompanyBrand();
    const { errors, currentTeam } = usePage().props;

    const [soldAt, setSoldAt] = useState(today);
    const [comment, setComment] = useState('');
    const [schemeDescription, setSchemeDescription] = useState('');
    const [schemeAmount, setSchemeAmount] = useState('0');
    const [processing, setProcessing] = useState(false);

    const draft = useInvoiceDraft(products, distributors);
    const scheme = Number.parseFloat(schemeAmount) || 0;

    const submit = () => {
        setProcessing(true);

        router.post(
            store(currentTeam?.slug ?? '').url,
            {
                sold_at: soldAt,
                distributor_id: draft.distributorId,
                comment: comment || null,
                scheme_description: schemeDescription || null,
                scheme_amount: scheme,
                items: draft.payloadItems,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const canSubmit =
        draft.distributorId !== null && draft.payloadItems.length > 0;

    // Errors for the item rows arrive keyed by index too; surface whichever
    // the server sent so a stock failure is never silent.
    const itemError =
        errors.items ??
        Object.entries(errors).find(([key]) => key.startsWith('items.'))?.[1];

    return (
        <>
            <Head title="Create Sales Invoice" />

            <div className="pb-16">
                <h1 className="mt-2 text-center text-2xl font-bold text-ocean-900">
                    Create Sales Invoice
                </h1>
                <p className="mt-1 text-center text-sm text-ocean-800/60">
                    Next number: {nextInvoiceNumber} — assigned when you press
                    Create, so simultaneous invoices never share one.
                </p>

                <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <InvoiceField label="Datetime:" htmlFor="sold_at">
                        <Input
                            id="sold_at"
                            type="date"
                            value={soldAt}
                            onChange={(event) => setSoldAt(event.target.value)}
                        />
                        <InputError message={errors.sold_at} />
                    </InvoiceField>

                    <InvoiceField label="Distributor:" htmlFor="distributor_id">
                        <select
                            id="distributor_id"
                            className={selectClasses}
                            value={draft.distributorId ?? ''}
                            onChange={(event) =>
                                draft.setDistributorId(
                                    event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                )
                            }
                        >
                            <option value="">Select a distributor…</option>
                            {distributors.map((distributor) => (
                                <option
                                    key={distributor.id}
                                    value={distributor.id}
                                >
                                    {distributor.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.distributor_id} />
                    </InvoiceField>

                    <InvoiceField label="Comment:" htmlFor="comment">
                        <Input
                            id="comment"
                            value={comment}
                            onChange={(event) => setComment(event.target.value)}
                        />
                    </InvoiceField>

                    <InvoiceField
                        label="Scheme Description:"
                        htmlFor="scheme_description"
                    >
                        <Input
                            id="scheme_description"
                            value={schemeDescription}
                            onChange={(event) =>
                                setSchemeDescription(event.target.value)
                            }
                        />
                    </InvoiceField>

                    <InvoiceField
                        label="Scheme Amount:"
                        htmlFor="scheme_amount"
                    >
                        <Input
                            id="scheme_amount"
                            type="number"
                            min={0}
                            step="0.01"
                            value={schemeAmount}
                            onChange={(event) =>
                                setSchemeAmount(event.target.value)
                            }
                        />
                        <InputError message={errors.scheme_amount} />
                    </InvoiceField>
                </div>

                <DistributorSummary distributor={draft.distributor} />

                <div className="mt-8 mb-3 flex items-center justify-between">
                    <h2 className="text-lg font-bold text-ocean-900">
                        Products
                    </h2>
                    <Button
                        type="button"
                        onClick={draft.addLine}
                        className="bg-ocean-600 hover:bg-ocean-700"
                    >
                        Add Item
                    </Button>
                </div>

                <InvoiceLineRows
                    lines={draft.lines}
                    products={products}
                    onUpdate={draft.updateLine}
                    onRemove={draft.removeLine}
                />

                {itemError && (
                    <p className="mt-3 text-sm font-medium text-red-600">
                        {itemError}
                    </p>
                )}

                <InvoiceTotals
                    className="mt-6"
                    currencySymbol={brand.currencySymbol}
                    rows={[
                        {
                            label: 'Invoice Total',
                            value: draft.totals.invoiceTotal,
                        },
                        {
                            label: 'Discount Total',
                            value: draft.totals.discountTotal,
                        },
                        ...(scheme > 0
                            ? [{ label: 'Scheme Amount', value: scheme }]
                            : []),
                        {
                            label: 'Previous Dues',
                            value: draft.totals.previousDues,
                            hint: "The distributor's outstanding balance, carried forward automatically.",
                        },
                        {
                            label: 'Total Amount',
                            value:
                                draft.totals.netTotal -
                                scheme +
                                draft.totals.previousDues,
                            emphasis: true,
                        },
                    ]}
                />

                <div className="mt-6 flex justify-end">
                    <Button
                        type="button"
                        onClick={submit}
                        disabled={!canSubmit || processing}
                        className="bg-ocean-700 px-8 hover:bg-ocean-800"
                        data-test="create-invoice-button"
                    >
                        {processing && <Spinner />}
                        Create
                    </Button>
                </div>
            </div>
        </>
    );
}
