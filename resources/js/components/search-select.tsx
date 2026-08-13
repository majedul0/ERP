import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

export type SearchOption = {
    value: number;
    label: string;
    /** Shown greyed beside the label — a code, a place, a stock figure. */
    hint?: string;
    /** Extra text to match on that is not worth showing, e.g. a proprietor. */
    keywords?: string;
};

const inputClasses =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none';

/**
 * Every word typed must appear somewhere in the option.
 *
 * Word-by-word rather than as one string, so "rm rajshahi" finds
 * "RM Enterprise — Rajshahi" without the person having to remember the order
 * the two halves were entered in.
 */
function matches(option: SearchOption, query: string): boolean {
    if (query.trim() === '') {
        return true;
    }

    const haystack =
        `${option.label} ${option.hint ?? ''} ${option.keywords ?? ''}`.toLowerCase();

    return query
        .toLowerCase()
        .split(/\s+/)
        .filter(Boolean)
        .every((word) => haystack.includes(word));
}

/**
 * A select you can type into.
 *
 * A plain `<select>` is fine for eight banks and unusable for four hundred
 * products: the browser's own type-ahead only matches from the first letter, so
 * finding "Bismillah Traders" means scrolling. This filters on any word in the
 * name, the code or the address.
 *
 * Deliberately not a library. Two fields needed it, and cmdk plus a popover is
 * a lot of dependency for a filtered list — this stays keyboard-navigable and
 * announces itself properly without any of that.
 */
export default function SearchSelect({
    options,
    value,
    onChange,
    placeholder = 'Search…',
    emptyText = 'Nothing found',
    className,
    id,
    'aria-label': ariaLabel,
}: {
    options: SearchOption[];
    value: number | null;
    onChange: (value: number | null) => void;
    placeholder?: string;
    emptyText?: string;
    className?: string;
    id?: string;
    'aria-label'?: string;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const [box, setBox] = useState({ top: 0, left: 0, width: 0, above: false });
    const inputRef = useRef<HTMLInputElement>(null);

    const selected = options.find((option) => option.value === value) ?? null;
    const visible = options.filter((option) => matches(option, query));

    /*
     * The list is drawn on `document.body`, not beside the input.
     *
     * Invoice lines sit inside a horizontally scrolling table, and an
     * absolutely positioned dropdown inside that container gets clipped by it
     * and pushes the table's own scrollbars around. A portal escapes the
     * overflow entirely; the cost is positioning it by hand, which is what this
     * measurement is for.
     */
    const measure = () => {
        const input = inputRef.current;

        if (!input) {
            return;
        }

        const rect = input.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;

        // Flip above when the list would run off the bottom, which it does for
        // the last line of a long invoice.
        const above = spaceBelow < 260 && rect.top > spaceBelow;

        setBox({
            top: above ? rect.top : rect.bottom + 4,
            left: rect.left,
            width: rect.width,
            above,
        });
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        // `true` for the capture phase, so scrolling any ancestor — including
        // that table — keeps the list attached to its input.
        window.addEventListener('scroll', measure, true);
        window.addEventListener('resize', measure);

        return () => {
            window.removeEventListener('scroll', measure, true);
            window.removeEventListener('resize', measure);
        };
    }, [open]);

    const close = () => {
        setOpen(false);
        setQuery('');
    };

    const choose = (option: SearchOption) => {
        onChange(option.value);
        close();
        inputRef.current?.blur();
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setOpen(true);

            setActive((current) => {
                const next =
                    event.key === 'ArrowDown' ? current + 1 : current - 1;

                // Wraps, so holding one arrow key cannot strand you at an end.
                return (next + visible.length) % Math.max(visible.length, 1);
            });

            return;
        }

        if (event.key === 'Enter' && open) {
            const option = visible[active];

            if (option) {
                event.preventDefault();
                choose(option);
            }

            return;
        }

        if (event.key === 'Escape') {
            close();
        }
    };

    const list = (
        <ul
            role="listbox"
            style={{
                position: 'fixed',
                top: box.above ? undefined : box.top,
                bottom: box.above
                    ? window.innerHeight - box.top + 4
                    : undefined,
                left: box.left,
                width: box.width,
            }}
            className="z-50 max-h-64 overflow-y-auto rounded-md border border-coffee-200 bg-white py-1 shadow-lg"
        >
            {visible.length === 0 && (
                <li className="px-3 py-2 text-sm text-coffee-800/60">
                    {emptyText}
                </li>
            )}

            {visible.map((option, index) => (
                <li key={option.value}>
                    <button
                        type="button"
                        role="option"
                        aria-selected={option.value === value}
                        tabIndex={-1}
                        /*
                         * The list lives on `document.body`, so a click here
                         * would blur the input and close it before the click
                         * lands. Suppressing mousedown keeps focus put.
                         */
                        onMouseDown={(event) => event.preventDefault()}
                        onMouseEnter={() => setActive(index)}
                        onClick={() => choose(option)}
                        className={cn(
                            'flex w-full items-baseline justify-between gap-3 px-3 py-2 text-left text-sm',
                            index === active
                                ? 'bg-coffee-600 text-white'
                                : 'text-coffee-900 hover:bg-coffee-50',
                        )}
                    >
                        <span>{option.label}</span>
                        {option.hint && (
                            <span
                                className={cn(
                                    'shrink-0 text-xs tabular-nums',
                                    index === active
                                        ? 'text-white/75'
                                        : 'text-coffee-800/60',
                                )}
                            >
                                {option.hint}
                            </span>
                        )}
                    </button>
                </li>
            ))}
        </ul>
    );

    return (
        <div className={className}>
            <input
                ref={inputRef}
                id={id}
                type="text"
                role="combobox"
                aria-expanded={open}
                aria-autocomplete="list"
                aria-label={ariaLabel}
                autoComplete="off"
                className={inputClasses}
                value={open ? query : (selected?.label ?? '')}
                placeholder={selected ? selected.label : placeholder}
                onFocus={() => {
                    measure();
                    setOpen(true);
                    setQuery('');
                    setActive(0);
                }}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                    setActive(0);
                }}
                onKeyDown={onKeyDown}
                onBlur={close}
            />

            {open && createPortal(list, document.body)}
        </div>
    );
}
