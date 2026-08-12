/** A vendor as the server presents them. */
export type Vendor = {
    id: number;
    name: string;
    proprietorName: string | null;
    phone: string | null;
    address: string | null;
    thana: string | null;
    district: string | null;
    division: string | null;
    fullAddress: string;
    /** What the company still owes. Negative means they hold an advance. */
    balance: number;
};

/** The subset a bill or payment form needs to choose a vendor. */
export type VendorOption = {
    id: number;
    name: string;
    balance: number;
};

/** A bill the company has received. */
export type VendorBill = {
    id: number;
    vendorId: number;
    vendorName: string;
    vendorUrl: string;
    reference: string | null;
    description: string | null;
    /** `YYYY-MM-DD`. A bill is dated to a day, not a moment. */
    billedOn: string;
    amount: number;
};

/** A payment the company has made. */
export type VendorPayment = {
    id: number;
    vendorId: number;
    vendorName: string;
    vendorUrl: string;
    bankId: number | null;
    bankName: string | null;
    /** `YYYY-MM-DD`. */
    paidOn: string;
    amount: number;
    comment: string | null;
};
