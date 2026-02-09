<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-pink-50/30 to-slate-50 py-8 px-4 sm:px-6">
      <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-8">
          <Link
            :href="route('reservations.index')"
            class="flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 transition"
          >
            <i class="fa-solid fa-arrow-left"></i>
          </Link>
          <div>
            <h1 class="text-2xl font-bold text-slate-800">
              {{ isEdit ? 'Edit Reservasi' : 'Tambah Reservasi' }}
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">{{ isEdit ? 'Ubah data reservasi' : 'Isi form untuk membuat reservasi baru' }}</p>
          </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
          <!-- Data Pemesan -->
          <section class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-3">
              <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-rose-100 text-rose-600">
                <i class="fa-solid fa-user text-lg"></i>
              </span>
              <div>
                <h2 class="font-semibold text-slate-800">Data Pemesan</h2>
                <p class="text-xs text-slate-500">Informasi kontak pemesan</p>
              </div>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">
              <div class="md:col-span-3">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Nama Lengkap <span class="text-rose-500">*</span></label>
                <input
                  v-model="form.name"
                  type="text"
                  required
                  maxlength="100"
                  placeholder="Nama lengkap pemesan"
                  class="input-field"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Nomor Telepon <span class="text-rose-500">*</span></label>
                <input
                  v-model="form.phone"
                  type="tel"
                  required
                  maxlength="20"
                  placeholder="08xxxxxxxxxx"
                  class="input-field"
                />
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                <input
                  v-model="form.email"
                  type="email"
                  maxlength="100"
                  placeholder="email@contoh.com (opsional)"
                  class="input-field"
                />
              </div>
            </div>
          </section>

          <!-- Detail Reservasi -->
          <section class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-3">
              <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-pink-100 text-pink-600">
                <i class="fa-solid fa-calendar-days text-lg"></i>
              </span>
              <div>
                <h2 class="font-semibold text-slate-800">Detail Reservasi</h2>
                <p class="text-xs text-slate-500">Tanggal, waktu, outlet & preferensi</p>
              </div>
            </div>
            <div class="p-6 space-y-5">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Outlet <span class="text-rose-500">*</span></label>
                <select v-model="form.outlet_id" required class="input-field">
                  <option value="">Pilih Outlet</option>
                  <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
                </select>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">Tanggal Reservasi <span class="text-rose-500">*</span></label>
                  <input
                    v-model="form.reservation_date"
                    type="date"
                    required
                    class="input-field"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">Waktu Reservasi <span class="text-rose-500">*</span></label>
                  <VueTimepicker
                    v-model="form.reservation_time"
                    format="HH:mm"
                    :is24="true"
                    minute-interval="15"
                    class="input-field"
                  />
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">Jumlah Tamu <span class="text-rose-500">*</span></label>
                  <input
                    v-model.number="form.number_of_guests"
                    type="number"
                    min="1"
                    required
                    class="input-field"
                    placeholder="1"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-slate-700 mb-1.5">Preferensi Area <span class="text-rose-500">*</span></label>
                  <select v-model="form.smoking_preference" required class="input-field">
                    <option value="">Pilih Area</option>
                    <option value="smoking">Smoking Area</option>
                    <option value="non_smoking">Non-Smoking Area</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Catatan Khusus</label>
                <textarea
                  v-model="form.special_requests"
                  rows="3"
                  placeholder="Request khusus (kue ulang tahun, kursi bayi, dll)"
                  class="input-field resize-none"
                ></textarea>
              </div>
            </div>
          </section>

          <!-- DP & Sales -->
          <section class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-3">
              <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600">
                <i class="fa-solid fa-money-bill-wave text-lg"></i>
              </span>
              <div>
                <h2 class="font-semibold text-slate-800">DP & Sales</h2>
                <p class="text-xs text-slate-500">Down payment dan sumber reservasi</p>
              </div>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">DP (Down Payment)</label>
                <input
                  v-model.number="form.dp"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0"
                  class="input-field"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Dari Sales?</label>
                <select v-model="form.from_sales" class="input-field" @change="onFromSalesChange">
                  <option :value="false">Bukan</option>
                  <option :value="true">Dari Sales</option>
                </select>
              </div>
              <div v-if="form.from_sales" class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Pilih Sales</label>
                <select v-model="form.sales_user_id" class="input-field">
                  <option :value="null">-- Pilih Sales --</option>
                  <option v-for="u in (salesUsers || [])" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
            </div>
          </section>

          <!-- Menu & Status -->
          <section class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center gap-3">
              <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-100 text-amber-600">
                <i class="fa-solid fa-utensils text-lg"></i>
              </span>
              <div>
                <h2 class="font-semibold text-slate-800">Menu & Status</h2>
                <p class="text-xs text-slate-500">Daftar menu dan status reservasi</p>
              </div>
            </div>
            <div class="p-6 space-y-5">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Menu</label>
                <textarea
                  v-model="form.menu"
                  rows="5"
                  placeholder="Tulis menu yang dipesan (tanpa batas karakter)"
                  class="input-field resize-none"
                ></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Status <span class="text-rose-500">*</span></label>
                <select v-model="form.status" required class="input-field">
                  <option value="pending">Pending</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
            </div>
          </section>

          <!-- Actions -->
          <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-3 pt-2">
            <Link
              :href="route('reservations.index')"
              class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl border border-slate-200 text-slate-700 font-medium hover:bg-slate-50 transition"
            >
              Batal
            </Link>
            <button
              type="submit"
              :disabled="loading"
              class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold text-white bg-gradient-to-r from-rose-500 to-pink-600 hover:from-rose-600 hover:to-pink-700 shadow-lg shadow-pink-500/25 disabled:opacity-70 disabled:cursor-not-allowed transition"
            >
              <i v-if="loading" class="fa-solid fa-spinner fa-spin"></i>
              <i v-else class="fa-solid fa-check"></i>
              {{ loading ? 'Menyimpan...' : 'Simpan Reservasi' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import VueTimepicker from 'vue3-timepicker';
import 'vue3-timepicker/dist/VueTimepicker.css';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  reservation: Object,
  outlets: Array,
  salesUsers: { type: Array, default: () => [] },
  isEdit: Boolean
});

