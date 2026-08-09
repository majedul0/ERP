import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import type {
    DistributorOption,
    InvoiceDetail,
    InvoicePayload,
} from '@/modules/invoices';
import { InvoiceForm } from '@/modules/invoices';
import type { InvoiceProductOption } from '@/modules/products';
import { show, update } from '@/routes/invoices';

type Props = {
    invoice: InvoiceDetail;
    distributors: DistributorOption[];
    products: InvoiceProductOption[];
};

export default function EditInvoice({
    invoice,
    distributors,
    products,
}: Props) {
    const { errors, currentTeam } = usePage().props;
    const [processing, setProcessing] = useState(false);
    const routeArgs = {
        current_team: currentTeam?.slug ?? '',
        invoice: invoice.id,
    };

    const submit = (payload: InvoicePayload) => {
        setProcessing(true);

        router.put(update(routeArgs).url, payload, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Head title={`Update ${invoice.invoiceNumber}`} />

            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold text-ocean-900">
                    Update {invoice.invoiceNumber}
                </h1>
                <Button asChild variant="outline">
                    <Link href={show(routeArgs)}>Cancel</Link>
                </Button>
            </div>

            <p className="mt-1 font-display text-sm text-ocean-800/60">
                The invoice number stays the same. Stock and the distributor's
                dues are recalculated when you save, and the challan follows
                automatically.
            </p>

            <InvoiceForm
                distributors={distributors}
                products={products}
                errors={errors}
                processing={processing}
                submitLabel="Save changes"
                seed={{
                    distributorId: invoice.distributor.id,
                    soldAt: invoice.soldAt.slice(0, 10),
                    comment: invoice.comment,
                    schemeDescription: invoice.schemeDescription,
                    schemeAmount: invoice.schemeAmount,
                    previousDues: invoice.previousDues,
                    lines: invoice.items.map((item) => ({
                        productId: item.productId,
                        cartonQuantity: item.cartonQuantity,
                        totalQuantity: item.totalQuantity,
                        unitPrice: item.unitPrice,
                        discount: item.discount,
                        remarks: item.remarks,
                    })),
                }}
                onSubmit={submit}
            />
        </>
    );
}
