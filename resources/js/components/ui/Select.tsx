import { forwardRef, SelectHTMLAttributes, useId, useRef, useState, useEffect } from 'react';
import { cn } from '@utils';
import { ChevronDown, Search, X } from 'lucide-react';

export interface SelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
    group?: string;
}

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    helperText?: string;
    error?: string;
    options: SelectOption[];
    placeholder?: string;
    searchable?: boolean;
    multiple?: boolean;
    clearable?: boolean;
    loading?: boolean;
}

const Select = forwardRef<HTMLSelectElement, SelectProps>(
    (
        {
            className,
            label,
            helperText,
            error,
            options,
            placeholder,
            searchable = false,
            multiple = false,
            clearable = false,
            loading = false,
            disabled,
            onChange,
            value,
            ...props
        },
        ref
    ) => {
        const selectId = useId();
        const [isOpen, setIsOpen] = useState(false);
        const [searchQuery, setSearchQuery] = useState('');
        const dropdownRef = useRef<HTMLDivElement>(null);
        const triggerRef = useRef<HTMLButtonElement>(null);

        const filteredOptions = options.filter(option => {
            if (option.disabled) return true;
            if (!searchQuery) return true;
            return option.label.toLowerCase().includes(searchQuery.toLowerCase());
        });

        const groupedOptions = filteredOptions.reduce((acc, option) => {
            const group = option.group || 'ungrouped';
            if (!acc[group]) acc[group] = [];
            acc[group].push(option);
            return acc;
        }, {} as Record<string, SelectOption[]>);

        const handleKeyDown = (e: React.KeyboardEvent) => {
            if (!isOpen) {
                if (['Enter', ' ', 'ArrowDown'].includes(e.key)) {
                    e.preventDefault();
                    setIsOpen(true);
                }
                return;
            }

            switch (e.key) {
                case 'Escape':
                    e.preventDefault();
                    setIsOpen(false);
                    triggerRef.current?.focus();
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    // Focus next option
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    // Focus previous option
                    break;
                case 'Enter':
                    e.preventDefault();
                    setIsOpen(false);
                    break;
            }
        };

        const handleClickOutside = (e: MouseEvent) => {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node) &&
                triggerRef.current && !triggerRef.current.contains(e.target as Node)) {
                setIsOpen(false);
            }
        };

        useEffect(() => {
            document.addEventListener('mousedown', handleClickOutside);
            return () => document.removeEventListener('mousedown', handleClickOutside);
        }, []);

        const selectedValues = Array.isArray(value) ? value : value ? [value] : [];
        const selectedOptions = options.filter(opt => selectedValues.includes(opt.value));

        const handleSelect = (option: SelectOption) => {
            if (option.disabled) return;
            
            if (multiple) {
                const newValues = selectedValues.includes(option.value)
                    ? selectedValues.filter(v => v !== option.value)
                    : [...selectedValues, option.value];
                onChange?.({ target: { value: newValues } } as React.ChangeEvent<HTMLSelectElement>);
            } else {
                onChange?.({ target: { value: option.value } } as React.ChangeEvent<HTMLSelectElement>);
                setIsOpen(false);
            }
        };

        const handleClear = (e: React.MouseEvent) => {
            e.stopPropagation();
            onChange?.({ target: { value: multiple ? [] : '' } } as React.ChangeEvent<HTMLSelectElement>);
            setIsOpen(false);
        };

        if (searchable) {
            // Searchable select with dropdown
            return (
                <div className="w-full">
                    {label && (
                        <label htmlFor={selectId} className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            {label}
                        </label>
                    )}
                    <div className="relative">
                        <button
                            ref={triggerRef}
                            type="button"
                            onClick={() => !disabled && setIsOpen(!isOpen)}
                            onKeyDown={handleKeyDown}
                            className={cn(
                                'w-full text-left px-4 py-2.5 rounded-lg border transition-colors duration-200 flex items-center justify-between',
                                'bg-white dark:bg-slate-800',
                                'text-slate-900 dark:text-slate-100',
                                disabled
                                    ? 'opacity-50 cursor-not-allowed bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700'
                                    : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500',
                                error && 'border-red-500 focus:ring-red-500',
                                'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500'
                            )}
                            disabled={disabled}
                            aria-haspopup="listbox"
                            aria-expanded={isOpen}
                            aria-controls={`${selectId}-options`}
                        >
                            <span className="flex-1 flex items-center gap-2 min-w-0">
                                {loading ? (
                                    <svg className="animate-spin h-4 w-4 text-slate-400" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                    </svg>
                                ) : (
                                    <>
                                        {multiple ? (
                                            selectedOptions.length > 0 ? (
                                                <span className="flex flex-wrap gap-1.5">
                                                    {selectedOptions.map(opt => (
                                                        <span key={opt.value} className="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded-full">
                                                            {opt.label}
                                                            <button type="button" onClick={(e) => { e.stopPropagation(); handleSelect(opt); }} className="hover:text-blue-600 dark:hover:text-blue-400">
                                                                <X className="w-3 h-3" />
                                                            </button>
                                                        </span>
                                                    ))}
                                                </span>
                                            ) : (
                                                <span className="text-slate-400">{placeholder || 'Select options...'}</span>
                                            )
                                        ) : (
                                            selectedOptions[0] ? (
                                                <span>{selectedOptions[0].label}</span>
                                            ) : (
                                                <span className="text-slate-400">{placeholder || 'Select an option...'}</span>
                                            )
                                        )}
                                    </>
                                )}
                            </span>
                            <ChevronDown className={cn('w-4 h-4 text-slate-400 transition-transform', isOpen && 'rotate-180')} />
                        </button>

                        {isOpen && (
                            <div
                                ref={dropdownRef}
                                id={`${selectId}-options`}
                                className="absolute z-50 mt-1 w-full max-h-96 overflow-auto rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-lg"
                                role="listbox"
                            >
                                {searchable && (
                                    <div className="p-2 border-b border-slate-200 dark:border-slate-700 sticky top-0 bg-white dark:bg-slate-800">
                                        <div className="relative">
                                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                                            <input
                                                type="text"
                                                value={searchQuery}
                                                onChange={e => setSearchQuery(e.target.value)}
                                                placeholder="Search options..."
                                                className="w-full pl-10 pr-4 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                autoFocus
                                            />
                                        </div>
                                    </div>
                                )}
                                {Object.entries(groupedOptions).map(([group, groupOptions]) => (
                                    <div key={group} className="py-1">
                                        {group !== 'ungrouped' && (
                                            <div className="px-3 py-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                                                {group}
                                            </div>
                                        )}
                                        {groupOptions.map(option => (
                                            <button
                                                key={option.value}
                                                type="button"
                                                onClick={() => handleSelect(option)}
                                                disabled={option.disabled}
                                                className={cn(
                                                    'w-full px-3 py-2 text-sm flex items-center gap-2',
                                                    'hover:bg-slate-100 dark:hover:bg-slate-700',
                                                    selectedValues.includes(option.value) && 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
                                                    option.disabled && 'opacity-50 cursor-not-allowed'
                                                )}
                                                role="option"
                                                aria-selected={selectedValues.includes(option.value)}
                                            >
                                                <span className="flex-1 truncate">{option.label}</span>
                                                {selectedValues.includes(option.value) && (
                                                    <svg className="w-4 h-4 flex-shrink-0 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                ))}
                                {filteredOptions.length === 0 && (
                                    <div className="px-3 py-4 text-center text-slate-500 dark:text-slate-400 text-sm">
                                        No options found
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                    {error && (
                        <p className="mt-1.5 text-sm text-red-600 dark:text-red-400" role="alert">{error}</p>
                    )}
                    {helperText && !error && (
                        <p className="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{helperText}</p>
                    )}
                </div>
            );
        }

        // Native select fallback
        return (
            <div className="w-full">
                {label && (
                    <label htmlFor={selectId} className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                        {label}
                    </label>
                )}
                <select
                    ref={ref}
                    id={selectId}
                    className={cn(
                        'w-full px-4 py-2.5 rounded-lg border transition-colors duration-200 appearance-none',
                        'bg-white dark:bg-slate-800',
                        'text-slate-900 dark:text-slate-100',
                        'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                        disabled
                            ? 'opacity-50 cursor-not-allowed bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700'
                            : error
                                ? 'border-red-500'
                                : 'border-slate-300 dark:border-slate-600',
                        className
                    )}
                    disabled={disabled}
                    value={value ?? ''}
                    onChange={onChange}
                    {...props}
                >
                    {placeholder && (
                        <option value="" disabled>
                            {placeholder}
                        </option>
                    )}
                    {options.map(option => (
                        <option key={option.value} value={option.value} disabled={option.disabled}>
                            {option.label}
                        </option>
                    ))}
                </select>
                {error && (
                    <p className="mt-1.5 text-sm text-red-600 dark:text-red-400" role="alert">{error}</p>
                )}
                {helperText && !error && (
                    <p className="mt-1.5 text-sm text-slate-500 dark:text-slate-400">{helperText}</p>
                )}
            </div>
        );
    }
);

Select.displayName = 'Select';
export default Select;
