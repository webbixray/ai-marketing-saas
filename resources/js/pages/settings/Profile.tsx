import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Camera, Loader2, Eye, EyeOff } from 'lucide-react';
import Button from '@components/ui/Button';
import Input from '@components/ui/Input';
import Avatar from '@components/ui/Avatar';
import { Card, CardHeader, CardContent, CardFooter } from '@components/ui/Card';
import { toast } from 'react-hot-toast';
import { cn } from '@utils';

const profileSchema = z.object({
    name: z.string().min(2, 'Name must be at least 2 characters'),
    email: z.string().email('Please enter a valid email address'),
    current_password: z.string().optional(),
    new_password: z.string().min(8, 'Password must be at least 8 characters').optional(),
    confirm_password: z.string().optional(),
}).refine(data => !data.new_password || data.new_password === data.confirm_password, {
    message: 'Passwords do not match',
    path: ['confirm_password'],
});

type ProfileFormData = z.infer<typeof profileSchema>;

export function Profile() {
    const [avatar, setAvatar] = useState<string | null>(null);
    const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
    const [showCurrentPassword, setShowCurrentPassword] = useState(false);
    const [showNewPassword, setShowNewPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    const {
        register,
        handleSubmit,
        watch,
        formState: { errors },
        setValue,
    } = useForm<ProfileFormData>({
        resolver: zodResolver(profileSchema),
        defaultValues: {
            name: 'John Doe',
            email: 'john@example.com',
        },
    });

    const newPassword = watch('new_password');

    const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            const reader = new FileReader();
            reader.onloadend = () => {
                setAvatarPreview(reader.result as string);
                setAvatar(reader.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const onSubmit = async (data: ProfileFormData) => {
        setIsLoading(true);
        try {
            await new Promise(resolve => setTimeout(resolve, 1000));
            toast.success('Profile updated successfully!');
        } catch {
            toast.error('Failed to update profile');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div className="space-y-6">
            {/* Avatar */}
            <Card>
                <CardHeader title="Profile Picture" />
                <CardContent>
                    <div className="flex items-center gap-6">
                        <Avatar 
                            src={avatarPreview || undefined} 
                            name="John Doe" 
                            size="xl" 
                        />
                        <div>
                            <Button variant="outline" onClick={() => document.getElementById('avatar-upload')?.click()}>
                                <Camera className="w-4 h-4 mr-2" />
                                Change Photo
                            </Button>
                            <input
                                id="avatar-upload"
                                type="file"
                                accept="image/*"
                                onChange={handleAvatarChange}
                                className="hidden"
                            />
                            <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                JPG, GIF or PNG. Max 2MB.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Profile Info */}
            <Card>
                <CardHeader title="Personal Information" />
                <CardContent>
                    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <Input
                                label="Full Name"
                                error={errors.name?.message}
                                {...register('name')}
                                required
                            />
                            <Input
                                label="Email Address"
                                type="email"
                                error={errors.email?.message}
                                {...register('email')}
                                required
                            />
                        </div>
                    </form>
                </CardContent>
            </Card>

            {/* Password Change */}
            <Card>
                <CardHeader title="Change Password" description="Leave blank to keep your current password" />
                <CardContent>
                    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
                        <Input
                            label="Current Password"
                            type={showCurrentPassword ? 'text' : 'password'}
                            error={errors.current_password?.message}
                            rightIcon={
                                <button
                                    type="button"
                                    onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                                    className="text-slate-400 hover:text-slate-600"
                                >
                                    {showCurrentPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                </button>
                            }
                            {...register('current_password')}
                            autoComplete="current-password"
                        />
                        <Input
                            label="New Password"
                            type={showNewPassword ? 'text' : 'password'}
                            error={errors.new_password?.message}
                            helperText={newPassword ? 'Must be at least 8 characters' : undefined}
                            rightIcon={
                                <button
                                    type="button"
                                    onClick={() => setShowNewPassword(!showNewPassword)}
                                    className="text-slate-400 hover:text-slate-600"
                                >
                                    {showNewPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                </button>
                            }
                            {...register('new_password')}
                            autoComplete="new-password"
                        />
                        <Input
                            label="Confirm New Password"
                            type={showConfirmPassword ? 'text' : 'password'}
                            error={errors.confirm_password?.message}
                            rightIcon={
                                <button
                                    type="button"
                                    onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                    className="text-slate-400 hover:text-slate-600"
                                >
                                    {showConfirmPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                </button>
                            }
                            {...register('confirm_password')}
                            autoComplete="new-password"
                        />
                    </form>
                </CardContent>
            </Card>

            {/* Save Button */}
            <div className="flex justify-end">
                <Button
                    type="submit"
                    form="profile-form"
                    size="lg"
                    loading={isLoading}
                >
                    {isLoading ? (
                        <>
                            <Loader2 className="w-4 h-4 animate-spin mr-2" />
                            Saving...
                        </>
                    ) : (
                        'Save Changes'
                    )}
                </Button>
            </div>
        </div>
    );
}
