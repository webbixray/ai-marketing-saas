import { Link, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Loader2 } from 'lucide-react';
import { useAuth } from '@hooks/useAuth';
import Button from '@components/ui/Button';
import Input from '@components/ui/Input';
import { Card, CardHeader, CardContent, CardFooter } from '@components/ui/Card';
import { AuthLayout } from '@components/layout/AuthLayout';
import toast from 'react-hot-toast';

const registerSchema = z.object({
    name: z.string().min(2, 'Name must be at least 2 characters'),
    email: z.string().email('Please enter a valid email address'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string(),
    tenant_name: z.string().min(2, 'Organization name must be at least 2 characters').optional(),
}).refine(data => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
});

type RegisterFormData = z.infer<typeof registerSchema>;

export function Register() {
    const navigate = useNavigate();
    const { register: registerUser, isLoading: authLoading } = useAuth();
    const [isLoading, setIsLoading] = useState(false);

    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<RegisterFormData>({
        resolver: zodResolver(registerSchema),
    });

    const onSubmit = async (data: RegisterFormData) => {
        setIsLoading(true);
        try {
            await registerUser(data);
            toast.success('Account created successfully!');
            navigate('/dashboard');
        } catch (error: unknown) {
            const message = error instanceof Error ? error.message : 'Registration failed';
            toast.error(message);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <AuthLayout>
            <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Create your account</h1>
                    <p className="mt-2 text-slate-500 dark:text-slate-400">
                        Already have an account?{' '}
                        <Link to="/login" className="text-blue-600 hover:text-blue-500 font-medium">
                            Sign in
                        </Link>
                    </p>
                </div>

                <div className="space-y-4">
                    <Input
                        label="Full name"
                        type="text"
                        placeholder="John Doe"
                        error={errors.name?.message}
                        leftIcon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>}
                        {...register('name')}
                        autoComplete="name"
                        required
                    />

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

                    <Input
                        label="Organization name (optional)"
                        type="text"
                        placeholder="Acme Inc."
                        error={errors.tenant_name?.message}
                        leftIcon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>}
                        {...register('tenant_name')}
                        autoComplete="organization"
                    />

                    <Input
                        label="Password"
                        type="password"
                        placeholder="Create a strong password"
                        error={errors.password?.message}
                        leftIcon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>}
                        {...register('password')}
                        autoComplete="new-password"
                        required
                    />

                    <Input
                        label="Confirm password"
                        type="password"
                        placeholder="Confirm your password"
                        error={errors.password_confirmation?.message}
                        leftIcon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
                        {...register('password_confirmation')}
                        autoComplete="new-password"
                        required
                    />
                </div>

                <div className="flex items-start gap-2">
                    <input
                        type="checkbox"
                        id="terms"
                        required
                        className="mt-1 w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                    />
                    <label htmlFor="terms" className="text-sm text-slate-600 dark:text-slate-400">
                        I agree to the{' '}
                        <Link to="/terms" className="text-blue-600 hover:text-blue-500">Terms of Service</Link>{' '}
                        and{' '}
                        <Link to="/privacy" className="text-blue-600 hover:text-blue-500">Privacy Policy</Link>
                    </label>
                </div>

                <Button
                    type="submit"
                    fullWidth
                    size="lg"
                    loading={isLoading || authLoading}
                >
                    {isLoading || authLoading ? (
                        <>
                            <Loader2 className="w-4 h-4 animate-spin" />
                            Creating account...
                        </>
                    ) : (
                        'Create account'
                    )}
                </Button>
            </form>
        </AuthLayout>
    );
}
