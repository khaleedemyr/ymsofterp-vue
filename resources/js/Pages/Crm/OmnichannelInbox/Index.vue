<template>
  <AppLayout>
    <div class="flex h-[calc(100vh-7rem)] min-h-[520px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <!-- Kolom 1: filter inbox & lifecycle -->
      <nav class="flex w-52 shrink-0 flex-col border-r border-slate-200 bg-slate-50">
        <div class="border-b border-slate-200 p-3">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Inbox</p>
          <div class="mt-2 space-y-1">
            <button
              v-for="opt in inboxMenuOptions"
              :key="opt.value"
              type="button"
              class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm"
              :class="inbox === opt.value ? 'bg-white font-medium text-emerald-800 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:bg-white/80'"
              :title="opt.hint || undefined"
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
                {{ conv.assignees.map((a) => formatUserOptionLabel(a)).join(', ') }}
              </p>
              <p v-if="conv.assigned_teams?.length" class="truncate text-[10px] text-sky-700">
                <i class="fa-solid fa-people-group mr-0.5" />
                {{ conv.assigned_teams.map((t) => t.name).join(', ') }}
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
                  {{ selectedConversation.assignees.map((a) => formatUserOptionLabel(a)).join(', ') }}
                </p>
                <p v-if="selectedConversation.assigned_teams?.length" class="mt-0.5 truncate text-[10px] text-sky-800">
                  <i class="fa-solid fa-people-group mr-0.5" />
                  {{ selectedConversation.assigned_teams.map((t) => t.name).join(', ') }}
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
                <p class="mt-1 text-right text-[10px] leading-relaxed opacity-85">
                  <span
                    v-if="msg.direction === 'outbound' && msg.author_name"
                    class="mb-0.5 block text-left font-medium text-slate-700"
                  >
                    Dibalas oleh {{ msg.author_name }}
                  </span>
                  <span class="block text-slate-600">
                    {{ formatTime(msg.sent_at) }}
                    <span v-if="msg.direction === 'outbound' && msg.status"> · {{ msg.status }}</span>
                  </span>
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
                @click="setComposerMode('reply')"
              >
                Balas
              </button>
              <button
                type="button"
                class="px-4 py-2 text-sm font-medium"
                :class="composerMode === 'internal' ? 'border-b-2 border-amber-600 text-amber-900' : 'text-slate-500'"
                @click="setComposerMode('internal')"
              >
                Catatan internal
              </button>
            </div>
            <form class="flex items-end gap-2 p-3" @submit.prevent="submitComposer">
              <div class="relative min-w-0 flex-1">
                <div
                  v-if="templateMenuOpen && filteredTemplates.length > 0"
                  class="absolute bottom-full left-0 right-0 z-20 mb-1 max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                >
                  <p class="border-b border-slate-100 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    Template balasan
                  </p>
                  <button
                    v-for="(tpl, idx) in filteredTemplates"
                    :key="tpl.id"
                    type="button"
                    class="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-emerald-50"
                    :class="idx === templateMenuHighlight ? 'bg-emerald-50' : ''"
                    @mousedown.prevent="applyTemplate(tpl)"
                  >
                    <span class="font-medium text-slate-900">
                      {{ tpl.title }}
                      <span v-if="tpl.shortcut" class="font-mono text-xs text-emerald-700">/{{ tpl.shortcut }}</span>
                    </span>
                    <span class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ tpl.body }}</span>
                  </button>
                </div>
                <p
                  v-else-if="templateMenuOpen && messageTemplates.length === 0"
                  class="absolute bottom-full left-0 mb-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                >
                  Belum ada template. Buat di menu Tim Inbox Omnichannel.
                </p>
                <textarea
                  ref="composerEl"
                  v-model="replyText"
                  rows="1"
                  class="w-full resize-none rounded-2xl border border-slate-200 py-2 pl-4 pr-10 text-sm focus:outline-none focus:ring-1"
                  :class="composerMode === 'internal'
                    ? 'focus:border-amber-500 focus:ring-amber-500'
                    : 'focus:border-emerald-500 focus:ring-emerald-500'"
                  :placeholder="composerPlaceholder"
                  :disabled="sending"
                  @input="onComposerInput"
                  @keydown="onComposerKeydown"
                />
                <div class="absolute bottom-1.5 right-1.5" data-omni-emoji-picker>
                  <button
                    type="button"
                    class="flex h-8 w-8 items-center justify-center rounded-full text-lg text-slate-500 hover:bg-slate-100"
                    :disabled="sending"
                    title="Emoji"
                    @click.stop="emojiPickerOpen = !emojiPickerOpen"
                  >
                    <i class="fa-regular fa-face-smile" />
                  </button>
                  <div
                    v-if="emojiPickerOpen"
                    class="absolute bottom-full right-0 z-30 mb-1 w-64 rounded-lg border border-slate-200 bg-white p-2 shadow-lg"
                    @mousedown.prevent
                  >
                    <p class="mb-1 px-1 text-[10px] font-semibold uppercase text-slate-500">Emoji</p>
                    <div class="grid max-h-36 grid-cols-8 gap-0.5 overflow-y-auto">
                      <button
                        v-for="(em, idx) in omniEmojiPickerList"
                        :key="idx"
                        type="button"
                        class="rounded p-1 text-lg leading-none hover:bg-slate-100"
                        @mousedown.prevent="insertEmoji(em)"
                      >
                        {{ em }}
                      </button>
                    </div>
                  </div>
                </div>
              </div>
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

      <!-- Kolom 4: informasi detail kontak + CRM -->
      <aside v-if="selectedConversation" class="flex w-80 shrink-0 flex-col border-l border-slate-200 bg-slate-50">
        <div class="border-b border-slate-200 px-3 py-2">
          <p class="text-xs font-semibold text-slate-800">Informasi detail kontak</p>
          <Link
            v-if="canManageOmnichannelTeams"
            href="/crm/omnichannel-teams"
            class="mt-1 inline-block text-[11px] font-medium text-emerald-700 hover:text-emerald-900 hover:underline"
          >
            Kelola tim & akses lihat semua chat
          </Link>
        </div>
        <div class="flex-1 space-y-3 overflow-y-auto p-3 text-sm">
          <div class="rounded-lg border border-slate-200 bg-white p-3 text-xs shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">WhatsApp</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedConversation.contact_name || '—' }}</p>
            <dl class="mt-2 space-y-1.5 text-slate-700">
              <div class="flex justify-between gap-2">
                <dt class="shrink-0 text-slate-500">Nomor (lokal)</dt>
                <dd class="text-right font-mono">{{ selectedConversation.display_phone || '—' }}</dd>
              </div>
              <div class="flex justify-between gap-2">
                <dt class="shrink-0 text-slate-500">Nomor (internasional)</dt>
                <dd class="text-right font-mono text-[11px]">{{ selectedConversation.display_phone_international || '—' }}</dd>
              </div>
              <div class="border-t border-slate-100 pt-1.5">
                <dt class="text-slate-500">Penanggung jawab</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ contactOwnerLabel }}</dd>
              </div>
            </dl>
          </div>

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
            <p v-if="selectedConversation.member.mobile_phone" class="mt-0.5 text-emerald-800">
              {{ selectedConversation.member.mobile_phone }}
            </p>
            <p v-if="selectedConversation.member.member_id" class="mt-0.5 font-mono text-[11px] text-emerald-700">
              ID: {{ selectedConversation.member.member_id }}
            </p>
          </div>

          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Data CRM (tersimpan)</p>
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
              :custom-label="formatUserOptionLabel"
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
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Tim (opsional)</label>
            <Multiselect
              v-model="crmForm.assigned_teams"
              :options="assignableTeams"
              :multiple="true"
              :close-on-select="false"
              :clear-on-select="false"
              :searchable="true"
              :preserve-search="true"
              :show-labels="false"
              placeholder="Pilih tim..."
              label="name"
              track-by="id"
              class="omni-assign-multiselect mt-1 text-sm"
            >
              <template #noResult>
                <span class="px-2 py-1 text-xs text-slate-500">Tidak ada tim</span>
              </template>
            </Multiselect>
          </div>
          <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 bg-white p-2 text-xs text-slate-700">
            <input v-model="notifyAssigneesOnSave" type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600" />
            <span>Kirim notifikasi in-app ke semua yang ditugaskan saat klik <strong>Simpan detail</strong></span>
          </label>
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Memo</label>
            <textarea v-model="crmForm.memo" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" placeholder="Catatan internal CRM..." />
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
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import AppLayout from '@/Layouts/AppLayout.vue'
import { insertEmojiIntoTextarea, omniEmojiPickerList } from '@/utils/omniEmojiPicker.js'

