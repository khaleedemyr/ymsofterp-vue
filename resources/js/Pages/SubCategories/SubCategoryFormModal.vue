<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { Switch } from '@headlessui/vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  subCategory: Object, // untuk edit
  categories: Array, // untuk dropdown kategori induk
  regions: Array,
  outlets: Array,
});
const emit = defineEmits(['close', 'success']);

const form = useForm({
  code: '',
  name: '',
  description: '',
  status: 'active',
  show_pos: '1',
  category_id: '',
  availability_type: '',
  selected_regions: [],
  selected_outlets: [],
});

const availabilityType = ref('byRegion'); // 'byRegion' | 'byOutlet'
const selectedRegions = ref([]); // array of region ids
const selectedOutlets = ref([]); // array of outlet ids

watch(() => props.show, (val) => {
  if (val && props.mode === 'edit' && props.subCategory) {
    form.code = props.subCategory.code;
    form.name = props.subCategory.name;
    form.description = props.subCategory.description;
    form.status = props.subCategory.status;
    form.show_pos = String(props.subCategory.show_pos);
    form.category_id = props.subCategory.category_id;

    // Ambil outlet yang sudah terhubung
    const selectedOutletObjs = props.subCategory.outlet_ids
      ? props.outlets.filter(o => props.subCategory.outlet_ids.includes(o.id))
      : [];
    selectedOutlets.value = selectedOutletObjs;

    // Ambil region dari outlet yang sudah terhubung
    const regionIds = [...new Set(selectedOutletObjs.map(o => o.region_id))];
    selectedRegions.value = props.regions.filter(r => regionIds.includes(r.id));

    // Set availabilityType berdasarkan jumlah outlet yang dipilih
    availabilityType.value = selectedOutletObjs.length > 0 ? 'byOutlet' : 'byRegion';
  } else {
    // Reset form
    form.code = '';
    form.name = '';
    form.description = '';
    form.status = 'active';
    form.show_pos = '1';
    form.category_id = '';
    selectedRegions.value = [];
    selectedOutlets.value = [];
    availabilityType.value = 'byRegion';
  }
});

const isSubmitting = ref(false);

async function submit() {
  let outletIds = [];
  if (availabilityType.value === 'byRegion') {
    if (selectedRegions.value.length === 0) {
      Swal.fire('Pilih minimal satu region!');
      isSubmitting.value = false;
      return;
    }
    outletIds = props.outlets
      .filter(o => selectedRegions.value.map(r => r.id).includes(o.region_id))
      .map(o => o.id);
  } else {
    if (selectedOutlets.value.length === 0) {
      Swal.fire('Pilih minimal satu outlet!');
      isSubmitting.value = false;
      return;
    }
    outletIds = selectedOutlets.value.map(o => o.id);
  }
  // Selalu set availability_type sesuai radio
  form.availability_type = availabilityType.value;
  form.outlet_ids = outletIds;
  form.selected_regions = selectedRegions.value.map(r => ({ id: r.id }));
  form.selected_outlets = selectedOutlets.value.map(o => ({ id: o.id }));
  console.log('DEBUG SUBMIT', { availability_type: form.availability_type, show_pos: form.show_pos, selected_regions: form.selected_regions });

  isSubmitting.value = true;
  if (props.mode === 'create') {
    form.post(route('sub-categories.store'), {
      forceFormData: true,
      onSuccess: () => {
        Swal.fire('Berhasil', 'Sub Kategori berhasil ditambahkan!', 'success');
        emit('success');
        emit('close');
      },
      onError: (errors) => {
        console.error('Error creating sub category:', errors);
        isSubmitting.value = false;
      },
      onFinish: () => isSubmitting.value = false,
    });
  } else if (props.mode === 'edit' && props.subCategory) {
    form._method = 'PUT';
    form.post(route('sub-categories.update', props.subCategory.id), {
      forceFormData: true,
      onSuccess: () => {
        Swal.fire('Berhasil', 'Sub Kategori berhasil diupdate!', 'success');
        emit('success');
        emit('close');
      },
      onError: (errors) => {
        console.error('Error updating sub category:', errors);
        isSubmitting.value = false;
      },
      onFinish: () => isSubmitting.value = false,
    });
  }
}

