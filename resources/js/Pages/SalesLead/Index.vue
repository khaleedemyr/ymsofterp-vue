<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-handshake text-emerald-600"></i>
            Sales Leads
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Tracking sales leads — update Real Pax / Revenue / Foto Bill setelah customer selesai.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('sales-leads.dashboard')"
            class="inline-flex items-center gap-2 bg-white border border-emerald-200 text-emerald-700 px-4 py-2 rounded-lg shadow-sm hover:bg-emerald-50 transition"
          >
            <i class="fa-solid fa-chart-column"></i>
            Summary
          </Link>
          <Link
            :href="route('sales-leads.create')"
            class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg shadow hover:bg-emerald-700 transition"
          >
            <i class="fa-solid fa-plus"></i>
            Buat Lead
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-4">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-6 gap-3">
          <input
            v-model="filterForm.search"
            type="text"
            placeholder="Cari nomor / company / sales..."
            class="md:col-span-2 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500"
          />
          <select v-model="filterForm.segment_1" class="rounded-lg border-gray-300">
            <option value="">Semua Segment 1</option>
            <option v-for="s in segment1Options" :key="s" :value="s">{{ s }}</option>
          </select>
          <select v-model="filterForm.status" class="rounded-lg border-gray-300">
            <option value="">Semua Status</option>
            <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
          </select>
          <select v-model="filterForm.result" class="rounded-lg border-gray-300">
            <option value="">Semua Result</option>
            <option value="gt_15">Result &gt; 15 Pax</option>
            <option value="lt_15">Result &lt; 15 Pax</option>
          </select>
          <div class="flex gap-2">
            <select v-model="filterForm.outlet_id" class="flex-1 rounded-lg border-gray-300">
              <option value="">Semua Outlet</option>
              <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Cari</button>
          </div>
        </form>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Total Leads (filter)</div>
          <div class="text-xl font-bold text-gray-900">{{ totals.total_leads || 0 }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Total Pax (filter)</div>
          <div class="text-xl font-bold text-gray-900">{{ totals.total_pax || 0 }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Total Revenue (Plan)</div>
          <div class="text-xl font-bold text-emerald-700">{{ formatMoney(totals.total_revenue) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
          <div class="text-xs text-gray-500">Total Real Revenue</div>
          <div class="text-xl font-bold text-emerald-700">{{ formatMoney(totals.total_real_revenue) }}</div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-3 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">Nomor</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700">Company</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700">Event / Date</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700">Status</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700">Sales</th>
              <th class="px-3 py-3 text-right font-semibold text-gray-700 whitespace-nowrap">Plan Rev</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700 whitespace-nowrap min-w-[90px]">Real Pax</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700 whitespace-nowrap min-w-[120px]">Real Revenue</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700 whitespace-nowrap min-w-[140px]">Foto Bill</th>
              <th class="px-3 py-3 text-left font-semibold text-gray-700 min-w-[160px]">Remarks</th>
              <th class="px-3 py-3 text-right font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!records.data?.length">
              <td colspan="11" class="px-4 py-8 text-center text-gray-500">Belum ada Sales Lead.</td>
            </tr>
            <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-emerald-50/30 align-top">
              <td class="px-3 py-3 font-medium whitespace-nowrap">{{ row.number }}</td>
              <td class="px-3 py-3">
                <div class="font-medium">{{ row.company_name }}</div>
                <div class="text-xs text-gray-500">{{ row.outlet?.nama_outlet || '-' }}</div>
                <div class="text-xs text-gray-400">Plan {{ row.pax ?? '-' }} pax</div>
              </td>
              <td class="px-3 py-3">
                <div>{{ row.type_of_event || '-' }}</div>
                <div class="text-xs text-gray-500">{{ formatDate(row.event_date) }}</div>
              </td>
              <td class="px-3 py-3">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusClass(row.status)">
                  {{ statuses[row.status] || row.status }}
                </span>
              </td>
              <td class="px-3 py-3">{{ row.sales?.nama_lengkap || '-' }}</td>
              <td class="px-3 py-3 text-right whitespace-nowrap">{{ formatMoney(row.revenue) }}</td>

              <td class="px-3 py-3">
                <input
                  v-model="actualForms[row.id].real_pax"
                  type="number"
                  min="0"
                  class="w-20 rounded-md border-gray-300 text-sm py-1.5"
                  placeholder="Pax"
                />
              </td>
              <td class="px-3 py-3">
                <input
                  v-model="actualForms[row.id].real_revenue"
                  type="number"
                  min="0"
                  step="0.01"
                  class="w-28 rounded-md border-gray-300 text-sm py-1.5"
                  placeholder="0"
                />
              </td>
              <td class="px-3 py-3">
                <div class="flex flex-col gap-1.5">
                  <div v-if="actualForms[row.id].preview_url || row.bill_photo_url" class="relative w-16 h-16">
                    <a :href="actualForms[row.id].preview_url || row.bill_photo_url" target="_blank" rel="noopener">
                      <img
                        :src="actualForms[row.id].preview_url || row.bill_photo_url"
                        class="w-16 h-16 object-cover rounded-md border border-gray-200"
                        alt="Bill"
                      />
                    </a>
                    <button
                      type="button"
                      class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-red-500 text-white text-[10px] flex items-center justify-center"
                      title="Hapus foto"
                      @click="clearBillPhoto(row.id)"
                    >
                      <i class="fa-solid fa-times"></i>
                    </button>
                  </div>
                  <div class="flex gap-1">
                    <button
                      type="button"
                      class="px-2 py-1 text-[11px] rounded border border-gray-200 text-gray-600 hover:bg-gray-50"
                      title="Upload"
                      @click="triggerUpload(row.id)"
                    >
                      <i class="fa-solid fa-upload"></i>
                    </button>
                    <button
                      type="button"
                      class="px-2 py-1 text-[11px] rounded border border-gray-200 text-gray-600 hover:bg-gray-50"
                      title="Kamera"
                      @click="triggerCamera(row.id)"
                    >
                      <i class="fa-solid fa-camera"></i>
                    </button>
                  </div>
                  <input
                    :ref="(el) => setFileRef(row.id, 'upload', el)"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="onBillSelected(row.id, $event)"
                  />
                  <input
                    :ref="(el) => setFileRef(row.id, 'camera', el)"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="hidden"
                    @change="onBillSelected(row.id, $event)"
                  />
                </div>
              </td>
              <td class="px-3 py-3">
                <textarea
                  v-model="actualForms[row.id].closing_remarks"
                  rows="2"
                  class="w-40 rounded-md border-gray-300 text-sm py-1.5"
                  placeholder="Remarks..."
                ></textarea>
              </td>
              <td class="px-3 py-3 text-right whitespace-nowrap">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md bg-emerald-600 text-white text-xs font-medium hover:bg-emerald-700 disabled:opacity-50 mr-2"
                  :disabled="savingId === row.id"
                  @click="saveActual(row)"
                >
                  <i class="fa-solid fa-spinner fa-spin" v-if="savingId === row.id"></i>
                  <i class="fa-solid fa-save" v-else></i>
                  Simpan
                </button>
                <Link :href="route('sales-leads.show', row.id)" class="text-emerald-600 hover:text-emerald-800 mr-2" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </Link>
                <Link :href="route('sales-leads.edit', row.id)" class="text-indigo-600 hover:text-indigo-800 mr-2" title="Edit">
                  <i class="fa-solid fa-pen"></i>
                </Link>
                <button type="button" class="text-red-600 hover:text-red-800" title="Hapus" @click="confirmDelete(row)">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="records.links?.length > 3" class="mt-4 flex justify-center gap-1 flex-wrap">
        <Link
          v-for="(link, i) in records.links"
          :key="i"
          :href="link.url || '#'"
          class="px-3 py-1 rounded text-sm"
          :class="link.active ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 border'"
          v-html="link.label"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  totals: {
    type: Object,
    default: () => ({
      total_leads: 0,
      total_pax: 0,
      total_revenue: 0,
      total_real_revenue: 0,
    }),
  },
  segment1Options: { type: Array, default: () => [] },
  statuses: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
});

const filterForm = reactive({
  search: props.filters.search || '',
  segment_1: props.filters.segment_1 || '',
  status: props.filters.status || '',
  outlet_id: props.filters.outlet_id || '',
  result: props.filters.result || '',
});

const actualForms = reactive({});
const fileRefs = reactive({});
const savingId = ref(null);

const initActualForms = () => {
  (props.records.data || []).forEach((row) => {
    actualForms[row.id] = {
      real_pax: row.real_pax ?? '',
      real_revenue: row.real_revenue ?? '',
      closing_remarks: row.closing_remarks || '',
      bill_file: null,
      preview_url: null,
      remove_bill_photo: false,
    };
  });
};
initActualForms();

watch(
  () => props.records.data,
  () => initActualForms(),
  { deep: true }
);

const setFileRef = (id, type, el) => {
  if (!fileRefs[id]) fileRefs[id] = {};
  if (el) fileRefs[id][type] = el;
};

const triggerUpload = (id) => fileRefs[id]?.upload?.click();
const triggerCamera = (id) => fileRefs[id]?.camera?.click();

const onBillSelected = (id, event) => {
  const file = event.target.files?.[0];
  if (!file) return;
  if (actualForms[id].preview_url) {
    URL.revokeObjectURL(actualForms[id].preview_url);
  }
  actualForms[id].bill_file = file;
  actualForms[id].preview_url = URL.createObjectURL(file);
  actualForms[id].remove_bill_photo = false;
  event.target.value = '';
};

const clearBillPhoto = (id) => {
  if (actualForms[id].preview_url) {
    URL.revokeObjectURL(actualForms[id].preview_url);
  }
  actualForms[id].bill_file = null;
  actualForms[id].preview_url = null;
  actualForms[id].remove_bill_photo = true;
};

const saveActual = async (row) => {
  const form = actualForms[row.id];
  if (!form) return;

  const fd = new FormData();
  if (form.real_pax !== '' && form.real_pax !== null) fd.append('real_pax', form.real_pax);
  if (form.real_revenue !== '' && form.real_revenue !== null) fd.append('real_revenue', form.real_revenue);
  fd.append('closing_remarks', form.closing_remarks || '');
  if (form.remove_bill_photo) fd.append('remove_bill_photo', '1');
  if (form.bill_file) fd.append('bill_photo', form.bill_file);

  savingId.value = row.id;
  try {
    const { data } = await axios.post(route('sales-leads.actual', row.id), fd, {
      headers: { 'Content-Type': 'multipart/form-data', Accept: 'application/json' },
    });
    if (data.success) {
      row.real_pax = data.lead.real_pax;
      row.real_revenue = data.lead.real_revenue;
      row.closing_remarks = data.lead.closing_remarks;
      row.bill_photo = data.lead.bill_photo;
      row.bill_photo_url = data.lead.bill_photo_url;
      if (form.preview_url) URL.revokeObjectURL(form.preview_url);
      form.bill_file = null;
      form.preview_url = null;
      form.remove_bill_photo = false;
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1800 });
    } else {
      Swal.fire('Error', data.message || 'Gagal menyimpan', 'error');
    }
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || 'Gagal menyimpan data real', 'error');
  } finally {
    savingId.value = null;
  }
};

const applyFilters = () => {
  router.get(route('sales-leads.index'), { ...filterForm }, { preserveState: true, replace: true });
};

const formatDate = (d) => (d ? String(d).substring(0, 10) : '-');

const formatMoney = (v) => {
  if (v === null || v === undefined || v === '') return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v));
};

const statusClass = (s) => {
  const map = {
    new_leads: 'bg-blue-100 text-blue-800',
    connected: 'bg-sky-100 text-sky-800',
    quotation_send: 'bg-amber-100 text-amber-800',
    confirmed: 'bg-emerald-100 text-emerald-800',
    no_respond: 'bg-gray-100 text-gray-700',
    cancelled: 'bg-red-100 text-red-800',
  };
  return map[s] || 'bg-gray-100 text-gray-700';
};

const confirmDelete = async (row) => {
  const result = await Swal.fire({
    title: 'Hapus Sales Lead?',
    text: `${row.number} — ${row.company_name}`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
  });
  if (!result.isConfirmed) return;
  router.delete(route('sales-leads.destroy', row.id));
};
</script>
