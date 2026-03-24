<script setup>
import { computed, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
  outletCodes: {
    type: Array,
    default: () => [],
  },
  outletOptions: {
    type: Array,
    default: () => [],
  },
  selectedOutlet: {
    type: String,
    default: '',
  },
  sections: {
    type: Array,
    default: () => [],
  },
  tablesBySection: {
    type: Object,
    default: () => ({}),
  },
  accessoriesBySection: {
    type: Object,
    default: () => ({}),
  },
});

const canvasWidth = 1200;
const canvasHeight = 900;

const activeSectionId = ref(props.sections[0]?.source_section_id ?? null);

watch(
  () => props.sections,
  (sections) => {
    if (!sections?.length) {
      activeSectionId.value = null;
      return;
    }

    const exists = sections.some((section) => String(section.source_section_id) === String(activeSectionId.value));
    if (!exists) {
      activeSectionId.value = sections[0].source_section_id;
    }
  },
  { immediate: true }
);

const currentTables = computed(() => {
  if (activeSectionId.value == null) return [];
  return props.tablesBySection?.[activeSectionId.value] || props.tablesBySection?.[String(activeSectionId.value)] || [];
});

const currentAccessories = computed(() => {
  if (activeSectionId.value == null) return [];
  return props.accessoriesBySection?.[activeSectionId.value] || props.accessoriesBySection?.[String(activeSectionId.value)] || [];
});

const tableStyle = (table) => ({
  position: 'absolute',
  left: `${Number(table.x || 0)}px`,
  top: `${Number(table.y || 0)}px`,
  display: 'flex',
  flexDirection: 'column',
  alignItems: 'center',
  gap: '6px',
});

const accessoryStyle = (accessory) => ({
  position: 'absolute',
  left: `${Number(accessory.x || 0)}px`,
  top: `${Number(accessory.y || 0)}px`,
});

const renderTableSvg = (table) => {
  const warna = table.warna || '#2563eb';
  const tipe = table.tipe || 'biasa';
  const bentuk = table.bentuk || 'round';
  const orientasi = table.orientasi || 'horizontal';
  const jumlahKursi = Math.max(1, Number(table.jumlah_kursi || 4));

  if (tipe === 'takeaway') {
    return `
      <svg width="80" height="80" viewBox="0 0 80 80">
        <rect x="20" y="30" width="40" height="32" rx="8" fill="#fbbf24" stroke="#b45309" stroke-width="3" />
        <rect x="28" y="38" width="24" height="16" rx="4" fill="#fde68a" />
        <rect x="32" y="24" width="16" height="12" rx="4" fill="#fbbf24" stroke="#b45309" stroke-width="2" />
        <rect x="36" y="18" width="8" height="8" rx="2" fill="#fde68a" />
      </svg>
    `;
  }

  if (tipe === 'ojol') {
    return `
      <svg width="80" height="80" viewBox="0 0 80 80">
        <ellipse cx="40" cy="54" rx="22" ry="10" fill="#2563eb" />
        <rect x="28" y="44" width="24" height="12" rx="4" fill="#fbbf24" stroke="#b45309" stroke-width="2" />
        <circle cx="32" cy="62" r="6" fill="#222" />
        <circle cx="48" cy="62" r="6" fill="#222" />
        <ellipse cx="40" cy="40" rx="14" ry="10" fill="#a3e635" stroke="#2563eb" stroke-width="2" />
      </svg>
    `;
  }

  if (bentuk === 'round') {
    let chairs = '';
    for (let i = 0; i < jumlahKursi; i += 1) {
      const angle = (2 * Math.PI * i) / jumlahKursi;
      const x = 40 + Math.cos(angle) * 32;
      const y = 40 + Math.sin(angle) * 32;
      chairs += `<circle cx="${x}" cy="${y}" r="10" fill="#fbbf24" stroke="#b45309" stroke-width="1.5" />`;
    }

    return `
      <svg width="80" height="80" viewBox="0 0 80 80">
        ${chairs}
        <circle cx="40" cy="40" r="28" fill="${warna}" stroke="#0f172a" stroke-width="2" />
      </svg>
    `;
  }

  const kursiA = Math.ceil(jumlahKursi / 2);
  const kursiB = Math.floor(jumlahKursi / 2);
  const kursiMax = Math.max(kursiA, kursiB);

  let mejaW = 0;
  let mejaH = 0;
  let svgW = 0;
  let svgH = 0;
  let mx = 0;
  let my = 0;
  let chairs = '';

  if (orientasi === 'vertical') {
    mejaW = 32;
    mejaH = 32 + kursiMax * 16;
    svgW = mejaW + 60;
    svgH = mejaH + 40;
    mx = svgW / 2 - mejaW / 2;
    my = svgH / 2 - mejaH / 2;

    for (let i = 0; i < kursiA; i += 1) {
      const y = my + (mejaH / (kursiA + 1)) * (i + 1);
      chairs += `<rect x="${mx - 18}" y="${y - 8}" width="16" height="16" rx="4" fill="#fbbf24" stroke="#b45309" stroke-width="1.5" />`;
    }

    for (let i = 0; i < kursiB; i += 1) {
      const y = my + (mejaH / (kursiB + 1)) * (i + 1);
      chairs += `<rect x="${mx + mejaW + 2}" y="${y - 8}" width="16" height="16" rx="4" fill="#fbbf24" stroke="#b45309" stroke-width="1.5" />`;
    }
  } else {
    mejaW = 32 + kursiMax * 16;
    mejaH = 32;
    svgW = mejaW + 40;
    svgH = mejaH + 60;
    mx = svgW / 2 - mejaW / 2;
    my = svgH / 2 - mejaH / 2;

    for (let i = 0; i < kursiA; i += 1) {
      const x = mx + (mejaW / (kursiA + 1)) * (i + 1);
      chairs += `<rect x="${x - 8}" y="${my - 18}" width="16" height="16" rx="4" fill="#fbbf24" stroke="#b45309" stroke-width="1.5" />`;
    }

    for (let i = 0; i < kursiB; i += 1) {
      const x = mx + (mejaW / (kursiB + 1)) * (i + 1);
      chairs += `<rect x="${x - 8}" y="${my + mejaH + 2}" width="16" height="16" rx="4" fill="#fbbf24" stroke="#b45309" stroke-width="1.5" />`;
    }
  }

  return `
    <svg width="${svgW}" height="${svgH}" viewBox="0 0 ${svgW} ${svgH}">
      ${chairs}
      <rect x="${mx}" y="${my}" width="${mejaW}" height="${mejaH}" rx="8" fill="${warna}" stroke="#0f172a" stroke-width="2" />
    </svg>
  `;
};

