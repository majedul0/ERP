import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useCompanyBrand } from '@/modules/company';
import type { InvoiceProductOption } from '@/modules/products';
import type { InvoiceDraftSeed } from '../hooks/use-invoice-draft';
import { toWholeAmount, useInvoiceDraft } from '../hooks/use-invoice-draft';
import { useStockWatcher } from '../hooks/use-stock-watcher';
import type { DistributorOption } from '../types';
import DistributorSummary from './distributor-summary';
import InvoiceField from './invoice-field';
import InvoiceLineRows from './invoice-line-rows';
import InvoiceTotals from './invoice-totals';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

export type InvoicePayload = {
    sold_at: string;
    distributor_id: number | null;
    comment: string | null;
    scheme_description: string | null;
    scheme_amount: number;
    previous_dues: number;
    items: ReturnType<typeof useInvoiceDraft>['payloadItems'];
};

export type InvoiceFormSeed = InvoiceDraftSeed & {
    soldAt: string;
    comment: string | null;
    schemeDescription: string | null;
    schemeAmount: number;
    previousDues: number;
};

type Props = {
    distributors: DistributorOption[];
    products: InvoiceProductOption[];
    errors: Record<string, string>;
    processing: boolean;
    submitLabel: string;
    onSubmit: (payload: InvoicePayload) => void;
    seed?: InvoiceFormSeed;
    /** Watches for stock other people have moved; omitted on the edit screen. */
    stock?: { version: number; versionUrl: string };
};

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * The invoice form, shared by the create and edit screens.
 *
 * One component so an edit cannot quietly diverge from a create — the same
 * fields, the same line editing, the same running totals.
 */
export default function InvoiceForm({
    distributors,
    products,
    errors,
    processing,
    submitLabel,
    onSubmit,
    seed,
    stock,
}: Props) {
    const brand = useCompanyBrand();

    const [soldAt, setSoldAt] = useState(() => seed?.soldAt ?? today());
    const [comment, setComment] = useState(seed?.comment ?? '');
    const [schemeDescription, setSchemeDescription] = useState(
        seed?.schemeDescription ?? '',
    );
    const [schemeAmount, setSchemeAmount] = useState(
        String(seed?.schemeAmount ?? 0),
    );

    const draft = useInvoiceDraft(products, distributors, seed);
    const scheme = toWholeAmount(schemeAmount);

    const watcher = useStockWatcher({
        version: stock?.version ?? 0,
        versionUrl: stock?.versionUrl ?? '',
        enabled: stock !== undefined,
    });

    /*
     * Previous dues default to whatever the distributor's account says, and
     * follow it when the distributor changes. A figure typed here is kept only
     * while that distributor stays selected — which avoids an effect, and
     * avoids carrying one distributor's opening balance onto another.
     */
    const [duesEntry, setDuesEntry] = useState<{
        distributorId: number | null;
        value: string;
    } | null>(
        seed
            ? {
                  distributorId: seed.distributorId,
                  value: String(seed.previousDues),
              }
            : null,
    );

    const duesIsEdited = duesEntry?.distributorId === draft.distributorId;
    const previousDuesText = duesIsEdited
        ? (duesEntry?.value ?? '0')
        : String(draft.totals.previousDues);
    const previousDues = toWholeAmount(previousDuesText);

    const canSubmit =
        draft.distributorId !== null && draft.payloadItems.length > 0;

    // Row errors arrive keyed by index too; surface whichever the server sent
    // so a stock failure is never silent.
    const itemError =
        errors.items ??
        Object.entries(errors).find(([key]) => key.startsWith('items.'))?.[1];

    return (
        <div className="pb-16">
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
                            <option key={distributor.id} value={distributor.id}>
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

                <InvoiceField label="Scheme Amount:" htmlFor="scheme_amount">
                    <Input
                        id="scheme_amount"
                        type="number"
                        min={0}
                        step={1}
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
                <h2 className="text-lg font-bold text-ocean-900">Products</h2>
                <div className="flex items-center gap-3">
                    {watcher.refreshedAt && (
                        <span className="text-xs text-emerald-700">
                            Stock updated{' '}
                            {watcher.refreshedAt.toLocaleTimeString()}
                        </span>
                    )}
                    <Button
                        type="button"
                        onClick={() => {
                            draft.addLine();

                            // Adding a line is the moment stock matters most,
                            // so re-check before the user picks a product.
                            void watcher.checkNow();
                        }}
                        className="bg-ocean-600 hover:bg-ocean-700"
                    >
                        Add Item
                    </Button>
                </div>
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
                        value: previousDues,
                        hint:
                            previousDues === draft.totals.previousDues
                                ? "The distributor's outstanding balance, carried forward automatically."
                                : `Overriding the account, which says ${draft.totals.previousDues}.`,
                        control: (
                            <Input
                                type="number"
                                step={1}
                                aria-label="Previous dues"
                                className="h-8 w-36 text-right tabular-nums"
                                value={previousDuesText}
                                onChange={(event) =>
                                    setDuesEntry({
                                        distributorId: draft.distributorId,
                                        value: event.target.value,
                                    })
                                }
                                data-test="previous-dues-input"
                            />
                        ),
                    },
                    {
                        label: 'Total Amount',
                        value: draft.totals.netTotal - scheme + previousDues,
                        emphasis: true,
                    },
                ]}
            />

            <div className="mt-6 flex justify-end">
                <Button
                    type="button"
                    disabled={!canSubmit || processing}
                    onClick={() =>
                        onSubmit({
                            sold_at: soldAt,
                            distributor_id: draft.distributorId,
                            comment: comment || null,
                            scheme_description: schemeDescription || null,
                            scheme_amount: scheme,
                            previous_dues: previousDues,
                            items: draft.payloadItems,
                        })
                    }
                    className="bg-ocean-700 px-8 hover:bg-ocean-800"
                    data-test="save-invoice-button"
                >
                    {processing && <Spinner />}
                    {submitLabel}
                </Button>
            </div>
        </div>
    );
}