const props = defineProps({
  conversations: { type: Array, default: () => [] },
  selectedConversation: { type: Object, default: null },
  messages: { type: Array, default: () => [] },
  inbox: { type: String, default: 'all' },
  leadStageFilter: { type: String, default: null },
  leadStages: { type: Array, default: () => [] },
  assignableUsers: { type: Array, default: () => [] },
  assignableTeams: { type: Array, default: () => [] },
  canSeeAllChats: { type: Boolean, default: true },
  canManageOmnichannelTeams: { type: Boolean, default: false },
  messageTemplates: { type: Array, default: () => [] },
})

/** Kunci partial reload Inertia agar daftar chat, pesan, dan hak "lihat semua" selalu sinkron (satu sumber kebenaran). */
const inboxPartialReloadKeys = [
  'conversations',
  'selectedConversation',
  'messages',
  'inbox',
  'leadStageFilter',
  'leadStages',
  'assignableUsers',
  'assignableTeams',
  'canSeeAllChats',
  'canManageOmnichannelTeams',
  'messageTemplates',
]

function sortMessages(msgs) {
  const list = Array.isArray(msgs) ? [...msgs] : []
  return list.sort((a, b) => {
    const ta = new Date(a.sent_at || 0).getTime()
    const tb = new Date(b.sent_at || 0).getTime()
    if (ta !== tb) {
      return ta - tb
    }
    return (Number(a.id) || 0) - (Number(b.id) || 0)
  })
}

