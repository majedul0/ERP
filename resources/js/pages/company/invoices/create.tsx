import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { DistributorOption, InvoicePayload } from '@/modules/invoices';
import { InvoiceForm } from '@/modules/invoices';
import type { InvoiceProductOption } from '@/modules/products';
import { store } from '@/routes/invoices';
import { version as stockVersionUrl } from '@/routes/stock';

type Props = {
    distributors: DistributorOption[];
    products: InvoiceProductOption[];
    nextInvoiceNumber: string;
    stockVersion: number;
};

export default function CreateInvoice({
    distributors,
    products,
    nextInvoiceNumber,
    stockVersion,
}: Props) {
    const { errors, currentTeam } = usePage().props;
    const [processing, setProcessing] = useState(false);

    const submit = (payload: InvoicePayload) => {
        setProcessing(true);

        router.post(store(currentTeam?.slug ?? '').url, payload, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Head title="Create Sales Invoice" />

            <h1 className="mt-2 text-center text-2xl font-bold text-coffee-900">
                Create Sales Invoice
            </h1>
            <p className="mt-1 text-center font-display text-sm text-coffee-800/60">
                Next number: {nextInvoiceNumber} — assigned when you press
                Create, so simultaneous invoices never share one.
            </p>

            <InvoiceForm
                distributors={distributors}
                products={products}
                errors={errors}
                processing={processing}
                submitLabel="Create"
                onSubmit={submit}
                stock={{
                    version: stockVersion,
                    versionUrl: stockVersionUrl(currentTeam?.slug ?? '').url,
                }}
            />
        </>
    );
}
