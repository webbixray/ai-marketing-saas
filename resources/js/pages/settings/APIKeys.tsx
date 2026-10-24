import { useState } from 'react';
import { Plus, Copy, Eye, EyeOff, Trash2, MoreHorizontal, Key, Calendar, Clock } from 'lucide-react';
import Button from '@components/ui/Button';
import { Input } from '@components/ui/Input';
import { Select } from '@components/ui/Select';
import { Table } from '@components/ui/Table';
import { Card, CardHeader, CardContent } from '@components/ui/Card';
import { Badge } from '@components/ui/Badge';
import { Modal } from '@components/ui/Modal';
import { Dropdown } from '@components/ui/Dropdown';
import { cn, formatRelativeTime } from '@utils';
import toast from 'react-hot-toast';

interface APIKey {
    id: string;
    name: string;
    key: string;
    prefix: string;
    scopes: string[];
    lastUsed: string | null;
    expiresAt: string | null;
    createdAt: string;
    createdBy: string;
}

const mockKeys: APIKey[] = [
    { id: '1', name: 'Production API', key: 'sk_live_abc123...', prefix: 'sk_live_', scopes: ['campaigns:read', 'campaigns:write', 'analytics:read'], lastUsed: '2024-01-15T10:30:00Z', expiresAt: null, createdAt: '2023-11-01', createdBy: 'John Doe' },
    { id: '2', name: 'Development', key: 'sk_test_xyz789...', prefix: 'sk_test_', scopes: ['campaigns:read', 'analytics:read'], lastUsed: '2024-01-14T14:20:00Z', expiresAt: '2024-12-31', createdAt: '2023-12-01', createdBy: 'Sarah Johnson' },
    { id: '3', name: 'Analytics Pipeline', key: 'sk_live_def456...', prefix: 'sk_live_', scopes: ['analytics:read', 'reports:read', 'reports:write'], lastUsed: '2024-01-15T08:00:00Z', expiresAt: null, createdAt: '2024-01-01', createdBy: 'Mike Chen' },
];

const scopeOptions = [
    { value: 'campaigns:read', label: 'Campaigns - Read' },
    { value: 'campaigns:write', label: 'Campaigns - Write' },
    { value: 'analytics:read', label: 'Analytics - Read' },
    { value: 'analytics:write', label: 'Analytics - Write' },
    { value: 'reports:read', label: 'Reports - Read' },
    { value: 'reports:write', label: 'Reports - Write' },
    { value: 'clients:read', label: 'Clients - Read' },
    { value: 'clients:write', label: 'Clients - Write' },
    { value: 'billing:read', label: 'Billing - Read' },
    { value: 'admin:users', label: 'Admin - Users' },
];

