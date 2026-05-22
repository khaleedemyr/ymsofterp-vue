<template>
  <AppLayout>
    <div class="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
      <div class="flex items-center gap-3">
        <Link
          href="/crm/wa-broadcast"
          class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
        >
          <i class="fa-solid fa-arrow-left" />
        </Link>
        <div>
          <h1 class="text-xl font-bold text-slate-900">Buat campaign baru</h1>
          <p class="text-sm text-slate-500">Sisa kuota hari ini: {{ dailyRemaining.toLocaleString('id-ID') }} pesan</p>
        </div>
      </div>

      <!-- Template info -->
      <div class="rounded-2xl border border-blue-100 bg-blue-50/60 p-4">
        <div class="flex gap-3">
          <i class="fa-solid fa-circle-info mt-0.5 text-blue-600" />
          <div class="text-sm text-blue-900">
            <p class="font-semibold">Di mana membuat template?</p>
            <p class="mt-1 text-blue-800/90">
              Template WhatsApp <strong>tidak dibuat di ERP</strong>. Buat & ajukan persetujuan di
              <a
                :href="metaTemplatesUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="font-semibold underline"
              >Meta WhatsApp Manager</a>
              (WhatsApp Business). Setelah status <em>Approved</em>, klik
              <strong>Muat template dari Meta</strong> di bawah untuk memilih template.
            </p>
          </div>
        </div>
      </div>

      <form class="space-y-6" @submit.prevent>
        <!-- Step 1: Basic -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-xs text-white">1</span>
            Informasi campaign
          </h2>
          <div class="mt-4">
            <label class="block text-sm font-medium text-slate-700">Nama campaign</label>
            <input
              v-model="form.name"
              type="text"
              class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-[#128C7E] focus:ring-1 focus:ring-[#128C7E]"
              placeholder="Contoh: Promo Ramadan 2026"
            />
          </div>
        </section>

        <!-- Step 2: Recipients -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-xs text-white">2</span>
            Penerima
          </h2>

          <div class="mt-4 flex flex-wrap gap-4">
            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm has-[:checked]:border-[#128C7E] has-[:checked]:bg-[#128C7E]/5">
              <input v-model="form.sources.member" type="checkbox" class="rounded text-[#128C7E]" />
              Data member
            </label>
            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm has-[:checked]:border-[#128C7E] has-[:checked]:bg-[#128C7E]/5">
              <input v-model="form.sources.omni" type="checkbox" class="rounded text-[#128C7E]" />
              Kontak omnichannel (WA)
            </label>
          </div>

          <div v-if="form.sources.member" class="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-4 space-y-3">
            <p class="text-xs font-semibold uppercase text-slate-500">Filter member</p>
            <div class="grid gap-2 sm:grid-cols-2">
              <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.member.is_active" type="checkbox" class="rounded" /> Hanya aktif
              </label>
              <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.member.allow_notification_only" type="checkbox" class="rounded" /> allow_notification
              </label>
              <label class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.member.mobile_verified_only" type="checkbox" class="rounded" /> Nomor terverifikasi
              </label>
            </div>
            <div>
              <p class="mb-2 text-xs text-slate-600">Level member</p>
              <div class="flex flex-wrap gap-2">
                <label
                  v-for="lv in memberLevels"
                  :key="lv"
                  class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs"
                >
                  <input v-model="form.member.levels" type="checkbox" :value="lv" /> {{ lv }}
                </label>
              </div>
            </div>
            <input
              v-model="form.member.min_spending"
              type="number"
              min="0"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
              placeholder="Min total spending (opsional)"
            />
            <input
              v-model="form.member.search"
              type="text"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
              placeholder="Cari nama / member_id / nomor HP"
            />
          </div>

          <div v-if="form.sources.omni" class="mt-4 rounded-xl border border-slate-100 bg-slate-50 p-4 space-y-3">
            <p class="text-xs font-semibold uppercase text-slate-500">Filter kontak omnichannel</p>
            <select v-model="form.omni.has_member" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
              <option value="">Semua kontak</option>
              <option value="yes">Sudah terhubung member</option>
              <option value="no">Belum terhubung member</option>
            </select>
            <input
              v-model="form.omni.search"
              type="text"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
              placeholder="Cari nama / nomor"
            />
          </div>

          <div class="mt-4">
            <label class="block text-sm font-medium text-slate-700">ID member manual (pisah koma)</label>
            <input
              v-model="form.manualMemberIds"
              type="text"
              class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm"
              placeholder="12, 45, 99"
            />
          </div>

          <button
            type="button"
            class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
            :disabled="previewLoading"
            @click="previewRecipients"
          >
            <i class="fa-solid fa-users" :class="{ 'fa-spin': previewLoading }" />
            Hitung penerima
          </button>

          <div v-if="previewCount !== null" class="mt-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <strong>{{ previewCount.toLocaleString('id-ID') }}</strong> nomor unik siap dikirim
          </div>
        </section>

        <!-- Step 3: Message -->
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-xs text-white">3</span>
            Isi pesan
          </h2>

          <div class="mt-4">
            <label class="block text-sm font-medium text-slate-700">Jenis pesan</label>
            <select
              v-model="form.messageType"
              class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm"
            >
              <option value="template">Template resmi Meta (disarankan)</option>
              <option value="session_text">Teks bebas (jendela 24 jam)</option>
            </select>
          </div>

          <template v-if="form.messageType === 'template'">
            <div class="mt-4 flex flex-wrap items-center gap-3">
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50"
                :disabled="templatesLoading"
                @click="loadTemplates"
              >
                <i class="fa-solid fa-cloud-arrow-down" :class="{ 'fa-spin': templatesLoading }" />
                Muat template dari Meta
              </button>
              <span v-if="templates.length" class="text-xs text-slate-500">{{ templates.length }} template approved</span>
            </div>

            <div class="mt-4 space-y-3">
              <label class="block text-sm font-medium text-slate-700">Pilih template</label>
              <select
                v-model="form.templateName"
                class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm"
              >
                <option value="">— Pilih template —</option>
                <option v-for="t in templates" :key="t.name + t.language" :value="t.name">
                  {{ t.name }} · {{ t.language }} · {{ t.category }}
                </option>
              </select>
              <div>
                <label class="block text-sm font-medium text-slate-700">Bahasa</label>
                <input
                  v-model="form.templateLanguage"
                  type="text"
                  class="mt-1 w-32 rounded-xl border border-slate-200 px-4 py-2.5 text-sm"
                  placeholder="id"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700">Variabel body (satu per baris, urutan {{1}}, {{2}}…)</label>
                <textarea
                  v-model="form.templateParamsText"
                  rows="4"
                  class="mt-1 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-mono"
                  placeholder="Halo {{1}}, promo khusus…"
                />
              </div>
            </div>
          </template>

          <template v-else>
            <textarea
              v-model="form.sessionText"
              rows="5"
              class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm"
              placeholder="Isi pesan…"
            />
            <p class="mt-2 text-xs text-amber-700">Hanya untuk kontak yang pernah chat dalam 24 jam terakhir.</p>
          </template>
        </section>

        <p v-if="formError" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ formError }}</p>

        <div class="flex flex-wrap gap-3 pb-8">
          <Link
            href="/crm/wa-broadcast"
            class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Batal
          </Link>
          <button
            type="button"
            class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50 disabled:opacity-50"
            :disabled="saving"
            @click="createCampaign(false)"
          >
            Simpan draft
          </button>
          <button
            type="button"
            class="rounded-xl bg-[#128C7E] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#0d6b5c] disabled:opacity-50"
            :disabled="saving || previewCount === 0"
            @click="createCampaign(true)"
          >
            {{ saving ? 'Memproses…' : 'Kirim sekarang' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  dailyCap: { type: Number, default: 100000 },
  dailySent: { type: Number, default: 0 },
  dailyRemaining: { type: Number, default: 100000 },
  memberLevels: { type: Array, default: () => ['Silver', 'Loyal', 'Elite'] },
  metaTemplatesUrl: { type: String, default: 'https://business.facebook.com/latest/whatsapp_manager/message_templates' },
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
const templates = ref([])
const templatesLoading = ref(false)
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

  const ids = form.manualMemberIds
    .split(/[,\s]+/)
    .map((s) => parseInt(s.trim(), 10))
    .filter((n) => n > 0)
  if (ids.length) filters.manual_member_ids = ids

  return filters
}

async function previewRecipients() {
  previewLoading.value = true
  formError.value = ''
  previewCount.value = null
  try {
    const { data } = await axios.post('/crm/wa-broadcast/preview-recipients', {
      filter_definition: buildFilterDefinition(),
    })
    previewCount.value = data.count ?? 0
  } catch (e) {
    formError.value = e.response?.data?.message || e.message
  } finally {
    previewLoading.value = false
  }
}

async function loadTemplates() {
  templatesLoading.value = true
  formError.value = ''
  try {
    const { data } = await axios.get('/crm/wa-broadcast/templates')
    templates.value = data.templates ?? []
    if (!templates.value.length) {
      formError.value = 'Tidak ada template approved. Buat template di Meta WhatsApp Manager terlebih dahulu.'
    }
  } catch (e) {
    formError.value = e.response?.data?.message || 'Gagal memuat template dari Meta'
  } finally {
    templatesLoading.value = false
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
    router.visit('/crm/wa-broadcast')
  } catch (e) {
    formError.value = e.response?.data?.message || e.message
  } finally {
    saving.value = false
  }
}
</script>
