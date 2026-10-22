import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { Theme, ThemeConfig } from '@types';

interface ThemeContextType {
    theme: Theme;
    resolvedTheme: 'light' | 'dark';
    config: ThemeConfig;
    setTheme: (theme: Theme) => void;
    updateConfig: (config: Partial<ThemeConfig>) => void;
}

const defaultConfig: ThemeConfig = {
    theme: 'system',
    colors: {
        primary: '#3b82f6',
        secondary: '#64748b',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444',
    },
    fonts: {
        heading: 'Instrument Sans',
        body: 'Instrument Sans',
        mono: 'JetBrains Mono',
    },
};

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export function ThemeProvider({ children }: { children: React.ReactNode }) {
    const [theme, setThemeState] = useState<Theme>(() => {
        if (typeof window !== 'undefined') {
            return (localStorage.getItem('theme') as Theme) || 'system';
        }
        return 'system';
    });

    const [config, setConfig] = useState<ThemeConfig>(() => {
        if (typeof window !== 'undefined') {
            const stored = localStorage.getItem('theme_config');
            if (stored) {
                try {
                    return { ...defaultConfig, ...JSON.parse(stored) };
                } catch {
                    return defaultConfig;
                }
            }
        }
        return defaultConfig;
    });

    const [resolvedTheme, setResolvedTheme] = useState<'light' | 'dark'>('light');

    // Resolve theme based on system preference
    const resolveTheme = useCallback((t: Theme): 'light' | 'dark' => {
        if (t === 'system') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return t;
    }, []);

    // Apply theme to document
    const applyTheme = useCallback((resolved: 'light' | 'dark') => {
        const root = document.documentElement;
        if (resolved === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
        
        // Apply custom colors from config
        root.style.setProperty('--color-primary', config.colors.primary);
        root.style.setProperty('--color-secondary', config.colors.secondary);
        root.style.setProperty('--color-success', config.colors.success);
        root.style.setProperty('--color-warning', config.colors.warning);
        root.style.setProperty('--color-danger', config.colors.danger);
        
        // Apply fonts
        root.style.setProperty('--font-heading', config.fonts.heading);
        root.style.setProperty('--font-body', config.fonts.body);
        root.style.setProperty('--font-mono', config.fonts.mono);
    }, [config]);

    // Update resolved theme when theme or system preference changes
    useEffect(() => {
        const resolved = resolveTheme(theme);
        setResolvedTheme(resolved);
        applyTheme(resolved);
        localStorage.setItem('theme', theme);
    }, [theme, resolveTheme, applyTheme]);

    // Listen for system theme changes
    useEffect(() => {
        if (theme !== 'system') return;

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = () => {
            const resolved = resolveTheme('system');
            setResolvedTheme(resolved);
            applyTheme(resolved);
        };

        mediaQuery.addEventListener('change', handleChange);
        return () => mediaQuery.removeEventListener('change', handleChange);
    }, [theme, resolveTheme, applyTheme]);

    // Apply config changes
    useEffect(() => {
        applyTheme(resolvedTheme);
        localStorage.setItem('theme_config', JSON.stringify(config));
    }, [config, resolvedTheme, applyTheme]);

    const setTheme = (newTheme: Theme) => {
        setThemeState(newTheme);
    };

    const updateConfig = (newConfig: Partial<ThemeConfig>) => {
        setConfig(prev => ({ ...prev, ...newConfig }));
    };

    return (
        <ThemeContext.Provider value={{
            theme,
            resolvedTheme,
            config,
            setTheme,
            updateConfig,
        }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (!context) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }
    return context;
}
