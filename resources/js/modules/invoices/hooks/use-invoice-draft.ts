import { useState } from 'react';
import type { InvoiceProductOption } from '@/modules/products';
import type { DistributorOption, InvoiceLineDraft } from '../types';

let nextKey = 0;

function blankLine(): InvoiceLineDraft {
    nextKey += 1;

    return {
        key: `line-${nextKey}`,
        productId: null,
        cartonQuantity: '0',
        totalQuantity: '0',
        unitPrice: '0',
        discount: '0',
        remarks: '',
    };
}

/**
 * Read a form field as a whole number.
 *
 * Money and quantities are integers everywhere — see App\Support\Money — so a
 * `90.5` typed into a price is truncated here rather than travelling to the
 * server as a fraction. The server's `integer` validation rejects one that
 * arrives another way.
 */
export function toWholeAmount(value: string): number {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? Math.trunc(parsed) : 0;
}

export type InvoiceLineView = InvoiceLineDraft & {
    product: InvoiceProductOption | null;
    /** Quantity times unit price, before the line's discount. */
    amount: number;
    /** Set when the line asks for more than the last known stock figure. */
    stockWarning: string | null;
};

/** What an edit screen starts from; a create screen passes nothing. */
export type InvoiceDraftSeed = {
    distributorId: number;
    lines: Array<{
        productId: number | null;
        cartonQuantity: number;
        totalQuantity: number;
        unitPrice: number;
        discount: number;
        remarks: string | null;
    }>;
};

function seededLines(seed: InvoiceDraftSeed): InvoiceLineDraft[] {
    return seed.lines.map((line) => {
        nextKey += 1;

        return {
            key: `line-${nextKey}`,
            productId: line.productId,
            cartonQuantity: String(line.cartonQuantity),
            totalQuantity: String(line.totalQuantity),
            unitPrice: String(line.unitPrice),
            discount: String(line.discount),
            remarks: line.remarks ?? '',
        };
    });
}

/**
 * Holds an invoice form's editable state and everything derived from it —
 * shared by the create and edit screens.
 *
 * The totals here are for the person filling the form. The server recomputes
 * all of them from the product rows it locks at save time, so a price that
 * changed while this form sat open cannot reach the ledger.
 */
export function useInvoiceDraft(
    products: InvoiceProductOption[],
    distributors: DistributorOption[],
    seed?: InvoiceDraftSeed,
) {
    const [distributorId, setDistributorId] = useState<number | null>(
        seed?.distributorId ?? null,
    );
    const [lines, setLines] = useState<InvoiceLineDraft[]>(() =>
        seed && seed.lines.length > 0 ? seededLines(seed) : [blankLine()],
    );

    const productsById = new Map(
        products.map((product) => [product.id, product]),
    );

    /*
     * On an edit, the stock figures the server sent already exclude what this
     * invoice is holding — it has not been returned yet. Add each line's
     * original quantity back before warning, or raising a quantity by one
     * would look like it needed a whole fresh allocation.
     */
    const heldByThisInvoice = new Map<number, number>();

    for (const line of seed?.lines ?? []) {
        if (line.productId !== null) {
            heldByThisInvoice.set(
                line.productId,
                (heldByThisInvoice.get(line.productId) ?? 0) +
                    line.totalQuantity,
            );
        }
    }

    const distributor =
        distributors.find((candidate) => candidate.id === distributorId) ??
        null;

    const addLine = () => setLines((current) => [...current, blankLine()]);

    const removeLine = (key: string) =>
        setLines((current) =>
            current.length === 1
                ? [blankLine()]
                : current.filter((line) => line.key !== key),
        );

    const updateLine = (key: string, changes: Partial<InvoiceLineDraft>) =>
        setLines((current) =>
            current.map((line) => {
                if (line.key !== key) {
                    return line;
                }

                const updated = { ...line, ...changes };

                // Choosing a product seeds the price the company sells at, so
                // the common case is no typing at all.
                if (changes.productId !== undefined) {
                    const product = productsById.get(changes.productId ?? -1);
                    updated.unitPrice = product
                        ? String(product.distributorPrice)
                        : '0';
                    updated.cartonQuantity = '0';
                    updated.totalQuantity = '0';
                }

                // Cartons are the unit people order in; pieces are the unit
                // stock moves in. Typing cartons fills the pieces, and typing
                // pieces directly still wins.
                if (changes.cartonQuantity !== undefined) {
                    const product = productsById.get(updated.productId ?? -1);

                    if (product) {
                        updated.totalQuantity = String(
                            toWholeAmount(changes.cartonQuantity) *
                                product.cartonSize,
                        );
                    }
                }

                return updated;
            }),
        );

    const views: InvoiceLineView[] = lines.map((line) => {
        const product = productsById.get(line.productId ?? -1) ?? null;
        const quantity = toWholeAmount(line.totalQuantity);
        const requested = lines
            .filter((other) => other.productId === line.productId)
            .reduce(
                (sum, other) => sum + toWholeAmount(other.totalQuantity),
                0,
            );

        const available = product
            ? product.stockQuantity + (heldByThisInvoice.get(product.id) ?? 0)
            : 0;

        return {
            ...line,
            product,
            amount: quantity * toWholeAmount(line.unitPrice),
            stockWarning:
                product && requested > available
                    ? `Only ${available} in stock`
                    : null,
        };
    });

    const invoiceTotal = views.reduce((sum, line) => sum + line.amount, 0);
    const discountTotal = views.reduce(
        (sum, line) => sum + toWholeAmount(line.discount),
        0,
    );
    const previousDues = distributor?.balance ?? 0;

    return {
        distributor,
        distributorId,
        setDistributorId,
        lines: views,
        addLine,
        removeLine,
        updateLine,
        totals: {
            invoiceTotal,
            discountTotal,
            previousDues,
            netTotal: invoiceTotal - discountTotal,
        },
        hasStockWarning: views.some((line) => line.stockWarning !== null),
        /** Rows the user actually filled in, shaped for the request body. */
        payloadItems: views
            .filter(
                (line) =>
                    line.productId !== null &&
                    toWholeAmount(line.totalQuantity) > 0,
            )
            .map((line) => ({
                product_id: line.productId,
                carton_quantity: toWholeAmount(line.cartonQuantity),
                total_quantity: toWholeAmount(line.totalQuantity),
                unit_price: toWholeAmount(line.unitPrice),
                discount: toWholeAmount(line.discount),
                remarks: line.remarks || null,
            })),
    };
}
