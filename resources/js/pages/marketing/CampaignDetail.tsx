import { useState } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Edit, Trash2, Play, Pause, Download, Share2, MoreHorizontal, TrendingUp, DollarSign, Users, Eye, Activity, Calendar, Clock } from 'lucide-react';
import Button from '@components/ui/Button';
import { Card, CardHeader, CardContent, CardFooter } from '@components/ui/Card';
import { Badge, StatusBadge } from '@components/ui/Badge';
import { Table } from '@components/ui/Table';
import { Tabs, TabPanel } from '@components/ui/Tabs';
import { Avatar } from '@components/ui/Avatar';
import { Dropdown } from '@components/ui/Dropdown';
import { cn, formatCurrency, formatNumber, formatPercent, formatRelativeTime } from '@utils';
import toast from 'react-hot-toast';

const mockCampaign = {
    id: 1,
    name: 'Summer Sale 2024',
    description: 'Promote summer collection across all channels',
    platform: 'facebook',
    status: 'active',
    objective: 'sales',
    budget_type: 'daily',
    budget: 25000,
    spend: 12450,
    currency: 'USD',
    targeting: {
        locations: ['United States', 'Canada'],
        age_min: 25,
        age_max: 45,
        genders: ['all'],
        interests: ['fashion', 'shopping', 'summer wear'],
    },
    creative: {
        format: 'carousel',
        primary_text: 'Summer is here! Discover our latest collection with up to 50% off.',
        headline: 'Summer Sale - Up to 50% Off',
        description: 'Limited time offer on all summer wear',
        call_to_action: 'shop_now',
    },
    schedule: {
        start_date: '2024-01-10',
        end_date: '2024-03-31',
        timezone: 'America/New_York',
    },
    utm: {
        source: 'facebook',
        medium: 'cpc',
        campaign: 'summer_sale_2024',
        content: 'carousel_ad',
    },
    metrics: {
        impressions: 245000,
        clicks: 3200,
        ctr: 1.31,
        conversions: 156,
        cpa: 79.81,
        roas: 4.2,
        revenue: 52340,
    },
    client_id: 1,
    created_by: 'John Doe',
    created_at: '2024-01-10T00:00:00Z',
    updated_at: '2024-01-15T00:00:00Z',
};

