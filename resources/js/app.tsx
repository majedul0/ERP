import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { CompanyLayout } from '@/modules/company';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            // The platform panel is not a company workspace and deliberately
            // carries none of the company chrome.
            case name === 'welcome':
            case name.startsWith('platform/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
            case name.startsWith('teams/'):
                return [CompanyLayout, SettingsLayout];
            case name === 'dashboard':
            case name.startsWith('company/'):
                return CompanyLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// Dark mode is deliberately off: the company surface is designed light-only,
// and the ocean palette has no dark variant. To turn it back on, import
// `initializeTheme` from '@/hooks/use-appearance' and call it here.
