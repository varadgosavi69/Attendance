import { useEffect, useState } from 'react';
import { AxiosError } from 'axios';
import { apiClient } from '../api/client';
import type { ApiError, ApiSuccess, PageMeta, Student } from '../types';

export function StudentListPage() {
  const [students, setStudents] = useState<Student[]>([]);
  const [meta, setMeta] = useState<PageMeta | null>(null);
  const [page, setPage] = useState(1);
  const [department, setDepartment] = useState('');
  const [semester, setSemester] = useState<number | ''>('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

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
        setStudents(response.data.data);
        setMeta(response.data.meta);
      })
      .catch((err: AxiosError<ApiError>) => {
        if (!cancelled) setError(err.response?.data?.error?.message ?? 'Failed to load students.');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [page, department, semester]);

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
