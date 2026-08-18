import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/stock-movements';
import type { Product } from '../types';

const selectClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

type Direction = 'add' | 'reduce';

/**
 * The reasons offered in each direction.
 *
 * Production only appears when adding and damage only when reducing, because
 * the pair are what the stock report separates: goods made, and goods lost. An
 * adjustment is the honest answer when neither fits — the shelf disagreed with
 * the books — and is offered both ways.
 */
const reasons: Record<Direction, Array<{ value: string; label: string }>> = {
    add: [
        { value: 'production', label: 'Production' },
        { value: 'adjustment', label: 'Adjustment (recount)' },
    ],
    reduce: [
        { value: 'damage', label: 'Damaged / wastage' },
        { value: 'adjustment', label: 'Adjustment (recount)' },
    ],
};

function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/**
 * Put stock on the shelf, or take it off, without repricing the product.
 *
 * Deliberately not the stock field on the edit form. That one is an absolute
 * recount typed over whatever was there; this asks how much moved and on what
 * day, which is what the stock report needs to place a production run in the
 * month it actually happened rather than the month somebody typed it in.
 */
export function StockMovementModal({
    product,
    direction,
    children,
}: {
    product: Product;
    direction: Direction;
    children: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const { currentTeam } = usePage().props;
    const adding = direction === 'add';

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent>
                <Form
                    // Remounts the form each time the dialog opens, so a
                    // half-typed quantity from last time is never submitted.
                    key={String(open)}
                    {...store.form({
                        current_team: currentTeam?.slug ?? '',
                        product: product.id,
                    })}
                    options={{ preserveScroll: true }}
                    className="space-y-5"
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors: formErrors }) => {
                        const errors = formErrors as Record<
                            string,
                            string | undefined
                        >;

                        return (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        {adding
                                            ? `Add production for ${product.name}`
                                            : `Reduce stock for ${product.name}`}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {adding
                                            ? 'Goods made or received. Dated, so the stock report counts them in the right month.'
                                            : 'Goods written off. Sales and returns are recorded elsewhere and must not be entered here.'}{' '}
                                        In stock now:{' '}
                                        <strong>{product.stockQuantity}</strong>
                                        .
                                    </DialogDescription>
                                </DialogHeader>

                                <input
                                    type="hidden"
                                    name="direction"
                                    value={direction}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor={`date-${product.id}`}>
                                        Date
                                    </Label>
                                    <Input
                                        id={`date-${product.id}`}
                                        name="occurred_on"
                                        type="date"
                                        defaultValue={today()}
                                        required
                                    />
                                    <InputError message={errors.occurred_on} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`quantity-${product.id}`}>
                                        Quantity
                                    </Label>
                                    <Input
                                        id={`quantity-${product.id}`}
                                        name="quantity"
                                        type="number"
                                        min={1}
                                        step={1}
                                        required
                                        autoFocus
                                    />
                                    <InputError message={errors.quantity} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`reason-${product.id}`}>
                                        Reason
                                    </Label>
                                    <select
                                        id={`reason-${product.id}`}
                                        name="reason"
                                        className={selectClasses}
                                        defaultValue={
                                            reasons[direction][0].value
                                        }
                                    >
                                        {reasons[direction].map((reason) => (
                                            <option
                                                key={reason.value}
                                                value={reason.value}
                                            >
                                                {reason.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.reason} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`remarks-${product.id}`}>
                                        Remarks
                                    </Label>
                                    <Input
                                        id={`remarks-${product.id}`}
                                        name="remarks"
                                        placeholder="Optional"
                                    />
                                    <InputError message={errors.remarks} />
                                </div>

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-test={`stock-${direction}-submit`}
                                        className="bg-coffee-600 hover:bg-coffee-700"
                                    >
                                        {adding ? 'Add' : 'Reduce'}
                                    </Button>
                                </DialogFooter>
                            </>
                        );
                    }}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
