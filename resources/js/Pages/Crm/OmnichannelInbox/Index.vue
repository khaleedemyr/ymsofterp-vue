<template>
  <AppLayout>
    <div class="flex h-[calc(100vh-7rem)] min-h-[520px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <!-- Kolom 1: filter inbox & lifecycle -->
      <nav class="flex w-52 shrink-0 flex-col border-r border-slate-200 bg-slate-50">
        <div class="border-b border-slate-200 p-3">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Inbox</p>
          <div class="mt-2 space-y-1">
            <button
              v-for="opt in inboxOptions"
              :key="opt.value"
              type="button"
              class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm"
              :class="inbox === opt.value ? 'bg-white font-medium text-emerald-800 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:bg-white/80'"
              @click="setInbox(opt.value)"
            >
              <i :class="opt.icon" class="w-4 text-center text-slate-400" />
              {{ opt.label }}
            </button>
          </div>
        </div>
        <div class="flex-1 overflow-y-auto p-3">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Tahap lead</p>
          <div class="mt-2 space-y-0.5">
            <button
              type="button"
              class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs"
              :class="!leadStageFilter ? 'bg-emerald-50 font-medium text-emerald-900' : 'text-slate-600 hover:bg-white'"
              @click="setLeadStage(null)"
            >
              <span class="h-2 w-2 shrink-0 rounded-full bg-slate-300" />
              Semua tahap
            </button>
            <button
              v-for="st in leadStages"
              :key="st.value"
              type="button"
              class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs"
              :class="leadStageFilter === st.value ? 'bg-emerald-50 font-medium text-emerald-900' : 'text-slate-600 hover:bg-white'"
              @click="setLeadStage(st.value)"
            >
              <span class="h-2 w-2 shrink-0 rounded-full" :class="stageDotClass(st.color)" />
              {{ st.label }}
            </button>
          </div>
        </div>
      </nav>

      <!-- Kolom 2: daftar percakapan -->
      <aside class="flex w-72 shrink-0 flex-col border-r border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-3 py-2">
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama / nomor..."
            class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
          />
        </div>
        <div class="flex-1 overflow-y-auto">
          <button
            v-for="conv in filteredConversations"
            :key="conv.id"
            type="button"
            class="flex w-full gap-2 border-b border-slate-100 px-3 py-2.5 text-left hover:bg-slate-50"
            :class="selectedId === conv.id ? 'bg-emerald-50/80 ring-1 ring-inset ring-emerald-200' : ''"
            @click="selectConversation(conv.id)"
          >
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
              <i class="fa-brands fa-whatsapp text-sm" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex w-full items-center justify-between gap-1">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1">
                  <span class="truncate text-sm font-medium text-slate-900">{{ conv.contact_name || conv.display_phone }}</span>
                  <span
                    v-if="conv.member"
                    class="inline-flex shrink-0 items-center gap-0.5 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800 ring-1 ring-emerald-200"
                    title="Terdaftar di member app"
                  >
                    Member<span v-if="conv.member.member_level"> · {{ formatMemberTier(conv.member.member_level) }}</span>
                    <span v-if="conv.member.is_exclusive_member" class="text-amber-600" title="Eksklusif">★</span>
                  </span>
                </div>
                <span v-if="conv.unread_count > 0" class="shrink-0 rounded-full bg-emerald-600 px-1.5 text-[10px] font-bold text-white">
                  {{ conv.unread_count }}
                </span>
              </div>
              <p class="truncate text-[11px] text-slate-500">{{ conv.display_phone }}</p>
              <p class="truncate text-[11px] text-slate-400">{{ conv.last_message_preview || '—' }}</p>
              <p v-if="conv.assignees?.length" class="truncate text-[10px] text-indigo-600">
                {{ conv.assignees.map((a) => a.name).join(', ') }}
              </p>
            </div>
          </button>
          <p v-if="filteredConversations.length === 0" class="px-3 py-6 text-center text-xs text-slate-400">
            Tidak ada percakapan.
          </p>
        </div>
      </aside>

      <!-- Kolom 3: thread chat -->
      <section class="flex min-w-0 flex-1 flex-col bg-[#e5ddd5]">
        <template v-if="selectedConversation">
          <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white px-4 py-2">
            <div class="flex min-w-0 flex-1 items-center gap-2">
              <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <i class="fa-brands fa-whatsapp" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="truncate font-semibold text-slate-900">
                    {{ selectedConversation.contact_name || selectedConversation.display_phone }}
                  </p>
                  <span
                    v-if="selectedConversation.member"
                    class="inline-flex shrink-0 items-center gap-0.5 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-200"
                  >
                    Member<span v-if="selectedConversation.member.member_level"> · {{ formatMemberTier(selectedConversation.member.member_level) }}</span>
                    <span v-if="selectedConversation.member.is_exclusive_member" class="text-amber-600" title="Eksklusif">★</span>
                  </span>
                </div>
                <p class="truncate text-xs text-slate-500">
                  {{ selectedConversation.display_phone }}
                  <span v-if="selectedConversation.member" class="text-emerald-600"> · {{ selectedConversation.member.nama_lengkap }}</span>
                </p>
                <p v-if="selectedConversation.assignees?.length" class="mt-0.5 truncate text-[10px] text-indigo-700">
                  <i class="fa-solid fa-user-tag mr-0.5" />
                  {{ selectedConversation.assignees.map((a) => a.name).join(', ') }}
                </p>
              </div>
            </div>
          </div>

          <div
            v-if="waWindowBanner"
            class="border-b px-4 py-2 text-center text-xs"
            :class="waWindowBanner.expired ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-sky-200 bg-sky-50 text-sky-900'"
          >
            {{ waWindowBanner.text }}
          </div>

          <div ref="messagesEl" class="flex-1 space-y-2 overflow-y-auto px-3 py-3">
            <div
              v-for="msg in localMessages"
              :key="msg.id"
              class="flex"
              :class="bubbleAlign(msg)"
            >
              <div
                class="max-w-[82%] rounded-lg px-3 py-2 text-sm shadow-sm"
                :class="bubbleClass(msg)"
              >
                <p v-if="msg.direction === 'internal'" class="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">
                  Catatan internal{{ msg.author_name ? ' · ' + msg.author_name : '' }}
                </p>
                <p class="whitespace-pre-wrap break-words">{{ msg.body }}</p>
                <p class="mt-1 text-right text-[10px] opacity-80">
                  {{ formatTime(msg.sent_at) }}
                  <span v-if="msg.direction === 'outbound' && msg.status"> · {{ msg.status }}</span>
                </p>
              </div>
            </div>
          </div>

          <div class="border-t border-slate-200 bg-white">
            <div class="flex border-b border-slate-100 px-2">
              <button
                type="button"
                class="px-4 py-2 text-sm font-medium"
                :class="composerMode === 'reply' ? 'border-b-2 border-emerald-600 text-emerald-800' : 'text-slate-500'"
                @click="composerMode = 'reply'"
              >
                Balas
              </button>
              <button
                type="button"
                class="px-4 py-2 text-sm font-medium"
                :class="composerMode === 'internal' ? 'border-b-2 border-amber-600 text-amber-900' : 'text-slate-500'"
                @click="composerMode = 'internal'"
              >
                Catatan internal
              </button>
            </div>
            <form class="flex gap-2 p-3" @submit.prevent="submitComposer">
              <input
                v-model="replyText"
                type="text"
                class="min-w-0 flex-1 rounded-full border border-slate-200 px-4 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                :placeholder="composerMode === 'internal' ? 'Catatan hanya untuk tim...' : 'Kirim ke WhatsApp...'"
                :disabled="sending"
              />
              <button
                type="submit"
                class="shrink-0 rounded-full px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                :class="composerMode === 'internal' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                :disabled="sending || !replyText.trim()"
              >
                <i v-if="sending" class="fa-solid fa-spinner fa-spin" />
                <span v-else>{{ composerMode === 'internal' ? 'Simpan' : 'Kirim' }}</span>
              </button>
            </form>
            <p v-if="sendError" class="px-3 pb-2 text-xs text-red-600">{{ sendError }}</p>
          </div>
        </template>

        <div v-else class="flex flex-1 flex-col items-center justify-center text-slate-500">
          <i class="fa-brands fa-whatsapp mb-2 text-3xl text-emerald-400" />
          <p class="text-sm">Pilih percakapan</p>
        </div>
      </section>

      <!-- Kolom 4: CRM ringkas -->
      <aside v-if="selectedConversation" class="flex w-80 shrink-0 flex-col border-l border-slate-200 bg-slate-50">
        <div class="border-b border-slate-200 px-3 py-2">
          <p class="text-xs font-semibold text-slate-700">Detail kontak</p>
        </div>
        <div class="flex-1 space-y-3 overflow-y-auto p-3 text-sm">
          <div
            v-if="selectedConversation.member"
            class="rounded-lg border border-emerald-200 bg-emerald-50/80 px-3 py-2 text-xs text-emerald-900"
          >
            <p class="font-semibold text-emerald-950">
              Member app
              <span v-if="selectedConversation.member.member_level" class="font-normal text-emerald-800">
                — {{ formatMemberTier(selectedConversation.member.member_level) }}
              </span>
              <span v-if="selectedConversation.member.is_exclusive_member" class="text-amber-700"> · Eksklusif</span>
            </p>
            <p class="mt-0.5 text-emerald-800">{{ selectedConversation.member.nama_lengkap }}</p>
            <p v-if="selectedConversation.member.member_id" class="mt-0.5 font-mono text-[11px] text-emerald-700">
              ID: {{ selectedConversation.member.member_id }}
            </p>
          </div>
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Tahap lead</label>
            <select v-model="crmForm.lead_stage" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
              <option v-for="st in leadStages" :key="st.value" :value="st.value">{{ st.label }}</option>
            </select>
          </div>
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Assign ke (bisa banyak)</label>
            <Multiselect
              v-model="crmForm.assignees"
              :options="assignableUsers"
              :multiple="true"
              :close-on-select="false"
              :clear-on-select="false"
              :searchable="true"
              :preserve-search="true"
              :show-labels="false"
              placeholder="Ketik nama untuk mencari..."
              label="name"
              track-by="id"
              class="omni-assign-multiselect mt-1 text-sm"
            >
              <template #noResult>
                <span class="px-2 py-1 text-xs text-slate-500">Tidak ada user</span>
              </template>
            </Multiselect>
          </div>
          <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 bg-white p-2 text-xs text-slate-700">
            <input v-model="notifyAssigneesOnSave" type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600" />
            <span>Kirim notifikasi in-app ke semua yang ditugaskan saat klik <strong>Simpan detail</strong></span>
          </label>
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Memo</label>
            <textarea v-model="crmForm.memo" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" placeholder="Catatan internal CRM..." />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="text-[10px] font-semibold uppercase text-slate-500">Nama depan</label>
              <input v-model="crmForm.contact_first_name" type="text" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs" />
            </div>
            <div>
              <label class="text-[10px] font-semibold uppercase text-slate-500">Nama belakang</label>
              <input v-model="crmForm.contact_last_name" type="text" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs" />
            </div>
          </div>
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Email</label>
            <input v-model="crmForm.contact_email" type="email" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs" />
          </div>
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Perusahaan</label>
            <input v-model="crmForm.contact_company" type="text" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs" />
          </div>
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Jabatan</label>
            <input v-model="crmForm.contact_job_title" type="text" class="mt-1 w-full rounded border border-slate-200 px-2 py-1 text-xs" />
          </div>
          <button
            type="button"
            class="w-full rounded-lg bg-slate-800 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50"
            :disabled="crmSaving"
            @click="saveCrmPanel"
          >
            {{ crmSaving ? 'Menyimpan...' : 'Simpan detail' }}
          </button>
          <p v-if="crmSaveError" class="text-xs text-red-600">{{ crmSaveError }}</p>
        </div>
      </aside>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  conversations: { type: Array, default: () => [] },
  selectedConversation: { type: Object, default: null },
  messages: { type: Array, default: () => [] },
  inbox: { type: String, default: 'all' },
  leadStageFilter: { type: String, default: null },
  leadStages: { type: Array, default: () => [] },
  assignableUsers: { type: Array, default: () => [] },
})