const renderAccessorySvg = (accessory) => {
  const type = accessory.type;
  const panjang = Number(accessory.panjang || 80);
  const orientasi = accessory.orientasi || 'horizontal';

  if (type === 'divider') {
    if (orientasi === 'horizontal') {
      return `<svg width="${panjang}" height="16" viewBox="0 0 ${panjang} 16"><rect x="4" y="6" width="${panjang - 8}" height="4" rx="2" fill="#64748b" /><rect x="2" y="4" width="6" height="8" rx="2" fill="#94a3b8" /><rect x="${panjang - 8}" y="4" width="6" height="8" rx="2" fill="#94a3b8" /></svg>`;
    }
    return `<svg width="16" height="${panjang}" viewBox="0 0 16 ${panjang}"><rect x="6" y="4" width="4" height="${panjang - 8}" rx="2" fill="#64748b" /><rect x="4" y="2" width="8" height="6" rx="2" fill="#94a3b8" /><rect x="4" y="${panjang - 8}" width="8" height="6" rx="2" fill="#94a3b8" /></svg>`;
  }

  if (type === 'lemari') {
    if (orientasi === 'horizontal') {
      return `<svg width="${panjang}" height="40" viewBox="0 0 ${panjang} 40"><rect x="2" y="4" width="${panjang - 4}" height="32" rx="4" fill="#f59e42" stroke="#b45309" stroke-width="2" /><rect x="6" y="8" width="24" height="24" fill="#fde68a" /></svg>`;
    }
    return `<svg width="40" height="${panjang}" viewBox="0 0 40 ${panjang}"><rect x="4" y="2" width="32" height="${panjang - 4}" rx="4" fill="#f59e42" stroke="#b45309" stroke-width="2" /><rect x="8" y="6" width="24" height="24" fill="#fde68a" /></svg>`;
  }

  if (type === 'pot') {
    return '<svg width="28" height="36" viewBox="0 0 28 36"><ellipse cx="14" cy="10" rx="12" ry="6" fill="#a3e635" /><rect x="4" y="10" width="20" height="18" rx="6" fill="#fbbf24" /><ellipse cx="14" cy="28" rx="10" ry="4" fill="#fbbf24" /></svg>';
  }

  if (type === 'pos') {
    return '<svg width="90" height="90" viewBox="0 0 90 90"><rect x="12" y="42" width="66" height="36" rx="10" fill="#2563eb" stroke="#222" stroke-width="4" /><rect x="28" y="18" width="34" height="22" rx="5" fill="#fff" stroke="#2563eb" stroke-width="3" /><rect x="22" y="68" width="16" height="10" rx="3" fill="#fbbf24" /><rect x="52" y="68" width="16" height="10" rx="3" fill="#fbbf24" /></svg>';
  }

  if (type === 'kasir') {
    return '<svg width="90" height="90" viewBox="0 0 90 90"><rect x="12" y="62" width="66" height="16" rx="6" fill="#b6d0ff" stroke="#2563eb" stroke-width="4" /><circle cx="45" cy="42" r="18" fill="#fbbf24" stroke="#b45309" stroke-width="3" /><rect x="32" y="72" width="26" height="12" rx="4" fill="#2563eb" /></svg>';
  }

  return '';
};
</script>

