<template>
  <div class="min-h-screen bg-[#0f0b06] text-[#f6ead8]">
    <div class="relative overflow-hidden border-b border-amber-500/20 bg-[radial-gradient(circle_at_15%_20%,rgba(245,158,11,0.2),transparent_45%),radial-gradient(circle_at_85%_15%,rgba(251,191,36,0.15),transparent_40%),linear-gradient(135deg,#1b1207,#0f0b06)]">
      <div class="absolute -top-20 -left-20 h-56 w-56 rounded-full bg-amber-300/10 blur-3xl"></div>
      <div class="absolute -bottom-24 right-10 h-64 w-64 rounded-full bg-orange-500/10 blur-3xl"></div>

      <div class="relative mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center justify-between gap-3">
          <button
            @click="goBack"
            class="inline-flex items-center gap-2 rounded-xl border border-amber-500/30 bg-black/30 px-3 py-2 text-sm font-semibold text-amber-200 transition hover:border-amber-400/60 hover:text-amber-100"
          >
            <i class="fa-solid fa-arrow-left"></i>
            Kembali
          </button>

          <div class="rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-amber-200">
            Self Order
          </div>
        </div>

        <div class="grid gap-5 md:grid-cols-[1.8fr_1fr] md:items-end">
          <div>
            <h1 class="font-['Playfair_Display'] text-3xl font-bold leading-tight text-amber-100 md:text-4xl">
              {{ menuBook?.name || 'Menu' }}
            </h1>
            <p class="mt-2 text-sm text-amber-100/70 md:text-base">
              {{ outlet?.nama_outlet || '-' }}
            </p>
            <p class="mt-3 max-w-2xl text-sm text-amber-50/80 md:text-base">
              Pilih menu favoritmu, atur jumlahnya, lalu kirim pesanan langsung ke outlet.
            </p>
          </div>

          <div class="rounded-2xl border border-amber-500/20 bg-black/35 p-4 backdrop-blur">
            <label class="text-xs font-bold uppercase tracking-[0.12em] text-amber-200/80">Cari menu</label>
            <div class="mt-2 flex items-center gap-3 rounded-xl border border-amber-500/25 bg-[#130d07] px-3 py-2">
              <i class="fa-solid fa-magnifying-glass text-amber-300/70"></i>
              <input
                v-model="search"
                type="text"
                placeholder="contoh: kopi, mie, nasi"
                class="w-full bg-transparent text-sm text-amber-50 placeholder:text-amber-100/40 focus:outline-none"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[1fr_340px] lg:px-8">
      <section>
        <div class="mb-4 flex flex-wrap gap-2">
          <button
            @click="selectedCategoryId = null"
            :class="[
              'rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.08em] transition',
              selectedCategoryId === null
                ? 'bg-amber-400 text-[#23170a]'
                : 'border border-amber-400/30 bg-[#1a1209] text-amber-100 hover:border-amber-300/60'
            ]"
          >
            Semua
          </button>

          <button
            v-for="category in categories"
            :key="category.id"
            @click="selectedCategoryId = category.id"
            :class="[
              'rounded-full px-4 py-2 text-xs font-bold uppercase tracking-[0.08em] transition',
              selectedCategoryId === category.id
                ? 'bg-amber-400 text-[#23170a]'
                : 'border border-amber-400/30 bg-[#1a1209] text-amber-100 hover:border-amber-300/60'
            ]"
          >
            {{ category.name }}
          </button>
        </div>

        <div v-if="filteredItems.length === 0" class="rounded-2xl border border-amber-500/20 bg-[#140e08] p-10 text-center">
          <i class="fa-solid fa-utensils mb-3 text-3xl text-amber-300/70"></i>
          <p class="text-amber-100/80">Menu tidak ditemukan.</p>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <article
            v-for="item in filteredItems"
            :key="item.id"
            class="group overflow-hidden rounded-2xl border border-amber-500/15 bg-[#1a1209] transition hover:-translate-y-0.5 hover:border-amber-400/45 hover:shadow-[0_20px_45px_-30px_rgba(251,191,36,0.55)]"
          >
            <div class="relative h-40 overflow-hidden bg-[#26180b]">
              <img
                v-if="resolveImage(item.image_path)"
                :src="resolveImage(item.image_path)"
                :alt="item.name"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                @error="handleImageError"
              />
              <div v-else class="flex h-full items-center justify-center bg-gradient-to-br from-[#3a240d] to-[#1f1308]">
                <i class="fa-solid fa-bowl-food text-3xl text-amber-200/50"></i>
              </div>

              <div class="absolute left-3 top-3 rounded-full bg-black/65 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.08em] text-amber-100/90">
                {{ item.category_name || 'Menu' }}
              </div>
            </div>

            <div class="space-y-3 p-4">
              <div>
                <h3 class="line-clamp-1 text-sm font-bold text-amber-50 md:text-base">{{ item.name }}</h3>
                <p class="mt-1 line-clamp-2 text-xs text-amber-100/70 md:text-sm">
                  {{ item.description || 'Menu andalan outlet.' }}
                </p>
              </div>

              <div class="flex items-center justify-between">
                <div class="text-sm font-black text-amber-300 md:text-base">{{ formatRupiah(item.price) }}</div>

                <button
                  @click="addToCart(item)"
                  class="inline-flex items-center gap-2 rounded-xl bg-amber-400 px-3 py-2 text-xs font-bold uppercase tracking-[0.08em] text-[#2a1a09] transition hover:bg-amber-300"
                >
                  <i class="fa-solid fa-plus"></i>
                  Tambah
                </button>
              </div>
            </div>
          </article>
        </div>
      </section>

      <aside class="h-fit rounded-2xl border border-amber-500/25 bg-[#140d07] p-4 lg:sticky lg:top-4">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-['Playfair_Display'] text-xl font-bold text-amber-100">Keranjang</h2>
          <span class="rounded-full bg-amber-500/15 px-2.5 py-1 text-xs font-bold text-amber-200">{{ totalQty }} item</span>
        </div>

        <div v-if="cartItems.length === 0" class="rounded-xl border border-dashed border-amber-500/30 p-4 text-center text-sm text-amber-100/65">
          Belum ada menu dipilih.
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="line in cartItems"
            :key="line.item.id"
            class="rounded-xl border border-amber-500/20 bg-[#1b1209] p-3"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-amber-50">{{ line.item.name }}</p>
                <p class="mt-1 text-xs text-amber-200/70">{{ formatRupiah(line.item.price) }}</p>
              </div>

              <button
                @click="removeFromCart(line.item.id)"
                class="text-xs text-amber-200/60 transition hover:text-red-300"
              >
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>

            <div class="mt-2 flex items-center justify-between">
              <div class="inline-flex items-center rounded-lg border border-amber-500/30">
                <button @click="decreaseQty(line.item.id)" class="px-2.5 py-1 text-amber-100 hover:bg-amber-500/10">-</button>
                <span class="min-w-8 border-x border-amber-500/25 px-2 text-center text-sm">{{ line.qty }}</span>
                <button @click="increaseQty(line.item.id)" class="px-2.5 py-1 text-amber-100 hover:bg-amber-500/10">+</button>
              </div>
              <p class="text-sm font-bold text-amber-200">{{ formatRupiah(line.qty * line.item.price) }}</p>
            </div>
          </div>

          <div class="mt-2 rounded-xl border border-amber-500/30 bg-[#21150a] p-3">
            <div class="flex items-center justify-between text-sm text-amber-100/80">
              <span>Subtotal</span>
              <span>{{ formatRupiah(subtotal) }}</span>
            </div>
            <div class="mt-1 flex items-center justify-between text-base font-black text-amber-200">
              <span>Total</span>
              <span>{{ formatRupiah(subtotal) }}</span>
            </div>
          </div>
        </div>

        <form class="mt-4 space-y-3" @submit.prevent="submitOrder">
          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.08em] text-amber-200/80">Nama</label>
            <input
              v-model="form.customer_name"
              type="text"
              required
              class="w-full rounded-xl border border-amber-500/30 bg-[#1a1109] px-3 py-2 text-sm text-amber-50 placeholder:text-amber-100/35 focus:border-amber-300 focus:outline-none"
              placeholder="Nama pemesan"
            />
          </div>

          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.08em] text-amber-200/80">No. HP</label>
            <input
              v-model="form.customer_phone"
              type="text"
              class="w-full rounded-xl border border-amber-500/30 bg-[#1a1109] px-3 py-2 text-sm text-amber-50 placeholder:text-amber-100/35 focus:border-amber-300 focus:outline-none"
              placeholder="Opsional"
            />
          </div>

          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.08em] text-amber-200/80">Tipe Order</label>
            <select
              v-model="form.order_type"
              class="w-full rounded-xl border border-amber-500/30 bg-[#1a1109] px-3 py-2 text-sm text-amber-50 focus:border-amber-300 focus:outline-none"
            >
              <option value="dine_in">Dine In</option>
              <option value="take_away">Take Away</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-[0.08em] text-amber-200/80">Catatan</label>
            <textarea
              v-model="form.notes"
              rows="2"
              class="w-full rounded-xl border border-amber-500/30 bg-[#1a1109] px-3 py-2 text-sm text-amber-50 placeholder:text-amber-100/35 focus:border-amber-300 focus:outline-none"
              placeholder="Opsional"
            ></textarea>
          </div>

          <button
            type="submit"
            :disabled="isSubmitting || cartItems.length === 0"
            class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-300 to-amber-400 px-4 py-3 text-sm font-black uppercase tracking-[0.09em] text-[#221507] transition hover:from-amber-200 hover:to-amber-300 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <i v-if="isSubmitting" class="fa-solid fa-spinner animate-spin"></i>
            {{ isSubmitting ? 'Memproses...' : 'Checkout Sekarang' }}
          </button>
        </form>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  menuBook: { type: Object, default: () => ({}) },
  outlet: { type: Object, default: () => ({}) },
  items: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
});