const inboxOptions = [
  { value: 'all', label: 'Semua', icon: 'fa-solid fa-inbox' },
  { value: 'mine', label: 'Ditugaskan ke saya', icon: 'fa-solid fa-user' },
  { value: 'unassigned', label: 'Belum ditugaskan', icon: 'fa-solid fa-user-slash' },
]

const search = ref('')
const selectedId = ref(props.selectedConversation?.id ?? null)
const localMessages = ref([...props.messages])
const replyText = ref('')
const composerMode = ref('reply')
const sending = ref(false)
const sendError = ref('')
const messagesEl = ref(null)
const crmSaving = ref(false)
const crmSaveError = ref('')
const notifyAssigneesOnSave = ref(true)
const crmForm = ref({
  lead_stage: 'new_lead',
  assignees: [],
  memo: '',
  contact_first_name: '',
  contact_last_name: '',
  contact_email: '',
  contact_company: '',
  contact_job_title: '',
})

let pollTimer = null

const inbox = computed(() => props.inbox || 'all')
const leadStageFilter = computed(() => props.leadStageFilter || null)

const selectedConversation = computed(() => props.selectedConversation)

const filteredConversations = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.conversations
  return props.conversations.filter((c) => {
    const name = (c.contact_name || '').toLowerCase()
    const phone = (c.display_phone || '').toLowerCase()
    const memberName = (c.member?.nama_lengkap || '').toLowerCase()
    const tier = (c.member?.member_level || '').toLowerCase().replace(/_/g, ' ')
    return name.includes(q) || phone.includes(q) || memberName.includes(q) || tier.includes(q)
  })
})

