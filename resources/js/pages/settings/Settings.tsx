import { useState } from 'react';
import { Tabs, TabPanel } from '@components/ui/Tabs';
import { Profile } from './Profile';
import { Team } from './Team';
import { Billing } from './Billing';
import { APIKeys } from './APIKeys';
import { Notifications } from './Notifications';
import { Card, CardHeader, CardContent } from '@components/ui/Card';
import Button from '@components/ui/Button';

const tabs = [
    { id: 'profile', label: 'Profile', icon: <UserIcon className="w-4 h-4" /> },
    { id: 'team', label: 'Team', icon: <UsersIcon className="w-4 h-4" /> },
    { id: 'billing', label: 'Billing', icon: <CreditCardIcon className="w-4 h-4" /> },
    { id: 'api-keys', label: 'API Keys', icon: <KeyIcon className="w-4 h-4" /> },
    { id: 'notifications', label: 'Notifications', icon: <BellIcon className="w-4 h-4" /> },
];

export function Settings() {
    const [activeTab, setActiveTab] = useState('profile');

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Settings</h1>
                <p className="text-slate-500 dark:text-slate-400 mt-1">
                    Manage your account, team, billing, and preferences
                </p>
            </div>

            <Card>
                <CardHeader>
                    <Tabs tabs={tabs} activeTab={activeTab} onChange={setActiveTab} variant="pills" />
                </CardHeader>
                <CardContent className="pt-4">
                    <TabPanel id="profile" activeTab={activeTab}>
                        <Profile />
                    </TabPanel>
                    <TabPanel id="team" activeTab={activeTab}>
                        <Team />
                    </TabPanel>
                    <TabPanel id="billing" activeTab={activeTab}>
                        <Billing />
                    </TabPanel>
                    <TabPanel id="api-keys" activeTab={activeTab}>
                        <APIKeys />
                    </TabPanel>
                    <TabPanel id="notifications" activeTab={activeTab}>
                        <Notifications />
                    </TabPanel>
                </CardContent>
            </Card>
        </div>
    );
}

// Icons
function UserIcon({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>;
}

function UsersIcon({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>;
}

function CreditCardIcon({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>;
}

function KeyIcon({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A1 1 0 0111 5H13a1 1 0 011 1v2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2a1 1 0 01-1-1H11a1 1 0 01-1-1V7a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1z" /></svg>;
}

function BellIcon({ className }: { className?: string }) {
    return <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>;
}
