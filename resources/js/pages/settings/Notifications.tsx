import { useState } from 'react';
import { Bell, Mail, BellOff, Save, CheckCircle } from 'lucide-react';
import Button from '@components/ui/Button';
import { Card, CardHeader, CardContent } from '@components/ui/Card';
import { Badge } from '@components/ui/Badge';
import { Switch } from '@components/ui/Switch';
import { Input } from '@components/ui/Input';
import { Toast } from 'react-hot-toast';

interface NotificationPreference {
    id: string;
    category: string;
    name: string;
    description: string;
    email: boolean;
    push: boolean;
    inApp: boolean;
}

const preferences: NotificationPreference[] = [
    { id: 'campaign_launched', category: 'Campaigns', name: 'Campaign Launched', description: 'When a campaign goes live', email: true, push: true, inApp: true },
    { id: 'campaign_paused', category: 'Campaigns', name: 'Campaign Paused', description: 'When a campaign is paused', email: true, push: false, inApp: true },
    { id: 'budget_alert', category: 'Campaigns', name: 'Budget Alert', description: 'When campaign reaches 80% of budget', email: true, push: true, inApp: true },
    { id: 'campaign_completed', category: 'Campaigns', name: 'Campaign Completed', description: 'When a campaign ends', email: false, push: false, inApp: true },
    { id: 'client_added', category: 'Clients', name: 'New Client', description: 'When a new client is added', email: true, push: false, inApp: true },
    { id: 'report_ready', category: 'Reports', name: 'Report Ready', description: 'When a scheduled report is generated', email: true, push: false, inApp: true },
    { id: 'anomaly_detected', category: 'Analytics', name: 'Anomaly Detected', description: 'When unusual activity is detected', email: true, push: true, inApp: true },
    { id: 'forecast_alert', category: 'Analytics', name: 'Forecast Alert', description: 'When forecasts predict significant changes', email: false, push: false, inApp: true },
    { id: 'team_invite', category: 'Team', name: 'Team Invitation', description: 'When invited to a team', email: true, push: true, inApp: true },
    { id: 'mention', category: 'Team', name: 'Mentions', description: 'When mentioned in comments', email: true, push: true, inApp: true },
    { id: 'security_alert', category: 'Security', name: 'Security Alerts', description: 'Suspicious login attempts, etc.', email: true, push: true, inApp: true },
    { id: 'billing_reminder', category: 'Billing', name: 'Billing Reminders', description: 'Upcoming payments, failed charges', email: true, push: false, inApp: true },
    { id: 'feature_announcement', category: 'Product', name: 'Feature Announcements', description: 'New features and updates', email: false, push: false, inApp: true },
    { id: 'maintenance', category: 'Product', name: 'Maintenance Windows', description: 'Scheduled maintenance notifications', email: true, push: false, inApp: true },
];

const categories = [...new Set(preferences.map(p => p.category))];

