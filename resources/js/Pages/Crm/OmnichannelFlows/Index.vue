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

      <div v-if="flows.length === 0" class="rounded-xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
        Belum ada flow. Klik &quot;Buat flow baru&quot; untuk mulai.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="f in flows"
          :key="f.id"
          class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
          :class="{ 'opacity-60': !f.is_active }"
        >
          <div class="flex min-w-0 flex-1 items-start gap-3">
            <div
              v-if="f.created_by"
              class="flex shrink-0 flex-col items-center gap-1"
              :title="f.created_by.name"
            >
              <img
                v-if="f.created_by.avatar_url"
                :src="f.created_by.avatar_url"
                :alt="f.created_by.name"
                class="h-9 w-9 rounded-full border border-slate-200 object-cover"
              />
              <div
                v-else
                class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-xs font-semibold text-slate-600"
              >
                {{ creatorInitials(f.created_by.name) }}
              </div>
              <span class="max-w-[72px] truncate text-[10px] text-slate-500">{{ f.created_by.name }}</span>
            </div>

            <div class="min-w-0">
              <p class="font-medium text-slate-900">{{ f.name }}</p>
              <p v-if="f.description" class="mt-0.5 text-xs text-slate-500">{{ f.description }}</p>
              <p class="mt-1 text-[11px] text-slate-400">
                Prioritas {{ f.priority }} · {{ f.step_count }} langkah
                <span v-if="f.channel"> · {{ f.channel }}</span>
                <span v-if="f.last_run"> · run terakhir: {{ f.last_run.status }}</span>
              </p>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <label class="flex cursor-pointer items-center gap-2" :title="f.is_active ? 'Nonaktifkan' : 'Aktifkan'">
              <span class="text-xs font-medium" :class="f.is_active ? 'text-emerald-700' : 'text-slate-500'">
                {{ f.is_active ? 'Aktif' : 'Nonaktif' }}
              </span>
              <button
                type="button"
                role="switch"
                :aria-checked="f.is_active"
                class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1"
                :class="f.is_active ? 'bg-emerald-500' : 'bg-slate-300'"
                :disabled="togglingId === f.id"
                @click="toggleActive(f)"
              >
                <span
                  class="pointer-events-none inline-block h-5 w-5 translate-y-0.5 rounded-full bg-white shadow ring-0 transition-transform"
                  :class="f.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                />
              </button>
            </label>

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
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  flows: { type: Array, default: () => [] },
})

const togglingId = ref(null)

function creatorInitials(name) {
  if (!name) return '?'
  const parts = String(name).trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

function toggleActive(flow) {
  togglingId.value = flow.id
  router.patch(`/crm/omnichannel-flows/${flow.id}/toggle-active`, {}, {
    preserveScroll: true,
    onFinish: () => { togglingId.value = null },
  })
}

function destroyFlow(id) {
  if (!confirm('Hapus flow ini?')) return
  router.delete(`/crm/omnichannel-flows/${id}`)
}
</script>
