<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-book"></i> {{ isEditing ? 'Edit Entri Buku Bank' : 'Tambah Entri Buku Bank' }}
        </h1>
        <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          <i class="fa fa-arrow-left mr-1"></i> Kembali
        </button>
      </div>

      <form @submit.prevent="submitForm" class="bg-white rounded-2xl shadow-2xl p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Bank Account <span class="text-red-500">*</span></label>
            <select 
              v-model="form.bank_account_id" 
              class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
              required
            >
              <option value="">Pilih Bank</option>
              <option v-for="bank in bankAccounts" :key="bank.id" :value="bank.id">
                {{ bank.bank_name }} - {{ bank.account_number }} ({{ bank.outlet_name }})
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal Transaksi <span class="text-red-500">*</span></label>
            <input 
              type="date" 
              v-model="form.transaction_date" 
              class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
              required
            />
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Tipe Transaksi <span class="text-red-500">*</span></label>
            <select 
              v-model="form.transaction_type" 
              class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
              required
            >
              <option value="">Pilih Tipe</option>
              <option value="credit">Credit (Masuk)</option>
              <option value="debit">Debit (Keluar)</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Jumlah <span class="text-red-500">*</span></label>
            <input 
              type="number" 
              v-model="form.amount" 
              step="0.01"
              min="0.01"
              class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
              placeholder="0.00"
              required
            />
          </div>

          <div class="lg:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Keterangan</label>
            <textarea 
              v-model="form.description" 
              rows="3"
              class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
              placeholder="Masukkan keterangan transaksi..."
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Tipe Referensi</label>
            <select 
              v-model="form.reference_type" 
              class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
            >
              <option value="">Tidak Ada</option>
              <option value="outlet_payment">Outlet Payment</option>
              <option value="food_payment">Food Payment</option>
              <option value="non_food_payment">Non Food Payment</option>
              <option value="manual">Manual</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">ID Referensi</label>
            <input 
              type="number" 
              v-model="form.reference_id" 
              class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm"
              placeholder="Masukkan ID referensi jika ada"
            />
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-8">
          <button type="button" @click="goBack" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-semibold shadow-sm">
            Batal
          </button>
          <button type="submit" :disabled="isSubmitting" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-bold shadow-lg hover:bg-blue-700 transition-all disabled:bg-gray-400 disabled:cursor-not-allowed">
            {{ isSubmitting ? 'Menyimpan...' : (isEditing ? 'Update' : 'Simpan') }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  bankBook: Object,
  bankAccounts: Array,
  mode: String,
});

const isEditing = computed(() => props.mode === 'edit');
const isSubmitting = ref(false);

const form = ref({
  bank_account_id: '',
  transaction_date: new Date().toISOString().split('T')[0],
  transaction_type: '',
  amount: '',
  description: '',
  reference_type: '',
  reference_id: '',
});

onMounted(() => {
  if (props.bankBook) {
    form.value = {
      bank_account_id: props.bankBook.bank_account_id || '',
      transaction_date: props.bankBook.transaction_date ? new Date(props.bankBook.transaction_date).toISOString().split('T')[0] : '',
      transaction_type: props.bankBook.transaction_type || '',
      amount: props.bankBook.amount || '',
      description: props.bankBook.description || '',
      reference_type: props.bankBook.reference_type || '',
      reference_id: props.bankBook.reference_id || '',
    };
  }
});

function goBack() {
  router.visit('/bank-books');
}

async function submitForm() {
  const confirm = await Swal.fire({
    title: isEditing.value ? 'Update Entri?' : 'Simpan Entri?',
    text: isEditing.value ? 'Yakin ingin update entri buku bank ini?' : 'Yakin ingin menyimpan entri buku bank ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: isEditing.value ? 'Update' : 'Simpan',
    cancelButtonText: 'Batal',
  });

  if (!confirm.isConfirmed) {
    return;
  }

  isSubmitting.value = true;

  Swal.fire({
    title: isEditing.value ? 'Mengupdate...' : 'Menyimpan...',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  const onSuccess = () => {
    Swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: isEditing.value ? 'Entri berhasil diupdate' : 'Entri berhasil disimpan',
      timer: 1500,
      showConfirmButton: false,
    });
    goBack();
  };

  const onError = (err) => {
    let msg = 'Gagal menyimpan data';
    if (err && err.response && err.response.data && err.response.data.message) {
      msg = err.response.data.message;
    } else if (err && err.message) {
      msg = err.message;
    }
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: msg,
    });
    isSubmitting.value = false;
  };

  try {
    if (isEditing.value) {
      await router.put(`/bank-books/${props.bankBook.id}`, form.value, { onSuccess, onError });
    } else {
      await router.post('/bank-books', form.value, { onSuccess, onError });
    }
  } catch (e) {
    onError(e);
  }
}
</script>
