import { Link, useNavigate } from 'react-router-dom';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Loader2, ArrowLeft } from 'lucide-react';
import Button from '@components/ui/Button';
import Input from '@components/ui/Input';
import { Card, CardHeader, CardContent, CardFooter } from '@components/ui/Card';
import { AuthLayout } from '@components/layout/AuthLayout';
import toast from 'react-hot-toast';

const forgotPasswordSchema = z.object({
    email: z.string().email('Please enter a valid email address'),
});

type ForgotPasswordFormData = z.infer<typeof forgotPasswordSchema>;

export function ForgotPassword() {
    const navigate = useNavigate();
    const [isLoading, setIsLoading] = useState(false);

    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<ForgotPasswordFormData>({
        resolver: zodResolver(forgotPasswordSchema),
    });

    const onSubmit = async (data: ForgotPasswordFormData) => {
        setIsLoading(true);
        try {
            await new Promise(resolve => setTimeout(resolve, 1000));
            toast.success('Password reset link sent to your email!');
            navigate('/login');
        } catch {
            toast.error('Failed to send reset link');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <AuthLayout>
            <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
                <div className="flex items-center gap-2 mb-6">
                    <Link to="/login" className="text-slate-500 hover:text-slate-700">
                        <ArrowLeft className="w-5 h-5" />
                    </Link>
                </div>
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Forgot Password</h1>
                    <p className="mt-2 text-slate-500 dark:text-slate-400">
                        Enter your email and we'll send you a reset link
                    </p>
                </div>

                <Input
                    label="Email address"
                    type="email"
                    placeholder="you@example.com"
                    error={errors.email?.message}
                    leftIcon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>}
                    {...register('email')}
                    autoComplete="email"
                    required
                />

                <Button
                    type="submit"
                    fullWidth
                    size="lg"
                    loading={isLoading}
                >
                    {isLoading ? (
                        <>
                            <Loader2 className="w-4 h-4 animate-spin" />
                            Sending...
                        </>
                    ) : (
                        'Send Reset Link'
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
