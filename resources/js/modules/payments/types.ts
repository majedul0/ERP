export type BankOption = {
    id: number;
    name: string;
};

export type Bank = BankOption & {
    accountNumber: string | null;
    paymentsCount: number;
};

export type PaymentRow = {
    id: number;
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
    paidOn: string;
    bankName: string | null;
    amount: number;
    comment: string | null;
};

/**
 * One line of a distributor's running account. A debit is what they were
 * charged, a credit what they paid, and `balanceAfter` what they owed once the
 * line landed.
 */
export type StatementEntry = {
    type: 'invoice' | 'payment';
    id: number;
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
