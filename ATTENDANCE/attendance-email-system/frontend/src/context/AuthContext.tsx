import { useCallback, useEffect, useState, type ReactNode } from 'react';
import type { ApiError, User } from '../types';
import { clearTokens, fetchMe, getAccessToken, getRefreshToken, login as loginRequest, logout as logoutRequest, storeTokens } from '../api/client';
import { AxiosError } from 'axios';
import { AuthContext } from './auth-context';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    async function bootstrap() {
      if (!getAccessToken()) {
        setIsLoading(false);
        return;
      }

      try {
        const response = await fetchMe();
        if (!cancelled) setUser(response.data.data);
      } catch {
        if (!cancelled) clearTokens();
      } finally {
        if (!cancelled) setIsLoading(false);
      }
    }

    void bootstrap();
    return () => {
      cancelled = true;
    };
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    try {
      const response = await loginRequest(email, password);
      const { user: loggedInUser, ...tokens } = response.data.data;
      storeTokens(tokens);
      setUser(loggedInUser);
    } catch (err) {
      if (err instanceof AxiosError) {
        const apiError = err.response?.data as ApiError | undefined;
        throw new Error(apiError?.error?.message ?? 'Login failed.', { cause: err });
      }
      throw err;
    }
  }, []);

  const logout = useCallback(async () => {
    try {
      await logoutRequest(getRefreshToken());
    } catch {
      // ignore — we clear local state regardless
    }
    clearTokens();
    setUser(null);
  }, []);

  return <AuthContext.Provider value={{ user, isLoading, login, logout }}>{children}</AuthContext.Provider>;
}
