<template>
  <AppLayout>
    <div class="flex h-[calc(100vh-7rem)] min-h-[520px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <nav class="flex w-52 shrink-0 flex-col border-r border-slate-200 bg-slate-50">
        <div class="border-b border-slate-200 p-2">
          <div class="flex rounded-lg bg-slate-200/80 p-0.5">
            <button
              type="button"
              class="flex flex-1 items-center justify-center gap-1 rounded-md px-2 py-1.5 text-xs font-medium"
              :class="platform === 'instagram' ? 'bg-white text-pink-800 shadow-sm' : 'text-slate-600'"
              @click="setPlatform('instagram')"
            >
              <i class="fa-brands fa-instagram" /> IG
            </button>
            <button
              type="button"
              class="flex flex-1 items-center justify-center gap-1 rounded-md px-2 py-1.5 text-xs font-medium"
              :class="platform === 'facebook' ? 'bg-white text-blue-800 shadow-sm' : 'text-slate-600'"
              @click="setPlatform('facebook')"
            >
              <i class="fa-brands fa-facebook" /> FB
            </button>
          </div>
        </div>

        <div class="border-b border-slate-200 px-3 py-2">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            {{ platform === 'instagram' ? 'Akun Instagram' : 'Facebook Page' }}
          </p>
        </div>

        <div class="flex-1 overflow-y-auto p-2 space-y-1">
          <button
            v-for="acc in accountList"
            :key="acc.id"
            type="button"
            class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm"
            :class="selectedAccount === acc.id ? activeAccountClass : 'text-slate-600 hover:bg-white/80'"
            @click="selectAccount(acc.id)"
          >
            <i :class="[acc.icon, 'w-4 text-center']" />
            <span class="truncate">{{ acc.label }}</span>
          </button>
          <p v-if="accountList.length === 0" class="px-2 py-4 text-xs text-slate-400">
            {{ emptyAccountsHint }}
          </p>
        </div>

        <div class="border-t border-slate-200 p-2">
          <Link href="/crm/omnichannel-inbox" class="text-xs text-emerald-700 hover:underline">← Inbox DM</Link>
        </div>
      </nav>

      <aside class="flex w-80 shrink-0 flex-col border-r border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2">
          <p class="text-sm font-semibold text-slate-800">Post</p>
          <button
            type="button"
            class="rounded-lg px-2 py-1 text-xs text-slate-600 hover:bg-slate-100"
            :disabled="loadingMedia"
            @click="loadMedia"
          >
            <i class="fa-solid fa-arrows-rotate" :class="{ 'fa-spin': loadingMedia }" />
          </button>
        </div>
        <p v-if="mediaError" class="px-3 py-2 text-xs text-red-600">{{ mediaError }}</p>
        <div class="flex-1 overflow-y-auto">
          <button
            v-for="post in mediaList"
            :key="post.id"
            type="button"
            class="flex w-full gap-2 border-b border-slate-100 px-3 py-2 text-left hover:bg-slate-50"
            :class="selectedMediaId === post.id ? selectedPostClass : ''"
            @click="selectMedia(post)"
          >
            <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-slate-100">
              <img
                v-if="post.thumbnail_url"
                :src="post.thumbnail_url"
                alt=""
                class="h-full w-full object-cover"
              />
              <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                <i :class="platform === 'instagram' ? 'fa-brands fa-instagram' : 'fa-brands fa-facebook'" />
              </div>
            </div>
            <div class="min-w-0 flex-1">
              <p class="line-clamp-2 text-xs text-slate-800">{{ post.caption || '(tanpa teks)' }}</p>
              <p class="mt-1 text-[10px] text-slate-500">
                {{ post.media_type }} · {{ post.comments_count }} komentar
              </p>
              <p class="text-[10px] text-slate-400">{{ formatTime(post.timestamp) }}</p>
            </div>
          </button>
          <p v-if="!loadingMedia && mediaList.length === 0 && !mediaError" class="px-3 py-6 text-center text-xs text-slate-400">
            Belum ada post atau permission belum aktif.
          </p>
        </div>
      </aside>

      <section class="flex min-w-0 flex-1 flex-col">
        <div v-if="!selectedMedia" class="flex flex-1 items-center justify-center text-sm text-slate-400">
          Pilih post untuk melihat komentar
        </div>
        <template v-else>
          <div class="border-b border-slate-200 px-4 py-3">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="line-clamp-2 text-sm font-medium text-slate-900">{{ selectedMedia.caption || 'Post' }}</p>
                <a
                  v-if="selectedMedia.permalink"
                  :href="selectedMedia.permalink"
                  target="_blank"
                  rel="noopener"
                  class="mt-1 inline-block text-xs hover:underline"
                  :class="platform === 'instagram' ? 'text-pink-700' : 'text-blue-700'"
                >
                  Buka di {{ platform === 'instagram' ? 'Instagram' : 'Facebook' }} ↗
                </a>
              </div>
              <span class="text-xs text-slate-500">{{ commentsList.length }} komentar dimuat</span>
            </div>
          </div>

          <p v-if="commentsError" class="px-4 py-2 text-xs text-red-600">{{ commentsError }}</p>

          <div class="flex-1 overflow-y-auto px-4 py-3 space-y-4">
            <div v-if="loadingComments" class="text-center text-sm text-slate-400">Memuat komentar…</div>

            <div
              v-for="c in commentsList"
              :key="c.id"
              class="rounded-xl border border-slate-200 bg-slate-50/50 p-3"
            >
              <div class="flex items-start gap-2">
                <img
                  v-if="c.avatar_url"
                  :src="c.avatar_url"
                  alt=""
                  class="h-8 w-8 shrink-0 rounded-full object-cover"
                />
                <div
                  v-else
                  class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs text-slate-500"
                >
                  {{ (c.username || '?').charAt(0) }}
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-900">{{ c.username || 'Pengguna' }}</p>
                    <span class="shrink-0 text-[10px] text-slate-400">{{ formatTime(c.timestamp) }}</span>
                  </div>
                  <p class="mt-1 text-sm text-slate-800 whitespace-pre-wrap">{{ c.text }}</p>
                </div>
              </div>

              <div v-if="c.replies?.length" class="mt-3 ml-10 space-y-2 border-l-2 pl-3" :class="replyBorderClass">
                <div v-for="r in c.replies" :key="r.id" class="text-sm">
                  <span class="font-medium" :class="platform === 'instagram' ? 'text-pink-800' : 'text-blue-800'">
                    {{ r.username || 'Balasan' }}
                  </span>
                  <span class="text-slate-700"> {{ r.text }}</span>
                  <span class="ml-1 text-[10px] text-slate-400">{{ formatTime(r.timestamp) }}</span>
                </div>
              </div>

              <div class="mt-3 flex items-end gap-1.5">
                <div class="relative min-w-0 flex-1">
                  <textarea
                    :ref="(el) => setReplyInputRef(c.id, el)"
                    v-model="replyDrafts[c.id]"
                    rows="2"
                    placeholder="Balas komentar… (Enter kirim, Shift+Enter baris baru)"
                    class="w-full resize-none rounded-lg border border-slate-200 py-2 pl-3 pr-20 text-sm focus:outline-none focus:ring-1"
                    :class="platform === 'instagram' ? 'focus:border-pink-500 focus:ring-pink-500' : 'focus:border-blue-500 focus:ring-blue-500'"
                    @keydown.enter.exact.prevent="sendReply(c.id)"
                  />
                  <div class="absolute bottom-1.5 right-1.5 flex items-center gap-0.5">
                    <button
                      type="button"
                      class="flex h-7 w-7 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 disabled:opacity-40"
                      :disabled="!mentionHandle(c.username)"
                      title="Tag pengguna"
                      @click="insertMention(c.id, c.username)"
                    >
                      <i class="fa-solid fa-at text-sm" />
                    </button>
                    <OmniEmojiPickerButton
                      :open="emojiPickerCommentId === c.id"
                      button-size="sm"
                      placement="top"
                      teleport
                      panel-size="lg"
                      panel-width="22rem"
                      :disabled="replyingId === c.id"
                      @update:open="(open) => onEmojiPickerToggle(c.id, open)"
                      @select="(emoji) => insertEmoji(c.id, emoji)"
                    />
                  </div>
                </div>
                <button
                  type="button"
                  class="shrink-0 rounded-lg px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                  :class="platform === 'instagram' ? 'bg-pink-600 hover:bg-pink-700' : 'bg-blue-600 hover:bg-blue-700'"
                  :disabled="replyingId === c.id || !replyDrafts[c.id]?.trim()"
                  @click="sendReply(c.id)"
                >
                  {{ replyingId === c.id ? '…' : 'Balas' }}
                </button>
              </div>
            </div>

            <p v-if="!loadingComments && commentsList.length === 0 && !commentsError" class="text-center text-sm text-slate-400">
              Tidak ada komentar pada post ini.
            </p>
          </div>
        </template>
      </section>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import OmniEmojiPickerButton from '@/Components/Omnichannel/OmniEmojiPickerButton.vue'
