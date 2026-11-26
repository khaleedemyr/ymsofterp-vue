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

      <form class="space-y-5" @submit.prevent="submit">
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
                  <th class="px-2 py-2">Date Arrangement</th>
                  <th class="px-2 py-2 w-16">Qty</th>
                  <th class="px-2 py-2 w-16">Day(s)</th>
                  <th class="px-2 py-2 w-28">Rate ++</th>
                  <th class="px-2 py-2 w-28">Rate Nett</th>
                  <th class="px-2 py-2 w-28">Breakdown ++</th>
                  <th class="px-2 py-2 w-28">Breakdown Nett</th>
                  <th class="px-2 py-2 w-10"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in form.revenue_items" :key="idx" class="border-b">
                  <td class="px-2 py-1.5"><input v-model="row.date_arrangement" type="text" class="w-full rounded border-gray-300 text-xs" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.qty" type="number" min="0" class="w-full rounded border-gray-300 text-xs" @input="recalcRow(idx)" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.days" type="number" min="0" class="w-full rounded border-gray-300 text-xs" @input="recalcRow(idx)" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.rate" type="number" min="0" class="w-full rounded border-gray-300 text-xs" @input="recalcRow(idx)" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.rate_nett" type="number" min="0" class="w-full rounded border-gray-300 text-xs" @input="recalcRow(idx)" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.breakdown" type="number" min="0" class="w-full rounded border-gray-300 text-xs" /></td>
                  <td class="px-2 py-1.5"><input v-model.number="row.breakdown_nett" type="number" min="0" class="w-full rounded border-gray-300 text-xs" /></td>
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
                    <th class="px-2 py-2">Venue Set Up</th>
                    <th class="px-2 py-2 w-10"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in form.time_of_event" :key="idx" class="border-b">
                    <td class="px-2 py-1.5"><input v-model="row.time" type="text" class="w-full rounded border-gray-300 text-xs" placeholder="15:30" /></td>
                    <td class="px-2 py-1.5"><input v-model="row.meals" type="text" class="w-full rounded border-gray-300 text-xs" /></td>
                    <td class="px-2 py-1.5"><input v-model="row.venue_setup" type="text" class="w-full rounded border-gray-300 text-xs" /></td>
                    <td class="px-2 py-1.5 text-center">
                      <button type="button" class="text-red-500" @click="form.time_of_event.splice(idx, 1)"><i class="fa-solid fa-trash"></i></button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Menu</label>
                <textarea v-model="form.menu_items" rows="6" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Satu menu per baris..."></textarea>
              </div>
              <div class="space-y-3">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Kitchen Maintenance</label>
                  <textarea v-model="form.kitchen_maintenance" rows="3" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Marcom</label>
                  <textarea v-model="form.marcom" rows="3" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Billing Instruction / Accounting</label>
              <textarea v-model="form.billing_instruction" rows="4" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
            </div>
          </div>
        </section>

        <section class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80">
            <h2 class="text-sm font-semibold text-gray-800">Signatures (nama & jabatan)</h2>
          </div>
          <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <div class="text-xs font-semibold text-gray-500 mb-2">Prepared by</div>
              <input v-model="form.prepared_by_name" type="text" class="w-full rounded-lg border-gray-300 text-sm mb-2" placeholder="Nama" />
              <input v-model="form.prepared_by_title" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Jabatan" />
            </div>
            <div>
              <div class="text-xs font-semibold text-gray-500 mb-2">Acknowledge by (1)</div>
              <input v-model="form.acknowledge_by_1_name" type="text" class="w-full rounded-lg border-gray-300 text-sm mb-2" placeholder="Nama" />
              <input v-model="form.acknowledge_by_1_title" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Jabatan" />
            </div>
            <div>
              <div class="text-xs font-semibold text-gray-500 mb-2">Acknowledge by (2)</div>
              <input v-model="form.acknowledge_by_2_name" type="text" class="w-full rounded-lg border-gray-300 text-sm mb-2" placeholder="Nama" />
              <input v-model="form.acknowledge_by_2_title" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Jabatan" />
            </div>
            <div>
              <div class="text-xs font-semibold text-gray-500 mb-2">Approved by</div>
              <input v-model="form.approved_by_name" type="text" class="w-full rounded-lg border-gray-300 text-sm mb-2" placeholder="Nama" />
              <input v-model="form.approved_by_title" type="text" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Jabatan" />
            </div>
          </div>
          <p class="px-5 pb-4 text-xs text-gray-500">Gambar TTD muncul di PDF setelah approval memakai e-signature approver.</p>
        </section>

        <div class="flex justify-end gap-2 sticky bottom-0 z-10 py-3 bg-gradient-to-t from-gray-50 via-gray-50 to-transparent">
          <Link
            :href="reo ? route('sales-leads.reo.show', reo.id) : route('sales-leads.index')"
            class="px-4 py-2.5 rounded-lg text-sm border border-gray-300 bg-white text-gray-700"
          >
            Batal
          </Link>
          <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50" :disabled="form.processing">
            <i class="fa-solid fa-save mr-1.5"></i>
            {{ form.processing ? 'Menyimpan...' : 'Simpan Draft' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  lead: { type: Object, required: true },
  reo: { type: Object, default: null },
  defaults: { type: Object, default: null },
});

