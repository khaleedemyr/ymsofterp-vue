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
          Pilih satu atau lebih user di bawah (daftar lengkap, bisa dicari). Hanya user yang terdaftar di sini yang mendapat tampilan tab &quot;Semua&quot; penuh. User lain tetap melihat percakapan relevan (tugas pribadi, tim, atau belum ditugaskan).
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
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  teams: { type: Array, default: () => [] },
  fullAccessUsers: { type: Array, default: () => [] },
  userOptions: { type: Array, default: () => [] },
})

const fullAccessSelection = ref([])
const fullAccessSaving = ref(false)

const createName = ref('')
const createDescription = ref('')
const createMembers = ref([])
const createSubmitting = ref(false)

const memberSelections = reactive({})

watch(
  () => props.fullAccessUsers,
  (list) => {
    fullAccessSelection.value = Array.isArray(list) ? [...list] : []
  },
  { immediate: true, deep: true }
)

watch(
  () => props.teams,
  (teams) => {
    for (const key of Object.keys(memberSelections)) {
      delete memberSelections[key]
    }
    for (const t of teams || []) {
      memberSelections[t.id] = Array.isArray(t.members) ? [...t.members] : []
    }
  },
  { immediate: true, deep: true }
)

function formatUserOptionLabel(opt) {
  if (!opt) return ''
  const bits = [opt.jabatan, opt.outlet].filter(Boolean)
  return bits.length ? `${opt.name} — ${bits.join(' · ')}` : opt.name
}

function saveFullAccess() {
  fullAccessSaving.value = true
  router.put(
    '/crm/omnichannel-teams/full-access-users',
    {
      user_ids: (fullAccessSelection.value || []).map((u) => u.id),
    },
    {
      preserveScroll: true,
      onFinish: () => {
        fullAccessSaving.value = false
      },
    }
  )
}

function submitCreate() {
  createSubmitting.value = true
  router.post(
    '/crm/omnichannel-teams',
    {
      name: createName.value.trim(),
      description: createDescription.value.trim() || null,
      user_ids: (createMembers.value || []).map((m) => m.id),
    },
    {
      preserveScroll: true,
      onFinish: () => {
        createSubmitting.value = false
      },
      onSuccess: () => {
        createName.value = ''
        createDescription.value = ''
        createMembers.value = []
      },
    }
  )
}

function saveTeamMembers(teamId) {
  const members = memberSelections[teamId] || []
  router.patch(`/crm/omnichannel-teams/${teamId}`, {
    user_ids: members.map((m) => m.id),
  }, { preserveScroll: true })
}

function destroyTeam(teamId) {
  if (!confirm('Hapus tim ini? Penugasan chat ke tim ini akan dilepas.')) return
  router.delete(`/crm/omnichannel-teams/${teamId}`, { preserveScroll: true })
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
