/**
 * Marks a material that has fallen to its reorder level.
 *
 * Rendered from the server's `isLow`, not recomputed here — the rule for what
 * counts as low (including "0 means never warn") lives on the model, so the
 * table and any future report cannot disagree about it.
 */
export function LowStockBadge() {
    return (
        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
            Low
        </span>
    );
}
