import { useEffect } from 'react';
import { useCompanyBrand } from './use-company-brand';

/**
 * Keeps `<html>` painted in the current company's colour.
 *
 * `app.blade.php` sets this on the server so the first paint is already right;
 * this is what keeps it right afterwards, when switching company or saving a
 * new colour happens over an Inertia visit with no page load at all.
 *
 * Written to the document element rather than to a wrapper because dialogs,
 * dropdowns and toasts portal to `<body>` — a wrapper would leave them wearing
 * the house palette while the page behind them wore the company's.
 *
 * The ten steps are derived from this one value in CSS; see
 * `:root[data-company-theme]` in `app.css`.
 */
export function useCompanyTheme(): void {
    const { themeColor } = useCompanyBrand();

    useEffect(() => {
        const root = document.documentElement;

        if (themeColor) {
            root.style.setProperty('--brand-base', themeColor);
            root.setAttribute('data-company-theme', '');
        } else {
            root.style.removeProperty('--brand-base');
            root.removeAttribute('data-company-theme');
        }
    }, [themeColor]);
}