const inboxMenuOptions = computed(() => {
  const restricted = !props.canSeeAllChats
  return [
    {
      value: 'all',
      label: restricted ? 'Semua (relevan)' : 'Semua',
      icon: 'fa-solid fa-inbox',
      hint: restricted
        ? 'Percakapan yang ditugaskan ke Anda, tim Anda, atau belum ditugaskan. Daftar "lihat semua" diatur di menu Tim Inbox Omnichannel.'
        : 'Semua percakapan (Anda ada di daftar lihat semua inbox)',
    },
    { value: 'mine', label: 'Ditugaskan ke saya', icon: 'fa-solid fa-user', hint: '' },
    { value: 'unassigned', label: 'Belum ditugaskan', icon: 'fa-solid fa-user-slash', hint: '' },
  ]
})

const search = ref('')
const selectedId = ref(props.selectedConversation?.id ?? null)
const localMessages = ref(sortMessages(props.messages || []))
const replyText = ref('')
const composerMode = ref('reply')
const sending = ref(false)
const sendError = ref('')
const messagesEl = ref(null)
const composerEl = ref(null)
const templateMenuOpen = ref(false)
const templateQuery = ref('')
const templateMenuHighlight = ref(0)
const emojiPickerOpen = ref(false)
const crmSaving = ref(false)
const crmSaveError = ref('')
const notifyAssigneesOnSave = ref(true)
const crmForm = ref({
  lead_stage: 'new_lead',
  assignees: [],
  assigned_teams: [],
  memo: '',
})

let pollTimer = null
let pollKickTimer = null
/** Hindari dua router.reload poll bersamaan (tab focus + interval). */
let pollInFlight = false

