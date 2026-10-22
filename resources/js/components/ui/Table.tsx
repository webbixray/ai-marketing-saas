import { useState, useMemo } from 'react';
import { cn } from '@utils';
import { ChevronUp, ChevronDown, ChevronUpDown } from 'lucide-react';
import { TableColumn, TableProps } from '@types';

export function Table<T>({
    columns,
    data,
    keyExtractor,
    sortable = true,
    filterable = false,
    pagination = false,
    pageSize = 10,
    onRowClick,
    selection = false,
    selectedRows = [],
    onSelectionChange,
    loading = false,
    emptyMessage = 'No data available',
    className,
}: TableProps<T>) {
    const [sortConfig, setSortConfig] = useState<{ key: string; direction: 'asc' | 'desc' } | null>(null);
    const [filters, setFilters] = useState<Record<string, string>>({});
    const [currentPage, setCurrentPage] = useState(1);
    const [selectAll, setSelectAll] = useState(false);

    // Handle select all
    useEffect(() => {
        if (pagination) {
            const pageData = paginatedData;
            setSelectAll(pageData.length > 0 && pageData.every(row => selectedRows.includes(keyExtractor(row))));
        } else {
            setSelectAll(data.length > 0 && data.every(row => selectedRows.includes(keyExtractor(row))));
        }
    }, [data, selectedRows, currentPage, pagination]);

    const handleSelectAll = () => {
        if (selectAll) {
            onSelectionChange?.([]);
        } else {
            const rowsToSelect = pagination ? paginatedData : data;
            onSelectionChange?.(rowsToSelect.map(keyExtractor));
        }
    };

    const handleRowSelect = (row: T) => {
        const key = keyExtractor(row);
        const newSelected = selectedRows.includes(key)
            ? selectedRows.filter(k => k !== key)
            : [...selectedRows, key];
        onSelectionChange?.(newSelected);
    };

    // Filter data
    const filteredData = useMemo(() => {
        return data.filter(row => {
            return Object.entries(filters).every(([key, value]) => {
                if (!value) return true;
                const column = columns.find(c => c.key === key);
                if (!column || !column.filterable) return true;
                const cellValue = String(column.accessor ? column.accessor(row) : (row as Record<string, unknown>)[key] ?? '');
                return cellValue.toLowerCase().includes(value.toLowerCase());
            });
        });
    }, [data, filters, columns]);

    // Sort data
    const sortedData = useMemo(() => {
        if (!sortConfig || !sortable) return filteredData;
        return [...filteredData].sort((a, b) => {
            const column = columns.find(c => c.key === sortConfig.key);
            if (!column) return 0;
            
            const aVal = column.accessor ? column.accessor(a) : (a as Record<string, unknown>)[sortConfig.key];
            const bVal = column.accessor ? column.accessor(b) : (b as Record<string, unknown>)[sortConfig.key];
            
            if (aVal === null || aVal === undefined) return 1;
            if (bVal === null || bVal === undefined) return -1;
            
            const aStr = String(aVal).toLowerCase();
            const bStr = String(bVal).toLowerCase();
            
            if (aStr < bStr) return sortConfig.direction === 'asc' ? -1 : 1;
            if (aStr > bStr) return sortConfig.direction === 'asc' ? 1 : -1;
            return 0;
        });
    }, [filteredData, sortConfig, sortable, columns]);

    // Pagination
    const totalPages = Math.ceil(sortedData.length / pageSize);
    const paginatedData = pagination
        ? sortedData.slice((currentPage - 1) * pageSize, currentPage * pageSize)
        : sortedData;

    const handleSort = (key: string) => {
        const column = columns.find(c => c.key === key);
        if (!column?.sortable) return;
        
        setSortConfig(prev => ({
            key,
            direction: prev?.key === key && prev.direction === 'asc' ? 'desc' : 'asc',
        }));
    };

    const handleFilterChange = (key: string, value: string) => {
        setFilters(prev => ({ ...prev, [key]: value }));
        if (pagination) setCurrentPage(1);
    };

    const isRowSelected = (row: T) => selectedRows.includes(keyExtractor(row));

    if (loading) {
        return (
            <div className="overflow-x-auto">
                <table className="w-full" role="grid">
                    <thead>
                        <tr className="border-b border-slate-200 dark:border-slate-700">
                            {columns.map(column => (
                                <th key={column.key} className="px-4 py-3 text-left">
                                    <div className="animate-pulse h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4" />
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {Array.from({ length: pagination ? pageSize : 5 }).map((_, i) => (
                            <tr key={i} className="border-b border-slate-100 dark:border-slate-800">
                                {columns.map(() => (
                                    <td key={Math.random()} className="px-4 py-3">
                                        <div className="animate-pulse h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/2" />
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }

    return (
        <div className={cn('overflow-x-auto', className)}>
            <table className="w-full" role="grid">
                <thead>
                    <tr className="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                        {selection && (
                            <th className="px-4 py-3 w-12">
                                <input
                                    type="checkbox"
                                    checked={selectAll}
                                    onChange={handleSelectAll}
                                    className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                    aria-label="Select all rows"
                                />
                            </th>
                        )}
                        {columns.map(column => (
                            <th
                                key={column.key}
                                className={cn(
                                    'px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider',
                                    column.align === 'center' && 'text-center',
                                    column.align === 'right' && 'text-right',
                                    column.sortable && sortable && 'cursor-pointer select-none hover:bg-slate-100 dark:hover:bg-slate-700',
                                    column.filterable && filterable && 'pr-8'
                                )}
                                style={{ width: column.width }}
                                onClick={() => column.sortable && sortable && handleSort(column.key)}
                            >
                                <div className="flex items-center gap-2">
                                    <span>{column.header}</span>
                                    {column.sortable && sortable && sortConfig?.key === column.key && (
                                        sortConfig.direction === 'asc' ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />
                                    )}
                                    {column.sortable && sortable && !sortConfig?.key === column.key && (
                                        <ChevronUpDown className="w-4 h-4 text-slate-300" />
                                    )}
                                </div>
                                {column.filterable && filterable && (
                                    <input
                                        type="text"
                                        placeholder="Filter"
                                        value={filters[column.key] || ''}
                                        onChange={e => handleFilterChange(column.key, e.target.value)}
                                        onClick={e => e.stopPropagation()}
                                        className="mt-1.5 px-2 py-1 text-xs border border-slate-200 dark:border-slate-600 rounded bg-white dark:bg-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500 w-full max-w-xs"
                                    />
                                )}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {paginatedData.length === 0 ? (
                        <tr>
                            <td colSpan={columns.length + (selection ? 1 : 0)} className="px-4 py-12 text-center">
                                <p className="text-slate-500 dark:text-slate-400">{emptyMessage}</p>
                            </td>
                        </tr>
                    ) : (
                        paginatedData.map(row => (
                            <tr
                                key={keyExtractor(row)}
                                className={cn(
                                    'transition-colors',
                                    onRowClick && 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50',
                                    isRowSelected(row) && 'bg-blue-50 dark:bg-blue-900/20'
                                )}
                                onClick={() => onRowClick?.(row)}
                            >
                                {selection && (
                                    <td className="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={isRowSelected(row)}
                                            onChange={e => { e.stopPropagation(); handleRowSelect(row); }}
                                            className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                            aria-label={`Select row ${keyExtractor(row)}`}
                                        />
                                    </td>
                                )}
                                {columns.map(column => (
                                    <td
                                        key={column.key}
                                        className={cn(
                                            'px-4 py-3 text-sm text-slate-900 dark:text-slate-100',
                                            column.align === 'center' && 'text-center',
                                            column.align === 'right' && 'text-right'
                                        )}
                                    >
                                        {column.cell ? column.cell(row) : column.accessor ? String(column.accessor(row)) : String((row as Record<string, unknown>)[column.key] ?? '')}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>

            {pagination && totalPages > 1 && (
                <div className="flex items-center justify-between px-4 py-4 border-t border-slate-200 dark:border-slate-700">
                    <div className="text-sm text-slate-600 dark:text-slate-400">
                        Showing {((currentPage - 1) * pageSize) + 1} to {Math.min(currentPage * pageSize, sortedData.length)} of {sortedData.length} results
                    </div>
                    <div className="flex gap-2">
                        <button
                            onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                            disabled={currentPage === 1}
                            className={cn(
                                'px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-600',
                                'hover:bg-slate-50 dark:hover:bg-slate-700',
                                'disabled:opacity-50 disabled:cursor-not-allowed',
                                'focus:outline-none focus:ring-2 focus:ring-blue-500'
                            )}
                        >
                            Previous
                        </button>
                        <span className="px-3 py-1.5 text-sm text-slate-600 dark:text-slate-400">
                            Page {currentPage} of {totalPages}
                        </span>
                        <button
                            onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                            disabled={currentPage === totalPages}
                            className={cn(
                                'px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-600',
                                'hover:bg-slate-50 dark:hover:bg-slate-700',
                                'disabled:opacity-50 disabled:cursor-not-allowed',
                                'focus:outline-none focus:ring-2 focus:ring-blue-500'
                            )}
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
