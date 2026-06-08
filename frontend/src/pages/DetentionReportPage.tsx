import { useEffect, useState } from 'react';
import { AxiosError } from 'axios';
import { apiClient } from '../api/client';
import { useAuth } from '../context/AuthContext';
import type { ApiError, ApiSuccess, DetainedStudent, PageMeta } from '../types';

function lastMonth(): string {
  const d = new Date();
  d.setMonth(d.getMonth() - 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export function DetentionReportPage() {
  const { user } = useAuth();
  const [month, setMonth] = useState(lastMonth);
  const [records, setRecords] = useState<DetainedStudent[]>([]);
  const [meta, setMeta] = useState<PageMeta | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [sendEmails, setSendEmails] = useState(false);

  function load() {
    setLoading(true);
    setError(null);

    apiClient
      .get<ApiSuccess<DetainedStudent[]> & { meta: PageMeta }>('/reports/detention', { params: { month } })
      .then((response) => {
        setRecords(response.data.data);
        setMeta(response.data.meta);
      })
      .catch((err: AxiosError<ApiError>) => setError(err.response?.data?.error?.message ?? 'Failed to load detention report.'))
      .finally(() => setLoading(false));
  }

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [month]);

  async function handleGenerate() {
    setGenerating(true);
    setError(null);
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
      load();
    } catch (err) {
      if (err instanceof AxiosError) {
        setError(err.response?.data?.error?.message ?? 'Failed to generate detention report.');
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
          <input type="month" value={month} onChange={(e) => setMonth(e.target.value)} />
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
