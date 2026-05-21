<template>
  <AppLayout>
    <div class="flex h-[calc(100vh-4rem)] flex-col">
      <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
        <div>
          <h1 class="text-lg font-semibold text-slate-900">{{ flow ? 'Edit flow' : 'Buat flow baru' }}</h1>
          <Link href="/crm/omnichannel-flows" class="text-sm text-emerald-700 hover:underline">← Daftar flow</Link>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
            @click="generalOpen = !generalOpen"
          >
            {{ generalOpen ? 'Sembunyikan' : 'Pengaturan umum' }}
          </button>
          <button
            type="button"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
            :disabled="submitting"
            @click="submit"
          >
            {{ submitting ? 'Menyimpan...' : 'Simpan flow' }}
          </button>
        </div>
      </header>

      <div v-if="generalOpen" class="border-b border-slate-200 bg-slate-50 px-4 py-3">
        <div class="mx-auto grid max-w-5xl gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div class="sm:col-span-2">
            <label class="text-xs font-medium text-slate-600">Nama flow</label>
            <input v-model="form.name" type="text" required maxlength="120" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-600">Prioritas</label>
            <input v-model.number="form.priority" type="number" min="1" max="9999" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
            <p class="mt-1 text-[10px] leading-snug text-slate-500">
              Angka lebih kecil = dicek lebih dulu saat pesan masuk. Hanya satu flow yang jalan per chat.
            </p>
          </div>
          <div>
            <label class="text-xs font-medium text-slate-600">Channel</label>
            <select v-model="form.channel" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
              <option :value="null">Semua channel</option>
              <option value="whatsapp">WhatsApp</option>
              <option value="messenger">Facebook Messenger</option>
              <option value="instagram">Instagram DM</option>
            </select>
          </div>
          <div class="sm:col-span-2 lg:col-span-4">
            <label class="text-xs font-medium text-slate-600">Deskripsi</label>
            <input v-model="form.description" type="text" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
          </div>
        </div>
      </div>

      <div class="flex min-h-0 flex-1">
        <!-- Palette -->
        <aside class="w-48 shrink-0 overflow-y-auto border-r border-slate-200 bg-white p-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Node</p>
          <p class="mt-1 text-[10px] text-slate-500">Seret ke canvas</p>
          <ul class="mt-3 space-y-2">
            <li
              v-for="item in nodePalette"
              :key="item.value"
              draggable="true"
              class="cursor-grab rounded-lg border px-2 py-2 text-xs font-medium text-slate-800 shadow-sm active:cursor-grabbing"
              :class="paletteDragClass(item.color)"
              @dragstart="onPaletteDragStart($event, item.value)"
            >
              {{ item.label }}
            </li>
          </ul>
          <p class="mt-4 text-[10px] leading-relaxed text-slate-500">
            Hubungkan node dengan menarik dari titik bawah ke atas node lain. Kondisi punya cabang Ya / Tidak.
          </p>
        </aside>

        <!-- Canvas -->
        <main class="relative min-w-0 flex-1">
          <FlowCanvas
            ref="canvasRef"
            v-model:nodes="nodes"
            v-model:edges="edges"
            :node-palette="nodePalette"
            @node-selected="selectedNode = $event"
          />
        </main>

        <!-- Config panel: overflow visible agar dropdown Multiselect tidak terpotong -->
        <aside class="relative z-30 flex w-80 shrink-0 flex-col self-stretch border-l border-slate-200 bg-white">
          <FlowNodeConfigPanel
            class="flex min-h-0 flex-1 flex-col"
            :node="selectedNode"
            :teams="teams"
            :users="users"
            :lead-stages="leadStages"
            :condition-fields="conditionFields"
            @remove-node="removeNode"
          />
        </aside>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FlowCanvas from '@/Components/OmnichannelFlows/FlowCanvas.vue'
import FlowNodeConfigPanel from '@/Components/OmnichannelFlows/FlowNodeConfigPanel.vue'
import {
  ensureDefinition,
  hydrateEdges,
  hydrateNodes,
  serializeDefinition,
} from '@/utils/omniFlowGraph'

const props = defineProps({
  flow: { type: Object, default: null },
  teams: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  leadStages: { type: Array, default: () => [] },
  nodePalette: { type: Array, default: () => [] },
  conditionFields: { type: Array, default: () => [] },
})

const submitting = ref(false)
const generalOpen = ref(!props.flow?.id)
const canvasRef = ref(null)
const selectedNode = ref(null)

const initialDef = ensureDefinition(props.flow?.definition)
const nodes = ref(hydrateNodes(initialDef.nodes, props.teams, props.users))
const edges = ref(hydrateEdges(initialDef.edges || [], nodes.value))

const form = reactive({
  name: props.flow?.name || '',
  description: props.flow?.description || '',
  trigger_type: 'inbound_message',
  channel: props.flow?.channel ?? 'whatsapp',
  priority: props.flow?.priority ?? 100,
})

function paletteDragClass(color) {
  const map = {
    amber: 'border-amber-200 bg-amber-50 hover:border-amber-400',
    emerald: 'border-emerald-200 bg-emerald-50 hover:border-emerald-400',
    blue: 'border-blue-200 bg-blue-50 hover:border-blue-400',
    indigo: 'border-indigo-200 bg-indigo-50 hover:border-indigo-400',
    violet: 'border-violet-200 bg-violet-50 hover:border-violet-400',
    slate: 'border-slate-200 bg-slate-50 hover:border-slate-400',
    rose: 'border-rose-200 bg-rose-50 hover:border-rose-400',
  }
  return map[color] || 'border-slate-200 bg-white hover:border-slate-400'
}

function onPaletteDragStart(event, nodeType) {
  event.dataTransfer.setData('application/omniflow', nodeType)
  event.dataTransfer.effectAllowed = 'move'
}

function removeNode(nodeId) {
  canvasRef.value?.removeNode(nodeId)
  selectedNode.value = null
}

function submit() {
  if (!form.name.trim()) return

  submitting.value = true
  const snap = canvasRef.value?.getFlowSnapshot?.()
  const payload = {
    name: form.name.trim(),
    description: form.description.trim() || null,
    trigger_type: form.trigger_type,
    channel: form.channel || null,
    priority: form.priority,
    definition: serializeDefinition(
      snap?.nodes ?? nodes.value,
      snap?.edges ?? edges.value,
    ),
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
