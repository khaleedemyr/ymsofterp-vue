<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  prFood: Object,
});

const user = usePage().props.auth?.user || {};

const isSuperadmin = computed(() =>
  user?.id_role === '5af56935b011a' && user?.status === 'A'
);
const canApproveSSD = computed(() =>
  ((user?.id_jabatan === 161 && user?.status === 'A') || isSuperadmin.value)
  && props.prFood.status === 'draft'
  && !props.prFood.ssd_manager_approved_at
);
const canApproveCOO = computed(() =>
  ((user?.id_jabatan === 154 && user?.status === 'A') || isSuperadmin.value)
  && props.prFood.status === 'draft'
  && props.prFood.ssd_manager_approved_at
);

const ssdNote = ref('');
const cooNote = ref('');

async function approveSSD(approved) {
  const { value: note } = await Swal.fire({
    title: approved ? 'Approve PR?' : 'Reject PR?',
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    inputValue: '',
    showCancelButton: true,
    confirmButtonText: approved ? 'Approve' : 'Reject',
    cancelButtonText: 'Batal',
  });
  if (note !== undefined) {
    router.post(route('pr-foods.approve-ssd-manager', props.prFood.id), {
      approved,
      ssd_manager_note: note,
    });
  }
}

async function approveCOO(approved) {
  const { value: note } = await Swal.fire({
    title: approved ? 'Approve PR?' : 'Reject PR?',
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    inputValue: '',
    showCancelButton: true,
    confirmButtonText: approved ? 'Approve' : 'Reject',
    cancelButtonText: 'Batal',
  });
  if (note !== undefined) {
    router.post(route('pr-foods.approve-vice-coo', props.prFood.id), {
      approved,
      vice_coo_note: note,
    });
  }
}
</script>
<template>
  <AppLayout>
    <div class="max-w-3xl w-full mx-auto py-8 px-2">
      <div class="flex items-center gap-2 mb-6">
        <button @click="$inertia.visit('/pr-foods')" class="text-blue-500 hover:underline"><i class="fa fa-arrow-left"></i> Kembali</button>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 ml-4">
          <i class="fa-solid fa-file-invoice text-blue-500"></i> Detail PR Foods
        </h1>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div><b>No. PR:</b> {{ prFood.pr_number }}</div>
          <div><b>Tanggal:</b> {{ new Date(prFood.tanggal).toLocaleDateString('id-ID') }}</div>
          <div><b>Warehouse:</b> {{ prFood.warehouse?.name }}</div>
          <div><b>Requester:</b> {{ prFood.requester?.nama_lengkap }}</div>
          <div><b>Status:</b> <span class="font-semibold">{{ prFood.status }}</span></div>
        </div>
        <div><b>Keterangan:</b> {{ prFood.description }}</div>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <h2 class="text-lg font-bold mb-2">Detail Item</h2>
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
              <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Qty</th>
              <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
              <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Note</th>
              <th class="px-3 py-2 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tgl Kedatangan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in prFood.items" :key="item.id">
              <td class="px-3 py-2">
                <div>{{ item.item?.name }}</div>
                <div v-if="item.stock_small !== undefined" class="text-xs text-gray-500 mt-1">
                  Stok:
                  <span v-if="item.unit_small">{{ Number(item.stock_small).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} {{ item.unit_small }}</span>
                  <span v-if="item.unit_medium"> | {{ Number(item.stock_medium).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} {{ item.unit_medium }}</span>
                  <span v-if="item.unit_large"> | {{ Number(item.stock_large).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} {{ item.unit_large }}</span>
                </div>
              </td>
              <td class="px-3 py-2">{{ item.qty }}</td>
              <td class="px-3 py-2">{{ item.unit }}</td>
              <td class="px-3 py-2">{{ item.note }}</td>
              <td class="px-3 py-2">{{ item.arrival_date ? new Date(item.arrival_date).toLocaleDateString('id-ID') : '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <h2 class="text-lg font-bold mb-2">Approval</h2>
        <div class="mb-2">
          <b>SSD Manager:</b>
          <span v-if="prFood.ssd_manager_approved_at">
            <span class="text-green-600 font-semibold">Approved</span>
            oleh <b>{{ prFood.ssd_manager?.nama_lengkap || prFood.ssd_manager_approved_by }}</b>
            pada {{ new Date(prFood.ssd_manager_approved_at).toLocaleString('id-ID') }}
            <span v-if="prFood.ssd_manager_note">- Note: {{ prFood.ssd_manager_note }}</span>
          </span>
          <span v-else class="text-gray-500">Belum di-approve</span>
        </div>
        <div class="mb-2">
          <b>Vice COO:</b>
          <span v-if="prFood.vice_coo_approved_at">
            <span class="text-green-600 font-semibold">Approved</span>
            oleh <b>{{ prFood.vice_coo?.nama_lengkap || prFood.vice_coo_approved_by }}</b>
            pada {{ new Date(prFood.vice_coo_approved_at).toLocaleString('id-ID') }}
            <span v-if="prFood.vice_coo_note">- Note: {{ prFood.vice_coo_note }}</span>
          </span>
          <span v-else class="text-gray-500">Belum di-approve</span>
        </div>
        <div v-if="canApproveSSD">
          <button @click="approveSSD(true)" class="px-4 py-2 rounded bg-green-600 text-white font-semibold hover:bg-green-700 mr-2">Approve (SSD Manager)</button>
          <button @click="approveSSD(false)" class="px-4 py-2 rounded bg-red-600 text-white font-semibold hover:bg-red-700">Reject</button>
        </div>
        <div v-if="canApproveCOO">
          <button @click="approveCOO(true)" class="px-4 py-2 rounded bg-green-600 text-white font-semibold hover:bg-green-700 mr-2">Approve (Vice COO)</button>
          <button @click="approveCOO(false)" class="px-4 py-2 rounded bg-red-600 text-white font-semibold hover:bg-red-700">Reject</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 