import type { DeliveryStatus } from '@/modules/dashboard';
import type { InvoicePayment } from '@/modules/payments';

/** Who a distributor is and where to find them. Carries no money. */
export type DistributorContact = {
    id: number;
    name: string;
    proprietorName: string | null;
    phone: string | null;
    address: string | null;
    thana: string | null;
    district: string | null;
    division: string | null;
    fullAddress: string;
};

export type DistributorOption = DistributorContact & {
    /** Outstanding balance, carried onto a new invoice as Previous Dues. */
    balance: number;
};

/**
 * The delivery note.
 *
 * Deliberately has no priced fields — the server does not send them, so the
 * challan cannot show money even by accident.
 */
export type ChallanDetail = {
    id: number;
    invoiceNumber: string;
    soldAt: string;
    deliveryStatus: DeliveryStatus;
    deliveryStatusLabel: string;
    comment: string | null;
    distributor: DistributorContact;
    items: ChallanItem[];
};

export type ChallanItem = {
    id: number;
    lineNumber: number;
    productName: string;
    productSku: string | null;
    cartonQuantity: number;
    totalQuantity: number;
    remarks: string | null;
};

export type InvoiceItem = {
    id: number;
    lineNumber: number;
    productId: number | null;
    productName: string;
    productSku: string | null;
    cartonQuantity: number;
    totalQuantity: number;
    unitPrice: number;
    amount: number;
    discount: number;
    remarks: string | null;
};

export type InvoiceDetail = {
    id: number;
    invoiceNumber: string;
    soldAt: string;
    deliveryStatus: DeliveryStatus;
    deliveryStatusLabel: string;
    comment: string | null;
    schemeDescription: string | null;
    schemeAmount: number;
    invoiceTotal: number;
    discountTotal: number;
    /** What this invoice prints as Previous Dues. */
    previousDues: number;
    /** What the account said before this sale, whatever the invoice prints. */
    accountPreviousDues: number;
    /** The typed print-only figure, or null when the invoice shows the account. */
    previousDuesOverride: number | null;
    /** Print this one as a clean bill, with no running account on it. */
    hidePreviousDues: boolean;
    /** What this invoice alone comes to, before any previous dues. */
    netAmount: number;
    totalAmount: number;
    createdBy: string | null;
    /** Money received while this invoice was the outstanding one. */
    payments: InvoicePayment[];
    distributor: DistributorOption;
    items: InvoiceItem[];
};

export type DeliveryStatusOption = {
    value: DeliveryStatus;
    label: string;
};

/** One editable row on the create screen, before it becomes an InvoiceItem. */
export type InvoiceLineDraft = {
    key: string;
    productId: number | null;
    cartonQuantity: string;
    totalQuantity: string;
    unitPrice: string;
    discount: string;
    remarks: string;
};
