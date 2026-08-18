/**
 * The colours the charts are drawn in.
 *
 * **Deliberately not the company's theme colour.** Everything else on the page
 * follows `--brand-base`, and these do not, for two reasons a chart cannot
 * negotiate: a series palette has to stay distinguishable under colour-vision
 * deficiency, and a company is free to pick a pale yellow that no amount of
 * derivation turns into six telling-apart-able hues. Identity in a chart is
 * carried by hue, so hue is the one thing the tenant does not get to set.
 *
 * These eight are the first slots of a validated categorical palette, in their
 * fixed order — assigned by position, never cycled, never generated. Checked
 * against this app's own white chart surface rather than a reference one:
 *
 *   lightness band PASS · chroma floor PASS
 *   worst adjacent CVD ΔE 9.1 (protan) · worst adjacent normal-vision ΔE 19.6
 *   contrast WARN for aqua, yellow and magenta (< 3:1 on white)
 *
 * The contrast warning is answered, not dismissed: every chart here ships a
 * legend with written labels, and the report tables below carry every figure
 * the marks encode. Nothing is reachable only by telling two colours apart.
 *
 * Adding a ninth series is not a matter of adding a ninth hex — under CVD it
 * would be indistinguishable from one already here. Fold the tail into "Other"
 * instead, which is what `FinancialAnalytics` does for expense categories.
 */
export const chartSeries = [
    '#2a78d6', // 1 blue
    '#eb6834', // 2 orange
    '#1baf7a', // 3 aqua
    '#eda100', // 4 yellow
    '#e87ba4', // 5 magenta
    '#008300', // 6 green
    '#4a3aa7', // 7 violet
    '#e34948', // 8 red
] as const;

/**
 * Chart chrome: everything that is not data.
 *
 * Recessive by construction — the grid is one hairline step off the surface,
 * and axis text is muted ink. Text never wears a series colour; identity comes
 * from the mark beside it.
 */
export const chartInk = {
    surface: '#ffffff',
    grid: '#e1e0d9',
    axis: '#c3c2b7',
    muted: '#898781',
    secondary: '#52514e',
    primary: '#0b0b0b',
} as const;

/** The slot a named series always occupies, whatever else is on screen. */
export const financeSeriesColor = {
    revenue: chartSeries[0],
    expenses: chartSeries[1],
    net: chartSeries[2],
} as const;
