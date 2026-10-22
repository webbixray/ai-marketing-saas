import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatCurrency(amount: number, currency = 'USD', locale = 'en-US'): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(amount);
}

export function formatNumber(num: number, locale = 'en-US'): string {
    return new Intl.NumberFormat(locale).format(num);
}

export function formatPercent(value: number, decimals = 1): string {
    return `${(value * 100).toFixed(decimals)}%`;
}

export function formatDate(date: string | Date, options?: Intl.DateTimeFormatOptions): string {
    const d = typeof date === 'string' ? new Date(date) : date;
    return d.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        ...options,
    });
}

export function formatDateTime(date: string | Date): string {
    const d = typeof date === 'string' ? new Date(date) : date;
    return d.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatRelativeTime(date: string | Date): string {
    const d = typeof date === 'string' ? new Date(date) : date;
    const now = new Date();
    const diffMs = now.getTime() - d.getTime();
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSecs < 60) return 'just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return formatDate(d);
}

export function truncate(str: string, length: number): string {
    if (str.length <= length) return str;
    return `${str.slice(0, length)}...`;
}

export function slugify(str: string): string {
    return str
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export function generateId(): string {
    return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
}

export function debounce<T extends (...args: unknown[]) => unknown>(
    func: T,
    wait: number
): (...args: Parameters<T>) => void {
    let timeout: NodeJS.Timeout | null = null;
    return (...args: Parameters<T>) => {
        if (timeout) clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
}

export function throttle<T extends (...args: unknown[]) => unknown>(
    func: T,
    limit: number
): (...args: Parameters<T>) => void {
    let inThrottle = false;
    return (...args: Parameters<T>) => {
        if (!inThrottle) {
            func(...args);
            inThrottle = true;
            setTimeout(() => (inThrottle = false), limit);
        }
    };
}

export function getInitials(name: string): string {
    return name
        .split(' ')
        .map(part => part[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

export function getColorForString(str: string): string {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    const hue = hash % 360;
    return `hsl(${hue}, 70%, 50%)`;
}

export function sleep(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
}

export function retry<T>(
    fn: () => Promise<T>,
    retries = 3,
    delay = 1000
): Promise<T> {
    return fn().catch(err => {
        if (retries <= 0) throw err;
        return sleep(delay).then(() => retry(fn, retries - 1, delay * 2));
    });
}

export function parseQueryString(query: string): Record<string, string> {
    const params = new URLSearchParams(query);
    const result: Record<string, string> = {};
    params.forEach((value, key) => {
        result[key] = value;
    });
    return result;
}

export function buildQueryString(params: Record<string, unknown>): string {
    const searchParams = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            if (Array.isArray(value)) {
                value.forEach(v => searchParams.append(key, String(v)));
            } else {
                searchParams.append(key, String(value));
            }
        }
    });
    return searchParams.toString();
}

export function deepClone<T>(obj: T): T {
    return JSON.parse(JSON.stringify(obj));
}

export function isEmpty(obj: unknown): boolean {
    if (obj === null || obj === undefined) return true;
    if (typeof obj === 'string') return obj.trim() === '';
    if (Array.isArray(obj)) return obj.length === 0;
    if (typeof obj === 'object') return Object.keys(obj).length === 0;
    return false;
}

export function groupBy<T>(array: T[], key: keyof T | ((item: T) => string)): Record<string, T[]> {
    return array.reduce((result, item) => {
        const groupKey = typeof key === 'function' ? key(item) : String(item[key]);
        if (!result[groupKey]) {
            result[groupKey] = [];
        }
        result[groupKey].push(item);
        return result;
    }, {} as Record<string, T[]>);
}

export function sortBy<T>(array: T[], key: keyof T | ((item: T) => unknown), direction: 'asc' | 'desc' = 'asc'): T[] {
    const getter = typeof key === 'function' ? key : (item: T) => item[key];
    return [...array].sort((a, b) => {
        const aVal = getter(a);
        const bVal = getter(b);
        if (aVal < bVal) return direction === 'asc' ? -1 : 1;
        if (aVal > bVal) return direction === 'asc' ? 1 : -1;
        return 0;
    });
}

export function uniqueBy<T>(array: T[], key: keyof T | ((item: T) => string)): T[] {
    const seen = new Set<string>();
    return array.filter(item => {
        const k = typeof key === 'function' ? key(item) : String(item[key]);
        if (seen.has(k)) return false;
        seen.add(k);
        return true;
    });
}

export function chunk<T>(array: T[], size: number): T[][] {
    const result: T[][] = [];
    for (let i = 0; i < array.length; i += size) {
        result.push(array.slice(i, i + size));
    }
    return result;
}

export function flatten<T>(array: T[][]): T[] {
    return array.reduce((acc, val) => acc.concat(val), []);
}
