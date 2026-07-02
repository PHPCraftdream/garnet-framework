import * as React from 'react';
import {useState} from 'react';
import * as Popover from '@radix-ui/react-popover';
import {cn} from '@common/Utils/cn';

interface ComboboxOption {
    value: string;
    label: string;
}

interface ComboboxProps {
    options: ComboboxOption[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    className?: string;
    testId?: string;
}

/**
 * Searchable dropdown (shadcn Combobox pattern).
 * Used for timezone selector, recipient search, etc.
 */
export function Combobox({options, value, onChange, placeholder = 'Select...', searchPlaceholder = 'Search...', emptyText = 'No results', className, testId}: ComboboxProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const selectedLabel = options.find(o => o.value === value)?.label || '';

    const filtered = search.trim()
        ? options.filter(o => o.label.toLowerCase().includes(search.toLowerCase()))
        : options;

    return (
        <Popover.Root open={open} onOpenChange={setOpen}>
            <Popover.Trigger asChild>
                <button
                    type="button"
                    role="combobox"
                    aria-expanded={open}
                    className={cn(
                        'flex h-9 w-full items-center justify-between rounded-md border px-3 py-2 text-sm',
                        'border-default bg-surface text-on-surface',
                        'hover:bg-surface-hover focus:outline-none focus:ring-2 focus:ring-accent',
                        !value && 'text-muted',
                        className,
                    )}
                    data-test-id={testId}
                >
                    <span className="truncate">{selectedLabel || placeholder}</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="ml-2 shrink-0 opacity-50">
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </button>
            </Popover.Trigger>

            <Popover.Portal>
                <Popover.Content
                    className={cn(
                        'z-50 w-[var(--radix-popover-trigger-width)] rounded-md border shadow-lg',
                        'border-default bg-surface',
                    )}
                    sideOffset={4}
                    align="start"
                >
                    {/* Search input */}
                    <div className="p-2 border-b border-default">
                        <input
                            type="text"
                            className="w-full px-2 py-1.5 text-sm rounded border-0 outline-none bg-surface-alt text-on-surface placeholder:text-muted"
                            placeholder={searchPlaceholder}
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            autoFocus
                            data-test-id={testId ? `${testId}-search` : undefined}
                        />
                    </div>

                    {/* Options list */}
                    <div className="max-h-60 overflow-y-auto p-1">
                        {filtered.length === 0 ? (
                            <div className="py-4 text-center text-sm text-muted">
                                {emptyText}
                            </div>
                        ) : (
                            filtered.map(option => (
                                <button
                                    key={option.value}
                                    type="button"
                                    className={cn(
                                        'flex w-full items-center rounded-sm px-2 py-1.5 text-sm cursor-pointer',
                                        'hover:bg-accent-subtle text-on-surface',
                                        option.value === value && 'bg-accent-subtle text-accent',
                                    )}
                                    onClick={() => {
                                        onChange(option.value);
                                        setSearch('');
                                        setOpen(false);
                                    }}
                                    data-test-id={testId ? `${testId}-option-${option.value}` : undefined}
                                >
                                    <span className="flex-1 text-left truncate">{option.label}</span>
                                    {option.value === value && (
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" className="shrink-0">
                                            <polyline points="20 6 9 17 4 12" />
                                        </svg>
                                    )}
                                </button>
                            ))
                        )}
                    </div>
                </Popover.Content>
            </Popover.Portal>
        </Popover.Root>
    );
}
