<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { Switch } from '@headlessui/vue'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  category: Object, // untuk edit
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
  outlet_ids: [],
});

const availabilityType = ref('byRegion'); // 'byRegion' | 'byOutlet'
const selectedRegions = ref([]); // array of region ids
const selectedOutlets = ref([]); // array of outlet ids

watch(() => props.show, (val) => {
  if (val && props.mode === 'edit' && props.category) {
    form.code = props.category.code;
    form.name = props.category.name;
    form.description = props.category.description;
    form.status = props.category.status;
    form.show_pos = String(props.category.show_pos);

    // Ambil outlet yang sudah terhubung (selalu isi selectedOutlets)
    const selectedOutletObjs = props.category.outlet_ids
      ? props.outlets.filter(o => props.category.outlet_ids.map(String).includes(String(o.id)))
      : [];
    selectedOutlets.value = selectedOutletObjs;

    // Ambil region dari outlet yang sudah terhubung
    const regionIds = [...new Set(selectedOutletObjs.map(o => o.region_id))];
    selectedRegions.value = props.regions.filter(r => regionIds.includes(r.id));

    // Set availabilityType berdasarkan jumlah outlet yang dipilih
    availabilityType.value = selectedOutletObjs.length > 0 ? 'byOutlet' : 'byRegion';
  } else if (val && props.mode === 'create') {
    form.code = '';
    form.name = '';
    form.description = '';
    form.status = 'active';
    form.show_pos = '1';
    selectedRegions.value = [];
    selectedOutlets.value = [];
    availabilityType.value = 'byRegion';
  }
});

const isSubmitting = ref(false);

async function submit() {
  console.log('SUBMIT TERPANGGIL', { selectedRegions: selectedRegions.value, availabilityType: availabilityType.value });
  let outletIds = [];
  if (form.show_pos === '1') {
    if (availabilityType.value === 'byRegion') {
      outletIds = props.outlets
        .filter(o => selectedRegions.value.map(r => r.id).includes(o.region_id))
        .map(o => o.id);
    } else {
      outletIds = selectedOutlets.value.map(o => o.id);
    }
  }
  form.outlet_ids = outletIds;
  console.log('submit form:', { ...form });
  isSubmitting.value = true;
  if (props.mode === 'create') {
    form.post(route('categories.store'), {
      forceFormData: true,
      onSuccess: () => {
        Swal.fire('Berhasil', 'Kategori berhasil ditambahkan!', 'success');
        emit('success');
        emit('close');
      },
      onError: (errors) => {
        console.error('Error creating category:', errors);
        isSubmitting.value = false;
      },
      onFinish: () => isSubmitting.value = false,
    });
  } else if (props.mode === 'edit' && props.category) {
    form._method = 'PUT';
    form.post(route('categories.update', props.category.id), {
      forceFormData: true,
      onSuccess: () => {
        Swal.fire('Berhasil', 'Kategori berhasil diupdate!', 'success');
        emit('success');
        emit('close');
      },
      onError: (errors) => {
        console.error('Error updating category:', errors);
        isSubmitting.value = false;
      },
      onFinish: () => isSubmitting.value = false,
    });
  }
}

function closeModal() {
  emit('close');
}

// Custom label untuk outlet (pakai name atau nama_outlet)
function outletLabel(option) {
  return option.name || option.nama_outlet || '-';
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
          <h3 class="text-2xl font-bold text-gray-900">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Kategori</h3>
        </div>
        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700">Kode</label>
            <input v-model="form.code" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="50" />
            <div v-if="form.errors.code" class="text-xs text-red-500 mt-1">{{ form.errors.code }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama</label>
            <input v-model="form.name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
            <div v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Deskripsi</label>
            <textarea v-model="form.description" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
            <div v-if="form.errors.description" class="text-xs text-red-500 mt-1">{{ form.errors.description }}</div>
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
          <div v-if="form.show_pos === '1'" class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Category Availability</label>
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
                :custom-label="outletLabel"
                track-by="id"
                placeholder="Pilih Outlet"
                class="mb-2"
              />
            </div>
            <!-- List outlet terpilih, selalu muncul jika ada -->
            <div v-if="selectedOutlets.length" class="flex flex-wrap gap-2 mt-2">
              <span v-for="o in selectedOutlets" :key="o.id" class="inline-flex items-center bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-semibold">
                {{ o.name || o.nama_outlet }}
              </span>
            </div>
          </div>
        </form>
      </div>
      <div class="bg-gray-50 px-6 py-4 flex justify-end rounded-b-2xl">
        <button type="button" @click="closeModal" class="inline-flex items-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition sm:w-auto sm:text-sm mr-2">
          Batal
        </button>
        <button type="button" @click="submit" :disabled="isSubmitting" class="inline-flex items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
          <svg v-if="isSubmitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isSubmitting ? (mode === 'edit' ? 'Menyimpan...' : 'Menambah...') : (mode === 'edit' ? 'Simpan' : 'Tambah') }}
        </button>
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