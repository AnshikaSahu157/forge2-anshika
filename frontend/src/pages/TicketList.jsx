import { useState, useEffect, useCallback } from 'react'
import { Link } from 'react-router-dom'
import api from '../api'
import { useDebounce } from '../hooks/useDebounce'

export default function TicketList() {
  const [tickets, setTickets] = useState([])
  const [loading, setLoading] = useState(true)
  const [meta, setMeta] = useState(null)
  const [filters, setFilters] = useState({ status: '', priority: '', assignee_id: '', search: '' })
  const [searchInput, setSearchInput] = useState('')
  const debouncedSearch = useDebounce(searchInput, 300)
  const [page, setPage] = useState(1)
  const [showCreate, setShowCreate] = useState(false)
  const [createForm, setCreateForm] = useState({ subject: '', description: '', priority: 'medium' })
  const [creating, setCreating] = useState(false)
  const [createError, setCreateError] = useState('')

  const fetchTickets = useCallback(async () => {
    setLoading(true)
    try {
      const params = new URLSearchParams()
      params.set('page', page)
      if (filters.status) params.set('status', filters.status)
      if (filters.priority) params.set('priority', filters.priority)
      if (filters.assignee_id) params.set('assignee_id', filters.assignee_id)
      if (filters.search) params.set('search', filters.search)
      const { data } = await api.get(`/api/tickets?${params}`)
      setTickets(data.data || data)
      setMeta(data.meta || null)
    } catch (err) {
      console.error('Failed to load tickets', err)
    } finally {
      setLoading(false)
    }
  }, [page, filters])

  useEffect(() => { fetchTickets() }, [fetchTickets])

  // Update search filter when debounced value changes
  useEffect(() => {
    if (debouncedSearch !== filters.search) {
      setFilters((prev) => ({ ...prev, search: debouncedSearch }))
      setPage(1)
    }
  }, [debouncedSearch]) // eslint-disable-line react-hooks/exhaustive-deps

  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value }))
    setPage(1)
  }

  const clearFilters = () => {
    setFilters({ status: '', priority: '', assignee_id: '', search: '' })
    setPage(1)
  }

  const handleCreate = async (e) => {
    e.preventDefault()
    setCreateError('')
    setCreating(true)
    try {
      const { data } = await api.post('/api/tickets', createForm)
      setTickets((prev) => [data, ...prev])
      setShowCreate(false)
      setCreateForm({ subject: '', description: '', priority: 'medium' })
    } catch (err) {
      const errors = err.response?.data?.errors
      if (errors) {
        setCreateError(Object.values(errors).flat().join(' '))
      } else {
        setCreateError(err.response?.data?.message || 'Failed to create ticket')
      }
    } finally {
      setCreating(false)
    }
  }

  const statusBadge = (status) => {
    const colors = {
      open: 'bg-blue-100 text-blue-800',
      in_progress: 'bg-yellow-100 text-yellow-800',
      resolved: 'bg-green-100 text-green-800',
      closed: 'bg-gray-100 text-gray-800',
    }
    return colors[status] || 'bg-gray-100 text-gray-800'
  }

  const priorityBadge = (priority) => {
    const colors = {
      low: 'bg-gray-100 text-gray-700',
      medium: 'bg-blue-100 text-blue-700',
      high: 'bg-orange-100 text-orange-700',
      urgent: 'bg-red-100 text-red-700',
    }
    return colors[priority] || 'bg-gray-100 text-gray-700'
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Tickets</h1>
        <button
          onClick={() => setShowCreate(!showCreate)}
          className="bg-primary-600 text-white px-4 py-1.5 rounded-md text-sm hover:bg-primary-700 font-medium"
        >
          {showCreate ? 'Cancel' : '+ New Ticket'}
        </button>
      </div>

      {/* Create Ticket Form */}
      {showCreate && (
        <div className="bg-white rounded-lg shadow-sm border p-6">
          <h2 className="text-lg font-semibold mb-4">Create New Ticket</h2>
          {createError && (
            <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-2 rounded-md text-sm">{createError}</div>
          )}
          <form onSubmit={handleCreate} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Subject</label>
              <input
                type="text"
                value={createForm.subject}
                onChange={(e) => setCreateForm((p) => ({ ...p, subject: e.target.value }))}
                required
                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
                placeholder="Brief summary of the issue"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea
                value={createForm.description}
                onChange={(e) => setCreateForm((p) => ({ ...p, description: e.target.value }))}
                required
                rows={4}
                className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
                placeholder="Describe the issue in detail..."
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Priority</label>
              <select
                value={createForm.priority}
                onChange={(e) => setCreateForm((p) => ({ ...p, priority: e.target.value }))}
                className="px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
            <button
              type="submit"
              disabled={creating}
              className="bg-primary-600 text-white px-4 py-2 rounded-md text-sm hover:bg-primary-700 disabled:opacity-50 font-medium"
            >
              {creating ? 'Creating...' : 'Create Ticket'}
            </button>
          </form>
        </div>
      )}

      {/* Filters */}
      <div className="bg-white rounded-lg shadow-sm border p-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Search</label>
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Search subject..."
              className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select
              value={filters.status}
              onChange={(e) => handleFilterChange('status', e.target.value)}
              className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"
            >
              <option value="">All</option>
              <option value="open">Open</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Priority</label>
            <select
              value={filters.priority}
              onChange={(e) => handleFilterChange('priority', e.target.value)}
              className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"
            >
              <option value="">All</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">Assignee</label>
            <input
              type="text"
              value={filters.assignee_id}
              onChange={(e) => handleFilterChange('assignee_id', e.target.value)}
              placeholder="User ID"
              className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"
            />
          </div>
        </div>
        {(filters.status || filters.priority || filters.assignee_id || filters.search) && (
          <button onClick={clearFilters} className="mt-2 text-xs text-gray-500 hover:text-gray-700">Clear filters</button>
        )}
      </div>

      {/* Table */}
      <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
        {loading ? (
          <div className="p-8 text-center text-gray-500">Loading tickets...</div>
        ) : tickets.length === 0 ? (
          <div className="p-8 text-center text-gray-500">No tickets found.</div>
        ) : (
          <table className="w-full">
            <thead className="bg-gray-50 border-b">
              <tr>
                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Subject</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Priority</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Requester</th>
                <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase hidden md:table-cell">Assignee</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {tickets.map((ticket) => (
                <tr key={ticket.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3">
                    <Link to={`/tickets/${ticket.id}`} className="text-primary-600 hover:underline font-medium text-sm">
                      {ticket.subject}
                    </Link>
                    {ticket.tags?.length > 0 && (
                      <div className="flex gap-1 mt-1">
                        {ticket.tags.map((tag) => (
                          <span key={tag} className="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded">{tag}</span>
                        ))}
                      </div>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${statusBadge(ticket.status)}`}>
                      {ticket.status.replace('_', ' ')}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${priorityBadge(ticket.priority)}`}>
                      {ticket.priority}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-600 hidden md:table-cell">{ticket.requester?.name}</td>
                  <td className="px-4 py-3 text-sm text-gray-600 hidden md:table-cell">{ticket.assignee?.name || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between">
          <span className="text-sm text-gray-500">Page {meta.current_page} of {meta.last_page}</span>
          <div className="flex gap-2">
            <button
              onClick={() => setPage((p) => Math.max(1, p - 1))}
              disabled={meta.current_page === 1}
              className="px-3 py-1 text-sm border rounded-md disabled:opacity-50 hover:bg-gray-50"
            >Prev</button>
            <button
              onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))}
              disabled={meta.current_page === meta.last_page}
              className="px-3 py-1 text-sm border rounded-md disabled:opacity-50 hover:bg-gray-50"
            >Next</button>
          </div>
        </div>
      )}
    </div>
  )
}
