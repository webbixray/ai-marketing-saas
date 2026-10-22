import { forwardRef, ButtonHTMLAttributes } from 'react';
import { cn } from '@utils';
import { Loader2 } from 'lucide-react';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger' | 'success';
    size?: 'sm' | 'md' | 'lg' | 'xl';
    loading?: boolean;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
    fullWidth?: boolean;
}

const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    (
        {
            className,
            variant = 'primary',
            size = 'md',
            loading = false,
            leftIcon,
            rightIcon,
            fullWidth = false,
            disabled,
            children,
            ...props
        },
        ref
    ) => {
        const baseStyles = 'inline-flex items-center justify-center font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
        
        const variants = {
            primary: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 shadow-sm',
            secondary: 'bg-slate-100 text-slate-900 hover:bg-slate-200 focus:ring-slate-500 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600',
            outline: 'border-2 border-slate-300 text-slate-700 hover:bg-slate-50 focus:ring-slate-500 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800',
            ghost: 'text-slate-700 hover:bg-slate-100 focus:ring-slate-500 dark:text-slate-300 dark:hover:bg-slate-800',
            danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 shadow-sm',
            success: 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500 shadow-sm',
        };

        const sizes = {
            sm: 'px-3 py-1.5 text-sm gap-1.5',
            md: 'px-4 py-2 text-sm gap-2',
            lg: 'px-6 py-3 text-base gap-2',
            xl: 'px-8 py-4 text-lg gap-2.5',
        };

        return (
            <button
                ref={ref}
                className={cn(
                    baseStyles,
                    variants[variant],
                    sizes[size],
                    fullWidth && 'w-full',
                    className
                )}
                disabled={disabled || loading}
                {...props}
            >
                {loading ? (
                    <Loader2 className="w-4 h-4 animate-spin" />
                ) : (
                    leftIcon
                )}
                {children}
                {!loading && rightIcon}
            </button>
        );
    }
);

Button.displayName = 'Button';
export default Button;
