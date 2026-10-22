// TypeScript declaration file for path aliases
// This file helps TypeScript understand the path aliases used in the project

declare module '@utils' {
    export const cn: (...inputs: any[]) => string;
    export const formatCurrency: (amount: number, currency?: string, locale?: string) => string;
    export const formatNumber: (num: number, locale?: string) => string;
    export const formatPercent: (value: number, decimals?: number) => string;
    export const formatDate: (date: string | Date, options?: Intl.DateTimeFormatOptions) => string;
    export const formatDateTime: (date: string | Date) => string;
    export const formatRelativeTime: (date: string | Date) => string;
    export const truncate: (str: string, length: number) => string;
    export const slugify: (str: string) => string;
    export const generateId: () => string;
    export const debounce: <T extends (...args: any[]) => any>(func: T, wait: number) => (...args: Parameters<T>) => void;
    export const throttle: <T extends (...args: any[]) => any>(func: T, limit: number) => (...args: Parameters<T>) => void;
    export const getInitials: (name: string) => string;
    export const getColorForString: (str: string) => string;
    export const sleep: (ms: number) => Promise<void>;
    export const retry: <T>(fn: () => Promise<T>, retries?: number, delay?: number) => Promise<T>;
    export const parseQueryString: (query: string) => Record<string, string>;
    export const buildQueryString: (params: Record<string, any>) => string;
    export const deepClone: <T>(obj: T) => T;
    export const isEmpty: (obj: any) => boolean;
    export const groupBy: <T>(array: T[], key: keyof T | ((item: T) => string)) => Record<string, T[]>;
    export const sortBy: <T>(array: T[], key: keyof T | ((item: T) => any), direction?: 'asc' | 'desc') => T[];
    export const uniqueBy: <T>(array: T[], key: keyof T | ((item: T) => string)) => T[];
    export const chunk: <T>(array: T[], size: number) => T[][];
    export const flatten: <T>(array: T[][]) => T[];
    export const formatRelativeTime: (date: string | Date) => string;
}

declare module '@utils/*' {
    const content: any;
    export default content;
}

declare module '@components/*' {
    const content: any;
    export default content;
}

declare module '@hooks/*' {
    const content: any;
    export default content;
}

declare module '@pages/*' {
    const content: any;
    export default content;
}

declare module '@stores/*' {
    const content: any;
    export default content;
}

declare module '@services/*' {
    const content: any;
    export default content;
}

declare module '@types/*' {
    const content: any;
    export default content;
}

declare module '@context/*' {
    const content: any;
    export default content;
}

declare module '@components/ui/*' {
    const content: any;
    export default content;
}

declare module '@components/layout/*' {
    const content: any;
    export default content;
}

declare module '@components/marketing/*' {
    const content: any;
    export default content;
}

declare module '@components/analytics/*' {
    const content: any;
    export default content;
}

declare module '@components/enterprise/*' {
    const content: any;
    export default content;
}

declare module '@components/global/*' {
    const content: any;
    export default content;
}
