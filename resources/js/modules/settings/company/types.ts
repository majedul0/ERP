/** A colour as the settings form edits it: three channels, 0-255. */
export type ThemeRgb = {
    red: number;
    green: number;
    blue: number;
};

export type CompanySettings = {
    name: string;
    slug: string;
    logoUrl: string | null;
    address: string | null;
    phone: string | null;
    /** The chosen colour as `#rrggbb`, or null for the house palette. */
    themeColor: string | null;
    /** The same colour as three channels, prefilled with the house one. */
    themeRgb: ThemeRgb;
    usesDefaultTheme: boolean;
    defaultThemeColor: string;
    /** What the screens are actually painted in — see App\Support\BrandColor. */
    appliedThemeColor: string;
};
