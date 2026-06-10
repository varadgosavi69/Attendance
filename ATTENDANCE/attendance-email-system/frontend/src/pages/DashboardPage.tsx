import { useEffect, useState } from 'react';
import { AxiosError } from 'axios';
import { apiClient } from '../api/client';
import { useAuth } from '../hooks/useAuth';
import type { ApiError, ApiSuccess, DashboardSummary } from '../types';

function formatLabel(key: string): string {
  return key
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return '—';
  if (typeof value === 'number') return Number.isInteger(value) ? String(value) : value.toFixed(2);
  if (typeof value === 'object') return JSON.stringify(value);
  return String(value);
}

export function DashboardPage() {
  const { user } = useAuth();
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    apiClient
      .get<ApiSuccess<DashboardSummary>>('/reports/dashboard')
      .then((response) => {
        if (!cancelled) setSummary(response.data.data);
      })
      .catch((err: AxiosError<ApiError>) => {
        if (!cancelled) setError(err.response?.data?.error?.message ?? 'Failed to load dashboard.');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <section>
      <h1>Dashboard</h1>
      <p className="page-subtitle">
        Welcome, {user?.full_name} — here's the {user?.role} summary.
      </p>

      {loading && <p>Loading…</p>}
      {error && <p className="form-error">{error}</p>}

      {summary && (
        <div className="stat-grid">
          {Object.entries(summary)
            .filter(([, value]) => typeof value !== 'object' || value === null)
            .map(([key, value]) => (
              <div className="stat-card" key={key}>
                <span className="stat-label">{formatLabel(key)}</span>
                <span className="stat-value">{formatValue(value)}</span>
              </div>
            ))}
        </div>
      )}

      {summary?.department_counts && (
        <>
          <h2>Department breakdown</h2>
          <table className="data-table">
            <thead>
              <tr>
                <th>Department</th>
                <th>Students</th>
              </tr>
            </thead>
            <tbody>
              {Object.entries(summary.department_counts).map(([dept, count]) => (
                <tr key={dept}>
                  <td>{dept}</td>
                  <td>{count}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </>
      )}
    </section>
  );
}
