<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  reservation_maintenance_enabled: { type: Boolean, default: true },
  guest_table_selection_enabled: { type: Boolean, default: true },
});

const savingKey = ref(null);
const maintenanceEnabled = computed(() => props.reservation_maintenance_enabled !== false);
const guestTableSelectionEnabled = computed(() => props.guest_table_selection_enabled !== false);

function goBack() {
  router.visit('/web-profile');
}

function patchSetting(payload, successText) {
  const key = Object.keys(payload)[0];
  savingKey.value = key;
  router.patch('/web-profile/reservation-settings', payload, {
    preserveScroll: true,
    onFinish: () => {
      savingKey.value = null;
    },
    onSuccess: () => {
      Swal.fire({
        title: 'Berhasil',
        text: successText,
        icon: 'success',
        timer: 2200,
        showConfirmButton: false,
      });
    },
    onError: () => {
      Swal.fire({ title: 'Error', text: 'Gagal menyimpan pengaturan.', icon: 'error' });
    },
  });
}

function toggleMaintenance() {
  if (savingKey.value) return;
  const next = !maintenanceEnabled.value;
  patchSetting(
    { reservation_maintenance_enabled: next },
    next
      ? 'Reservasi website dalam mode Under Maintenance.'
      : 'Under Maintenance dimatikan. Menu reservasi website aktif kembali.'
  );
}

function toggleGuestTableSelection() {
  if (savingKey.value) return;
  const next = !guestTableSelectionEnabled.value;
  patchSetting(
    { guest_table_selection_enabled: next },
    next
      ? 'Pemilihan meja di website diaktifkan.'
      : 'Pemilihan meja dinonaktifkan. Tamu skip cek meja.'
  );
}
</script>

<template>
  <AppLayout title="Web Profile - Reservation Settings">
    <div class="max-w-3xl mx-auto py-8 px-4">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Reservation Settings</h1>
          <p class="mt-1 text-sm text-gray-500">
            Pengaturan halaman reservasi Justus Kunest (website publik)
          </p>
        </div>
        <button
          type="button"
          class="px-4 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
          @click="goBack"
        >
          Back
        </button>
      </div>

      <div class="space-y-4">
        <!-- Under maintenance -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
                <span class="font-semibold text-gray-800">Under Maintenance</span>
              </div>
              <p class="text-sm text-gray-500 max-w-xl">
                Jika aktif, menu Reservation di Justus Kunest menampilkan halaman maintenance
                (tidak bisa arrange / cek status / API reservasi).
              </p>
            </div>
            <button
              type="button"
              class="inline-flex items-center gap-3 self-start sm:self-center rounded-xl border px-4 py-2.5 text-sm font-semibold transition disabled:opacity-60"
              :class="maintenanceEnabled
                ? 'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100'
                : 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100'"
              :disabled="savingKey === 'reservation_maintenance_enabled'"
              @click="toggleMaintenance"
            >
              <span
                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
                :class="maintenanceEnabled ? 'bg-amber-500' : 'bg-emerald-500'"
              >
                <span
                  class="inline-block h-5 w-5 rounded-full bg-white shadow transition"
                  :class="maintenanceEnabled ? 'translate-x-5' : 'translate-x-0.5'"
                />
              </span>
              {{ maintenanceEnabled ? 'Aktif (under maintenance)' : 'Nonaktif (website buka)' }}
            </button>
          </div>
        </div>

        <!-- Guest table selection -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <div class="flex items-center gap-2 mb-1">
                <i class="fa-solid fa-chair text-rose-500"></i>
                <span class="font-semibold text-gray-800">Pemilihan meja di website</span>
              </div>
              <p class="text-sm text-gray-500 max-w-xl">
                Jika dimatikan, tamu melewati cek ketersediaan &amp; pilih meja. Reservasi tetap masuk;
                meja bisa di-assign manual di ERP / POS.
              </p>
            </div>
            <button
              type="button"
              class="inline-flex items-center gap-3 self-start sm:self-center rounded-xl border px-4 py-2.5 text-sm font-semibold transition disabled:opacity-60"
              :class="guestTableSelectionEnabled
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100'
                : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100'"
              :disabled="savingKey === 'guest_table_selection_enabled'"
              @click="toggleGuestTableSelection"
            >
              <span
                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
                :class="guestTableSelectionEnabled ? 'bg-emerald-500' : 'bg-slate-300'"
              >
                <span
                  class="inline-block h-5 w-5 rounded-full bg-white shadow transition"
                  :class="guestTableSelectionEnabled ? 'translate-x-5' : 'translate-x-0.5'"
                />
              </span>
              {{ guestTableSelectionEnabled ? 'Aktif (tamu pilih meja)' : 'Nonaktif (skip pilih meja)' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