const loading = ref(false);
const form = ref({
  name: props.reservation?.name || '',
  phone: props.reservation?.phone || '',
  email: props.reservation?.email || '',
  outlet_id: props.reservation?.outlet_id || '',
  reservation_date: props.reservation?.reservation_date || '',
  reservation_time: props.reservation?.reservation_time || '',
  number_of_guests: props.reservation?.number_of_guests || 1,
  smoking_preference: props.reservation?.smoking_preference || '',
  special_requests: props.reservation?.special_requests || '',
  dp: props.reservation?.dp ?? null,
  from_sales: Boolean(props.reservation?.from_sales),
  sales_user_id: props.reservation?.sales_user_id ?? null,
  menu: props.reservation?.menu || '',
  status: props.reservation?.status || 'pending',
});

function onFromSalesChange() {
  if (!form.value.from_sales) form.value.sales_user_id = null;
}

function submit() {
  loading.value = true;
  if (props.isEdit) {
    router.put(route('reservations.update', props.reservation.id), form.value, {
      onSuccess: () => {
        loading.value = false;
        Swal.fire({ title: 'Sukses', text: 'Reservasi berhasil diupdate!', icon: 'success' });
      },
      onError: () => {
        loading.value = false;
        Swal.fire({ title: 'Gagal', text: 'Terjadi kesalahan saat menyimpan.', icon: 'error' });
      }
    });
  } else {
    router.post(route('reservations.store'), form.value, {
      onSuccess: () => {
        loading.value = false;
        Swal.fire({ title: 'Sukses', text: 'Reservasi berhasil disimpan!', icon: 'success' });
      },
      onError: () => {
        loading.value = false;
        Swal.fire({ title: 'Gagal', text: 'Terjadi kesalahan saat menyimpan.', icon: 'error' });
      }
    });
  }
}
</script>

<style scoped>
.input-field {
  @apply w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-800 placeholder-slate-400 focus:border-rose-400 focus:ring-2 focus:ring-rose-400/20 outline-none transition text-sm;
}
</style>
