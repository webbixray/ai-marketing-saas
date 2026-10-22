import { Fragment, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { cn } from '@utils';
import { 
    LayoutDashboard, 
    Users, 
    Target, 
    FileText, 
    BarChart3, 
    TrendingUp, 
    Zap, 
    Brain, 
    Palette, 
    Shield, 
    Database, 
    Globe, 
    Cloud, 
    HardDrive, 
    DollarSign, 
    Settings, 
    ChevronLeft, 
    ChevronRight,
    Menu,
    X,
} from 'lucide-react';
import { useAuth } from '@hooks/useAuth';
import { Badge } from '@components/ui';
import { Dropdown, UserMenu } from '@components/ui/Dropdown';
import { Avatar } from '@components/ui/Avatar';

const navigation = [
    { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, roles: [] },
    { name: 'Campaigns', href: '/campaigns', icon: Target, roles: [] },
    { name: 'Clients', href: '/clients', icon: Users, roles: [] },
    { name: 'Creative Studio', href: '/creative-studio', icon: Palette, roles: [] },
    { name: 'Copy Generator', href: '/copy-generator', icon: FileText, roles: [] },
    { 
        name: 'Analytics', 
        href: '/analytics', 
        icon: BarChart3, 
        children: [
            { name: 'Overview', href: '/analytics' },
            { name: 'Attribution', href: '/analytics/attribution' },
            { name: 'Forecasting', href: '/analytics/forecasting' },
            { name: 'Anomalies', href: '/analytics/anomalies' },
        ] 
    },
    { 
        name: 'Reports', 
        href: '/reports', 
        icon: FileText, 
        children: [
            { name: 'Standard Reports', href: '/reports' },
            { name: 'Custom Reports', href: '/reports/custom' },
        ] 
    },
];

const enterpriseNavigation = [
    { name: 'SSO & Identity', href: '/enterprise/sso', icon: Shield, permission: 'configure_sso' },
    { name: 'SCIM Provisioning', href: '/enterprise/scim', icon: Users, permission: 'configure_scim' },
    { name: 'White Label', href: '/enterprise/white-label', icon: Palette, permission: 'configure_white_label' },
    { name: 'Audit & Compliance', href: '/enterprise/audit', icon: Database, permission: 'view_compliance_reports' },
    { name: 'Advanced RBAC', href: '/enterprise/rbac', icon: Shield, permission: 'assign_roles' },
    { name: 'Marketplace', href: '/enterprise/marketplace', icon: Globe, permission: 'submit_marketplace_listing' },
];

const globalNavigation = [
    { name: 'Multi-Region', href: '/global/regions', icon: Globe, permission: 'manage_regions' },
    { name: 'CDN Management', href: '/global/cdn', icon: Cloud, permission: 'manage_cdn' },
    { name: 'Advanced Analytics', href: '/global/analytics', icon: TrendingUp, permission: 'view_advanced_analytics' },
    { name: 'Data Governance', href: '/global/data-governance', icon: Database, permission: 'manage_data_governance' },
    { name: 'Disaster Recovery', href: '/global/disaster-recovery', icon: HardDrive, permission: 'manage_disaster_recovery' },
    { name: 'Cost Optimization', href: '/global/cost-optimization', icon: DollarSign, permission: 'view_cost_optimization' },
];

interface SidebarProps {
    isOpen: boolean;
    onClose: () => void;
    onToggle: () => void;
}

export function Sidebar({ isOpen, onClose, onToggle }: SidebarProps) {
    const location = useLocation();
    const { user, hasPermission } = useAuth();
    const [expandedSections, setExpandedSections] = useState<string[]>(['marketing', 'analytics', 'enterprise', 'global']);
    const [mobileOpen, setMobileOpen] = useState(false);

    const toggleSection = (section: string) => {
        setExpandedSections(prev => 
            prev.includes(section) 
                ? prev.filter(s => s !== section) 
                : [...prev, section]
        );
    };

    const isActive = (href: string) => {
        if (href === '/dashboard') return location.pathname === '/dashboard';
        return location.pathname.startsWith(href);
    };

    const isSectionActive = (section: string) => {
        const sectionRoutes: Record<string, string[]> = {
            marketing: ['/campaigns', '/clients', '/creative-studio', '/copy-generator'],
            analytics: ['/analytics', '/reports'],
            enterprise: ['/enterprise/sso', '/enterprise/scim', '/enterprise/white-label', '/enterprise/audit', '/enterprise/rbac', '/enterprise/marketplace'],
            global: ['/global/regions', '/global/cdn', '/global/analytics', '/global/data-governance', '/global/disaster-recovery', '/global/cost-optimization'],
        };
        return sectionRoutes[section]?.some(route => location.pathname.startsWith(route)) ?? false;
    };

    const renderNavItem = (item: typeof navigation[0]) => {
        const active = isActive(item.href);
        const hasChildren = item.children && item.children.length > 0;
        const isExpanded = expandedSections.includes(item.name.toLowerCase().replace(' ', '-'));

        if (hasChildren) {
            return (
                <div key={item.name}>
                    <button
                        onClick={() => toggleSection(item.name.toLowerCase().replace(' ', '-'))}
                        className={cn(
                            'w-full flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg',
                            'text-sm font-medium transition-colors',
                            active || isSectionActive(item.name.toLowerCase().replace(' ', '-'))
                                ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100'
                        )}
                    >
                        <span className="flex items-center gap-3">
                            <item.icon className="w-5 h-5 flex-shrink-0" />
                            <span className="truncate">{item.name}</span>
                        </span>
                        <ChevronRight 
                            className={cn(
                                'w-4 h-4 text-slate-400 transition-transform',
                                isExpanded && 'rotate-90'
                            )} 
                        />
                    </button>
                    {isExpanded && (
                        <div className="mt-1 ml-8 space-y-1 border-l border-slate-200 dark:border-slate-700 pl-3">
                            {item.children?.map(child => (
                                <NavLink
                                    key={child.name}
                                    to={child.href}
                                    className={({ isActive }) => cn(
                                        'block px-3 py-1.5 rounded-lg text-sm transition-colors',
                                        isActive
                                            ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                            : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100'
                                    )}
                                >
                                    {child.name}
                                </NavLink>
                            ))}
                        </div>
                    )}
                </div>
            );
        }

        return (
            <NavLink
                to={item.href}
                className={({ isActive }) => cn(
                    'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                    isActive
                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100'
                )}
            >
                <item.icon className="w-5 h-5 flex-shrink-0" />
                <span className="truncate">{item.name}</span>
            </NavLink>
        );
    };

    if (isOpen) {
        // Desktop sidebar
        return (
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-40 w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-700',
                    'flex flex-col transition-transform duration-300 ease-in-out',
                    'lg:translate-x-0'
                )}
            >
                <div className="flex h-16 items-center justify-between px-4 border-b border-slate-200 dark:border-slate-700">
                    <div className="flex items-center gap-2">
                        <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center">
                            <Zap className="w-5 h-5 text-white" />
                        </div>
                        <span className="font-bold text-lg text-slate-900 dark:text-white">
                            Webixray
                        </span>
                    </div>
                    <button
                        onClick={onClose}
                        className="lg:hidden p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
                        aria-label="Close sidebar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <nav className="flex-1 overflow-y-auto p-4 space-y-6" aria-label="Main navigation">
                    {/* Marketing Section */}
                    <div>
                        <h3 className="px-3 py-1 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            Marketing
                        </h3>
                        <div className="space-y-1">
                            {navigation.map(renderNavItem)}
                        </div>
                    </div>

                    {/* Enterprise Section */}
                    {enterpriseNavigation.some(item => hasPermission(item.permission)) && (
                        <div>
                            <div className="flex items-center justify-between px-3 py-1">
                                <h3 className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    Enterprise
                                </h3>
                                <button
                                    onClick={() => toggleSection('enterprise')}
                                    className="p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-800"
                                >
                                    {expandedSections.includes('enterprise') ? <ChevronRight className="w-4 h-4 rotate-90" /> : <ChevronRight className="w-4 h-4" />}
                                </button>
                            </div>
                            {expandedSections.includes('enterprise') && (
                                <div className="space-y-1 border-l border-slate-200 dark:border-slate-700 pl-3 ml-3">
                                    {enterpriseNavigation
                                        .filter(item => hasPermission(item.permission))
                                        .map(item => (
                                            <NavLink
                                                key={item.name}
                                                to={item.href}
                                                className={({ isActive }) => cn(
                                                    'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                                    isActive
                                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100'
                                                )}
                                            >
                                                <item.icon className="w-5 h-5 flex-shrink-0" />
                                                <span className="truncate">{item.name}</span>
                                            </NavLink>
                                        ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Global Section */}
                    {globalNavigation.some(item => hasPermission(item.permission)) && (
                        <div>
                            <div className="flex items-center justify-between px-3 py-1">
                                <h3 className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    Global Scale
                                </h3>
                                <button
                                    onClick={() => toggleSection('global')}
                                    className="p-1 rounded hover:bg-slate-100 dark:hover:bg-slate-800"
                                >
                                    {expandedSections.includes('global') ? <ChevronRight className="w-4 h-4 rotate-90" /> : <ChevronRight className="w-4 h-4" />}
                                </button>
                            </div>
                            {expandedSections.includes('global') && (
                                <div className="space-y-1 border-l border-slate-200 dark:border-slate-700 pl-3 ml-3">
                                    {globalNavigation
                                        .filter(item => hasPermission(item.permission))
                                        .map(item => (
                                            <NavLink
                                                key={item.name}
                                                to={item.href}
                                                className={({ isActive }) => cn(
                                                    'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                                    isActive
                                                        ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100'
                                                )}
                                            >
                                                <item.icon className="w-5 h-5 flex-shrink-0" />
                                                <span className="truncate">{item.name}</span>
                                            </NavLink>
                                        ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Settings */}
                    <div>
                        <h3 className="px-3 py-1 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            Settings
                        </h3>
                        <NavLink
                            to="/settings"
                            className={({ isActive }) => cn(
                                'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                isActive
                                    ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100'
                            )}
                        >
                            <Settings className="w-5 h-5 flex-shrink-0" />
                            <span>Settings</span>
                        </NavLink>
                    </div>
                </nav>

                {/* User menu */}
                <div className="p-4 border-t border-slate-200 dark:border-slate-700">
                    <UserMenu user={{ name: user?.name || 'User', email: user?.email || '', avatar: user?.avatar }} 
                        items={[]}
                        onLogout={() => {}}
                    />
                </div>
            </aside>
        );
    }

    // Mobile drawer
    return (
        <Fragment>
            <button
                onClick={onToggle}
                className="lg:hidden fixed top-4 left-4 z-50 p-2 rounded-lg bg-white dark:bg-slate-800 shadow-lg border border-slate-200 dark:border-slate-700"
                aria-label="Open navigation menu"
            >
                <Menu className="w-6 h-6" />
            </button>

            <Transition.Root show={mobileOpen} as={Fragment}>
                <Dialog as="div" className="relative z-50 lg:hidden" onClose={setMobileOpen(false)}>
                    <Transition.Child
                        as={Fragment}
                        enter="ease-in-out duration-300"
                        enterFrom="opacity-0"
                        enterTo="opacity-100"
                        leave="ease-in-out duration-300"
                        leaveFrom="opacity-100"
                        leaveTo="opacity-0"
                    >
                        <div className="fixed inset-0 bg-black/50" />
                    </Transition.Child>

                    <div className="fixed inset-y-0 left-0 w-64 bg-white dark:bg-slate-900 shadow-xl">
                        <div className="flex h-16 items-center justify-between px-4 border-b border-slate-200 dark:border-slate-700">
                            <div className="flex items-center gap-2">
                                <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center">
                                    <Zap className="w-5 h-5 text-white" />
                                </div>
                                <span className="font-bold text-lg text-slate-900 dark:text-white">
                                    Webixray
                                </span>
                            </div>
                            <button onClick={() => setMobileOpen(false)} className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <nav className="flex-1 overflow-y-auto p-4 space-y-6">
                            <div>
                                <h3 className="px-3 py-1 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    Marketing
                                </h3>
                                <div className="space-y-1">
                                    {navigation.map(renderNavItem)}
                                </div>
                            </div>

                            {enterpriseNavigation.some(item => hasPermission(item.permission)) && (
                                <div>
                                    <div className="flex items-center justify-between px-3 py-1">
                                        <h3 className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Enterprise
                                        </h3>
                                    </div>
                                    <div className="space-y-1 border-l border-slate-200 dark:border-slate-700 pl-3 ml-3">
                                        {enterpriseNavigation
                                            .filter(item => hasPermission(item.permission))
                                            .map(item => (
                                                <NavLink
                                                    key={item.name}
                                                    to={item.href}
                                                    className={({ isActive }) => cn(
                                                        'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                                        isActive
                                                            ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                                            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100'
                                                    )}
                                                >
                                                    <item.icon className="w-5 h-5 flex-shrink-0" />
                                                    <span className="truncate">{item.name}</span>
                                                </NavLink>
                                            ))}
                                    </div>
                                </div>
                            )}

                            {globalNavigation.some(item => hasPermission(item.permission)) && (
                                <div>
                                    <div className="flex items-center justify-between px-3 py-1">
                                        <h3 className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            Global Scale
                                        </h3>
                                    </div>
                                    <div className="space-y-1 border-l border-slate-200 dark:border-slate-700 pl-3 ml-3">
                                        {globalNavigation
                                            .filter(item => hasPermission(item.permission))
                                            .map(item => (
                                                <NavLink
                                                    key={item.name}
                                                    to={item.href}
                                                    className={({ isActive }) => cn(
                                                        'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                                        isActive
                                                            ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                                            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100'
                                                    )}
                                                >
                                                    <item.icon className="w-5 h-5 flex-shrink-0" />
                                                    <span className="truncate">{item.name}</span>
                                                </NavLink>
                                            ))}
                                    </div>
                                </div>
                            )}

                            <div>
                                <h3 className="px-3 py-1 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    Settings
                                </h3>
                                <NavLink
                                    to="/settings"
                                    className={({ isActive }) => cn(
                                        'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                        isActive
                                            ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'
                                            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100'
                                    )}
                                >
                                    <Settings className="w-5 h-5 flex-shrink-0" />
                                    <span>Settings</span>
                                </NavLink>
                            </div>
                        </nav>

                        <div className="p-4 border-t border-slate-200 dark:border-slate-700">
                            <UserMenu user={{ name: user?.name || 'User', email: user?.email || '', avatar: user?.avatar }} 
                                items={[]}
                                onLogout={() => {}}
                            />
                        </div>
                    </div>
                </Dialog>
            </Transition.Root>
        </Fragment>
    );
}
