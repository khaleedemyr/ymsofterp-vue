<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Multiselect from '@vueform/multiselect';
import '@vueform/multiselect/themes/default.css';
import VueTimepicker from 'vue3-timepicker';
import 'vue3-timepicker/dist/VueTimepicker.css';

const props = defineProps({
  editData: Object,
  regions: Array,
  outlets: Array,
  warehouseDivisions: Array,
  foModes: Array
});

const isEdit = computed(() => !!props.editData);

const days = [
  'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
];

const form = useForm({
  fo_mode: props.editData?.fo_mode || '',
  warehouse_division_ids: props.editData?.warehouse_divisions?.map(wd => wd.id) || [],
  day: props.editData?.day || '',
  open_time: props.editData?.open_time || '',
  close_time: props.editData?.close_time || '',
  region_ids: props.editData?.regions?.map(r => r.id) || [],
  outlet_ids: props.editData?.outlets?.map(o => o.id_outlet) || []
});

const regionSearch = ref('');
const filteredRegions = computed(() => {
  if (!regionSearch.value) return props.regions;
  return props.regions.filter(r =>
    r.name.toLowerCase().includes(regionSearch.value.toLowerCase())
  );
});

const outletSearch = ref('');
const filteredOutlets = computed(() => {
  if (!outletSearch.value) return props.outlets;
  return props.outlets.filter(o =>
    o.nama_outlet.toLowerCase().includes(outletSearch.value.toLowerCase())
  );
});

const warehouseDivisionSearch = ref('');
const filteredWarehouseDivisions = computed(() => {
  if (!warehouseDivisionSearch.value) return props.warehouseDivisions;
  return props.warehouseDivisions.filter(wd =>
    wd.name.toLowerCase().includes(warehouseDivisionSearch.value.toLowerCase())
  );
});

function onSubmit() {
  if (isEdit.value) {
    form.put(`/fo-schedules/${props.editData.id}`, {
      onSuccess: () => router.visit('/fo-schedules')
    });
  } else {
    form.post('/fo-schedules', {
      onSuccess: () => router.visit('/fo-schedules')
    });
  }
}

function goBack() {
  router.visit('/fo-schedules');
}
</script>

