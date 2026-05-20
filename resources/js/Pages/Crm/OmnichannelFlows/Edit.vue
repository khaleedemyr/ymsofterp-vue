<template>
  <AppLayout>
    <div class="mx-auto max-w-4xl space-y-6 p-4">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">{{ flow ? 'Edit flow' : 'Buat flow baru' }}</h1>
        <Link href="/crm/omnichannel-flows" class="mt-1 inline-block text-sm text-emerald-700 hover:underline">
          ← Daftar flow
        </Link>
      </div>

      <form class="space-y-6" @submit.prevent="submit">
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-800">Umum</h2>
          <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label class="text-xs font-medium text-slate-600">Nama flow</label>
              <input v-model="form.name" type="text" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm" />
            </div>
            <div class="sm:col-span-2">
              <label class="text-xs font-medium text-slate-600">Deskripsi</label>
              <input v-model="form.description" type="text" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm" />
            </div>
            <div>
              <label class="text-xs font-medium text-slate-600">Prioritas (angka kecil = lebih dulu)</label>
              <input v-model.number="form.priority" type="number" min="1" max="9999" required class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm" />
            </div>
            <div>
              <label class="text-xs font-medium text-slate-600">Channel</label>
              <select v-model="form.channel" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm">
                <option :value="null">Semua</option>
                <option value="whatsapp">WhatsApp</option>
              </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
              <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-emerald-600" />
              Flow aktif
            </label>
          </div>
          <p class="mt-2 text-xs text-slate-500">Pemicu: <strong>Pesan masuk</strong> dari pelanggan.</p>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-800">Langkah (berurutan)</h2>
            <select v-model="newStepType" class="rounded-lg border border-slate-200 px-2 py-1 text-sm">
              <option v-for="st in stepTypes" :key="st.value" :value="st.value">{{ st.label }}</option>
            </select>
            <button type="button" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white" @click="addStep">
              + Tambah langkah
            </button>
          </div>

          <p v-if="form.steps.length === 0" class="mt-4 text-sm text-slate-500">Minimal satu langkah (biasanya mulai dengan Kondisi).</p>

          <div v-for="(step, idx) in form.steps" :key="idx" class="mt-4 rounded-lg border border-slate-100 bg-slate-50/80 p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-xs font-semibold text-slate-700">
                {{ idx + 1 }}. {{ stepTypeLabel(step.type) }}
              </p>
              <div class="flex gap-1">
                <button type="button" class="text-xs text-slate-500 hover:underline" :disabled="idx === 0" @click="moveStep(idx, -1)">↑</button>
                <button type="button" class="text-xs text-slate-500 hover:underline" :disabled="idx === form.steps.length - 1" @click="moveStep(idx, 1)">↓</button>
                <button type="button" class="text-xs text-red-600 hover:underline" @click="removeStep(idx)">Hapus</button>
              </div>
            </div>

            <!-- condition -->
            <div v-if="step.type === 'condition'" class="mt-2 space-y-2">
              <select v-model="step.config.match" class="rounded border border-slate-200 px-2 py-1 text-xs">
                <option value="all">Semua aturan harus cocok</option>
                <option value="any">Salah satu aturan cocok</option>
              </select>
              <div v-for="(rule, ri) in step.config.rules" :key="ri" class="flex flex-wrap items-end gap-2 rounded border border-slate-200 bg-white p-2">
                <select v-model="rule.field" class="rounded border border-slate-200 px-1 py-1 text-xs">
                  <option v-for="cf in conditionFields" :key="cf.value" :value="cf.value">{{ cf.label }}</option>
                </select>
                <template v-if="rule.field === 'hour_between'">
                  <input v-model.number="rule.from" type="number" min="0" max="23" class="w-14 rounded border px-1 text-xs" placeholder="dari" />
                  <span class="text-xs">–</span>
                  <input v-model.number="rule.to" type="number" min="0" max="23" class="w-14 rounded border px-1 text-xs" placeholder="sampai" />
                  <span class="text-[10px] text-slate-500">jam WIB</span>
                </template>
                <template v-else-if="rule.field === 'no_assignee'">
                  <span class="text-xs text-slate-500">(otomatis)</span>
                </template>
                <template v-else-if="rule.field === 'has_member'">
                  <select v-model="rule.value" class="rounded border px-1 text-xs">
                    <option :value="true">Ya</option>
                    <option :value="false">Tidak</option>
                  </select>
                </template>
                <template v-else-if="rule.field === 'lead_stage'">
                  <select v-model="rule.value" class="rounded border px-1 text-xs">
                    <option v-for="ls in leadStages" :key="ls.value" :value="ls.value">{{ ls.label }}</option>
                  </select>
                </template>
                <template v-else>
                  <input v-model="rule.value" type="text" class="min-w-[120px] flex-1 rounded border px-1 text-xs" placeholder="nilai" />
                </template>
                <button type="button" class="text-xs text-red-500" @click="step.config.rules.splice(ri, 1)">×</button>
              </div>
              <button type="button" class="text-xs font-medium text-emerald-700" @click="addRule(step)">+ Aturan</button>
            </div>

            <!-- send_message -->
            <div v-else-if="step.type === 'send_message'" class="mt-2">
              <textarea v-model="step.config.body" rows="3" class="w-full rounded border border-slate-200 px-2 py-1 text-sm" placeholder="Isi pesan... {{nama}} {{nomor}}" />
            </div>

            <!-- assign_team -->
            <div v-else-if="step.type === 'assign_team'" class="mt-2">
              <Multiselect
                v-model="step.config._teams"
                :options="teams"
                :multiple="true"
                label="name"
                track-by="id"
                placeholder="Pilih tim..."
                class="text-sm"
              />
            </div>

            <!-- assign_users -->
            <div v-else-if="step.type === 'assign_users'" class="mt-2">
              <Multiselect
                v-model="step.config._users"
                :options="users"
                :custom-label="formatUserLabel"
                :multiple="true"
                label="name"
                track-by="id"
                placeholder="Pilih user..."
                class="text-sm"
              />
            </div>

            <!-- set_lead_stage -->
            <div v-else-if="step.type === 'set_lead_stage'" class="mt-2">
              <select v-model="step.config.lead_stage" class="w-full rounded border border-slate-200 px-2 py-1 text-sm">
                <option v-for="ls in leadStages" :key="ls.value" :value="ls.value">{{ ls.label }}</option>
              </select>
            </div>

            <!-- append_memo -->
            <div v-else-if="step.type === 'append_memo'" class="mt-2">
              <textarea v-model="step.config.text" rows="2" class="w-full rounded border border-slate-200 px-2 py-1 text-sm" />
            </div>

            <!-- notify -->
            <div v-else-if="step.type === 'notify_assignees'" class="mt-2">
              <input v-model="step.config.message" type="text" class="w-full rounded border border-slate-200 px-2 py-1 text-sm" placeholder="Teks notifikasi..." />
            </div>
          </div>
        </section>

        <button
          type="submit"
          class="rounded-lg bg-emerald-600 px-6 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
          :disabled="submitting"
        >
          {{ submitting ? 'Menyimpan...' : flow ? 'Simpan perubahan' : 'Buat flow' }}
        </button>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  flow: { type: Object, default: null },
  teams: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  leadStages: { type: Array, default: () => [] },
  stepTypes: { type: Array, default: () => [] },
  conditionFields: { type: Array, default: () => [] },
})

