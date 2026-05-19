<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-[120] flex items-center justify-center bg-black/40 px-2"
      @click.self="emit('close')"
    >
      <div class="relative z-[121] bg-white rounded-xl shadow-2xl w-full max-w-6xl max-h-[92vh] flex flex-col">
        <div class="flex items-start justify-between gap-4 p-5 border-b border-gray-100">
          <div>
            <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-tags text-amber-500"></i>
              Update harga item (non-POS)
            </h2>
            <p class="text-sm text-gray-500 mt-1">
              Item dari kategori <code class="bg-gray-100 px-1 rounded">is_asset=0</code> dan
              <code class="bg-gray-100 px-1 rounded">show_pos=0</code>. Mengatur harga default
              (<strong>scope All</strong>) per satuan <strong>besar (large)</strong>.
            </p>
            <p class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded mt-2 px-2 py-1 inline-block">
              <strong>Auto:</strong> Food Good Receive terakhir + 12% (konversi ke satuan large sesuai GR). <strong>Manual:</strong> input bebas.
            </p>
            <p class="text-xs text-gray-600 mt-2 max-w-3xl">
              <strong>Export template</strong> mengunduh semua item yang cocok filter pencarian (kosong = semua). Kolom
              <code class="bg-gray-100 px-1 rounded">category_id</code> /
              <code class="bg-gray-100 px-1 rounded">category_name</code> untuk referensi; saat import, jika diisi harus cocok dengan item (mencegah salah baris).
              Edit <code class="bg-gray-100 px-1 rounded">pricing_mode</code> (<code>manual</code> / <code>auto</code>) dan
              <code class="bg-gray-100 px-1 rounded">price</code> (wajib &gt; 0 jika manual), lalu <strong>Import Excel</strong>.
            </p>
          </div>
          <button type="button" class="text-gray-400 hover:text-red-500 text-2xl leading-none" @click="emit('close')" aria-label="Tutup">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>

        <div class="p-4 border-b border-gray-100 flex flex-wrap gap-3 items-end">
          <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-gray-600 mb-1">Cari nama / SKU</label>
            <input
              v-model="search"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
              placeholder="Ketik lalu Enter atau tombol Muat"
              @keydown.enter.prevent="load(1)"
            />
          </div>
          <button
            type="button"
            class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg text-sm font-semibold"
            :disabled="loading"
            @click="load(1)"
          >
            <i class="fa-solid fa-rotate mr-1" :class="{ 'fa-spin': loading }"></i>
            Muat
          </button>
          <button
            type="button"
            class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-semibold"
            :disabled="loading || exporting"
            @click="exportTemplate"
          >
            <i class="fa-solid fa-file-export mr-1" :class="{ 'fa-spin': exporting }"></i>
            Export template
          </button>
          <button
            type="button"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold"
            :disabled="importing"
            @click="importFileInput?.click()"
          >
            <i class="fa-solid fa-file-import mr-1" :class="{ 'fa-spin': importing }"></i>
            Import Excel
          </button>
          <input
            ref="importFileInput"
            type="file"
            class="hidden"
            accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
            @change="onImportFile"
          />
        </div>

        <div class="flex-1 overflow-auto px-4 pb-2">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 sticky top-0 z-10">
              <tr class="text-left text-xs uppercase text-gray-500">
                <th class="px-3 py-2">Kategori</th>
                <th class="px-3 py-2">Item</th>
                <th class="px-3 py-2">SKU</th>
                <th class="px-3 py-2">Sat. large</th>
                <th class="px-3 py-2">Mode</th>
                <th class="px-3 py-2">Harga</th>
                <th class="px-3 py-2">Acuan GR</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="!loading && rows.length === 0">
                <td colspan="7" class="px-3 py-8 text-center text-gray-400">Belum ada data. Klik Muat.</td>
              </tr>
              <tr v-for="r in rows" :key="r.item_id" class="hover:bg-gray-50/80">
                <td class="px-3 py-2 text-gray-700">
                  <div class="font-medium text-gray-900">{{ r.category_name || '—' }}</div>
                  <div class="text-xs text-gray-400 font-mono">#{{ r.category_id }}</div>
                </td>
                <td class="px-3 py-2 font-medium text-gray-900">{{ r.name }}</td>
                <td class="px-3 py-2 text-gray-600">{{ r.sku }}</td>
                <td class="px-3 py-2 text-gray-600">{{ r.large_unit || '—' }}</td>
                <td class="px-3 py-2">
                  <select
                    v-model="r.pricing_mode"
                    class="border border-gray-300 rounded px-2 py-1 text-sm"
                    @change="onModeChange(r)"
                  >
                    <option value="manual">Manual</option>
                    <option value="auto">Auto (GR+12%)</option>
                  </select>
                </td>
                <td class="px-3 py-2">
                  <input
                    v-model.number="r.price"
                    type="number"
                    min="0"
                    step="1"
                    class="border border-gray-300 rounded px-2 py-1 w-32 text-sm"
                    :disabled="r.pricing_mode === 'auto'"
                    :placeholder="r.pricing_mode === 'auto' ? '—' : '0'"
                  />
                  <div v-if="r.pricing_mode === 'auto' && r.auto_suggested_price != null" class="text-xs text-emerald-700 mt-0.5">
                    ≈ {{ formatMoney(r.auto_suggested_price) }}
                  </div>
                </td>
                <td class="px-3 py-2 text-xs text-gray-600">
                  <template v-if="r.last_gr_number">
                    <div class="font-mono">{{ r.last_gr_number }}</div>
                    <div>{{ r.last_gr_date }}</div>
                    <div v-if="r.cost_large_last != null" class="text-gray-500">HPP large: {{ r.cost_large_last }}</div>
                  </template>
                  <span v-else class="text-amber-600">Belum ada GR</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="p-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
          <div class="text-sm text-gray-600">
            Halaman {{ meta.current_page }} / {{ meta.last_page || 1 }} — total {{ meta.total }} item
          </div>
          <div class="flex gap-2">
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg border text-sm font-semibold disabled:opacity-40"
              :disabled="loading || meta.current_page <= 1"
              @click="load(meta.current_page - 1)"
            >
              Sebelumnya
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg border text-sm font-semibold disabled:opacity-40"
              :disabled="loading || meta.current_page >= meta.last_page"
              @click="load(meta.current_page + 1)"
            >
              Berikutnya
            </button>
            <button
              type="button"
              class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-sm font-bold disabled:opacity-50"
              :disabled="saving || rows.length === 0"
              @click="savePage"
            >
              <i class="fa-solid fa-floppy-disk mr-1"></i>
              Simpan halaman ini
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'saved']);

