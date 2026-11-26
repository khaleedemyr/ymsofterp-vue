<template>
  <AppLayout>
    <div class="w-full max-w-5xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5">
        <div>
          <h1 class="text-xl font-bold text-gray-900">{{ isEdit ? 'Edit' : 'Buat' }} Restaurant Event Order</h1>
          <p class="text-sm text-gray-500 mt-0.5">
            {{ lead.number }} · {{ lead.company_name }}
            <span v-if="reo?.number"> · {{ reo.number }}</span>
          </p>
        </div>
        <Link
          :href="reo ? route('sales-leads.reo.show', reo.id) : route('sales-leads.index')"
          class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
        >
          <i class="fa-solid fa-arrow-left text-xs"></i> Kembali
        </Link>
      </div>

      <form class="space-y-5" @submit.prevent="saveDraft">
        <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80">
            <h2 class="text-sm font-semibold text-gray-800">Header</h2>
          </div>
          <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Company</label>
              <input :value="lead.company_name" type="text" readonly class="w-full rounded-lg border-gray-300 bg-gray-50 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Type of Event</label>
              <input :value="lead.type_of_event || '-'" type="text" readonly class="w-full rounded-lg border-gray-300 bg-gray-50 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">REO No</label>
              <input :value="reo?.number || 'Auto generate saat simpan'" type="text" readonly class="w-full rounded-lg border-gray-300 bg-gray-50 text-sm" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
              <textarea v-model="form.address" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Alamat..."></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
              <input v-model="form.contact_person" type="text" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date of Issue</label>
              <input v-model="form.date_of_issue" type="date" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Mobile</label>
              <input v-model="form.mobile" type="text" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Venue / Set Up</label>
              <input v-model="form.venue_setup" type="text" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input v-model="form.email" type="email" class="w-full rounded-lg border-gray-300 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sales Person</label>
              <input v-model="form.sales_person_name" type="text" readonly class="w-full rounded-lg border-gray-300 bg-gray-50 text-sm" />
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Sign Board</label>
              <textarea v-model="form.sign_board" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Teks sign board..."></textarea>
            </div>
          </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800">Revenue Breakdown</h2>
            <button type="button" class="text-xs px-2.5 py-1.5 rounded-md bg-emerald-600 text-white hover:bg-emerald-700" @click="addRevenueRow">
              <i class="fa-solid fa-plus mr-1"></i> Tambah baris
            </button>
          </div>
          <div class="p-4 overflow-x-auto">
            <table class="min-w-full text-xs">
              <thead>
                <tr class="bg-gray-50 text-left">
                  <th class="px-2 py-2 w-36">Date</th>
                  <th class="px-2 py-2">Arrangement</th>
                  <th class="px-2 py-2 w-16">Qty</th>
                  <th class="px-2 py-2 w-16">Day(s)</th>
                  <th class="px-2 py-2 w-28">Rate ++</th>
                  <th class="px-2 py-2 w-28">Rate Nett</th>
                  <th class="px-2 py-2 w-28">Total Amount ++</th>
                  <th class="px-2 py-2 w-28">Total Amount Nett</th>
                  <th class="px-2 py-2 w-10"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in form.revenue_items" :key="idx" class="border-b">
                  <td class="px-2 py-1.5"><input v-model="row.date" type="date" class="w-full rounded border-gray-300 text-xs" /></td>
                  <td class="px-2 py-1.5"><input v-model="row.arrangement" type="text" class="w-full rounded border-gray-300 text-xs" placeholder="Arrangement" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.qty" type="number" min="0" class="w-full rounded border-gray-300 text-xs" @input="recalcRow(idx)" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.days" type="number" min="0" class="w-full rounded border-gray-300 text-xs" @input="recalcRow(idx)" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.rate" type="number" min="0" class="w-full rounded border-gray-300 text-xs" @input="recalcRow(idx)" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.rate_nett" type="number" min="0" class="w-full rounded border-gray-300 text-xs" @input="recalcRow(idx)" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.total_amount" type="number" min="0" class="w-full rounded border-gray-300 text-xs" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.total_amount_nett" type="number" min="0" class="w-full rounded border-gray-300 text-xs" /></td>
                  <td class="px-2 py-1.5 text-center">
                    <button type="button" class="text-red-500 hover:text-red-700" @click="removeRevenueRow(idx)"><i class="fa-solid fa-trash"></i></button>
                  </td>
                </tr>
              </tbody>
            </table>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
              <div>
                <label class="block text-xs text-gray-500 mb-1">Catering Fee %</label>
                <input v-model.number="form.catering_fee_percent" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border-gray-300 text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Total Restaurant Revenue</label>
                <input v-model.number="form.total_restaurant_revenue" type="number" min="0" class="w-full rounded-lg border-gray-300 text-sm" :placeholder="String(computedSubtotalNett)" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Total Transaction</label>
                <input v-model.number="form.total_transaction" type="number" min="0" class="w-full rounded-lg border-gray-300 text-sm" :placeholder="String(computedFee)" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Total Amount</label>
                <input v-model.number="form.total_amount" type="number" min="0" class="w-full rounded-lg border-gray-300 text-sm" :placeholder="String(computedTotal)" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">PAX</label>
                <input v-model.number="form.pax" type="number" min="0" class="w-full rounded-lg border-gray-300 text-sm" />
              </div>
              <div class="text-xs text-gray-500 self-end pb-2">
                Subtotal Nett: <b>{{ formatMoney(computedSubtotalNett) }}</b><br />
                Fee {{ form.catering_fee_percent }}%: <b>{{ formatMoney(computedFee) }}</b>
              </div>
            </div>
          </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800">Event Detail</h2>
            <button type="button" class="text-xs px-2.5 py-1.5 rounded-md bg-emerald-600 text-white hover:bg-emerald-700" @click="addTimeRow">
              <i class="fa-solid fa-plus mr-1"></i> Time row
            </button>
          </div>
          <div class="p-5 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date of Event</label>
              <input v-model="form.date_of_event" type="date" class="w-full sm:w-60 rounded-lg border-gray-300 text-sm" />
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full text-xs">
                <thead>
                  <tr class="bg-gray-50 text-left">
                    <th class="px-2 py-2">Time</th>
                    <th class="px-2 py-2">Meals</th>
                    <th class="px-2 py-2">Venue</th>
                    <th class="px-2 py-2 w-20">Pax</th>
                    <th class="px-2 py-2">Set Up</th>
                    <th class="px-2 py-2 w-10"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in form.time_of_event" :key="idx" class="border-b">
                    <td class="px-2 py-1.5"><input v-model="row.time" type="text" class="w-full rounded border-gray-300 text-xs" placeholder="15:30" /></td>
                    <td class="px-2 py-1.5"><input v-model="row.meals" type="text" class="w-full rounded border-gray-300 text-xs" /></td>
                    <td class="px-2 py-1.5"><input v-model="row.venue" type="text" class="w-full rounded border-gray-300 text-xs" placeholder="Venue" /></td>
                    <td class="px-2 py-1.5"><input v-model.number="row.pax" type="number" min="0" class="w-full rounded border-gray-300 text-xs" placeholder="0" /></td>
                    <td class="px-2 py-1.5"><input v-model="row.set_up" type="text" class="w-full rounded border-gray-300 text-xs" placeholder="Set up" /></td>
                    <td class="px-2 py-1.5 text-center">
                      <button type="button" class="text-red-500" @click="form.time_of_event.splice(idx, 1)"><i class="fa-solid fa-trash"></i></button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kitchen</label>
                <textarea
                  v-model="form.kitchen_maintenance"
                  rows="5"
                  class="w-full rounded-lg border-gray-300 text-sm"
                  placeholder="All Main course are served with&#10;Potato : ...&#10;Vegetables : ...&#10;Sauce : ..."
                ></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Maintenance</label>
                <textarea
                  v-model="form.maintenance"
                  rows="5"
                  class="w-full rounded-lg border-gray-300 text-sm"
                  placeholder="Kindly to support all the preparation for Kitchen Equipment"
                ></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Billing Instruction / Accounting</label>
                <textarea v-model="form.billing_instruction" rows="5" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Marcom</label>
                <textarea v-model="form.marcom" rows="5" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
              </div>
            </div>
          </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80 rounded-t-xl">
            <h2 class="text-sm font-semibold text-gray-800">Approval Flow</h2>
          </div>
          <div class="p-5 space-y-4 overflow-visible">
            <p class="text-sm text-gray-600">
              Tambahkan approver dari level terendah ke tertinggi (seperti Purchase Requisition Ops).
              Saat approve, sistem memakai e-signature approver untuk TTD di PDF.
            </p>
            <div class="relative z-30">
              <input
                v-model="approverSearch"
                type="text"
                placeholder="Cari user by nama, email, atau jabatan..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
                @focus="approverSearch.length >= 2 && loadApprovers(approverSearch)"
              />
              <div
                v-if="showApproverDropdown && approverResults.length"
                class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto"
              >
                <button
                  v-for="user in approverResults"
                  :key="user.id"
                  type="button"
                  class="w-full text-left px-3 py-2 hover:bg-gray-100 border-b border-gray-200 last:border-b-0"
                  @click="addApprover(user)"
                >
                  <div class="font-medium text-sm">{{ user.nama_lengkap || user.name }}</div>
                  <div class="text-xs text-gray-600">{{ user.email }}</div>
                  <div v-if="user.jabatan" class="text-xs text-emerald-700 font-medium">{{ user.jabatan }}</div>
                </button>
              </div>
            </div>

            <div v-if="approvers.length" class="space-y-2">
              <h4 class="font-medium text-gray-700 text-sm">Approval Order (Lowest → Highest)</h4>
              <div
                v-for="(approver, index) in approvers"
                :key="approver.id"
                class="flex items-center justify-between p-3 rounded-md bg-gray-50 border border-gray-200"
              >
                <div class="flex items-center gap-3">
                  <div class="flex items-center gap-1">
                    <button v-if="index > 0" type="button" class="p-1 text-gray-500 hover:text-gray-700" title="Move Up" @click="reorderApprover(index, index - 1)">
                      <i class="fa fa-arrow-up"></i>
                    </button>
                    <button v-if="index < approvers.length - 1" type="button" class="p-1 text-gray-500 hover:text-gray-700" title="Move Down" @click="reorderApprover(index, index + 1)">
                      <i class="fa fa-arrow-down"></i>
                    </button>
                  </div>
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    Level {{ index + 1 }}
                  </span>
                  <div>
                    <div class="font-medium text-sm">{{ approver.nama_lengkap || approver.name }}</div>
                    <div class="text-xs text-gray-600">{{ approver.email }}</div>
                    <div v-if="approver.jabatan" class="text-xs text-emerald-700 font-medium">{{ approver.jabatan }}</div>
                  </div>
                </div>
                <button type="button" class="p-1 text-red-500 hover:text-red-700" title="Remove" @click="removeApprover(index)">
                  <i class="fa fa-trash"></i>
                </button>
              </div>
            </div>
            <p v-else class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
              Untuk Submit, minimal 1 approver. Draft boleh tanpa approver.
            </p>
          </div>
        </section>

        <div class="flex justify-end gap-2 sticky bottom-0 z-10 py-3 bg-gradient-to-t from-gray-50 via-gray-50 to-transparent">
          <Link
            :href="reo ? route('sales-leads.reo.show', reo.id) : route('sales-leads.index')"
            class="px-4 py-2.5 rounded-lg text-sm border border-gray-300 bg-white text-gray-700"
          >
            Batal
          </Link>
          <button
            type="button"
            class="px-5 py-2.5 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50"
            :disabled="form.processing"
            @click="saveDraft"
          >
            <i class="fa-solid fa-save mr-1.5"></i>
            {{ form.processing && !submitNow ? 'Menyimpan...' : 'Simpan Draft' }}
          </button>
          <button
            type="button"
            class="px-5 py-2.5 rounded-lg text-sm font-medium bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50"
            :disabled="form.processing"
            @click="submitForApproval"
          >
            <i class="fa-solid fa-paper-plane mr-1.5"></i>
            {{ form.processing && submitNow ? 'Submitting...' : 'Submit' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  lead: { type: Object, required: true },
  reo: { type: Object, default: null },
  defaults: { type: Object, default: null },
});

