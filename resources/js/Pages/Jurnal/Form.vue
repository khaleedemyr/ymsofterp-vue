<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  jurnal: Object,
  coas: Array,
  outlets: Array,
});

const isEdit = computed(() => !!props.jurnal);

// Initialize entries - jika edit, buat 1 entry, jika create, buat 1 entry kosong
const initialEntries = isEdit.value ? [{
  coa_debit_id: props.jurnal.coa_debit_id || '',
  coa_kredit_id: props.jurnal.coa_kredit_id || '',
  jumlah: props.jurnal.jumlah_debit || 0,
}] : [{
  coa_debit_id: '',
  coa_kredit_id: '',
  jumlah: 0,
}];

const entries = ref(initialEntries.map(entry => ({ ...entry })));

const form = useForm({
  tanggal: props.jurnal?.tanggal || new Date().toISOString().split('T')[0],
  keterangan: props.jurnal?.keterangan || '',
  outlet_id: props.jurnal?.outlet_id || '',
  entries: entries.value,
});

// CoA Options untuk semua dropdown
const coaOptions = computed(() => {
  return props.coas.map(coa => ({
    value: coa.id,
    label: `${coa.code} - ${coa.name} (${coa.type})`,
    code: coa.code,
    name: coa.name,
    type: coa.type
  }));
});

// Get CoA info by ID
function getCoaInfo(coaId) {
  if (!coaId) return null;
  return props.coas.find(c => c.id == coaId);
}

// Total debit dan kredit
const totalDebit = computed(() => {
  return entries.value.reduce((sum, entry) => sum + (parseFloat(entry.jumlah) || 0), 0);
});

const totalKredit = computed(() => {
  return entries.value.reduce((sum, entry) => sum + (parseFloat(entry.jumlah) || 0), 0);
});

// Add new entry
function addEntry() {
  entries.value.push({
    coa_debit_id: '',
    coa_kredit_id: '',
    jumlah: 0,
  });
}

// Remove entry
async function removeEntry(index) {
  if (entries.value.length > 1) {
    const result = await Swal.fire({
      title: 'Hapus Entry?',
      text: `Yakin ingin menghapus entry #${index + 1}?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Ya, Hapus!',
      cancelButtonText: 'Batal',
    });
    if (result.isConfirmed) {
      entries.value.splice(index, 1);
    }
  } else {
    Swal.fire('Peringatan', 'Minimal harus ada 1 entry jurnal!', 'warning');
  }
}

// Validasi entry
function validateEntry(entry, index) {
  if (entry.coa_debit_id && entry.coa_kredit_id && entry.coa_debit_id == entry.coa_kredit_id) {
    return `Entry #${index + 1}: CoA debit dan kredit tidak boleh sama!`;
  }
  if (entry.jumlah <= 0) {
    return `Entry #${index + 1}: Jumlah harus lebih dari 0!`;
  }
  return null;
}

function submit() {
  // Update form entries
  form.entries = entries.value;
  
  // Validasi semua entries
  const errors = [];
  entries.value.forEach((entry, index) => {
    const error = validateEntry(entry, index);
    if (error) errors.push(error);
  });
  
  if (errors.length > 0) {
    Swal.fire({
      title: 'Validasi Gagal',
      html: errors.join('<br>'),
      icon: 'error',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  // Validasi total debit = total kredit
  if (totalDebit.value !== totalKredit.value) {
    Swal.fire('Validasi Gagal', 'Total debit dan kredit harus sama!', 'error');
    return;
  }
  
  // Validasi semua entry harus lengkap
  const incompleteEntries = entries.value.filter(e => !e.coa_debit_id || !e.coa_kredit_id || !e.jumlah);
  if (incompleteEntries.length > 0) {
    Swal.fire('Validasi Gagal', 'Semua entry harus lengkap (CoA debit, CoA kredit, dan jumlah)!', 'error');
    return;
  }
  
  if (isEdit.value) {
    // Edit mode: hanya support single entry untuk sekarang
    if (entries.value.length > 1) {
      Swal.fire('Peringatan', 'Mode edit hanya support 1 entry. Silakan hapus entry tambahan atau buat jurnal baru.', 'warning');
      return;
    }
    const entry = entries.value[0];
    form.coa_debit_id = entry.coa_debit_id;
    form.coa_kredit_id = entry.coa_kredit_id;
    form.jumlah_debit = entry.jumlah;
    form.jumlah_kredit = entry.jumlah;
    // outlet_id sudah ada di form
    
    form.put(route('jurnal.update', props.jurnal.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Jurnal berhasil diupdate!', 'success').then(() => {
          router.visit('/jurnal');
        });
      },
      onError: (errors) => {
        if (errors.error) {
          Swal.fire('Error', errors.error, 'error');
        }
      }
    });
  } else {
    // Create mode: bisa multiple entries
    form.post(route('jurnal.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Jurnal berhasil dibuat!', 'success').then(() => {
          router.visit('/jurnal');
        });
      },
      onError: (errors) => {
        if (errors.error) {
          Swal.fire('Error', errors.error, 'error');
        }
      }
    });
  }
}

