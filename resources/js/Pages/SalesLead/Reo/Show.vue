<template>
  <AppLayout>
    <div class="w-full max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5">
        <div>
          <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <i class="fa-solid fa-file-signature text-amber-500"></i>
            {{ reo.number }}
          </h1>
          <p class="text-sm text-gray-500 mt-0.5">
            {{ reo.company_name }} · {{ lead?.number }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('sales-leads.index')"
            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
          >
            <i class="fa-solid fa-arrow-left text-xs"></i> List
          </Link>
          <a
            :href="route('sales-leads.reo.pdf', reo.id)"
            target="_blank"
            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm bg-slate-800 text-white hover:bg-slate-900"
          >
            <i class="fa-solid fa-file-pdf"></i> Export PDF
          </a>
          <Link
            v-if="canEdit"
            :href="route('sales-leads.reo.edit', reo.id)"
            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm bg-indigo-600 text-white hover:bg-indigo-700"
          >
            <i class="fa-solid fa-pen"></i> Edit
          </Link>
        </div>
      </div>

      <div class="mb-4">
        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold" :class="statusClass(reo.status)">
          {{ statuses[reo.status] || reo.status }}
        </span>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-4 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
        <div><span class="text-gray-500 text-xs">Company</span><div class="font-medium">{{ reo.company_name }}</div></div>
        <div><span class="text-gray-500 text-xs">Type of Event</span><div class="font-medium">{{ lead?.type_of_event || '-' }}</div></div>
        <div><span class="text-gray-500 text-xs">Date of Issue</span><div class="font-medium">{{ reo.date_of_issue || '-' }}</div></div>
        <div><span class="text-gray-500 text-xs">Contact</span><div class="font-medium">{{ reo.contact_person || '-' }} · {{ reo.mobile || '-' }}</div></div>
        <div><span class="text-gray-500 text-xs">Venue / Set Up</span><div class="font-medium">{{ reo.venue_setup || '-' }}</div></div>
        <div><span class="text-gray-500 text-xs">Sales Person</span><div class="font-medium">{{ reo.sales_person_name || '-' }}</div></div>
        <div><span class="text-gray-500 text-xs">PAX / Total Amount</span><div class="font-medium">{{ reo.pax ?? '-' }} · {{ formatMoney(reo.total_amount) }}</div></div>
        <div class="md:col-span-2"><span class="text-gray-500 text-xs">Address</span><div class="whitespace-pre-wrap">{{ reo.address || '-' }}</div></div>
        <div class="md:col-span-2"><span class="text-gray-500 text-xs">Sign Board</span><div class="whitespace-pre-wrap">{{ reo.sign_board || '-' }}</div></div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-4">
        <div class="px-5 py-3 border-b bg-gray-50 text-sm font-semibold">Revenue Breakdown</div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-xs">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left">Date Arrangement</th>
                <th class="px-3 py-2 text-center">Qty</th>
                <th class="px-3 py-2 text-center">Days</th>
                <th class="px-3 py-2 text-right">Rate ++</th>
                <th class="px-3 py-2 text-right">Rate Nett</th>
                <th class="px-3 py-2 text-right">Breakdown ++</th>
                <th class="px-3 py-2 text-right">Breakdown Nett</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in (reo.revenue_items || [])" :key="i" class="border-b">
                <td class="px-3 py-2">{{ row.date_arrangement }}</td>
                <td class="px-3 py-2 text-center">{{ row.qty }}</td>
                <td class="px-3 py-2 text-center">{{ row.days }}</td>
                <td class="px-3 py-2 text-right">{{ formatMoney(row.rate) }}</td>
                <td class="px-3 py-2 text-right">{{ formatMoney(row.rate_nett) }}</td>
                <td class="px-3 py-2 text-right">{{ formatMoney(row.breakdown) }}</td>
                <td class="px-3 py-2 text-right">{{ formatMoney(row.breakdown_nett) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="px-5 py-3 text-sm grid grid-cols-2 md:grid-cols-4 gap-2 border-t">
          <div>Fee {{ reo.catering_fee_percent }}%: <b>{{ formatMoney(reo.catering_fee_amount) }}</b></div>
          <div>Restaurant Rev: <b>{{ formatMoney(reo.total_restaurant_revenue) }}</b></div>
          <div>Transaction: <b>{{ formatMoney(reo.total_transaction) }}</b></div>
          <div>Total Amount: <b>{{ formatMoney(reo.total_amount) }}</b></div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-4">
        <h3 class="text-sm font-semibold mb-3">Approval Flow</h3>
        <div v-if="(reo.approvals || []).length" class="space-y-2 mb-4">
          <div
            v-for="flow in reo.approvals"
            :key="flow.id"
            class="flex items-center justify-between gap-3 border rounded-lg px-3 py-2 text-sm"
          >
            <div>
              <span class="text-xs text-gray-500 mr-2">Level {{ flow.approval_level }}</span>
              <span class="font-medium">{{ flow.approver?.nama_lengkap || '-' }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs px-2 py-0.5 rounded-full font-semibold" :class="approvalStatusClass(flow.status)">{{ flow.status }}</span>
              <img v-if="flow.signature_url" :src="flow.signature_url" alt="TTD" class="h-8 object-contain border rounded bg-white" />
            </div>
          </div>
        </div>
        <p v-else class="text-sm text-gray-500 mb-4">Belum ada approver. Submit REO untuk memilih approval.</p>

        <div v-if="canEdit" class="border-t pt-4 space-y-3">
          <div class="text-sm font-medium text-gray-700">Submit untuk Approval</div>
          <div class="relative">
            <input
              v-model="approverSearch"
              type="text"
              class="w-full rounded-lg border-gray-300 text-sm"
              placeholder="Cari approver..."
              @input="searchApprovers"
            />
            <div v-if="approverOptions.length" class="absolute z-20 mt-1 w-full bg-white border rounded-lg shadow max-h-48 overflow-auto">
              <button
                v-for="u in approverOptions"
                :key="u.id"
                type="button"
                class="w-full text-left px-3 py-2 text-sm hover:bg-emerald-50"
                @click="addApprover(u)"
              >
                <div class="font-medium">{{ u.nama_lengkap }}</div>
                <div class="text-xs text-gray-500">{{ u.jabatan || u.email }}</div>
              </button>
            </div>
          </div>
          <div v-if="selectedApprovers.length" class="space-y-1">
            <div
              v-for="(a, idx) in selectedApprovers"
              :key="a.id"
              class="flex items-center justify-between border rounded-lg px-3 py-2 text-sm"
            >
              <span>Level {{ idx + 1 }} — {{ a.nama_lengkap }}</span>
              <button type="button" class="text-red-500" @click="selectedApprovers.splice(idx, 1)"><i class="fa-solid fa-times"></i></button>
            </div>
          </div>
          <button
            type="button"
            class="px-4 py-2 rounded-lg text-sm bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50"
            :disabled="submitting || !selectedApprovers.length"
            @click="submitForApproval"
          >
            <i class="fa-solid fa-paper-plane mr-1"></i> Submit Approval
          </button>
        </div>

        <div v-if="canApprove" class="border-t pt-4 mt-4 flex flex-wrap gap-2">
          <button type="button" class="px-4 py-2 rounded-lg text-sm bg-emerald-600 text-white hover:bg-emerald-700" :disabled="acting" @click="approveReo">
            <i class="fa-solid fa-signature mr-1"></i> Approve (pakai E-Signature)
          </button>
          <button type="button" class="px-4 py-2 rounded-lg text-sm bg-red-600 text-white hover:bg-red-700" :disabled="acting" @click="rejectReo">
            <i class="fa-solid fa-xmark mr-1"></i> Reject
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  reo: { type: Object, required: true },
  lead: { type: Object, default: null },
  canEdit: { type: Boolean, default: false },
  canApprove: { type: Boolean, default: false },
  statuses: { type: Object, default: () => ({}) },
});

const selectedApprovers = ref([]);
const approverOptions = ref([]);
const approverSearch = ref('');
const submitting = ref(false);
const acting = ref(false);
let searchTimer = null;

const formatMoney = (v) => {
  if (v === null || v === undefined || v === '') return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v));
};

