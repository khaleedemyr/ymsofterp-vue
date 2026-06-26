<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-utensils text-violet-600"></i>
            {{ isEdit ? 'Edit' : 'Tambah' }} Jadwal Calibration
          </h1>
        </div>
        <Link :href="route('fb-product-calibration.index')" class="text-gray-600 hover:text-gray-800">
          <i class="fa-solid fa-arrow-left mr-1"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <div class="bg-white rounded-xl shadow p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Outlet <span class="text-red-500">*</span></label>
            <select
              v-model="form.outlet_id"
              required
              class="w-full rounded-lg border-gray-300 focus:border-violet-500 focus:ring-violet-500"
              @change="onOutletChange"
            >
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Tanggal Calibration <span class="text-red-500">*</span></label>
            <input
              v-model="form.scheduled_date"
              type="date"
              required
              :min="isEdit ? undefined : minScheduleDate"
              class="w-full rounded-lg border-gray-300 focus:border-violet-500 focus:ring-violet-500"
            />
          </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Conducted By <span class="text-red-500">*</span></label>
          <div class="relative">
            <input
              id="conductor-search-input"
              v-model="conductorSearch"
              type="text"
              placeholder="Cari user conductor..."
              autocomplete="off"
              class="w-full rounded-lg border-gray-300 focus:border-violet-500 focus:ring-violet-500"
              @input="onConductorSearch"
              @focus="onConductorFocus"
              @blur="onConductorBlur"
            />
            <Teleport to="body">
              <div
                v-if="showConductorDropdown && conductorSuggestions.length"
                :style="conductorDropdownStyle"
                class="fixed z-[99999] bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
              >
                <button
                  v-for="user in conductorSuggestions"
                  :key="user.id"
                  type="button"
                  class="w-full text-left px-3 py-2 hover:bg-violet-50 border-b border-gray-100 last:border-b-0"
                  @mousedown.prevent="selectConductor(user)"
                >
                  <div class="font-medium text-gray-800">{{ user.nama_lengkap }}</div>
                  <div class="text-xs text-gray-500">{{ user.jabatan_name }}</div>
                </button>
              </div>
            </Teleport>
          </div>
          <p v-if="form.errors.conductor_id" class="text-sm text-red-600 mt-1">{{ form.errors.conductor_id }}</p>
        </div>

        <div class="bg-white rounded-xl shadow p-6 space-y-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Product <span class="text-red-500">*</span></label>
            <p class="text-xs text-gray-500 mb-2">Item POS yang available untuk outlet terpilih (show_pos=1)</p>
            <Multiselect
              v-model="selectedProducts"
              :options="productOptions"
              :multiple="true"
              :searchable="true"
              :internal-search="false"
              :loading="productLoading"
              :disabled="!form.outlet_id"
              placeholder="Cari product..."
              label="display_label"
              track-by="id"
              @search-change="searchProducts"
            />
            <p v-if="form.errors.products" class="text-sm text-red-600 mt-1">{{ form.errors.products }}</p>
          </div>

          <div v-if="selectedProducts.length" class="space-y-2">
            <div
              v-for="product in selectedProducts"
              :key="product.id"
              class="flex items-start justify-between gap-3 rounded-lg border border-gray-200 p-3 text-sm"
            >
              <div>
                <div class="font-semibold text-gray-800">{{ product.item_name }}</div>
                <div class="text-gray-500">
                  {{ product.category_name }}
                  <span v-if="product.sub_category_name"> · {{ product.sub_category_name }}</span>
                </div>
              </div>
              <button type="button" class="text-red-600 hover:text-red-800" @click="removeProduct(product)">
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('fb-product-calibration.index')" class="px-6 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
            Batal
          </Link>
          <button type="submit" :disabled="form.processing" class="px-6 py-2.5 rounded-lg bg-violet-600 text-white hover:bg-violet-700 disabled:opacity-50">
            {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Update' : 'Simpan') }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import { computed, nextTick, onMounted, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, default: null },
  scheduledDate: { type: String, default: '' },
  outlets: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.record?.id);

const minScheduleDate = computed(() => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
});

const form = useForm({
  outlet_id: props.record?.outlet_id || '',
  scheduled_date: props.record?.scheduled_date?.slice?.(0, 10) || props.scheduledDate || '',
  conductor_id: props.record?.conductor_id || '',
  products: [],
});

