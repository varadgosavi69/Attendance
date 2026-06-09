import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios';
import type { ApiSuccess, AuthTokens, LoginResponse } from '../types';

const ACCESS_TOKEN_KEY = 'attendance.access_token';
const REFRESH_TOKEN_KEY = 'attendance.refresh_token';

export function getAccessToken(): string | null {
  return localStorage.getItem(ACCESS_TOKEN_KEY);
}

export function getRefreshToken(): string | null {
  return localStorage.getItem(REFRESH_TOKEN_KEY);
}

export function storeTokens(tokens: AuthTokens): void {
  localStorage.setItem(ACCESS_TOKEN_KEY, tokens.access_token);
  localStorage.setItem(REFRESH_TOKEN_KEY, tokens.refresh_token);
}

export function clearTokens(): void {
  localStorage.removeItem(ACCESS_TOKEN_KEY);
  localStorage.removeItem(REFRESH_TOKEN_KEY);
}

export const apiClient = axios.create({
  baseURL: '/api/v1',
  headers: { Accept: 'application/json' },
});

apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = getAccessToken();
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`);
  }
  return config;
});

interface RetriableConfig extends InternalAxiosRequestConfig {
  _retried?: boolean;
}

let refreshPromise: Promise<string> | null = null;

async function refreshAccessToken(): Promise<string> {
  const refreshToken = getRefreshToken();
  if (!refreshToken) {
    throw new Error('No refresh token available');
  }

  const response = await axios.post<ApiSuccess<AuthTokens>>('/api/v1/auth/refresh', {
    refresh_token: refreshToken,
  });

  const tokens = response.data.data;
  storeTokens(tokens);
  return tokens.access_token;
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const original = error.config as RetriableConfig | undefined;

    const isAuthEndpoint = original?.url?.includes('/auth/login') || original?.url?.includes('/auth/refresh');

    if (error.response?.status === 401 && original && !original._retried && !isAuthEndpoint) {
      original._retried = true;

      try {
        refreshPromise ??= refreshAccessToken().finally(() => {
          refreshPromise = null;
        });

        const newAccessToken = await refreshPromise;
        original.headers.set('Authorization', `Bearer ${newAccessToken}`);
        return apiClient(original);
      } catch {
        clearTokens();
        window.location.assign('/login');
        return Promise.reject(error);
      }
    }

    return Promise.reject(error);
  },
);

export function login(email: string, password: string) {
  return apiClient.post<ApiSuccess<LoginResponse>>('/auth/login', { email, password });
}

export function logout(refreshToken: string | null) {
  return apiClient.post('/auth/logout', refreshToken ? { refresh_token: refreshToken } : {});
}

export function fetchMe() {
  return apiClient.get<ApiSuccess<import('../types').User>>('/auth/me');
}
