import { cn } from '@utils';
import { getInitials, getColorForString } from '@utils';

export interface AvatarProps {
    src?: string;
    alt?: string;
    name?: string;
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    shape?: 'circle' | 'square';
    status?: 'online' | 'offline' | 'busy' | 'away';
    className?: string;
    onClick?: () => void;
}

const sizeClasses = {
    xs: 'w-6 h-6 text-xs',
    sm: 'w-8 h-8 text-sm',
    md: 'w-10 h-10 text-base',
    lg: 'w-12 h-12 text-lg',
    xl: 'w-16 h-16 text-xl',
    '2xl': 'w-24 h-24 text-2xl',
};

const statusSizeClasses = {
    xs: 'w-1.5 h-1.5',
    sm: 'w-2 h-2',
    md: 'w-2.5 h-2.5',
    lg: 'w-3 h-3',
    xl: 'w-4 h-4',
    '2xl': 'w-5 h-5',
};

const statusColors = {
    online: 'bg-green-500',
    offline: 'bg-slate-400',
    busy: 'bg-red-500',
    away: 'bg-amber-500',
};

export function Avatar({
    src,
    alt,
    name,
    size = 'md',
    shape = 'circle',
    status,
    className,
    onClick,
}: AvatarProps) {
    const hasImage = src && src.length > 0;
    const initials = name ? getInitials(name) : '?';
    const bgColor = name ? getColorForString(name) : 'bg-slate-400';

    return (
        <div className={cn('relative inline-flex', className)}>
            <button
                type="button"
                onClick={onClick}
                disabled={!onClick}
                className={cn(
                    'relative flex items-center justify-center overflow-hidden',
                    'bg-slate-100 dark:bg-slate-800',
                    sizeClasses[size],
                    shape === 'circle' ? 'rounded-full' : 'rounded-lg',
                    onClick && 'cursor-pointer hover:opacity-80 transition-opacity',
                    'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900'
                )}
                aria-label={alt || name || 'User avatar'}
            >
                {hasImage ? (
                    <img
                        src={src}
                        alt={alt || name || 'Avatar'}
                        className="w-full h-full object-cover"
                    />
                ) : (
                    <span
                        className={cn(
                            'font-medium text-white select-none',
                            bgColor
                        )}
                    >
                        {initials}
                    </span>
                )}
            </button>
            {status && (
                <span
                    className={cn(
                        'absolute bottom-0 right-0 border-2 border-white dark:border-slate-900 rounded-full',
                        statusSizeClasses[size],
                        statusColors[status]
                    )}
                    aria-label={status}
                />
            )}
        </div>
    );
}

export interface AvatarGroupProps {
    avatars: Array<{
        src?: string;
        alt?: string;
        name?: string;
    }>;
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
    max?: number;
    className?: string;
    onClick?: (index: number) => void;
}

export function AvatarGroup({ avatars, size = 'md', max = 5, className, onClick }: AvatarGroupProps) {
    const visibleAvatars = avatars.slice(0, max);
    const remainingCount = avatars.length - max;

    return (
        <div className={cn('flex -space-x-2', className)}>
            {visibleAvatars.map((avatar, index) => (
                <Avatar
                    key={index}
                    {...avatar}
                    size={size}
                    onClick={() => onClick?.(index)}
                />
            ))}
            {remainingCount > 0 && (
                <div
                    className={cn(
                        'flex items-center justify-center bg-slate-100 dark:bg-slate-800 border-2 border-white dark:border-slate-900 font-medium text-slate-600 dark:text-slate-400',
                        sizeClasses[size]
                    )}
                >
                    +{remainingCount}
                </div>
            )}
        </div>
    );
}
