import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios';
import { ApiError, ApiResponse, PaginatedResponse } from '@types';

class ApiService {
    private client: AxiosInstance;
    private refreshTokenPromise: Promise<string> | null = null;

    constructor() {
        this.client = axios.create({
            baseURL: '/api/v1',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            withCredentials: true,
        });

        this.setupInterceptors();
    }

    private setupInterceptors() {
        // Request interceptor - add auth token
        this.client.interceptors.request.use(
            (config: InternalAxiosRequestConfig) => {
                const token = localStorage.getItem('access_token');
                if (token && config.headers) {
                    config.headers.Authorization = `Bearer ${token}`;
                }
                return config;
            },
            (error) => Promise.reject(error)
        );

        // Response interceptor - handle token refresh
        this.client.interceptors.response.use(
            (response) => response,
            async (error: AxiosError) => {
                const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };

                if (error.response?.status === 401 && !originalRequest._retry) {
                    originalRequest._retry = true;

                    try {
                        const newToken = await this.refreshToken();
                        if (originalRequest.headers) {
                            originalRequest.headers.Authorization = `Bearer ${newToken}`;
                        }
                        return this.client(originalRequest);
                    } catch {
                        // Refresh failed - logout
                        this.logout();
                        window.location.href = '/login';
                        return Promise.reject(error);
                    }
                }

                return Promise.reject(this.formatError(error));
            }
        );
    }

    private async refreshToken(): Promise<string> {
        if (this.refreshTokenPromise) {
            return this.refreshTokenPromise;
        }

        this.refreshTokenPromise = (async () => {
            const response = await axios.post('/api/v1/auth/refresh', {}, {
                withCredentials: true,
            });
            const token = response.data.data.token;
            localStorage.setItem('access_token', token);
            return token;
        })();

        try {
            return await this.refreshTokenPromise;
        } finally {
            this.refreshTokenPromise = null;
        }
    }

    private formatError(error: AxiosError): ApiError {
        if (error.response) {
            return {
                message: error.response.data?.message || 'An error occurred',
                errors: error.response.data?.errors,
                status: error.response.status,
            };
        }
        return {
            message: error.message || 'Network error',
            status: 0,
        };
    }

    private logout() {
        localStorage.removeItem('access_token');
        localStorage.removeItem('user');
    }

    // Generic HTTP methods
    async get<T>(url: string, params?: Record<string, unknown>): Promise<T> {
        const response = await this.client.get<ApiResponse<T> | T>(url, { params });
        return this.extractData(response);
    }

    async post<T>(url: string, data?: unknown): Promise<T> {
        const response = await this.client.post<ApiResponse<T> | T>(url, data);
        return this.extractData(response);
    }

    async put<T>(url: string, data?: unknown): Promise<T> {
        const response = await this.client.put<ApiResponse<T> | T>(url, data);
        return this.extractData(response);
    }

    async patch<T>(url: string, data?: unknown): Promise<T> {
        const response = await this.client.patch<ApiResponse<T> | T>(url, data);
        return this.extractData(response);
    }

    async delete<T>(url: string): Promise<T> {
        const response = await this.client.delete<ApiResponse<T> | T>(url);
        return this.extractData(response);
    }

    private extractData<T>(response: AxiosResponse<ApiResponse<T> | T>): T {
        const data = response.data;
        if (data && typeof data === 'object' && 'data' in data) {
            return (data as ApiResponse<T>).data;
        }
        return data as T;
    }

    // Paginated requests
    async getPaginated<T>(url: string, params?: Record<string, unknown>): Promise<PaginatedResponse<T>> {
        const response = await this.client.get<PaginatedResponse<T>>(url, { params });
        return response.data;
    }

    // File upload
    async upload<T>(url: string, file: File, onProgress?: (progress: number) => void): Promise<T> {
        const formData = new FormData();
        formData.append('file', file);

        const response = await this.client.post<ApiResponse<T>>(url, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total && onProgress) {
                    onProgress(Math.round((progressEvent.loaded * 100) / progressEvent.total));
                }
            },
        });
        return this.extractData(response);
    }

    // Health check
    async healthCheck(): Promise<{ status: string }> {
        const response = await this.client.get('/health');
        return response.data;
    }

    // Set auth token manually (for SSR or initial load)
    setToken(token: string) {
        localStorage.setItem('access_token', token);
    }

    // Clear auth
    clearAuth() {
        localStorage.removeItem('access_token');
        localStorage.removeItem('user');
    }
}

export const api = new ApiService();
export default api;