const conductorSearch = ref(props.record?.conductor_name || '');
const selectedConductor = ref(null);
const conductorSuggestions = ref([]);
const showConductorDropdown = ref(false);
const conductorDropdownStyle = ref({});
let conductorTimer = null;

const selectedProducts = ref([]);
const productOptions = ref([]);
const productLoading = ref(false);
let productTimer = null;

function initFromRecord() {
  if (!props.record) return;
  selectedProducts.value = (props.record.products || []).map((p) => ({
    id: p.item_id,
    item_id: p.item_id,
    item_name: p.item_name,
    category_name: p.category_name,
    sub_category_name: p.sub_category_name,
    display_label: p.sub_category_name
      ? `${p.item_name} (${p.category_name} · ${p.sub_category_name})`
      : `${p.item_name} (${p.category_name || '-'})`,
  }));
  productOptions.value = [...selectedProducts.value];
  selectedConductor.value = {
    id: props.record.conductor_id,
    nama_lengkap: props.record.conductor_name,
  };
}

function onOutletChange() {
  selectedProducts.value = [];
  productOptions.value = [];
}

function onConductorSearch() {
  clearTimeout(conductorTimer);
  conductorTimer = setTimeout(async () => {
    const q = conductorSearch.value.trim();
    if (q.length < 2) {
      conductorSuggestions.value = [];
      showConductorDropdown.value = false;
      return;
    }
    try {
      const res = await axios.get('/api/fb-product-calibration/search-conductors', { params: { q } });
      conductorSuggestions.value = res.data.users || [];
      showConductorDropdown.value = conductorSuggestions.value.length > 0;
      await nextTick();
      updateConductorDropdownPosition();
    } catch {
      conductorSuggestions.value = [];
      showConductorDropdown.value = false;
    }
  }, 300);
}

function onConductorFocus() {
  if (conductorSuggestions.value.length) {
    showConductorDropdown.value = true;
    nextTick().then(updateConductorDropdownPosition);
  }
}

function onConductorBlur() {
  setTimeout(() => { showConductorDropdown.value = false; }, 150);
}

function updateConductorDropdownPosition() {
  const el = document.getElementById('conductor-search-input');
  if (!el) return;
  const rect = el.getBoundingClientRect();
  conductorDropdownStyle.value = {
    top: `${rect.bottom + 4}px`,
    left: `${rect.left}px`,
    width: `${rect.width}px`,
  };
}

function selectConductor(user) {
  selectedConductor.value = user;
  form.conductor_id = user.id;
  conductorSearch.value = user.nama_lengkap;
  showConductorDropdown.value = false;
}

function searchProducts(query) {
  if (!form.outlet_id) return;
  clearTimeout(productTimer);
  productTimer = setTimeout(async () => {
    productLoading.value = true;
    try {
      const excludeIds = selectedProducts.value.map((p) => p.id);
      const res = await axios.get('/api/fb-product-calibration/search-products', {
        params: { outlet_id: form.outlet_id, q: query || '', exclude_ids: excludeIds },
      });
      const fetched = res.data.items || [];
      const selectedIds = new Set(selectedProducts.value.map((p) => p.id));
      productOptions.value = [
        ...selectedProducts.value,
        ...fetched.filter((item) => !selectedIds.has(item.id)),
      ];
    } finally {
      productLoading.value = false;
    }
  }, 300);
}

function removeProduct(product) {
  selectedProducts.value = selectedProducts.value.filter((p) => p.id !== product.id);
}

function submit() {
  if (!form.conductor_id) {
    Swal.fire({ icon: 'warning', title: 'Conducted By wajib dipilih' });
    return;
  }
  if (!selectedProducts.value.length) {
    Swal.fire({ icon: 'warning', title: 'Pilih minimal satu product' });
    return;
  }

  form.products = selectedProducts.value.map((p) => ({
    item_id: p.id,
    item_name: p.item_name,
    category_name: p.category_name || null,
    sub_category_name: p.sub_category_name || null,
  }));

  if (isEdit.value) {
    form.put(route('fb-product-calibration.update', props.record.id));
  } else {
    form.post(route('fb-product-calibration.store'));
  }
}

onMounted(() => {
  initFromRecord();
  if (form.outlet_id) searchProducts('');
});
</script>
