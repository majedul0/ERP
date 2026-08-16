export type BankOption = {
    id: number;
    name: string;
};

export type Bank = BankOption & {
    accountNumber: string | null;
    paymentsCount: number;
};

/** A payment as the edit form needs it. */
export type PaymentDetail = {
    id: number;
    distributorId: number;
    bankId: number | null;
    /** `YYYY-MM-DD`. */
    paidOn: string;
    amount: number;
    comment: string | null;
};

export type PaymentRow = {
    id: number;
    /** `YYYY-MM-DD`. A payment is booked on a day, not at a moment. */
    paidOn: string;
    distributorName: string;
    distributorUrl: string;
    bankName: string | null;
    amount: number;
    comment: string | null;
};

/** A payment shown beside an invoice's totals. */
export type InvoicePayment = {
    id: number;
    /** `YYYY-MM-DD`. A payment is booked on a day, not at a moment. */
    paidOn: string;
    bankName: string | null;
    amount: number;
    comment: string | null;
};

/**
 * One line of a distributor's running account. A debit is what they were
 * charged, a credit what settled it — money paid, or goods returned — and
 * `balanceAfter` what they owed once the line landed.
 */
export type StatementEntry = {
    type: 'invoice' | 'payment' | 'return';
    id: number;
    /** `YYYY-MM-DD`. Ledger lines are dated, not timed. */
    occurredOn: string;
    reference: string;
    description: string;
    debit: number;
    credit: number;
    balanceAfter: number;
};

export type StatementTotals = {
    charged: number;
    paid: number;
    due: number;
};