const waWindowBanner = computed(() => {
  const c = props.selectedConversation
  if (!c?.last_customer_message_at) return null
  const start = new Date(c.last_customer_message_at).getTime()
  const end = start + 24 * 60 * 60 * 1000
  const now = Date.now()
  if (now >= end) {
    return { expired: true, text: 'Jendela 24 jam WhatsApp untuk membalas bebas sudah berakhir. Gunakan template pesan resmi jika perlu.' }
  }
  const left = end - now
  const h = Math.floor(left / (60 * 60 * 1000))
  const m = Math.floor((left % (60 * 60 * 1000)) / (60 * 1000))
  return { expired: false, text: `Jendela percakapan WhatsApp: ±${h} jam ${m} menit lagi untuk balasan bebas (24 jam dari pesan terakhir pelanggan).` }
})

function bubbleAlign(msg) {
  if (msg.direction === 'internal') return 'justify-center'
  return msg.direction === 'outbound' ? 'justify-end' : 'justify-start'
}

function bubbleClass(msg) {
  if (msg.direction === 'internal') return 'border border-amber-200 bg-amber-50 text-amber-950'
  if (msg.direction === 'outbound') return 'bg-[#dcf8c6] text-slate-900'
  return 'bg-white text-slate-900'
}

function stageDotClass(color) {
  const map = {
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    yellow: 'bg-yellow-500',
    blue: 'bg-blue-500',
    green: 'bg-green-500',
    purple: 'bg-purple-500',
    slate: 'bg-slate-500',
  }
  return map[color] || 'bg-slate-400'
}

