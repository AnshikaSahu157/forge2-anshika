import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, Legend } from 'recharts'
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

        const statusData = Object.entries(byStatus).map(([name, value]) => ({
          name: name.replace('_', ' '),
          value,
        }))

        const priorityData = Object.entries(byPriority).map(([name, value]) => ({
          name,
          value,
        }))

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

        const perDayData = days.map((d) => ({ name: d.label, tickets: d.count }))

        setStats({ statusData, priorityData, perDayData, total: tickets.length })
      } catch (err) {
        console.error('Dashboard load failed', err)
      } finally {
        setLoading(false)
      }
    })()
  }, [])

  if (loading) return <div className="text-gray-500">Loading dashboard...</div>

  const statusColors = ['#3b82f6', '#eab308', '#22c55e', '#9ca3af']
  const priorityColors = ['#9ca3af', '#3b82f6', '#f97316', '#ef4444']

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Dashboard</h1>
        <Link to="/tickets" className="text-primary-600 hover:underline text-sm font-medium">View all tickets →</Link>
      </div>

      {/* Charts Row */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Status Pie Chart */}
        <div className="bg-white rounded-lg shadow-sm border p-6">
          <h2 className="text-sm font-semibold text-gray-500 uppercase mb-4">Tickets by Status</h2>
          <ResponsiveContainer width="100%" height={250}>
            <PieChart>
              <Pie data={stats.statusData} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={80} label>
                {stats.statusData.map((_, index) => (
                  <Cell key={index} fill={statusColors[index % statusColors.length]} />
                ))}
              </Pie>
              <Tooltip />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        </div>

        {/* Priority Pie Chart */}
        <div className="bg-white rounded-lg shadow-sm border p-6">
          <h2 className="text-sm font-semibold text-gray-500 uppercase mb-4">Tickets by Priority</h2>
          <ResponsiveContainer width="100%" height={250}>
            <PieChart>
              <Pie data={stats.priorityData} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={80} label>
                {stats.priorityData.map((_, index) => (
                  <Cell key={index} fill={priorityColors[index % priorityColors.length]} />
                ))}
              </Pie>
              <Tooltip />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        </div>
      </div>

      {/* Per Day Bar Chart */}
      <div className="bg-white rounded-lg shadow-sm border p-6">
        <h2 className="text-sm font-semibold text-gray-500 uppercase mb-4">Tickets Created (Last 7 Days)</h2>
        <ResponsiveContainer width="100%" height={300}>
          <BarChart data={stats.perDayData}>
            <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
            <XAxis dataKey="name" tick={{ fontSize: 12 }} />
            <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
            <Tooltip />
            <Bar dataKey="tickets" fill="#3b82f6" radius={[4, 4, 0, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </div>

      <div className="text-sm text-gray-400">Total tickets: {stats.total}</div>
    </div>
  )
}
