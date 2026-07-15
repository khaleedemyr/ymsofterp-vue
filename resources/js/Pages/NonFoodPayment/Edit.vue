<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-pencil-alt"></i> Edit Non Food Payment
        </h1>
        <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          <i class="fa fa-arrow-left mr-1"></i> Kembali
        </button>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Payment Information -->
        <div class="bg-white rounded-2xl shadow-2xl p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Payment</h2>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Number</label>
            <p class="text-lg font-semibold text-gray-900">{{ payment.payment_number }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Supplier *</label>
              <select 
                v-model="form.supplier_id" 
                required 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              >
                <option value="">Pilih Supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                  {{ supplier.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Akun Pembayaran (COA)</label>
              <multiselect
                v-model="selectedCoa"
                :options="coaList"
                :searchable="true"
                :close-on-select="true"
                :show-labels="false"
                placeholder="Pilih Akun (ketik untuk mencari)"
                label="display_name"
                track-by="id"
                @select="onCoaSelect"
                @remove="onCoaRemove"
                class="w-full"
              >
                <template #noOptions>
                  <span>Tidak ada akun ditemukan</span>
                </template>
              </multiselect>
              <p class="mt-1 text-xs text-gray-500">Opsional: pilih akun untuk jurnal pembayaran</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Amount * 
                <span class="text-xs font-normal text-gray-500">
                  {{ hasOutletPayments ? '(Otomatis dari total outlet)' : '(Dapat diubah)' }}
                </span>
              </label>
              <input 
                type="number" 
                v-model="form.amount" 
                step="0.01"
                min="0"
                required
                :readonly="hasOutletPayments"
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                :class="{ 'bg-gray-100': hasOutletPayments }"
                placeholder="0.00"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
              <select v-model="form.payment_method" required class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
                <option value="">Pilih Payment Method</option>
                <option value="cash">Cash</option>
                <option value="transfer">Transfer</option>
                <option value="check">Check</option>
              </select>
            </div>

            <!-- Bank utama hanya jika tidak ada multi outlet -->
            <div
              v-if="!hasOutletPayments && (form.payment_method === 'transfer' || form.payment_method === 'check')"
              class="md:col-span-2"
            >
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Pilih Bank <span class="text-red-500">*</span>
              </label>
              <multiselect
                v-model="selectedBank"
                :options="banks"
                :searchable="true"
                :close-on-select="true"
                :show-labels="false"
                placeholder="Cari dan pilih bank..."
                label="display_name"
                track-by="id"
                @select="onBankSelect"
                @remove="onBankRemove"
                class="w-full"
                required
              >
                <template #noOptions>
                  <span>Tidak ada bank ditemukan</span>
                </template>
                <template #noResult>
                  <span>Tidak ada bank ditemukan</span>
                </template>
              </multiselect>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Payment Date *</label>
              <input 
                type="date" 
                v-model="form.payment_date" 
                required 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
              <input 
                type="date" 
                v-model="form.due_date" 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>
              <input 
                type="text" 
                v-model="form.reference_number" 
                class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                placeholder="Nomor referensi"
              />
            </div>
          </div>

          <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea 
              v-model="form.description" 
              rows="3"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              placeholder="Deskripsi payment"
            ></textarea>
          </div>

          <div class="mt-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
            <textarea 
              v-model="form.notes" 
              rows="3"
              class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              placeholder="Catatan tambahan"
            ></textarea>
          </div>
        </div>

        <!-- Payment Per Outlet (multi pembayaran) -->
        <div v-if="hasOutletPayments" class="bg-white rounded-2xl shadow-2xl p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4">Pembayaran Per Outlet</h2>
          <p class="text-sm text-gray-600 mb-4">
            <i class="fa fa-info-circle mr-1"></i>
            Payment ini memiliki {{ outletPaymentList.length }} baris pembayaran per outlet. Semua baris harus tampil dan dapat diedit.
            <span v-if="form.payment_method === 'transfer' || form.payment_method === 'check'" class="block mt-2 text-blue-600">
              <i class="fa fa-university mr-1"></i>
              <strong>Penting:</strong> Pilih bank untuk setiap outlet.
            </span>
          </p>

          <div class="space-y-4">
            <div
              v-for="row in outletPaymentList"
              :key="row.key"
              class="border border-gray-200 rounded-lg p-4"
            >
              <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                  <h3 class="text-lg font-semibold text-gray-900">
                    {{ row.outlet_name || 'Global / Head Office' }}
                  </h3>
                  <div class="text-sm text-gray-600 mt-1">
                    <span
                      v-if="row.category_name"
                      class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs mr-2"
                    >
                      Category: {{ row.category_name }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Jumlah Pembayaran <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="number"
                    v-model="outletPayments[row.key].amount"
                    step="0.01"
                    :min="0"
                    required
                    class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
                    placeholder="0.00"
                    @input="updateTotalAmount"
                  />
                </div>
              </div>

              <div v-if="form.payment_method === 'transfer' || form.payment_method === 'check'" class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  <i class="fa fa-university mr-1"></i>
                  Pilih Bank untuk Outlet Ini <span class="text-red-500">*</span>
                </label>
                <multiselect
                  v-model="outletPayments[row.key].selectedBank"
                  :options="getBankOptionsForOutlet(outletPayments[row.key].outlet_id)"
                  :searchable="true"
                  :close-on-select="true"
                  :show-labels="false"
                  placeholder="Cari dan pilih bank untuk outlet ini..."
                  label="display_name"
                  track-by="id"
                  @select="(bank) => onOutletBankSelect(row.key, bank)"
                  @remove="() => onOutletBankRemove(row.key)"
                  class="w-full"
                  required
                >
                  <template #noOptions>
                    <span>Tidak ada bank ditemukan</span>
                  </template>
                  <template #noResult>
                    <span>Tidak ada bank ditemukan</span>
                  </template>
                </multiselect>
                <p v-if="outletPayments[row.key].selectedBank" class="mt-1 text-xs text-green-600">
                  <i class="fa fa-check-circle mr-1"></i>
                  Bank terpilih: <strong>{{ outletPayments[row.key].selectedBank.display_name }}</strong>
                </p>
              </div>
            </div>
          </div>

          <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex justify-between items-center">
              <span class="text-sm font-medium text-gray-700">Total Pembayaran Semua Outlet:</span>
              <span class="text-lg font-bold text-blue-600">{{ formatCurrency(totalOutletPayments) }}</span>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-4">
          <button type="button" @click="goBack" class="bg-gray-500 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            Batal
          </button>
          <button type="submit" :disabled="isSubmitting" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-6 py-3 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold disabled:opacity-50">
            <i v-if="isSubmitting" class="fa fa-spinner fa-spin mr-2"></i>
            {{ isSubmitting ? 'Menyimpan...' : 'Update Payment' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useLoading } from '@/Composables/useLoading';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';

const props = defineProps({
  payment: Object,
  suppliers: Array,
  banks: {
    type: Array,
    default: () => []
  }
});

const isSubmitting = ref(false);
const { showLoading, hideLoading } = useLoading();

const selectedBank = ref(null);
const coaList = ref([]);
const selectedCoa = ref(null);
const outletPayments = ref({});
const outletPaymentList = ref([]);

async function notify(opts) {
  const { default: Swal } = await import('sweetalert2');
  return Swal.fire(opts);
}
const form = reactive({
  supplier_id: '',
  amount: '',
  payment_method: '',
  bank_id: null,
  coa_id: '',
  payment_date: '',
  due_date: '',
  description: '',
  reference_number: '',
  notes: ''
});

const banks = computed(() => {
  if (!props.banks || !Array.isArray(props.banks)) return [];
  return props.banks.map(bank => {
    const outletName = bank.outlet?.nama_outlet || bank.outlet_name || 'Head Office';
    return {
      ...bank,
      display_name: `${bank.bank_name} - ${bank.account_number} (${bank.account_name}) - ${outletName}`
    };
  });
});

const hasOutletPayments = computed(() => outletPaymentList.value.length > 0);

const totalOutletPayments = computed(() => {
  return Object.values(outletPayments.value).reduce((sum, outlet) => {
    return sum + (parseFloat(outlet.amount) || 0);
  }, 0);
});

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value || 0);
}

function normalizeOutletId(outletId) {
  if (outletId === undefined || outletId === null || outletId === '') return null;
  if (outletId === 0 || outletId === '0') return null;
  if (typeof outletId === 'string' && Number.isNaN(Number(outletId))) return null;
  const n = Number(outletId);
  return Number.isFinite(n) ? n : null;
}

function getOutletKey(outletId, categoryId = null, fallbackId = null) {
  const normalized = normalizeOutletId(outletId);
  const base = normalized == null ? 'HO' : String(normalized);
  if (categoryId != null && categoryId !== '') {
    return `${base}_${categoryId}`;
  }
  if (fallbackId != null) {
    return `${base}_${fallbackId}`;
  }
  return base;
}

function getBankOutletId(bank) {
  return normalizeOutletId(bank?.outlet?.id_outlet ?? bank?.outlet_id ?? null);
}

const bankOptionsByOutletId = computed(() => {
  const map = new Map();
  (banks.value || []).forEach((bank) => {
    const key = normalizeOutletId(getBankOutletId(bank)) == null ? 'HO' : String(normalizeOutletId(getBankOutletId(bank)));
    if (!map.has(key)) map.set(key, []);
    map.get(key).push(bank);
  });
  return map;
});

function getBankOptionsForOutlet(outletId) {
  if (normalizeOutletId(outletId) == null) return banks.value || [];
  const key = String(normalizeOutletId(outletId));
  return bankOptionsByOutletId.value.get(key) || [];
}

function onBankSelect(bank) {
  if (bank && bank.id) {
    form.bank_id = bank.id;
  }
}

function onBankRemove() {
  form.bank_id = null;
  selectedBank.value = null;
}

function onCoaSelect(coa) {
  if (coa && coa.id) {
    form.coa_id = coa.id;
    selectedCoa.value = coa;
  }
}

function onCoaRemove() {
  form.coa_id = '';
  selectedCoa.value = null;
}

function onOutletBankSelect(outletKey, bank) {
  if (bank && bank.id) {
    outletPayments.value[outletKey].bank_id = bank.id;
    outletPayments.value[outletKey].selectedBank = bank;
  }
}

function onOutletBankRemove(outletKey) {
  outletPayments.value[outletKey].bank_id = null;
  outletPayments.value[outletKey].selectedBank = null;
}

function updateTotalAmount() {
  form.amount = totalOutletPayments.value;
}

function initializeOutletPayments() {
  const rows = props.payment?.payment_outlets || props.payment?.paymentOutlets || [];
  outletPayments.value = {};
  outletPaymentList.value = [];

  if (!Array.isArray(rows) || rows.length === 0) {
    return;
  }

  rows.forEach((row) => {
    const outletId = row.outlet_id ?? row.outlet?.id_outlet ?? null;
    const categoryId = row.category_id ?? row.category?.id ?? null;
    const key = getOutletKey(outletId, categoryId, row.id);
    const bankId = row.bank_id || null;
    const selected = bankId
      ? (banks.value || []).find(b => String(b.id) === String(bankId)) || null
      : null;

    outletPayments.value[key] = {
      id: row.id || null,
      outlet_id: outletId,
      category_id: categoryId,
      amount: parseFloat(row.amount) || 0,
      bank_id: bankId,
      selectedBank: selected,
    };

    outletPaymentList.value.push({
      key,
      outlet_id: outletId,
      category_id: categoryId,
      outlet_name: row.outlet?.nama_outlet || (outletId == null ? 'Head Office' : `Outlet #${outletId}`),
      category_name: row.category?.name || null,
    });
  });

  updateTotalAmount();
}

watch(() => form.payment_method, (method) => {
  const isBankMethod = method === 'transfer' || method === 'check';
  if (!isBankMethod) {
    Object.keys(outletPayments.value).forEach((outletKey) => onOutletBankRemove(outletKey));
    form.bank_id = null;
    selectedBank.value = null;
  }
});

onMounted(() => {
  if (props.payment) {
    form.supplier_id = props.payment.supplier_id || '';
    form.amount = props.payment.amount || '';
    form.payment_method = props.payment.payment_method || '';
    form.bank_id = props.payment.bank_id || null;
    form.coa_id = props.payment.coa_id || '';
    form.payment_date = props.payment.payment_date ? new Date(props.payment.payment_date).toISOString().split('T')[0] : '';
    form.due_date = props.payment.due_date ? new Date(props.payment.due_date).toISOString().split('T')[0] : '';
    form.description = props.payment.description || '';
    form.reference_number = props.payment.reference_number || '';
    form.notes = props.payment.notes || '';

    if (props.payment.bank_id && banks.value.length > 0) {
      const bank = banks.value.find(b => b.id == props.payment.bank_id);
      if (bank) selectedBank.value = bank;
    }
  }

  initializeOutletPayments();

  (async function(){
    try {
      const res = await axios.get('/api/chart-of-accounts/dropdown');
      coaList.value = (res.data || []).map(c => ({ ...c, display_name: `${c.code} - ${c.name}` }));
      if (form.coa_id) {
        const found = coaList.value.find(c => String(c.id) === String(form.coa_id));
        if (found) selectedCoa.value = found;
      }
    } catch (e) {
      console.error('Failed to load COA list', e);
      coaList.value = [];
    }
  })();
});

function goBack() {
  router.visit(`/non-food-payments/${props.payment.id}`);
}

async function submitForm() {
  if (hasOutletPayments.value) {
    updateTotalAmount();

    if ((form.payment_method === 'transfer' || form.payment_method === 'check')) {
      const outletsWithoutBank = Object.keys(outletPayments.value).filter((outletKey) => {
        const outlet = outletPayments.value[outletKey];
        return (parseFloat(outlet.amount) || 0) > 0 && !outlet.bank_id;
      });

      if (outletsWithoutBank.length > 0) {
        await notify({
          icon: 'warning',
          title: 'Bank belum lengkap',
          text: 'Setiap outlet dengan jumlah pembayaran > 0 wajib punya bank untuk metode Transfer/Check.',
        });
        return;
      }
    }
  } else if ((form.payment_method === 'transfer' || form.payment_method === 'check') && !form.bank_id) {
    await notify({
      icon: 'warning',
      title: 'Bank wajib',
      text: 'Pilih bank untuk metode Transfer/Check.',
    });
    return;
  }

  const outletPaymentsArray = Object.values(outletPayments.value)
    .filter((o) => (parseFloat(o.amount) || 0) > 0)
    .map((o) => ({
      outlet_id: o.outlet_id,
      category_id: o.category_id,
      amount: o.amount,
      bank_id: o.bank_id || null,
    }));

  const payload = {
    ...form,
    outlet_payments: outletPaymentsArray.length > 0 ? outletPaymentsArray : null,
  };

  if (outletPaymentsArray.length > 0) {
    // Bank ditangani per outlet
    payload.bank_id = null;
  }

  isSubmitting.value = true;
  showLoading('Menyimpan perubahan Non Food Payment...', 'Mohon tunggu sebentar');

  router.put(`/non-food-payments/${props.payment.id}`, payload, {
    onSuccess: () => {
      isSubmitting.value = false;
      hideLoading();
    },
    onError: (errors) => {
      isSubmitting.value = false;
      hideLoading();
      console.error('Validation errors:', errors);
      const firstError = errors ? Object.values(errors)[0] : null;
      notify({
        icon: 'error',
        title: 'Gagal menyimpan',
        text: Array.isArray(firstError) ? firstError[0] : (firstError || 'Validasi gagal'),
      });
    },
    onFinish: () => {
      isSubmitting.value = false;
      hideLoading();
    }
  });
}
</script>
