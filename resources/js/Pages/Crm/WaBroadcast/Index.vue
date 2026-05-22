<template>
  <AppLayout>
    <div class="mx-auto max-w-6xl space-y-6 p-4">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="text-xl font-bold text-slate-900">Broadcast WhatsApp</h1>
          <p class="mt-1 text-sm text-slate-600">
            Kirim pesan massal via template resmi Meta. Sumber kontak: member + omnichannel.
          </p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm">
          <p class="font-medium text-emerald-900">Kuota hari ini</p>
          <p class="text-emerald-800">
            Terpakai {{ dailySent.toLocaleString('id-ID') }} / {{ dailyCap.toLocaleString('id-ID') }}
            · sisa {{ dailyRemaining.toLocaleString('id-ID') }}
          </p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-800">Buat campaign baru</h2>

          <div class="mt-4 space-y-3">
            <label class="block text-xs font-medium text-slate-600">Nama campaign</label>
            <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Promo Ramadan 2026" />

            <label class="block text-xs font-medium text-slate-600">Sumber penerima</label>
            <div class="flex flex-wrap gap-3 text-sm">
              <label class="flex items-center gap-1.5">
                <input v-model="form.sources.member" type="checkbox" class="rounded" /> Data member
              </label>
              <label class="flex items-center gap-1.5">
                <input v-model="form.sources.omni" type="checkbox" class="rounded" /> Omni kontak (WA)
              </label>
            </div>

            <div v-if="form.sources.member" class="rounded-lg border border-slate-100 bg-slate-50 p-3 space-y-2">
              <p class="text-xs font-semibold text-slate-700">Filter member</p>
              <label class="flex items-center gap-2 text-xs">
                <input v-model="form.member.is_active" type="checkbox" /> Hanya member aktif
              </label>
              <label class="flex items-center gap-2 text-xs">
                <input v-model="form.member.allow_notification_only" type="checkbox" /> allow_notification = true
              </label>
              <label class="flex items-center gap-2 text-xs">
                <input v-model="form.member.mobile_verified_only" type="checkbox" /> Nomor terverifikasi
              </label>
              <div>
                <p class="text-xs text-slate-600 mb-1">Level member (kosongkan = semua)</p>
                <div class="flex flex-wrap gap-2">
                  <label v-for="lv in memberLevels" :key="lv" class="flex items-center gap-1 text-xs">
                    <input v-model="form.member.levels" type="checkbox" :value="lv" /> {{ lv }}
                  </label>
                </div>
              </div>
              <input v-model="form.member.min_spending" type="number" min="0" step="1000" class="w-full rounded border border-slate-200 px-2 py-1 text-xs" placeholder="Min total spending (opsional)" />
              <input v-model="form.member.search" type="text" class="w-full rounded border border-slate-200 px-2 py-1 text-xs" placeholder="Cari nama / member_id / HP" />
            </div>

            <div v-if="form.sources.omni" class="rounded-lg border border-slate-100 bg-slate-50 p-3 space-y-2">
              <p class="text-xs font-semibold text-slate-700">Filter omni kontak</p>
              <select v-model="form.omni.has_member" class="w-full rounded border border-slate-200 px-2 py-1 text-xs">
                <option value="">Semua kontak</option>
                <option value="yes">Sudah terhubung member</option>
                <option value="no">Belum terhubung member</option>
              </select>
              <input v-model="form.omni.search" type="text" class="w-full rounded border border-slate-200 px-2 py-1 text-xs" placeholder="Cari nama / nomor" />
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600">ID member manual (pisah koma)</label>
              <input v-model="form.manualMemberIds" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="12,45,99" />
            </div>

            <button
              type="button"
              class="rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50"
              :disabled="previewLoading"
              @click="previewRecipients"
            >
              <i class="fa-solid fa-calculator mr-1" :class="{ 'fa-spin': previewLoading }" />
              Preview jumlah penerima
            </button>
            <p v-if="previewCount !== null" class="text-sm font-medium text-slate-800">
              Estimasi: <span class="text-emerald-700">{{ previewCount.toLocaleString('id-ID') }}</span> nomor unik
            </p>
            <ul v-if="previewSample.length" class="text-xs text-slate-600 space-y-0.5 max-h-24 overflow-y-auto">
              <li v-for="(s, i) in previewSample" :key="i">
                {{ s.display_name || '-' }} · {{ s.phone_normalized }} ({{ s.source }})
              </li>
            </ul>

            <hr class="border-slate-100" />

            <label class="block text-xs font-medium text-slate-600">Jenis pesan</label>
            <select v-model="form.messageType" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
              <option value="template">Template resmi Meta (disarankan)</option>
              <option value="session_text">Teks bebas (hanya jendela 24 jam)</option>
            </select>

            <template v-if="form.messageType === 'template'">
              <button type="button" class="text-xs text-emerald-700 hover:underline" @click="loadTemplates">Muat template dari Meta</button>
              <select v-model="form.templateName" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">Pilih template…</option>
                <option v-for="t in templates" :key="t.name + t.language" :value="t.name">
                  {{ t.name }} ({{ t.language }}) — {{ t.category }}
                </option>
              </select>
              <input v-model="form.templateLanguage" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Bahasa template, mis. id" />
              <label class="block text-xs text-slate-600">Variabel body template (satu per baris, urutan {{1}}, {{2}}…)</label>
              <textarea v-model="form.templateParamsText" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            </template>
            <template v-else>
              <textarea v-model="form.sessionText" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Isi pesan…" />
              <p class="text-xs text-amber-700">Hanya untuk kontak yang pernah chat dalam 24 jam terakhir.</p>
            </template>

            <div class="flex gap-2 pt-2">
              <button
                type="button"
                class="rounded-lg bg-slate-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50"
                :disabled="saving"
                @click="createCampaign(false)"
              >
                Simpan draft
              </button>
              <button
                type="button"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                :disabled="saving || previewCount === 0"
                @click="createCampaign(true)"
              >
                {{ saving ? 'Memproses…' : 'Kirim sekarang' }}
              </button>
            </div>
            <p v-if="formError" class="text-xs text-red-600">{{ formError }}</p>
          </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-800">Riwayat campaign</h2>
          <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="text-slate-500">
                <tr>
                  <th class="py-2 pr-2">Nama</th>
                  <th class="py-2 pr-2">Status</th>
                  <th class="py-2 pr-2">Ter kirim</th>
                  <th class="py-2">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in campaigns" :key="c.id" class="border-t border-slate-100">
                  <td class="py-2 pr-2 font-medium text-slate-800">{{ c.name }}</td>
                  <td class="py-2 pr-2">
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusClass(c.status)">{{ c.status }}</span>
                  </td>
                  <td class="py-2 pr-2">
                    {{ c.recipient_count_sent }} / {{ c.recipient_count_total || c.recipient_count_estimated }}
                  </td>
                  <td class="py-2">
                    <button
                      v-if="c.status === 'draft' || c.status === 'paused'"
                      type="button"
                      class="text-emerald-700 hover:underline"
                      @click="startCampaign(c.id)"
                    >
                      Jalankan
                    </button>
                    <button
                      v-if="c.status === 'running'"
                      type="button"
                      class="text-amber-700 hover:underline ml-2"
                      @click="pauseCampaign(c.id)"
                    >
                      Jeda
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="campaigns.length === 0" class="py-6 text-center text-slate-400">Belum ada campaign.</p>
          </div>
          <p class="mt-4 text-xs text-slate-500">
            Pastikan queue worker jalan: <code class="bg-slate-100 px-1 rounded">php artisan queue:work --queue=wa-broadcast,omnichannel,notifications</code>
          </p>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  campaigns: { type: Array, default: () => [] },
  dailyCap: { type: Number, default: 100000 },
  dailySent: { type: Number, default: 0 },
  dailyRemaining: { type: Number, default: 100000 },
  memberLevels: { type: Array, default: () => ['Silver', 'Loyal', 'Elite'] },
})

