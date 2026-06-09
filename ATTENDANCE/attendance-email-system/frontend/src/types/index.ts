export type Role = 'admin' | 'teacher' | 'hod' | 'principal';

export interface ApiSuccess<T> {
  success: true;
  data: T;
  meta?: Record<string, unknown>;
}

export interface ApiError {
  success: false;
  error: {
    code: string;
    message: string;
    details?: unknown;
  };
}

export interface PageMeta {
  page: number;
  per_page: number;
  total: number;
  [key: string]: unknown;
}

export interface User {
  user_id: number;
  username: string;
  email: string;
  full_name: string;
  role: Role;
  faculty_id: number | null;
  department: string | null;
}

export interface AuthTokens {
  access_token: string;
  refresh_token: string;
  token_type: string;
  expires_in: number;
}

export interface LoginResponse extends AuthTokens {
  user: User;
}

export interface Student {
  student_id: number;
  roll_number: string;
  student_name: string;
  email: string;
  parent_email?: string | null;
  department: string;
  semester: number;
}

export interface Subject {
  subject_id: number;
  subject_name: string;
  subject_code: string;
  department: string;
  semester: number;
}

export type AttendanceStatus = 'Present' | 'Absent' | 'Leave';

export interface DashboardSummary {
  [key: string]: unknown;
  total_students?: number;
  avg_attendance?: number | string;
  department?: string;
  department_counts?: Record<string, number>;
}

export interface DetainedStudent {
  detention_id: number;
  student_id: number;
  month: string;
  total_classes: number;
  attended_classes: number;
  attendance_percentage: number | string;
  is_detained: boolean;
  student?: Student;
}
