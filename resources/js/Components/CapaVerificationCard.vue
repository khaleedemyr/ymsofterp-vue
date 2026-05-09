<template>
  <div>
    <div v-if="visible" class="mb-4 flex-shrink-0">
      <div
        class="rounded-2xl border p-4 shadow-2xl backdrop-blur-md transition-all duration-500 animate-fade-in hover:shadow-3xl"
        :class="isNight ? 'border-violet-600/40 bg-slate-800/90' : 'border-violet-200/80 bg-white/90'"
      >
        <div class="mb-3 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="h-3 w-3 animate-pulse rounded-full bg-violet-500" />
            <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
              <i class="fa fa-clipboard-check mr-2 text-violet-500" />
              Verifikasi CAPA
            </h3>
          </div>
          <div class="rounded-full bg-violet-600 px-2 py-1 text-xs font-bold text-white">
            {{ count }}
          </div>
        </div>
        <p class="mb-3 text-xs leading-relaxed" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
          Anda ditunjuk sebagai <span class="font-semibold text-violet-600 dark:text-violet-300">verifikator</span> pada form CAPA (bagian G — hasil belum diisi). Buka kasus untuk mengisi hasil verifikasi.
        </p>

        <div v-if="loading" class="py-4 text-center">
          <div class="inline-block h-6 w-6 animate-spin rounded-full border-b-2 border-violet-500" />
          <p class="mt-2 text-sm" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat…</p>
        </div>

        <div v-else class="space-y-2">
          <button
            v-for="item in items.slice(0, 3)"
            :key="'capa-ver-' + item.id"
            type="button"
            class="w-full rounded-lg p-3 text-left transition-all duration-200 hover:scale-[1.01]"
            :class="isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-violet-50 hover:bg-violet-100'"
            @click="openCase(item.id)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold" :class="isNight ? 'text-white' : 'text-slate-800'">
                  Case #{{ item.id }}
                  <span v-if="item.nama_outlet" class="font-normal text-slate-500">— {{ item.nama_outlet }}</span>
                </div>
                <div class="mt-1 line-clamp-2 text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                  {{ item.summary_id || '—' }}
                </div>
                <div class="mt-1 flex flex-wrap gap-2 text-[11px]" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                  <span class="rounded bg-white/10 px-1.5 py-0.5">{{ item.status || '—' }}</span>
                  <span class="rounded bg-white/10 px-1.5 py-0.5">{{ item.severity || '—' }}</span>
                  <span>{{ formatDt(item.event_at) }}</span>
                </div>
              </div>
              <span class="shrink-0 text-xs font-medium text-violet-600 dark:text-violet-300">
                <i class="fa fa-arrow-right" />
              </span>
            </div>
          </button>

          <div v-if="items.length > 3" class="pt-2 text-center">
            <button
              type="button"
              class="text-sm font-medium text-violet-600 hover:text-violet-800 dark:text-violet-300 dark:hover:text-violet-100"
              @click="openCommandCenter"
            >
              Lihat {{ items.length - 3 }} lainnya di Customer Voice…
            </button>
          </div>

          <div class="border-t border-violet-200/50 pt-2 dark:border-violet-900/40">
            <button
              type="button"
              class="text-xs font-medium text-violet-700 hover:underline dark:text-violet-300"
              @click="openCommandCenter"
            >
              Buka Customer Voice Command Center
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'

const props = defineProps({
  isNight: { type: Boolean, default: false },
})

const loading = ref(true)
const items = ref([])
const count = ref(0)

const visible = computed(() => loading.value || count.value > 0)

function formatDt(v) {
  if (!v) return '—'
  try {
    return new Date(v).toLocaleString('id-ID')
  } catch {
    return String(v)
  }
}

async function load() {
  loading.value = true
  try {
    const res = await fetch(route('customer-voice-command-center.pending-capa-verifications'), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    const data = await res.json()
    if (data.success) {
      items.value = data.items || []
      count.value = data.count ?? items.value.length
    } else {
      items.value = []
      count.value = 0
    }
  } catch {
    items.value = []
    count.value = 0
  } finally {
    loading.value = false
  }
}

function openCase(id) {
  const base = route('customer-voice-command-center.index')
  router.visit(`${base}?show_all=1&open_case=${encodeURIComponent(id)}`)
}

function openCommandCenter() {
  const base = route('customer-voice-command-center.index')
  router.visit(`${base}?show_all=1`)
}

onMounted(() => {
  load()
})
</script>
