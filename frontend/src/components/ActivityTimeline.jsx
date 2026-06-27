function ActivityTimeline({ activities }) {
  if (!activities || activities.length === 0) {
    return <p className="text-sm text-gray-400">No activity yet.</p>
  }

  const icon = (action) => {
    const map = {
      created: '🟢',
      updated: '✏️',
      assigned: '👤',
      claimed: '✋',
      commented: '💬',
      deleted: '🗑️',
      status_changed: '🔄',
    }
    return map[action] || '•'
  }

  return (
    <div className="relative pl-6">
      <div className="absolute left-2 top-2 bottom-2 w-px bg-gray-200"></div>
      <div className="space-y-4">
        {activities.map((log) => (
          <div key={log.id} className="relative">
            <div className="absolute -left-4 w-3 h-3 rounded-full bg-primary-500 border-2 border-white"></div>
            <div className="ml-2">
              <div className="flex items-center gap-2">
                <span className="text-sm font-medium">{log.user?.name}</span>
                <span className="text-xs text-gray-400">
                  {icon(log.action)} {log.action.replace(/_/g, ' ')}
                </span>
                <span className="text-xs text-gray-400">{new Date(log.created_at).toLocaleString()}</span>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

export default ActivityTimeline
