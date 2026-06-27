import { useState, useEffect, useCallback } from 'react'
import { useParams, Link } from 'react-router-dom'
import api from '../api'
import { useAuth } from '../AuthContext'

export default function TicketDetail() {
  const { id } = useParams()
  const { isAgent } = useAuth()
  const [ticket, setTicket] = useState(null)
  const [comments, setComments] = useState([])
  const [loading, setLoading] = useState(true)
  const [reply, setReply] = useState('')
  const [isInternal, setIsInternal] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [editing, setEditing] = useState(false)
  const [editForm, setEditForm] = useState({ status: '', priority: '' })

  const fetchTicket = useCallback(async () => {
    setLoading(true)
    try {
      const [ticketRes, commentsRes] = await Promise.all([
        api.get(`/api/tickets/${id}`),
        api.get(`/api/tickets/${id}/comments`),
      ])
      setTicket(ticketRes.data)
      setComments(commentsRes.data)
      setEditForm({ status: ticketRes.data.status, priority: ticketRes.data.priority })
    } catch (err) {
      console.error('Failed to load ticket', err)
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => { fetchTicket() }, [fetchTicket])

  const handleReply = async (e) => {
    e.preventDefault()
    if (!reply.trim()) return
    setSubmitting(true)
    try {
      const payload = { body: reply }
      if (isInternal && isAgent()) payload.is_internal = true
      const { data } = await api.post(`/api/tickets/${id}/comments`, payload)
      setComments((prev) => [...prev, data])
      setReply('')
      setIsInternal(false)
    } catch (err) {
      console.error('Failed to post comment', err)
    } finally {
      setSubmitting(false)
    }
  }

  const handleUpdate = async (e) => {
    e.preventDefault()
    try {
      const { data } = await api.put(`/api/tickets/${id}`, editForm)
      setTicket(data)
      setEditing(false)
    } catch (err) {
      console.error('Failed to update ticket', err)
    }
  }

  if (loading) return <div className="text-gray-500">Loading ticket...</div>
  if (!ticket) return <div className="text-gray-500">Ticket not found.</div>

  const statusBadge = (status) => {
    const colors = {
      open: 'bg-blue-100 text-blue-800',
      in_progress: 'bg-yellow-100 text-yellow-800',
      resolved: 'bg-green-100 text-green-800',
      closed: 'bg-gray-100 text-gray-800',
    }
    return colors[status] || 'bg-gray-100'
  }

  const priorityBadge = (priority) => {
    const colors = {
      low: 'bg-gray-100 text-gray-700',
      medium: 'bg-blue-100 text-blue-700',
      high: 'bg-orange-100 text-orange-700',
      urgent: 'bg-red-100 text-red-700',
    }
    return colors[priority] || 'bg-gray-100'
  }

  return (
    <div className="space-y-6">
      <div>
        <Link to="/tickets" className="text-sm text-gray-500 hover:text-gray-700">← Back to Tickets</Link>
      </div>

      {/* Ticket Header */}
      <div className="bg-white rounded-lg shadow-sm border p-6">
        <div className="flex items-start justify-between mb-4">
          <div>
            <h1 className="text-xl font-bold mb-2">{ticket.subject}</h1>
            <div className="flex flex-wrap gap-2">
              <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusBadge(ticket.status)}`}>
                {ticket.status.replace('_', ' ')}
              </span>
              <span className={`px-2 py-0.5 rounded text-xs font-medium ${priorityBadge(ticket.priority)}`}>
                {ticket.priority}
              </span>
            </div>
          </div>
          {isAgent() && (
            <button
              onClick={() => setEditing(!editing)}
              className="text-sm text-primary-600 hover:underline"
            >
              {editing ? 'Cancel' : 'Edit'}
            </button>
          )}
        </div>

        {/* Edit Form */}
        {editing ? (
          <form onSubmit={handleUpdate} className="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Status</label>
              <select
                value={editForm.status}
                onChange={(e) => setEditForm((p) => ({ ...p, status: e.target.value }))}
                className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md"
              >
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Priority</label>
              <select
                value={editForm.priority}
                onChange={(e) => setEditForm((p) => ({ ...p, priority: e.target.value }))}
                className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-md"
              >
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
            <div className="col-span-2">
              <button type="submit" className="bg-primary-600 text-white px-4 py-1.5 rounded-md text-sm hover:bg-primary-700">Save</button>
            </div>
          </form>
        ) : null}

        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mt-4">
          <div>
            <span className="text-gray-400">Requester</span>
            <p className="font-medium">{ticket.requester?.name}</p>
          </div>
          <div>
            <span className="text-gray-400">Assignee</span>
            <p className="font-medium">{ticket.assignee?.name || 'Unassigned'}</p>
          </div>
          <div>
            <span className="text-gray-400">Created</span>
            <p className="font-medium">{new Date(ticket.created_at).toLocaleDateString()}</p>
          </div>
          <div>
            <span className="text-gray-400">Tags</span>
            <p className="font-medium">{ticket.tags?.join(', ') || '—'}</p>
          </div>
        </div>

        <div className="mt-4 pt-4 border-t">
          <h3 className="text-xs font-semibold text-gray-400 uppercase mb-2">Description</h3>
          <p className="text-sm text-gray-700 whitespace-pre-wrap">{ticket.description}</p>
        </div>
      </div>

      {/* Conversation Thread */}
      <div className="bg-white rounded-lg shadow-sm border p-6">
        <h2 className="text-lg font-semibold mb-4">Conversation ({comments.length})</h2>
        {comments.length === 0 ? (
          <p className="text-sm text-gray-400">No comments yet.</p>
        ) : (
          <div className="space-y-4">
            {comments.map((comment) => (
              <div
                key={comment.id}
                className={`p-4 rounded-lg ${comment.is_internal ? 'bg-yellow-50 border border-yellow-200' : 'bg-gray-50 border border-gray-200'}`}
              >
                <div className="flex items-center justify-between mb-2">
                  <span className="text-sm font-medium">{comment.user?.name}</span>
                  <div className="flex items-center gap-2">
                    {comment.is_internal && (
                      <span className="text-xs bg-yellow-200 text-yellow-800 px-1.5 py-0.5 rounded font-medium">Internal</span>
                    )}
                    <span className="text-xs text-gray-400">{new Date(comment.created_at).toLocaleString()}</span>
                  </div>
                </div>
                <p className="text-sm text-gray-700 whitespace-pre-wrap">{comment.body}</p>
              </div>
            ))}
          </div>
        )}

        {/* Reply Form */}
        <form onSubmit={handleReply} className="mt-6">
          <textarea
            value={reply}
            onChange={(e) => setReply(e.target.value)}
            placeholder="Write a reply..."
            rows={3}
            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
          />
          <div className="flex items-center justify-between mt-2">
            {isAgent() && (
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={isInternal}
                  onChange={(e) => setIsInternal(e.target.checked)}
                  className="rounded"
                />
                Internal note
              </label>
            )}
            <button
              type="submit"
              disabled={submitting || !reply.trim()}
              className="bg-primary-600 text-white px-4 py-1.5 rounded-md text-sm hover:bg-primary-700 disabled:opacity-50 font-medium"
            >
              {submitting ? 'Posting...' : 'Post Reply'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