const search = ref('');
const loading = ref(false);
const saving = ref(false);
const exporting = ref(false);
const importing = ref(false);
const importFileInput = ref(null);
const rows = ref([]);
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});

function formatMoney(n) {
  if (n == null || n === '') return '—';
  try {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(n));
  } catch {
    return String(n);
  }
}

function onModeChange(r) {
  if (r.pricing_mode === 'auto' && r.auto_suggested_price != null) {
    r.price = Math.round(Number(r.auto_suggested_price));
  }
}

watch(
  () => props.show,
  (open) => {
    if (open) {
      search.value = '';
      load(1);
    }
  }
);

async function load(page = 1) {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/items/non-pos-pricing', {
      params: {
        page,
        search: search.value.trim(),
        per_page: 15,
      },
    });
    rows.value = (data.data || []).map((row) => ({
      ...row,
      pricing_mode: row.pricing_mode === 'auto' ? 'auto' : 'manual',
      price:
        row.pricing_mode === 'auto' && row.auto_suggested_price != null
          ? Math.round(Number(row.auto_suggested_price))
          : row.price != null
            ? Math.round(Number(row.price))
            : '',
    }));
    meta.value = {
      current_page: data.current_page || 1,
      last_page: data.last_page || 1,
      per_page: data.per_page || 15,
      total: data.total || 0,
    };
  } catch (e) {
    console.error(e);
    Swal.fire('Gagal', e.response?.data?.message || e.message || 'Tidak dapat memuat data', 'error');
  } finally {
    loading.value = false;
  }
}