import { insertEmojiIntoTextarea } from '@/utils/omniEmojiPicker.js'

const props = defineProps({
  platform: { type: String, default: 'instagram' },
  instagramAccounts: { type: Array, default: () => [] },
  facebookPages: { type: Array, default: () => [] },
  selectedAccount: { type: String, default: '' },
  initialPostId: { type: String, default: '' },
})

const platform = ref(props.platform === 'facebook' ? 'facebook' : 'instagram')
const selectedAccount = ref(props.selectedAccount || '')

const accountList = computed(() => {
  if (platform.value === 'facebook') {
    return (props.facebookPages || []).map((p) => ({
      id: p.page_id,
      label: p.label,
      icon: 'fa-brands fa-facebook text-blue-600',
    }))
  }
  return (props.instagramAccounts || []).map((a) => ({
    id: a.ig_id,
    label: a.label,
    icon: 'fa-brands fa-instagram text-pink-600',
  }))
})

const emptyAccountsHint = computed(() =>
  platform.value === 'facebook'
    ? 'Isi META_PAGE_TOKENS di .env'
    : 'Isi META_INSTAGRAM_LOGIN_TOKENS di .env'
)

const activeAccountClass = computed(() =>
  platform.value === 'instagram'
    ? 'bg-white font-medium text-pink-800 shadow-sm ring-1 ring-pink-200'
    : 'bg-white font-medium text-blue-800 shadow-sm ring-1 ring-blue-200'
)

