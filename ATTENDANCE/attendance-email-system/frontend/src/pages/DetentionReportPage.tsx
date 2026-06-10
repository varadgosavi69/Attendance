import { useEffect, useState } from 'react';
import { AxiosError } from 'axios';
import { apiClient } from '../api/client';
import { useAuth } from '../hooks/useAuth';
import type { ApiError, ApiSuccess, DetainedStudent, PageMeta } from '../types';

function lastMonth(): string {
  const d = new Date();
  d.setMonth(d.getMonth() - 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

interface DetentionReportResult {
  key: string;
  records: DetainedStudent[];
  meta: PageMeta | null;
  error: string | null;
}

export function DetentionReportPage() {
  const { user } = useAuth();
  const [month, setMonth] = useState(lastMonth);
  const [refreshKey, setRefreshKey] = useState(0);
  const [report, setReport] = useState<DetentionReportResult | null>(null);
  const [generateError, setGenerateError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [generating, setGenerating] = useState(false);
  const [sendEmails, setSendEmails] = useState(false);

  const queryKey = `${month}#${refreshKey}`;

  useEffect(() => {
    let cancelled = false;

    apiClient
      .get<ApiSuccess<DetainedStudent[]> & { meta: PageMeta }>('/reports/detention', { params: { month } })
      .then((response) => {
        if (cancelled) return;
        setReport({ key: queryKey, records: response.data.data, meta: response.data.meta, error: null });
      })
      .catch((err: AxiosError<ApiError>) => {
        if (!cancelled) {
          setReport({ key: queryKey, records: [], meta: null, error: err.response?.data?.error?.message ?? 'Failed to load detention report.' });
        }
      });

    return () => {
      cancelled = true;
    };
  }, [month, queryKey]);

  const loaded = report && report.key === queryKey ? report : null;
  const loading = !loaded;
  const records = loaded?.records ?? [];
  const meta = loaded?.meta ?? null;
  const error = loaded ? (loaded.error ?? generateError) : null;

  async function handleGenerate() {
    setGenerating(true);
    setGenerateError(null);
    setMessage(null);

    const [year, monthNum] = month.split('-').map(Number);

    try {
      const response = await apiClient.post<
        ApiSuccess<{ detained_count: number; emails_queued: number; emails_skipped: number; total_students: number }>
      >('/reports/detention/generate', { year, month: monthNum, send_emails: sendEmails });

      const { detained_count, emails_queued, emails_skipped, total_students } = response.data.data;
      setMessage(
        `Generated for ${total_students} students — ${detained_count} detained` +
          (sendEmails ? `, ${emails_queued} email(s) queued, ${emails_skipped} skipped` : '') +
          '.',
      );
      setRefreshKey((key) => key + 1);
    } catch (err) {
      if (err instanceof AxiosError) {
        setGenerateError(err.response?.data?.error?.message ?? 'Failed to generate detention report.');
      }
    } finally {
      setGenerating(false);
    }
  }

  return (
    <section>
      <h1>Detention Report</h1>

      <div className="filter-row">
        <label>
          Month
          <input
            type="month"
            value={month}
            onChange={(e) => {
              setMonth(e.target.value);
              setGenerateError(null);
            }}
          />
        </label>

        {user?.role === 'principal' && (
          <>
            <label className="checkbox-label">
              <input type="checkbox" checked={sendEmails} onChange={(e) => setSendEmails(e.target.checked)} />
              Send notification emails
            </label>
            <button type="button" onClick={() => void handleGenerate()} disabled={generating}>
              {generating ? 'Generating…' : 'Generate report'}
            </button>
          </>
        )}
      </div>

      {error && <p className="form-error">{error}</p>}
      {message && <p className="form-success">{message}</p>}
      {loading && <p>Loading…</p>}

      {!loading && records.length > 0 && (
        <table className="data-table">
          <thead>
            <tr>
              <th>Roll No.</th>
              <th>Name</th>
              <th>Department</th>
              <th>Semester</th>
              <th>Attendance %</th>
              <th>Classes Attended</th>
            </tr>
          </thead>
          <tbody>
            {records.map((record) => (
              <tr key={record.detention_id}>
                <td>{record.student?.roll_number ?? record.student_id}</td>
                <td>{record.student?.student_name ?? '—'}</td>
                <td>{record.student?.department ?? '—'}</td>
                <td>{record.student?.semester ?? '—'}</td>
                <td>{Number(record.attendance_percentage).toFixed(2)}%</td>
                <td>
                  {record.attended_classes} / {record.total_classes}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {!loading && records.length === 0 && !error && (
        <p>
          No detained students for {month}
          {meta?.threshold ? ` (threshold: ${meta.threshold}%)` : ''}.
        </p>
      )}
    </section>
  );
}
