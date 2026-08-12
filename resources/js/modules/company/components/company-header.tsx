import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, Settings } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { useCan } from '../hooks/use-can';
import { companyNavItems } from '../nav-items';
import type { CompanyNavItem } from '../nav-items';
import type { CompanyBrand } from '../types';
import CompanyLogo from './company-logo';

const triggerBase =
    'flex h-10 items-center gap-1 rounded-lg px-3.5 text-sm font-semibold whitespace-nowrap transition-colors';

function isActive(item: CompanyNavItem, currentPath: string): boolean {
    if (item.href) {
        return currentPath === new URL(item.href, 'http://localhost').pathname;
    }

    return false;
}

function TopLevelLink({
    item,
    active,
}: {
    item: CompanyNavItem;
    active: boolean;
}) {
    if (!item.href) {
        return (
            <span
                aria-disabled="true"
                title="Coming soon"
                className={cn(
                    triggerBase,
                    'cursor-not-allowed text-coffee-800/45',
                )}
            >
                {item.title}
            </span>
        );
    }

    return (
        <Link
            href={item.href}
            prefetch
            className={cn(
                triggerBase,
                active
                    ? 'bg-coffee-500 text-white shadow-sm'
                    : 'text-coffee-800 hover:bg-coffee-50',
            )}
        >
            {item.title}
        </Link>
    );
}

function TopLevelMenu({ item }: { item: CompanyNavItem }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    triggerBase,
                    'text-coffee-800 hover:bg-coffee-50 data-[state=open]:bg-coffee-50',
                )}
            >
                {item.title}
                <ChevronDown className="size-4 opacity-70" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-56">
                {item.items?.map((child) =>
                    child.href ? (
                        <DropdownMenuItem key={child.title} asChild>
                            <Link href={child.href} className="cursor-pointer">
                                {child.title}
                            </Link>
                        </DropdownMenuItem>
                    ) : (
                        <DropdownMenuItem
                            key={child.title}
                            disabled
                            className="justify-between"
                        >
                            {child.title}
                            <span className="rounded-full bg-coffee-50 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-coffee-600 uppercase">
                                Soon
                            </span>
                        </DropdownMenuItem>
                    ),
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function CompanyHeader({ brand }: { brand: CompanyBrand }) {
    const { currentTeam } = usePage().props;
    const { currentUrl } = useCurrentUrl();
    const cleanup = useMobileNavigation();
    const can = useCan();
    const items = companyNavItems(currentTeam?.slug ?? null, can);
    const homeHref = items[0]?.href;

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <header className="sticky top-0 z-40 border-b border-coffee-100 bg-white">
            <div className="mx-auto flex h-16 w-full max-w-[1600px] items-center gap-3 px-4 lg:px-6">
                {/* Mobile navigation */}
                <Sheet>
                    <SheetTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="text-coffee-800 lg:hidden"
                            aria-label="Open navigation"
                        >
                            <Menu className="size-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" className="w-72 bg-white p-0">
                        <SheetHeader className="border-b border-coffee-100 px-4 py-3">
                            <SheetTitle className="text-left">
                                <CompanyLogo brand={brand} />
                            </SheetTitle>
                        </SheetHeader>
                        <nav className="flex flex-col gap-1 overflow-y-auto p-3">
                            {items.map((item) => (
                                <div key={item.title}>
                                    {item.href ? (
                                        <Link
                                            href={item.href}
                                            onClick={cleanup}
                                            className={cn(
                                                'block rounded-lg px-3 py-2 text-sm font-semibold',
                                                isActive(item, currentUrl)
                                                    ? 'bg-coffee-500 text-white'
                                                    : 'text-coffee-800 hover:bg-coffee-50',
                                            )}
                                        >
                                            {item.title}
                                        </Link>
                                    ) : (
                                        <span className="block px-3 py-2 text-sm font-semibold text-coffee-800/45">
                                            {item.title}
                                        </span>
                                    )}
                                    {item.items?.map((child) => (
                                        <span
                                            key={child.title}
                                            className="block px-6 py-1.5 text-sm text-coffee-800/45"
                                        >
                                            {child.title}
                                        </span>
                                    ))}
                                </div>
                            ))}
                        </nav>
                    </SheetContent>
                </Sheet>

                {homeHref ? (
                    <Link href={homeHref} prefetch className="shrink-0">
                        <CompanyLogo brand={brand} />
                    </Link>
                ) : (
                    <CompanyLogo brand={brand} className="shrink-0" />
                )}

                <nav className="mx-auto hidden items-center gap-1 lg:flex">
                    {items.map((item) =>
                        item.items ? (
                            <TopLevelMenu key={item.title} item={item} />
                        ) : (
                            <TopLevelLink
                                key={item.title}
                                item={item}
                                active={isActive(item, currentUrl)}
                            />
                        ),
                    )}
                </nav>

                <div className="ml-auto flex shrink-0 items-center gap-1 lg:ml-0">
                    <Link
                        href={logout()}
                        as="button"
                        onClick={handleLogout}
                        data-test="logout-button"
                        className="rounded-lg px-3 py-2 text-sm font-semibold text-coffee-800 transition-colors hover:bg-coffee-50"
                    >
                        Logout
                    </Link>
                    <Link
                        href={edit()}
                        prefetch
                        aria-label="Settings"
                        className="flex size-9 items-center justify-center rounded-lg text-coffee-800 transition-colors hover:bg-coffee-50"
                    >
                        <Settings className="size-5" />
                    </Link>
                </div>
            </div>
        </header>
    );
}
