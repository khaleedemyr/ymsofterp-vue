<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <div class="mb-6">
        <Link :href="route('payment-types.index')" class="text-blue-600 hover:text-blue-800 mb-4 inline-block">
          <i class="fa fa-arrow-left mr-1"></i> Kembali ke Daftar
        </Link>
        <h1 class="text-2xl font-bold text-blue-700">Manage Bank Accounts - {{ paymentType.name }}</h1>
        <p class="text-gray-600 mt-1">Kelola bank accounts untuk payment type ini per outlet</p>
      </div>

      <div class="bg-white rounded-2xl shadow-xl p-8 space-y-6">
        <!-- Form Tambah Bank (Batch Input) -->
        <div class="border-b pb-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Tambah Bank Account</h2>
          <div class="space-y-4">
            <!-- List Bank yang akan ditambahkan -->
            <div v-if="pendingBanks.length > 0" class="mb-4 space-y-2">
              <div v-for="(item, index) in pendingBanks" :key="index" class="flex items-center justify-between p-3 bg-blue-50 rounded-lg border border-blue-200">
                <div class="flex-1">
                  <div class="font-semibold text-gray-800">{{ item.bank.bank_name }}</div>
                  <div class="text-sm text-gray-600">{{ item.bank.account_number }} - {{ item.bank.account_name }}</div>
                  <div class="text-xs text-gray-500 mt-1">
                    <i class="fa fa-store mr-1"></i> {{ item.outlet_name }}
                  </div>
                </div>
                <button 
                  @click="removePendingBank(index)" 
                  class="text-red-500 hover:text-red-700 px-2 py-1"
                >
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </div>

            <!-- Form Input Baru -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
                <select v-model="newBank.outlet_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                  <option :value="null">Head Office (Semua Outlet)</option>
                  <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                    {{ outlet.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bank Account</label>
                <multiselect 
                  v-model="newBank.bank" 
                  :options="getAvailableBanksForOutlet(newBank.outlet_id)" 
                  label="bank_name" 
                  track-by="id" 
                  placeholder="Pilih Bank"
                  :custom-label="formatBankLabel"
                  :searchable="true"
                  @select="addToPending"
                >
                  <template slot="option" slot-scope="props">
                    <div>
                      <div class="font-semibold">{{ props.option.bank_name }}</div>
                      <div class="text-sm text-gray-600">{{ props.option.account_number }} - {{ props.option.account_name }}</div>
                      <div class="text-xs text-gray-500">{{ props.option.outlet_name }}</div>
                    </div>
                  </template>
                </multiselect>
              </div>
            </div>
            <button 
              @click="saveAllBanks" 
              :disabled="pendingBanks.length === 0 || loading" 
              class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 disabled:opacity-50 font-semibold"
            >
              <i v-if="loading" class="fa fa-spinner fa-spin mr-1"></i>
              <i v-else class="fa fa-save mr-1"></i>
              Simpan Semua ({{ pendingBanks.length }})
            </button>
          </div>
        </div>

        <!-- Daftar Bank yang Sudah Ditambahkan -->
        <div>
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Bank Accounts Terdaftar</h2>
          <div v-if="bankAccounts.length === 0" class="text-center py-8 text-gray-400">
            Belum ada bank account yang ditambahkan
          </div>
          <div v-else class="space-y-3">
            <div v-for="(bank, index) in bankAccounts" :key="index" class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border">
              <div class="flex-1">
                <div class="font-semibold text-gray-800">{{ bank.bank_name }}</div>
                <div class="text-sm text-gray-600">{{ bank.account_number }} - {{ bank.account_name }}</div>
                <div class="text-xs text-gray-500 mt-1">
                  <i class="fa fa-store mr-1"></i> {{ bank.outlet_name }}
                </div>
              </div>
              <button 
                @click="removeBank(bank.id, bank.outlet_id)" 
                :disabled="loadingDelete === `${bank.id}_${bank.outlet_id}`"
                class="text-red-500 hover:text-red-700 px-3 py-2 rounded hover:bg-red-50 disabled:opacity-50"
              >
                <i v-if="loadingDelete === `${bank.id}_${bank.outlet_id}`" class="fa fa-spinner fa-spin"></i>
                <i v-else class="fa fa-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import Swal from 'sweetalert2';

const props = defineProps({
  paymentType: Object,
  outlets: Array,
  banks: Array,
  bankAccounts: Array
});

const loading = ref(false);
const loadingDelete = ref(null);
const newBank = ref({
  outlet_id: null,
  bank: null
});
const pendingBanks = ref([]);

function formatBankLabel(option) {
  return `${option.bank_name} - ${option.account_number} (${option.outlet_name})`;
}

function getAvailableBanksForOutlet(outletId) {
  // Filter banks that match the outlet or are Head Office (outlet_id = null)
  // Also exclude banks that are already added for this outlet (both saved and pending)
  const existingForOutlet = props.bankAccounts
    .filter(ba => ba.outlet_id === outletId)
    .map(ba => ba.id);
  
  const pendingForOutlet = pendingBanks.value
    .filter(pb => pb.outlet_id === outletId)
    .map(pb => pb.bank.id);
  
  return props.banks?.filter(b => {
    // Exclude already added banks for this outlet (saved)
    if (existingForOutlet.includes(b.id)) {
      return false;
    }
    
    // Exclude banks in pending list for this outlet
    if (pendingForOutlet.includes(b.id)) {
      return false;
    }
    
    if (outletId === null) {
      // For Head Office, show all banks that aren't already added (saved or pending)
      const isInSaved = props.bankAccounts.some(ba => ba.outlet_id === null && ba.id === b.id);
      const isInPending = pendingBanks.value.some(pb => pb.outlet_id === null && pb.bank.id === b.id);
      return !isInSaved && !isInPending;
    } else {
      // For specific outlet, show banks for that outlet or Head Office
      return (b.outlet_id === outletId || b.outlet_id === null) && 
             !props.bankAccounts.some(ba => ba.outlet_id === outletId && ba.id === b.id) &&
             !pendingBanks.value.some(pb => pb.outlet_id === outletId && pb.bank.id === b.id);
    }
  }) || [];
}

function addToPending(bank) {
  if (!bank) return;
  
  // Check if already in pending
  const exists = pendingBanks.value.some(pb => 
    pb.bank.id === bank.id && pb.outlet_id === newBank.value.outlet_id
  );
  
  if (exists) {
    Swal.fire('Info', 'Bank account ini sudah ada di daftar', 'info');
    newBank.value.bank = null;
    return;
  }
  
  // Check if already in saved banks
  const alreadySaved = props.bankAccounts.some(ba => 
    ba.id === bank.id && ba.outlet_id === newBank.value.outlet_id
  );
  
  if (alreadySaved) {
    Swal.fire('Info', 'Bank account ini sudah terdaftar', 'info');
    newBank.value.bank = null;
    return;
  }
  
  const outletName = newBank.value.outlet_id 
    ? props.outlets.find(o => o.id === newBank.value.outlet_id)?.name || 'Unknown'
    : 'Head Office (Semua Outlet)';
  
  pendingBanks.value.push({
    bank: bank,
    outlet_id: newBank.value.outlet_id,
    outlet_name: outletName
  });
  
  // Reset form
  newBank.value.bank = null;
}

function removePendingBank(index) {
  pendingBanks.value.splice(index, 1);
}

async function saveAllBanks() {
  if (pendingBanks.value.length === 0) {
    Swal.fire('Info', 'Tidak ada bank account yang akan disimpan', 'info');
    return;
  }

  loading.value = true;
  
  // Prepare data for batch insert
  const banksData = pendingBanks.value.map(pb => ({
    bank_account_id: pb.bank.id,
    outlet_id: pb.outlet_id
  }));

  router.post(route('payment-types.store-bank', props.paymentType.id), {
    banks: banksData
  }, {
    onSuccess: () => {
      loading.value = false;
      pendingBanks.value = [];
      newBank.value = { outlet_id: null, bank: null };
      Swal.fire('Sukses', `${banksData.length} bank account berhasil ditambahkan`, 'success');
    },
    onError: (errors) => {
      loading.value = false;
      Swal.fire('Error', errors.message || 'Gagal menambahkan bank account', 'error');
    }
  });
}

async function removeBank(bankId, outletId) {
  const confirm = await Swal.fire({
    title: 'Hapus Bank Account?',
    text: 'Bank account akan dihapus dari payment type ini',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal'
  });

  if (!confirm.isConfirmed) return;

  loadingDelete.value = `${bankId}_${outletId}`;
  router.post(route('payment-types.delete-bank', props.paymentType.id), {
    bank_account_id: bankId,
    outlet_id: outletId
  }, {
    onSuccess: () => {
      loadingDelete.value = null;
      Swal.fire('Sukses', 'Bank account berhasil dihapus', 'success');
    },
    onError: () => {
      loadingDelete.value = null;
      Swal.fire('Error', 'Gagal menghapus bank account', 'error');
    }
  });
}
</script>
