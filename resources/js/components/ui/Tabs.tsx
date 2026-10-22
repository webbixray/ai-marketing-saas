import { cn } from '@utils';

export interface Tab {
    id: string;
    label: string;
    icon?: React.ReactNode;
    disabled?: boolean;
    badge?: string | number;
}

export interface TabsProps {
    tabs: Tab[];
    activeTab: string;
    onChange: (tabId: string) => void;
    variant?: 'default' | 'pills' | 'underline' | 'enclosed';
    fullWidth?: boolean;
    className?: string;
}

const variantClasses = {
    default: '',
    pills: '',
    underline: 'border-b border-slate-200 dark:border-slate-700',
    enclosed: '',
};

const tabVariantClasses = {
    default: {
        base: 'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
        active: 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-100',
        inactive: 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800',
    },
    pills: {
        base: 'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
        active: 'bg-blue-600 text-white shadow-sm',
        inactive: 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800',
    },
    underline: {
        base: 'px-4 py-3 text-sm font-medium border-b-2 transition-colors -mb-px',
        active: 'border-blue-600 text-blue-600 dark:text-blue-400',
        inactive: 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300 dark:hover:border-slate-600',
    },
    enclosed: {
        base: 'px-4 py-2 text-sm font-medium rounded-lg transition-colors border',
        active: 'bg-blue-600 text-white border-blue-600',
        inactive: 'border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800',
    },
};

export function Tabs({ tabs, activeTab, onChange, variant = 'default', fullWidth = false, className }: TabsProps) {
    const tabStyles = tabVariantClasses[variant];

    return (
        <div className={cn('w-full', className)}>
            <div
                className={cn(
                    'flex gap-1',
                    variant === 'underline' && 'border-b border-slate-200 dark:border-slate-700',
                    fullWidth && 'w-full'
                )}
                role="tablist"
                aria-label="Tabs"
            >
                {tabs.map(tab => {
                    const isActive = activeTab === tab.id;
                    const styles = isActive ? tabStyles.active : tabStyles.inactive;

                    return (
                        <button
                            key={tab.id}
                            role="tab"
                            aria-selected={isActive}
                            aria-controls={`${tab.id}-panel`}
                            id={`${tab.id}-tab`}
                            onClick={() => !tab.disabled && onChange(tab.id)}
                            disabled={tab.disabled}
                            className={cn(
                                'flex items-center gap-1.5 whitespace-nowrap',
                                tabStyles.base,
                                styles,
                                tab.disabled && 'opacity-50 cursor-not-allowed',
                                fullWidth && 'flex-1 justify-center',
                                'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'
                            )}
                        >
                            {tab.icon && <span className="flex-shrink-0">{tab.icon}</span>}
                            {tab.label}
                            {tab.badge && (
                                <span className={cn(
                                    'px-1.5 py-0.5 text-xs font-medium rounded-full',
                                    isActive
                                        ? variant === 'pills' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'
                                )}>
                                    {tab.badge}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

export interface TabPanelProps {
    id: string;
    activeTab: string;
    children: React.ReactNode;
    className?: string;
}

export function TabPanel({ id, activeTab, children, className }: TabPanelProps) {
    if (activeTab !== id) return null;

    return (
        <div
            role="tabpanel"
            id={`${id}-panel`}
            aria-labelledby={`${id}-tab`}
            className={cn('mt-4', className)}
        >
            {children}
        </div>
    );
}
