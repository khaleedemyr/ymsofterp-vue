<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="$emit('close')">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col relative animate-fadeIn overflow-hidden">
      <!-- Header -->
      <div class="bg-gradient-to-r from-slate-700 to-slate-800 text-white px-6 py-4 flex items-center justify-between shrink-0">
        <div>
          <h2 class="text-lg font-bold">Detail Order</h2>
          <p class="text-slate-300 text-sm mt-0.5">{{ order.nomor }}{{ order.paid_number ? ` · ${order.paid_number}` : '' }}</p>
        </div>
        <button
          type="button"
          @click="$emit('close')"
          class="p-2 rounded-lg text-slate-300 hover:bg-white/10 hover:text-white transition"
          aria-label="Tutup"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>

      <div class="overflow-y-auto flex-1 p-6 space-y-5">
        <!-- Info Order (hanya field yang user-friendly) -->
        <section class="bg-slate-50 rounded-xl p-4">
          <h3 class="text-sm font-semibold text-slate-600 uppercase tracking-wide mb-3">Info Transaksi</h3>
          <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <template v-for="key in displayOrderKeys" :key="key">
              <template v-if="order[key] !== undefined && order[key] !== null">
                <span class="text-slate-500">{{ formatKey(key) }}</span>
                <span class="text-slate-800 font-medium text-right">{{ formatValue(key, order[key]) }}</span>
              </template>
            </template>
          </div>
        </section>

        <!-- Payments -->
        <section v-if="order.payments && order.payments.length" class="bg-violet-50 rounded-xl p-4">
          <h3 class="text-sm font-semibold text-violet-700 uppercase tracking-wide mb-2">Pembayaran</h3>
          <div class="space-y-1.5">
            <div
              v-for="(payment, idx) in order.payments"
              :key="idx"
              class="flex justify-between items-center text-sm bg-white/60 rounded-lg px-3 py-2"
            >
              <span class="font-medium text-slate-700">{{ payment.payment_code || payment.payment_type || 'Payment' }}</span>
              <span class="font-semibold text-violet-800">{{ formatCurrency(paymentAmount(payment)) }}</span>
            </div>
          </div>
        </section>

        <!-- Manual Discount -->
        <section v-if="manualDiscountVisible" class="bg-amber-50 rounded-xl p-4">
          <h3 class="text-sm font-semibold text-amber-800 uppercase tracking-wide mb-2">Diskon Manual</h3>
          <div class="flex justify-between text-sm">
            <span class="text-slate-600">Nominal</span>
            <span class="font-semibold text-amber-900">{{ formatCurrency(order.manual_discount_amount) }}</span>
          </div>
          <div v-if="order.manual_discount_reason" class="mt-1.5 text-sm text-slate-600">
            Alasan: {{ order.manual_discount_reason }}
          </div>
        </section>

        <!-- Promo Discount (ringkas) -->
        <section v-if="promoDiscountVisible" class="bg-emerald-50 rounded-xl p-4">
          <h3 class="text-sm font-semibold text-emerald-800 uppercase tracking-wide mb-1">Total Diskon Promo</h3>
          <p class="text-lg font-bold text-emerald-900">{{ formatCurrency(promoDiscountAmount) }}</p>
          <div v-if="parsedPromoDiscountInfo.length" class="mt-2 space-y-1 text-sm">
            <div v-for="(p, i) in parsedPromoDiscountInfo" :key="i" class="flex justify-between text-slate-700">
              <span>{{ p.promo_name }}</span>
              <span class="font-medium">{{ formatCurrency(p.discount_amount) }}</span>
            </div>
          </div>
        </section>

        <!-- Items -->
        <section v-if="order.items && order.items.length" class="bg-sky-50 rounded-xl p-4">
          <h3 class="text-sm font-semibold text-sky-800 uppercase tracking-wide mb-3">Item Pesanan</h3>
          <div class="overflow-x-auto rounded-lg border border-sky-200 bg-white">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="bg-sky-100 text-sky-900">
                  <th class="px-3 py-2.5 text-left font-semibold">No</th>
                  <th class="px-3 py-2.5 text-left font-semibold">Nama Item</th>
                  <th class="px-3 py-2.5 text-right font-semibold">Qty</th>
                  <th class="px-3 py-2.5 text-right font-semibold">Harga</th>
                  <th class="px-3 py-2.5 text-right font-semibold">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(item, idx) in order.items"
                  :key="item.id || idx"
                  class="border-t border-sky-100 hover:bg-sky-50/50"
                >
                  <td class="px-3 py-2">{{ idx + 1 }}</td>
                  <td class="px-3 py-2">
                    <div class="font-medium text-slate-800">{{ item.item_name || '-' }}</div>
                    <div v-if="normalizedModifiers(item).length" class="mt-1 space-y-1">
                      <div
                        v-for="(modifier, mIdx) in normalizedModifiers(item)"
                        :key="`mod-${idx}-${mIdx}`"
                        class="text-xs text-slate-600"
                      >
                        • Modifier: {{ modifier }}
                      </div>
                    </div>
                    <div v-if="normalizedNotes(item)" class="mt-1 text-xs text-amber-700">
                      • Notes: {{ normalizedNotes(item) }}
                    </div>
                  </td>
                  <td class="px-3 py-2 text-right">{{ item.qty ?? 0 }}</td>
                  <td class="px-3 py-2 text-right">{{ formatCurrency(item.price ?? 0) }}</td>
                  <td class="px-3 py-2 text-right font-medium">{{ formatCurrency(item.subtotal ?? 0) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Promo (kartu promo yang dipakai) -->
        <section v-if="order.promo && !isPromoEmpty(order.promo)" class="bg-blue-50 rounded-xl p-4">
          <h3 class="text-sm font-semibold text-blue-800 uppercase tracking-wide mb-2">Promo Diterapkan</h3>
          <div class="flex flex-wrap gap-2">
            <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-100 text-blue-900 font-medium text-sm">
              {{ order.promo.name || order.promo.code || 'Promo' }}
            </span>
            <span v-if="order.promo.code" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-200/70 text-blue-900 text-sm">
              {{ order.promo.code }}
            </span>
            <span v-if="order.promo.type" class="text-slate-600 text-sm">{{ order.promo.type }} {{ order.promo.value != null ? `· ${order.promo.value}` : '' }}</span>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  order: { type: Object, required: true },
});