/** Saat tab aktif: cukup cepat agar WA masuk terasa “live” tanpa refresh manual. Tab di background: irit server. */
const POLL_MS_TAB_VISIBLE = 3000
const POLL_MS_TAB_HIDDEN = 25000

const inbox = computed(() => props.inbox || 'all')
const leadStageFilter = computed(() => props.leadStageFilter || null)

const selectedConversation = computed(() => props.selectedConversation)

const contactOwnerLabel = computed(() => {
  const c = props.selectedConversation
  if (!c) return '—'
  if (c.assignee) return formatUserOptionLabel(c.assignee)
  const first = c.assignees?.[0]
  return first ? formatUserOptionLabel(first) : '—'
})

const filteredConversations = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.conversations
  return props.conversations.filter((c) => {
    const name = (c.contact_name || '').toLowerCase()
    const phone = (c.display_phone || '').toLowerCase()
    const memberName = (c.member?.nama_lengkap || '').toLowerCase()
    const tier = (c.member?.member_level || '').toLowerCase().replace(/_/g, ' ')
    const teamNames = (c.assigned_teams || []).map((t) => (t.name || '').toLowerCase()).join(' ')
    const assigneeBits = (c.assignees || [])
      .map((a) => [a.name, a.jabatan, a.outlet].filter(Boolean).join(' ').toLowerCase())
      .join(' ')
    return (
      name.includes(q) ||
      phone.includes(q) ||
      memberName.includes(q) ||
      tier.includes(q) ||
      teamNames.includes(q) ||
      assigneeBits.includes(q)
    )
  })
})

const composerPlaceholder = computed(() => {
  if (composerMode.value === 'internal') {
    return 'Catatan hanya untuk tim... (ketik / untuk template)'
  }
  return 'Kirim ke WhatsApp... (ketik / untuk template balasan)'
})

