import { usePage } from '@inertiajs/react';

/**
 * Fair warning that the company's subscription is running out.
 *
 * Shown as the date approaches and after it passes. It **blocks nothing** —
 * losing access is a deliberate act by the platform owner, never something a
 * date does on its own, so this is only ever a message.
 *
 * Hidden entirely for a company with no plan: most will never have one, and a
 * permanent notice about a subscription nobody sold them is noise.
 */
export default function SubscriptionBanner() {
    const { subscription } = usePage().props;

    if (!subscription?.needsAttention) {
        return null;
    }

    const overdue = subscription.isOverdue;

    return (
        <div
            role="status"
            className={
                overdue
                    ? 'border-b border-red-200 bg-red-50 px-4 py-2 text-center text-sm text-red-900'
                    : 'border-b border-gold-200 bg-gold-100 px-4 py-2 text-center text-sm text-coffee-900'
            }
        >
            {overdue ? (
                <>
                    <strong>Your subscription has expired</strong>
                    {subscription.daysOverdue !== null && (
                        <> — {subscription.daysOverdue} day(s) ago</>
                    )}
                    . Please contact your provider to renew.
                </>
            ) : (
                <>
                    Your subscription ends in{' '}
                    <strong>{subscription.daysRemaining} day(s)</strong>
                    {subscription.paidThrough && (
                        <> (on {subscription.paidThrough})</>
                    )}
                    . Please contact your provider to renew.
                </>
            )}
        </div>
    );
}
