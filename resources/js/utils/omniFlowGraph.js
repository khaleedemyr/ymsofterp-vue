export const TRIGGER_NODE_ID = 'trigger-1'

export function createTriggerNode() {
  return {
    id: TRIGGER_NODE_ID,
    type: 'omniFlow',
    position: { x: 120, y: 48 },
    data: {
      nodeType: 'trigger',
      label: 'Pesan masuk',
      config: {},
    },
  }
}

export function createEmptyDefinition() {
  return {
    version: 2,
    nodes: [createTriggerNode()],
    edges: [],
  }
}

export function newNodeId() {
  return `node-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
}

export function defaultNodeConfig(nodeType) {
  switch (nodeType) {
    case 'condition':
      return { match: 'all', rules: [{ field: 'message_contains', value: '' }] }
    case 'send_message':
      return { body: '' }
    case 'assign_team':
      return { _teams: [], team_ids: [] }
    case 'assign_users':
      return { _users: [], user_ids: [] }
    case 'set_lead_stage':
      return { lead_stage: 'new_lead' }
    case 'append_memo':
      return { text: '' }
    case 'notify_assignees':
      return { message: 'Chat masuk — otomasi inbox' }
    default:
      return {}
  }
}

export function nodeTypeLabel(nodeType, stepTypes = []) {
  if (nodeType === 'trigger') return 'Pesan masuk'
  return stepTypes.find((s) => s.value === nodeType)?.label || nodeType
}

export function nodeColorClass(nodeType, palette = []) {
  const item = palette.find((p) => p.value === nodeType)
  const color = item?.color || 'slate'
  const map = {
    amber: 'border-amber-300 bg-amber-50',
    emerald: 'border-emerald-300 bg-emerald-50',
    blue: 'border-blue-300 bg-blue-50',
    indigo: 'border-indigo-300 bg-indigo-50',
    violet: 'border-violet-300 bg-violet-50',
    slate: 'border-slate-300 bg-slate-50',
    rose: 'border-rose-300 bg-rose-50',
  }
  if (nodeType === 'trigger') return 'border-sky-400 bg-sky-50'
  return map[color] || 'border-slate-300 bg-white'
}

export function hydrateNodes(nodes, teams, users) {
  return (nodes || []).map((n) => {
    const data = { ...(n.data || {}) }
    const config = { ...(data.config || {}) }
    if (data.nodeType === 'assign_team') {
      const ids = config.team_ids || []
      config._teams = teams.filter((t) => ids.includes(t.id))
    }
    if (data.nodeType === 'assign_users') {
      const ids = config.user_ids || []
      config._users = users.filter((u) => ids.includes(u.id))
    }
    if (data.nodeType === 'condition' && !Array.isArray(config.rules)) {
      config.rules = []
    }
    return {
      ...n,
      type: n.type || 'omniFlow',
      data: { ...data, config },
    }
  })
}

function serializeNodeConfig(nodeType, config) {
  const out = { ...config }
  if (nodeType === 'assign_team') {
    out.team_ids = (out._teams || []).map((t) => t.id)
    delete out._teams
  }
  if (nodeType === 'assign_users') {
    out.user_ids = (out._users || []).map((u) => u.id)
    delete out._users
  }
  return out
}

export function serializeDefinition(nodes, edges) {
  return {
    version: 2,
    nodes: nodes.map((n) => ({
      id: n.id,
      type: n.type || 'omniFlow',
      position: { x: n.position.x, y: n.position.y },
      data: {
        nodeType: n.data?.nodeType,
        label: n.data?.label || nodeTypeLabel(n.data?.nodeType),
        config: serializeNodeConfig(n.data?.nodeType, n.data?.config || {}),
      },
    })),
    edges: edges.map((e) => ({
      id: e.id,
      source: e.source,
      target: e.target,
      sourceHandle: e.sourceHandle || 'default',
    })),
  }
}

export function ensureDefinition(definition) {
  if (definition?.nodes?.length) {
    const hasTrigger = definition.nodes.some((n) => n.data?.nodeType === 'trigger' || n.id === TRIGGER_NODE_ID)
    if (!hasTrigger) {
      return {
        ...definition,
        nodes: [createTriggerNode(), ...definition.nodes],
      }
    }
    return definition
  }
  return createEmptyDefinition()
}

export function createPaletteNode(nodeType, position, palette) {
  const label = palette.find((p) => p.value === nodeType)?.label || nodeType
  return {
    id: newNodeId(),
    type: 'omniFlow',
    position,
    data: {
      nodeType,
      label,
      config: defaultNodeConfig(nodeType),
    },
  }
}
