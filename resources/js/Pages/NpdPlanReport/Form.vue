<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-lightbulb text-amber-500"></i>
            {{ isEdit ? 'Edit NPD Plan & Report' : 'Buat NPD Plan & Report' }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">Isi data report, produk, dan pilih approver sebelum simpan</p>
        </div>
        <Link
          :href="isEdit ? route('npd-plan-report.show', record.id) : route('npd-plan-report.index')"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
        >
          <i class="fa-solid fa-arrow-left"></i>
          Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <div class="bg-white rounded-xl shadow p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Report</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan *</label>
              <input
                v-model="form.report_month"
                type="month"
                required
                class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
              />
              <p v-if="form.errors.report_month" class="text-xs text-red-500 mt-1">{{ form.errors.report_month }}</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Outlet *</label>
              <select
                v-model="form.outlet_id"
                required
                class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
              >
                <option value="">Pilih outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
              <p v-if="form.errors.outlet_id" class="text-xs text-red-500 mt-1">{{ form.errors.outlet_id }}</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan</label>
              <input
                v-model="form.notes"
                type="text"
                placeholder="Catatan opsional..."
                class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
              />
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow mb-6 overflow-visible">
          <div class="flex items-center justify-between px-6 py-4 border-b bg-gradient-to-r from-amber-50 to-white">
            <div>
              <h2 class="text-lg font-semibold text-gray-800">Daftar Produk</h2>
              <p class="text-xs text-gray-500">Category searchable, area/outlet bisa multi-select</p>
            </div>
            <button
              type="button"
              @click="addItem"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600 text-sm transition"
            >
              <i class="fa-solid fa-plus"></i>
              Tambah Produk
            </button>
          </div>

          <p v-if="form.errors.items" class="px-6 pt-4 text-sm text-red-500">{{ form.errors.items }}</p>

          <div class="overflow-x-auto overflow-y-visible pb-4">
          <div class="min-w-[1280px]">
          <div class="hidden xl:grid xl:grid-cols-14 gap-3 px-6 py-3 bg-gray-50 border-b text-xs font-bold text-gray-600 uppercase">
            <div class="col-span-2">Product Name</div>
            <div class="col-span-2">Category</div>
            <div class="col-span-2">PIC</div>
            <div class="col-span-1">Dev. Date</div>
            <div class="col-span-1">Purpose</div>
            <div class="col-span-1">Launch Date</div>
            <div class="col-span-2">Area / Outlet</div>
            <div class="col-span-1">F&B Cost</div>
            <div class="col-span-1">Selling Price</div>
            <div class="col-span-1 text-center">Aksi</div>
          </div>

          <div
            v-for="(item, index) in form.items"
            :key="index"
            class="px-6 py-4 border-b last:border-b-0 hover:bg-amber-50/30 transition-colors overflow-visible"
          >
            <div class="flex items-center gap-2 mb-3 xl:hidden">
              <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100 text-amber-700 text-xs font-bold">
                {{ index + 1 }}
              </span>
              <span class="text-sm font-semibold text-gray-700">Product #{{ index + 1 }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-14 gap-3 items-start overflow-visible">
              <div class="xl:col-span-2 overflow-visible">
                <label class="xl:hidden text-xs font-semibold text-gray-500 mb-1 block">Product Name *</label>
                <input
                  v-model="item.product_name"
                  type="text"
                  required
                  placeholder="Nama produk"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>

              <div class="xl:col-span-2 overflow-visible npd-ms-field">
                <label class="xl:hidden text-xs font-semibold text-gray-500 mb-1 block">Category *</label>
                <Multiselect
                  v-model="item.category"
                  :options="categories"
                  label="name"
                  track-by="id"
                  :searchable="true"
                  :allow-empty="false"
                  :show-labels="false"
                  placeholder="Cari category..."
                  class="text-sm"
                  @open="onMultiselectOpen"
                  @close="onMultiselectClose"
                />
              </div>

              <div class="xl:col-span-2 overflow-visible npd-ms-field">
                <label class="xl:hidden text-xs font-semibold text-gray-500 mb-1 block">PIC</label>
                <Multiselect
                  v-model="item.pics"
                  :options="item.picOptions"
                  label="name"
                  track-by="id"
                  :multiple="true"
                  :searchable="true"
                  :internal-search="false"
                  :close-on-select="false"
                  :show-labels="false"
                  :loading="item.picLoading"
                  placeholder="Cari user PIC..."
                  class="text-sm"
                  @search-change="(query) => searchPicUsers(index, query)"
                  @open="() => onPicMultiselectOpen(index)"
                  @close="onMultiselectClose"
                >
                  <template #option="{ option }">
                    <div>
                      <div class="font-medium text-sm">{{ option.name }}</div>
                      <div class="text-xs text-gray-500">{{ option.jabatan || option.email || '-' }}</div>
                    </div>
                  </template>
                </Multiselect>
              </div>

              <div class="xl:col-span-1">
                <label class="xl:hidden text-xs font-semibold text-gray-500 mb-1 block">Development Date</label>
                <input
                  v-model="item.development_date"
                  type="date"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>

              <div class="xl:col-span-1">
                <label class="xl:hidden text-xs font-semibold text-gray-500 mb-1 block">Purpose *</label>
                <select
                  v-model="item.purpose"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                >
                  <option v-for="opt in purposeOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>

              <div class="xl:col-span-1">
                <label class="xl:hidden text-xs font-semibold text-gray-500 mb-1 block">Launch Date</label>
                <input
                  v-model="item.proposed_launch_date"
                  type="date"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>

              <div class="xl:col-span-2 overflow-visible npd-ms-field">
                <label class="xl:hidden text-xs font-semibold text-gray-500 mb-1 block">Area / Outlet *</label>
                <Multiselect
                  v-model="item.launch_outlets"
                  :options="launchOutlets"
                  label="nama_outlet"
                  track-by="id_outlet"
                  :multiple="true"
                  :searchable="true"
                  :close-on-select="false"
                  :show-labels="false"
                  placeholder="Pilih outlet launch..."
                  class="text-sm"
                  @open="onMultiselectOpen"
                  @close="onMultiselectClose"
                />
              </div>

              <div class="xl:col-span-1">
                <label class="xl:hidden text-xs font-semibold text-gray-500 mb-1 block">F&B Cost</label>
                <input
                  v-model.number="item.fb_cost"
                  type="number"
                  min="0"
                  step="1"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>

              <div class="xl:col-span-1">
                <label class="xl:hidden text-xs font-semibold text-gray-500 mb-1 block">Selling Price</label>
                <input
                  v-model.number="item.selling_price"
                  type="number"
                  min="0"
                  step="1"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>

              <div class="xl:col-span-1 flex xl:justify-center">
                <button
                  type="button"
                  @click="removeItem(index)"
                  :disabled="form.items.length === 1"
                  class="px-3 py-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 disabled:opacity-40 disabled:cursor-not-allowed transition text-sm"
                  title="Hapus baris"
                >
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </div>
          </div>
          </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-1">Approval Flow *</h2>
          <p class="text-sm text-gray-500 mb-4">Pilih approver secara berurutan (level 1 = pertama disetujui)</p>
          <p v-if="form.errors.approvers" class="text-sm text-red-500 mb-3">{{ form.errors.approvers }}</p>

          <div class="relative mb-4 max-w-xl">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Cari Approver</label>
            <input
              v-model="approverSearch"
              type="text"
              placeholder="Nama, email, atau jabatan..."
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              @input="onApproverSearch"
            />
            <div
              v-if="showApproverDropdown && approverResults.length"
              class="absolute z-20 mt-1 w-full bg-white border rounded-lg shadow-lg max-h-48 overflow-y-auto"
            >
              <button
                v-for="user in approverResults"
                :key="user.id"
                type="button"
                class="w-full text-left px-4 py-2 hover:bg-blue-50 text-sm border-b last:border-b-0"
                @click="addApprover(user)"
              >
                <div class="font-medium">{{ user.name }}</div>
                <div class="text-xs text-gray-500">{{ user.jabatan || user.email }}</div>
              </button>
            </div>
          </div>

          <div v-if="selectedApprovers.length" class="space-y-2 max-w-xl">
            <div
              v-for="(approver, index) in selectedApprovers"
              :key="approver.id"
              class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 border border-blue-100"
            >
              <span class="w-7 h-7 rounded-full bg-blue-500 text-white text-xs font-bold flex items-center justify-center">
                {{ index + 1 }}
              </span>
              <div class="flex-1 min-w-0">
                <div class="font-medium text-sm truncate">{{ approver.name }}</div>
                <div class="text-xs text-gray-500 truncate">{{ approver.jabatan || approver.email }}</div>
              </div>
              <button type="button" class="text-red-500 hover:text-red-700" @click="removeApprover(index)">
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400">Belum ada approver dipilih.</p>
        </div>

        <div class="flex justify-end gap-3">
          <Link
            :href="isEdit ? route('npd-plan-report.show', record.id) : route('npd-plan-report.index')"
            class="px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
          >
            Batal
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-5 py-2.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50 transition inline-flex items-center gap-2"
          >
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
import { computed, nextTick, onMounted, ref } from 'vue';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, default: null },
  outlets: { type: Array, default: () => [] },
  launchOutlets: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  purposeOptions: { type: Array, default: () => [] },
});

