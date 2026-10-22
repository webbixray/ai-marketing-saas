import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ArrowLeft, Sparkles, Target, DollarSign, Users, Image, Save, CheckCircle, ArrowRight } from 'lucide-react';
import { Button } from '@components/ui/Button';
import { Input } from '@components/ui/Input';
import { Select } from '@components/ui/Select';
import { Textarea } from '@components/ui/Input';
import { Card, CardHeader, CardContent, CardFooter } from '@components/ui/Card';
import { Tabs, TabPanel } from '@components/ui/Tabs';
import { Badge } from '@components/ui/Badge';
import { cn } from '@utils';
import toast from 'react-hot-toast';

const campaignSchema = z.object({
    name: z.string().min(3, 'Campaign name must be at least 3 characters'),
    platform: z.string().min(1, 'Select a platform'),
    objective: z.string().min(1, 'Select an objective'),
    budget_type: z.enum(['daily', 'lifetime']),
    budget: z.number().min(1, 'Budget must be at least $1'),
    currency: z.string().default('USD'),
    start_date: z.string().min(1, 'Start date is required'),
    end_date: z.string().optional(),
    targeting: z.object({
        locations: z.array(z.string()).optional(),
        age_min: z.number().min(13).optional(),
        age_max: z.number().max(65).optional(),
        genders: z.array(z.string()).optional(),
        interests: z.array(z.string()).optional(),
    }),
    creative: z.object({
        format: z.string().min(1, 'Select a format'),
        primary_text: z.string().optional(),
        headline: z.string().optional(),
        description: z.string().optional(),
        call_to_action: z.string().optional(),
    }),
});

type CampaignFormData = z.infer<typeof campaignSchema>;

const platformOptions = [
    { value: 'facebook', label: 'Facebook' },
    { value: 'instagram', label: 'Instagram' },
    { value: 'google', label: 'Google Ads' },
    { value: 'linkedin', label: 'LinkedIn' },
    { value: 'twitter', label: 'Twitter/X' },
    { value: 'tiktok', label: 'TikTok' },
    { value: 'youtube', label: 'YouTube' },
];

const objectiveOptions = [
    { value: 'awareness', label: 'Brand Awareness' },
    { value: 'traffic', label: 'Traffic' },
    { value: 'engagement', label: 'Engagement' },
    { value: 'leads', label: 'Lead Generation' },
    { value: 'sales', label: 'Sales/Conversions' },
    { value: 'app_installs', label: 'App Installs' },
];

const formatOptions = [
    { value: 'single_image', label: 'Single Image' },
    { value: 'carousel', label: 'Carousel' },
    { value: 'video', label: 'Video' },
    { value: 'collection', label: 'Collection' },
    { value: 'story', label: 'Story' },
];

const ctaOptions = [
    { value: 'learn_more', label: 'Learn More' },
    { value: 'shop_now', label: 'Shop Now' },
    { value: 'sign_up', label: 'Sign Up' },
    { value: 'download', label: 'Download' },
    { value: 'contact_us', label: 'Contact Us' },
    { value: 'book_now', label: 'Book Now' },
    { value: 'apply_now', label: 'Apply Now' },
];

const campaignTabs = [
    { id: 'basics', label: 'Basics', icon: <Target className="w-4 h-4" /> },
    { id: 'targeting', label: 'Targeting', icon: <Users className="w-4 h-4" /> },
    { id: 'creative', label: 'Creative', icon: <Image className="w-4 h-4" /> },
    { id: 'budget', label: 'Budget & Schedule', icon: <DollarSign className="w-4 h-4" /> },
    { id: 'review', label: 'Review', icon: <Save className="w-4 h-4" /> },
];

