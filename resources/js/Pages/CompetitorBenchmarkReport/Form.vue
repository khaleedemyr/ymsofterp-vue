<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-teal-600"></i>
            {{ isEdit ? 'Edit Competitor Benchmark Report' : 'Buat Competitor Benchmark Report' }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">Isi data report, benchmark kompetitor, dan PIC</p>
        </div>
        <Link
          :href="isEdit ? route('competitor-benchmark-report.show', record.id) : route('competitor-benchmark-report.index')"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
        >
          <i class="fa-solid fa-arrow-left"></i>
          Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <div class="bg-white rounded-xl shadow p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Report</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan *</label>
              <input v-model="form.report_month" type="month" required class="w-full rounded-lg border-gray-300 focus:border-teal-500 focus:ring-teal-500" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Outlet *</label>
              <select v-model="form.outlet_id" required class="w-full rounded-lg border-gray-300 focus:border-teal-500 focus:ring-teal-500">
                <option value="">Pilih outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">{{ outlet.nama_outlet }}</option>
              </select>
            </div>
            <div class="md:col-span-2 xl:col-span-2 cbr-ms-field">
              <label class="block text-xs font-semibold text-gray-600 mb-1">PIC</label>
              <Multiselect
                v-model="selectedPics"
                :options="picOptions"
                label="name"
                track-by="id"
                :multiple="true"
                :searchable="true"
                :internal-search="false"
                :close-on-select="false"
                :show-labels="false"
                :loading="picLoading"
                placeholder="Cari user PIC..."
                @search-change="searchPicUsers"
                @open="onPicOpen"
              >
                <template #option="{ option }">
                  <div>
                    <div class="font-medium text-sm">{{ option.name }}</div>
                    <div class="text-xs text-gray-500">{{ option.jabatan || option.email || '-' }}</div>
                  </div>
                </template>
              </Multiselect>
            </div>
            <div class="md:col-span-2 xl:col-span-4">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan</label>
              <input v-model="form.notes" type="text" placeholder="Catatan opsional..." class="w-full rounded-lg border-gray-300 focus:border-teal-500 focus:ring-teal-500" />
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow mb-6 overflow-visible">
          <div class="flex items-center justify-between px-6 py-4 border-b bg-gradient-to-r from-teal-50 to-white">
            <div>
              <h2 class="text-lg font-semibold text-gray-800">Daftar Benchmark</h2>
              <p class="text-xs text-gray-500">Satu baris = satu kunjungan kompetitor</p>
            </div>
            <button type="button" @click="addItem" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-teal-600 text-white hover:bg-teal-700 text-sm transition">
              <i class="fa-solid fa-plus"></i>
              Tambah Baris
            </button>
          </div>

          <div class="overflow-x-auto pb-4">
            <table class="min-w-[1400px] w-full text-sm">
              <thead class="bg-gray-50 border-b text-xs font-bold text-gray-600 uppercase">
                <tr>
                  <th class="px-3 py-3 text-left w-10">No</th>
                  <th class="px-3 py-3 text-left min-w-[160px]">Brand / Restaurant</th>
                  <th class="px-3 py-3 text-left min-w-[140px]">Location</th>
                  <th class="px-3 py-3 text-left min-w-[120px]">Visit Date</th>
                  <th class="px-3 py-3 text-left min-w-[160px]">Product Benchmark</th>
                  <th class="px-3 py-3 text-left min-w-[160px]">Service Benchmark</th>
                  <th class="px-3 py-3 text-left min-w-[160px]">Pricing Benchmark</th>
                  <th class="px-3 py-3 text-left min-w-[160px]">Operational Benchmark</th>
                  <th class="px-3 py-3 text-left min-w-[160px]">Market & Positioning</th>
                  <th class="px-3 py-3 text-left min-w-[160px]">Summary Report</th>
                  <th class="px-3 py-3 text-left min-w-[160px]">Development & Action Plan</th>
                  <th class="px-3 py-3 text-center w-14">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in form.items" :key="index" class="border-b align-top hover:bg-teal-50/30">
                  <td class="px-3 py-3 text-gray-500">{{ index + 1 }}</td>
                  <td class="px-3 py-3"><input v-model="item.brand_restaurant_visited" required type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Nama brand" /></td>
                  <td class="px-3 py-3"><input v-model="item.location" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Lokasi" /></td>
                  <td class="px-3 py-3"><input v-model="item.visit_date" type="date" class="w-full rounded-lg border-gray-300 text-sm" /></td>
                  <td class="px-3 py-3"><textarea v-model="item.product_benchmark" rows="3" class="w-full rounded-lg border-gray-300 text-sm" /></td>
                  <td class="px-3 py-3"><textarea v-model="item.service_benchmark" rows="3" class="w-full rounded-lg border-gray-300 text-sm" /></td>
                  <td class="px-3 py-3"><textarea v-model="item.pricing_benchmark" rows="3" class="w-full rounded-lg border-gray-300 text-sm" /></td>
                  <td class="px-3 py-3"><textarea v-model="item.operational_benchmark" rows="3" class="w-full rounded-lg border-gray-300 text-sm" /></td>
                  <td class="px-3 py-3"><textarea v-model="item.market_positioning_benchmark" rows="3" class="w-full rounded-lg border-gray-300 text-sm" /></td>
                  <td class="px-3 py-3"><textarea v-model="item.summary_report" rows="3" class="w-full rounded-lg border-gray-300 text-sm" /></td>
                  <td class="px-3 py-3"><textarea v-model="item.development_action_plan" rows="3" class="w-full rounded-lg border-gray-300 text-sm" /></td>
                  <td class="px-3 py-3 text-center">
                    <button type="button" @click="removeItem(index)" :disabled="form.items.length === 1" class="text-red-500 hover:text-red-700 disabled:opacity-40">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="isEdit ? route('competitor-benchmark-report.show', record.id) : route('competitor-benchmark-report.index')" class="px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition">Batal</Link>
          <button type="submit" :disabled="form.processing" class="px-5 py-2.5 rounded-lg bg-teal-600 text-white hover:bg-teal-700 disabled:opacity-50 transition inline-flex items-center gap-2">
            <i v-if="form.processing" class="fa fa-spinner fa-spin"></i>
            Simpan
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  record: { type: Object, default: null },
  outlets: { type: Array, default: () => [] },
});

