import { useState } from 'react';
import { 
    TrendingUp, TrendingDown, DollarSign, Users, 
    Target, BarChart3, ArrowUpRight, ArrowDownRight,
    Plus, Filter, Calendar, Download, RefreshCw
} from 'lucide-react';
import { Card, CardHeader, CardContent, CardFooter } from '@components/ui/Card';
import { Button } from '@components/ui/Button';
import { Badge } from '@components/ui/Badge';
import { Select } from '@components/ui/Select';
import { Avatar } from '@components/ui/Avatar';
import { Table } from '@components/ui/Table';
import { LoadingSpinner, LoadingCard } from '@components/ui/LoadingSpinner';
import { cn, formatCurrency, formatNumber, formatPercent, formatRelativeTime } from '@utils';
import { Campaign, Client } from '@types';

interface MetricCardProps {
    title: string;
    value: string | number;
    change?: number;
    changeLabel?: string;
    icon: React.ReactNode;
    iconColor: string;
    trend?: 'up' | 'down' | 'neutral';
}

function MetricCard({ title, value, change, changeLabel, icon, iconColor, trend = 'neutral' }: MetricCardProps) {
    return (
        <Card>
            <CardContent className="pt-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium text-slate-500 dark:text-slate-400">{title}</p>
                        <p className="mt-1 text-3xl font-bold text-slate-900 dark:text-white">{value}</p>
                        {change !== undefined && (
                            <div className="mt-2 flex items-center gap-1">
                                {trend === 'up' ? (
                                    <ArrowUpRight className="w-4 h-4 text-green-500" />
                                ) : trend === 'down' ? (
                                    <ArrowDownRight className="w-4 h-4 text-red-500" />
                                ) : (
                                    <span className="w-4 h-4" />
                                )}
                                <span className={cn(
                                    'text-sm font-medium',
                                    trend === 'up' ? 'text-green-600 dark:text-green-400' :
                                    trend === 'down' ? 'text-red-600 dark:text-red-400' :
                                    'text-slate-500 dark:text-slate-400'
                                )}>
                                    {change >= 0 ? '+' : ''}{change.toFixed(1)}%
                                </span>
                                {changeLabel && (
                                    <span className="text-sm text-slate-500 dark:text-slate-400">{changeLabel}</span>
                                )}
                            </div>
                        )}
                    </div>
                    <div className={cn('p-3 rounded-xl', iconColor)}>
                        {icon}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

interface RecentActivity {
    id: string;
    type: 'campaign_created' | 'campaign_launched' | 'client_added' | 'report_generated' | 'budget_alert';
    title: string;
    description: string;
    user: { name: string; avatar?: string };
    timestamp: string;
}

export function Dashboard() {
    const [period, setPeriod] = useState<'week' | 'month' | 'quarter' | 'year'>('month');
    const [loading, setLoading] = useState(false);

    // Mock data - in real app, fetch from API
    const metrics = [
        {
            title: 'Total Revenue',
            value: '$127,432',
            change: 12.5,
            changeLabel: 'vs last month',
            icon: <DollarSign className="w-6 h-6" />,
            iconColor: 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400',
            trend: 'up' as const,
        },
        {
            title: 'Active Campaigns',
            value: 24,
            change: 8.2,
            changeLabel: 'vs last month',
            icon: <Target className="w-6 h-6" />,
            iconColor: 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400',
            trend: 'up' as const,
        },
        {
            title: 'Total Spend',
            value: '$89,234',
            change: -3.1,
            changeLabel: 'vs last month',
            icon: <TrendingUp className="w-6 h-6" />,
            iconColor: 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400',
            trend: 'down' as const,
        },
        {
            title: 'ROAS',
            value: '4.2x',
            change: 15.3,
            changeLabel: 'vs last month',
            icon: <BarChart3 className="w-6 h-6" />,
            iconColor: 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400',
            trend: 'up' as const,
        },
    ];

    const recentActivity: RecentActivity[] = [
        {
            id: '1',
            type: 'campaign_launched',
            title: 'Campaign Launched',
            description: 'Summer Sale 2024 campaign is now live on Facebook & Instagram',
            user: { name: 'Sarah Johnson', avatar: undefined },
            timestamp: '2024-01-15T10:30:00Z',
        },
        {
            id: '2',
            type: 'client_added',
            title: 'New Client Added',
            description: 'TechStart Inc. has been onboarded to your portfolio',
            user: { name: 'Mike Chen', avatar: undefined },
            timestamp: '2024-01-15T09:15:00Z',
        },
        {
            id: '3',
            type: 'budget_alert',
            title: 'Budget Alert',
            description: 'Q4 Holiday campaign has reached 85% of monthly budget',
            user: { name: 'System', avatar: undefined },
            timestamp: '2024-01-15T08:00:00Z',
        },
        {
            id: '4',
            type: 'report_generated',
            title: 'Report Generated',
            description: 'Monthly Performance Report for December 2023 is ready',
            user: { name: 'Alex Rivera', avatar: undefined },
            timestamp: '2024-01-14T16:45:00Z',
        },
        {
            id: '5',
            type: 'campaign_created',
            title: 'Campaign Created',
            description: 'New "Product Launch" campaign created in draft mode',
            user: { name: 'Emma Wilson', avatar: undefined },
            timestamp: '2024-01-14T14:20:00Z',
        },
    ];

    const topCampaigns: Campaign[] = [
        {
            id: 1,
            name: 'Summer Sale 2024',
            platform: 'facebook',
            status: 'active',
            spend: 12450,
            budget: 25000,
            metrics: { impressions: 245000, clicks: 3200, ctr: 1.31, conversions: 156, cpa: 79.81, roas: 4.2, revenue: 52340 },
            client_id: 1,
            created_at: '2024-01-10T00:00:00Z',
            updated_at: '2024-01-15T00:00:00Z',
        } as Campaign,
        {
            id: 2,
            name: 'Q4 Holiday Campaign',
            platform: 'google',
            status: 'active',
            spend: 34200,
            budget: 50000,
            metrics: { impressions: 189000, clicks: 5400, ctr: 2.86, conversions: 342, cpa: 100.00, roas: 3.8, revenue: 129960 },
            client_id: 2,
            created_at: '2023-11-01T00:00:00Z',
            updated_at: '2024-01-15T00:00:00Z',
        } as Campaign,
        {
            id: 3,
            name: 'Brand Awareness',
            platform: 'instagram',
            status: 'paused',
            spend: 8900,
            budget: 15000,
            metrics: { impressions: 156000, clicks: 1200, ctr: 0.77, conversions: 45, cpa: 197.78, roas: 2.1, revenue: 18700 },
            client_id: 1,
            created_at: '2023-12-15T00:00:00Z',
            updated_at: '2024-01-10T00:00:00Z',
        } as Campaign,
    ];

    const topClients: Client[] = [
        { id: 1, name: 'TechStart Inc.', email: 'contact@techstart.io', status: 'active', industry: 'SaaS', company_size: '50-200', created_at: '2023-06-15T00:00:00Z', updated_at: '2024-01-15T00:00:00Z', tags: ['enterprise', 'high-value'] } as Client,
        { id: 2, name: 'FashionForward', email: 'hello@fashionforward.com', status: 'active', industry: 'E-commerce', company_size: '200-500', created_at: '2023-03-20T00:00:00Z', updated_at: '2024-01-15T00:00:00Z', tags: ['retail', 'seasonal'] } as Client,
        { id: 3, name: 'HealthPlus', email: 'info@healthplus.com', status: 'active', industry: 'Healthcare', company_size: '1000+', created_at: '2023-09-10T00:00:00Z', updated_at: '2024-01-15T00:00:00Z', tags: ['healthcare', 'compliance'] } as Client,
    ];

    const periodOptions = [
        { value: 'week', label: 'Last 7 days' },
        { value: 'month', label: 'Last 30 days' },
        { value: 'quarter', label: 'Last 90 days' },
        { value: 'year', label: 'Last 365 days' },
    ];

    const getStatusBadge = (status: string) => {
        const statusConfig: Record<string, { variant: 'success' | 'warning' | 'danger' | 'default' | 'info'; label: string }> = {
            active: { variant: 'success', label: 'Active' },
            paused: { variant: 'warning', label: 'Paused' },
            draft: { variant: 'default', label: 'Draft' },
            completed: { variant: 'info', label: 'Completed' },
            archived: { variant: 'default', label: 'Archived' },
        };
        const config = statusConfig[status] || { variant: 'default', label: status };
        return <Badge variant={config.variant}>{config.label}</Badge>;
    };

    const campaignColumns = [
        { key: 'name', header: 'Campaign', accessor: (row: Campaign) => (
            <div>
                <p className="font-medium text-slate-900 dark:text-white">{row.name}</p>
                <p className="text-xs text-slate-500 dark:text-slate-400 capitalize">{row.platform}</p>
            </div>
        )},
        { key: 'status', header: 'Status', accessor: (row: Campaign) => getStatusBadge(row.status) },
        { key: 'spend', header: 'Spend', accessor: (row: Campaign) => formatCurrency(row.spend), align: 'right' },
        { key: 'budget', header: 'Budget', accessor: (row: Campaign) => formatCurrency(row.budget), align: 'right' },
        { key: 'roas', header: 'ROAS', accessor: (row: Campaign) => `${row.metrics.roas}x`, align: 'right' },
        { key: 'revenue', header: 'Revenue', accessor: (row: Campaign) => formatCurrency(row.metrics.revenue), align: 'right' },
    ];

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Dashboard</h1>
                    <p className="text-slate-500 dark:text-slate-400 mt-1">
                        Welcome back! Here's what's happening with your campaigns.
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <Select
                        value={period}
                        onChange={setPeriod}
                        options={periodOptions}
                        placeholder="Select period"
                        className="w-48"
                    />
                    <Button variant="outline" size="sm">
                        <RefreshCw className="w-4 h-4 mr-2" />
                        Refresh
                    </Button>
                    <Button>
                        <Plus className="w-4 h-4 mr-2" />
                        New Campaign
                    </Button>
                </div>
            </div>

            {/* Metrics Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                {metrics.map((metric, index) => (
                    <MetricCard key={index} {...metric} />
                ))}
            </div>

            {/* Charts Row - Placeholder for real charts */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader title="Revenue Trend" description="Monthly revenue vs spend" action={
                        <Button variant="ghost" size="sm">
                            <Download className="w-4 h-4 mr-1" />
                            Export
                        </Button>
                    } />
                    <CardContent>
                        <div className="h-64 flex items-end justify-around p-4">
                            {/* Simple bar chart visualization */}
                            {[12, 19, 15, 25, 22, 30, 28, 35, 32, 40, 38, 45].map((value, i) => (
                                <div key={i} className="flex-1 flex flex-col items-center gap-2">
                                    <div 
                                        className="w-full bg-blue-500 rounded-t transition-all hover:bg-blue-600"
                                        style={{ height: `${value}%` }}
                                    />
                                    <span className="text-xs text-slate-500 dark:text-slate-400">
                                        {['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][i]}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader title="Campaign Performance" description="Spend vs Revenue by platform" action={
                        <Button variant="ghost" size="sm">
                            <Download className="w-4 h-4 mr-1" />
                            Export
                        </Button>
                    } />
                    <CardContent>
                        <div className="h-64 flex items-center justify-around p-4">
                            {/* Simple donut chart placeholder */}
                            <div className="relative w-48 h-48">
                                <svg className="w-full h-full transform -rotate-90">
                                    <circle
                                        cx="96" cy="96" r="70"
                                        stroke="#e2e8f0" strokeWidth="14" fill="none"
                                        className="dark:stroke-slate-700"
                                    />
                                    <circle
                                        cx="96" cy="96" r="70"
                                        stroke="#3b82f6" strokeWidth="14" fill="none"
                                        strokeDasharray="439.6"
                                        strokeDashoffset="131.88"
                                        strokeLinecap="round"
                                        className="text-blue-500"
                                    />
                                    <circle
                                        cx="96" cy="96" r="70"
                                        stroke="#10b981" strokeWidth="14" fill="none"
                                        strokeDasharray="439.6"
                                        strokeDashoffset="295.72"
                                        strokeLinecap="round"
                                        className="text-green-500"
                                    />
                                    <circle
                                        cx="96" cy="96" r="70"
                                        stroke="#f59e0b" strokeWidth="14" fill="none"
                                        strokeDasharray="439.6"
                                        strokeDashoffset="373.66"
                                        strokeLinecap="round"
                                        className="text-amber-500"
                                    />
                                </svg>
                                <div className="absolute inset-0 flex items-center justify-center">
                                    <div className="text-center">
                                        <p className="text-2xl font-bold text-slate-900 dark:text-white">$201K</p>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">Total Revenue</p>
                                    </div>
                                </div>
                            </div>
                            <div className="flex flex-col gap-3 ml-8">
                                <div className="flex items-center gap-2">
                                    <div className="w-3 h-3 rounded-full bg-blue-500" />
                                    <span className="text-sm text-slate-600 dark:text-slate-400">Facebook (42%)</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="w-3 h-3 rounded-full bg-green-500" />
                                    <span className="text-sm text-slate-600 dark:text-slate-400">Google (35%)</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="w-3 h-3 rounded-full bg-amber-500" />
                                    <span className="text-sm text-slate-600 dark:text-slate-400">Instagram (15%)</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="w-3 h-3 rounded-full bg-purple-500" />
                                    <span className="text-sm text-slate-600 dark:text-slate-400">Other (8%)</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Bottom Row - Top Campaigns & Recent Activity */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Top Campaigns */}
                <Card className="lg:col-span-2">
                    <CardHeader 
                        title="Top Campaigns" 
                        description="Best performing campaigns this period"
                        action={
                            <Button variant="ghost" size="sm">
                                View all
                                <ArrowUpRight className="w-4 h-4 ml-1" />
                            </Button>
                        }
                    />
                    <CardContent className="pt-0">
                        <Table
                            columns={campaignColumns}
                            data={topCampaigns}
                            keyExtractor={(row) => row.id.toString()}
                            sortable
                            pagination
                            pageSize={5}
                        />
                    </CardContent>
                </Card>

                {/* Recent Activity & Top Clients */}
                <div className="space-y-6">
                    <Card>
                        <CardHeader 
                            title="Recent Activity" 
                            description="Latest actions across your workspace"
                        />
                        <CardContent className="pt-0">
                            <div className="space-y-4">
                                {recentActivity.map((activity) => (
                                    <div key={activity.id} className="flex items-start gap-3 p-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-lg transition-colors">
                                        <Avatar name={activity.user.name} size="sm" />
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-slate-900 dark:text-white">{activity.title}</p>
                                            <p className="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{activity.description}</p>
                                            <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                                By {activity.user.name} • {formatRelativeTime(activity.timestamp)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Button variant="ghost" size="sm" fullWidth>
                                View all activity
                                <ArrowUpRight className="w-4 h-4 ml-1" />
                            </Button>
                        </CardFooter>
                    </Card>

                    <Card>
                        <CardHeader 
                            title="Top Clients" 
                            description="Clients by revenue this period"
                        />
                        <CardContent className="pt-0">
                            <div className="space-y-4">
                                {topClients.map((client) => (
                                    <div key={client.id} className="flex items-center gap-3 p-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-lg transition-colors">
                                        <Avatar name={client.name} size="sm" />
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium text-slate-900 dark:text-white truncate">{client.name}</p>
                                            <p className="text-sm text-slate-500 dark:text-slate-400">{client.industry} • {client.company_size}</p>
                                        </div>
                                        <Badge variant="success">{client.status}</Badge>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                        <CardFooter>
                            <Button variant="ghost" size="sm" fullWidth>
                                View all clients
                                <ArrowUpRight className="w-4 h-4 ml-1" />
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </div>
    );
}