export function APIKeys() {
    const [modalOpen, setModalOpen] = useState(false);
    const [keyName, setKeyName] = useState('');
    const [keyScopes, setKeyScopes] = useState<string[]>([]);
    const [keyExpires, setKeyExpires] = useState('');
    const [loading, setLoading] = useState(false);

    const keyColumns = [
        { key: 'name', header: 'Name', accessor: (row: APIKey) => (
            <div>
                <p className="font-medium text-slate-900 dark:text-white">{row.name}</p>
                <p className="text-sm text-slate-500 dark:text-slate-400 font-mono">{row.prefix}••••••••</p>
            </div>
        )},
        { key: 'scopes', header: 'Scopes', accessor: (row: APIKey) => (
            <div className="flex flex-wrap gap-1">
                {row.scopes.map(scope => (
                    <Badge key={scope} variant="outline" className="text-xs">{scope}</Badge>
                ))}
            </div>
        )},
        { key: 'lastUsed', header: 'Last Used', accessor: (row: APIKey) => 
            row.lastUsed ? formatRelativeTime(row.lastUsed) : 'Never' 
        },
        { key: 'expires', header: 'Expires', accessor: (row: APIKey) => 
            row.expiresAt ? row.expiresAt : 'Never' 
        },
        { key: 'created', header: 'Created', accessor: (row: APIKey) => row.createdAt },
        { key: 'actions', header: 'Actions', accessor: (row: APIKey) => (
            <Dropdown
                trigger={<MoreHorizontal className="w-5 h-5 text-slate-400 hover:text-slate-600" />}
                items={[
                    { label: 'Copy Key', icon: <Copy className="w-4 h-4" />, onClick: () => copyKey(row.key) },
                    { label: 'View Full Key', icon: <Eye className="w-4 h-4" />, onClick: () => viewKey(row.key) },
                    { divider: true },
                    { label: 'Regenerate', icon: <Key className="w-4 h-4" />, onClick: () => regenerateKey(row.id) },
                    { label: 'Delete', icon: <Trash2 className="w-4 h-4" />, onClick: () => deleteKey(row.id), danger: true },
                ]}
                align="right"
            />
        )},
    ];

    const handleCreateKey = async () => {
        if (!keyName || keyScopes.length === 0) return;
        setLoading(true);
        try {
            await new Promise(resolve => setTimeout(resolve, 1000));
            // In real app, call API
            const newKey = `sk_${keyScopes.includes('campaigns:write') ? 'live' : 'test'}_${Math.random().toString(36).substr(2, 24)}`;
            toast.success(`API Key created! Key: ${newKey}`);
            setModalOpen(false);
            setKeyName('');
            setKeyScopes([]);
            setKeyExpires('');
        } catch {
            toast.error('Failed to create API key');
        } finally {
            setLoading(false);
        }
    };

    const copyKey = (key: string) => {
        navigator.clipboard.writeText(key);
        toast.success('Key copied to clipboard!');
    };

    const viewKey = (key: string) => {
        // In real app, show in modal
        toast.success(`Full key: ${key}`);
    };

    const regenerateKey = (id: string) => {
        toast.success('Key regenerated! New key copied to clipboard.');
    };

    const deleteKey = (id: string) => {
        toast.success('API key deleted');
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">API Keys</h1>
                    <p className="text-slate-500 dark:text-slate-400 mt-1">
                        Manage API keys for programmatic access to your account
                    </p>
                </div>
                <Button onClick={() => setModalOpen(true)}>
                    <Plus className="w-4 h-4 mr-2" />
                    Create API Key
                </Button>
            </div>

            <Card>
                <CardContent className="pt-6">
                    <Table
                        columns={keyColumns}
                        data={mockKeys}
                        keyExtractor={(row) => row.id}
                        sortable
                        pagination
                        pageSize={10}
                    />
                </CardContent>
            </Card>

            {/* Create Key Modal */}
            <Modal
                isOpen={modalOpen}
                onClose={() => { setModalOpen(false); setKeyName(''); setKeyScopes([]); setKeyExpires(''); }}
                title="Create API Key"
                description="Generate a new API key with specific permissions"
            >
                <form onSubmit={e => { e.preventDefault(); handleCreateKey(); }} className="space-y-4">
                    <Input
                        label="Key Name"
                        placeholder="e.g., Production API, CI/CD Pipeline"
                        value={keyName}
                        onChange={e => setKeyName(e.target.value)}
                        required
                        autoFocus
                    />
                    <div>
                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            Scopes (Permissions)
                        </label>
                        <div className="flex flex-wrap gap-2">
                            {scopeOptions.map(scope => (
                                <label key={scope.value} className="inline-flex items-center gap-2 px-3 py-1.5 border border-slate-200 dark:border-slate-600 rounded-lg cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800">
                                    <input
                                        type="checkbox"
                                        value={scope.value}
                                        checked={keyScopes.includes(scope.value)}
                                        onChange={e => setKeyScopes(prev => 
                                            e.target.checked ? [...prev, scope.value] : prev.filter(s => s !== scope.value)
                                        )}
                                        className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                    />
                                    <span className="text-sm">{scope.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            Expiration (Optional)
                        </label>
                        <Select
                            value={keyExpires}
                            onChange={setKeyExpires}
                            options={[
                                { value: '', label: 'Never expires' },
                                { value: '30', label: '30 days' },
                                { value: '90', label: '90 days' },
                                { value: '365', label: '1 year' },
                            ]}
                            placeholder="Select expiration"
                        />
                    </div>
                    <div className="flex justify-end gap-3 pt-4">
                        <Button variant="outline" onClick={() => { setModalOpen(false); setKeyName(''); setKeyScopes([]); setKeyExpires(''); }}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={loading}>
                            Create API Key
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
