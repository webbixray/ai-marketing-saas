import { Fragment, useState, useRef, useEffect } from 'react';
import { cn } from '@utils';

export interface TooltipProps {
    content: React.ReactNode;
    children: React.ReactElement;
    position?: 'top' | 'bottom' | 'left' | 'right';
    delay?: number;
    className?: string;
}

const positionClasses = {
    top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
    bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
    left: 'right-full top-1/2 -translate-y-1/2 mr-2',
    right: 'left-full top-1/2 -translate-y-1/2 ml-2',
};

const arrowClasses = {
    top: 'bottom-[-4px] left-1/2 -translate-x-1/2 border-t-transparent border-slate-900',
    bottom: 'top-[-4px] left-1/2 -translate-x-1/2 border-b-transparent border-slate-900',
    left: 'right-[-4px] top-1/2 -translate-y-1/2 border-l-transparent border-slate-900',
    right: 'left-[-4px] top-1/2 -translate-y-1/2 border-r-transparent border-slate-900',
};

export function Tooltip({ content, children, position = 'top', delay = 200, className }: TooltipProps) {
    const [isVisible, setIsVisible] = useState(false);
    const timeoutRef = useRef<NodeJS.Timeout>();
    const childRef = useRef<HTMLElement>(null);

    const showTooltip = () => {
        if (delay > 0) {
            timeoutRef.current = setTimeout(() => setIsVisible(true), delay);
        } else {
            setIsVisible(true);
        }
    };

    const hideTooltip = () => {
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }
        setIsVisible(false);
    };

    useEffect(() => {
        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, []);

    const childWithProps = typeof children === 'object' && children !== null
        ? children
        : null;

    if (!childWithProps) {
        return <>{children}</>;
    }

    return (
        <Fragment>
            {React.cloneElement(childWithProps, {
                ref: childRef,
                onMouseEnter: showTooltip,
                onMouseLeave: hideTooltip,
                onFocus: showTooltip,
                onBlur: hideTooltip,
            })}
            {isVisible && (
                <div
                    className={cn(
                        'absolute z-50 px-3 py-1.5 text-xs font-medium text-white',
                        'bg-slate-900 dark:bg-slate-700',
                        'rounded-lg shadow-lg',
                        'whitespace-nowrap',
                        'animate-in fade-in-50 zoom-in-95',
                        positionClasses[position],
                        className
                    )}
                    role="tooltip"
                >
                    {content}
                    <div className={cn('absolute w-0 h-0 border-4', arrowClasses[position])} />
                </div>
            )}
        </Fragment>
    );
}

export function TooltipTrigger({ children, ...props }: TooltipProps & { children: React.ReactElement }) {
    return <Tooltip {...props}>{children}</Tooltip>;
}
