import { cn } from '@utils';

export interface CardProps {
    children: React.ReactNode;
    className?: string;
    padding?: 'none' | 'sm' | 'md' | 'lg';
    hover?: boolean;
    bordered?: boolean;
}

const paddingClasses = {
    none: '',
    sm: 'p-4',
    md: 'p-6',
    lg: 'p-8',
};

export function Card({ children, className, padding = 'md', hover = false, bordered = true }: CardProps) {
    return (
        <div
            className={cn(
                'rounded-xl bg-white dark:bg-slate-800',
                bordered && 'border border-slate-200 dark:border-slate-700',
                paddingClasses[padding],
                hover && 'transition-shadow duration-200 hover:shadow-lg cursor-pointer',
                className
            )}
        >
            {children}
        </div>
    );
}

export interface CardHeaderProps {
    title: string;
    description?: string;
    action?: React.ReactNode;
    className?: string;
}

export function CardHeader({ title, description, action, className }: CardHeaderProps) {
    return (
        <div className={cn('flex items-start justify-between gap-4 mb-4', className)}>
            <div className="flex-1 min-w-0">
                <h3 className="text-lg font-semibold text-slate-900 dark:text-slate-100 truncate">
                    {title}
                </h3>
                {description && (
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {description}
                    </p>
                )}
            </div>
            {action && (
                <div className="flex-shrink-0">
                    {action}
                </div>
            )}
        </div>
    );
}

export interface CardContentProps {
    children: React.ReactNode;
    className?: string;
}

export function CardContent({ children, className }: CardContentProps) {
    return (
        <div className={cn('', className)}>
            {children}
        </div>
    );
}

export interface CardFooterProps {
    children: React.ReactNode;
    className?: string;
    divided?: boolean;
}

export function CardFooter({ children, className, divided = true }: CardFooterProps) {
    return (
        <div className={cn(
            'mt-4 flex items-center gap-3',
            divided && 'pt-4 border-t border-slate-200 dark:border-slate-700',
            className
        )}>
            {children}
        </div>
    );
}
