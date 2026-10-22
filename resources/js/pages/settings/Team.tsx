import { useState } from 'react';
import { Plus, Search, MoreVertical, Edit, Trash2, Shield, User, Mail, Send } from 'lucide-react';
import { Button } from '@components/ui/Button';
import { Input } from '@components/ui/Input';
import { Select } from '@components/ui/Select';
import { Table } from '@components/ui/Table';
import { Card, CardHeader, CardContent } from '@components/ui/Card';
import { Badge } from '@components/ui/Badge';
import { Avatar } from '@components/ui/Avatar';
import { Modal } from '@components/ui/Modal';
import { Dropdown } from '@components/ui/Dropdown';
import { cn } from '@utils';

interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'member' | 'viewer';
    status: 'active' | 'invited' | 'disabled';
    avatar?: string;
    joinedAt: string;
    lastActive: string;
}

const mockMembers: TeamMember[] = [
    { id: 1, name: 'John Doe', email: 'john@example.com', role: 'owner', status: 'active', avatar: undefined, joinedAt: '2023-01-15', lastActive: '2024-01-15' },
    { id: 2, name: 'Sarah Johnson', email: 'sarah@example.com', role: 'admin', status: 'active', avatar: undefined, joinedAt: '2023-03-20', lastActive: '2024-01-14' },
    { id: 3, name: 'Mike Chen', email: 'mike@example.com', role: 'member', status: 'active', avatar: undefined, joinedAt: '2023-06-10', lastActive: '2024-01-15' },
    { id: 4, name: 'Emma Wilson', email: 'emma@example.com', role: 'member', status: 'invited', avatar: undefined, joinedAt: '2024-01-10', lastActive: 'Never' },
    { id: 5, name: 'Alex Rivera', email: 'alex@example.com', role: 'viewer', status: 'active', avatar: undefined, joinedAt: '2023-09-01', lastActive: '2024-01-13' },
];

const roleOptions = [
    { value: 'owner', label: 'Owner' },
    { value: 'admin', label: 'Admin' },
    { value: 'member', label: 'Member' },
    { value: 'viewer', label: 'Viewer' },
];

const memberColumns = [
    { key: 'member', header: 'Team Member', accessor: (row: TeamMember) => (
        <div className="flex items-center gap-3">
            <Avatar name={row.name} size="sm" />
            <div>
                <p className="font-medium text-slate-900 dark:text-white">{row.name}</p>
                <p className="text-sm text-slate-500 dark:text-slate-400">{row.email}</p>
            </div>
        </div>
    )},
    { key: 'role', header: 'Role', accessor: (row: TeamMember) => (
        <Badge variant={row.role === 'owner' ? 'success' : row.role === 'admin' ? 'info' : row.role === 'member' ? 'default' : 'outline'}>
            {row.role.charAt(0).toUpperCase() + row.role.slice(1)}
        </Badge>
    )},
    { key: 'status', header: 'Status', accessor: (row: TeamMember) => (
        <Badge variant={row.status === 'active' ? 'success' : row.status === 'invited' ? 'warning' : 'danger'}>
            {row.status.charAt(0).toUpperCase() + row.status.slice(1)}
        </Badge>
    )},
    { key: 'joined', header: 'Joined', accessor: (row: TeamMember) => row.joinedAt },
    { key: 'lastActive', header: 'Last Active', accessor: (row: TeamMember) => row.lastActive },
    { key: 'actions', header: 'Actions', accessor: (row: TeamMember) => (
        <Dropdown
            trigger={<MoreVertical className="w-5 h-5 text-slate-400 hover:text-slate-600" />}
            items={[
                { label: 'Edit Role', icon: <Edit className="w-4 h-4" />, onClick: () => {} },
                { label: row.status === 'invited' ? 'Resend Invite' : 'Remove', icon: row.status === 'invited' ? <Send className="w-4 h-4" /> : <Trash2 className="w-4 h-4" />, onClick: () => {}, danger: row.status !== 'invited' },
            ]}
            align="right"
        />
    )},
];

export function Team() {
    const [search, setSearch] = useState('');
    const [inviteModalOpen, setInviteModalOpen] = useState(false);
    const [inviteEmail, setInviteEmail] = useState('');
    const [inviteRole, setInviteRole] = useState<'admin' | 'member' | 'viewer'>('member');
    const [loading, setLoading] = useState(false);

    const handleInvite = async () => {
        if (!inviteEmail) return;
        setLoading(true);
        try {
            await new Promise(resolve => setTimeout(resolve, 1000));
            setInviteModalOpen(false);
            setInviteEmail('');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Team Members</h1>
                    <p className="text-slate-500 dark:text-slate-400 mt-1">
                        Manage team members and their permissions
                    </p>
                </div>
                <Button onClick={() => setInviteModalOpen(true)}>
                    <Plus className="w-4 h-4 mr-2" />
                    Invite Member
                </Button>
            </div>

            <Card>
                <CardContent className="pt-6">
                    <Table
                        columns={memberColumns}
                        data={mockMembers}
                        keyExtractor={(row) => row.id.toString()}
                        sortable
                        searchable
                        filterable
                        pagination
                        pageSize={10}
                    />
                </CardContent>
            </Card>

            {/* Invite Modal */}
            <Modal
                isOpen={inviteModalOpen}
                onClose={() => { setInviteModalOpen(false); setInviteEmail(''); }}
                title="Invite Team Member"
                description="Enter the email address of the person you'd like to invite"
            >
                <form onSubmit={e => { e.preventDefault(); handleInvite(); }} className="space-y-4">
                    <Input
                        label="Email Address"
                        type="email"
                        placeholder="colleague@example.com"
                        value={inviteEmail}
                        onChange={e => setInviteEmail(e.target.value)}
                        required
                        autoFocus
                    />
                    <Select
                        label="Role"
                        value={inviteRole}
                        onChange={setInviteRole}
                        options={roleOptions.filter(r => r.value !== 'owner')}
                        placeholder="Select role"
                    />
                    <div className="flex justify-end gap-3 pt-4">
                        <Button variant="outline" onClick={() => { setInviteModalOpen(false); setInviteEmail(''); }}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={loading}>
                            Send Invitation
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
