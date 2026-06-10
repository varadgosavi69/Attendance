import { useEffect, useMemo, useState } from 'react';
import { AxiosError } from 'axios';
import { apiClient } from '../api/client';
import type { ApiError, ApiSuccess, AttendanceStatus, Student, Subject } from '../types';

const STATUSES: AttendanceStatus[] = ['Present', 'Absent', 'Leave'];

export function MarkAttendancePage() {
  const [subjects, setSubjects] = useState<Subject[]>([]);
  const [subjectId, setSubjectId] = useState<number | ''>('');
  const [date, setDate] = useState(() => new Date().toISOString().slice(0, 10));

  const [roster, setRoster] = useState<{ subjectId: number; students: Student[]; error: string | null } | null>(null);
  const [statuses, setStatuses] = useState<Record<number, AttendanceStatus>>({});

  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const selectedSubject = useMemo(() => subjects.find((s) => s.subject_id === subjectId), [subjects, subjectId]);

  useEffect(() => {
    apiClient
      .get<ApiSuccess<Subject[]>>('/attendance/subjects')
      .then((response) => {
        setSubjects(response.data.data);
        if (response.data.data.length > 0) setSubjectId(response.data.data[0].subject_id);
      })
      .catch((err: AxiosError<ApiError>) => setError(err.response?.data?.error?.message ?? 'Failed to load subjects.'));
  }, []);

  useEffect(() => {
    if (!selectedSubject) return;

    let cancelled = false;

    apiClient
      .get<ApiSuccess<Student[]>>('/attendance/students', { params: { semester: selectedSubject.semester, branch: selectedSubject.department } })
      .then((response) => {
        if (cancelled) return;
        setRoster({ subjectId: selectedSubject.subject_id, students: response.data.data, error: null });
        setStatuses(Object.fromEntries(response.data.data.map((s) => [s.student_id, 'Present' as AttendanceStatus])));
      })
      .catch((err: AxiosError<ApiError>) => {
        if (!cancelled) {
          setRoster({ subjectId: selectedSubject.subject_id, students: [], error: err.response?.data?.error?.message ?? 'Failed to load students.' });
        }
      });

    return () => {
      cancelled = true;
    };
  }, [selectedSubject]);

  const currentRoster = selectedSubject && roster && roster.subjectId === selectedSubject.subject_id ? roster : null;
  const students = currentRoster?.students ?? [];
  const loadingStudents = selectedSubject !== undefined && currentRoster === null;
  const displayError = currentRoster?.error ?? error;

  function setStatus(studentId: number, status: AttendanceStatus) {
    setStatuses((prev) => ({ ...prev, [studentId]: status }));
  }

  async function handleSubmit() {
    if (!subjectId) return;

    setSubmitting(true);
    setError(null);
    setMessage(null);

    try {
      const response = await apiClient.post<ApiSuccess<{ message: string; count: number }>>('/attendance', {
        subject_id: subjectId,
        date,
        records: statuses,
      });
      setMessage(response.data.data.message);
    } catch (err) {
      if (err instanceof AxiosError) {
        setError(err.response?.data?.error?.message ?? 'Failed to mark attendance.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section>
      <h1>Mark Attendance</h1>

      <div className="filter-row">
        <label>
          Subject
          <select
            value={subjectId}
            onChange={(e) => {
              setSubjectId(e.target.value ? Number(e.target.value) : '');
              setError(null);
              setMessage(null);
            }}
          >
            {subjects.map((subject) => (
              <option key={subject.subject_id} value={subject.subject_id}>
                {subject.subject_name} ({subject.subject_code}) — {subject.department} sem {subject.semester}
              </option>
            ))}
          </select>
        </label>

        <label>
          Date
          <input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
        </label>
      </div>

      {displayError && <p className="form-error">{displayError}</p>}
      {message && <p className="form-success">{message}</p>}
      {loadingStudents && <p>Loading students…</p>}

      {!loadingStudents && students.length > 0 && (
        <>
          <table className="data-table">
            <thead>
              <tr>
                <th>Roll No.</th>
                <th>Student</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {students.map((student) => (
                <tr key={student.student_id}>
                  <td>{student.roll_number}</td>
                  <td>{student.student_name}</td>
                  <td>
                    <div className="status-toggle">
                      {STATUSES.map((status) => (
                        <label key={status}>
                          <input
                            type="radio"
                            name={`status-${student.student_id}`}
                            checked={statuses[student.student_id] === status}
                            onChange={() => setStatus(student.student_id, status)}
                          />
                          {status}
                        </label>
                      ))}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          <button type="button" onClick={() => void handleSubmit()} disabled={submitting}>
            {submitting ? 'Saving…' : 'Save attendance'}
          </button>
        </>
      )}

      {!loadingStudents && subjectId !== '' && students.length === 0 && <p>No students found for this subject's class.</p>}
    </section>
  );
}
