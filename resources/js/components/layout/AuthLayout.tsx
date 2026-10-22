import { Fragment } from 'react';
import { Outlet } from 'react-router-dom';
import { cn } from '../../utils';
import { Zap } from 'lucide-react';

export function AuthLayout({ children }: { children?: React.ReactNode }) {
    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex items-center justify-center p-4">
            <div className="w-full max-w-md">
                <div className="text-center mb-8">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-blue-600 mx-auto mb-4">
                        <Zap className="w-8 h-8 text-white" />
                    </div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Webixray</h1>
                    <p className="text-slate-500 dark:text-slate-400 mt-1">AI-Powered Marketing Platform</p>
                </div>
                
                <div className="bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 p-8">
                    {children || <Outlet />}
                </div>

                <p className="mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
                    © 2024 Webixray. All rights reserved.
                </p>
            </div>
        </div>
    );
}
