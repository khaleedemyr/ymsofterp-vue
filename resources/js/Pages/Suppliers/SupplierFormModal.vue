<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String, // 'create' | 'edit'
  supplier: Object, // untuk edit
});
const emit = defineEmits(['close', 'success']);

const form = useForm({
  code: '',
  name: '',
  contact_person: '',
  phone: '',
  email: '',
  address: '',
  city: '',
  province: '',
  postal_code: '',
  npwp: '',
  bank_name: '',
  bank_account_number: '',
  bank_account_name: '',
  payment_term: '',
  payment_days: '',
  status: 'active',
});

watch(() => props.show, (val) => {
  if (val && props.mode === 'edit' && props.supplier) {
    Object.assign(form, {
      code: props.supplier.code,
      name: props.supplier.name,
      contact_person: props.supplier.contact_person,
      phone: props.supplier.phone,
      email: props.supplier.email,
      address: props.supplier.address,
      city: props.supplier.city,
      province: props.supplier.province,
      postal_code: props.supplier.postal_code,
      npwp: props.supplier.npwp,
      bank_name: props.supplier.bank_name,
      bank_account_number: props.supplier.bank_account_number,
      bank_account_name: props.supplier.bank_account_name,
      payment_term: props.supplier.payment_term,
      payment_days: props.supplier.payment_days,
      status: props.supplier.status,
    });
  } else if (val && props.mode === 'create') {
    Object.assign(form, {
      code: '',
      name: '',
      contact_person: '',
      phone: '',
      email: '',
      address: '',
      city: '',
      province: '',
      postal_code: '',
      npwp: '',
      bank_name: '',
      bank_account_number: '',
      bank_account_name: '',
      payment_term: '',
      payment_days: '',
      status: 'active',
    });
  }
});

const isSubmitting = ref(false);

async function submit() {
  isSubmitting.value = true;
  if (props.mode === 'create') {
    form.post(route('suppliers.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Supplier berhasil ditambahkan!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
      onFinish: () => isSubmitting.value = false,
    });
  } else if (props.mode === 'edit' && props.supplier) {
    form.patch(route('suppliers.update', props.supplier.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Supplier berhasil diupdate!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
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
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-auto p-0 animate-fade-in">
      <div class="px-8 pt-8 pb-2">
        <div class="flex items-center gap-2 mb-6">
          <svg class="w-7 h-7 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path d="M16 7a4 4 0 01-8 0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 3v4m0 0a4 4 0 01-4 4H4m8-4a4 4 0 014 4h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <h3 class="text-2xl font-bold text-gray-900">{{ mode === 'edit' ? 'Edit' : 'Tambah' }} Supplier</h3>
        </div>
        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
            <label class="block text-sm font-medium text-gray-700">Contact Person</label>
            <input v-model="form.contact_person" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="100" />
            <div v-if="form.errors.contact_person" class="text-xs text-red-500 mt-1">{{ form.errors.contact_person }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Phone</label>
            <input v-model="form.phone" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="30" />
            <div v-if="form.errors.phone" class="text-xs text-red-500 mt-1">{{ form.errors.phone }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Email</label>
            <input v-model="form.email" type="email" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="100" />
            <div v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</div>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Alamat</label>
            <textarea v-model="form.address" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
            <div v-if="form.errors.address" class="text-xs text-red-500 mt-1">{{ form.errors.address }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Kota</label>
            <input v-model="form.city" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="100" />
            <div v-if="form.errors.city" class="text-xs text-red-500 mt-1">{{ form.errors.city }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Provinsi</label>
            <input v-model="form.province" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="100" />
            <div v-if="form.errors.province" class="text-xs text-red-500 mt-1">{{ form.errors.province }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Kode Pos</label>
            <input v-model="form.postal_code" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="20" />
            <div v-if="form.errors.postal_code" class="text-xs text-red-500 mt-1">{{ form.errors.postal_code }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">NPWP</label>
            <input v-model="form.npwp" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="30" />
            <div v-if="form.errors.npwp" class="text-xs text-red-500 mt-1">{{ form.errors.npwp }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Bank</label>
            <input v-model="form.bank_name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="100" />
            <div v-if="form.errors.bank_name" class="text-xs text-red-500 mt-1">{{ form.errors.bank_name }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">No. Rekening</label>
            <input v-model="form.bank_account_number" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="50" />
            <div v-if="form.errors.bank_account_number" class="text-xs text-red-500 mt-1">{{ form.errors.bank_account_number }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Nama Rekening</label>
            <input v-model="form.bank_account_name" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="100" />
            <div v-if="form.errors.bank_account_name" class="text-xs text-red-500 mt-1">{{ form.errors.bank_account_name }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Term Pembayaran</label>
            <input v-model="form.payment_term" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" maxlength="50" />
            <div v-if="form.errors.payment_term" class="text-xs text-red-500 mt-1">{{ form.errors.payment_term }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Hari Pembayaran</label>
            <input v-model="form.payment_days" type="number" min="0" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500" />
            <div v-if="form.errors.payment_days" class="text-xs text-red-500 mt-1">{{ form.errors.payment_days }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select v-model="form.status" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <div v-if="form.errors.status" class="text-xs text-red-500 mt-1">{{ form.errors.status }}</div>
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