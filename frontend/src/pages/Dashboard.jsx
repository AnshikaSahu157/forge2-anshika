import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import api from '../api'

export default function Dashboard() {
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    (async () => {
      try {
        const { data } = await api.get('/api/tickets?per_page=100')
        const tickets = data.data || data

        const byStatus = { open: 0, in_progress: 0, resolved: 0, closed: 0 }
        const byPriority = { low: 0, medium: 0, high: 0, urgent: 0 }

        tickets.forEach((t) => {
          if (byStatus[t.status] !== undefined) byStatus[t.status]++
          if (byPriority[t.priority] !== undefined) byPriority[t.priority]++
        })

        // last 7 days
        const days = []
        for (let i = 6; i >= 0; i--) {
          const d = new Date()
          d.setDate(d.getDate() - i)
          const key = d.toISOString().slice(0, 10)
          days.push({ date: key, label: d.toLocaleDateString('en', { weekday: 'short', month: 'short', day: 'numeric' }), count: 0 })
        }
        tickets.forEach((t) => {
          const created = t.created_at?.slice(0, 10)
          const day = days.find((d) => d.date === created)
          if (day) day.count++
        })

        setStats({ byStatus, byPriority, perDay: days, total: tickets.length })
      } catch (err) {
        console.error('Dashboard load failed', err)
      } finally {
        setLoading(false)
      }
    })()
  }, [])

  if (loading) return <div className="text-gray-500">Loading dashboard...</div>

  const statusColors = {
    open: 'bg-blue-100 text-blue-800',
    in_progress: 'bg-yellow-100 text-yellow-800',
    resolved: 'bg-green-100 text-green-800',
    closed: 'bg-gray-100 text-gray-800',
  }

  const priorityColors = {
    low: 'bg-gray-100 text-gray-800',
    medium: 'bg-blue-100 text-blue-800',
    high: 'bg-orange-100 text-orange-800',
    urgent: 'bg-red-100 text-red-800',
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Dashboard</h1>
        <Link to="/tickets" className="text-primary-600 hover:underline text-sm font-medium">View all tickets →</Link>
      </div>

      {/* Status Cards */}
      <div>
        <h2 className="text-sm font-semibold text-gray-500 uppercase mb-3">Tickets by Status</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {Object.entries(stats.byStatus).map(([status, count]) => (
            <div key={status} className="bg-white rounded-lg shadow-sm border p-5">
              <div className={`inline-block px-2 py-0.5 rounded text-xs font-medium mb-2 ${statusColors[status]}`}>
                {status.replace('_', ' ')}
              </div>
              <div className="text-3xl font-bold">{count}</div>
            </div>
          ))}
        </div>
      </div>

      {/* Priority Cards */}
      <div>
        <h2 className="text-sm font-semibold text-gray-500 uppercase mb-3">Tickets by Priority</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {Object.entries(stats.byPriority).map(([priority, count]) => (
            <div key={priority} className="bg-white rounded-lg shadow-sm border p-5">
              <div className={`inline-block px-2 py-0.5 rounded text-xs font-medium mb-2 ${priorityColors[priority]}`}>
                {priority}
              </div>
              <div className="text-3xl font-bold">{count}</div>
            </div>
          ))}
        </div>
      </div>

      {/* Per Day Chart */}
      <div>
        <h2 className="text-sm font-semibold text-gray-500 uppercase mb-3">Tickets Created (Last 7 Days)</h2>
        <div className="bg-white rounded-lg shadow-sm border p-6">
          <div className="flex items-end justify-between gap-2 h-48">
            {stats.perDay.map((day) => {
              const maxCount = Math.max(...stats.perDay.map((d) => d.count), 1)
              const height = (day.count / maxCount) * 100
              return (
                <div key={day.date} className="flex-1 flex flex-col items-center gap-2">
                  <span className="text-xs font-medium text-gray-600">{day.count}</span>
                  <div className="w-full bg-primary-500 rounded-t-md transition-all" style={{ height: `${height}%`, minHeight: '4px' }}></div>
                  <span className="text-xs text-gray-400">{day.label}</span>
                </div>
              )
            })}
          </div>
        </div>
      </div>

      <div className="text-sm text-gray-400">Total tickets: {stats.total}</div>
    </div>
  )
}
