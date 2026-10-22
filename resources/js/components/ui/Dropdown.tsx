import { Fragment, useRef, useEffect } from 'react';
import { Menu, Transition } from '@headlessui/react';
import { cn } from '@utils';
import { ChevronDown } from 'lucide-react';

export interface DropdownItem {
    label: string;
    onClick?: () => void;
    icon?: React.ReactNode;
    disabled?: boolean;
    danger?: boolean;
    divider?: boolean;
    shortcut?: string;
}

export interface DropdownProps {
    trigger: React.ReactNode;
    items: DropdownItem[];
    align?: 'left' | 'right';
    className?: string;
    closeOnClick?: boolean;
}

export function Dropdown({ trigger, items, align = 'right', className, closeOnClick = true }: DropdownProps) {
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                // Menu will auto-close via headlessui
            }
        };
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, []);

    return (
        <Menu as="div" className={cn('relative inline-block', className)}>
            <Menu.Button as={Fragment}>
                {trigger}
            </Menu.Button>

            <Transition
                as={Fragment}
                enter="transition ease-out duration-100"
                enterFrom="transform opacity-0 scale-95"
                enterTo="transform opacity-100 scale-100"
                leave="transition ease-in duration-75"
                leaveFrom="transform opacity-100 scale-100"
                leaveTo="transform opacity-0 scale-95"
            >
                <Menu.Items
                    ref={menuRef}
                    className={cn(
                        'absolute z-50 min-w-[160px] mt-1.5 overflow-hidden rounded-lg',
                        'bg-white dark:bg-slate-800',
                        'border border-slate-200 dark:border-slate-700',
                        'shadow-lg',
                        'py-1',
                        align === 'right' ? 'right-0' : 'left-0'
                    )}
                >
                    {items.map((item, index) => {
                        if (item.divider) {
                            return (
                                <div key={index} className="h-px bg-slate-200 dark:bg-slate-700 my-1" role="separator" />
                            );
                        }

                        return (
                            <Menu.Item key={index} disabled={item.disabled}>
                                {({ active }) => (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            item.onClick?.();
                                            if (closeOnClick) {
                                                // Menu auto-closes
                                            }
                                        }}
                                        disabled={item.disabled}
                                        className={cn(
                                            'w-full px-4 py-2 text-sm flex items-center gap-2',
                                            'transition-colors',
                                            'focus:outline-none focus:bg-slate-100 dark:focus:bg-slate-700',
                                            item.disabled
                                                ? 'opacity-50 cursor-not-allowed text-slate-400 dark:text-slate-500'
                                                : item.danger
                                                    ? 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'
                                                    : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700',
                                            active && 'bg-slate-100 dark:bg-slate-700'
                                        )}
                                    >
                                        {item.icon && (
                                            <span className="w-5 h-5 flex-shrink-0 flex items-center justify-center">
                                                {item.icon}
                                            </span>
                                        )}
                                        <span className="flex-1 text-left">{item.label}</span>
                                        {item.shortcut && (
                                            <span className="text-xs text-slate-400 dark:text-slate-500 font-mono">
                                                {item.shortcut}
                                            </span>
                                        )}
                                    </button>
                                )}
                            </Menu.Item>
                        );
                    })}
                </Menu.Items>
            </Transition>
        </Menu>
    );
}

export interface UserMenuProps {
    user: {
        name: string;
        email: string;
        avatar?: string;
    };
    items: DropdownItem[];
    onLogout: () => void;
    className?: string;
}

export function UserMenu({ user, items, onLogout, className }: UserMenuProps) {
    return (
        <Dropdown
            trigger={
                <button
                    type="button"
                    className={cn(
                        'flex items-center gap-2 p-1.5 rounded-lg',
                        'hover:bg-slate-100 dark:hover:bg-slate-800',
                        'transition-colors',
                        className
                    )}
                >
                    <img
                        src={user.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=3b82f6&color=fff`}
                        alt={user.name}
                        className="w-8 h-8 rounded-full"
                    />
                    <span className="hidden sm:block text-sm font-medium text-slate-700 dark:text-slate-300">
                        {user.name}
                    </span>
                    <ChevronDown className="w-4 h-4 text-slate-400" />
                </button>
            }
            items={[
                { label: 'Profile', icon: <UserIcon className="w-4 h-4" />, onClick: () => {} },
                { label: 'Settings', icon: <SettingsIcon className="w-4 h-4" />, onClick: () => {} },
                { divider: true },
                { label: 'Sign out', icon: <LogoutIcon className="w-4 h-4" />, onClick: onLogout, danger: true },
            ]}
            align="right"
        />
    );
}

// Icon components
function UserIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
    );
}

function SettingsIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    );
}

function LogoutIcon({ className }: { className?: string }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
    );
}