const search = ref('');
const selectedCategoryId = ref(null);
const cartMap = ref({});
const isSubmitting = ref(false);

const form = ref({
  customer_name: '',
  customer_phone: '',
  order_type: 'dine_in',
  notes: '',
});

const filteredItems = computed(() => {
  const keyword = search.value.trim().toLowerCase();

  return (props.items || []).filter((item) => {
    const categoryMatch = selectedCategoryId.value === null || item.category_id === selectedCategoryId.value;

    if (!categoryMatch) {
      return false;
    }

    if (!keyword) {
      return true;
    }

    return [item.name, item.description, item.category_name]
      .filter(Boolean)
      .some((text) => String(text).toLowerCase().includes(keyword));
  });
});

const cartItems = computed(() => {
  const map = cartMap.value;
  return Object.keys(map).map((itemId) => ({
    item: map[itemId].item,
    qty: map[itemId].qty,
  }));
});

const totalQty = computed(() => cartItems.value.reduce((sum, row) => sum + row.qty, 0));
const subtotal = computed(() => cartItems.value.reduce((sum, row) => sum + (row.qty * Number(row.item.price || 0)), 0));

const addToCart = (item) => {
  const current = cartMap.value[item.id];
  if (!current) {
    cartMap.value = {
      ...cartMap.value,
      [item.id]: { item, qty: 1 },
    };
    return;
  }

  cartMap.value = {
    ...cartMap.value,
    [item.id]: { item, qty: Math.min(current.qty + 1, 99) },
  };
};