<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="goBack" class="text-blue-500 hover:underline">
          <i class="fa fa-arrow-left"></i> Kembali
        </button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-calendar-days text-blue-500"></i>
          <span v-if="isEdit">Edit Jadwal RO</span>
          <span v-else>Buat Jadwal RO</span>
        </h1>
      </div>

      <form @submit.prevent="onSubmit" class="bg-white rounded-2xl shadow-2xl p-6 space-y-6">
        <!-- Mode FO -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mode RO</label>
          <select v-model="form.fo_mode" 
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
            <option value="">Pilih Mode FO</option>
            <option v-for="mode in foModes" :key="mode" :value="mode">{{ mode }}</option>
          </select>
          <div v-if="form.errors.fo_mode" class="text-xs text-red-500 mt-1">{{ form.errors.fo_mode }}</div>
        </div>

        <!-- Warehouse Division -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse Division</label>
          <input
            v-model="warehouseDivisionSearch"
            type="text"
            placeholder="Cari warehouse division..."
            class="input w-full mb-2"
          />
          <div class="border rounded mb-2 h-32 overflow-y-auto">
            <div
              v-for="wd in filteredWarehouseDivisions"
              :key="wd.id"
              class="flex items-center px-2 py-1 hover:bg-blue-50 cursor-pointer"
              @click="!form.warehouse_division_ids.includes(wd.id) && form.warehouse_division_ids.push(wd.id)"
            >
              <input type="checkbox" :checked="form.warehouse_division_ids.includes(wd.id)" class="mr-2" />
              <span>{{ wd.name }}</span>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 mt-2">
            <span
              v-for="wdId in form.warehouse_division_ids"
              :key="wdId"
              class="bg-blue-100 text-blue-700 px-2 py-1 rounded flex items-center"
            >
              {{ warehouseDivisions.find(wd => wd.id === wdId)?.name || wdId }}
              <button
                type="button"
                class="ml-1 text-red-500 hover:text-red-700"
                @click="form.warehouse_division_ids = form.warehouse_division_ids.filter(id => id !== wdId)"
                title="Hapus"
              >×</button>
            </span>
          </div>
          <div v-if="form.errors.warehouse_division_ids" class="text-xs text-red-500 mt-1">
            {{ form.errors.warehouse_division_ids }}
          </div>
        </div>

        <!-- Regions -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Regions</label>
          <input
            v-model="regionSearch"
            type="text"
            placeholder="Cari region..."
            class="input w-full mb-2"
          />
          <div class="border rounded mb-2 h-32 overflow-y-auto">
            <div
              v-for="r in filteredRegions"
              :key="r.id"
              class="flex items-center px-2 py-1 hover:bg-blue-50 cursor-pointer"
              @click="!form.region_ids.includes(r.id) && form.region_ids.push(r.id)"
            >
              <input type="checkbox" :checked="form.region_ids.includes(r.id)" class="mr-2" />
              <span>{{ r.name }}</span>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 mt-2">
            <span
              v-for="rid in form.region_ids"
              :key="rid"
              class="bg-blue-100 text-blue-700 px-2 py-1 rounded flex items-center"
            >
              {{ regions.find(r => r.id === rid)?.name || rid }}
              <button
                type="button"
                class="ml-1 text-red-500 hover:text-red-700"
                @click="form.region_ids = form.region_ids.filter(id => id !== rid)"
                title="Hapus"
              >×</button>
            </span>
          </div>
          <div v-if="form.errors.region_ids" class="text-xs text-red-500 mt-1">{{ form.errors.region_ids }}</div>
        </div>

        <!-- Outlets -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Outlets</label>
          <input
            v-model="outletSearch"
            type="text"
            placeholder="Cari outlet..."
            class="input w-full mb-2"
          />
          <div class="border rounded mb-2 h-32 overflow-y-auto">
            <div
              v-for="o in filteredOutlets"
              :key="o.id_outlet"
              class="flex items-center px-2 py-1 hover:bg-blue-50 cursor-pointer"
              @click="!form.outlet_ids.includes(o.id_outlet) && form.outlet_ids.push(o.id_outlet)"
            >
              <input type="checkbox" :checked="form.outlet_ids.includes(o.id_outlet)" class="mr-2" />
              <span>{{ o.nama_outlet }}</span>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 mt-2">
            <span
              v-for="oid in form.outlet_ids"
              :key="oid"
              class="bg-green-100 text-green-700 px-2 py-1 rounded flex items-center"
            >
              {{ outlets.find(o => o.id_outlet == oid)?.nama_outlet || oid }}
              <button
                type="button"
                class="ml-1 text-red-500 hover:text-red-700"
                @click="form.outlet_ids = form.outlet_ids.filter(id => id !== oid)"
                title="Hapus"
              >×</button>
            </span>
          </div>
          <div v-if="form.errors.outlet_ids" class="text-xs text-red-500 mt-1">{{ form.errors.outlet_ids }}</div>
        </div>

        <!-- Day -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Hari</label>
          <select v-model="form.day"
            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
            <option value="">Pilih Hari</option>
            <option v-for="day in days" :key="day" :value="day">{{ day }}</option>
          </select>
          <div v-if="form.errors.day" class="text-xs text-red-500 mt-1">{{ form.errors.day }}</div>
        </div>

        <!-- Time -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Jam Buka</label>
            <VueTimepicker
              v-model="form.open_time"
              format="HH:mm"
              :minute-interval="1"
              :is24="true"
              placeholder="Pilih Jam Buka"
              :clearable="true"
            />
            <small class="text-gray-400">Format 24 jam (contoh: 14:30)</small>
            <div v-if="form.errors.open_time" class="text-xs text-red-500 mt-1">{{ form.errors.open_time }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Jam Tutup</label>
            <VueTimepicker
              v-model="form.close_time"
              format="HH:mm"
              :minute-interval="1"
              :is24="true"
              placeholder="Pilih Jam Tutup"
              :clearable="true"
            />
            <small class="text-gray-400">Format 24 jam (contoh: 18:00)</small>
            <div v-if="form.errors.close_time" class="text-xs text-red-500 mt-1">{{ form.errors.close_time }}</div>
          </div>
        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-2 pt-4">
          <button type="button" @click="goBack"
            class="px-4 py-2 rounded bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">
            Batal
          </button>
          <button type="submit" :disabled="form.processing"
            class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-50">
            Simpan
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template> 