// Hanya field yang ditampilkan di "Info Transaksi" — tidak ada raw JSON
const displayOrderKeys = [
  'nomor',
  'paid_number',
  'nama_outlet',
  'kode_outlet',
  'table',
  'waiters',
  'mode',
  'pax',
  'created_at',
  'total',
  'discount',
  'service',
  'grand_total',
  'status',
  'member_name',
];

function formatKey(key) {
  const keyMap = {
    created_at: 'Waktu',
    waiters: 'Waiter',
    mode: 'Mode',
    nomor: 'No. Order',
    paid_number: 'Paid Number',
    table: 'Meja',
    pax: 'Pax',
    total: 'Subtotal',
    discount: 'Diskon',
    cashback: 'Cashback',
    dpp: 'DPP',
    pb1: 'PB1',
    service: 'Service',
    grand_total: 'Grand Total',
    status: 'Status',
    kode_outlet: 'Kode Outlet',
    nama_outlet: 'Nama Outlet',
    member_name: 'Member',
  };
  return keyMap[key] || key.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
}

function formatValue(key, val) {
  if (key.match(/total|discount|cashback|service|pb1|grand|dpp|rounding/i)) {
    if (typeof val === 'number') return val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  }
  if (key === 'created_at') {
    return val ? new Date(val).toLocaleString('id-ID') : '-';
  }
  if (val === '' || val == null) return '-';
  return val;
}

function formatCurrency(val) {
  if (typeof val === 'number') return val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  if (val == null || val === '') return '-';
  const num = Number(val);
  if (!isNaN(num)) return num.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  return String(val);
}

