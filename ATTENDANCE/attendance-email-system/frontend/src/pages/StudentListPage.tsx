import { useEffect, useState } from 'react';
import { AxiosError } from 'axios';
import { apiClient } from '../api/client';
import type { ApiError, ApiSuccess, PageMeta, Student } from '../types';

interface StudentListResult {
  key: string;
  students: Student[];
  meta: PageMeta | null;
  error: string | null;
}

export function StudentListPage() {
  const [page, setPage] = useState(1);
  const [department, setDepartment] = useState('');
  const [semester, setSemester] = useState<number | ''>('');
  const [result, setResult] = useState<StudentListResult | null>(null);

  const queryKey = `${page}|${department}|${semester}`;

  useEffect(() => {
    let cancelled = false;

    apiClient
      .get<ApiSuccess<Student[]> & { meta: PageMeta }>('/students', {
        params: {
          page,
          ...(department ? { department } : {}),
          ...(semester ? { semester } : {}),
        },
      })
      .then((response) => {
        if (cancelled) return;
        setResult({ key: queryKey, students: response.data.data, meta: response.data.meta, error: null });
      })
      .catch((err: AxiosError<ApiError>) => {
        if (!cancelled) {
          setResult({ key: queryKey, students: [], meta: null, error: err.response?.data?.error?.message ?? 'Failed to load students.' });
        }
      });

    return () => {
      cancelled = true;
    };
  }, [page, department, semester, queryKey]);

  const loaded = result && result.key === queryKey ? result : null;
  const loading = !loaded;
  const students = loaded?.students ?? [];
  const meta = loaded?.meta ?? null;
  const error = loaded?.error ?? null;

  const totalPages = meta ? Math.max(1, Math.ceil(meta.total / meta.per_page)) : 1;

  return (
    <section>
      <h1>Students</h1>

      <div className="filter-row">
        <label>
          Department
          <input
            type="text"
            placeholder="e.g. CSE"
            value={department}
            onChange={(e) => {
              setPage(1);
              setDepartment(e.target.value);
            }}
          />
        </label>

        <label>
          Semester
          <select
            value={semester}
            onChange={(e) => {
              setPage(1);
              setSemester(e.target.value ? Number(e.target.value) : '');
            }}
          >
            <option value="">All</option>
            {[1, 2, 3, 4, 5, 6, 7, 8].map((sem) => (
              <option key={sem} value={sem}>
                Semester {sem}
              </option>
            ))}
          </select>
        </label>
      </div>

      {loading && <p>Loading…</p>}
      {error && <p className="form-error">{error}</p>}

      {!loading && students.length > 0 && (
        <>
          <table className="data-table">
            <thead>
              <tr>
                <th>Roll No.</th>
                <th>Name</th>
                <th>Email</th>
                <th>Department</th>
                <th>Semester</th>
              </tr>
            </thead>
            <tbody>
              {students.map((student) => (
                <tr key={student.student_id}>
                  <td>{student.roll_number}</td>
                  <td>{student.student_name}</td>
                  <td>{student.email}</td>
                  <td>{student.department}</td>
                  <td>{student.semester}</td>
                </tr>
              ))}
            </tbody>
          </table>

          {meta && (
            <div className="pagination">
              <button type="button" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page <= 1}>
                Previous
              </button>
              <span>
                Page {meta.page} of {totalPages} ({meta.total} students)
              </span>
              <button type="button" onClick={() => setPage((p) => Math.min(totalPages, p + 1))} disabled={page >= totalPages}>
                Next
              </button>
            </div>
          )}
        </>
      )}

      {!loading && students.length === 0 && !error && <p>No students found.</p>}
    </section>
  );
}