function closeModal() {
  emit('close');
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 transition-all">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto p-0 animate-fade-in">
      <div class="px-8 pt-8 pb-2">
        <div class="flex items-center gap-2 mb-6">
          <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path d="M16 7a4 4 0 01-8 0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 3v4m0 0a4 4 0 01-4 4H4m8-4a4 4 0 014 4h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 class="text-2xl font-bold text-gray-900">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Sub Kategori</h3>
        </div>
        <form @submit.prevent="submit">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Nama</label>
              <input
                v-model="form.name"
                type="text"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                required
              />
              <div v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Deskripsi</label>
              <textarea
                v-model="form.description"
                rows="3"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              ></textarea>
              <div v-if="form.errors.description" class="text-xs text-red-500 mt-1">{{ form.errors.description }}</div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Kategori</label>
              <select
                v-model="form.category_id"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                required
              >
                <option value="">Pilih Kategori</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
              <div v-if="form.errors.category_id" class="text-xs text-red-500 mt-1">{{ form.errors.category_id }}</div>
            </div>
            <div class="flex gap-4">
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select v-model="form.status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
                <div v-if="form.errors.status" class="text-xs text-red-500 mt-1">{{ form.errors.status }}</div>
              </div>
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Show POS</label>
                <Switch
                  v-model="form.show_pos"
                  :class="form.show_pos === '1' ? 'bg-blue-600' : 'bg-gray-200'"
                  class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                  :checked="form.show_pos === '1'"
                  @update:modelValue="val => form.show_pos = val ? '1' : '0'"
                >
                  <span
                    :class="form.show_pos === '1' ? 'translate-x-6' : 'translate-x-1'"
                    class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                  />
                </Switch>
                <span class="ml-2 text-gray-700">{{ form.show_pos === '1' ? 'Yes' : 'No' }}</span>
                <div v-if="form.errors.show_pos" class="text-xs text-red-500 mt-1">{{ form.errors.show_pos }}</div>
              </div>
            </div>

            <!-- Availability Section -->
            <div class="mt-4">
              <label class="block text-sm font-medium text-gray-700 mb-1">Sub Category Availability</label>
              <div class="flex gap-4 mb-2">
                <label class="inline-flex items-center">
                  <input type="radio" value="byRegion" v-model="availabilityType" class="form-radio text-blue-600" />
                  <span class="ml-2">By Region</span>
                </label>
                <label class="inline-flex items-center">
                  <input type="radio" value="byOutlet" v-model="availabilityType" class="form-radio text-blue-600" />
                  <span class="ml-2">By Outlet</span>
                </label>
              </div>
              <div v-if="availabilityType === 'byRegion'">
                <label class="block text-xs text-gray-500 mb-1">Pilih Region</label>
                <Multiselect
                  v-model="selectedRegions"
                  :options="props.regions"
                  :multiple="true"
                  :close-on-select="false"
                  :clear-on-select="false"
                  :preserve-search="true"
                  label="name"
                  track-by="id"
                  placeholder="Pilih Region"
                  class="mb-2"
                />
                <div class="flex flex-wrap gap-2 mt-2">
                  <span v-for="r in selectedRegions" :key="r.id" class="inline-flex items-center bg-blue-100 text-blue-700 px-2 py-1 rounded-full text-xs font-semibold">
                    {{ r.name }}
                    <button type="button" class="ml-1 text-blue-500 hover:text-red-500" @click="selectedRegions = selectedRegions.filter(val => val.id !== r.id)">&times;</button>
                  </span>
                </div>
              </div>
              <div v-if="availabilityType === 'byOutlet'">
                <label class="block text-xs text-gray-500 mb-1">Pilih Outlet</label>
                <Multiselect
                  v-model="selectedOutlets"
                  :options="props.outlets"
                  :multiple="true"
                  :close-on-select="false"
                  :clear-on-select="false"
                  :preserve-search="true"
                  label="nama_outlet"
                  track-by="id"
                  placeholder="Pilih Outlet"
                  class="mb-2"
                />
                <div class="flex flex-wrap gap-2 mt-2">
                  <span v-for="o in selectedOutlets" :key="o.id" class="inline-flex items-center bg-blue-100 text-blue-700 px-2 py-1 rounded-full text-xs font-semibold">
                    {{ o.nama_outlet }}
                    <button type="button" class="ml-1 text-blue-500 hover:text-red-500" @click="selectedOutlets = selectedOutlets.filter(val => val.id !== o.id)">&times;</button>
                  </span>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-8 flex justify-end gap-3">
            <button
              type="button"
              @click="closeModal"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
            >
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(20px);}
  to { opacity: 1; transform: translateY(0);}
}
.animate-fade-in {
  animation: fade-in 0.3s cubic-bezier(.4,0,.2,1);
}
</style> 