async function exportTemplate() {
  exporting.value = true;
  try {
    const params = new URLSearchParams();
    if (search.value.trim()) params.set('search', search.value.trim());
    const url = `/api/items/non-pos-pricing/template${params.toString() ? `?${params.toString()}` : ''}`;
    window.location.assign(url);
  } catch (e) {
    console.error(e);
    Swal.fire('Gagal', e.response?.data?.message || e.message || 'Export gagal', 'error');
  } finally {
    setTimeout(() => {
      exporting.value = false;
    }, 800);
  }
}

async function onImportFile(e) {
  const input = e.target;
  const file = input?.files?.[0];
  if (!file) return;

  importing.value = true;
  try {
    const fd = new FormData();
    fd.append('file', file);
    const { data } = await axios.post('/api/items/non-pos-pricing/import', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    if (data.errors && data.errors.length) {
      await Swal.fire({
        icon: data.success ? 'warning' : 'error',
        title: data.success ? 'Sebagian berhasil' : 'Import',
        html: `<p class="mb-2 text-sm">${data.message || ''}</p><ul style="text-align:left;font-size:13px;max-height:240px;overflow:auto">${data.errors.map((err) => `<li>${err}</li>`).join('')}</ul>`,
      });
    } else {
      await Swal.fire({ icon: 'success', title: 'Berhasil', text: data.message || 'Import selesai' });
    }
    if (data.success) emit('saved');
    await load(meta.value.current_page);
  } catch (err) {
    const msg = err.response?.data?.message || err.message;
    const errs = err.response?.data?.errors;
    await Swal.fire({
      icon: 'error',
      title: 'Import gagal',
      html: errs && errs.length
        ? `<ul style="text-align:left">${errs.map((x) => `<li>${x}</li>`).join('')}</ul>`
        : msg,
    });
  } finally {
    importing.value = false;
    if (input) input.value = '';
  }
}

async function savePage() {
  const payloadRows = rows.value.map((r) => ({
    item_id: r.item_id,
    category_id: r.category_id,
    category_name: r.category_name ?? '',
    pricing_mode: r.pricing_mode,
    price: r.pricing_mode === 'auto' ? 0 : Number(r.price),
  }));

  const invalidManual = payloadRows.filter((x) => x.pricing_mode === 'manual' && (!x.price || x.price <= 0 || Number.isNaN(x.price)));
  if (invalidManual.length) {
    Swal.fire('Periksa input', 'Beberapa baris manual belum punya harga (> 0).', 'warning');
    return;
  }

  saving.value = true;
  try {
    const { data } = await axios.post('/api/items/non-pos-pricing', { rows: payloadRows });
    if (data.errors && data.errors.length) {
      await Swal.fire({
        icon: data.success ? 'warning' : 'error',
        title: data.success ? 'Sebagian tersimpan' : 'Gagal',
        html: `<ul style="text-align:left;font-size:13px">${data.errors.map((e) => `<li>${e}</li>`).join('')}</ul>`,
      });
    } else {
      Swal.fire('Berhasil', data.message || 'Tersimpan', 'success');
    }
    if (data.success) emit('saved');
    await load(meta.value.current_page);
  } catch (e) {
    const msg = e.response?.data?.message || e.message;
    const errs = e.response?.data?.errors;
    await Swal.fire({
      icon: 'error',
      title: 'Gagal simpan',
      html: errs && errs.length
        ? `<ul style="text-align:left">${errs.map((x) => `<li>${x}</li>`).join('')}</ul>`
        : msg,
    });
  } finally {
    saving.value = false;
  }
}
</script>