const isEdit = computed(() => !!props.reo?.id);
const source = props.reo || props.defaults || {};
const submitNow = ref(false);
const approvers = ref([]);
const approverSearch = ref('');
const approverResults = ref([]);
const showApproverDropdown = ref(false);
let approverTimer = null;

const emptyRevenueRow = () => ({
  date: '',
  arrangement: '',
  qty: '',
  days: 1,
  rate: '',
  rate_nett: '',
  total_amount: '',
  total_amount_nett: '',
});

const normalizeRevenueRow = (r = {}) => {
  // Backward compatible with old date_arrangement / breakdown fields
  let date = r.date || '';
  let arrangement = r.arrangement || '';
  if (!date && !arrangement && r.date_arrangement) {
    const s = String(r.date_arrangement);
    const m = s.match(/^(\d{4}-\d{2}-\d{2})\s*(.*)$/);
    if (m) {
      date = m[1];
      arrangement = m[2] || '';
    } else {
      arrangement = s;
    }
  }
  return {
    ...emptyRevenueRow(),
    ...r,
    date,
    arrangement,
    total_amount: r.total_amount ?? r.breakdown ?? '',
    total_amount_nett: r.total_amount_nett ?? r.breakdown_nett ?? '',
  };
};

const form = useForm({
  address: source.address || '',
  contact_person: source.contact_person || '',
  mobile: source.mobile || '',
  email: source.email || '',
  date_of_issue: toDateInput(source.date_of_issue),
  venue_setup: source.venue_setup || '',
  sales_person_name: source.sales_person_name || props.lead.sales?.nama_lengkap || '',
  sign_board: source.sign_board || '',
  revenue_items: (source.revenue_items?.length ? source.revenue_items : [emptyRevenueRow()]).map(normalizeRevenueRow),
  catering_fee_percent: source.catering_fee_percent ?? 30,
  total_restaurant_revenue: source.total_restaurant_revenue ?? '',
  total_transaction: source.total_transaction ?? '',
  total_amount: source.total_amount ?? '',
  pax: source.pax ?? props.lead.pax ?? '',
  date_of_event: toDateInput(source.date_of_event),
  time_of_event: (source.time_of_event?.length
    ? source.time_of_event
    : [{ time: '', meals: '', venue: '', pax: '', set_up: '' }]
  ).map((r) => ({
    time: r.time || '',
    meals: r.meals || '',
    // backward compatible with old venue_setup field
    venue: r.venue || r.venue_setup || '',
    pax: r.pax ?? '',
    set_up: r.set_up || '',
  })),
  kitchen_maintenance: source.kitchen_maintenance || '',
  maintenance: source.maintenance || '',
  marcom: source.marcom || '',
  billing_instruction: source.billing_instruction || '',
  approvers: [],
  submit_now: false,
});

