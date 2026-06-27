import { Outlet, NavLink } from 'react-router-dom'
import { useAuth } from './AuthContext'
import NotificationBell from './components/NotificationBell'

export default function App() {
  const { user, logout } = useAuth()

  const navLink = ({ isActive }) =>
    `px-3 py-2 rounded-md text-sm font-medium ${isActive ? 'bg-primary-600 text-white' : 'text-gray-700 hover:bg-gray-200'}`

  return (
    <div className="min-h-screen">
      <nav className="bg-white shadow-sm border-b">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex items-center space-x-4">
              <span className="text-xl font-bold text-primary-600">PulseDesk</span>
              <div className="flex space-x-2">
                <NavLink to="/dashboard" className={navLink}>Dashboard</NavLink>
                <NavLink to="/tickets" className={navLink}>Tickets</NavLink>
              </div>
            </div>
            <div className="flex items-center space-x-4">
              <NotificationBell />
              <span className="text-sm text-gray-600">{user?.name} ({user?.role})</span>
              <button onClick={logout} className="text-sm text-red-600 hover:text-red-800 font-medium">Logout</button>
            </div>
          </div>
        </div>
      </nav>
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Outlet />
      </main>
    </div>
  )
}