const statusClass = (s) => ({
  DRAFT: 'bg-gray-100 text-gray-700',
  SUBMITTED: 'bg-amber-100 text-amber-800',
  APPROVED: 'bg-emerald-100 text-emerald-800',
  REJECTED: 'bg-red-100 text-red-800',
}[s] || 'bg-gray-100 text-gray-700');

const approvalStatusClass = (s) => ({
  PENDING: 'bg-amber-100 text-amber-800',
  APPROVED: 'bg-emerald-100 text-emerald-800',
  REJECTED: 'bg-red-100 text-red-800',
}[s] || 'bg-gray-100 text-gray-700');

const searchApprovers = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(async () => {
    const q = approverSearch.value.trim();
    if (q.length < 2) {
      approverOptions.value = [];
      return;
    }
    try {
      const { data } = await axios.get(route('sales-leads.reo.approvers'), { params: { search: q } });
      approverOptions.value = (data.users || []).filter((u) => !selectedApprovers.value.find((s) => s.id === u.id));
    } catch (e) {
      console.error(e);
    }
  }, 250);
};

const addApprover = (u) => {
  if (!selectedApprovers.value.find((s) => s.id === u.id)) {
    selectedApprovers.value.push(u);
  }
  approverSearch.value = '';
  approverOptions.value = [];
};

const submitForApproval = async () => {
  if (!selectedApprovers.value.length) return;
  submitting.value = true;
  router.post(route('sales-leads.reo.submit', props.reo.id), {
    approvers: selectedApprovers.value.map((a) => a.id),
  }, {
    onFinish: () => { submitting.value = false; },
  });
};

const approveReo = async () => {
  const result = await Swal.fire({
    title: 'Approve REO?',
    text: 'Approval memakai e-signature Anda. Pastikan sudah tersimpan di menu E-Signature.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Approve',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#059669',
  });
  if (!result.isConfirmed) return;
  acting.value = true;
  router.post(route('sales-leads.reo.approve', props.reo.id), {}, {
    onFinish: () => { acting.value = false; },
  });
};

const rejectReo = async () => {
  const { value: notes, isConfirmed } = await Swal.fire({
    title: 'Reject REO?',
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    showCancelButton: true,
    confirmButtonText: 'Reject',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
  });
  if (!isConfirmed) return;
  acting.value = true;
  router.post(route('sales-leads.reo.reject', props.reo.id), { notes }, {
    onFinish: () => { acting.value = false; },
  });
};
</script>