const metricsCards = [
    { label: 'Impressions', value: '245K', change: 12.5, icon: <Eye className="w-5 h-5" />, color: 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400' },
    { label: 'Clicks', value: '3.2K', change: 8.2, icon: <Activity className="w-5 h-5" />, color: 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400' },
    { label: 'CTR', value: '1.31%', change: -3.1, icon: <TrendingUp className="w-5 h-5" />, color: 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400' },
    { label: 'Conversions', value: 156, change: 15.3, icon: <Users className="w-5 h-5" />, color: 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400' },
    { label: 'CPA', value: '$79.81', change: -5.2, icon: <DollarSign className="w-5 h-5" />, color: 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' },
    { label: 'ROAS', value: '4.2x', change: 12.1, icon: <TrendingUp className="w-5 h-5" />, color: 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400' },
];

const detailTabs = [
    { id: 'overview', label: 'Overview', icon: <Activity className="w-4 h-4" /> },
    { id: 'performance', label: 'Performance', icon: <TrendingUp className="w-4 h-4" /> },
    { id: 'creative', label: 'Creative', icon: <Image className="w-4 h-4" /> },
    { id: 'targeting', label: 'Targeting', icon: <Users className="w-4 h-4" /> },
    { id: 'settings', label: 'Settings', icon: <Settings className="w-4 h-4" /> },
];

function Settings({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>;
}

export function CampaignDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [activeTab, setActiveTab] = useState('overview');
    const [actionMenuOpen, setActionMenuOpen] = useState<string | null>(null);

    const handleAction = (action: string) => {
        setActionMenuOpen(null);
        switch (action) {
            case 'edit':
                navigate(`/campaigns/${id}/edit`);
                break;
            case 'duplicate':
                toast.success('Campaign duplicated!');
                break;
            case 'delete':
                if (confirm('Are you sure you want to delete this campaign?')) {
                    toast.success('Campaign deleted!');
                    navigate('/campaigns');
                }
                break;
            case 'pause':
                toast.success('Campaign paused');
                break;
            case 'launch':
                toast.success('Campaign launched!');
                break;
        }
    };

    const getStatusConfig = (status: string) => {
        const configs: Record<string, { variant: 'success' | 'warning' | 'danger' | 'default' | 'info'; label: string; icon: React.ReactNode }> = {
            active: { variant: 'success', label: 'Active', icon: <Play className="w-3 h-3" /> },
            paused: { variant: 'warning', label: 'Paused', icon: <Pause className="w-3 h-3" /> },
            draft: { variant: 'default', label: 'Draft', icon: <Edit className="w-3 h-3" /> },
            completed: { variant: 'info', label: 'Completed', icon: <CheckCircle className="w-3 h-3" /> },
            archived: { variant: 'default', label: 'Archived', icon: <Archive className="w-3 h-3" /> },
        };
        return configs[status] || { variant: 'default', label: status, icon: <HelpCircle className="w-3 h-3" /> };
    };

    function Archive({ className }: { className?: string }) {
        return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8M9 8v10" /></svg>;
    }

    function HelpCircle({ className }: { className?: string }) {
        return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.5-3 3-1.764 0-3.404-1.292-3.761-2.454" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.5 13.5a.5.5 0 11-1 0 .5.5 0 011 0zM12 16.5a.5.5 0 11-1 0 .5.5 0 011 0z" /></svg>;
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex items-center gap-3">
                    <Link to="/campaigns" className="text-slate-500 hover:text-slate-700">
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">{mockCampaign.name}</h1>
                        <p className="text-slate-500 dark:text-slate-400">{mockCampaign.platform} • {mockCampaign.objective}</p>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <Dropdown
                        trigger={
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="w-5 h-5" />
                            </Button>
                        }
                        items={[
                            { label: 'Edit Campaign', icon: <Edit className="w-4 h-4" />, onClick: () => handleAction('edit') },
                            { label: 'Duplicate', icon: <CopyIcon className="w-4 h-4" />, onClick: () => handleAction('duplicate') },
                            { divider: true },
                            { label: mockCampaign.status === 'active' ? 'Pause Campaign' : 'Launch Campaign', icon: mockCampaign.status === 'active' ? <Pause className="w-4 h-4" /> : <Play className="w-4 h-4" />, onClick: () => handleAction(mockCampaign.status === 'active' ? 'pause' : 'launch') },
                            { label: 'Delete', icon: <Trash2 className="w-4 h-4" />, onClick: () => handleAction('delete'), danger: true },
                        ]}
                        align="right"
                    />
                    <Link to="/campaigns">
                        <Button variant="outline" size="sm">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            Back to Campaigns
                        </Button>
                    </Link>
                </div>
            </div>

            {/* Status & Key Metrics */}
            <div className="flex flex-wrap items-center gap-4 mb-6">
                <StatusBadge status={mockCampaign.status} />
                <div className="flex items-center gap-2 px-3 py-1 bg-slate-100 dark:bg-slate-800 rounded-lg text-sm">
                    <Calendar className="w-4 h-4 text-slate-500" />
                    <span>Created {formatRelativeTime(mockCampaign.created_at)}</span>
                </div>
                <div className="flex items-center gap-2 px-3 py-1 bg-slate-100 dark:bg-slate-800 rounded-lg text-sm">
                    <Clock className="w-4 h-4 text-slate-500" />
                    <span>Updated {formatRelativeTime(mockCampaign.updated_at)}</span>
                </div>
            </div>

            {/* Metrics Grid */}
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                {metricsCards.map((metric, index) => (
                    <Card key={index}>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-slate-500 dark:text-slate-400">{metric.label}</p>
                                    <p className="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{metric.value}</p>
                                    <div className="mt-1 flex items-center gap-1">
                                        {metric.change >= 0 ? (
                                            <TrendingUp className="w-4 h-4 text-green-500" />
                                        ) : (
                                            <TrendingDown className="w-4 h-4 text-red-500" />
                                        )}
                                        <span className={cn(
                                            'text-sm font-medium',
                                            metric.change >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                                        )}>
                                            {metric.change >= 0 ? '+' : ''}{metric.change.toFixed(1)}%
                                        </span>
                                    </div>
                                </div>
                                <div className={cn('p-3 rounded-xl', metric.color)}>
                                    {metric.icon}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {/* Detail Tabs */}
            <Tabs tabs={detailTabs} activeTab={activeTab} onChange={setActiveTab} variant="default" />

            <Card>
                <CardContent className="pt-6">
                    {activeTab === 'overview' && (
                        <div className="space-y-6">
                            {/* Description */}
                            <div>
                                <h3 className="text-lg font-semibold mb-3">Description</h3>
                                <p className="text-slate-600 dark:text-slate-400">{mockCampaign.description}</p>
                            </div>

                            {/* Budget & Schedule */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                    <h4 className="font-medium mb-3">Budget</h4>
                                    <dl className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Type</dt>
                                            <dd className="font-medium capitalize">{mockCampaign.budget_type}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Amount</dt>
                                            <dd className="font-medium">{formatCurrency(mockCampaign.budget, mockCampaign.currency)}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Spent</dt>
                                            <dd className="font-medium">{formatCurrency(mockCampaign.spend, mockCampaign.currency)}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Remaining</dt>
                                            <dd className="font-medium text-green-600 dark:text-green-400">{formatCurrency(mockCampaign.budget - mockCampaign.spend, mockCampaign.currency)}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Budget Used</dt>
                                            <dd className="font-medium">
                                                <div className="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                                    <div className="h-full bg-blue-500 rounded-full" style={{ width: `${(mockCampaign.spend / mockCampaign.budget) * 100}%` }} />
                                                </div>
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                    <h4 className="font-medium mb-3">Schedule</h4>
                                    <dl className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Start Date</dt>
                                            <dd className="font-medium">{formatDate(mockCampaign.schedule.start_date)}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">End Date</dt>
                                            <dd className="font-medium">{mockCampaign.schedule.end_date ? formatDate(mockCampaign.schedule.end_date) : 'Ongoing'}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Timezone</dt>
                                            <dd className="font-medium">{mockCampaign.schedule.timezone}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            {/* Targeting Summary */}
                            <div>
                                <h3 className="text-lg font-semibold mb-3">Targeting</h3>
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                        <p className="text-sm text-slate-500">Locations</p>
                                        <p className="font-medium">{mockCampaign.targeting.locations.join(', ')}</p>
                                    </div>
                                    <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                        <p className="text-sm text-slate-500">Age Range</p>
                                        <p className="font-medium">{mockCampaign.targeting.age_min} - {mockCampaign.targeting.age_max}</p>
                                    </div>
                                    <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                        <p className="text-sm text-slate-500">Genders</p>
                                        <p className="font-medium capitalize">{mockCampaign.targeting.genders.join(', ')}</p>
                                    </div>
                                    <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                        <p className="text-sm text-slate-500">Interests</p>
                                        <p className="font-medium text-sm truncate">{mockCampaign.targeting.interests.join(', ')}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {activeTab === 'performance' && (
                        <div className="space-y-6">
                            <h3 className="text-lg font-semibold">Performance Metrics</h3>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                {[
                                    { label: 'CPA', value: formatCurrency(mockCampaign.metrics.cpa) },
                                    { label: 'ROAS', value: `${mockCampaign.metrics.roas}x` },
                                    { label: 'Revenue', value: formatCurrency(mockCampaign.metrics.revenue) },
                                    { label: 'Conversion Rate', value: `${formatPercent(mockCampaign.metrics.conversions / mockCampaign.metrics.clicks)}` },
                                ].map((m, i) => (
                                    <Card key={i}>
                                        <CardContent className="pt-6 text-center">
                                            <p className="text-sm text-slate-500">{m.label}</p>
                                            <p className="text-3xl font-bold text-slate-900 dark:text-white mt-1">{m.value}</p>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </div>
                    )}

                    {activeTab === 'creative' && (
                        <div className="space-y-6">
                            <h3 className="text-lg font-semibold">Creative Assets</h3>
                            <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                <h4 className="font-medium mb-3">Primary Text</h4>
                                <p className="text-slate-600 dark:text-slate-400">{mockCampaign.creative.primary_text}</p>
                            </div>
                            <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                <h4 className="font-medium mb-3">Headline</h4>
                                <p className="font-semibold text-lg">{mockCampaign.creative.headline}</p>
                            </div>
                            <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                <h4 className="font-medium mb-3">Description</h4>
                                <p className="text-slate-600 dark:text-slate-400">{mockCampaign.creative.description}</p>
                            </div>
                            <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                <h4 className="font-medium mb-3">Call to Action</h4>
                                <Badge variant="info">{mockCampaign.creative.call_to_action}</Badge>
                            </div>
                            <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                <h4 className="font-medium mb-3">Format</h4>
                                <Badge variant="outline" className="text-sm capitalize">{mockCampaign.creative.format.replace('_', ' ')}</Badge>
                            </div>
                        </div>
                    )}

                    {activeTab === 'targeting' && (
                        <div className="space-y-6">
                            <h3 className="text-lg font-semibold">Target Audience Details</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                    <h4 className="font-medium mb-3">Locations</h4>
                                    <ul className="space-y-1">
                                        {mockCampaign.targeting.locations.map((loc, i) => (
                                            <li key={i} className="text-sm flex items-center gap-2">
                                                <MapPin className="w-4 h-4 text-slate-400" />
                                                {loc}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                                <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                    <h4 className="font-medium mb-3">Age & Gender</h4>
                                    <dl className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Min Age</dt>
                                            <dd className="font-medium">{mockCampaign.targeting.age_min}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Max Age</dt>
                                            <dd className="font-medium">{mockCampaign.targeting.age_max}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Genders</dt>
                                            <dd className="font-medium capitalize">{mockCampaign.targeting.genders.join(', ')}</dd>
                                        </div>
                                    </dl>
                                </div>
                                <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                    <h4 className="font-medium mb-3">Interests</h4>
                                    <div className="flex flex-wrap gap-2">
                                        {mockCampaign.targeting.interests.map((interest, i) => (
                                            <Badge key={i} variant="outline" className="text-sm">{interest}</Badge>
                                        ))}
                                    </div>
                                </div>
                                <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                    <h4 className="font-medium mb-3">UTM Parameters</h4>
                                    <dl className="space-y-2 text-sm font-mono text-xs">
                                        {Object.entries(mockCampaign.utm).map(([key, value]) => (
                                            <div key={key} className="flex justify-between">
                                                <dt className="text-slate-500 capitalize">{key}</dt>
                                                <dd className="font-medium">{value}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </div>
                            </div>
                        </div>
                    )}

                    {activeTab === 'settings' && (
                        <div className="space-y-6">
                            <h3 className="text-lg font-semibold">Campaign Settings</h3>
                            <div className="space-y-4">
                                <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                    <h4 className="font-medium mb-3">UTM Parameters</h4>
                                    <dl className="space-y-2 text-sm font-mono text-xs">
                                        {Object.entries(mockCampaign.utm).map(([key, value]) => (
                                            <div key={key} className="flex justify-between">
                                                <dt className="text-slate-500 capitalize">{key}</dt>
                                                <dd className="font-medium">{value}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </div>
                                <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                    <h4 className="font-medium mb-3">Tracking</h4>
                                    <dl className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Pixel ID</dt>
                                            <dd className="font-medium font-mono">••••••••••</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Conversion API</dt>
                                            <dd className="font-medium text-green-600">Connected</dd>
                                        </div>
                                    </dl>
                                </div>
                                <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                    <h4 className="font-medium mb-3">Advanced</h4>
                                    <dl className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Bid Strategy</dt>
                                            <dd className="font-medium">Lowest Cost</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-slate-500">Optimization Goal</dt>
                                            <dd className="font-medium">Conversions</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

function CopyIcon({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>;
}

function CheckCircle({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
}

function MapPin({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>;
}
