import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

export function Layout() {
  const { user, logout } = useAuth();

  return (
    <div className="app-shell">
      <header className="app-header">
        <span className="app-title">Attendance System</span>
        <nav className="app-nav">
          <NavLink to="/dashboard">Dashboard</NavLink>
          {(user?.role === 'teacher' || user?.role === 'admin') && <NavLink to="/attendance">Mark Attendance</NavLink>}
          <NavLink to="/students">Students</NavLink>
          {(user?.role === 'hod' || user?.role === 'principal') && <NavLink to="/detention">Detention Report</NavLink>}
        </nav>
        <div className="app-user">
          <span>
            {user?.full_name} <em>({user?.role})</em>
          </span>
          <button type="button" onClick={() => void logout()}>
            Log out
          </button>
        </div>
      </header>
      <main className="app-content">
        <Outlet />
      </main>
    </div>
  );
}
