import { cn } from '@utils';

export interface BadgeProps {
    children: React.ReactNode;
    variant?: 'default' | 'success' | 'warning' | 'danger' | 'info' | 'outline';
    size?: 'sm' | 'md' | 'lg';
    dot?: boolean;
    dotColor?: string;
    className?: string;
    removable?: boolean;
    onRemove?: () => void;
}

const variantClasses = {
    default: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    success: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    warning: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    danger: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    info: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    outline: 'border border-slate-300 text-slate-700 dark:border-slate-600 dark:text-slate-300 bg-transparent',
};

const sizeClasses = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-xs',
    lg: 'px-3 py-1 text-sm',
};

export function Badge({
    children,
    variant = 'default',
    size = 'md',
    dot = false,
    dotColor,
    className,
    removable = false,
    onRemove,
}: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 font-medium rounded-full',
                variantClasses[variant],
                sizeClasses[size],
                removable && 'pr-1',
                className
            )}
        >
            {dot && (
                <span
                    className={cn(
                        'rounded-full',
                        size === 'sm' && 'w-1.5 h-1.5',
                        size === 'md' && 'w-2 h-2',
                        size === 'lg' && 'w-2.5 h-2.5',
                        dotColor || (variant === 'success' && 'bg-green-500'),
                        (variant === 'warning' && 'bg-amber-500'),
                        (variant === 'danger' && 'bg-red-500'),
                        (variant === 'info' && 'bg-blue-500'),
                        (variant === 'default' && 'bg-slate-500'),
                    )}
                />
            )}
            {children}
            {removable && (
                <button
                    type="button"
                    onClick={onRemove}
                    className={cn(
                        'ml-1 p-0.5 rounded-full hover:bg-black/10 dark:hover:bg-white/10',
                        'transition-colors focus:outline-none focus:ring-2 focus:ring-current',
                        size === 'sm' && '-ml-1',
                        size === 'md' && '-ml-0.5',
                        size === 'lg' && '-ml-0.5'
                    )}
                    aria-label="Remove"
                >
                    <svg className={cn('w-3 h-3', size === 'sm' && 'w-2.5 h-2.5')} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            )}
        </span>
    );
}

export interface StatusBadgeProps {
    status: string;
    className?: string;
}

const statusConfig: Record<string, { variant: BadgeProps['variant']; label: string }> = {
    active: { variant: 'success', label: 'Active' },
    inactive: { variant: 'default', label: 'Inactive' },
    pending: { variant: 'warning', label: 'Pending' },
    approved: { variant: 'success', label: 'Approved' },
    rejected: { variant: 'danger', label: 'Rejected' },
    draft: { variant: 'outline', label: 'Draft' },
    archived: { variant: 'default', label: 'Archived' },
    paused: { variant: 'warning', label: 'Paused' },
    completed: { variant: 'success', label: 'Completed' },
    failed: { variant: 'danger', label: 'Failed' },
    running: { variant: 'info', label: 'Running' },
    scheduled: { variant: 'info', label: 'Scheduled' },
    processing: { variant: 'info', label: 'Processing' },
    completed: { variant: 'success', label: 'Completed' },
};

export function StatusBadge({ status, className }: StatusBadgeProps) {
    const config = statusConfig[status] || { variant: 'default', label: status };
    return <Badge variant={config.variant}>{config.label}</Badge>;
}
