import { Form, Head, Link, usePage } from '@inertiajs/react';
import PaymentController from '@/actions/App/Http/Controllers/Payments/PaymentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { DeletePaymentDialog } from '@/modules/payments';
import type { BankOption, PaymentDetail } from '@/modules/payments';
import { index as banksIndex } from '@/routes/banks';
import { index as paymentsIndex } from '@/routes/payments';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

type Props = {
    payment: PaymentDetail;
    distributors: Array<{ id: number; name: string }>;
    banks: BankOption[];
};

export default function EditPayment({ payment, distributors, banks }: Props) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    return (
        <>
            <Head title="Update Payment" />

            <div className="mx-auto w-full max-w-xl">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-bold text-coffee-900">
                        Update Payment
                    </h1>
                    <div className="flex items-center gap-2">
                        <DeletePaymentDialog
                            teamSlug={teamSlug}
                            paymentId={payment.id}
                        />
                        <Button asChild variant="outline">
                            <Link href={paymentsIndex(teamSlug)}>Cancel</Link>
                        </Button>
                    </div>
                </div>

                <Form
                    {...PaymentController.update.form({
                        current_team: teamSlug,
                        payment: payment.id,
                    })}
                    options={{ preserveScroll: true }}
                    className="mt-8 space-y-5 pb-16"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="distributor_id"
                                    className="text-coffee-900"
                                >
                                    Distributor:
                                </Label>
                                <select
                                    id="distributor_id"
                                    name="distributor_id"
                                    className={selectClasses}
                                    defaultValue={payment.distributorId}
                                    required
                                >
                                    {distributors.map((distributor) => (
                                        <option
                                            key={distributor.id}
                                            value={distributor.id}
                                        >
                                            {distributor.name}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-xs text-coffee-800/60">
                                    Moving a payment replays both accounts — the
                                    one losing the money and the one gaining it.
                                </p>
                                <InputError message={errors.distributor_id} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="paid_on"
                                    className="text-coffee-900"
                                >
                                    Payment date:
                                </Label>
                                <Input
                                    id="paid_on"
                                    name="paid_on"
                                    type="date"
                                    required
                                    defaultValue={payment.paidOn}
                                />
                                <InputError message={errors.paid_on} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="bank_id"
                                    className="text-coffee-900"
                                >
                                    Bank:
                                </Label>
                                <select
                                    id="bank_id"
                                    name="bank_id"
                                    className={selectClasses}
                                    defaultValue={payment.bankId ?? ''}
                                >
                                    <option value="">---------</option>
                                    {banks.map((bank) => (
                                        <option key={bank.id} value={bank.id}>
                                            {bank.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.bank_id} />
                                {banks.length === 0 && (
                                    <p className="text-xs text-coffee-800/60">
                                        No banks yet —{' '}
                                        <Link
                                            href={banksIndex(teamSlug)}
                                            className="underline underline-offset-4"
                                        >
                                            add one
                                        </Link>{' '}
                                        to record where the money landed.
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="amount"
                                    className="text-coffee-900"
                                >
                                    Amount:
                                </Label>
                                <Input
                                    id="amount"
                                    name="amount"
                                    type="number"
                                    min={1}
                                    step={1}
                                    required
                                    defaultValue={payment.amount}
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label
                                    htmlFor="comment"
                                    className="text-coffee-900"
                                >
                                    Comment:
                                </Label>
                                <Input
                                    id="comment"
                                    name="comment"
                                    defaultValue={payment.comment ?? ''}
                                />
                                <InputError message={errors.comment} />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-coffee-600 hover:bg-coffee-700"
                                data-test="update-payment-button"
                            >
                                {processing && <Spinner />}
                                Save changes
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
