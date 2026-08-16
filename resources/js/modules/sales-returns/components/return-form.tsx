import { router, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import SearchSelect from '@/components/search-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/format';
import { useCompanyBrand } from '@/modules/company';
import type { DistributorOption } from '@/modules/invoices';
import type {
    ReturnProductOption,
    SalesReturnDetail,
    SalesReturnPayload,
} from '../types';

const headCell =
    'bg-coffee-500 px-3 py-2 text-left text-xs font-bold tracking-wide text-white uppercase';
const bodyCell = 'px-3 py-2 align-top';

type Line = {
    key: string;
    productId: number | null;
    quantity: string;
    unitPrice: string;
    remarks: string;
};

function blankLine(): Line {
    return {
        key: crypto.randomUUID(),
        productId: null,
        quantity: '',
        unitPrice: '',
        remarks: '',
    };
}

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/** Whole units only, matching the server's `integer` validation. */
function toWhole(value: string): number {
    const parsed = Number.parseInt(value, 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

/**
 * Recording goods a distributor sent back.
 *
 * The totals shown here are for the person filling the form. The server
 * recomputes every one of them from the locked product rows, so a stale price
 * on a form left open cannot decide what a return is worth — the same rule
 * invoices follow.
 */
export default function ReturnForm({
    distributors,
    products,
    action,
    method,
    submitLabel,
    seed,
    testId,
}: {
    distributors: DistributorOption[];
    products: ReturnProductOption[];
    action: string;
    method: 'post' | 'put';
    submitLabel: string;
    seed?: SalesReturnDetail;
    testId: string;
}) {
    const brand = useCompanyBrand();
    const { errors } = usePage().props;
    const money = (amount: number) => formatMoney(amount, brand.currencySymbol);

    const [distributorId, setDistributorId] = useState<number | null>(
        seed?.distributorId ?? null,
    );
    const [returnedOn, setReturnedOn] = useState(seed?.returnedOn ?? today());
    const [comment, setComment] = useState(seed?.comment ?? '');
    const [restock, setRestock] = useState(seed?.restock ?? true);
    const [processing, setProcessing] = useState(false);
    const [lines, setLines] = useState<Line[]>(
        seed
            ? seed.items.map((item) => ({
                  key: crypto.randomUUID(),
                  productId: item.productId,
                  quantity: String(item.quantity),
                  unitPrice: String(item.unitPrice),
                  remarks: item.remarks ?? '',
              }))
            : [blankLine()],
    );

    const chosen =
        distributors.find((distributor) => distributor.id === distributorId) ??
        null;

    const setLine = (key: string, patch: Partial<Line>) =>
        setLines((current) =>
            current.map((line) =>
                line.key === key ? { ...line, ...patch } : line,
            ),
        );

    /** Picking a product fills in its price, unless one was typed already. */
    const chooseProduct = (key: string, productId: number | null) => {
        const product = products.find((item) => item.id === productId);

        setLines((current) =>
            current.map((line) =>
                line.key === key
                    ? {
                          ...line,
                          productId,
                          unitPrice:
                              line.unitPrice === '' && product
                                  ? String(product.distributorPrice)
                                  : line.unitPrice,
                      }
                    : line,
            ),
        );
    };

    const lineAmount = (line: Line) =>
        toWhole(line.quantity) * toWhole(line.unitPrice);

    const total = lines.reduce((sum, line) => sum + lineAmount(line), 0);

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);

        const payload: SalesReturnPayload = {
            distributor_id: distributorId,
            returned_on: returnedOn,
            comment: comment.trim() === '' ? null : comment.trim(),
            restock,
            items: lines
                .filter((line) => line.productId !== null)
                .map((line) => ({
                    product_id: line.productId as number,
                    quantity: toWhole(line.quantity),
                    unit_price: toWhole(line.unitPrice),
                    remarks:
                        line.remarks.trim() === '' ? null : line.remarks.trim(),
                })),
        };

        router[method](action, payload, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <form onSubmit={submit} className="mt-6 space-y-6 pb-16">
            <section className="grid gap-4 rounded-lg border border-coffee-100 bg-white p-5 shadow-sm sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="distributor_id" className="text-coffee-900">
                        Distributor<span aria-hidden="true">*</span>
                    </Label>
                    <SearchSelect
                        id="distributor_id"
                        aria-label="Distributor"
                        options={distributors.map((distributor) => ({
                            value: distributor.id,
                            label: distributor.name,
                            hint: distributor.district ?? undefined,
                            keywords: [
                                distributor.proprietorName,
                                distributor.phone,
                                distributor.fullAddress,
                            ]
                                .filter(Boolean)
                                .join(' '),
                        }))}
                        value={distributorId}
                        onChange={setDistributorId}
                        placeholder="Search distributors…"
                        emptyText="No distributor matches that"
                    />
                    {chosen && (
                        <p className="text-xs text-coffee-800/70">
                            {chosen.balance < 0
                                ? `In credit ${money(Math.abs(chosen.balance))}`
                                : `Currently due ${money(chosen.balance)}`}
                        </p>
                    )}
                    <InputError message={errors.distributor_id} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="returned_on" className="text-coffee-900">
                        Return date<span aria-hidden="true">*</span>
                    </Label>
                    <Input
                        id="returned_on"
                        type="date"
                        required
                        value={returnedOn}
                        onChange={(event) => setReturnedOn(event.target.value)}
                    />
                    <InputError message={errors.returned_on} />
                </div>

                <div className="grid gap-1.5 sm:col-span-2">
                    <Label htmlFor="comment" className="text-coffee-900">
                        Comment
                    </Label>
                    <Input
                        id="comment"
                        value={comment}
                        onChange={(event) => setComment(event.target.value)}
                        placeholder="Damage, expired, wrong delivery…"
                    />
                    <InputError message={errors.comment} />
                </div>

                <div className="flex items-start gap-2 sm:col-span-2">
                    <Checkbox
                        id="restock"
                        checked={restock}
                        onCheckedChange={(checked) =>
                            setRestock(checked === true)
                        }
                    />
                    <div className="grid gap-0.5">
                        <Label
                            htmlFor="restock"
                            className="text-sm text-coffee-900"
                        >
                            Put these goods back into stock
                        </Label>
                        {/* The credit is the same either way — this decides
                            only whether the goods become sellable again. */}
                        <p className="text-xs text-coffee-800/60">
                            Untick for damaged or expired goods. The distributor
                            is credited either way.
                        </p>
                    </div>
                </div>
            </section>

            <section className="overflow-hidden rounded-lg border border-coffee-100 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[46rem] text-sm">
                        <thead>
                            <tr>
                                <th className={`${headCell} w-[38%]`}>
                                    Product
                                </th>
                                <th className={headCell}>Quantity</th>
                                <th className={headCell}>Unit Price</th>
                                <th className={`${headCell} text-right`}>
                                    Amount
                                </th>
                                <th className={headCell}>Remarks</th>
                                <th className={headCell}>
                                    <span className="sr-only">Remove</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-coffee-100">
                            {lines.map((line) => (
                                <tr key={line.key}>
                                    <td className={bodyCell}>
                                        <SearchSelect
                                            aria-label="Product"
                                            options={products.map(
                                                (product) => ({
                                                    value: product.id,
                                                    label: product.name,
                                                    hint: `${product.stockQuantity} in stock`,
                                                    keywords:
                                                        product.sku ??
                                                        undefined,
                                                }),
                                            )}
                                            value={line.productId}
                                            onChange={(value) =>
                                                chooseProduct(line.key, value)
                                            }
                                            placeholder="Search products…"
                                            emptyText="No product matches that"
                                        />
                                    </td>
                                    <td className={bodyCell}>
                                        <Input
                                            type="number"
                                            min={1}
                                            step={1}
                                            aria-label="Quantity"
                                            value={line.quantity}
                                            onChange={(event) =>
                                                setLine(line.key, {
                                                    quantity:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </td>
                                    <td className={bodyCell}>
                                        <Input
                                            type="number"
                                            min={0}
                                            step={1}
                                            aria-label="Unit price"
                                            value={line.unitPrice}
                                            onChange={(event) =>
                                                setLine(line.key, {
                                                    unitPrice:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </td>
                                    <td
                                        className={`${bodyCell} pt-4 text-right font-medium text-coffee-900 tabular-nums`}
                                    >
                                        {money(lineAmount(line))}
                                    </td>
                                    <td className={bodyCell}>
                                        <Input
                                            aria-label="Remarks"
                                            value={line.remarks}
                                            onChange={(event) =>
                                                setLine(line.key, {
                                                    remarks: event.target.value,
                                                })
                                            }
                                        />
                                    </td>
                                    <td className={`${bodyCell} pt-3`}>
                                        <button
                                            type="button"
                                            aria-label="Remove line"
                                            className="text-coffee-800/50 hover:text-red-700"
                                            onClick={() =>
                                                setLines((current) =>
                                                    current.length === 1
                                                        ? [blankLine()]
                                                        : current.filter(
                                                              (item) =>
                                                                  item.key !==
                                                                  line.key,
                                                          ),
                                                )
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-coffee-100 px-3 py-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            setLines((current) => [...current, blankLine()])
                        }
                    >
                        <Plus className="size-4" />
                        Add line
                    </Button>

                    <p className="text-sm text-coffee-800/70">
                        Credit to the account{' '}
                        <span className="ml-2 text-lg font-bold text-coffee-900 tabular-nums">
                            {money(total)}
                        </span>
                    </p>
                </div>
            </section>

            <InputError message={errors.items} />

            <Button
                type="submit"
                disabled={processing || distributorId === null}
                className="w-full bg-coffee-600 hover:bg-coffee-700"
                data-test={testId}
            >
                {processing && <Spinner />}
                {submitLabel}
            </Button>
        </form>
    );
}