function listQuery(extra = {}) {
  const q = { inbox: extra.inbox ?? inbox.value }
  if ('lead_stage' in extra) {
    if (extra.lead_stage) q.lead_stage = extra.lead_stage
  } else if (leadStageFilter.value) {
    q.lead_stage = leadStageFilter.value
  }
  if (extra.conversation !== undefined) {
    if (extra.conversation !== null && extra.conversation !== '') q.conversation = extra.conversation
  } else if (selectedId.value) {
    q.conversation = selectedId.value
  }
  return q
}

function setInbox(val) {
  router.get('/crm/omnichannel-inbox', listQuery({ inbox: val }), {
    preserveState: true,
    preserveScroll: true,
    only: ['conversations', 'selectedConversation', 'messages', 'inbox', 'leadStageFilter', 'leadStages', 'assignableUsers'],
  })
}

function setLeadStage(val) {
  router.get('/crm/omnichannel-inbox', listQuery({ lead_stage: val }), {
    preserveState: true,
    preserveScroll: true,
    only: ['conversations', 'selectedConversation', 'messages', 'inbox', 'leadStageFilter', 'leadStages', 'assignableUsers'],
  })
}

function selectConversation(id) {
  router.get('/crm/omnichannel-inbox', listQuery({ conversation: id }), {
    preserveState: true,
    preserveScroll: true,
    only: ['conversations', 'selectedConversation', 'messages', 'inbox', 'leadStageFilter', 'leadStages', 'assignableUsers'],
  })
}

