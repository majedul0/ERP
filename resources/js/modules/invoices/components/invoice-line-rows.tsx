import { X } from 'lucide-react';
import SearchSelect from '@/components/search-select';
import { Input } from '@/components/ui/input';
import { formatAmount } from '@/lib/format';
import type { InvoiceProductOption } from '@/modules/products';
import type { InvoiceLineView } from '../hooks/use-invoice-draft';
import type { InvoiceLineDraft } from '../types';

const headCell =
    'bg-coffee-500 px-3 py-2.5 text-left text-xs font-bold tracking-wide text-white uppercase';
const cell = 'px-3 py-3 align-top';

type Props = {
    lines: InvoiceLineView[];
    products: InvoiceProductOption[];
    onUpdate: (key: string, changes: Partial<InvoiceLineDraft>) => void;
    onRemove: (key: string) => void;
};

export default function InvoiceLineRows({
    lines,
    products,
    onUpdate,
    onRemove,
}: Props) {
    return (
        <div className="overflow-x-auto rounded-lg border border-coffee-100 bg-white">
            <table className="w-full min-w-[68rem] text-sm">
                <thead>
                    <tr>
                        <th className={headCell}>Remove</th>
                        <th className={headCell}>Product</th>
                        <th className={headCell}>CTN QTY</th>
                        <th className={headCell}>Total QTY</th>
                        <th className={headCell}>Unit Price</th>
                        <th className={headCell}>Amount</th>
                        <th className={headCell}>Discount</th>
                        <th className={headCell}>Remarks</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-coffee-100">
                    {lines.map((line) => (
                        <tr key={line.key}>
                            <td className={cell}>
                                <button
                                    type="button"
                                    onClick={() => onRemove(line.key)}
                                    aria-label="Remove line"
                                    className="flex size-9 items-center justify-center rounded-md border border-coffee-100 text-coffee-800/60 transition-colors hover:bg-red-50 hover:text-red-600"
                                >
                                    <X className="size-4" />
                                </button>
                            </td>

                            <td className={cell}>
                                {/*
                                    Searchable by name or SKU, with the stock
                                    on hand beside each — the two things
                                    somebody needs to pick the right line.
                                */}
                                <SearchSelect
                                    className="min-w-[16rem]"
                                    aria-label="Product"
                                    options={products.map((product) => ({
                                        value: product.id,
                                        label: product.name,
                                        hint: `${product.sku} · ${formatAmount(product.stockQuantity)} in stock`,
                                    }))}
                                    value={line.productId}
                                    onChange={(productId) =>
                                        onUpdate(line.key, { productId })
                                    }
                                    placeholder="Search products…"
                                    emptyText="No product matches that"
                                />
                            </td>

                            <td className={cell}>
                                <Input
                                    type="number"
                                    min={0}
                                    className="w-28"
                                    value={line.cartonQuantity}
                                    onChange={(event) =>
                                        onUpdate(line.key, {
                                            cartonQuantity: event.target.value,
                                        })
                                    }
                                />
                                <p className="mt-1 text-xs font-medium text-coffee-800/60">
                                    CTN SIZE: {line.product?.cartonSize ?? 0}
                                </p>
                            </td>

                            <td className={cell}>
                                <Input
                                    type="number"
                                    min={0}
                                    className="w-28"
                                    value={line.totalQuantity}
                                    onChange={(event) =>
                                        onUpdate(line.key, {
                                            totalQuantity: event.target.value,
                                        })
                                    }
                                />
                                <p
                                    className={
                                        line.stockWarning
                                            ? 'mt-1 text-xs font-semibold text-red-600'
                                            : 'mt-1 text-xs font-medium text-coffee-800/60'
                                    }
                                >
                                    {line.stockWarning ??
                                        `STOCK: ${line.product?.stockQuantity ?? 0}`}
                                </p>
                            </td>

                            <td className={cell}>
                                <Input
                                    type="number"
                                    min={0}
                                    step={1}
                                    className="w-32"
                                    value={line.unitPrice}
                                    onChange={(event) =>
                                        onUpdate(line.key, {
                                            unitPrice: event.target.value,
                                        })
                                    }
                                />
                            </td>

                            <td className={cell}>
                                <div className="flex h-9 w-32 items-center rounded-md bg-coffee-50 px-3 text-sm font-medium text-coffee-900 tabular-nums">
                                    {formatAmount(line.amount)}
                                </div>
                            </td>

                            <td className={cell}>
                                <Input
                                    type="number"
                                    min={0}
                                    step={1}
                                    className="w-28"
                                    value={line.discount}
                                    onChange={(event) =>
                                        onUpdate(line.key, {
                                            discount: event.target.value,
                                        })
                                    }
                                />
                            </td>

                            <td className={cell}>
                                <Input
                                    className="w-40"
                                    value={line.remarks}
                                    onChange={(event) =>
                                        onUpdate(line.key, {
                                            remarks: event.target.value,
                                        })
                                    }
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
