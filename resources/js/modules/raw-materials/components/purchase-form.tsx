import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { PurchaseMaterialOption } from '../types';

const selectClasses =
    'h-9 w-full rounded-md border border-coffee-200 bg-white px-2 text-sm text-coffee-900 shadow-xs focus-visible:border-coffee-400 focus-visible:ring-[3px] focus-visible:ring-coffee-200 focus-visible:outline-none';
const headCell =
    'bg-coffee-500 px-3 py-2 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-3 py-2 align-top';

export type PurchaseLinePayload = {
    raw_material_id: number;
    quantity: number;
    unit_cost: number;
};

export type PurchasePayload = {
    supplier_name: string;
    reference: string | null;
    purchased_at: string;
    note: string | null;
    items: PurchaseLinePayload[];
};

type Line = {
    key: number;
    materialId: number | null;
    quantity: string;
    unitCost: string;
};

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * Whole numbers only, matching the server. An empty or half-typed field reads
 * as 0 here so the running total never shows NaN while someone is typing; the
 * server validates the submitted figure properly and is what decides.
 */
function toWhole(value: string): number {
    const parsed = Number.parseInt(value, 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

let nextKey = 1;

function blankLine(): Line {
    return { key: nextKey++, materialId: null, quantity: '', unitCost: '' };
}

/**
 * The raw-material purchase form.
 *
 * The totals shown here are for the person typing. The server recomputes every
 * one of them from the submitted quantities and costs, so nothing on this
 * screen decides what a purchase is worth — see RecordMaterialPurchase.
 */
export function PurchaseForm({
    materials,
    errors,
    processing,
    onSubmit,
}: {
    materials: PurchaseMaterialOption[];
    errors: Record<string, string>;
    processing: boolean;
    onSubmit: (payload: PurchasePayload) => void;
}) {
    const brand = useCompanyBrand();

    const [supplierName, setSupplierName] = useState('');
    const [reference, setReference] = useState('');
    const [purchasedAt, setPurchasedAt] = useState(today);
    const [note, setNote] = useState('');
    const [lines, setLines] = useState<Line[]>(() => [blankLine()]);

    const updateLine = (key: number, patch: Partial<Line>) => {
        setLines((current) =>
            current.map((line) =>
                line.key === key ? { ...line, ...patch } : line,
            ),
        );
    };

    const lineTotal = (line: Line) =>
        toWhole(line.quantity) * toWhole(line.unitCost);

    const total = lines.reduce((sum, line) => sum + lineTotal(line), 0);

    const submit = () => {
        onSubmit({
            supplier_name: supplierName,
            reference: reference || null,
            purchased_at: purchasedAt,
            note: note || null,
            items: lines
                // A blank trailing row is how people leave a form, not an
                // attempt to buy nothing — drop it rather than fail on it.
                .filter((line) => line.materialId !== null)
                .map((line) => ({
                    raw_material_id: line.materialId as number,
                    quantity: toWhole(line.quantity),
                    unit_cost: toWhole(line.unitCost),
                })),
        });
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
            className="space-y-6 pb-16"
        >
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="supplier_name" className="text-coffee-900">
                        Supplier<span aria-hidden="true">*</span>
                    </Label>
                    <Input
                        id="supplier_name"
                        value={supplierName}
                        onChange={(event) =>
                            setSupplierName(event.target.value)
                        }
                        required
                    />
                    <InputError message={errors.supplier_name} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="reference" className="text-coffee-900">
                        Bill Number
                    </Label>
                    <Input
                        id="reference"
                        value={reference}
                        onChange={(event) => setReference(event.target.value)}
                        placeholder="The supplier's own reference"
                    />
                    <InputError message={errors.reference} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="purchased_at" className="text-coffee-900">
                        Purchase Date<span aria-hidden="true">*</span>
                    </Label>
                    <Input
                        id="purchased_at"
                        type="date"
                        value={purchasedAt}
                        onChange={(event) => setPurchasedAt(event.target.value)}
                        required
                    />
                    <InputError message={errors.purchased_at} />
                </div>
            </div>

            <div className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[48rem] text-sm">
                        <thead>
                            <tr>
                                <th className={headCell}>Material</th>
                                <th className={headCell}>In Stock</th>
                                <th className={headCell}>Quantity</th>
                                <th className={headCell}>Unit Cost</th>
                                <th className={`${headCell} text-right`}>
                                    Line Total
                                </th>
                                <th className={headCell}>
                                    <span className="sr-only">Remove</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {lines.map((line) => {
                                const material = materials.find(
                                    (candidate) =>
                                        candidate.id === line.materialId,
                                );

                                return (
                                    <tr key={line.key}>
                                        <td
                                            className={`${bodyCell} min-w-[14rem]`}
                                        >
                                            <select
                                                aria-label="Material"
                                                className={selectClasses}
                                                value={line.materialId ?? ''}
                                                onChange={(event) => {
                                                    const id = event.target
                                                        .value
                                                        ? Number(
                                                              event.target
                                                                  .value,
                                                          )
                                                        : null;
                                                    const chosen =
                                                        materials.find(
                                                            (candidate) =>
                                                                candidate.id ===
                                                                id,
                                                        );

                                                    updateLine(line.key, {
                                                        materialId: id,
                                                        // Start from what it
                                                        // last cost; the person
                                                        // typing corrects it to
                                                        // this bill.
                                                        unitCost: chosen
                                                            ? String(
                                                                  chosen.unitCost,
                                                              )
                                                            : '',
                                                    });
                                                }}
                                            >
                                                <option value="">
                                                    Select a material…
                                                </option>
                                                {materials.map((option) => (
                                                    <option
                                                        key={option.id}
                                                        value={option.id}
                                                    >
                                                        {option.name} (
                                                        {option.code})
                                                    </option>
                                                ))}
                                            </select>
                                        </td>

                                        <td
                                            className={`${bodyCell} whitespace-nowrap text-coffee-800/70 tabular-nums`}
                                        >
                                            {material
                                                ? `${material.stockQuantity} ${material.unitShort}`
                                                : '—'}
                                        </td>

                                        <td className={bodyCell}>
                                            <Input
                                                aria-label="Quantity"
                                                type="number"
                                                min={1}
                                                step={1}
                                                value={line.quantity}
                                                onChange={(event) =>
                                                    updateLine(line.key, {
                                                        quantity:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        </td>

                                        <td className={bodyCell}>
                                            <Input
                                                aria-label="Unit cost"
                                                type="number"
                                                min={0}
                                                step={1}
                                                value={line.unitCost}
                                                onChange={(event) =>
                                                    updateLine(line.key, {
                                                        unitCost:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        </td>

                                        <td
                                            className={`${bodyCell} text-right font-medium text-coffee-900 tabular-nums`}
                                        >
                                            {formatMoney(
                                                lineTotal(line),
                                                brand.currencySymbol,
                                            )}
                                        </td>

                                        <td className={bodyCell}>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setLines((current) =>
                                                        current.length === 1
                                                            ? [blankLine()]
                                                            : current.filter(
                                                                  (candidate) =>
                                                                      candidate.key !==
                                                                      line.key,
                                                              ),
                                                    )
                                                }
                                                className="text-sm text-coffee-700 underline underline-offset-4 hover:text-coffee-900"
                                            >
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            <InputError message={errors.items} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        setLines((current) => [...current, blankLine()])
                    }
                >
                    + Add Line
                </Button>

                <p className="text-lg font-bold text-coffee-900 tabular-nums">
                    Total: {formatMoney(total, brand.currencySymbol)}
                </p>
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="note" className="text-coffee-900">
                    Note
                </Label>
                <Input
                    id="note"
                    value={note}
                    onChange={(event) => setNote(event.target.value)}
                    placeholder="Optional"
                />
                <InputError message={errors.note} />
            </div>

            <Button
                type="submit"
                disabled={processing}
                className="w-full bg-coffee-600 hover:bg-coffee-700"
                data-test="record-purchase-button"
            >
                {processing && <Spinner />}
                Record Purchase
            </Button>
        </form>
    );
}
