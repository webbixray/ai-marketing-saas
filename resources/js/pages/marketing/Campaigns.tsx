import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Search, Filter, MoreVertical, Edit, Trash2, Play, Pause, Eye } from 'lucide-react';
import { Button } from '@components/ui/Button';
import { Input } from '@components/ui/Input';
import { Select } from '@components/ui/Select';
import { Table } from '@components/ui/Table';
import { Card, CardHeader, CardContent } from '@components/ui/Card';
import { Badge, StatusBadge } from '@components/ui/Badge';
import { Dropdown } from '@components/ui/Dropdown';
import { Avatar } from '@components/ui/Avatar';
import { LoadingSpinner } from '@components/ui/LoadingSpinner';
import { Campaign } from '@types';
import { cn, formatCurrency, formatRelativeTime } from '@utils';

const mockCampaigns: Campaign[] = [
    {
        id: 1, name: 'Summer Sale 2024', platform: 'facebook', status: 'active',
        spend: 12450, budget: 25000, metrics: { impressions: 245000, clicks: 3200, ctr: 1.31, conversions: 156, cpa: 79.81, roas: 4.2, revenue: 52340 },
        client_id: 1, created_at: '2024-01-10T00:00:00Z', updated_at: '2024-01-15T00:00:00Z',
    } as Campaign,
    {
        id: 2, name: 'Q4 Holiday Campaign', platform: 'google', status: 'active',
        spend: 34200, budget: 50000, metrics: { impressions: 189000, clicks: 5400, ctr: 2.86, conversions: 342, cpa: 100.00, roas: 3.8, revenue: 129960 },
        client_id: 2, created_at: '2023-11-01T00:00:00Z', updated_at: '2024-01-15T00:00:00Z',
    } as Campaign,
    {
        id: 3, name: 'Brand Awareness', platform: 'instagram', status: 'paused',
        spend: 8900, budget: 15000, metrics: { impressions: 156000, clicks: 1200, ctr: 0.77, conversions: 45, cpa: 197.78, roas: 2.1, revenue: 18700 },
        client_id: 1, created_at: '2023-12-15T00:00:00Z', updated_at: '2024-01-10T00:00:00Z',
    } as Campaign,
    {
        id: 4, name: 'Product Launch', platform: 'linkedin', status: 'draft',
        spend: 0, budget: 30000, metrics: { impressions: 0, clicks: 0, ctr: 0, conversions: 0, cpa: 0, roas: 0, revenue: 0 },
        client_id: 3, created_at: '2024-01-14T00:00:00Z', updated_at: '2024-01-14T00:00:00Z',
    } as Campaign,
];

const platformOptions = [
    { value: '', label: 'All Platforms' },
    { value: 'facebook', label: 'Facebook' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'google', label: 'Google Ads' },
    { value: 'linkedin', label: 'LinkedIn' },
    { value: 'twitter', label: 'Twitter/X' },
    { value: 'tiktok', label: 'TikTok' },
];

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'paused', label: 'Paused' },
    { value: 'draft', label: 'Draft' },
    { value: 'completed', label: 'Completed' },
    { value: 'archived', label: 'Archived' },
];