const submitting = ref(false)
const newStepType = ref('condition')

function defaultStepConfig(type) {
  switch (type) {
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

function hydrateSteps(steps) {
  return (steps || []).map((s) => {
    const config = { ...(s.config || {}) }
    if (s.type === 'assign_team') {
      const ids = config.team_ids || []
      config._teams = props.teams.filter((t) => ids.includes(t.id))
    }
    if (s.type === 'assign_users') {
      const ids = config.user_ids || []
      config._users = props.users.filter((u) => ids.includes(u.id))
    }
    if (s.type === 'condition' && !Array.isArray(config.rules)) {
      config.rules = []
    }
    return { type: s.type, config }
  })
}

const form = reactive({
  name: props.flow?.name || '',
  description: props.flow?.description || '',
  is_active: props.flow?.is_active ?? false,
  trigger_type: 'inbound_message',
  channel: props.flow?.channel ?? 'whatsapp',
  priority: props.flow?.priority ?? 100,
  steps: props.flow ? hydrateSteps(props.flow.steps) : [
    { type: 'condition', config: defaultStepConfig('condition') },
    { type: 'send_message', config: defaultStepConfig('send_message') },
  ],
})

function stepTypeLabel(type) {
  return props.stepTypes.find((s) => s.value === type)?.label || type
}

function formatUserLabel(opt) {
  if (!opt) return ''
  const bits = [opt.jabatan, opt.outlet].filter(Boolean)
  return bits.length ? `${opt.name} — ${bits.join(' · ')}` : opt.name
}

function addStep() {
  const type = newStepType.value
  form.steps.push({ type, config: defaultStepConfig(type) })
}

function removeStep(idx) {
  form.steps.splice(idx, 1)
}

function moveStep(idx, dir) {
  const j = idx + dir
  if (j < 0 || j >= form.steps.length) return
  const tmp = form.steps[idx]
  form.steps[idx] = form.steps[j]
  form.steps[j] = tmp
}

function addRule(step) {
  if (!Array.isArray(step.config.rules)) {
    step.config.rules = []
  }
  step.config.rules.push({ field: 'message_contains', value: '' })
}

function serializeSteps() {
  return form.steps.map((step) => {
    const config = { ...step.config }
    if (step.type === 'assign_team') {
      config.team_ids = (config._teams || []).map((t) => t.id)
      delete config._teams
    }
    if (step.type === 'assign_users') {
      config.user_ids = (config._users || []).map((u) => u.id)
      delete config._users
    }
    return { type: step.type, config }
  })
}

function submit() {
  submitting.value = true
  const payload = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    is_active: form.is_active,
    trigger_type: form.trigger_type,
    channel: form.channel || null,
    priority: form.priority,
    steps: serializeSteps(),
  }
  if (props.flow?.id) {
    router.patch(`/crm/omnichannel-flows/${props.flow.id}`, payload, {
      onFinish: () => { submitting.value = false },
    })
  } else {
    router.post('/crm/omnichannel-flows', payload, {
      onFinish: () => { submitting.value = false },
    })
  }
}
</script>