export function Notifications() {
    const [saving, setSaving] = useState(false);

    const handleSave = async () => {
        setSaving(true);
        try {
            await new Promise(resolve => setTimeout(resolve, 1000));
            toast.success('Notification preferences saved!');
        } catch {
            toast.error('Failed to save preferences');
        } finally {
            setSaving(false);
        }
    };

    const toggleAll = (field: 'email' | 'push' | 'inApp', value: boolean) => {
        preferences.forEach(p => p[field] = value);
    };

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Notifications</h1>
                    <p className="text-slate-500 dark:text-slate-400 mt-1">
                        Configure how and when you receive notifications
                    </p>
                </div>
                <Button onClick={handleSave} loading={saving}>
                    <Save className="w-4 h-4 mr-2" />
                    Save Changes
                </Button>
            </div>

            {/* Global Toggles */}
            <Card>
                <CardHeader title="Global Notification Channels" />
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                        <Mail className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div>
                                        <h4 className="font-medium">Email</h4>
                                        <p className="text-sm text-slate-500">Receive notifications via email</p>
                                    </div>
                                </div>
                                <Switch checked={preferences.some(p => p.email)} onChange={e => toggleAll('email', e.target.checked)} />
                            </div>
                        </div>
                        <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                        <Bell className="w-5 h-5 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <h4 className="font-medium">Push</h4>
                                        <p className="text-sm text-slate-500">Receive push notifications</p>
                                    </div>
                                </div>
                                <Switch checked={preferences.some(p => p.push)} onChange={e => toggleAll('push', e.target.checked)} />
                            </div>
                        </div>
                        <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <div className="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                                        <BellOff className="w-5 h-5 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <div>
                                        <h4 className="font-medium">In-App</h4>
                                        <p className="text-sm text-slate-500">Show in notification center</p>
                                    </div>
                                </div>
                                <Switch checked={preferences.some(p => p.inApp)} onChange={e => toggleAll('inApp', e.target.checked)} />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Preferences by Category */}
            <div className="space-y-6">
                {categories.map(category => {
                    const categoryPrefs = preferences.filter(p => p.category === category);
                    return (
                        <Card key={category}>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <h3 className="text-lg font-semibold capitalize">{category}</h3>
                                <div className="flex items-center gap-2 text-sm text-slate-500">
                                    <span>Email</span>
                                    <Switch checked={categoryPrefs.every(p => p.email)} onChange={e => categoryPrefs.forEach(p => p.email = e.target.checked)} size="sm" />
                                    <span>Push</span>
                                    <Switch checked={categoryPrefs.every(p => p.push)} onChange={e => categoryPrefs.forEach(p => p.push = e.target.checked)} size="sm" />
                                    <span>In-App</span>
                                    <Switch checked={categoryPrefs.every(p => p.inApp)} onChange={e => categoryPrefs.forEach(p => p.inApp = e.target.checked)} size="sm" />
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {categoryPrefs.map(pref => (
                                        <div key={pref.id} className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-3">
                                                    <h4 className="font-medium text-slate-900 dark:text-white">{pref.name}</h4>
                                                    <Badge variant="outline" className="text-xs capitalize">{pref.category.toLowerCase()}</Badge>
                                                </div>
                                                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">{pref.description}</p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <label className="flex items-center gap-2 text-sm">
                                                    <Switch checked={pref.email} onChange={e => pref.email = e.target.checked} size="sm" />
                                                    <span>Email</span>
                                                </label>
                                                <label className="flex items-center gap-2 text-sm">
                                                    <Switch checked={pref.push} onChange={e => pref.push = e.target.checked} size="sm" />
                                                    <span>Push</span>
                                                </label>
                                                <label className="flex items-center gap-2 text-sm">
                                                    <Switch checked={pref.inApp} onChange={e => pref.inApp = e.target.checked} size="sm" />
                                                    <span>In-App</span>
                                                </label>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            {/* Digest Settings */}
            <Card>
                <CardHeader title="Digest Settings" description="Configure summary emails" />
                <CardContent className="space-y-4">
                    <div className="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                        <div>
                            <h4 className="font-medium">Daily Digest</h4>
                            <p className="text-sm text-slate-500">Receive a daily summary of all notifications at 8:00 AM</p>
                        </div>
                        <Switch checked={false} onChange={() => {}} />
                    </div>
                    <div className="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                        <div>
                            <h4 className="font-medium">Weekly Digest</h4>
                            <p className="text-sm text-slate-500">Receive a weekly summary every Monday at 9:00 AM</p>
                        </div>
                        <Switch checked={true} onChange={() => {}} />
                    </div>
                    <div className="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
                        <div>
                            <h4 className="font-medium">Marketing Emails</h4>
                            <p className="text-sm text-slate-500">Receive product updates, tips, and best practices</p>
                        </div>
                        <Switch checked={true} onChange={() => {}} />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

// Simple Switch component
function Switch({ checked, onChange, size = 'md', disabled }: { checked: boolean; onChange: (e: React.ChangeEvent<HTMLInputElement>) => void; size?: 'sm' | 'md' | 'lg'; disabled?: boolean }) {
    const sizeClasses = {
        sm: 'w-8 h-5',
        md: 'w-11 h-6',
        lg: 'w-14 h-7',
    };

    return (
        <label className="relative inline-flex items-center cursor-pointer">
            <input
                type="checkbox"
                checked={checked}
                onChange={onChange}
                disabled={disabled}
                className="peer sr-only"
            />
            <div className={cn(
                'relative rounded-full border-2 transition-colors',
                sizeClasses[size],
                checked
                    ? 'bg-blue-600 border-blue-600'
                    : 'bg-slate-200 dark:bg-slate-700 border-slate-300 dark:border-slate-600',
                disabled && 'opacity-50 cursor-not-allowed'
            )}>
                <span className={cn(
                    'absolute top-0.5 left-0.5 bg-white rounded-full transition-transform',
                    size === 'sm' && 'w-4 h-4',
                    size === 'md' && 'w-5 h-5',
                    size === 'lg' && 'w-6 h-6',
                    checked ? 'translate-x-full' : 'translate-x-0'
                )} />
            </div>
        </label>
    );
}