const isEdit = computed(() => Boolean(props.record?.id));

const approverSearch = ref('');
const approverResults = ref([]);
const showApproverDropdown = ref(false);
const selectedApprovers = ref([]);
let searchTimer = null;

function emptyItem() {
  return {
    product_name: '',
    category: null,
    pics: [],
    picOptions: [],
    picLoading: false,
    development_date: '',
    purpose: 'new_product',
    proposed_launch_date: '',
    launch_outlets: [],
    fb_cost: 0,
    selling_price: 0,
  };
}

function extractMonth(value) {
  if (!value) return new Date().toISOString().slice(0, 7);
  return String(value).slice(0, 7);
}

function resolveCategory(item) {
  if (item.category_id) {
    return props.categories.find((c) => c.id === item.category_id) || { id: item.category_id, name: item.category || '' };
  }
  if (item.category) {
    return props.categories.find((c) => c.name === item.category) || { id: null, name: item.category };
  }
  return null;
}

function resolveLaunchOutlets(item) {
  const stored = item.proposed_launch_area_outlet;
  if (Array.isArray(stored) && stored.length) {
    return stored
      .map((entry) => {
        const id = entry?.id ?? entry?.id_outlet;
        const found = props.launchOutlets.find((o) => o.id_outlet === id);
        return found || { id_outlet: id, nama_outlet: entry?.name || entry?.nama_outlet || `#${id}` };
      })
      .filter(Boolean);
  }
  return [];
}

