<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <div>
          <Link
            href="/manual-point"
            class="text-blue-600 hover:text-blue-800 mb-2 inline-flex items-center gap-2"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar
          </Link>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-coins"></i> Inject Point Manual (Backfill POS)
          </h1>
        </div>
      </div>

      <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded">
        <div class="flex">
          <div class="flex-shrink-0">
            <i class="fa-solid fa-info-circle text-blue-500"></i>
          </div>
          <div class="ml-3 text-sm text-blue-700 space-y-1">
            <p>
              Sama dengan poin dari POS: isi <strong>nilai transaksi (Rp)</strong> dan
              <strong>paid_number / nomor bill</strong> dari struk. Poin dihitung otomatis dari tier member,
              channel, serta aturan sisa desimal seperti API <code class="bg-blue-100 px-1 rounded">/points/earn</code>.
            </p>
            <p>
              Belanja rolling 12 bulan dan tier ikut ter-update. Expiry poin 1 tahun dari tanggal transaksi.
              Nomor bill yang sama tidak bisa didobel jika sudah ada transaksi earn.
            </p>
          </div>
        </div>
      </div>

      <form @submit.prevent="submitForm" class="bg-white rounded-xl shadow-lg p-6 space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Pilih Member <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <input
              type="text"
              v-model="memberSearch"
              @input="searchMembersDebounced"
              @blur="searchMembersNow"
              placeholder="ID database (boleh 1 angka, mis. 1), kode member, nama, email, atau HP — min. 2 huruf untuk teks"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
            <div v-if="searchingMembers" class="absolute right-3 top-3">
              <i class="fa fa-spinner fa-spin text-gray-400"></i>
            </div>
            <div
              v-if="memberOptions.length > 0 && memberSearch && (memberSearch.trim().length >= 2 || /^\d+$/.test(memberSearch.trim()))"
              class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
            >
              <div
                v-for="member in memberOptions"
                :key="member.id"
                @click="selectMember(member)"
                class="px-4 py-3 hover:bg-blue-50 cursor-pointer border-b last:border-b-0"
              >
                <div class="flex items-center justify-between">
                  <div class="flex-1">
                    <div class="font-semibold text-gray-900">{{ member.nama_lengkap }}</div>
                    <div class="text-sm text-gray-600">
                      {{ member.member_id }} | {{ member.email }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      Level: {{ member.member_level || 'Silver' }}
                    </div>
                  </div>
                  <div class="text-sm text-blue-600 font-semibold ml-4">
                    {{ formatNumber(member.just_points || 0) }} points
                  </div>
                </div>
              </div>
            </div>
            <div
              v-if="memberSearch && (memberSearch.trim().length >= 2 || /^\d+$/.test(memberSearch.trim())) && memberOptions.length === 0 && !searchingMembers"
              class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg p-4 text-center text-gray-500"
            >
              Tidak ada member yang ditemukan
            </div>
          </div>
          <div v-if="selectedMember" class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex items-center justify-between">
              <div>
                <div class="font-semibold text-gray-900">{{ selectedMember.nama_lengkap }}</div>
                <div class="text-sm text-gray-600">{{ selectedMember.member_id }} | {{ selectedMember.email }}</div>
              </div>
              <button type="button" @click="clearMember" class="text-red-600 hover:text-red-800">
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
          </div>
          <p v-if="!selectedMember && memberSearch && !form.member_id" class="mt-2 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2">
            <i class="fa-solid fa-hand-pointer mr-1"></i>
            <strong>Member belum terpilih.</strong> Ketik lalu <strong>klik salah satu nama</strong> di daftar bawah kolom.
            Untuk <strong>ID database</strong> angka (mis. <code class="bg-amber-100 px-1 rounded">1</code>), ketik lalu klik di luar kolom atau tunggu sebentar — jika satu hasil, terpilih otomatis.
          </p>
          <div v-if="errors.member_id" class="mt-1 text-sm text-red-600">
            {{ errors.member_id }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Paid number / nomor bill <span class="text-red-500">*</span>
          </label>
          <input
            type="text"
            v-model="form.paid_number"
            required
            maxlength="255"
            placeholder="paid_number di bill / struk POS (bukan internal id order)"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <div class="text-xs text-gray-500 mt-1">Wajib unik: jika bill ini sudah pernah earn, tidak bisa di-inject lagi.</div>
          <div v-if="errors.paid_number" class="mt-1 text-sm text-red-600">
            {{ errors.paid_number }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Outlet <span class="text-red-500">*</span>
          </label>
          <select
            v-model="form.outlet_id"
            required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option disabled value="">— Pilih outlet —</option>
            <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">
              {{ o.nama_outlet }}
            </option>
          </select>
          <div class="text-xs text-gray-500 mt-1">Tercatat di transaksi poin dan notifikasi member.</div>
          <div v-if="errors.outlet_id" class="mt-1 text-sm text-red-600">
            {{ errors.outlet_id }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Nilai transaksi (Rp) <span class="text-red-500">*</span>
          </label>
          <input
            type="number"
            v-model.number="form.transaction_amount"
            required
            min="0.01"
            step="0.01"
            placeholder="Grand total order"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <div class="text-xs text-gray-500 mt-1">Poin = floor((nilai / 10.000) × rate tier), plus akumulasi sisa desimal (Loyal).</div>
          <div v-if="errors.transaction_amount" class="mt-1 text-sm text-red-600">
            {{ errors.transaction_amount }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Channel <span class="text-red-500">*</span>
          </label>
          <select
            v-model="form.channel"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="pos">POS / default (dicatat sebagai dine-in)</option>
            <option value="dine-in">Dine-in</option>
            <option value="take-away">Take-away</option>
            <option value="delivery-restaurant">Delivery restoran</option>
            <option value="gift-voucher">Gift voucher (biasanya tidak dapat poin)</option>
            <option value="e-commerce">E-commerce / ojol (biasanya tidak dapat poin)</option>
          </select>
        </div>

        <div class="flex flex-col gap-2">
          <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" v-model="form.is_gift_voucher_payment" class="rounded border-gray-300" />
            Pembayaran memakai gift voucher (poin tidak diberikan)
          </label>
          <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" v-model="form.is_ecommerce_order" class="rounded border-gray-300" />
            Order dari platform e-commerce / ojol (poin tidak diberikan)
          </label>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Tanggal transaksi <span class="text-red-500">*</span>
          </label>
          <input
            type="date"
            v-model="form.transaction_date"
            required
            :max="today"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <div v-if="errors.transaction_date" class="mt-1 text-sm text-red-600">
            {{ errors.transaction_date }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Keterangan <span class="text-red-500">*</span>
          </label>
          <textarea
            v-model="form.description"
            required
            maxlength="500"
            rows="3"
            placeholder="Contoh: Backfill karena sync POS gagal, shift X"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
          <div class="text-xs text-gray-500 mt-1">{{ form.description.length }}/500 karakter</div>
          <div v-if="errors.description" class="mt-1 text-sm text-red-600">
            {{ errors.description }}
          </div>
        </div>

        <div v-if="selectedMember && form.transaction_amount > 0" class="bg-gray-50 rounded-lg p-4 border border-gray-200">
          <h3 class="text-sm font-semibold text-gray-700 mb-2">Perkiraan (tanpa sisa desimal di browser):</h3>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Tier saat simpan:</span>
              <span class="font-semibold">{{ selectedMember.member_level || 'Silver' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Rate / Rp10.000:</span>
              <span class="font-semibold">{{ ratePreview }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Poin kasar (floor):</span>
              <span class="font-semibold text-green-600">~{{ roughPointsPreview }} poin</span>
            </div>
            <p class="text-xs text-gray-500 pt-1">Nilai final mengikuti server (termasuk point_remainder).</p>
          </div>
        </div>

        <div v-if="errors.error" class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
          <p class="text-sm text-red-700">{{ errors.error }}</p>
        </div>

        <div class="flex gap-4">
          <button
            type="submit"
            :disabled="loading || !canSubmit"
            class="flex-1 px-6 py-3 bg-gradient-to-r from-green-500 to-green-700 text-white rounded-lg font-semibold hover:from-green-600 hover:to-green-800 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <i v-if="loading" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fa-solid fa-coins"></i>
            {{ loading ? 'Memproses...' : 'Simpan (hitung poin seperti POS)' }}
          </button>
          <Link href="/manual-point" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
            Batal
          </Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  errors: Object,
  outlets: {
    type: Array,
    default: () => [],
  },
});

const form = useForm({
  member_id: null,
  paid_number: '',
  outlet_id: '',
  transaction_amount: null,
  transaction_date: new Date().toISOString().split('T')[0],
  channel: 'pos',
  is_gift_voucher_payment: false,
  is_ecommerce_order: false,
  description: '',
});

const memberOptions = ref([]);
const selectedMember = ref(null);
const memberSearch = ref('');
const searchingMembers = ref(false);
const loading = ref(false);
const today = new Date().toISOString().split('T')[0];

let searchTimeout = null;

const runMemberSearch = async () => {
  const search = memberSearch.value.trim();
  if (!search) {
    memberOptions.value = [];
    return;
  }
  const numericOnly = /^\d+$/.test(search);
  if (search.length < 2 && !numericOnly) {
    memberOptions.value = [];
    return;
  }
  searchingMembers.value = true;
  try {
    const response = await axios.get('/manual-point/search-members', { params: { search } });
    memberOptions.value = response.data || [];
    if (numericOnly && memberOptions.value.length === 1) {
      selectMember(memberOptions.value[0]);
    }
  } catch {
    memberOptions.value = [];
  } finally {
    searchingMembers.value = false;
  }
};

const searchMembersDebounced = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => runMemberSearch(), 300);
};

/** Langsung cari saat blur — penting untuk ID satu digit seperti 1 tanpa nunggu debounce */
const searchMembersNow = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
    searchTimeout = null;
  }
  runMemberSearch();
};

const selectMember = (member) => {
  form.member_id = member.id;
  selectedMember.value = member;
  memberSearch.value = '';
  memberOptions.value = [];
};

const clearMember = () => {
  form.member_id = null;
  selectedMember.value = null;
  memberSearch.value = '';
  memberOptions.value = [];
};

const formatNumber = (num) => {
  if (num === null || num === undefined) return '0';
  return new Intl.NumberFormat('id-ID').format(num);
};

const ratePreview = computed(() => {
  if (!selectedMember.value) return '—';
  const t = String(selectedMember.value.member_level || 'silver').toLowerCase();
  if (t === 'loyal') return '1,5';
  if (t === 'elite') return '2';
  return '1';
});

const roughPointsPreview = computed(() => {
  if (!selectedMember.value || !form.transaction_amount || form.transaction_amount <= 0) return 0;
  const t = String(selectedMember.value.member_level || 'silver').toLowerCase();
  let rate = 1;
  if (t === 'loyal') rate = 1.5;
  if (t === 'elite') rate = 2;
  return Math.floor((form.transaction_amount / 10000) * rate);
});

const canSubmit = computed(() => {
  const oid = form.outlet_id;
  const hasOutlet = oid !== '' && oid !== null && Number(oid) > 0;
  return (
    form.member_id &&
    form.paid_number.trim().length > 0 &&
    hasOutlet &&
    form.transaction_amount > 0 &&
    form.transaction_date &&
    form.description.trim().length > 0
  );
});

const submitForm = () => {
  if (!canSubmit.value) return;
  loading.value = true;
  form.post('/manual-point', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      form.transaction_date = new Date().toISOString().split('T')[0];
      form.channel = 'pos';
      form.outlet_id = '';
      form.is_gift_voucher_payment = false;
      form.is_ecommerce_order = false;
      selectedMember.value = null;
      memberOptions.value = [];
    },
    onFinish: () => {
      loading.value = false;
    },
  });
};

onMounted(() => {
  if (!form.transaction_date) form.transaction_date = today;
});
</script>
