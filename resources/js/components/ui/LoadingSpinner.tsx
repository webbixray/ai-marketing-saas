import { cn } from '@utils';

export interface LoadingSpinnerProps {
    size?: 'sm' | 'md' | 'lg' | 'xl';
    className?: string;
    color?: 'blue' | 'white' | 'gray' | 'primary';
}

const sizeClasses = {
    sm: 'w-4 h-4 border-2',
    md: 'w-6 h-6 border-2',
    lg: 'w-8 h-8 border-3',
    xl: 'w-12 h-12 border-4',
};

const colorClasses = {
    blue: 'border-blue-600 border-t-transparent',
    white: 'border-white border-t-transparent',
    gray: 'border-slate-400 border-t-transparent',
    primary: 'border-blue-600 border-t-transparent',
};

export function LoadingSpinner({ size = 'md', className, color = 'primary' }: LoadingSpinnerProps) {
    return (
        <svg
            className={cn('animate-spin', sizeClasses[size], colorClasses[color], className)}
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
            />
            <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
        </svg>
    );
}

export function LoadingOverlay({ message = 'Loading...', className }: { message?: string; className?: string }) {
    return (
        <div className={cn('fixed inset-0 z-50 flex items-center justify-center bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm', className)}>
            <div className="flex flex-col items-center gap-4 p-6 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700">
                <LoadingSpinner size="lg" />
                <p className="text-slate-600 dark:text-slate-400">{message}</p>
            </div>
        </div>
    );
}

export function LoadingCard({ className }: { className?: string }) {
    return (
        <div className={cn('animate-pulse', className)}>
            <div className="h-6 bg-slate-200 dark:bg-slate-700 rounded w-3/4 mb-4" />
            <div className="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/2 mb-2" />
            <div className="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/3" />
        </div>
    );
}

export function LoadingTable({ rows = 5, columns = 4 }: { rows?: number; columns?: number }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full" role="grid">
                <thead>
                    <tr className="border-b border-slate-200 dark:border-slate-700">
                        {Array.from({ length: columns }).map((_, i) => (
                            <th key={i} className="px-4 py-3 text-left">
                                <div className="animate-pulse h-4 bg-slate-200 dark:bg-slate-700 rounded w-3/4" />
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {Array.from({ length: rows }).map((_, i) => (
                        <tr key={i} className="border-b border-slate-100 dark:border-slate-800">
                            {Array.from({ length: columns }).map(() => (
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

export function LoadingList({ items = 5 }: { items?: number }) {
    return (
        <div className="space-y-4">
            {Array.from({ length: items }).map((_, i) => (
                <div key={i} className="flex items-center gap-4 p-4 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                    <div className="animate-pulse w-12 h-12 bg-slate-200 dark:bg-slate-700 rounded-full" />
                    <div className="flex-1 space-y-2">
                        <div className="animate-pulse h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/4" />
                        <div className="animate-pulse h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2" />
                    </div>
                </div>
            ))}
        </div>
    );
}
