<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold text-blue-700 mb-6">{{ isEdit ? 'Edit Jenis Pembayaran' : 'Tambah Jenis Pembayaran' }}</h1>
      <form @submit.prevent="submit" class="space-y-5 bg-white rounded-2xl shadow-xl p-8">
        <div>
          <label class="block text-sm font-medium text-gray-700">Nama Jenis Pembayaran</label>
          <input v-model="form.name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Kode</label>
          <input v-model="form.code" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 bg-gray-100" readonly />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Pembayaran</label>
          <label class="inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="form.is_bank" class="form-checkbox h-5 w-5 text-blue-600">
            <span class="ml-2">Pembayaran Bank</span>
          </label>
        </div>
        <div v-if="form.is_bank" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Bank</label>
            <input v-model="form.bank_name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" required maxlength="100" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Outlet Pembayaran</label>
          <div class="flex gap-6 items-center mb-2">
            <label class="inline-flex items-center">
              <input type="radio" value="region" v-model="outletType" class="form-radio text-blue-600" />
              <span class="ml-2">By Region</span>
            </label>
            <label class="inline-flex items-center">
              <input type="radio" value="outlet" v-model="outletType" class="form-radio text-blue-600" />
              <span class="ml-2">By Outlet</span>
            </label>
          </div>
          <multiselect v-if="outletType === 'region'" v-model="form.regions" :options="regions" :multiple="true" label="name" track-by="id" placeholder="Pilih Region" />
          <multiselect v-if="outletType === 'outlet'" v-model="form.outlets" :options="outlets" :multiple="true" label="name" track-by="id" placeholder="Pilih Outlet" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Deskripsi</label>
          <textarea v-model="form.description" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Status</label>
          <select v-model="form.status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
          </select>
        </div>
        <div class="flex justify-end gap-4">
          <Link :href="route('payment-types.index')" class="px-4 py-2 rounded bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200">Batal</Link>
          <button type="submit" :disabled="loading" class="px-4 py-2 rounded bg-blue-500 text-white font-semibold hover:bg-blue-600 disabled:opacity-50">
            <i v-if="loading" class="fa fa-spinner fa-spin mr-1"></i>
            {{ isEdit ? 'Update' : 'Simpan' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import Swal from 'sweetalert2';

const props = defineProps({
  paymentType: Object,
  outlets: Array,
  regions: Array,
  isEdit: Boolean
});

const loading = ref(false);
const outletType = ref('outlet');

const form = ref({
  name: props.paymentType?.name || '',
  code: props.paymentType?.code || '',
  is_bank: props.paymentType?.is_bank || false,
  bank_name: props.paymentType?.bank_name || '',
  description: props.paymentType?.description || '',
  status: props.paymentType?.status || 'active',
  outlets: props.paymentType?.outlets || [],
  regions: props.paymentType?.regions || []
});


// Set initial outlet type based on existing data
watch(() => props.paymentType, (newVal) => {
  if (newVal) {
    outletType.value = newVal.regions?.length ? 'region' : 'outlet';
  }
}, { immediate: true });

// Auto-generate code from name (slugify, uppercase, dash/underscore allowed)
watch(() => form.value.name, (val) => {
  if (!props.isEdit) {
    form.value.code = val
      .toString()
      .toUpperCase()
      .replace(/\s+/g, '_')
      .replace(/[^A-Z0-9_]/g, '')
      .substring(0, 50);
  }
});

async function submit() {
  const confirm = await Swal.fire({
    title: 'Simpan Jenis Pembayaran?',
    text: 'Apakah Anda yakin ingin menyimpan data jenis pembayaran ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Simpan',
    cancelButtonText: 'Batal'
  });
  if (!confirm.isConfirmed) return;

  loading.value = true;
  const formData = {
    ...form.value,
    outlet_type: outletType.value,
    outlets: (form.value.outlets || []).map((item) => item?.id ?? item).filter((id) => id !== null && id !== undefined && id !== ''),
    regions: (form.value.regions || []).map((item) => item?.id ?? item).filter((id) => id !== null && id !== undefined && id !== '')
  };

  if (outletType.value === 'region') {
    formData.outlets = [];
  } else {
    formData.regions = [];
  }


  if (props.isEdit) {
    router.put(route('payment-types.update', props.paymentType.id), formData, {
      onSuccess: () => {
        loading.value = false;
        Swal.fire('Sukses', 'Jenis pembayaran berhasil diperbarui!', 'success');
      },
      onError: () => {
        loading.value = false;
        Swal.fire('Error', 'Gagal memperbarui jenis pembayaran', 'error');
      }
    });
  } else {
    router.post(route('payment-types.store'), formData, {
      onSuccess: () => {
        loading.value = false;
        Swal.fire('Sukses', 'Jenis pembayaran berhasil ditambahkan!', 'success');
      },
      onError: () => {
        loading.value = false;
        Swal.fire('Error', 'Gagal menambahkan jenis pembayaran', 'error');
      }
    });
  }
}
</script> 