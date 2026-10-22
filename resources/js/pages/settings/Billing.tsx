import { useState } from 'react';
import { CreditCard, Zap, Download, Settings, ArrowUpRight, CheckCircle } from 'lucide-react';
import { Button } from '@components/ui/Button';
import { Card, CardHeader, CardContent, CardFooter } from '@components/ui/Card';
import { Badge } from '@components/ui/Badge';
import { Tabs, TabPanel } from '@components/ui/Tabs';
import { cn } from '@utils';

const plans = [
    {
        id: 'starter',
        name: 'Starter',
        price: 49,
        interval: 'month',
        features: ['Up to 5 campaigns', 'Basic analytics', 'Email support', '1 team member', '5GB storage'],
        highlighted: false,
    },
    {
        id: 'professional',
        name: 'Professional',
        price: 149,
        interval: 'month',
        features: ['Up to 25 campaigns', 'Advanced analytics', 'Priority support', '5 team members', '25GB storage', 'AI copy generator', 'A/B testing'],
        highlighted: true,
    },
    {
        id: 'enterprise',
        name: 'Enterprise',
        price: 499,
        interval: 'month',
        features: ['Unlimited campaigns', 'Custom analytics', '24/7 dedicated support', 'Unlimited team members', 'Unlimited storage', 'All AI features', 'Custom integrations', 'SLA guarantee'],
        highlighted: false,
    },
];

const invoices = [
    { id: 'INV-2024-001', date: '2024-01-01', amount: 149, status: 'paid', pdf: '#' },
    { id: 'INV-2023-012', date: '2023-12-01', amount: 149, status: 'paid', pdf: '#' },
    { id: 'INV-2023-011', date: '2023-11-01', amount: 149, status: 'paid', pdf: '#' },
    { id: 'INV-2023-010', date: '2023-10-01', amount: 49, status: 'paid', pdf: '#' },
];

const paymentMethods = [
    { id: 'pm_1', type: 'card', brand: 'Visa', last4: '4242', expiry: '12/25', default: true },
    { id: 'pm_2', type: 'card', brand: 'Mastercard', last4: '5555', expiry: '06/26', default: false },
];

export function Billing() {
    const [activeTab, setActiveTab] = useState('subscription');

    const tabs = [
        { id: 'subscription', label: 'Subscription', icon: <Zap className="w-4 h-4" /> },
        { id: 'invoices', label: 'Invoices', icon: <Download className="w-4 h-4" /> },
        { id: 'payment-methods', label: 'Payment Methods', icon: <CreditCard className="w-4 h-4" /> },
        { id: 'settings', label: 'Billing Settings', icon: <Settings className="w-4 h-4" /> },
    ];

    return (
        <Tabs tabs={tabs} activeTab={activeTab} onChange={setActiveTab} variant="default">
            <TabPanel id="subscription" activeTab={activeTab}>
                <div className="space-y-6">
                    {/* Current Plan */}
                    <Card className="border-blue-500 dark:border-blue-500">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <h3 className="text-lg font-semibold">Current Plan</h3>
                                <p className="text-slate-500 dark:text-slate-400">Professional - Monthly billing</p>
                            </div>
                            <Badge variant="success">Active</Badge>
                        </CardHeader>
                        <CardContent className="pt-0">
                            <div className="flex items-center justify-between p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                <div>
                                    <p className="text-3xl font-bold text-blue-600 dark:text-blue-400">$149<span className="text-base font-normal text-slate-500">/month</span></p>
                                    <p className="text-sm text-slate-500">Billed monthly on the 1st</p>
                                </div>
                                <Button variant="outline">
                                    <ArrowUpRight className="w-4 h-4 mr-2" />
                                    Upgrade Plan
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Plan Comparison */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {plans.map(plan => (
                            <Card key={plan.id} className={cn(
                                'relative flex flex-col',
                                plan.highlighted && 'border-2 border-blue-500 dark:border-blue-500'
                            )}>
                                {plan.highlighted && (
                                    <div className="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-blue-500 text-white text-xs font-medium rounded-full">
                                        Most Popular
                                    </div>
                                )}
                                <CardHeader>
                                    <h3 className="text-lg font-semibold">{plan.name}</h3>
                                    <p className="text-3xl font-bold text-slate-900 dark:text-white mt-2">
                                        ${plan.price}<span className="text-base font-normal text-slate-500">/{plan.interval}</span>
                                    </p>
                                </CardHeader>
                                <CardContent className="flex-1 pt-0">
                                    <ul className="space-y-3">
                                        {plan.features.map((feature, i) => (
                                            <li key={i} className="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-400">
                                                <CheckCircle className="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                                                {feature}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                                <CardFooter>
                                    <Button 
                                        fullWidth 
                                        variant={plan.highlighted ? 'primary' : 'outline'}
                                        disabled={plan.id === 'professional'}
                                    >
                                        {plan.id === 'professional' ? 'Current Plan' : 'Select Plan'}
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                </div>
            </TabPanel>

            <TabPanel id="invoices" activeTab={activeTab}>
                <Card>
                    <CardHeader title="Invoice History" />
                    <CardContent className="pt-0">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b border-slate-200 dark:border-slate-700">
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Invoice</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Date</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Amount</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Status</th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {invoices.map(invoice => (
                                        <tr key={invoice.id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                            <td className="px-4 py-3 font-mono text-sm">{invoice.id}</td>
                                            <td className="px-4 py-3 text-sm">{invoice.date}</td>
                                            <td className="px-4 py-3 text-right font-medium">${invoice.amount.toFixed(2)}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant={invoice.status === 'paid' ? 'success' : 'warning'}>{invoice.status}</Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button variant="ghost" size="sm">Download</Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </TabPanel>

            <TabPanel id="payment-methods" activeTab={activeTab}>
                <div className="space-y-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <h3 className="text-lg font-semibold">Payment Methods</h3>
                            <Button size="sm">
                                <Plus className="w-4 h-4 mr-2" />
                                Add Payment Method
                            </Button>
                        </CardHeader>
                        <CardContent className="pt-0">
                            <div className="space-y-4">
                                {paymentMethods.map(method => (
                                    <div key={method.id} className="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
                                        <div className="flex items-center gap-4">
                                            <div className="w-12 h-8 bg-slate-100 dark:bg-slate-800 rounded flex items-center justify-center">
                                                <CreditCard className="w-6 h-6 text-slate-500" />
                                            </div>
                                            <div>
                                                <p className="font-medium">{method.brand} ending in {method.last4}</p>
                                                <p className="text-sm text-slate-500">Expires {method.expiry}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {method.default && <Badge variant="success">Default</Badge>}
                                            <Button variant="ghost" size="sm">Remove</Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </TabPanel>

            <TabPanel id="settings" activeTab={activeTab}>
                <Card>
                    <CardHeader title="Billing Settings" />
                    <CardContent className="space-y-6">
                        <div>
                            <h4 className="font-medium mb-4">Billing Information</h4>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <Input label="Company Name" placeholder="Acme Inc." />
                                <Input label="Tax ID / VAT Number" placeholder="US12345678" />
                            </div>
                        </div>
                        <div>
                            <h4 className="font-medium mb-4">Billing Address</h4>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <Input label="Street Address" placeholder="123 Main St" />
                                <Input label="City" placeholder="San Francisco" />
                                <Input label="State/Province" placeholder="CA" />
                                <Input label="Postal Code" placeholder="94105" />
                                <Input label="Country" placeholder="United States" />
                            </div>
                        </div>
                        <Button>Save Changes</Button>
                    </CardContent>
                </Card>
            </TabPanel>
        </Tabs>
    );
}
