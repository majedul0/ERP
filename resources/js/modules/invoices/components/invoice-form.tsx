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
    /**
     * Present only when the user turned the override on.
     *
     * Absent means "follow the account", which the server reads with `isset()`.
     * Sending a number the user never chose is what pinned invoices by
     * accident, so the key simply does not exist unless it was asked for.
     */
    previous_dues?: number;
    /** The opt-in the server requires before it will honour `previous_dues`. */
    previous_dues_override?: true;
    /**
     * Print this invoice without the running account on it.
     *
     * Presentation only — the balance and the statement are identical either
     * way. Deliberately separate from `previous_dues`, which changes what the
     * account says.
     */
    hide_previous_dues: boolean;
    items: ReturnType<typeof useInvoiceDraft>['payloadItems'];
};

export type InvoiceFormSeed = InvoiceDraftSeed & {
    soldAt: string;
    comment: string | null;
    schemeDescription: string | null;
    schemeAmount: number;
    /** The pinned figure, or null when the invoice follows the account. */
    previousDuesOverride: number | null;
    /** Whether this invoice prints without the running account on it. */
    hidePreviousDues: boolean;
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
     * Previous dues follow the distributor's account unless the user
     * deliberately types a figure — which changes what this invoice prints and
     * nothing else. The balance is a plain running total either way.
     *
     * Opting in explicitly, rather than inferring intent from "the field holds
     * a different number", is the whole point. The field used to be permanently
     * editable and a `number` input changes on a stray scroll or arrow key, so
     * an accidental nudge silently rewrote the invoice. A figure can only be
     * sent now if this is on, and it starts on only for an invoice that already
     * carries one.
     */
    const [overrideDues, setOverrideDues] = useState(
        seed?.previousDuesOverride != null,
    );
    const [duesText, setDuesText] = useState(
        seed?.previousDuesOverride != null
            ? String(seed.previousDuesOverride)
            : '',
    );

    const accountDues = draft.totals.previousDues;
    const previousDues = overrideDues ? toWholeAmount(duesText) : accountDues;

    /*
     * Leave the running account off the printed invoice.
     *
     * Presentation only, and deliberately separate from the override: this
     * hands the distributor a clean bill for these goods while their balance
     * and statement carry on exactly as they would have. Nothing about what is
     * owed changes — only what this sheet of paper says.
     */
    const [hideDues, setHideDues] = useState(seed?.hidePreviousDues ?? false);

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
                <h2 className="text-lg font-bold text-coffee-900">Products</h2>
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
                        className="bg-coffee-600 hover:bg-coffee-700"
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
                        value: hideDues ? 0 : previousDues,
                        hint: hideDues
                            ? `Left off this invoice. The account still says ${accountDues}, and the balance is unchanged.`
                            : overrideDues
                              ? `Printed on this invoice only. The account still says ${accountDues}, and the balance is unchanged.`
                              : "The distributor's outstanding balance, carried forward automatically.",
                        control: (
                            <div className="flex flex-wrap items-center justify-end gap-x-3 gap-y-1">
                                <label className="flex items-center gap-1.5 text-xs text-coffee-800/70">
                                    <input
                                        type="checkbox"
                                        checked={hideDues}
                                        onChange={(event) =>
                                            setHideDues(event.target.checked)
                                        }
                                        data-test="hide-dues-toggle"
                                    />
                                    Hide on invoice
                                </label>

                                <label
                                    className="flex items-center gap-1.5 text-xs text-coffee-800/70"
                                    hidden={hideDues}
                                >
                                    <input
                                        type="checkbox"
                                        checked={overrideDues}
                                        onChange={(event) => {
                                            setOverrideDues(
                                                event.target.checked,
                                            );

                                            // Start from what the account says,
                                            // so turning this on to nudge a
                                            // figure does not begin at zero.
                                            if (event.target.checked) {
                                                setDuesText(
                                                    String(accountDues),
                                                );
                                            }
                                        }}
                                        data-test="override-dues-toggle"
                                    />
                                    Set manually
                                </label>

                                {overrideDues && !hideDues && (
                                    <Input
                                        type="number"
                                        step={1}
                                        aria-label="Previous dues"
                                        className="h-8 w-36 text-right tabular-nums"
                                        value={duesText}
                                        onChange={(event) =>
                                            setDuesText(event.target.value)
                                        }
                                        data-test="previous-dues-input"
                                    />
                                )}
                            </div>
                        ),
                    },
                    {
                        label: 'Total Amount',
                        value: hideDues
                            ? draft.totals.netTotal - scheme
                            : draft.totals.netTotal - scheme + previousDues,
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
                            items: draft.payloadItems,
                            hide_previous_dues: hideDues,

                            /*
                             * The key is omitted entirely when the override is
                             * off, rather than sent as null. The server decides
                             * with `isset()`, so an absent key cannot be
                             * misread however the request is serialised — and
                             * there is then no value in the payload for
                             * anything downstream to coerce into a zero.
                             */
                            ...(overrideDues
                                ? {
                                      previous_dues_override: true,
                                      previous_dues: previousDues,
                                  }
                                : {}),
                        })
                    }
                    className="bg-coffee-700 px-8 hover:bg-coffee-800"
                    data-test="save-invoice-button"
                >
                    {processing && <Spinner />}
                    {submitLabel}
                </Button>
            </div>
        </div>
    );
}