function paymentAmount(payment) {
  const amount = Number(payment.amount) || 0;
  const change = Number(payment.change) || 0;
  return amount - change;
}

function normalizedNotes(item) {
  const note = item?.notes;
  if (note == null) return '';

  const text = String(note).trim();
  if (!text || text.toLowerCase() === 'null') return '';

  return text;
}

function normalizedModifiers(item) {
  if (Array.isArray(item?.modifiers_formatted) && item.modifiers_formatted.length) {
    return item.modifiers_formatted
      .map((value) => String(value ?? '').trim())
      .filter(Boolean);
  }

  const raw = item?.modifiers;
  if (raw == null) return [];

  if (Array.isArray(raw)) {
    return raw.map((value) => stringifyModifier(value)).filter(Boolean);
  }

  const rawString = String(raw).trim();
  if (!rawString || rawString.toLowerCase() === 'null') return [];

  try {
    const parsed = JSON.parse(rawString);
    if (Array.isArray(parsed)) {
      return parsed.map((value) => stringifyModifier(value)).filter(Boolean);
    }

    if (parsed && typeof parsed === 'object') {
      const flattened = [];
      Object.entries(parsed).forEach(([key, value]) => {
        if (Array.isArray(value)) {
          value.forEach((child) => {
            const rendered = stringifyModifier(child);
            if (rendered) flattened.push(`${key}: ${rendered}`);
          });
          return;
        }

        const rendered = stringifyModifier(value);
        flattened.push(rendered ? `${key}: ${rendered}` : key);
      });
      return flattened.filter(Boolean);
    }

    const primitive = stringifyModifier(parsed);
    return primitive ? [primitive] : [];
  } catch (_) {
    return [rawString];
  }
}

function stringifyModifier(value) {
  if (value == null) return '';
  if (typeof value === 'string') return value.trim();
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);

  if (typeof value === 'object') {
    if (value.name) return String(value.name);
    if (value.label) return String(value.label);
    if (value.option_name) return String(value.option_name);
    if (value.option) return String(value.option);
    if (value.title) return String(value.title);

    return Object.values(value)
      .map((v) => (v == null ? '' : String(v).trim()))
      .filter(Boolean)
      .join(' - ');
  }

  return '';
}

function isPromoEmpty(promo) {
  if (!promo || typeof promo !== 'object') return true;
  return !promo.name && !promo.code && promo.id == null && promo.value == null;
}


const manualDiscountVisible = computed(() => {
  const val = props.order.manual_discount_amount;
  return val !== undefined && val !== null && String(val).replace(/\D/g, '') !== '' && Number(val) > 0;
});

const promoDiscountAmount = computed(() => {
  const discount = parseInt(props.order.discount, 10) || 0;
  const manualDiscount = parseFloat(props.order.manual_discount_amount) || 0;
  if (discount > 0 && manualDiscount > 0) return Math.max(discount, manualDiscount);
  return discount + manualDiscount;
});

const promoDiscountVisible = computed(() => promoDiscountAmount.value > 0);

// Parse promo_discount_info JSON agar tampil rapi (bukan raw string)
const parsedPromoDiscountInfo = computed(() => {
  const raw = props.order.promo_discount_info;
  if (!raw || typeof raw !== 'string') return [];
  const trimmed = raw.trim();
  if (!trimmed || (trimmed[0] !== '{' && trimmed[0] !== '[')) return [];
  try {
    const data = JSON.parse(raw);
    if (data && Array.isArray(data.promos)) {
      return data.promos.map((p) => ({
        promo_name: p.promo_name || p.name || 'Promo',
        discount_amount: p.discount_amount ?? p.amount ?? 0,
      }));
    }
  } catch (_) {
    return [];
  }
  return [];
});
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(40px); }
  to { opacity: 1; transform: none; }
}
.animate-fadeIn {
  animation: fadeIn 0.25s;
}
</style> 