const isEdit = computed(() => Boolean(props.record?.id));
const selectedPics = ref([]);
const picOptions = ref([]);
const picLoading = ref(false);

function emptyItem() {
  return {
    brand_restaurant_visited: '',
    location: '',
    visit_date: '',
    product_benchmark: '',
    service_benchmark: '',
    pricing_benchmark: '',
    operational_benchmark: '',
    market_positioning_benchmark: '',
    summary_report: '',
    development_action_plan: '',
  };
}

function extractMonth(value) {
  if (!value) return new Date().toISOString().slice(0, 7);
  return String(value).slice(0, 7);
}

function resolvePics(stored) {
  if (!Array.isArray(stored)) return [];
  return stored.map((entry) => ({
    id: entry?.id,
    name: entry?.name || entry?.nama_lengkap || `#${entry?.id || ''}`,
    jabatan: entry?.jabatan || '',
  })).filter((entry) => entry.id);
}

function mapRecordItems(items) {
  if (!items?.length) return [emptyItem()];
  return items.map((item) => ({
    brand_restaurant_visited: item.brand_restaurant_visited || '',
    location: item.location || '',
    visit_date: item.visit_date ? String(item.visit_date).slice(0, 10) : '',
    product_benchmark: item.product_benchmark || '',
    service_benchmark: item.service_benchmark || '',
    pricing_benchmark: item.pricing_benchmark || '',
    operational_benchmark: item.operational_benchmark || '',
    market_positioning_benchmark: item.market_positioning_benchmark || '',
    summary_report: item.summary_report || '',
    development_action_plan: item.development_action_plan || '',
  }));
}

async function searchPicUsers(query = '') {
  picLoading.value = true;
  try {
    const { data } = await axios.get(route('competitor-benchmark-report.pic-users'), { params: { search: query || '' } });
    const selectedIds = new Set((selectedPics.value || []).map((pic) => pic.id));
    const fetched = (data.users || []).map((user) => ({
      id: user.id,
      name: user.name,
      jabatan: user.jabatan || '',
      email: user.email || '',
    }));
    picOptions.value = [
      ...(selectedPics.value || []),
      ...fetched.filter((user) => !selectedIds.has(user.id)),
    ];
  } catch {
    picOptions.value = [...(selectedPics.value || [])];
  } finally {
    picLoading.value = false;
  }
}

function onPicOpen() {
  searchPicUsers('');
}

const form = useForm({
  report_month: extractMonth(props.record?.report_month),
  outlet_id: props.record?.outlet_id || '',
  notes: props.record?.notes || '',
  items: mapRecordItems(props.record?.items),
});

function addItem() {
  form.items.push(emptyItem());
}

function removeItem(index) {
  if (form.items.length <= 1) return;
  form.items.splice(index, 1);
}

function buildPayload() {
  return {
    report_month: form.report_month,
    outlet_id: form.outlet_id,
    notes: form.notes,
    pic_user_ids: (selectedPics.value || []).map((pic) => pic.id),
    items: form.items.map((item) => ({
      brand_restaurant_visited: item.brand_restaurant_visited,
      location: item.location || null,
      visit_date: item.visit_date || null,
      product_benchmark: item.product_benchmark || null,
      service_benchmark: item.service_benchmark || null,
      pricing_benchmark: item.pricing_benchmark || null,
      operational_benchmark: item.operational_benchmark || null,
      market_positioning_benchmark: item.market_positioning_benchmark || null,
      summary_report: item.summary_report || null,
      development_action_plan: item.development_action_plan || null,
    })),
  };
}

function submit() {
  const payload = buildPayload();

  if (isEdit.value) {
    form.transform(() => payload).put(route('competitor-benchmark-report.update', props.record.id));
  } else {
    form.transform(() => payload).post(route('competitor-benchmark-report.store'));
  }
}

onMounted(() => {
  selectedPics.value = resolvePics(props.record?.pics);
  picOptions.value = [...selectedPics.value];
  searchPicUsers('');
});
</script>

<style scoped>
:deep(.multiselect) { min-height: 42px; }
:deep(.multiselect__tags) { border-radius: 0.5rem; border-color: rgb(209 213 219); min-height: 42px; }
:deep(.multiselect--active) { z-index: 40; }
</style>