function toDateInput(d) {
  if (!d) return '';
  const s = String(d);
  if (s.includes('T')) {
    const dt = new Date(s);
    if (!Number.isNaN(dt.getTime())) {
      return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
    }
  }
  const m = s.match(/^(\d{4}-\d{2}-\d{2})/);
  return m ? m[1] : s.substring(0, 10);
}

const computedSubtotalNett = computed(() =>
  form.revenue_items.reduce((sum, r) => sum + (Number(r.total_amount_nett) || 0), 0)
);
const computedFee = computed(() =>
  Math.round(computedSubtotalNett.value * ((Number(form.catering_fee_percent) || 0) / 100))
);
const computedTotal = computed(() => computedSubtotalNett.value + computedFee.value);

const formatMoney = (v) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(v) || 0);

const addRevenueRow = () => form.revenue_items.push(emptyRevenueRow());
const removeRevenueRow = (idx) => {
  form.revenue_items.splice(idx, 1);
  if (!form.revenue_items.length) addRevenueRow();
};
const addTimeRow = () => form.time_of_event.push({ time: '', meals: '', venue: '', pax: '', set_up: '' });

const recalcRow = (idx) => {
  const row = form.revenue_items[idx];
  if (!row) return;
  const qty = Number(row.qty) || 0;
  const days = Number(row.days) || 0;
  const rate = Number(row.rate) || 0;
  const rateNett = Number(row.rate_nett) || 0;
  row.total_amount = qty * days * rate;
  row.total_amount_nett = qty * days * rateNett;
};

