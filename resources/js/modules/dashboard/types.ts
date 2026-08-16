/**
 * Dashboard props.
 *
 * The invoicing domain tables do not exist yet, so `DashboardController`
 * currently ships placeholder values in these shapes. Once the Postgres tables
 * land, only the controller changes — these types are the contract.
 */

export type DashboardStats = {
    /** Headline balance shown above the stat row. */
    total: number;
    sales: number;
    distributorPayments: number;
    expenses: number;
    promotions: number;
};

export type DeliveryStatus = 'pending' | 'delivered' | 'cancelled' | 'returned';

export type TodaySale = {
    id: number;
    /** Human-facing, per-company sequential number, e.g. `INV2574`. */
    invoiceNumber: string;
    distributorName: string;
    /** Null until the distributor module exists. */
    distributorUrl: string | null;
    proprietorName: string;
    /** The calendar date the sale is booked under, `YYYY-MM-DD`. No time. */
    saleDate: string;
    /** ISO 8601 timestamp of when the invoice was written. */
    createdAt: string | null;
    /**
     * What the invoice prints as its Total Amount — the goods plus any dues it
     * carried, or the goods alone when it was printed without a dues line.
     */
    amount: number;
    /** The goods alone, whatever the invoice chose to print. */
    netAmount: number;
    deliveryStatus: DeliveryStatus;
    detailUrl: string | null;
};
