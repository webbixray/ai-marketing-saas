import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { User, Tenant } from '@types';
import api from '@services/api';

interface AuthContextType {
    user: User | null;
    tenant: Tenant | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    login: (email: string, password: string, remember?: boolean) => Promise<void>;
    register: (data: RegisterData) => Promise<void>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
    updateUser: (data: Partial<User>) => void;
    hasPermission: (permission: string) => boolean;
    hasRole: (role: string) => boolean;
    hasAnyRole: (roles: string[]) => boolean;
}

interface RegisterData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    tenant_name?: string;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [tenant, setTenant] = useState<Tenant | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const loadUser = useCallback(async () => {
        const token = localStorage.getItem('access_token');
        if (!token) {
            setIsLoading(false);
            return;
        }

        try {
            const [userData, tenantData] = await Promise.all([
                api.get<User>('/user'),
                api.get<Tenant>('/tenant'),
            ]);
            setUser(userData);
            setTenant(tenantData);
        } catch {
            localStorage.removeItem('access_token');
            setUser(null);
            setTenant(null);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        loadUser();
    }, [loadUser]);

    const login = async (email: string, password: string, remember = false) => {
        const response = await api.post<{ user: User; token: string }>('/login', {
            email,
            password,
            remember,
        });

        localStorage.setItem('access_token', response.token);
        setUser(response.user);
        
        // Load tenant
        const tenantData = await api.get<Tenant>('/tenant');
        setTenant(tenantData);
    };

    const register = async (data: RegisterData) => {
        const response = await api.post<{ user: User; token: string }>('/register', data);
        localStorage.setItem('access_token', response.token);
        setUser(response.user);
        
        const tenantData = await api.get<Tenant>('/tenant');
        setTenant(tenantData);
    };

    const logout = async () => {
        try {
            await api.post('/logout');
        } finally {
            localStorage.removeItem('access_token');
            setUser(null);
            setTenant(null);
        }
    };

    const refreshUser = async () => {
        await loadUser();
    };

    const updateUser = (data: Partial<User>) => {
        setUser(prev => prev ? { ...prev, ...data } : null);
    };

    const hasPermission = (permission: string): boolean => {
        return user?.permissions?.includes(permission) ?? false;
    };

    const hasRole = (role: string): boolean => {
        return user?.roles?.includes(role) ?? false;
    };

    const hasAnyRole = (roles: string[]): boolean => {
        return user?.roles?.some(r => roles.includes(r)) ?? false;
    };

    return (
        <AuthContext.Provider value={{
            user,
            tenant,
            isAuthenticated: !!user,
            isLoading,
            login,
            register,
            logout,
            refreshUser,
            updateUser,
            hasPermission,
            hasRole,
            hasAnyRole,
        }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
}