const form = reactive({
  name: '',
  sources: { member: true, omni: true },
  member: {
    is_active: true,
    allow_notification_only: false,
    mobile_verified_only: false,
    levels: [],
    min_spending: '',
    search: '',
  },
  omni: { has_member: '', search: '' },
  manualMemberIds: '',
  messageType: 'template',
  templateName: '',
  templateLanguage: 'id',
  templateParamsText: '',
  sessionText: '',
})

const previewLoading = ref(false)
const previewCount = ref(null)
const previewSample = ref([])
const templates = ref([])
const saving = ref(false)
const formError = ref('')

function buildFilterDefinition() {
  const filters = {
    sources: [],
    dedupe: true,
    member: {},
    omni_contact: {},
  }
  if (form.sources.member) filters.sources.push('member')
  if (form.sources.omni) filters.sources.push('omni_contact')

  filters.member = {
    is_active: form.member.is_active,
    allow_notification_only: form.member.allow_notification_only,
    mobile_verified_only: form.member.mobile_verified_only,
    min_total_spending: form.member.min_spending ? Number(form.member.min_spending) : null,
    member_levels: form.member.levels.length ? [...form.member.levels] : [],
    search: form.member.search || null,
  }

  if (form.omni.has_member === 'yes') {
    filters.omni_contact.has_member_link = true
  } else if (form.omni.has_member === 'no') {
    filters.omni_contact.has_member_link = false
  }
  filters.omni_contact.search = form.omni.search || null

  const ids = form.manualMemberIds.split(/[,\s]+/).map((s) => parseInt(s.trim(), 10)).filter((n) => n > 0)
  if (ids.length) filters.manual_member_ids = ids

  return filters
}

