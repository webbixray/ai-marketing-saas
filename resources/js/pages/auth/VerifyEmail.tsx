import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useState, useEffect } from 'react';
import { Loader2, CheckCircle, AlertCircle, Mail, ArrowLeft } from 'lucide-react';
import Button from '@components/ui/Button';
import { Card, CardHeader, CardContent, CardFooter } from '@components/ui/Card';
import { AuthLayout } from '@components/layout/AuthLayout';
import toast from 'react-hot-toast';

export function VerifyEmail() {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const token = searchParams.get('token') || '';
    const [status, setStatus] = useState<'verifying' | 'success' | 'error'>('verifying');
    const [message, setMessage] = useState('');

    useEffect(() => {
        const verifyEmail = async () => {
            if (!token) {
                setStatus('error');
                setMessage('Invalid verification link');
                return;
            }

            try {
                // In real app, call API to verify email
                await new Promise(resolve => setTimeout(resolve, 1500));
                setStatus('success');
                setMessage('Email verified successfully!');
            } catch {
                setStatus('error');
                setMessage('Verification failed. Link may be expired.');
            }
        };

        verifyEmail();
    }, [token]);

    if (status === 'verifying') {
        return (
            <AuthLayout>
                <Card className="max-w-md">
                    <CardContent className="pt-6 text-center">
                        <Loader2 className="w-10 h-10 animate-spin text-blue-600 mx-auto mb-4" />
                        <h2 className="text-xl font-semibold text-slate-900 dark:text-white">Verifying your email...</h2>
                        <p className="text-slate-500 dark:text-slate-400 mt-2">Please wait while we verify your email address</p>
                    </CardContent>
                </Card>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout>
            <Card className="max-w-md">
                <CardContent className="pt-6 text-center">
                    {status === 'success' ? (
                        <>
                            <CheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
                            <h2 className="text-xl font-semibold text-slate-900 dark:text-white">Email Verified!</h2>
                            <p className="text-slate-500 dark:text-slate-400 mt-2">{message}</p>
                        </>
                    ) : (
                        <>
                            <AlertCircle className="w-16 h-16 text-red-500 mx-auto mb-4" />
                            <h2 className="text-xl font-semibold text-slate-900 dark:text-white">Verification Failed</h2>
                            <p className="text-slate-500 dark:text-slate-400 mt-2">{message}</p>
                        </>
                    )}
                <div className="mt-6 space-y-3">
                    <Button onClick={() => navigate('/login')} className="w-full">
                        Go to Login
                    </Button>
                    <Link to="/register" className="text-sm text-blue-600 hover:text-blue-500 block">
                        Need a new account? Sign up
                    </Link>
                </div>
                </CardContent>
            </Card>
        </AuthLayout>
    );
}
