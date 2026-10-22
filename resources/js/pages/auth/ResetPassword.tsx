import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Loader2, Eye, EyeOff, ArrowLeft } from 'lucide-react';
import Button from '@components/ui/Button';
import Input from '@components/ui/Input';
import { Card, CardHeader, CardContent, CardFooter } from '@components/ui/Card';
import { AuthLayout } from '@components/layout/AuthLayout';
import toast from 'react-hot-toast';

const resetPasswordSchema = z.object({
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string(),
}).refine(data => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
});

type ResetPasswordFormData = z.infer<typeof resetPasswordSchema>;

export function ResetPassword() {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const token = searchParams.get('token') || '';
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<ResetPasswordFormData>({
        resolver: zodResolver(resetPasswordSchema),
    });

    const onSubmit = async (data: ResetPasswordFormData) => {
        setIsLoading(true);
        try {
            await new Promise(resolve => setTimeout(resolve, 1000));
            toast.success('Password reset successful!');
            navigate('/login');
        } catch {
            toast.error('Failed to reset password');
        } finally {
            setIsLoading(false);
        }
    };

    if (!token) {
        navigate('/forgot-password');
        return null;
    }

    return (
        <AuthLayout>
            <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
                <div className="flex items-center gap-2 mb-6">
                    <Link to="/login" className="text-slate-500 hover:text-slate-700">
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                </div>
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Reset Password</h1>
                    <p className="mt-2 text-slate-500 dark:text-slate-400">
                        Enter your new password below
                    </p>
                </div>

                <div className="relative">
                    <Input
                        label="New Password"
                        type={showPassword ? 'text' : 'password'}
                        placeholder="Create a strong password"
                        error={errors.password?.message}
                        leftIcon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>}
                        rightIcon={
                            <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                            >
                                {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                            </button>
                        }
                        {...register('password')}
                        autoComplete="new-password"
                        required
                    />
                </div>

                <div className="relative">
                    <Input
                        label="Confirm New Password"
                        type={showConfirmPassword ? 'text' : 'password'}
                        placeholder="Confirm your password"
                        error={errors.password_confirmation?.message}
                        leftIcon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
                        rightIcon={
                            <button
                                type="button"
                                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                                aria-label={showConfirmPassword ? 'Hide password' : 'Show password'}
                            >
                                {showConfirmPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                            </button>
                        }
                        {...register('password_confirmation')}
                        autoComplete="new-password"
                        required
                    />
                </div>

                <Button
                    type="submit"
                    fullWidth
                    size="lg"
                    loading={isLoading}
                >
                    {isLoading ? (
                        <>
                            <Loader2 className="w-4 h-4 animate-spin" />
                            Resetting...
                        </>
                    ) : (
                        'Reset Password'
                    )}
                </Button>

                <div className="text-center">
                    <Link to="/login" className="text-sm text-blue-600 hover:text-blue-500 font-medium">
                        Back to login
                    </Link>
                </div>
            </form>
        </AuthLayout>
    );
}