const filteredTemplates = computed(() => {
  const q = templateQuery.value.trim().toLowerCase()
  const list = props.messageTemplates || []
  if (!q) {
    return list
  }
  return list.filter((tpl) => {
    const shortcut = (tpl.shortcut || '').toLowerCase()
    const title = (tpl.title || '').toLowerCase()
    return shortcut.includes(q) || title.includes(q)
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
    only: inboxPartialReloadKeys,
  })
}

function setLeadStage(val) {
  router.get('/crm/omnichannel-inbox', listQuery({ lead_stage: val }), {
    preserveState: true,
    preserveScroll: true,
    only: inboxPartialReloadKeys,
  })
}

function selectConversation(id) {
  router.get('/crm/omnichannel-inbox', listQuery({ conversation: id }), {
    preserveState: true,
    preserveScroll: true,
    only: inboxPartialReloadKeys,
  })
}

function syncCrmFormFromConversation(conv) {
  if (!conv) return
  crmForm.value = {
    lead_stage: conv.lead_stage || 'new_lead',
    assignees: Array.isArray(conv.assignees) ? [...conv.assignees] : [],
    assigned_teams: Array.isArray(conv.assigned_teams) ? [...conv.assigned_teams] : [],
    memo: conv.memo || '',
  }
}

/** User sedang membaca riwayat di atas — jangan auto-scroll kecuali sudah dekat bawah. */
function shouldStickToBottom() {
  const el = messagesEl.value
  if (!el) {
    return true
  }
  return el.scrollHeight - el.scrollTop - el.clientHeight < 100
}

/** Gabungkan pesan server + lokal (hindari hilang saat poll/reload lebih cepat dari DB). */
function mergeMessagesFromProps(serverMsgs) {
  const next = sortMessages(serverMsgs ?? [])
  const prev = localMessages.value
  if (prev.length === 0) {
    return next
  }
  const byId = new Map()
  for (const m of next) {
    if (m.id != null) {
      byId.set(m.id, m)
    }
  }
  for (const m of prev) {
    if (m.id != null && !byId.has(m.id)) {
      byId.set(m.id, m)
    }
  }
  return sortMessages([...byId.values()])
}

watch(
  () => props.messages,
  (val) => {
    const prev = localMessages.value
    const merged = mergeMessagesFromProps(val)
    const prevLastId = prev[prev.length - 1]?.id
    const nextLastId = merged[merged.length - 1]?.id
    localMessages.value = merged
    const newTail = nextLastId !== prevLastId || merged.length > prev.length
    if (shouldStickToBottom() && newTail) {
      scrollToBottom()
    }
  },
  { deep: true }
)

watch(
  () => props.selectedConversation?.id,
  (newId, prevId) => {
    const conv = props.selectedConversation
    selectedId.value = newId ?? null
    syncCrmFormFromConversation(conv)
    sendError.value = ''
    if (newId !== prevId) {
      composerMode.value = 'reply'
      templateMenuOpen.value = false
      emojiPickerOpen.value = false
      nextTick(() => scrollToBottom())
    }
  },
  { immediate: true }
)

function setComposerMode(mode) {
  composerMode.value = mode
  templateMenuOpen.value = false
  emojiPickerOpen.value = false
  nextTick(() => composerEl.value?.focus())
}

function insertEmoji(emoji) {
  insertEmojiIntoTextarea(composerEl.value, replyText, emoji, () => {
    emojiPickerOpen.value = false
  })
}

function onComposerInput() {
  if (composerMode.value !== 'reply') {
    templateMenuOpen.value = false
    return
  }
  const text = replyText.value
  const match = text.match(/\/([\p{L}\p{N}_-]*)$/u)
  if (match) {
    templateMenuOpen.value = true
    templateQuery.value = match[1]
    templateMenuHighlight.value = 0
    return
  }
  templateMenuOpen.value = false
  templateQuery.value = ''
}

function onComposerKeydown(e) {
  if (!templateMenuOpen.value || filteredTemplates.value.length === 0) {
    return
  }
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    templateMenuHighlight.value = Math.min(
      templateMenuHighlight.value + 1,
      filteredTemplates.value.length - 1
    )
    return
  }
  if (e.key === 'ArrowUp') {
    e.preventDefault()
    templateMenuHighlight.value = Math.max(templateMenuHighlight.value - 1, 0)
    return
  }
  if (e.key === 'Escape') {
    e.preventDefault()
    templateMenuOpen.value = false
    return
  }
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    const tpl = filteredTemplates.value[templateMenuHighlight.value]
    if (tpl) {
      applyTemplate(tpl)
    }
  }
}

function applyTemplateBody(body) {
  let text = body
  const c = props.selectedConversation
  if (c) {
    const nama = c.contact_name || c.contact_first_name || c.display_phone || ''
    text = text
      .replace(/\{\{nama\}\}/gi, nama)
      .replace(/\{\{nomor\}\}/gi, c.display_phone || '')
      .replace(/\{\{nama_depan\}\}/gi, c.contact_first_name || nama)
  }
  return text
}

function applyTemplate(tpl) {
  const body = applyTemplateBody(tpl.body)
  replyText.value = replyText.value.replace(/\/[\p{L}\p{N}_-]*$/u, body)
  templateMenuOpen.value = false
  templateQuery.value = ''
  nextTick(() => composerEl.value?.focus())
}

async function submitComposer() {
  if (templateMenuOpen.value && filteredTemplates.value.length > 0) {
    return
  }
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
      if (data.message) {
        localMessages.value = sortMessages([...localMessages.value, data.message])
      }
      replyText.value = ''
      scrollToBottom()
      await router.reload({
        only: ['conversations'],
        preserveState: true,
        preserveScroll: true,
      })
    } else {
      const { data } = await axios.post(
        `/crm/omnichannel-inbox/conversations/${selectedId.value}/messages`,
        { body }
      )
      if (data.message) {
        localMessages.value = sortMessages([...localMessages.value, data.message])
      }
      replyText.value = ''
      scrollToBottom()
      await router.reload({
        only: inboxPartialReloadKeys,
        preserveState: true,
        preserveScroll: true,
      })
    }
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
    assigned_user_ids: (f.assignees || []).map((a) => a.id),
    assigned_team_ids: (f.assigned_teams || []).map((t) => t.id),
    notify_assignees: notifyAssigneesOnSave.value,
  }
  try {
    await axios.patch(`/crm/omnichannel-inbox/conversations/${selectedId.value}`, payload)
    await router.reload({
      only: inboxPartialReloadKeys,
      preserveState: true,
      preserveScroll: true,
    })
  } catch (e) {
    crmSaveError.value = e.response?.data?.message || 'Gagal menyimpan.'
  } finally {
    crmSaving.value = false
  }
}

