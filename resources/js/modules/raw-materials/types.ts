/** A raw material as the server presents it. */
export type RawMaterial = {
    id: number;
    name: string;
    code: string;
    /** The enum value, e.g. `kg` — what the form submits. */
    unit: string;
    /** The short form to print beside a quantity, e.g. `kg`. */
    unitShort: string;
    stockQuantity: number;
    reorderLevel: number;
    unitCost: number;
    /** stockQuantity × unitCost, computed server-side. */
    stockValue: number;
    /** Whether stock has fallen to the reorder level. */
    isLow: boolean;
    note: string | null;
};

/** One selectable unit, from App\Enums\MaterialUnit. */
export type MaterialUnitOption = {
    value: string;
    label: string;
};

/**
 * A material on the Stock Levels screen.
 *
 * Spelled out rather than derived from RawMaterial: this screen answers "what
 * needs buying" and carries no `note`, so widening it later should be a
 * deliberate change on both sides.
 */
export type StockLevel = {
    id: number;
    name: string;
    code: string;
    unitShort: string;
    stockQuantity: number;
    reorderLevel: number;
    unitCost: number;
    stockValue: number;
    isLow: boolean;
    /** How much to buy to get back above the reorder level; 0 when not low. */
    shortfall: number;
};

/** The headline figures above the Stock Levels table. */
export type StockSummary = {
    materialCount: number;
    lowCount: number;
    outOfStockCount: number;
    totalValue: number;
};

/** The subset a purchase line needs to name and price a material. */
export type PurchaseMaterialOption = {
    id: number;
    name: string;
    code: string;
    unitShort: string;
    stockQuantity: number;
    unitCost: number;
};

/** A purchase as the list screen shows it. */
export type MaterialPurchaseSummary = {
    id: number;
    supplierName: string;
    reference: string | null;
    purchasedAt: string;
    totalAmount: number;
    itemCount: number;
};

/** One line on a recorded purchase. */
export type MaterialPurchaseItem = {
    id: number;
    materialName: string;
    materialCode: string;
    unit: string;
    quantity: number;
    unitCost: number;
    lineTotal: number;
};

/** A recorded purchase with its lines. */
export type MaterialPurchase = {
    id: number;
    supplierName: string;
    reference: string | null;
    purchasedAt: string;
    totalAmount: number;
    note: string | null;
    recordedBy: string;
    items: MaterialPurchaseItem[];
};
