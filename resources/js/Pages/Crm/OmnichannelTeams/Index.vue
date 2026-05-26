<template>
  <AppLayout>
    <div class="mx-auto max-w-5xl space-y-8 p-4">
      <div>
        <h1 class="text-xl font-semibold text-slate-900">Tim Inbox Omnichannel</h1>
        <p class="mt-1 text-sm text-slate-600">
          Atur <strong>siapa saja</strong> yang melihat <strong>semua</strong> percakapan di inbox, dan buat <strong>tim</strong> untuk penugasan chat (pilih anggota dari seluruh user).
        </p>
        <Link href="/crm/omnichannel-inbox" class="mt-2 inline-block text-sm font-medium text-emerald-700 hover:underline">
          ← Kembali ke inbox
        </Link>
      </div>

      <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">Pengguna yang melihat semua inbox</h2>
        <p class="mt-1 text-xs text-slate-500">
          Pilih satu atau lebih user di bawah (daftar lengkap, bisa dicari). Hanya user yang terdaftar di sini yang mendapat tampilan tab &quot;Semua&quot; penuh dan notifikasi semua chat masuk. User lain hanya melihat chat yang ditugaskan ke mereka atau tim mereka.
        </p>
        <label class="mt-3 block text-xs font-medium text-slate-600">Pilih pengguna</label>
        <Multiselect
          v-model="fullAccessSelection"
          :options="userOptions"
          :custom-label="formatUserOptionLabel"
          :multiple="true"
          :close-on-select="false"
          :searchable="true"
          :preserve-search="true"
          label="name"
          track-by="id"
          placeholder="Cari nama / email..."
          class="omni-team-multiselect mt-1 text-sm"
        />
        <button
          type="button"
          class="mt-3 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
          :disabled="fullAccessSaving"
          @click="saveFullAccess"
        >
          {{ fullAccessSaving ? 'Menyimpan...' : 'Simpan daftar lihat semua inbox' }}
        </button>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">Template balasan cepat</h2>
        <p class="mt-1 text-xs text-slate-500">
          Di inbox, ketik <strong>/</strong> di kotak chat untuk memilih template. Shortcut opsional (mis. <code class="rounded bg-slate-100 px-1">salam</code> → ketik <code class="rounded bg-slate-100 px-1">/salam</code>).
          Placeholder:
          <code v-pre class="rounded bg-slate-100 px-1">{{nama}}</code>,
          <code v-pre class="rounded bg-slate-100 px-1">{{nomor}}</code>,
          <code v-pre class="rounded bg-slate-100 px-1">{{nama_depan}}</code>.
        </p>
        <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submitCreateTemplate">
          <div>
            <label class="text-xs font-medium text-slate-600">Judul template</label>
            <input
              v-model="tplTitle"
              type="text"
              required
              maxlength="120"
              class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"
              placeholder="Salam pembuka"
            />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-600">Shortcut (opsional)</label>
            <input
              v-model="tplShortcut"
              type="text"
              maxlength="64"
              class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm font-mono"
              placeholder="salam"
            />
          </div>
          <div class="sm:col-span-2">
            <OmniWaMessageConfigFields
              v-model:message-mode="tplMessageMode"
              :config="tplConfig"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="text-xs font-medium text-slate-600">
              {{ tplMessageMode === 'image' || tplMessageMode === 'document' ? 'Caption (opsional)' : 'Isi pesan' }}
            </label>
            <div class="relative mt-1">
              <textarea
                ref="tplBodyEl"
                v-model="tplBody"
                required
                rows="3"
                maxlength="4096"
                class="w-full rounded-lg border border-slate-200 py-2 pl-2 pr-10 text-sm"
                placeholder="Halo {{nama}}, terima kasih sudah menghubungi kami..."
              />
              <OmniEmojiPickerButton
                v-model:open="tplEmojiOpen"
                class="absolute bottom-2 right-2"
                teleport
                placement="top"
                @select="insertTplEmoji"
              />
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-4 sm:col-span-2">
            <label class="flex items-center gap-2 text-xs text-slate-700">
              <input v-model="tplActive" type="checkbox" class="rounded border-slate-300 text-emerald-600" />
              Aktif (tampil di inbox)
            </label>
            <p class="text-xs text-slate-500">Urutan tampil di inbox diatur otomatis (template baru di bawah).</p>
            <button
              type="submit"
              class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
              :disabled="tplSubmitting"
            >
              {{ tplSubmitting ? 'Menyimpan...' : 'Tambah template' }}
            </button>
          </div>
        </form>
        <div v-if="messageTemplates.length === 0" class="mt-4 text-sm text-slate-500">Belum ada template.</div>
        <div v-else class="mt-4 space-y-3">
          <div
            v-for="tpl in messageTemplates"
            :key="tpl.id"
            class="rounded-lg border border-slate-100 bg-slate-50/80 p-3 text-sm"
          >
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-900">
                  {{ tpl.title }}
                  <span v-if="tpl.shortcut" class="font-mono text-xs text-emerald-700">/{{ tpl.shortcut }}</span>
                  <span v-if="!tpl.is_active" class="ml-1 rounded bg-slate-200 px-1.5 text-[10px] text-slate-600">Nonaktif</span>
                  <span class="ml-1 text-[10px] font-normal text-slate-400">#{{ tpl.sort_order }}</span>
                </p>
                <p v-if="templateModeSummary(tpl)" class="mt-0.5 text-[10px] font-medium text-emerald-700">
                  {{ templateModeSummary(tpl) }}
                </p>
                <p class="mt-1 whitespace-pre-wrap text-xs text-slate-600">{{ tpl.body }}</p>
              </div>
              <button
                type="button"
                class="text-xs font-medium text-red-600 hover:underline"
                @click="destroyTemplate(tpl.id)"
              >
                Hapus
              </button>
            </div>
            <div class="mt-2 flex flex-wrap gap-2">
              <button
                type="button"
                class="text-xs font-medium text-slate-700 hover:underline"
                @click="toggleTemplateActive(tpl)"
              >
                {{ tpl.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
              </button>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">Tambah tim</h2>
        <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submitCreate">
          <div>
            <label class="text-xs font-medium text-slate-600">Nama tim</label>
            <input
              v-model="createName"
              type="text"
              required
              maxlength="120"
              class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"
            />
          </div>
          <div>
            <label class="text-xs font-medium text-slate-600">Deskripsi (opsional)</label>
            <input
              v-model="createDescription"
              type="text"
              maxlength="500"
              class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="text-xs font-medium text-slate-600">Anggota</label>
            <Multiselect
              v-model="createMembers"
              :options="userOptions"
              :custom-label="formatUserOptionLabel"
              :multiple="true"
              :close-on-select="false"
              :searchable="true"
              :preserve-search="true"
              label="name"
              track-by="id"
              placeholder="Pilih pengguna..."
              class="omni-team-multiselect mt-1 text-sm"
            />
          </div>
          <div class="sm:col-span-2">
            <button
              type="submit"
              class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
              :disabled="createSubmitting"
            >
              {{ createSubmitting ? 'Menyimpan...' : 'Buat tim' }}
            </button>
          </div>
        </form>
      </section>

      <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">Daftar tim</h2>
        <p v-if="teams.length === 0" class="mt-3 text-sm text-slate-500">Belum ada tim.</p>
        <div v-else class="mt-4 space-y-4">
          <div v-for="team in teams" :key="team.id" class="rounded-lg border border-slate-100 bg-slate-50/50 p-3">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p class="font-medium text-slate-900">{{ team.name }}</p>
                <p v-if="team.description" class="text-xs text-slate-600">{{ team.description }}</p>
              </div>
              <button
                type="button"
                class="text-xs font-medium text-red-600 hover:underline"
                @click="destroyTeam(team.id)"
              >
                Hapus tim
              </button>
            </div>
            <label class="mt-2 block text-xs font-medium text-slate-600">Anggota tim</label>
            <Multiselect
              v-model="memberSelections[team.id]"
              :options="userOptions"
              :custom-label="formatUserOptionLabel"
              :multiple="true"
              :close-on-select="false"
              :searchable="true"
              :preserve-search="true"
              label="name"
              track-by="id"
              placeholder="Pilih pengguna..."
              class="omni-team-multiselect mt-1 text-sm"
            />
            <button
              type="button"
              class="mt-2 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-900"
              @click="saveTeamMembers(team.id)"
            >
              Simpan anggota
            </button>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import AppLayout from '@/Layouts/AppLayout.vue'
import { insertEmojiIntoTextarea } from '@/utils/omniEmojiPicker.js'
import OmniEmojiPickerButton from '@/Components/Omnichannel/OmniEmojiPickerButton.vue'
import OmniWaMessageConfigFields from '@/Components/Omnichannel/OmniWaMessageConfigFields.vue'
import { inferSendMessageMode } from '@/utils/omniFlowGraph'

const props = defineProps({
  teams: { type: Array, default: () => [] },
  fullAccessUsers: { type: Array, default: () => [] },
  userOptions: { type: Array, default: () => [] },
  messageTemplates: { type: Array, default: () => [] },
})

const fullAccessSelection = ref([])
const fullAccessSaving = ref(false)

const createName = ref('')
const createDescription = ref('')
const createMembers = ref([])
const createSubmitting = ref(false)

function defaultTplConfig() {
  return {
    buttons: [{ id: 'btn_1', title: '' }],
    cta_url: { display_text: 'Buka link', url: '' },
    media_path: '',
    media_url: '',
    media_filename: '',
    media_mime: '',
  }
}

const tplTitle = ref('')
const tplShortcut = ref('')
const tplBody = ref('')
const tplMessageMode = ref('text')
const tplConfig = reactive(defaultTplConfig())
const tplActive = ref(true)
const tplSubmitting = ref(false)
const tplBodyEl = ref(null)
const tplEmojiOpen = ref(false)

function templateModeSummary(tpl) {
  const mode = tpl.message_mode || inferSendMessageMode({ ...tpl.config, body: tpl.body })
  if (mode === 'quick_reply') return 'WA · tombol balas'
  if (mode === 'cta_url') {
    const label = tpl.config?.cta_url?.display_text?.trim() || 'link'
    return `WA · tombol [${label}]`
  }
  if (mode === 'image') return 'WA · gambar'
  if (mode === 'document') return 'WA · PDF'
  return ''
}

const memberSelections = reactive({})

watch(
  () => props.fullAccessUsers,
  (list) => {
    if (fullAccessSaving.value) return
    fullAccessSelection.value = Array.isArray(list) ? list.map((u) => ({ ...u })) : []
  },
  { immediate: true },
)

watch(
  () => props.teams,
  (teams) => {
    for (const key of Object.keys(memberSelections)) {
      delete memberSelections[key]
    }
    for (const t of teams || []) {
      memberSelections[t.id] = Array.isArray(t.members) ? t.members.map((m) => ({ ...m })) : []
    }
  },
  { immediate: true },
)

function formatUserOptionLabel(opt) {
  if (!opt) return ''
  const bits = [opt.jabatan, opt.outlet].filter(Boolean)
  return bits.length ? `${opt.name} — ${bits.join(' · ')}` : opt.name
}

function saveFullAccess() {
  const ids = (fullAccessSelection.value || [])
    .map((u) => Number(u?.id))
    .filter((id) => Number.isFinite(id) && id > 0)

  fullAccessSaving.value = true
  router.put(
    '/crm/omnichannel-teams/full-access-users',
    { user_ids: ids },
    {
      preserveScroll: true,
      only: ['fullAccessUsers'],
      onSuccess: () => {
        fullAccessSelection.value = (props.fullAccessUsers || []).map((u) => ({ ...u }))
      },
      onFinish: () => {
        fullAccessSaving.value = false
      },
    },
  )
}

function showInertiaErrors(errors) {
  const lines = Object.entries(errors || {}).flatMap(([field, msgs]) => {
    const list = Array.isArray(msgs) ? msgs : [msgs]
    return list.map((m) => `${field}: ${m}`)
  })
  if (lines.length === 0) return
  Swal.fire('Gagal menyimpan', lines.join('\n'), 'error')
}

function submitCreate() {
  const name = createName.value.trim()
  if (!name) {
    Swal.fire('Peringatan', 'Nama tim wajib diisi.', 'warning')
    return
  }

  const userIds = (createMembers.value || [])
    .map((m) => Number(m?.id))
    .filter((id) => Number.isFinite(id) && id > 0)

  createSubmitting.value = true
  router.post(
    '/crm/omnichannel-teams',
    {
      name,
      description: createDescription.value.trim() || null,
      user_ids: userIds,
    },
    {
      preserveScroll: true,
      only: ['teams'],
      onSuccess: () => {
        createName.value = ''
        createDescription.value = ''
        createMembers.value = []
        Swal.fire({ icon: 'success', title: 'Tim dibuat', timer: 1800, showConfirmButton: false })
      },
      onError: (errors) => showInertiaErrors(errors),
      onFinish: () => {
        createSubmitting.value = false
      },
    },
  )
}

function saveTeamMembers(teamId) {
  const members = memberSelections[teamId] || []
  const userIds = members
    .map((m) => Number(m?.id))
    .filter((id) => Number.isFinite(id) && id > 0)

  router.patch(
    `/crm/omnichannel-teams/${teamId}`,
    { user_ids: userIds },
    {
      preserveScroll: true,
      only: ['teams'],
      onSuccess: () => {
        Swal.fire({ icon: 'success', title: 'Anggota tim disimpan', timer: 1500, showConfirmButton: false })
      },
      onError: (errors) => showInertiaErrors(errors),
    },
  )
}

async function destroyTeam(teamId) {
  const result = await Swal.fire({
    title: 'Hapus tim ini?',
    text: 'Penugasan chat ke tim ini akan dilepas.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  router.delete(`/crm/omnichannel-teams/${teamId}`, { preserveScroll: true })
}

function insertTplEmoji(emoji) {
  insertEmojiIntoTextarea(tplBodyEl.value, tplBody, emoji, () => {
    tplEmojiOpen.value = false
  })
}

function submitCreateTemplate() {
  const cfg = { ...tplConfig, body: tplBody.value }
  const mode = inferSendMessageMode({ ...cfg, message_mode: tplMessageMode.value })
  if (mode === 'cta_url') {
    const url = String(cfg.cta_url?.url || '').trim()
    if (!/^https:\/\//i.test(url)) {
      Swal.fire('Peringatan', 'URL tombol harus diawali https://', 'warning')
      return
    }
  }
  if (mode === 'quick_reply') {
    const hasBtn = (cfg.buttons || []).some((b) => String(b?.title || '').trim() !== '')
    if (!hasBtn) {
      Swal.fire('Peringatan', 'Isi minimal satu label tombol balas.', 'warning')
      return
    }
  }

  tplSubmitting.value = true
  router.post(
    '/crm/omnichannel-teams/message-templates',
    {
      title: tplTitle.value.trim(),
      shortcut: tplShortcut.value.trim() || null,
      body: tplBody.value.trim(),
      message_mode: mode,
      config: {
        buttons: cfg.buttons,
        cta_url: cfg.cta_url,
        media_path: cfg.media_path,
        media_filename: cfg.media_filename,
        media_mime: cfg.media_mime,
      },
      is_active: tplActive.value,
    },
    {
      preserveScroll: true,
      onFinish: () => {
        tplSubmitting.value = false
      },
      onSuccess: () => {
        tplTitle.value = ''
        tplShortcut.value = ''
        tplBody.value = ''
        tplMessageMode.value = 'text'
        Object.assign(tplConfig, defaultTplConfig())
        tplActive.value = true
        tplEmojiOpen.value = false
      },
    }
  )
}

function toggleTemplateActive(tpl) {
  router.patch(`/crm/omnichannel-teams/message-templates/${tpl.id}`, {
    is_active: !tpl.is_active,
  }, { preserveScroll: true })
}

async function destroyTemplate(id) {
  const result = await Swal.fire({
    title: 'Hapus template ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return
  router.delete(`/crm/omnichannel-teams/message-templates/${id}`, { preserveScroll: true })
}
</script>

<style scoped>
.omni-team-multiselect :deep(.multiselect__tags) {
  min-height: 40px;
  font-size: 0.875rem;
}
.omni-team-multiselect :deep(.multiselect__content-wrapper) {
  max-height: 320px;
}
</style>