async function previewRecipients() {
  previewLoading.value = true
  formError.value = ''
  try {
    const { data } = await axios.post('/crm/wa-broadcast/preview-recipients', {
      filter_definition: buildFilterDefinition(),
    })
    previewCount.value = data.count ?? 0
    previewSample.value = data.sample ?? []
  } catch (e) {
    formError.value = e.response?.data?.message || e.message
  } finally {
    previewLoading.value = false
  }
}

async function loadTemplates() {
  try {
    const { data } = await axios.get('/crm/wa-broadcast/templates')
    templates.value = data.templates ?? []
  } catch (e) {
    formError.value = e.response?.data?.message || 'Gagal memuat template'
  }
}

async function createCampaign(startNow) {
  saving.value = true
  formError.value = ''
  try {
    await axios.post('/crm/wa-broadcast/campaigns', {
      name: form.name || 'Broadcast WA',
      message_type: form.messageType,
      template_name: form.templateName,
      template_language: form.templateLanguage,
      template_body_params: form.templateParamsText.split('\n').map((s) => s.trim()).filter(Boolean),
      session_text: form.sessionText,
      filter_definition: buildFilterDefinition(),
      start_now: startNow,
    })
    router.reload()
  } catch (e) {
    formError.value = e.response?.data?.message || e.message
  } finally {
    saving.value = false
  }
}

async function startCampaign(id) {
  await axios.post(`/crm/wa-broadcast/campaigns/${id}/start`)
  router.reload()
}

async function pauseCampaign(id) {
  await axios.post(`/crm/wa-broadcast/campaigns/${id}/pause`)
  router.reload()
}

function statusClass(status) {
  if (status === 'running') return 'bg-emerald-100 text-emerald-800'
  if (status === 'completed') return 'bg-slate-100 text-slate-700'
  if (status === 'failed') return 'bg-red-100 text-red-800'
  if (status === 'paused') return 'bg-amber-100 text-amber-800'
  return 'bg-slate-100 text-slate-600'
}
</script>