function resolvePics(stored) {
  if (!Array.isArray(stored)) return [];
  return stored
    .map((entry) => ({
      id: entry?.id,
      name: entry?.name || entry?.nama_lengkap || `#${entry?.id || ''}`,
      jabatan: entry?.jabatan || '',
    }))
    .filter((entry) => entry.id);
}

function mapRecordItems(items) {
  if (!items?.length) return [emptyItem()];
  return items.map((item) => {
    const pics = resolvePics(item.pics);
    return {
      product_name: item.product_name || '',
      category: resolveCategory(item),
      pics,
      picOptions: [...pics],
      picLoading: false,
      development_date: item.development_date ? String(item.development_date).slice(0, 10) : '',
      purpose: item.purpose || 'new_product',
      proposed_launch_date: item.proposed_launch_date ? String(item.proposed_launch_date).slice(0, 10) : '',
      launch_outlets: resolveLaunchOutlets(item),
      fb_cost: Number(item.fb_cost || 0),
      selling_price: Number(item.selling_price || 0),
    };
  });
}

async function searchPicUsers(index, query = '') {
  const item = form.items[index];
  if (!item) return;

  item.picLoading = true;
  try {
    const { data } = await axios.get(route('npd-plan-report.approvers'), { params: { search: query || '' } });
    const selectedIds = new Set((item.pics || []).map((pic) => pic.id));
    const fetched = (data.users || []).map((user) => ({
      id: user.id,
      name: user.name,
      jabatan: user.jabatan || '',
      email: user.email || '',
    }));
    item.picOptions = [
      ...(item.pics || []),
      ...fetched.filter((user) => !selectedIds.has(user.id)),
    ];
  } catch {
    item.picOptions = [...(item.pics || [])];
  } finally {
    item.picLoading = false;
  }
}

function applyMultiselectDropdownPosition(root) {
  if (!root) return;
  const wrapper = root.querySelector('.multiselect__content-wrapper');
  if (!wrapper) return;

  const rect = root.getBoundingClientRect();
  const isAbove = root.classList.contains('multiselect--above');

  wrapper.style.position = 'fixed';
  wrapper.style.left = `${rect.left}px`;
  wrapper.style.width = `${Math.max(rect.width, 220)}px`;
  wrapper.style.zIndex = '99999';
  wrapper.style.maxHeight = '240px';

  if (isAbove) {
    wrapper.style.top = 'auto';
    wrapper.style.bottom = `${window.innerHeight - rect.top + 4}px`;
  } else {
    wrapper.style.top = `${rect.bottom + 4}px`;
    wrapper.style.bottom = 'auto';
  }
}