const increaseQty = (itemId) => {
  const current = cartMap.value[itemId];
  if (!current) {
    return;
  }

  cartMap.value = {
    ...cartMap.value,
    [itemId]: { ...current, qty: Math.min(current.qty + 1, 99) },
  };
};

const decreaseQty = (itemId) => {
  const current = cartMap.value[itemId];
  if (!current) {
    return;
  }

  if (current.qty <= 1) {
    removeFromCart(itemId);
    return;
  }

  cartMap.value = {
    ...cartMap.value,
    [itemId]: { ...current, qty: current.qty - 1 },
  };
};

const removeFromCart = (itemId) => {
  const next = { ...cartMap.value };
  delete next[itemId];
  cartMap.value = next;
};

const submitOrder = async () => {
  if (cartItems.value.length === 0) {
    Swal.fire('Keranjang kosong', 'Pilih minimal 1 menu.', 'warning');
    return;
  }

  isSubmitting.value = true;

  try {
    const payload = {
      customer_name: form.value.customer_name,
      customer_phone: form.value.customer_phone,
      order_type: form.value.order_type,
      notes: form.value.notes,
      items: cartItems.value.map((row) => ({
        item_id: row.item.id,
        qty: row.qty,
        modifiers: row.modifiers || null,
        notes: null,
      })),
    };

    const response = await axios.post(`/menu/book/${props.menuBook.id}/self-order/checkout`, payload);
    const data = response?.data?.data || {};

    await Swal.fire({
      icon: 'success',
      title: 'Order berhasil!',
      html: `
        <div style="text-align:left">
          <div><b>No. Order:</b> ${data.order_no || '-'}</div>
          <div><b>Total Item:</b> ${data.total_item || 0}</div>
          <div><b>Total:</b> ${formatRupiah(data.grand_total || 0)}</div>
        </div>
      `,
      confirmButtonText: 'OK',
    });

    cartMap.value = {};
    form.value.notes = '';
  } catch (error) {
    const msg = error?.response?.data?.message || 'Gagal checkout. Silakan coba lagi.';
    Swal.fire('Checkout gagal', msg, 'error');
  } finally {
    isSubmitting.value = false;
  }
};

const resolveImage = (path) => {
  if (!path) {
    return null;
  }

  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) {
    return path;
  }

  return `/storage/${path}`;
};

const handleImageError = (event) => {
  event.target.style.display = 'none';
};

const goBack = () => {
  router.visit(`/menu/book/${props.menuBook.id}`);
};

const formatRupiah = (value) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
};
</script>
