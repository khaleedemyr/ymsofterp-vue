<template>
  <AppLayout>
    <div class="flex h-[calc(100vh-7rem)] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
        <div>
          <h1 class="text-lg font-semibold text-slate-900">Inbox Omnichannel</h1>
          <p class="text-sm text-slate-500">WhatsApp (uji) — balas chat customer dari ERP</p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
          <i class="fa-brands fa-whatsapp" />
          WhatsApp
        </span>
      </div>

      <div class="flex min-h-0 flex-1">
        <aside class="flex w-full max-w-sm flex-col border-r border-slate-200 bg-slate-50/80">
          <div class="border-b border-slate-200 px-4 py-3">
            <input
              v-model="search"
              type="text"
              placeholder="Cari nama atau nomor..."
              class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />
          </div>

          <div class="flex-1 overflow-y-auto">
            <button
              v-for="conv in filteredConversations"
              :key="conv.id"
              type="button"
              class="flex w-full items-start gap-3 border-b border-slate-100 px-4 py-3 text-left transition hover:bg-white"
              :class="selectedId === conv.id ? 'bg-white ring-1 ring-inset ring-emerald-200' : ''"
              @click="selectConversation(conv.id)"
            >
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <i class="fa-brands fa-whatsapp" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                  <span class="truncate font-medium text-slate-900">
                    {{ conv.contact_name || conv.display_phone }}
                  </span>
                  <span
                    v-if="conv.unread_count > 0"
                    class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                  >
                    {{ conv.unread_count }}
                  </span>
                </div>
                <p class="truncate text-xs text-slate-500">{{ conv.display_phone }}</p>
                <p class="mt-0.5 truncate text-xs text-slate-400">{{ conv.last_message_preview || '—' }}</p>
              </div>
            </button>

            <p v-if="filteredConversations.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">
              Belum ada percakapan. Kirim atau terima pesan uji dari WhatsApp.
            </p>
          </div>
        </aside>

        <section class="flex min-w-0 flex-1 flex-col bg-[#e5ddd5]">
          <template v-if="selectedConversation">
            <div class="flex items-center gap-3 border-b border-slate-200 bg-white px-5 py-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                <i class="fa-brands fa-whatsapp" />
              </div>
              <div>
                <p class="font-semibold text-slate-900">
                  {{ selectedConversation.contact_name || selectedConversation.display_phone }}
                </p>
                <p class="text-xs text-slate-500">
                  {{ selectedConversation.display_phone }}
                  <span v-if="selectedConversation.member" class="text-emerald-600">
                    · Member: {{ selectedConversation.member.nama_lengkap }}
                  </span>
                </p>
              </div>
            </div>

            <div ref="messagesEl" class="flex-1 space-y-2 overflow-y-auto px-4 py-4">
              <div
                v-for="msg in localMessages"
                :key="msg.id"
                class="flex"
                :class="msg.direction === 'outbound' ? 'justify-end' : 'justify-start'"
              >
                <div
                  class="max-w-[75%] rounded-lg px-3 py-2 text-sm shadow-sm"
                  :class="msg.direction === 'outbound' ? 'bg-[#dcf8c6] text-slate-900' : 'bg-white text-slate-900'"
                >
                  <p class="whitespace-pre-wrap break-words">{{ msg.body }}</p>
                  <p class="mt-1 text-right text-[10px] text-slate-500">
                    {{ formatTime(msg.sent_at) }}
                    <span v-if="msg.direction === 'outbound' && msg.status"> · {{ msg.status }}</span>
                  </p>
                </div>
              </div>
            </div>

            <form class="flex gap-2 border-t border-slate-200 bg-white p-4" @submit.prevent="sendMessage">
              <input
                v-model="replyText"
                type="text"
                placeholder="Ketik pesan..."
                class="min-w-0 flex-1 rounded-full border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                :disabled="sending"
              />
              <button
                type="submit"
                class="rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                :disabled="sending || !replyText.trim()"
              >
                <i v-if="sending" class="fa-solid fa-spinner fa-spin" />
                <span v-else>Kirim</span>
              </button>
            </form>
            <p v-if="sendError" class="bg-red-50 px-4 py-2 text-xs text-red-600">{{ sendError }}</p>
          </template>

          <div v-else class="flex flex-1 flex-col items-center justify-center text-slate-500">
            <i class="fa-brands fa-whatsapp mb-3 text-4xl text-emerald-400" />
            <p class="text-sm">Pilih percakapan untuk mulai membalas</p>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  conversations: { type: Array, default: () => [] },
  selectedConversation: { type: Object, default: null },
  messages: { type: Array, default: () => [] },
})

const search = ref('')
const selectedId = ref(props.selectedConversation?.id ?? null)
const localMessages = ref([...props.messages])
const replyText = ref('')
const sending = ref(false)
const sendError = ref('')
const messagesEl = ref(null)
let pollTimer = null

const selectedConversation = computed(() => props.selectedConversation)

const filteredConversations = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.conversations
  return props.conversations.filter((c) => {
    const name = (c.contact_name || '').toLowerCase()
    const phone = (c.display_phone || '').toLowerCase()
    return name.includes(q) || phone.includes(q)
  })
})

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
  }
)

function selectConversation(id) {
  router.get('/crm/omnichannel-inbox', { conversation: id }, {
    preserveState: true,
    preserveScroll: true,
    only: ['conversations', 'selectedConversation', 'messages'],
  })
}

async function sendMessage() {
  if (!selectedId.value || !replyText.value.trim()) return
  sending.value = true
  sendError.value = ''
  try {
    const { data } = await axios.post(
      `/crm/omnichannel-inbox/conversations/${selectedId.value}/messages`,
      { body: replyText.value.trim() }
    )
    localMessages.value.push(data.message)
    replyText.value = ''
    scrollToBottom()
    router.reload({ only: ['conversations'] })
  } catch (e) {
    sendError.value = e.response?.data?.message || 'Gagal mengirim pesan.'
  } finally {
    sending.value = false
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
      router.reload({ only: ['conversations'] })
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