/**
 * Segarkan daftar percakapan, pesan terbuka, dan flag canSeeAllChats tanpa harus keluar halaman.
 * Sebelumnya reload memakai `only` tanpa `messages` + poll hanya membandingkan panjang array,
 * sehingga data di DB sudah ada tapi UI / urutan chat terasa "nyangkut".
 *
 * Catatan arsitektur: ini polling Inertia (sederhana, tanpa WebSocket). Untuk push instan,
 * langkah berikutnya bisa Laravel Reverb + broadcast saat webhook Meta menyimpan pesan.
 */
async function pollInbox() {
  if (pollInFlight) {
    return
  }
  pollInFlight = true
  try {
    await router.reload({
      only: inboxPartialReloadKeys,
      preserveState: true,
      preserveScroll: true,
    })
  } catch {
    /* ignore */
  } finally {
    pollInFlight = false
  }
}

function pollIntervalMs() {
  return document.visibilityState === 'visible' ? POLL_MS_TAB_VISIBLE : POLL_MS_TAB_HIDDEN
}

function restartInboxPollTimer() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
  pollTimer = setInterval(pollInbox, pollIntervalMs())
}

/** Satu reload singkat setelah user kembali ke tab (debounce agar tidak double dengan focus). */
function kickPollInboxSoon() {
  clearTimeout(pollKickTimer)
  pollKickTimer = setTimeout(() => pollInbox(), 350)
}

function onInboxVisibilityChange() {
  restartInboxPollTimer()
  if (document.visibilityState === 'visible') {
    kickPollInboxSoon()
  }
}

function onInboxWindowFocus() {
  if (document.visibilityState === 'visible') {
    kickPollInboxSoon()
  }
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesEl.value) {
      messagesEl.value.scrollTop = messagesEl.value.scrollHeight
    }
  })
}

function formatUserOptionLabel(opt) {
  if (!opt) return ''
  const bits = [opt.jabatan, opt.outlet].filter(Boolean)
  return bits.length ? `${opt.name} — ${bits.join(' · ')}` : opt.name
}

function formatMemberTier(level) {
  if (!level) return ''
  const s = String(level).replace(/_/g, ' ')
  return s.length ? s.charAt(0).toUpperCase() + s.slice(1) : ''
}

function formatTime(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  // Selalu WIB: tanpa ini, jam mengikuti zona waktu OS/browser (sering terasa "ngaco" vs WhatsApp).
  return d.toLocaleString('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
}

function closeEmojiPickerOnOutsideClick(e) {
  if (!emojiPickerOpen.value) {
    return
  }
  const target = e.target
  if (target instanceof Element && target.closest('[data-omni-emoji-picker]')) {
    return
  }
  emojiPickerOpen.value = false
}

onMounted(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf
  }
  scrollToBottom()
  restartInboxPollTimer()
  document.addEventListener('visibilitychange', onInboxVisibilityChange)
  window.addEventListener('focus', onInboxWindowFocus)
  document.addEventListener('mousedown', closeEmojiPickerOnOutsideClick)
})

onUnmounted(() => {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
  clearTimeout(pollKickTimer)
  document.removeEventListener('visibilitychange', onInboxVisibilityChange)
  window.removeEventListener('focus', onInboxWindowFocus)
  document.removeEventListener('mousedown', closeEmojiPickerOnOutsideClick)
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
