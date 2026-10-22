import { forwardRef, InputHTMLAttributes, TextareaHTMLAttributes } from 'react';
import { cn } from '@utils';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    helperText?: string;
    error?: string;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
}

const Input = forwardRef<HTMLInputElement, InputProps>(
    ({ className, label, helperText, error, leftIcon, rightIcon, id, ...props }, ref) => {
        const inputId = id || props.name;

        return (
            <div className="w-full">
                {label && (
                    <label htmlFor={inputId} className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                        {label}
                    </label>
                )}
                <div className="relative">
                    {leftIcon && (
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            {leftIcon}
                        </div>
                    )}
                    <input
                        ref={ref}
                        id={inputId}
                        className={cn(
                            'block w-full rounded-lg border transition-colors duration-200',
                            'bg-white dark:bg-slate-800',
                            'text-slate-900 dark:text-slate-100',
                            'placeholder:text-slate-400',
                            'focus:outline-none focus:ring-2 focus:ring-offset-0',
                            leftIcon ? 'pl-10' : 'pl-4',
                            rightIcon ? 'pr-10' : 'pr-4',
                            'py-2.5',
                            error
                                ? 'border-red-500 focus:ring-red-500 focus:border-red-500'
                                : 'border-slate-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500',
                            props.disabled && 'opacity-50 cursor-not-allowed bg-slate-50 dark:bg-slate-900',
                            className
                        )}
                        aria-invalid={error ? 'true' : 'false'}
                        aria-describedby={error ? `${inputId}-error` : helperText ? `${inputId}-helper` : undefined}
                        {...props}
                    />
                    {rightIcon && (
                        <div className="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                            {rightIcon}
                        </div>
                    )}
                </div>
                {error && (
                    <p id={`${inputId}-error`} className="mt-1.5 text-sm text-red-600 dark:text-red-400" role="alert">
                        {error}
                    </p>
                )}
                {helperText && !error && (
                    <p id={`${inputId}-helper`} className="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                        {helperText}
                    </p>
                )}
            </div>
        );
    }
);

Input.displayName = 'Input';
export default Input;

export interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    label?: string;
    helperText?: string;
    error?: string;
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
    ({ className, label, helperText, error, id, ...props }, ref) => {
        const textareaId = id || props.name;

        return (
            <div className="w-full">
                {label && (
                    <label htmlFor={textareaId} className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                        {label}
                    </label>
                )}
                <textarea
                    ref={ref}
                    id={textareaId}
                    className={cn(
                        'block w-full rounded-lg border transition-colors duration-200 resize-y min-h-[100px]',
                        'bg-white dark:bg-slate-800',
                        'text-slate-900 dark:text-slate-100',
                        'placeholder:text-slate-400',
                        'focus:outline-none focus:ring-2 focus:ring-offset-0',
                        'p-4',
                        error
                            ? 'border-red-500 focus:ring-red-500 focus:border-red-500'
                            : 'border-slate-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500',
                        props.disabled && 'opacity-50 cursor-not-allowed bg-slate-50 dark:bg-slate-900',
                        className
                    )}
                    aria-invalid={error ? 'true' : 'false'}
                    aria-describedby={error ? `${textareaId}-error` : helperText ? `${textareaId}-helper` : undefined}
                    {...props}
                />
                {error && (
                    <p id={`${textareaId}-error`} className="mt-1.5 text-sm text-red-600 dark:text-red-400" role="alert">
                        {error}
                    </p>
                )}
                {helperText && !error && (
                    <p id={`${textareaId}-helper`} className="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                        {helperText}
                    </p>
                )}
            </div>
        );
    }
);

Textarea.displayName = 'Textarea';
