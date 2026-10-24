import { Fragment } from 'react';
import { cn } from '@utils';
import { Bell, Search, Moon, Sun, HelpCircle, ChevronDown } from 'lucide-react';
import { useAuth } from '@hooks/useAuth';
import { useTheme } from '@context/ThemeContext';
import { Dropdown, UserMenu } from '@components/ui/Dropdown';
import { Avatar } from '@components/ui/Avatar';
import { Badge } from '@components/ui/Badge';
import Button from '@components/ui/Button';
import { Tooltip } from '@components/ui/Tooltip';

interface HeaderProps {
    onMenuClick: () => void;
    sidebarOpen: boolean;
}

export function Header({ onMenuClick, sidebarOpen }: HeaderProps) {
    const { user, hasPermission } = useAuth();
    const { theme, resolvedTheme, setTheme } = useTheme();

    const notifications = [
        { id: 1, title: 'Campaign approved', message: 'Your campaign "Summer Sale" has been approved', time: '2 min ago', read: false },
        { id: 2, title: 'Budget alert', message: 'Campaign "Q4 Launch" has reached 80% of budget', time: '1 hour ago', read: false },
        { id: 3, title: 'New feature available', message: 'AI Copy Generator v2.0 is now available', time: '3 hours ago', read: true },
    ];

    const unreadCount = notifications.filter(n => !n.read).length;

    return (
        <header className={cn(
            'fixed top-0 right-0 left-0 z-30 h-16 bg-white/80 dark:bg-slate-900/80 backdrop-blur-lg',
            'border-b border-slate-200 dark:border-slate-700',
            'transition-all duration-300',
            sidebarOpen ? 'lg:left-64' : 'left-0'
        )}>
            <div className="flex h-full items-center justify-between px-4 lg:px-6">
                {/* Left side - Menu toggle and search */}
                <div className="flex items-center gap-4">
                    <button
                        onClick={onMenuClick}
                        className={cn(
                            'lg:hidden p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800',
                            'transition-colors'
                        )}
                        aria-label="Toggle navigation menu"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    {/* Search */}
                    <div className="hidden sm:block relative">
                        <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            type="search"
                            placeholder="Search campaigns, clients, reports..."
                            className="w-64 sm:w-80 pl-10 pr-4 py-2 text-sm bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            aria-label="Global search"
                        />
                    </div>
                </div>

                {/* Right side - Notifications, Theme, Help, User */}
                <div className="flex items-center gap-2">
                    {/* Theme toggle */}
                    <Tooltip content={resolvedTheme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'} position="bottom">
                        <button
                            onClick={() => setTheme(resolvedTheme === 'dark' ? 'light' : 'dark')}
                            className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            aria-label={resolvedTheme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
                        >
                            {resolvedTheme === 'dark' ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
                        </button>
                    </Tooltip>

                    {/* Notifications */}
                    <Tooltip content="Notifications" position="bottom">
                        <Dropdown
                            trigger={
                                <button
                                    className={cn(
                                        'relative p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800',
                                        'transition-colors'
                                    )}
                                    aria-label="Notifications"
                                >
                                    <Bell className="w-5 h-5 text-slate-600 dark:text-slate-400" />
                                    {unreadCount > 0 && (
                                        <span className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-medium rounded-full flex items-center justify-center">
                                            {unreadCount > 9 ? '9+' : unreadCount}
                                        </span>
                                    )}
                                </button>
                            }
                            items={notifications.map(n => ({
                                label: (
                                    <div className="w-80">
                                        <p className={cn('font-medium text-sm', !n.read && 'font-semibold')}>{n.title}</p>
                                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{n.message}</p>
                                        <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">{n.time}</p>
                                    </div>
                                ),
                                onClick: () => {},
                                danger: false,
                            }))}
                            align="right"
                        />
                    </Tooltip>

                    {/* Help */}
                    <Tooltip content="Help & Documentation" position="bottom">
                        <button
                            className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            aria-label="Help & Documentation"
                        >
                            <HelpCircle className="w-5 h-5 text-slate-600 dark:text-slate-400" />
                        </button>
                    </Tooltip>

                    {/* User menu */}
                    <UserMenu 
                        user={{ name: user?.name || 'User', email: user?.email || '', avatar: user?.avatar }} 
                        items={[
                            { label: 'Profile', icon: <UserIcon className="w-4 h-4" />, onClick: () => {} },
                            { label: 'Settings', icon: <SettingsIcon className="w-4 h-4" />, onClick: () => {} },
                            { divider: true },
                            { label: 'Sign out', icon: <LogoutIcon className="w-4 h-4" />, onClick: () => {}, danger: true },
                        ]}
                        onLogout={() => {}}
                    />
                </div>
            </div>
        </header>
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