const isEdit = computed(() => !!props.reo?.id);
const source = props.reo || props.defaults || {};

const emptyRevenueRow = () => ({
  date_arrangement: '',
  qty: '',
  days: 1,
  rate: '',
  rate_nett: '',
  breakdown: '',
  breakdown_nett: '',
});

const form = useForm({
  address: source.address || '',
  contact_person: source.contact_person || '',
  mobile: source.mobile || '',
  email: source.email || '',
  date_of_issue: toDateInput(source.date_of_issue),
  venue_setup: source.venue_setup || '',
  sales_person_name: source.sales_person_name || props.lead.sales?.nama_lengkap || '',
  sign_board: source.sign_board || '',
  revenue_items: (source.revenue_items?.length ? source.revenue_items : [emptyRevenueRow()]).map((r) => ({ ...emptyRevenueRow(), ...r })),
  catering_fee_percent: source.catering_fee_percent ?? 30,
  total_restaurant_revenue: source.total_restaurant_revenue ?? '',
  total_transaction: source.total_transaction ?? '',
  total_amount: source.total_amount ?? '',
  pax: source.pax ?? props.lead.pax ?? '',
  date_of_event: toDateInput(source.date_of_event),
  time_of_event: (source.time_of_event?.length ? source.time_of_event : [{ time: '', meals: '', venue_setup: '' }]).map((r) => ({
    time: r.time || '',
    meals: r.meals || '',
    venue_setup: r.venue_setup || '',
  })),
  menu_items: source.menu_items || '',
  kitchen_maintenance: source.kitchen_maintenance || '',
  marcom: source.marcom || '',
  billing_instruction: source.billing_instruction || '',
  prepared_by_name: source.prepared_by_name || '',
  prepared_by_title: source.prepared_by_title || '',
  acknowledge_by_1_name: source.acknowledge_by_1_name || '',
  acknowledge_by_1_title: source.acknowledge_by_1_title || '',
  acknowledge_by_2_name: source.acknowledge_by_2_name || '',
  acknowledge_by_2_title: source.acknowledge_by_2_title || '',
  approved_by_name: source.approved_by_name || '',
  approved_by_title: source.approved_by_title || '',
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
  form.revenue_items.reduce((sum, r) => sum + (Number(r.breakdown_nett) || 0), 0)
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
const addTimeRow = () => form.time_of_event.push({ time: '', meals: '', venue_setup: '' });

const recalcRow = (idx) => {
  const row = form.revenue_items[idx];
  if (!row) return;
  const qty = Number(row.qty) || 0;
  const days = Number(row.days) || 0;
  const rate = Number(row.rate) || 0;
  const rateNett = Number(row.rate_nett) || 0;
  row.breakdown = qty * days * rate;
  row.breakdown_nett = qty * days * rateNett;
};

const submit = () => {
  if (isEdit.value) {
    form.put(route('sales-leads.reo.update', props.reo.id));
  } else {
    form.post(route('sales-leads.reo.store', props.lead.id));
  }
};
</script>
