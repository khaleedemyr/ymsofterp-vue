<template>
  <AppLayout>
    <div class="mx-auto max-w-5xl space-y-6 p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-900">Otomasi Inbox</h1>
          <p class="mt-1 text-sm text-slate-600">
            Flow otomasi saat pesan masuk (WhatsApp). Editor visual drag-and-drop — dijalankan di server ERP lewat antrian.
          </p>
          <Link href="/crm/omnichannel-inbox" class="mt-2 inline-block text-sm font-medium text-emerald-700 hover:underline">
            ← Kembali ke inbox
          </Link>
        </div>
        <Link
          href="/crm/omnichannel-flows/create"
          class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
        >
          Buat flow baru
        </Link>
      </div>

      <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
        <strong>Catatan:</strong> pastikan <code class="rounded bg-amber-100 px-1">php artisan queue:work</code> berjalan di server agar flow dieksekusi setelah pesan masuk.
      </p>

      <div v-if="flows.length === 0" class="rounded-xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        Belum ada flow. Klik &quot;Buat flow baru&quot; untuk mulai.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="f in flows"
          :key="f.id"
          class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
        >
          <div>
            <p class="font-medium text-slate-900">
              {{ f.name }}
              <span
                class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                :class="f.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
              >
                {{ f.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
            </p>
            <p v-if="f.description" class="mt-0.5 text-xs text-slate-500">{{ f.description }}</p>
            <p class="mt-1 text-[11px] text-slate-400">
              Prioritas {{ f.priority }} · {{ f.step_count }} langkah
              <span v-if="f.channel"> · {{ f.channel }}</span>
              <span v-if="f.last_run"> · run terakhir: {{ f.last_run.status }}</span>
            </p>
          </div>
          <div class="flex gap-2">
            <Link
              :href="`/crm/omnichannel-flows/${f.id}/edit`"
              class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
            >
              Edit
            </Link>
            <button
              type="button"
              class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
              @click="destroyFlow(f.id)"
            >
              Hapus
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  flows: { type: Array, default: () => [] },
})

function destroyFlow(id) {
  if (!confirm('Hapus flow ini?')) return
  router.delete(`/crm/omnichannel-flows/${id}`)
}
</script>