const loadApprovers = async (search = '') => {
  try {
    const { data } = await axios.get(route('sales-leads.reo.approvers'), { params: { search } });
    approverResults.value = (data.users || []).filter((u) => !approvers.value.find((a) => a.id === u.id));
    showApproverDropdown.value = true;
  } catch (e) {
    console.error(e);
  }
};

watch(approverSearch, (val) => {
  clearTimeout(approverTimer);
  if (!val || val.length < 2) {
    approverResults.value = [];
    showApproverDropdown.value = false;
    return;
  }
  approverTimer = setTimeout(() => loadApprovers(val), 250);
});

const addApprover = (user) => {
  if (!approvers.value.find((a) => a.id === user.id)) {
    approvers.value.push(user);
  }
  approverSearch.value = '';
  approverResults.value = [];
  showApproverDropdown.value = false;
};

const removeApprover = (index) => {
  approvers.value.splice(index, 1);
};

const reorderApprover = (fromIndex, toIndex) => {
  if (toIndex < 0 || toIndex >= approvers.value.length) return;
  const item = approvers.value.splice(fromIndex, 1)[0];
  approvers.value.splice(toIndex, 0, item);
};

const persist = (asSubmit) => {
  submitNow.value = !!asSubmit;
  form.submit_now = asSubmit ? 1 : 0;
  form.approvers = approvers.value.map((a) => a.id);

  if (asSubmit && !approvers.value.length) {
    Swal.fire('Warning', 'Pilih minimal 1 approver sebelum submit.', 'warning');
    return;
  }

  if (isEdit.value) {
    form.put(route('sales-leads.reo.update', props.reo.id));
  } else {
    form.post(route('sales-leads.reo.store', props.lead.id));
  }
};

const saveDraft = () => persist(false);
const submitForApproval = () => persist(true);
</script>
