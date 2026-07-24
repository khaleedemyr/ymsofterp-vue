<script setup>
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  isNight: {
    type: Boolean,
    default: false,
  },
  compact: {
    type: Boolean,
    default: true,
  },
});

const loading = ref(true);
const items = ref([]);
const count = ref(0);

const visible = computed(() => loading.value || count.value > 0);

function formatDt(v) {
  if (!v) return '—';
  try {
    return new Date(v).toLocaleString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });
  } catch {
    return String(v);
  }
}

function severityClass(sev) {
  const s = String(sev || '').toLowerCase();
  if (s === 'critical' || s === 'severe' || s === 'negative') {
    return props.isNight
      ? 'bg-rose-900/40 text-rose-200 border-rose-700'
      : 'bg-rose-50 text-rose-800 border-rose-200';
  }
  if (s === 'major' || s === 'mild_negative') {
    return props.isNight
      ? 'bg-amber-900/40 text-amber-200 border-amber-700'
      : 'bg-amber-50 text-amber-900 border-amber-200';
  }
  return props.isNight
    ? 'bg-slate-700/50 text-slate-300 border-slate-600'
    : 'bg-slate-50 text-slate-600 border-slate-200';
}

function statusBadgeClass(key) {
  if (key === 'awaiting_approval') {
    return props.isNight
      ? 'bg-amber-900/50 text-amber-200'
      : 'bg-amber-100 text-amber-800';
  }
  return props.isNight
    ? 'bg-sky-900/50 text-sky-200'
    : 'bg-sky-100 text-sky-800';
}

async function load() {
  loading.value = true;
  try {
    const res = await fetch(route('home.cvcc-regional-capa-pending'), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const json = await res.json();
    if (json.success) {
      items.value = Array.isArray(json.items) ? json.items : [];
      count.value = Number(json.count ?? items.value.length) || 0;
    } else {
      items.value = [];
      count.value = 0;
    }
  } catch {
    items.value = [];
    count.value = 0;
  } finally {
    loading.value = false;
  }
}

function openCase(caseId) {
  const base = route('customer-voice-command-center.index');
  router.visit(`${base}?show_all=1&open_case=${caseId}`);
}

function openAll() {
  const base = route('customer-voice-command-center.index');
  router.visit(`${base}?show_all=1`);
}

onMounted(load);
</script>

<template>
  <div v-if="visible" class="mt-3">
    <div
      class="rounded-xl border shadow-sm p-4 transition-all duration-300"
      :class="isNight ? 'bg-slate-700/40 border-sky-700/50 text-white' : 'bg-white border-sky-200 text-slate-800'"
    >
      <div class="flex items-center justify-between gap-2 mb-2">
        <div class="flex items-center gap-2 min-w-0">
          <i class="fa-solid fa-clipboard-list text-xs shrink-0" :class="isNight ? 'text-sky-300' : 'text-sky-600'" />
          <span
            class="text-xs font-semibold tracking-wide uppercase truncate"
            :class="isNight ? 'text-sky-200/90' : 'text-slate-500'"
          >
            CVCC — CAPA Regional
          </span>
        </div>
        <div
          v-if="!loading && count > 0"
          class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold text-white"
          :class="isNight ? 'bg-sky-600' : 'bg-sky-600'"
        >
          {{ count }}
        </div>
      </div>

      <div v-if="loading" class="text-sm py-1" :class="isNight ? 'text-slate-300' : 'text-slate-500'">
        Memuat kasus CVCC…
      </div>

      <template v-else-if="count > 0">
        <p class="text-xs mb-3 leading-relaxed" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
          Ada kasus Customer Voice yang di-tag ke Anda dan masih perlu diisi CAPA
          (hilang setelah CAPA diisi &amp; approved).
        </p>

        <div class="space-y-2">
          <button
            v-for="item in items.slice(0, 4)"
            :key="'cvcc-capa-' + item.id"
            type="button"
            class="w-full text-left rounded-lg border px-3 py-2.5 transition hover:scale-[1.01]"
            :class="severityClass(item.severity)"
            @click="openCase(item.id)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold truncate">
                  Case #{{ item.id }}
                  <span v-if="item.nama_outlet" class="font-normal opacity-80">— {{ item.nama_outlet }}</span>
                </div>
                <div class="mt-0.5 line-clamp-2 text-xs opacity-90">
                  {{ item.summary_id || 'Tanpa ringkasan' }}
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                  <span
                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                    :class="statusBadgeClass(item.capa_status)"
                  >
                    {{ item.capa_status_label }}
                  </span>
                  <span class="text-[10px] opacity-70">{{ formatDt(item.event_at) }}</span>
                </div>
              </div>
              <i class="fa-solid fa-chevron-right text-[10px] mt-1 opacity-60 shrink-0" />
            </div>
          </button>
        </div>

        <div v-if="items.length > 4" class="mt-2 text-center">
          <button
            type="button"
            class="text-xs font-semibold"
            :class="isNight ? 'text-sky-300 hover:text-sky-200' : 'text-sky-700 hover:text-sky-900'"
            @click="openAll"
          >
            Lihat {{ items.length - 4 }} lainnya di CVCC…
          </button>
        </div>
      </template>
    </div>
  </div>
</template>
