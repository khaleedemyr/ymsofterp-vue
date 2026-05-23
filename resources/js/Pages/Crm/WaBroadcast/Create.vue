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

      <div class="rounded-2xl border border-blue-100 bg-blue-50/60 p-4">
        <div class="flex gap-3">
          <i class="fa-solid fa-circle-info mt-0.5 text-blue-600" />
          <div class="text-sm text-blue-900">
            <p class="font-semibold">Template WhatsApp</p>
            <p class="mt-1 text-blue-800/90">
              Buat & ajukan template langsung di ERP (langkah 3), atau buka
              <a
                :href="metaTemplatesUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="font-semibold underline"
              >Meta WhatsApp Manager</a>.
              Setelah status <em>Approved</em>, pilih template untuk broadcast.
            </p>
          </div>
        </div>
      </div>

      <form class="space-y-6" @submit.prevent>
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

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-xs text-white">2</span>
            Penerima
          </h2>

          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase text-slate-500">Filter statis (selalu diterapkan)</p>
            <ul class="mt-2 space-y-1 text-sm text-slate-700">
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-check text-[#128C7E]" />
                Nomor HP terisi (tidak null / kosong di database)
              </li>
              <li class="flex items-center gap-2">
                <i class="fa-solid fa-check text-[#128C7E]" />
                Member status <strong>aktif</strong> (<code class="text-xs">is_active = 1</code>)
              </li>
              <li class="flex items-center gap-2 text-slate-500">
                <i class="fa-solid fa-info-circle" />
                Kontak omnichannel: nomor WA terisi; jika terhubung member, member harus aktif
              </li>
            </ul>
          </div>

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

          <div class="mt-4 space-y-3 rounded-xl border border-amber-100 bg-amber-50/50 p-4">
            <p class="text-xs font-semibold uppercase text-amber-800">Transaksi aktif (opsional)</p>
            <p class="text-xs text-amber-900/80">
              Member yang punya order <strong>paid</strong> di tabel <code class="rounded bg-white px-1">orders</code> pada rentang tanggal.
              Kosongkan jika tidak perlu filter transaksi.
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label class="block text-sm font-medium text-slate-700">Dari tanggal</label>
                <input
                  v-model="form.transactionFrom"
                  type="date"
                  class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700">Sampai tanggal</label>
                <input
                  v-model="form.transactionTo"
                  type="date"
                  class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                />
              </div>
            </div>
          </div>

          <div v-if="form.sources.member" class="mt-4 space-y-3 rounded-xl border border-slate-100 bg-slate-50 p-4">
            <p class="text-xs font-semibold uppercase text-slate-500">Filter member tambahan</p>
            <div class="grid gap-2 sm:grid-cols-2">
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

          <div v-if="form.sources.omni" class="mt-4 space-y-3 rounded-xl border border-slate-100 bg-slate-50 p-4">
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

          <div class="mt-4 flex flex-wrap items-center gap-3">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl bg-[#128C7E] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#0d6b5c] disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="previewLoading || !hasRecipientSource"
              @click="previewRecipients"
            >
              <i class="fa-solid fa-database" :class="{ 'fa-spin': previewLoading }" />
              {{ previewLoading ? 'Menghitung dari database…' : 'Hitung penerima' }}
            </button>
            <div
              class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm"
              :class="previewBadgeClass"
            >
              <i class="fa-solid fa-users" />
              <template v-if="previewError">
                Gagal hitung
              </template>
              <template v-else-if="previewCount !== null">
                <strong>{{ previewCount.toLocaleString('id-ID') }}</strong> nomor unik sesuai filter
              </template>
              <template v-else>
                Belum dihitung — atur filter lalu klik tombol di kiri
              </template>
            </div>
          </div>
          <p v-if="previewError" class="mt-2 text-xs text-red-700">{{ previewError }}</p>
          <p v-else-if="!hasRecipientSource" class="mt-2 text-xs text-amber-700">
            Centang minimal satu sumber penerima atau isi ID member manual.
          </p>
          <p v-else class="mt-2 text-xs text-slate-500">
            Data dari tabel <strong>member_apps_members</strong> dan/atau <strong>omni_contacts</strong> (query langsung ke database).
          </p>
          <div
            v-if="previewSample.length"
            class="mt-3 overflow-hidden rounded-xl border border-slate-200"
          >
            <p class="border-b border-slate-100 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">
              Contoh penerima (maks. 20)
            </p>
            <ul class="max-h-36 divide-y divide-slate-100 overflow-y-auto text-xs">
              <li
                v-for="(row, idx) in previewSample"
                :key="idx"
                class="flex flex-wrap gap-2 px-3 py-2 text-slate-700"
              >
                <span class="font-medium">{{ row.display_name || '—' }}</span>
                <span class="text-slate-500">{{ row.phone_normalized }}</span>
                <span class="rounded bg-slate-100 px-1.5 text-slate-500">{{ row.source }}</span>
              </li>
            </ul>
          </div>
        </section>

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
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-[#128C7E] px-4 py-2 text-sm font-medium text-[#128C7E] hover:bg-[#128C7E]/5"
                @click="showTemplateForm = !showTemplateForm"
              >
                <i class="fa-solid fa-plus" />
                {{ showTemplateForm ? 'Tutup form template' : 'Buat template baru' }}
              </button>
              <span v-if="templates.length" class="text-xs text-slate-500">{{ templates.length }} approved</span>
            </div>

            <div
              v-if="showTemplateForm"
              class="mt-4 space-y-3 rounded-xl border border-dashed border-[#128C7E]/40 bg-[#128C7E]/5 p-4"
            >
              <p class="text-xs font-semibold uppercase text-[#0d6b5c]">Ajukan template ke Meta</p>
              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="block text-sm font-medium text-slate-700">Nama template</label>
                  <input
                    v-model="newTemplate.name"
                    type="text"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    placeholder="promo_ramadan_2026"
                  />
                  <p class="mt-1 text-xs text-slate-500">Huruf kecil, angka, underscore (min. 3 karakter)</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-700">Kategori</label>
                  <select v-model="newTemplate.category" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <option value="MARKETING">Marketing</option>
                    <option value="UTILITY">Utility</option>
                    <option value="AUTHENTICATION">Authentication</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700">Bahasa</label>
                <input
                  v-model="newTemplate.language"
                  type="text"
                  class="mt-1 w-32 rounded-lg border border-slate-200 px-3 py-2 text-sm"
                  placeholder="id"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700">Isi body</label>
                <p class="mt-1 text-xs text-slate-500">
                  Variabel dinamis: tulis <code class="rounded bg-white px-1">&#123;&#123;1&#125;&#125;</code>,
                  <code class="rounded bg-white px-1">&#123;&#123;2&#125;&#125;</code>, … (urutan angka harus berurutan).
                </p>
                <textarea
                  v-model="newTemplate.body"
                  rows="4"
                  class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                  placeholder="Halo {{1}}, diskon {{2}}% berlaku hingga {{3}}."
                />
              </div>
              <div v-if="templateVarCount > 0" class="space-y-3 rounded-lg border border-slate-200 bg-white p-3">
                <p class="text-xs font-semibold text-slate-600">Keterangan & contoh pengisian variabel (wajib untuk Meta)</p>
                <div class="hidden gap-2 text-xs font-medium text-slate-500 sm:grid sm:grid-cols-[7rem_1fr_1fr]">
                  <span>Token</span>
                  <span>Keterangan (internal)</span>
                  <span>Contoh nilai (ke Meta)</span>
                </div>
                <div
                  v-for="slot in templateVarSlots"
                  :key="slot.n"
                  class="grid gap-2 sm:grid-cols-[7rem_1fr_1fr]"
                >
                  <span class="self-center text-sm font-mono font-semibold text-[#128C7E]">{{ slot.token }}</span>
                  <input
                    v-model="slot.hint"
                    type="text"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    :placeholder="slot.defaultHint"
                  />
                  <input
                    v-model="slot.example"
                    type="text"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm"
                    :placeholder="'Contoh: ' + slot.defaultExample"
                  />
                </div>
                <p class="text-xs text-slate-500">Kolom tengah = keterangan (untuk tim). Kolom kanan = contoh nilai dikirim ke Meta saat review.</p>
              </div>
              <div
                v-if="templateBodyPreview"
                class="rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-800"
              >
                <p class="mb-1 text-xs font-semibold uppercase text-slate-500">Preview isi pesan</p>
                <p class="whitespace-pre-wrap">{{ templateBodyPreview }}</p>
              </div>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-[#128C7E] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0d6b5c] disabled:opacity-50"
                :disabled="creatingTemplate"
                @click="submitNewTemplate"
              >
                <i class="fa-solid fa-paper-plane" :class="{ 'fa-spin': creatingTemplate }" />
                Ajukan ke Meta
              </button>
              <p v-if="templateSubmitMessage" class="text-sm text-emerald-800">{{ templateSubmitMessage }}</p>
            </div>

            <ul v-if="allTemplates.length" class="mt-4 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-slate-100 bg-slate-50 p-3 text-xs">
              <li
                v-for="t in allTemplates"
                :key="t.name + t.language + t.status"
                class="flex flex-wrap items-center gap-2 text-slate-700"
              >
                <span class="font-medium">{{ t.name }}</span>
                <span class="text-slate-400">{{ t.language }}</span>
                <span
                  class="rounded px-1.5 py-0.5 font-semibold uppercase"
                  :class="statusClass(t.status)"
                >{{ t.status }}</span>
              </li>
            </ul>

            <div class="mt-4 space-y-3">
              <label class="block text-sm font-medium text-slate-700">Pilih template (approved)</label>
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
                <label class="block text-sm font-medium text-slate-700">Variabel saat kirim campaign</label>
                <p class="mt-1 text-xs text-slate-500">
                  Satu nilai per baris: baris 1 = <code class="rounded bg-slate-100 px-1">&#123;&#123;1&#125;&#125;</code>,
                  baris 2 = <code class="rounded bg-slate-100 px-1">&#123;&#123;2&#125;&#125;</code>, … (bisa nama member, kode promo, dll.)
                </p>
                <textarea
                  v-model="form.templateParamsText"
                  rows="4"
                  class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-2.5 font-mono text-sm"
                  placeholder="Baris 1 = nilai {{1}}, baris 2 = {{2}}, dst."
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
            :disabled="saving || !canSendNow"
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
import { computed, reactive, ref, watch } from 'vue'
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
  transactionFrom: '',
  transactionTo: '',
  member: {
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

const TEMPLATE_VAR_DEFAULTS = [
  { hint: 'Nama pelanggan', example: 'Budi' },
  { hint: 'Persen / nilai diskon', example: '20' },
  { hint: 'Batas berlaku promo', example: '31 Desember 2026' },
  { hint: 'Nama produk / outlet', example: 'Justus Kitchen' },
  { hint: 'Kode voucher', example: 'PROMO20' },
]

const newTemplate = reactive({
  name: '',
  category: 'MARKETING',
  language: 'id',
  body: '',
})

const templateVarSlots = ref([])
const previewLoading = ref(false)
const previewCount = ref(null)
const previewError = ref('')
const previewSample = ref([])
const templates = ref([])
const allTemplates = ref([])
const templatesLoading = ref(false)
const saving = ref(false)
const formError = ref('')
const showTemplateForm = ref(false)
const creatingTemplate = ref(false)
const templateSubmitMessage = ref('')

let previewAbortController = null

const hasRecipientSource = computed(() => {
  const hasManual = form.manualMemberIds.split(/[,\s]+/).some((s) => parseInt(s.trim(), 10) > 0)
  return form.sources.member || form.sources.omni || hasManual
})

const canSendNow = computed(() => {
  if (!hasRecipientSource.value) return false
  if (previewLoading.value) return false
  if (previewCount.value === null) return false
  return previewCount.value > 0
})

const templateVarCount = computed(() => {
  const matches = newTemplate.body.match(/\{\{\d+\}\}/g)
  if (!matches?.length) return 0
  const nums = matches.map((m) => parseInt(m.replace(/\D/g, ''), 10))
  return Math.max(...nums, 0)
})

const previewBadgeClass = computed(() => {
  if (previewLoading.value) return 'bg-slate-100 text-slate-600'
  if (previewError.value) return 'bg-red-50 text-red-800'
  if (previewCount.value !== null) return 'bg-emerald-50 text-emerald-900'
  return 'bg-slate-100 text-slate-600'
})

const templateBodyPreview = computed(() => {
  if (!newTemplate.body.trim()) return ''
  let text = newTemplate.body
  for (const slot of templateVarSlots.value) {
    const val = (slot.example || slot.defaultExample || '').trim()
    if (val) {
      text = text.split(`{{${slot.n}}}`).join(val)
    }
  }
  return text
})

watch(templateVarCount, (count) => {
  const prevByN = Object.fromEntries(templateVarSlots.value.map((s) => [s.n, s]))
  const slots = []
  for (let n = 1; n <= count; n++) {
    const prev = prevByN[n]
    const def = TEMPLATE_VAR_DEFAULTS[n - 1] ?? {
      hint: `Isi variabel ${n}`,
      example: `contoh_${n}`,
    }
    slots.push({
      n,
      token: `{{${n}}}`,
      hint: prev?.hint ?? def.hint,
      example: prev?.example ?? def.example,
      defaultHint: def.hint,
      defaultExample: def.example,
    })
  }
  templateVarSlots.value = slots
})

function statusClass(status) {
  const s = (status || '').toUpperCase()
  if (s === 'APPROVED') return 'bg-emerald-100 text-emerald-800'
  if (s === 'PENDING') return 'bg-amber-100 text-amber-800'
  if (s === 'REJECTED') return 'bg-red-100 text-red-800'
  return 'bg-slate-200 text-slate-700'
}

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
    allow_notification_only: form.member.allow_notification_only,
    mobile_verified_only: form.member.mobile_verified_only,
    min_total_spending: form.member.min_spending ? Number(form.member.min_spending) : null,
    member_levels: form.member.levels.length ? [...form.member.levels] : [],
    search: form.member.search || null,
  }

  if (form.transactionFrom || form.transactionTo) {
    const tx = {
      transaction_from: form.transactionFrom || null,
      transaction_to: form.transactionTo || null,
    }
    Object.assign(filters.member, tx)
    Object.assign(filters.omni_contact, tx)
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
  if (!hasRecipientSource.value) {
    previewCount.value = 0
    previewSample.value = []
    previewError.value = 'Centang minimal satu sumber penerima atau isi ID member manual.'
    return
  }

  if (previewAbortController) {
    previewAbortController.abort()
  }
  previewAbortController = new AbortController()

  previewLoading.value = true
  previewError.value = ''
  formError.value = ''
  try {
    const { data } = await axios.post(
      '/crm/wa-broadcast/preview-recipients',
      { filter_definition: buildFilterDefinition() },
      { signal: previewAbortController.signal, timeout: 90000 }
    )
    previewCount.value = data.count ?? 0
    previewSample.value = data.sample ?? []
  } catch (e) {
    if (e.code === 'ERR_CANCELED' || e.name === 'CanceledError') {
      return
    }
    const msg = e.response?.data?.message || e.message || 'Gagal menghitung penerima'
    previewError.value = msg
    previewCount.value = null
    previewSample.value = []
    formError.value = msg
  } finally {
    previewLoading.value = false
  }
}

/** Reset hasil hitung saat filter berubah (tanpa memanggil API). */
function resetPreviewOnFilterChange() {
  if (previewLoading.value) {
    return
  }
  previewCount.value = null
  previewSample.value = []
  previewError.value = ''
}

watch(
  () => [
    form.sources.member,
    form.sources.omni,
    form.transactionFrom,
    form.transactionTo,
    form.member.allow_notification_only,
    form.member.mobile_verified_only,
    form.member.min_spending,
    form.member.search,
    form.member.levels.join(','),
    form.omni.has_member,
    form.omni.search,
    form.manualMemberIds,
  ],
  resetPreviewOnFilterChange
)

async function loadTemplates() {
  templatesLoading.value = true
  formError.value = ''
  try {
    const { data } = await axios.get('/crm/wa-broadcast/templates')
    templates.value = data.templates ?? []
    allTemplates.value = data.all ?? data.templates ?? []
    if (!templates.value.length && !allTemplates.value.length) {
      formError.value = 'Belum ada template di Meta. Buat template di form di atas atau di WhatsApp Manager.'
    }
  } catch (e) {
    formError.value = e.response?.data?.message || 'Gagal memuat template dari Meta'
  } finally {
    templatesLoading.value = false
  }
}

async function submitNewTemplate() {
  creatingTemplate.value = true
  templateSubmitMessage.value = ''
  formError.value = ''
  try {
    const bodyExamples = templateVarSlots.value
      .map((s) => (s.example || '').trim())
      .filter(Boolean)
    const { data } = await axios.post('/crm/wa-broadcast/templates', {
      name: newTemplate.name,
      category: newTemplate.category,
      language: newTemplate.language,
      body: newTemplate.body,
      body_examples: bodyExamples,
    })
    templateSubmitMessage.value = data.message || 'Template diajukan.'
    if (data.template?.name) {
      form.templateName = data.template.name
      form.templateLanguage = data.template.language || 'id'
    }
    await loadTemplates()
  } catch (e) {
    formError.value = e.response?.data?.message || e.message
  } finally {
    creatingTemplate.value = false
  }
}

async function createCampaign(startNow) {
  saving.value = true
  formError.value = ''
  try {
    if (startNow && previewCount.value === null) {
      formError.value = 'Klik "Hitung penerima" dulu sebelum kirim sekarang.'
      return
    }
    if (startNow && previewCount.value === 0) {
      formError.value = 'Tidak ada penerima sesuai filter.'
      return
    }
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
