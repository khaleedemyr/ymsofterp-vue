<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-link"></i> Non Food Payment — Asset Service
        </h1>
        <button type="button" class="text-gray-600 hover:text-gray-900 text-sm font-medium" @click="goBack">
          <i class="fa fa-arrow-left mr-1"></i> Kembali
        </button>
      </div>

      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-3">Service order</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
          <div>
            <dt class="text-gray-500">Nomor</dt>
            <dd class="font-semibold text-teal-700">{{ serviceOrder.number }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Supplier</dt>
            <dd>{{ serviceOrder.supplier_name }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Outlet</dt>
            <dd>{{ serviceOrder.outlet_name || '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Status</dt>
            <dd class="capitalize">{{ serviceOrder.status?.replace(/_/g, ' ') }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-gray-500">Deskripsi service</dt>
            <dd class="text-gray-800">{{ serviceOrder.description }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Estimasi biaya</dt>
            <dd>Rp {{ formatMoney(serviceOrder.estimated_cost) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Biaya aktual (dari return)</dt>
            <dd>Rp {{ formatMoney(serviceOrder.actual_cost) }}</dd>
          </div>
        </dl>
      </div>

      <form @submit.prevent="submit" class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 space-y-4">
        <input type="hidden" v-model="form.asset_service_order_id" />

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Supplier <span class="text-red-500">*</span></label>
          <input type="hidden" v-model="form.supplier_id" />
          <p class="text-gray-900 font-medium">{{ serviceOrder.supplier_name }} (ID {{ form.supplier_id }})</p>
          <p class="text-xs text-gray-500 mt-1">Harus sama dengan supplier di service order.</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah pembayaran <span class="text-red-500">*</span></label>
          <input
            v-model="form.amount"
            type="number"
            step="0.01"
            min="0"
            class="w-full rounded-lg border-gray-300 focus:ring-teal-500 focus:border-teal-500"
            required
          />
          <p class="text-xs text-gray-500 mt-1">Saran: Rp {{ formatMoney(serviceOrder.suggested_amount) }} (dari biaya aktual atau estimasi)</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Metode <span class="text-red-500">*</span></label>
          <select v-model="form.payment_method" class="w-full rounded-lg border-gray-300 focus:ring-teal-500 focus:border-teal-500" required>
            <option value="transfer">Transfer</option>
            <option value="cash">Cash</option>
            <option value="check">Cek</option>
          </select>
        </div>

        <div v-if="form.payment_method === 'transfer' || form.payment_method === 'check'">
          <label class="block text-sm font-medium text-gray-700 mb-1">Rekening bank <span class="text-red-500">*</span></label>
          <select v-model="form.bank_id" class="w-full rounded-lg border-gray-300 focus:ring-teal-500 focus:border-teal-500" :required="form.payment_method !== 'cash'">
            <option value="">Pilih bank</option>
            <option v-for="b in banks" :key="b.id" :value="b.id">
              {{ b.bank_name }} — {{ b.account_number }} ({{ b.outlet_name }})
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal pembayaran <span class="text-red-500">*</span></label>
          <input v-model="form.payment_date" type="date" class="w-full rounded-lg border-gray-300 focus:ring-teal-500 focus:border-teal-500" required />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
          <textarea v-model="form.description" rows="2" class="w-full rounded-lg border-gray-300 focus:ring-teal-500 focus:border-teal-500" placeholder="Opsional"></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
          <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border-gray-300 focus:ring-teal-500 focus:border-teal-500" placeholder="Opsional"></textarea>
        </div>

        <div class="flex gap-3 pt-2">
          <button
            type="submit"
            class="flex-1 py-3 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-semibold shadow disabled:opacity-50"
            :disabled="form.processing"
          >
            <i v-if="form.processing" class="fa fa-spinner fa-spin mr-2"></i>
            Simpan Non Food Payment
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  serviceOrder: { type: Object, required: true },
  banks: { type: Array, default: () => [] },
})

function formatMoney(v) {
  if (v == null || v === '') return '0'
  return Number(v).toLocaleString('id-ID')
}

function goBack() {
  router.visit(`/asset-service-orders/${props.serviceOrder.id}`)
}

const today = new Date().toISOString().slice(0, 10)

const form = useForm({
  asset_service_order_id: props.serviceOrder.id,
  purchase_order_ops_id: null,
  purchase_requisition_id: null,
  retail_non_food_id: null,
  supplier_id: props.serviceOrder.supplier_id,
  amount: props.serviceOrder.suggested_amount > 0 ? String(props.serviceOrder.suggested_amount) : '',
  payment_method: 'transfer',
  bank_id: '',
  payment_date: today,
  due_date: null,
  description: `Pembayaran vendor service — ${props.serviceOrder.number}`,
  reference_number: props.serviceOrder.number,
  notes: null,
})

function submit() {
  form.transform((data) => ({
    ...data,
    amount: Number(data.amount),
    bank_id: data.payment_method === 'cash' ? null : (data.bank_id || null),
    purchase_order_ops_id: null,
    purchase_requisition_id: null,
    retail_non_food_id: null,
  })).post('/non-food-payments', {
    preserveScroll: true,
    onSuccess: () => {},
  })
}
</script>