<template>
  <Head title="POS Design Layout Viewer" />

  <AppLayout>
    <div class="min-h-screen bg-slate-100 px-4 py-8">
      <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-2xl font-bold text-slate-800">POS Design Layout Viewer</h1>
            <p class="text-sm text-slate-500">Preview layout meja dari snapshot sinkronisasi YMSoftPOS</p>
          </div>
          <a
            href="/admin/pos-design-sync-monitor"
            class="inline-flex items-center rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
          >
            Kembali ke Monitor
          </a>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <form method="get" action="/admin/pos-design-sync-layout" class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <select
              name="kode_outlet"
              :value="selectedOutlet"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outletOptions" :key="outlet.code" :value="outlet.code">{{ outlet.label }}</option>
            </select>
            <button
              type="submit"
              class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            >
              Tampilkan Layout
            </button>
            <a
              href="/admin/pos-design-sync-layout"
              class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-100"
            >
              Reset
            </a>
          </form>
        </div>

        <div v-if="!selectedOutlet" class="rounded-xl border border-slate-200 bg-white p-6 text-slate-500 shadow-sm">
          Pilih outlet terlebih dahulu untuk melihat layout.
        </div>

        <div v-else class="space-y-4">
          <div class="flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <button
              v-for="section in sections"
              :key="section.source_section_id"
              type="button"
              class="rounded-lg px-4 py-2 text-sm font-semibold transition"
              :class="String(activeSectionId) === String(section.source_section_id)
                ? 'bg-blue-600 text-white shadow'
                : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
              @click="activeSectionId = section.source_section_id"
            >
              {{ section.nama }}
            </button>
            <span v-if="!sections.length" class="px-2 py-2 text-sm text-slate-500">Belum ada section untuk outlet ini.</span>
          </div>

          <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div
              class="relative rounded-lg border border-dashed border-slate-300"
              :style="{
                width: `${canvasWidth}px`,
                height: `${canvasHeight}px`,
                backgroundImage: 'linear-gradient(to right, rgba(148,163,184,0.16) 1px, transparent 1px), linear-gradient(to bottom, rgba(148,163,184,0.16) 1px, transparent 1px)',
                backgroundSize: '40px 40px',
                backgroundColor: '#f8fafc',
              }"
            >
              <div
                v-for="acc in currentAccessories"
                :key="`acc-${acc.source_accessory_id}`"
                :style="accessoryStyle(acc)"
                class="pointer-events-none"
              >
                <div v-html="renderAccessorySvg(acc)" />
              </div>

              <div
                v-for="table in currentTables"
                :key="`table-${table.source_table_id}`"
                :style="tableStyle(table)"
                class="pointer-events-none"
              >
                <div v-html="renderTableSvg(table)" />
                <div class="rounded-md bg-white/95 px-2 py-1 text-xs font-bold text-slate-800 shadow-sm ring-1 ring-slate-200">
                  {{ table.nama || '-' }}
                </div>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <div class="text-xs uppercase tracking-wide text-slate-500">Outlet</div>
              <div class="mt-1 text-lg font-bold text-slate-800">{{ selectedOutlet }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <div class="text-xs uppercase tracking-wide text-slate-500">Total Meja di Section</div>
              <div class="mt-1 text-lg font-bold text-slate-800">{{ currentTables.length }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
              <div class="text-xs uppercase tracking-wide text-slate-500">Total Aksesoris di Section</div>
              <div class="mt-1 text-lg font-bold text-slate-800">{{ currentAccessories.length }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
