<template>
  <div
    class="min-w-[168px] max-w-[220px] rounded-xl border-2 px-3 py-2 shadow-sm transition-shadow"
    :class="colorClass"
  >
    <Handle
      v-if="nodeType !== 'trigger'"
      type="target"
      :position="Position.Top"
      class="!h-2 !w-2 !border-2 !border-slate-400 !bg-white"
    />

    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
      {{ nodeType === 'trigger' ? 'Pemicu' : 'Langkah' }}
    </p>
    <p class="text-sm font-semibold text-slate-900">{{ data.label }}</p>
    <p v-if="summary" class="mt-1 line-clamp-2 text-[11px] text-slate-600">{{ summary }}</p>

    <template v-if="nodeType === 'condition'">
      <Handle
        id="true"
        type="source"
        :position="Position.Bottom"
        class="!left-[28%] !h-2 !w-2 !border-2 !border-emerald-500 !bg-emerald-100"
      />
      <Handle
        id="false"
        type="source"
        :position="Position.Bottom"
        class="!left-[72%] !h-2 !w-2 !border-2 !border-rose-400 !bg-rose-50"
      />
      <div class="mt-2 flex justify-between text-[9px] font-medium text-slate-500">
        <span>Ya</span>
        <span>Tidak</span>
      </div>
    </template>
    <Handle
      v-else
      type="source"
      :position="Position.Bottom"
      id="default"
      class="!h-2 !w-2 !border-2 !border-slate-400 !bg-white"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'
import { nodeColorClass } from '@/utils/omniFlowGraph'

const props = defineProps({
  data: { type: Object, required: true },
  nodePalette: { type: Array, default: () => [] },
})

const nodeType = computed(() => props.data?.nodeType || '')
const colorClass = computed(() => nodeColorClass(nodeType.value, props.nodePalette))

const summary = computed(() => {
  const cfg = props.data?.config || {}
  switch (nodeType.value) {
    case 'send_message':
      return cfg.body ? cfg.body.slice(0, 60) : '— isi pesan —'
    case 'condition': {
      const n = (cfg.rules || []).length
      return n ? `${n} aturan` : 'Tanpa aturan'
    }
    case 'assign_team':
      return (cfg._teams?.length || cfg.team_ids?.length)
        ? `${cfg._teams?.length || cfg.team_ids?.length} tim`
        : 'Pilih tim'
    case 'assign_users':
      return (cfg._users?.length || cfg.user_ids?.length)
        ? `${cfg._users?.length || cfg.user_ids?.length} user`
        : 'Pilih user'
    case 'set_lead_stage':
      return cfg.lead_stage || ''
    case 'append_memo':
      return cfg.text ? cfg.text.slice(0, 50) : '— memo —'
    case 'notify_assignees':
      return cfg.message || ''
    default:
      return ''
  }
})
</script>
