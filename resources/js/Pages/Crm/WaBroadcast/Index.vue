<template>
  <AppLayout>
    <div class="mx-auto max-w-7xl space-y-6 p-4 md:p-6">
      <!-- Header -->
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-4">
          <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#25D366]/10 text-[#128C7E]">
            <i class="fa-brands fa-whatsapp text-2xl" />
          </div>
          <div>
            <h1 class="text-2xl font-bold text-slate-900">Broadcast WhatsApp</h1>
            <p class="mt-1 max-w-xl text-sm text-slate-600">
              Kirim pesan massal ke member & kontak omnichannel menggunakan template resmi Meta.
            </p>
          </div>
        </div>
        <Link
          href="/crm/wa-broadcast/create"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#128C7E] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0d6b5c]"
        >
          <i class="fa-solid fa-plus" />
          Buat campaign
        </Link>
      </div>

      <!-- Stats -->
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Kuota hari ini</p>
          <p class="mt-2 text-2xl font-bold text-slate-900">{{ dailyCap.toLocaleString('id-ID') }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Terpakai</p>
          <p class="mt-2 text-2xl font-bold text-amber-700">{{ dailySent.toLocaleString('id-ID') }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 shadow-sm">
          <p class="text-xs font-medium uppercase tracking-wide text-emerald-800">Sisa kuota</p>
          <p class="mt-2 text-2xl font-bold text-emerald-900">{{ dailyRemaining.toLocaleString('id-ID') }}</p>
        </div>
      </div>

      <!-- List -->
      <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <h2 class="text-base font-semibold text-slate-900">Daftar campaign</h2>
          <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
            {{ campaigns.length }} campaign
          </span>
        </div>

        <div v-if="campaigns.length === 0" class="px-6 py-16 text-center">
          <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-400">
            <i class="fa-brands fa-whatsapp text-3xl" />
          </div>
          <p class="mt-4 text-base font-medium text-slate-800">Belum ada campaign</p>
          <p class="mt-1 text-sm text-slate-500">Buat campaign pertama untuk mulai broadcast ke member & kontak WA.</p>
          <Link
            href="/crm/wa-broadcast/create"
            class="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#128C7E] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#0d6b5c]"
          >
            <i class="fa-solid fa-plus" />
            Buat campaign
          </Link>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-5 py-3">Campaign</th>
                <th class="px-5 py-3">Pesan</th>
                <th class="px-5 py-3">Status</th>
                <th class="px-5 py-3">Progress</th>
                <th class="px-5 py-3">Dibuat</th>
                <th class="px-5 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="c in campaigns" :key="c.id" class="hover:bg-slate-50/80">
                <td class="px-5 py-4">
                  <p class="font-semibold text-slate-900">{{ c.name }}</p>
                  <p class="text-xs text-slate-500">#{{ c.id }}</p>
                </td>
                <td class="px-5 py-4">
                  <p v-if="c.message_type === 'template'" class="text-slate-700">
                    <i class="fa-solid fa-file-lines mr-1 text-slate-400" />
                    {{ c.template_name || '—' }}
                    <span v-if="c.template_language" class="text-slate-400">({{ c.template_language }})</span>
                  </p>
                  <p v-else class="text-slate-600 italic">Teks sesi 24 jam</p>
                </td>
                <td class="px-5 py-4">
                  <span
                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="statusClass(c.status)"
                  >
                    {{ statusLabel(c.status) }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <div class="min-w-[140px]">
                    <div class="flex justify-between text-xs text-slate-600">
                      <span>{{ c.recipient_count_sent }} terkirim</span>
                      <span>{{ totalRecipients(c) }}</span>
                    </div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                      <div
                        class="h-full rounded-full bg-[#128C7E] transition-all"
                        :style="{ width: progressPct(c) + '%' }"
                      />
                    </div>
                    <p v-if="c.recipient_count_failed > 0" class="mt-1 text-xs text-red-600">
                      {{ c.recipient_count_failed }} gagal
                    </p>
                  </div>
                </td>
                <td class="px-5 py-4 text-slate-600 whitespace-nowrap">
                  {{ formatDate(c.created_at) }}
                </td>
                <td class="px-5 py-4 text-right whitespace-nowrap">
                  <button
                    v-if="c.status === 'draft' || c.status === 'paused'"
                    type="button"
                    class="rounded-lg bg-[#128C7E] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#0d6b5c] disabled:opacity-50"
                    :disabled="actionLoading === c.id"
                    @click="startCampaign(c.id)"
                  >
                    Jalankan
                  </button>
                  <button
                    v-if="c.status === 'running' || c.status === 'building'"
                    type="button"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 disabled:opacity-50"
                    :disabled="actionLoading === c.id"
                    @click="pauseCampaign(c.id)"
                  >
                    Jeda
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <p class="text-center text-xs text-slate-400">
        Template pesan dibuat & disetujui di
        <a
          :href="metaTemplatesUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="font-medium text-[#128C7E] hover:underline"
        >Meta WhatsApp Manager</a>,
        lalu dimuat di halaman buat campaign.
      </p>
    </div>
  </AppLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout.vue'

const metaTemplatesUrl = 'https://business.facebook.com/latest/whatsapp_manager/message_templates'

defineProps({
  campaigns: { type: Array, default: () => [] },
  dailyCap: { type: Number, default: 100000 },
  dailySent: { type: Number, default: 0 },
  dailyRemaining: { type: Number, default: 100000 },
})

const actionLoading = ref(null)
const page = usePage()

onMounted(() => {
  if (page.props.flash?.success) {
    Swal.fire({ icon: 'success', title: page.props.flash.success, timer: 2500, showConfirmButton: false })
  }
})

function totalRecipients(c) {
  return c.recipient_count_total || c.recipient_count_estimated || 0
}

function progressPct(c) {
  const total = totalRecipients(c)
  if (!total) return 0
  return Math.min(100, Math.round((c.recipient_count_sent / total) * 100))
}

function statusLabel(status) {
  const map = {
    draft: 'Draft',
    building: 'Menyiapkan',
    scheduled: 'Terjadwal',
    running: 'Berjalan',
    paused: 'Dijeda',
    completed: 'Selesai',
    failed: 'Gagal',
    cancelled: 'Dibatalkan',
  }
  return map[status] || status
}

function statusClass(status) {
  if (status === 'running' || status === 'building') return 'bg-emerald-100 text-emerald-800'
  if (status === 'completed') return 'bg-slate-100 text-slate-700'
  if (status === 'failed') return 'bg-red-100 text-red-800'
  if (status === 'paused') return 'bg-amber-100 text-amber-800'
  if (status === 'scheduled') return 'bg-blue-100 text-blue-800'
  return 'bg-slate-100 text-slate-600'
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function startCampaign(id) {
  actionLoading.value = id
  try {
    await axios.post(`/crm/wa-broadcast/campaigns/${id}/start`)
    router.reload()
  } finally {
    actionLoading.value = null
  }
}

async function pauseCampaign(id) {
  actionLoading.value = id
  try {
    await axios.post(`/crm/wa-broadcast/campaigns/${id}/pause`)
    router.reload()
  } finally {
    actionLoading.value = null
  }
}
</script>