const selectedPostClass = computed(() =>
  platform.value === 'instagram'
    ? 'bg-pink-50/80 ring-1 ring-inset ring-pink-200'
    : 'bg-blue-50/80 ring-1 ring-inset ring-blue-200'
)

const replyBorderClass = computed(() =>
  platform.value === 'instagram' ? 'border-pink-200' : 'border-blue-200'
)

const mediaList = ref([])
const selectedMediaId = ref(null)
const selectedMedia = ref(null)
const commentsList = ref([])
const loadingMedia = ref(false)
const loadingComments = ref(false)
const mediaError = ref('')
const commentsError = ref('')
const replyDrafts = reactive({})
const replyingId = ref(null)
const emojiPickerCommentId = ref(null)
/** @type {Record<string, HTMLTextAreaElement | null>} */
const replyInputRefs = {}

function setReplyInputRef(commentId, el) {
  if (el) {
    replyInputRefs[commentId] = el
  } else {
    delete replyInputRefs[commentId]
  }
}

function replyTextRef(commentId) {
  return {
    get value() {
      return replyDrafts[commentId] ?? ''
    },
    set value(v) {
      replyDrafts[commentId] = v
    },
  }
}

function mentionHandle(username) {
  const u = (username || '').trim().replace(/^@+/, '').replace(/\s+/g, '')
  if (!u || u === 'Pengguna') return ''
  return u
}

