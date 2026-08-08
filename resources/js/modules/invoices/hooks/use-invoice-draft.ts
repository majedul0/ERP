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

function toNumber(value: string): number {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

export type InvoiceLineView = InvoiceLineDraft & {
    product: InvoiceProductOption | null;
    /** Quantity times unit price, before the line's discount. */
    amount: number;
    /** Set when the line asks for more than the last known stock figure. */
    stockWarning: string | null;
};

/**
 * Holds the create-invoice form's editable state and everything derived from
 * it.
 *
 * The totals here are for the person filling the form. The server recomputes
 * all of them from the product rows it locks at save time, so a price that
 * changed while this form sat open cannot reach the ledger.
 */
export function useInvoiceDraft(
    products: InvoiceProductOption[],
    distributors: DistributorOption[],
) {
    const [distributorId, setDistributorId] = useState<number | null>(null);
    const [lines, setLines] = useState<InvoiceLineDraft[]>(() => [blankLine()]);

    const productsById = new Map(
        products.map((product) => [product.id, product]),
    );
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
                            toNumber(changes.cartonQuantity) *
                                product.cartonSize,
                        );
                    }
                }

                return updated;
            }),
        );

    const views: InvoiceLineView[] = lines.map((line) => {
        const product = productsById.get(line.productId ?? -1) ?? null;
        const quantity = toNumber(line.totalQuantity);
        const requested = lines
            .filter((other) => other.productId === line.productId)
            .reduce((sum, other) => sum + toNumber(other.totalQuantity), 0);

        return {
            ...line,
            product,
            amount: quantity * toNumber(line.unitPrice),
            stockWarning:
                product && requested > product.stockQuantity
                    ? `Only ${product.stockQuantity} in stock`
                    : null,
        };
    });

    const invoiceTotal = views.reduce((sum, line) => sum + line.amount, 0);
    const discountTotal = views.reduce(
        (sum, line) => sum + toNumber(line.discount),
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
                    line.productId !== null && toNumber(line.totalQuantity) > 0,
            )
            .map((line) => ({
                product_id: line.productId,
                carton_quantity: Math.trunc(toNumber(line.cartonQuantity)),
                total_quantity: Math.trunc(toNumber(line.totalQuantity)),
                unit_price: toNumber(line.unitPrice),
                discount: toNumber(line.discount),
                remarks: line.remarks || null,
            })),
    };
}