function resetMultiselectDropdownPosition(root) {
  if (!root) return;
  const wrapper = root.querySelector('.multiselect__content-wrapper');
  if (!wrapper) return;

  wrapper.style.position = '';
  wrapper.style.left = '';
  wrapper.style.top = '';
  wrapper.style.bottom = '';
  wrapper.style.width = '';
  wrapper.style.zIndex = '';
  wrapper.style.maxHeight = '';
}

function onMultiselectOpen() {
  nextTick(() => {
    document.querySelectorAll('.multiselect.multiselect--active').forEach(applyMultiselectDropdownPosition);
  });
}

function onMultiselectClose() {
  nextTick(() => {
    document.querySelectorAll('.multiselect').forEach(resetMultiselectDropdownPosition);
  });
}

async function onPicMultiselectOpen(index) {
  await searchPicUsers(index, '');
  onMultiselectOpen();
}

const form = useForm({
  report_month: extractMonth(props.record?.report_month),
  outlet_id: props.record?.outlet_id || '',
  notes: props.record?.notes || '',
  items: mapRecordItems(props.record?.items),
  approvers: [],
});

function addItem() {
  form.items.push(emptyItem());
  searchPicUsers(form.items.length - 1, '');
}

function removeItem(index) {
  if (form.items.length <= 1) return;
  form.items.splice(index, 1);
}

function onApproverSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadApprovers(approverSearch.value), 300);
}

async function loadApprovers(search = '') {
  try {
    const { data } = await axios.get(route('npd-plan-report.approvers'), { params: { search } });
    approverResults.value = data.users || [];
    showApproverDropdown.value = approverResults.value.length > 0;
  } catch {
    approverResults.value = [];
    showApproverDropdown.value = false;
  }
}

function addApprover(user) {
  if (!selectedApprovers.value.find((a) => a.id === user.id)) {
    selectedApprovers.value.push(user);
  }
  approverSearch.value = '';
  showApproverDropdown.value = false;
}

function removeApprover(index) {
  selectedApprovers.value.splice(index, 1);
}

function buildPayload() {
  return {
    report_month: form.report_month,
    outlet_id: form.outlet_id,
    notes: form.notes,
    approvers: selectedApprovers.value.map((a) => a.id),
    items: form.items.map((item) => ({
      product_name: item.product_name,
      category_id: item.category?.id,
      development_date: item.development_date || null,
      purpose: item.purpose,
      proposed_launch_date: item.proposed_launch_date || null,
      proposed_launch_outlet_ids: (item.launch_outlets || []).map((o) => o.id_outlet),
      pic_user_ids: (item.pics || []).map((pic) => pic.id),
      fb_cost: item.fb_cost,
      selling_price: item.selling_price,
    })),
  };
}

function submit() {
  if (!selectedApprovers.value.length) {
    Swal.fire('Error', 'Pilih minimal satu approver.', 'error');
    return;
  }

  for (const item of form.items) {
    if (!item.category?.id) {
      Swal.fire('Error', 'Semua produk wajib memiliki category.', 'error');
      return;
    }
    if (!item.launch_outlets?.length) {
      Swal.fire('Error', 'Semua produk wajib memiliki area/outlet launch.', 'error');
      return;
    }
  }

  const payload = buildPayload();

  if (isEdit.value) {
    form.transform(() => payload).put(route('npd-plan-report.update', props.record.id));
  } else {
    form.transform(() => payload).post(route('npd-plan-report.store'));
  }
}

onMounted(() => {
  form.items.forEach((_, index) => searchPicUsers(index, ''));
});
</script>

<style scoped>
:deep(.multiselect) {
  min-height: 42px;
}
:deep(.multiselect__tags) {
  border-radius: 0.5rem;
  border-color: rgb(209 213 219);
  min-height: 42px;
}
:deep(.multiselect--active) {
  z-index: 40;
}
:deep(.multiselect__content-wrapper) {
  border-radius: 0.5rem;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
}
.npd-ms-field {
  position: relative;
  z-index: 1;
}
.npd-ms-field:focus-within {
  z-index: 50;
}
@media (min-width: 1280px) {
  .xl\:grid-cols-14 {
    grid-template-columns: repeat(14, minmax(0, 1fr));
  }
}
</style>