function insertMention(commentId, username) {
  const handle = mentionHandle(username)
  if (!handle) return
  insertEmojiIntoTextarea(replyInputRefs[commentId] ?? null, replyTextRef(commentId), `@${handle} `)
}

function insertEmoji(commentId, emoji) {
  insertEmojiIntoTextarea(replyInputRefs[commentId] ?? null, replyTextRef(commentId), emoji, () => {
    emojiPickerCommentId.value = null
  })
}

function onEmojiPickerToggle(commentId, open) {
  emojiPickerCommentId.value = open ? commentId : null
}

function ensureDefaultAccount() {
  if (!selectedAccount.value && accountList.value.length > 0) {
    selectedAccount.value = accountList.value[0].id
  }
}

watch(
  () => props.selectedAccount,
  (v) => {
    if (v) selectedAccount.value = v
  }
)

watch(platform, () => {
  ensureDefaultAccount()
  resetPosts()
  loadMedia()
})

watch(selectedAccount, () => {
  resetPosts()
  loadMedia()
})

onMounted(async () => {
  ensureDefaultAccount()
  if (selectedAccount.value) {
    await loadMedia()
    if (props.initialPostId) {
      const post = mediaList.value.find((p) => p.id === props.initialPostId)
      if (post) await selectMedia(post)
    }
  }
})

function resetPosts() {
  selectedMediaId.value = null
  selectedMedia.value = null
  commentsList.value = []
  mediaList.value = []
  emojiPickerCommentId.value = null
}

function setPlatform(p) {
  platform.value = p
  selectedAccount.value = accountList.value[0]?.id || ''
  router.get('/crm/instagram-comments', { platform: p, account: selectedAccount.value }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function selectAccount(id) {
  selectedAccount.value = id
  router.get('/crm/instagram-comments', { platform: platform.value, account: id }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function mediaUrl() {
  if (platform.value === 'facebook') {
    return `/crm/instagram-comments/facebook/${selectedAccount.value}/posts`
  }
  return `/crm/instagram-comments/${selectedAccount.value}/media`
}

function commentsUrl(postId) {
  if (platform.value === 'facebook') {
    return `/crm/instagram-comments/facebook/${selectedAccount.value}/posts/${postId}/comments`
  }
  return `/crm/instagram-comments/${selectedAccount.value}/media/${postId}/comments`
}

function replyUrl(commentId) {
  if (platform.value === 'facebook') {
    return `/crm/instagram-comments/facebook/${selectedAccount.value}/comments/${commentId}/reply`
  }
  return `/crm/instagram-comments/${selectedAccount.value}/comments/${commentId}/reply`
}

async function loadMedia() {
  if (!selectedAccount.value) return
  loadingMedia.value = true
  mediaError.value = ''
  try {
    const { data } = await axios.get(mediaUrl())
    mediaList.value = data.media ?? []
  } catch (e) {
    mediaList.value = []
    mediaError.value = e.response?.data?.message || e.message || 'Gagal memuat post'
  } finally {
    loadingMedia.value = false
  }
}

async function selectMedia(post) {
  selectedMediaId.value = post.id
  selectedMedia.value = post
  commentsList.value = []
  commentsError.value = ''
  emojiPickerCommentId.value = null
  loadingComments.value = true
  try {
    const { data } = await axios.get(commentsUrl(post.id))
    commentsList.value = data.comments ?? []
  } catch (e) {
    commentsError.value = e.response?.data?.message || e.message || 'Gagal memuat komentar'
  } finally {
    loadingComments.value = false
  }
}

async function sendReply(commentId) {
  const text = (replyDrafts[commentId] || '').trim()
  if (!text || !selectedAccount.value) return
  replyingId.value = commentId
  try {
    await axios.post(replyUrl(commentId), { message: text })
    replyDrafts[commentId] = ''
    if (selectedMedia.value) await selectMedia(selectedMedia.value)
  } catch (e) {
    alert(e.response?.data?.message || e.message || 'Gagal membalas')
  } finally {
    replyingId.value = null
  }
}

function formatTime(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString('id-ID', {
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return iso
  }
}
</script>
