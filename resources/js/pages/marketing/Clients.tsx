import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search, MoreVertical, Edit, Trash2, ArrowLeft, Eye, Pause, Play } from 'lucide-react';
import { Button } from '@components/ui/Button';
import { Input } from '@components/ui/Input';
import { Select } from '@components/ui/Select';
import { Table } from '@components/ui/Table';
import { Card, CardHeader, CardContent } from '@components/ui/Card';
import { Badge } from '@components/ui/Badge';
import { Avatar } from '@components/ui/Avatar';
import { Dropdown } from '@components/ui/Dropdown';
import { cn, formatRelativeTime } from '@utils';
import toast from 'react-hot-toast';

const mockClients = [
    { id: 1, name: 'TechStart Inc.', email: 'contact@techstart.io', phone: '+1 (555) 123-4567', website: 'techstart.io', industry: 'SaaS', company_size: '50-200', status: 'active', assigned_to: 'John Doe', tags: ['enterprise', 'high-value'], created_at: '2023-06-15', updated_at: '2024-01-15' },
    { id: 2, name: 'FashionForward', email: 'hello@fashionforward.com', phone: '+1 (555) 987-6543', website: 'fashionforward.com', industry: 'E-commerce', company_size: '200-500', status: 'active', assigned_to: 'Sarah Johnson', tags: ['retail', 'seasonal'], created_at: '2023-03-20', updated_at: '2024-01-14' },
    { id: 3, name: 'HealthPlus', email: 'info@healthplus.com', phone: '+1 (555) 456-7890', website: 'healthplus.com', industry: 'Healthcare', company_size: '1000+', status: 'active', assigned_to: 'Mike Chen', tags: ['healthcare', 'compliance'], created_at: '2023-09-10', updated_at: '2024-01-13' },
    { id: 4, name: 'EduTech Solutions', email: 'contact@edutech.io', phone: '+1 (555) 321-0987', website: 'edutech.io', industry: 'Education', company_size: '10-50', status: 'inactive', assigned_to: 'Emma Wilson', tags: ['startup', 'edtech'], created_at: '2023-11-05', updated_at: '2023-12-20' },
    { id: 5, name: 'GreenEnergy Corp', email: 'contact@greenenergy.com', phone: '+1 (555) 654-3210', website: 'greenenergy.com', industry: 'Energy', company_size: '500-1000', status: 'active', assigned_to: 'Alex Rivera', tags: ['sustainability', 'enterprise'], created_at: '2024-01-08', updated_at: '2024-01-15' },
];

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'prospect', label: 'Prospect' },
    { value: 'churned', label: 'Churned' },
];

const industryOptions = [
    { value: '', label: 'All Industries' },
    { value: 'SaaS', label: 'SaaS' },
    { value: 'E-commerce', label: 'E-commerce' },
    { value: 'Healthcare', label: 'Healthcare' },
    { value: 'Education', label: 'Education' },
    { value: 'Energy', label: 'Energy' },
    { value: 'Finance', label: 'Finance' },
];

const clientColumns = [
    { key: 'client', header: 'Client', accessor: (row: any) => (
        <div className="flex items-center gap-3">
            <Avatar name={row.name} size="sm" />
            <div>
                <p className="font-medium text-slate-900 dark:text-white">{row.name}</p>
                <p className="text-sm text-slate-500 dark:text-slate-400">{row.industry} • {row.company_size}</p>
            </div>
        </div>
    )},
    { key: 'contact', header: 'Contact', accessor: (row: any) => (
        <div>
            <p className="text-sm text-slate-600 dark:text-slate-400">{row.email}</p>
            <p className="text-xs text-slate-400">{row.phone}</p>
        </div>
    )},
    { key: 'industry', header: 'Industry', accessor: (row: any) => (
        <Badge variant="outline" className="text-xs">{row.industry}</Badge>
    )},
    { key: 'size', header: 'Size', accessor: (row: any) => row.company_size },
    { key: 'status', header: 'Status', accessor: (row: any) => (
        <Badge variant={row.status === 'active' ? 'success' : row.status === 'inactive' ? 'warning' : 'danger'}>{row.status}</Badge>
    )},
    { key: 'assigned', header: 'Assigned To', accessor: (row: any) => (
        <div className="flex items-center gap-2">
            <Avatar name={row.assigned_to} size="xs" />
            <span className="text-sm">{row.assigned_to}</span>
        </div>
    )},
    { key: 'tags', header: 'Tags', accessor: (row: any) => (
        <div className="flex flex-wrap gap-1">
            {row.tags.map((tag: string, i: number) => (
                <Badge key={i} variant="outline" className="text-xs">{tag}</Badge>
            ))}
        </div>
    )},
    { key: 'actions', header: 'Actions', accessor: (row: any) => (
        <Dropdown
            trigger={<MoreVertical className="w-5 h-5 text-slate-400 hover:text-slate-600" />}
            items={[
                { label: 'View Details', icon: <EyeIcon className="w-4 h-4" />, onClick: () => {} },
                { label: 'Edit', icon: <Edit className="w-4 h-4" />, onClick: () => {} },
                { label: row.status === 'active' ? 'Deactivate' : 'Activate', icon: row.status === 'active' ? <PauseIcon className="w-4 h-4" /> : <PlayIcon className="w-4 h-4" />, onClick: () => {} },
                { divider: true },
                { label: 'Delete', icon: <Trash2 className="w-4 h-4" />, onClick: () => {}, danger: true },
            ]}
            align="right"
        />
    )},
];

function EyeIcon({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>;
}

function PauseIcon({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
}

function PlayIcon({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>;
}

export function Clients() {
    const navigate = useNavigate();
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [industryFilter, setIndustryFilter] = useState('');

    const filteredClients = mockClients.filter(client => {
        const matchesSearch = client.name.toLowerCase().includes(search.toLowerCase()) ||
            client.email.toLowerCase().includes(search.toLowerCase());
        const matchesStatus = !statusFilter || client.status === statusFilter;
        const matchesIndustry = !industryFilter || client.industry === industryFilter;
        return matchesSearch && matchesStatus && matchesIndustry;
    });

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Clients</h1>
                    <p className="text-slate-500 dark:text-slate-400 mt-1">
                        Manage your client portfolio and relationships
                    </p>
                </div>
                <Link to="/clients/new">
                    <Button>
                        <Plus className="w-4 h-4 mr-2" />
                        Add Client
                    </Button>
                </Link>
            </div>

            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-col sm:flex-row gap-4 mb-6">
                        <div className="flex-1">
                            <Input
                                placeholder="Search clients..."
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                leftIcon={<Search className="w-5 h-5" />}
                            />
                        </div>
                        <Select
                            value={statusFilter}
                            onChange={setStatusFilter}
                            options={statusOptions}
                            placeholder="Filter by status"
                            className="w-40"
                        />
                        <Select
                            value={industryFilter}
                            onChange={setIndustryFilter}
                            options={industryOptions}
                            placeholder="Filter by industry"
                            className="w-44"
                        />
                    </div>

                    <Table
                        columns={clientColumns}
                        data={filteredClients}
                        keyExtractor={(row) => row.id.toString()}
                        sortable
                        pagination
                        pageSize={10}
                    />
                </CardContent>
            </Card>
        </div>
    );
}
