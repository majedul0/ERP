import { formatSaleDateTime } from '@/lib/format';
import { CompanyLogo } from '@/modules/company';
import type { CompanyBrand } from '@/modules/company';
import type { DistributorContact } from '../types';

type Props = {
    brand: CompanyBrand;
    /** `Invoice` or `Challan` — the same masthead serves both. */
    title: string;
    invoiceNumber: string;
    soldAt: string;
    distributor: DistributorContact;
    /** Extra lines beside the sale date, e.g. status or who issued it. */
    meta?: Array<{ label: string; value: string }>;
};

/**
 * The masthead shared by the invoice and the challan: company identity, the
 * document's name and number, who it is for, and when it was sold.
 *
 * One component so the two documents cannot drift — a company that changes its
 * phone number changes it on both, and a layout fix lands on both.
 */
export default function InvoiceDocumentHeader({
    brand,
    title,
    invoiceNumber,
    soldAt,
    distributor,
    meta = [],
}: Props) {
    return (
        <>
            <header className="flex flex-wrap items-start justify-between gap-4">
                <CompanyLogo brand={brand} />

                <div className="text-right text-sm text-ocean-800/80">
                    {brand.address && <p>{brand.address}</p>}
                    {brand.phone && <p>{brand.phone}</p>}
                </div>
            </header>

            <div className="mt-8 text-center">
                <h2 className="text-3xl font-bold text-ocean-900">{title}</h2>
                <p className="mt-1 font-display font-semibold text-ocean-800">
                    #{invoiceNumber}
                </p>
            </div>

            <div className="mt-8 flex flex-wrap justify-between gap-6">
                <div>
                    <p className="text-sm font-bold text-ocean-900">
                        Distributor
                    </p>
                    <p className="mt-1 text-lg font-semibold text-ocean-800 underline underline-offset-4">
                        {distributor.name}
                    </p>
                    {distributor.fullAddress && (
                        <p className="text-sm text-ocean-800/80">
                            {distributor.fullAddress}
                        </p>
                    )}
                    {distributor.phone && (
                        <p className="text-sm text-ocean-800/80">
                            {distributor.phone}
                        </p>
                    )}
                </div>

                <div className="text-right text-sm text-ocean-800/80">
                    <p>
                        <span className="font-bold text-ocean-900">
                            Sale Date:
                        </span>{' '}
                        {formatSaleDateTime(soldAt)}
                    </p>
                    {meta.map((entry) => (
                        <p key={entry.label} className="mt-1">
                            <span className="font-bold text-ocean-900">
                                {entry.label}:
                            </span>{' '}
                            {entry.value}
                        </p>
                    ))}
                </div>
            </div>
        </>
    );
}