function formatCurrency(value) {
  if (!value) return '0';
  return new Intl.NumberFormat('id-ID').format(value);
}

function parseCurrency(value) {
  return parseFloat(value.toString().replace(/[^\d]/g, '')) || 0;
}

function onDebitChange(event) {
  const value = parseCurrency(event.target.value);
  form.jumlah_debit = value;
  form.jumlah_kredit = value; // Auto sync
}

function onKreditChange(event) {
  const value = parseCurrency(event.target.value);
  form.jumlah_kredit = value;
  form.jumlah_debit = value; // Auto sync
}
</script>

<template>
  <AppLayout :title="isEdit ? 'Edit Jurnal' : 'Tambah Jurnal Baru'">
    <div class="w-full py-8 px-4">
      <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl shadow-lg p-6">
          <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-book text-blue-500"></i>
              {{ isEdit ? 'Edit Jurnal' : 'Tambah Jurnal Baru' }}
            </h1>
            <button 
              @click="router.visit('/jurnal')" 
              class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
              <i class="fa-solid fa-arrow-left mr-2"></i>Kembali
            </button>
          </div>

          <form @submit.prevent="submit" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Tanggal -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Tanggal <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="form.tanggal"
                  type="date"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  :class="{ 'border-red-500': form.errors.tanggal }"
                />
                <p v-if="form.errors.tanggal" class="mt-1 text-sm text-red-600">{{ form.errors.tanggal }}</p>
              </div>

              <!-- Outlet -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Outlet <span class="text-red-500">*</span>
                </label>
                <select
                  v-model="form.outlet_id"
                  required
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  :class="{ 'border-red-500': form.errors.outlet_id }"
                >
                  <option value="">-- Pilih Outlet --</option>
                  <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                    {{ outlet.nama_outlet }}
                  </option>
                </select>
                <p v-if="form.errors.outlet_id" class="mt-1 text-sm text-red-600">{{ form.errors.outlet_id }}</p>
              </div>
            </div>

            <!-- No Jurnal (readonly jika edit) -->
            <div v-if="isEdit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  No Jurnal
                </label>
                <input
                  :value="jurnal.no_jurnal"
                  type="text"
                  readonly
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100"
                />
              </div>
            </div>

            <!-- Keterangan -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Keterangan
              </label>
              <textarea
                v-model="form.keterangan"
                rows="3"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :class="{ 'border-red-500': form.errors.keterangan }"
              ></textarea>
              <p v-if="form.errors.keterangan" class="mt-1 text-sm text-red-600">{{ form.errors.keterangan }}</p>
            </div>

            <!-- Multiple Entries -->
            <div class="space-y-4">
              <div class="flex justify-between items-center">
                <label class="block text-sm font-medium text-gray-700">
                  Entri Jurnal <span class="text-red-500">*</span>
                </label>
                <button
                  v-if="!isEdit"
                  type="button"
                  @click="addEntry"
                  class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm font-semibold"
                >
                  <i class="fa-solid fa-plus mr-1"></i>Tambah Entry
                </button>
              </div>

              <!-- Entry List -->
              <div class="space-y-4">
                <div
                  v-for="(entry, index) in entries"
                  :key="index"
                  class="bg-gray-50 border-2 border-gray-200 rounded-lg p-4"
                >
                  <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold text-gray-700">Entry #{{ index + 1 }}</h3>
                    <button
                      v-if="!isEdit && entries.length > 1"
                      type="button"
                      @click="removeEntry(index)"
                      class="px-3 py-1 bg-red-500 text-white rounded-lg hover:bg-red-600 transition text-sm"
                      title="Hapus Entry"
                    >
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- CoA Debit -->
                    <div>
                      <label class="block text-xs font-medium text-gray-700 mb-1">
                        <i class="fa-solid fa-arrow-down text-green-600 mr-1"></i>
                        CoA Debit <span class="text-red-500">*</span>
                      </label>
                      <select
                        v-model="entry.coa_debit_id"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm"
                      >
                        <option value="">-- Pilih CoA Debit --</option>
                        <option v-for="coa in coaOptions" :key="coa.value" :value="coa.value">
                          {{ coa.label }}
                        </option>
                      </select>
                      <div v-if="getCoaInfo(entry.coa_debit_id)" class="mt-1 text-xs text-gray-600">
                        {{ getCoaInfo(entry.coa_debit_id)?.code }} - {{ getCoaInfo(entry.coa_debit_id)?.name }}
                      </div>
                    </div>

                    <!-- CoA Kredit -->
                    <div>
                      <label class="block text-xs font-medium text-gray-700 mb-1">
                        <i class="fa-solid fa-arrow-up text-red-600 mr-1"></i>
                        CoA Kredit <span class="text-red-500">*</span>
                      </label>
                      <select
                        v-model="entry.coa_kredit_id"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm"
                      >
                        <option value="">-- Pilih CoA Kredit --</option>
                        <option v-for="coa in coaOptions" :key="coa.value" :value="coa.value">
                          {{ coa.label }}
                        </option>
                      </select>
                      <div v-if="getCoaInfo(entry.coa_kredit_id)" class="mt-1 text-xs text-gray-600">
                        {{ getCoaInfo(entry.coa_kredit_id)?.code }} - {{ getCoaInfo(entry.coa_kredit_id)?.name }}
                      </div>
                    </div>

                    <!-- Jumlah -->
                    <div>
                      <label class="block text-xs font-medium text-gray-700 mb-1">
                        Jumlah <span class="text-red-500">*</span>
                      </label>
                      <input
                        :value="formatCurrency(entry.jumlah)"
                        @input="(e) => entry.jumlah = parseCurrency(e.target.value)"
                        type="text"
                        required
                        placeholder="0"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-right text-sm"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <!-- Total Summary -->
              <div class="bg-blue-50 border-2 border-blue-200 rounded-lg p-4">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Total Debit</label>
                    <div class="text-lg font-bold text-green-700">{{ formatCurrency(totalDebit) }}</div>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Total Kredit</label>
                    <div class="text-lg font-bold text-red-700">{{ formatCurrency(totalKredit) }}</div>
                  </div>
                </div>
                <div v-if="totalDebit !== totalKredit" class="mt-2 text-sm text-red-600 font-semibold">
                  <i class="fa-solid fa-exclamation-triangle mr-1"></i>
                  Total debit dan kredit harus sama!
                </div>
                <div v-else class="mt-2 text-sm text-green-600 font-semibold">
                  <i class="fa-solid fa-check-circle mr-1"></i>
                  Balance: Total debit = Total kredit
                </div>
              </div>
            </div>

            <!-- Error Summary -->
            <div v-if="form.errors.error" class="bg-red-50 border border-red-200 rounded-lg p-4">
              <p class="text-sm text-red-600">{{ form.errors.error }}</p>
            </div>

            <!-- Submit Buttons -->
            <div class="flex justify-end gap-4 pt-4 border-t">
              <button
                type="button"
                @click="router.visit('/jurnal')"
                class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="form.processing || totalDebit !== totalKredit || entries.length === 0"
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="form.processing">Menyimpan...</span>
                <span v-else>{{ isEdit ? 'Update' : 'Simpan' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

