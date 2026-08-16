import type { DistributorContact } from '@/modules/invoices';

/** A product as the return form needs it. */
export type ReturnProductOption = {
    id: number;
    name: string;
    sku: string | null;
    distributorPrice: number;
    stockQuantity: number;
};

export type SalesReturnItem = {
    id: number;
    productId: number | null;
    productName: string;
    productSku: string | null;
    quantity: number;
    unitPrice: number;
    amount: number;
    remarks: string | null;
};

/** A row on the returns list. */
export type SalesReturnRow = {
    id: number;
    returnNumber: string;
    /** `YYYY-MM-DD`. A return is booked on a day, not at a moment. */
    returnedOn: string;
    distributorName: string;
    proprietorName: string | null;
    distributorUrl: string;
    amount: number;
    restock: boolean;
    comment: string | null;
};

/** A return in full, for the detail, print and edit screens. */
export type SalesReturnDetail = SalesReturnRow & {
    distributorId: number;
    /** Address and phone, as the printed document needs them. */
    distributor: DistributorContact;
    /** Who recorded it — the name on the signature line. */
    createdBy: string | null;
    /** ISO 8601 — a real timestamp, unlike `returnedOn`. */
    recordedAt: string | null;
    items: SalesReturnItem[];
};

/** What the form posts. The server recomputes every amount from it. */
export type SalesReturnPayload = {
    distributor_id: number | null;
    returned_on: string;
    comment: string | null;
    restock: boolean;
    items: Array<{
        product_id: number;
        quantity: number;
        unit_price: number;
        remarks: string | null;
    }>;
};
