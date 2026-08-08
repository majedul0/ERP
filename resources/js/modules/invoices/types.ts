import type { DeliveryStatus } from '@/modules/dashboard';

export type DistributorOption = {
    id: number;
    name: string;
    proprietorName: string | null;
    phone: string | null;
    address: string | null;
    thana: string | null;
    district: string | null;
    division: string | null;
    fullAddress: string;
    /** Outstanding balance, carried onto a new invoice as Previous Dues. */
    balance: number;
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
    previousDues: number;
    totalAmount: number;
    createdBy: string | null;
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
