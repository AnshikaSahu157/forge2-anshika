function SlaBadge({ slaStatus }) {
  if (!slaStatus || !slaStatus.has_policy) return null

  const styles = {
    ok: 'bg-green-100 text-green-800',
    warning: 'bg-yellow-100 text-yellow-800',
    breached: 'bg-red-100 text-red-800',
    none: 'bg-gray-100 text-gray-600',
  }

  const labels = {
    ok: 'SLA OK',
    warning: `SLA ${slaStatus.percent_remaining}% left`,
    breached: 'SLA BREACHED',
  }

  return (
    <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${styles[slaStatus.status] || styles.none}`}>
      {labels[slaStatus.status] || 'SLA'}
    </span>
  )
}

export default SlaBadge
