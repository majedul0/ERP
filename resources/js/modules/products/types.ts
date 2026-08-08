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