function syncCrmFormFromConversation(conv) {
  if (!conv) return
  crmForm.value = {
    lead_stage: conv.lead_stage || 'new_lead',
    assignees: Array.isArray(conv.assignees) ? [...conv.assignees] : [],
    memo: conv.memo || '',
    contact_first_name: conv.contact_first_name || '',
    contact_last_name: conv.contact_last_name || '',
    contact_email: conv.contact_email || '',
    contact_company: conv.contact_company || '',
    contact_job_title: conv.contact_job_title || '',
  }
}

watch(
  () => props.messages,
  (val) => {
    localMessages.value = [...val]
    scrollToBottom()
  },
  { deep: true }
)

watch(
  () => props.selectedConversation,
  (conv) => {
    selectedId.value = conv?.id ?? null
    syncCrmFormFromConversation(conv)
    sendError.value = ''
    composerMode.value = 'reply'
  },
  { immediate: true }
)

async function submitComposer() {
  if (!selectedId.value || !replyText.value.trim()) return
  sending.value = true
  sendError.value = ''
  const body = replyText.value.trim()
  try {
    if (composerMode.value === 'internal') {
      const { data } = await axios.post(
        `/crm/omnichannel-inbox/conversations/${selectedId.value}/internal-notes`,
        { body }
      )
      localMessages.value.push(data.message)
    } else {
      const { data } = await axios.post(
        `/crm/omnichannel-inbox/conversations/${selectedId.value}/messages`,
        { body }
      )
      localMessages.value.push(data.message)
    }
    replyText.value = ''
    scrollToBottom()
    router.reload({
      only: ['conversations', 'selectedConversation'],
    })
  } catch (e) {
    sendError.value = e.response?.data?.message || 'Gagal mengirim.'
  } finally {
    sending.value = false
  }
}

async function saveCrmPanel() {
  if (!selectedId.value) return
  crmSaving.value = true
  crmSaveError.value = ''
  const f = crmForm.value
  const payload = {
    lead_stage: f.lead_stage,
    memo: f.memo,
    contact_first_name: f.contact_first_name,
    contact_last_name: f.contact_last_name,
    contact_email: f.contact_email,
    contact_company: f.contact_company,
    contact_job_title: f.contact_job_title,
    assigned_user_ids: (f.assignees || []).map((a) => a.id),
    notify_assignees: notifyAssigneesOnSave.value,
  }
  try {
    await axios.patch(`/crm/omnichannel-inbox/conversations/${selectedId.value}`, payload)
    router.reload({ only: ['conversations', 'selectedConversation'] })
  } catch (e) {
    crmSaveError.value = e.response?.data?.message || 'Gagal menyimpan.'
  } finally {
    crmSaving.value = false
  }
}

async function pollMessages() {
  if (!selectedId.value) return
  try {
    const { data } = await axios.get(
      `/crm/omnichannel-inbox/conversations/${selectedId.value}/messages`
    )
    if (data.messages?.length !== localMessages.value.length) {
      localMessages.value = data.messages
      scrollToBottom()
      router.reload({ only: ['conversations', 'selectedConversation'] })
    }
  } catch {
    /* ignore */
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesEl.value) {
      messagesEl.value.scrollTop = messagesEl.value.scrollHeight
    }
  })
}

function formatMemberTier(level) {
  if (!level) return ''
  const s = String(level).replace(/_/g, ' ')
  return s.length ? s.charAt(0).toUpperCase() + s.slice(1) : ''
}

function formatTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf
  }
  scrollToBottom()
  pollTimer = setInterval(pollMessages, 8000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})
</script>

<style scoped>
.omni-assign-multiselect :deep(.multiselect__tags) {
  min-height: 38px;
  padding-top: 6px;
  font-size: 0.875rem;
}
.omni-assign-multiselect :deep(.multiselect__input),
.omni-assign-multiselect :deep(.multiselect__single) {
  font-size: 0.875rem;
}
.omni-assign-multiselect :deep(.multiselect__content-wrapper) {
  max-height: 220px;
}
</style>
