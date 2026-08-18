export type Product = {
    id: number;
    name: string;
    sku: string;
    cartonSize: number;
    distributorPrice: number;
    tradePrice: number;
    mrp: number;
    stockQuantity: number;
    photoUrl: string | null;
};

/** The subset the invoice form needs to price and check a line. */
export type InvoiceProductOption = {
    id: number;
    name: string;
    sku: string;
    cartonSize: number;
    distributorPrice: number;
    stockQuantity: number;
};

/** One product's month on the stock report. */
export type ProductStockRow = {
    id: number;
    name: string;
    sku: string;
    opening: number;
    productions: number;
    total: number;
    /** Net of returns — see App\Support\ProductStockReport. */
    sales: number;
    salesValue: number;
    freshReturns: number;
    damaged: number;
    closing: number;
    closingValue: number;
    /** Zero in a healthy company; the ledger's check against the shelf. */
    balance: number;
};

export type ProductStockReport = {
    period: {
        from: string;
        to: string;
        month: number;
        year: number;
        /** `August, 2026` */
        label: string;
    };
    rows: ProductStockRow[];
    totals: Omit<ProductStockRow, 'id' | 'name' | 'sku'>;
};
