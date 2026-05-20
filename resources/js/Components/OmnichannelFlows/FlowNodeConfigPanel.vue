<template>
  <div v-if="!node" class="flex h-full items-center justify-center p-4 text-center text-sm text-slate-500">
    Klik node di canvas untuk mengatur detail langkah.
  </div>
  <div v-else-if="node.data.nodeType === 'trigger'" class="p-4 text-sm text-slate-600">
    <p class="font-semibold text-slate-800">Pemicu: Pesan masuk</p>
    <p class="mt-2 text-xs">Setiap pesan masuk dari pelanggan dapat memicu flow ini (sesuai prioritas & channel).</p>
  </div>
  <div v-else class="space-y-3 overflow-y-auto p-4">
    <p class="text-sm font-semibold text-slate-800">{{ node.data.label }}</p>

    <div v-if="node.data.nodeType === 'condition'" class="space-y-2">
      <select v-model="node.data.config.match" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
        <option value="all">Semua aturan harus cocok</option>
        <option value="any">Salah satu aturan cocok</option>
      </select>
      <div
        v-for="(rule, ri) in node.data.config.rules"
        :key="ri"
        class="space-y-1 rounded-lg border border-slate-200 bg-slate-50 p-2"
      >
        <select v-model="rule.field" class="w-full rounded border border-slate-200 px-2 py-1 text-xs">
          <option v-for="cf in conditionFields" :key="cf.value" :value="cf.value">{{ cf.label }}</option>
        </select>
        <template v-if="rule.field === 'hour_between'">
          <div class="flex items-center gap-2">
            <input v-model.number="rule.from" type="number" min="0" max="23" class="w-14 rounded border px-1 text-xs" />
            <span class="text-xs">–</span>
            <input v-model.number="rule.to" type="number" min="0" max="23" class="w-14 rounded border px-1 text-xs" />
            <span class="text-[10px] text-slate-500">WIB</span>
          </div>
        </template>
        <template v-else-if="rule.field === 'no_assignee'">
          <span class="text-xs text-slate-500">Belum ada penugasan</span>
        </template>
        <template v-else-if="rule.field === 'has_member'">
          <select v-model="rule.value" class="w-full rounded border px-2 py-1 text-xs">
            <option :value="true">Ya</option>
            <option :value="false">Tidak</option>
          </select>
        </template>
        <template v-else-if="rule.field === 'lead_stage'">
          <select v-model="rule.value" class="w-full rounded border px-2 py-1 text-xs">
            <option v-for="ls in leadStages" :key="ls.value" :value="ls.value">{{ ls.label }}</option>
          </select>
        </template>
        <template v-else>
          <input v-model="rule.value" type="text" class="w-full rounded border px-2 py-1 text-xs" placeholder="nilai" />
        </template>
        <button type="button" class="text-xs text-red-600" @click="node.data.config.rules.splice(ri, 1)">Hapus aturan</button>
      </div>
      <button type="button" class="text-xs font-medium text-emerald-700" @click="addRule">+ Aturan</button>
      <p class="text-[10px] text-slate-500">Cabang <strong>Ya</strong> / <strong>Tidak</strong> di node kondisi.</p>
    </div>

    <div v-else-if="node.data.nodeType === 'send_message'">
      <textarea
        v-model="node.data.config.body"
        rows="5"
        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"
        placeholder="Isi pesan... {{nama}} {{nomor}}"
      />
    </div>

    <div v-else-if="node.data.nodeType === 'assign_team'">
      <Multiselect
        v-model="node.data.config._teams"
        :options="teams"
        :multiple="true"
        label="name"
        track-by="id"
        placeholder="Pilih tim..."
        class="text-sm"
      />
    </div>

    <div v-else-if="node.data.nodeType === 'assign_users'">
      <Multiselect
        v-model="node.data.config._users"
        :options="users"
        :custom-label="formatUserLabel"
        :multiple="true"
        label="name"
        track-by="id"
        placeholder="Pilih user..."
        class="text-sm"
      />
    </div>

    <div v-else-if="node.data.nodeType === 'set_lead_stage'">
      <select v-model="node.data.config.lead_stage" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
        <option v-for="ls in leadStages" :key="ls.value" :value="ls.value">{{ ls.label }}</option>
      </select>
    </div>

    <div v-else-if="node.data.nodeType === 'append_memo'">
      <textarea v-model="node.data.config.text" rows="3" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
    </div>

    <div v-else-if="node.data.nodeType === 'notify_assignees'">
      <input
        v-model="node.data.config.message"
        type="text"
        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"
        placeholder="Teks notifikasi..."
      />
    </div>

    <button
      v-if="node.data.nodeType !== 'trigger'"
      type="button"
      class="w-full rounded-lg border border-red-200 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
      @click="$emit('remove-node', node.id)"
    >
      Hapus node
    </button>
  </div>
</template>

<script setup>
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  node: { type: Object, default: null },
  teams: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  leadStages: { type: Array, default: () => [] },
  conditionFields: { type: Array, default: () => [] },
})

defineEmits(['remove-node'])

function formatUserLabel(opt) {
  if (!opt) return ''
  const bits = [opt.jabatan, opt.outlet].filter(Boolean)
  return bits.length ? `${opt.name} — ${bits.join(' · ')}` : opt.name
}

function addRule() {
  if (!props.node?.data?.config) return
  if (!Array.isArray(props.node.data.config.rules)) {
    props.node.data.config.rules = []
  }
  props.node.data.config.rules.push({ field: 'message_contains', value: '' })
}
</script>