export function CampaignBuilder() {
    const navigate = useNavigate();
    const [activeTab, setActiveTab] = useState(0);
    const [isLoading, setIsLoading] = useState(false);
    const [aiGenerating, setAiGenerating] = useState(false);

    const {
        register,
        handleSubmit,
        watch,
        setValue,
        formState: { errors },
    } = useForm<CampaignFormData>({
        resolver: zodResolver(campaignSchema),
        defaultValues: {
            budget_type: 'daily',
            budget: 50,
            currency: 'USD',
            targeting: {
                locations: [],
                age_min: 18,
                age_max: 65,
                genders: [],
                interests: [],
            },
            creative: {
                format: 'single_image',
                primary_text: '',
                headline: '',
                description: '',
                call_to_action: 'learn_more',
            },
        },
    });

    const onSubmit = async (data: CampaignFormData) => {
        setIsLoading(true);
        try {
            await new Promise(resolve => setTimeout(resolve, 1000));
            toast.success('Campaign created successfully!');
            navigate('/campaigns');
        } catch {
            toast.error('Failed to create campaign');
        } finally {
            setIsLoading(false);
        }
    };

    const handleAIGenerate = async (field: string) => {
        setAiGenerating(true);
        try {
            await new Promise(resolve => setTimeout(resolve, 1500));
            // In real app, call AI service
            const suggestions: Record<string, string> = {
                name: 'AI-Generated Campaign Name',
                primary_text: 'Discover the future of marketing with our AI-powered platform. Transform your campaigns with intelligent automation.',
                headline: 'Revolutionize Your Marketing',
                description: 'Leverage AI to create, optimize, and scale your campaigns like never before.',
            };
            if (suggestions[field]) {
                setValue(`creative.${field}` as any, suggestions[field]);
            }
            toast.success('AI suggestion generated!');
        } catch {
            toast.error('Failed to generate');
        } finally {
            setAiGenerating(false);
        }
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex items-center gap-3">
                    <Link to="/campaigns" className="text-slate-500 hover:text-slate-700">
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Create Campaign</h1>
                        <p className="text-slate-500 dark:text-slate-400">Build your campaign step by step</p>
                    </div>
                </div>
            </div>

            {/* Progress Indicator */}
            <div className="hidden md:block mb-6">
                <div className="flex items-center">
                    {campaignTabs.map((tab, index) => (
                        <div key={tab.id} className="flex items-center">
                            <div className={cn(
                                'w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium transition-all',
                                index < activeTab
                                    ? 'bg-blue-600 text-white'
                                    : index === activeTab
                                        ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border-2 border-blue-500'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-400'
                            )}>
                                {index < activeTab ? <CheckCircle className="w-5 h-5" /> : tab.icon}
                            </div>
                            {index < campaignTabs.length - 1 && (
                                <div className={cn(
                                    'w-16 h-0.5 mx-2',
                                    index < activeTab ? 'bg-blue-500' : 'bg-slate-200 dark:bg-slate-700'
                                )} />
                            )}
                        </div>
                    ))}
                </div>
                <div className="flex justify-between mt-2 text-xs text-slate-500">
                    {campaignTabs.map((tab, index) => (
                        <span key={tab.id} className={cn(
                            'w-24 text-center',
                            index === activeTab && 'font-medium text-blue-600 dark:text-blue-400'
                        )}>
                            {tab.label}
                        </span>
                    ))}
                </div>
            </div>

            <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
                <Tabs tabs={campaignTabs} activeTab={campaignTabs[activeTab].id} onChange={id => setActiveTab(campaignTabs.findIndex(t => t.id === id))} variant="pills" className="mb-6" />

                <Card>
                    <CardContent className="pt-6">
                        {activeTab === 0 && (
                            <div className="space-y-6">
                                <h3 className="text-lg font-semibold">Campaign Basics</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <Input
                                        label="Campaign Name"
                                        placeholder="e.g., Summer Sale 2024"
                                        error={errors.name?.message}
                                        {...register('name')}
                                        required
                                    />
                                    <Select
                                        label="Platform"
                                        placeholder="Select platform"
                                        options={platformOptions}
                                        error={errors.platform?.message}
                                        {...register('platform')}
                                        required
                                    />
                                    <Select
                                        label="Objective"
                                        placeholder="Select objective"
                                        options={objectiveOptions}
                                        error={errors.objective?.message}
                                        {...register('objective')}
                                        required
                                    />
                                    <Select
                                        label="Budget Type"
                                        options={[
                                            { value: 'daily', label: 'Daily Budget' },
                                            { value: 'lifetime', label: 'Lifetime Budget' },
                                        ]}
                                        {...register('budget_type')}
                                    />
                                </div>
                            </div>
                        )}

                        {activeTab === 1 && (
                            <div className="space-y-6">
                                <h3 className="text-lg font-semibold">Target Audience</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <Input
                                        label="Locations (comma separated)"
                                        placeholder="United States, Canada, UK"
                                        {...register('targeting.locations')}
                                    />
                                    <div className="grid grid-cols-2 gap-4">
                                        <Input
                                            label="Min Age"
                                            type="number"
                                            min={13}
                                            max={65}
                                            {...register('targeting.age_min', { valueAsNumber: true })}
                                        />
                                        <Input
                                            label="Max Age"
                                            type="number"
                                            min={13}
                                            max={65}
                                            {...register('targeting.age_max', { valueAsNumber: true })}
                                        />
                                    </div>
                                    <Select
                                        label="Genders"
                                        options={[
                                            { value: 'male', label: 'Male' },
                                            { value: 'female', label: 'Female' },
                                            { value: 'all', label: 'All' },
                                        ]}
                                        multiple
                                        {...register('targeting.genders')}
                                    />
                                    <Input
                                        label="Interests (comma separated)"
                                        placeholder="Marketing, Technology, SaaS"
                                        {...register('targeting.interests')}
                                    />
                                </div>
                            </div>
                        )}

                        {activeTab === 2 && (
                            <div className="space-y-6">
                                <h3 className="text-lg font-semibold">Creative Assets</h3>
                                <Select
                                    label="Format"
                                    options={formatOptions}
                                    error={errors.creative?.format?.message}
                                    {...register('creative.format')}
                                    required
                                />
                                <div className="space-y-4">
                                    <div className="flex items-center gap-2">
                                        <Sparkles className="w-4 h-4 text-purple-500" />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleAIGenerate('primary_text')}
                                            loading={aiGenerating}
                                        >
                                            AI Generate Primary Text
                                        </Button>
                                    </div>
                                    <Textarea
                                        label="Primary Text"
                                        placeholder="Main ad copy..."
                                        error={errors.creative?.primary_text?.message}
                                        {...register('creative.primary_text')}
                                    />
                                    <div className="flex items-center gap-2">
                                        <Sparkles className="w-4 h-4 text-purple-500" />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleAIGenerate('headline')}
                                            loading={aiGenerating}
                                        >
                                            AI Generate Headline
                                        </Button>
                                    </div>
                                    <Input
                                        label="Headline"
                                        placeholder="Catchy headline (max 40 chars)"
                                        error={errors.creative?.headline?.message}
                                        {...register('creative.headline')}
                                    />
                                    <div className="flex items-center gap-2">
                                        <Sparkles className="w-4 h-4 text-purple-500" />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleAIGenerate('description')}
                                            loading={aiGenerating}
                                        >
                                            AI Generate Description
                                        </Button>
                                    </div>
                                    <Textarea
                                        label="Description"
                                        placeholder="Additional description..."
                                        {...register('creative.description')}
                                    />
                                    <Select
                                        label="Call to Action"
                                        options={ctaOptions}
                                        {...register('creative.call_to_action')}
                                    />
                                </div>
                            </div>
                        )}

                        {activeTab === 3 && (
                            <div className="space-y-6">
                                <h3 className="text-lg font-semibold">Budget & Schedule</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="space-y-4">
                                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">Budget</label>
                                        <div className="flex items-center gap-2">
                                            <Select
                                                options={[
                                                    { value: 'USD', label: 'USD' },
                                                    { value: 'EUR', label: 'EUR' },
                                                    { value: 'GBP', label: 'GBP' },
                                                ]}
                                                value={watch('currency')}
                                                onChange={e => setValue('currency', e.target.value)}
                                                className="w-24"
                                            />
                                            <Input
                                                type="number"
                                                placeholder="100"
                                                step="0.01"
                                                min="1"
                                                {...register('budget', { valueAsNumber: true })}
                                                error={errors.budget?.message}
                                            />
                                        </div>
                                    </div>
                                    <Input
                                        label="Start Date"
                                        type="date"
                                        error={errors.start_date?.message}
                                        {...register('start_date')}
                                        required
                                    />
                                    <Input
                                        label="End Date (optional)"
                                        type="date"
                                        {...register('end_date')}
                                    />
                                </div>
                            </div>
                        )}

                        {activeTab === 4 && (
                            <div className="space-y-6">
                                <h3 className="text-lg font-semibold">Review & Launch</h3>
                                <div className="space-y-4">
                                    <div className="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg">
                                        <h4 className="font-medium mb-3">Campaign Summary</h4>
                                        <dl className="space-y-2 text-sm">
                                            <div className="flex justify-between">
                                                <dt className="text-slate-500">Name</dt>
                                                <dd className="font-medium">{watch('name') || '—'}</dd>
                                            </div>
                                            <div className="flex justify-between">
                                                <dt className="text-slate-500">Platform</dt>
                                                <dd className="font-medium capitalize">{watch('platform') || '—'}</dd>
                                            </div>
                                            <div className="flex justify-between">
                                                <dt className="text-slate-500">Objective</dt>
                                                <dd className="font-medium capitalize">{watch('objective') || '—'}</dd>
                                            </div>
                                            <div className="flex justify-between">
                                                <dt className="text-slate-500">Budget</dt>
                                                <dd className="font-medium">{watch('budget_type') === 'daily' ? 'Daily' : 'Lifetime'}: ${watch('budget') || 0}/{watch('currency')}</dd>
                                            </div>
                                            <div className="flex justify-between">
                                                <dt className="text-slate-500">Schedule</dt>
                                                <dd className="font-medium">{watch('start_date') || '—'} to {watch('end_date') || 'Ongoing'}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                    <CardFooter>
                        <div className="flex justify-between w-full">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setActiveTab(Math.max(0, activeTab - 1))}
                                disabled={activeTab === 0}
                            >
                                <ArrowLeft className="w-4 h-4 mr-2" />
                                Previous
                            </Button>
                            <div className="flex gap-3">
                                {activeTab === campaignTabs.length - 1 ? (
                                    <Button
                                        type="submit"
                                        loading={isLoading}
                                        size="lg"
                                    >
                                        <Save className="w-4 h-4 mr-2" />
                                        Launch Campaign
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        onClick={() => setActiveTab(activeTab + 1)}
                                        size="lg"
                                    >
                                        Next
                                        <ArrowRight className="w-4 h-4 ml-2" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardFooter>
                </Card>
            </form>
        </div>
    );
}