const campaignColumns = [
    { key: 'name', header: 'Campaign', accessor: (row: Campaign) => (
        <div>
            <p className="font-medium text-slate-900 dark:text-white">{row.name}</p>
            <p className="text-xs text-slate-500 dark:text-slate-400 capitalize">{row.platform}</p>
        </div>
    )},
    { key: 'client', header: 'Client', accessor: (row: Campaign) => (
        <span className="text-sm text-slate-600 dark:text-slate-400">Client #{row.client_id}</span>
    )},
    { key: 'status', header: 'Status', accessor: (row: Campaign) => <StatusBadge status={row.status} /> },
    { key: 'spend', header: 'Spend', accessor: (row: Campaign) => formatCurrency(row.spend), align: 'right' },
    { key: 'budget', header: 'Budget', accessor: (row: Campaign) => formatCurrency(row.budget), align: 'right' },
    { key: 'budget_used', header: 'Budget Used', accessor: (row: Campaign) => {
        const pct = row.budget > 0 ? (row.spend / row.budget) * 100 : 0;
        return (
            <div className="w-32">
                <div className="h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                    <div 
                        className="h-full bg-blue-500 rounded-full transition-all"
                        style={{ width: `${Math.min(pct, 100)}%` }}
                    />
                </div>
                <span className="text-xs text-slate-500 dark:text-slate-400">{pct.toFixed(0)}%</span>
            </div>
        );
    }},
    { key: 'roas', header: 'ROAS', accessor: (row: Campaign) => `${row.metrics.roas}x`, align: 'right' },
    { key: 'revenue', header: 'Revenue', accessor: (row: Campaign) => formatCurrency(row.metrics.revenue), align: 'right' },
    { key: 'actions', header: 'Actions', accessor: (row: Campaign) => (
        <Dropdown
            trigger={<MoreVertical className="w-5 h-5 text-slate-400 hover:text-slate-600" />}
            items={[
                { label: 'View Details', icon: <Eye className="w-4 h-4" />, onClick: () => {} },
                { label: 'Edit', icon: <Edit className="w-4 h-4" />, onClick: () => {} },
                { label: row.status === 'active' ? 'Pause' : 'Launch', icon: row.status === 'active' ? <Pause className="w-4 h-4" /> : <Play className="w-4 h-4" />, onClick: () => {} },
                { divider: true },
                { label: 'Duplicate', icon: <CopyIcon className="w-4 h-4" />, onClick: () => {} },
                { label: 'Delete', icon: <Trash2 className="w-4 h-4" />, onClick: () => {}, danger: true },
            ]}
            align="right"
        />
    )},
];

function CopyIcon({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>;
}

export function Campaigns() {
    const [search, setSearch] = useState('');
    const [platformFilter, setPlatformFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [loading, setLoading] = useState(false);

    const filteredCampaigns = mockCampaigns.filter(campaign => {
        const matchesSearch = campaign.name.toLowerCase().includes(search.toLowerCase());
        const matchesPlatform = !platformFilter || campaign.platform === platformFilter;
        const matchesStatus = !statusFilter || campaign.status === statusFilter;
        return matchesSearch && matchesPlatform && matchesStatus;
    });

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Campaigns</h1>
                    <p className="text-slate-500 dark:text-slate-400 mt-1">
                        Manage and monitor all your marketing campaigns
                    </p>
                </div>
                <Link to="/campaigns/new">
                    <Button>
                        <Plus className="w-4 h-4 mr-2" />
                        Create Campaign
                    </Button>
                </Link>
            </div>

            {/* Filters */}
            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-col sm:flex-row gap-4">
                        <div className="flex-1">
                            <Input
                                placeholder="Search campaigns..."
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                leftIcon={<Search className="w-5 h-5" />}
                            />
                        </div>
                        <Select
                            value={platformFilter}
                            onChange={setPlatformFilter}
                            options={platformOptions}
                            placeholder="Filter by platform"
                            className="w-48"
                        />
                        <Select
                            value={statusFilter}
                            onChange={setStatusFilter}
                            options={statusOptions}
                            placeholder="Filter by status"
                            className="w-40"
                        />
                        <Button variant="outline">
                            <Filter className="w-4 h-4 mr-2" />
                            Advanced
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Campaigns Table */}
            <Card>
                <CardContent className="pt-6">
                    {loading ? (
                        <LoadingTable rows={5} columns={campaignColumns.length} />
                    ) : (
                        <Table
                            columns={campaignColumns}
                            data={filteredCampaigns}
                            keyExtractor={(row) => row.id.toString()}
                            sortable
                            pagination
                            pageSize={10}
                            emptyMessage="No campaigns found. Create your first campaign to get started!"
                        />
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
