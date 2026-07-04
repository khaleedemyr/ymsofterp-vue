<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-lightbulb text-amber-500"></i>
            {{ isEdit ? 'Edit NPD Plan & Report' : 'Buat NPD Plan & Report' }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">Isi header report dan daftar produk yang dikembangkan</p>
        </div>
        <Link
          :href="isEdit ? route('npd-plan-report.show', record.id) : route('npd-plan-report.index')"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
        >
          <i class="fa-solid fa-arrow-left"></i>
          Kembali
        </Link>
      </div>

      <form @submit.prevent="submit">
        <div class="bg-white rounded-xl shadow p-6 mb-6">
          <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Report</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Bulan *</label>
              <input
                v-model="form.report_month"
                type="month"
                required
                class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
              />
              <p v-if="form.errors.report_month" class="text-xs text-red-500 mt-1">{{ form.errors.report_month }}</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Outlet *</label>
              <select
                v-model="form.outlet_id"
                required
                class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
              >
                <option value="">Pilih outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
              <p v-if="form.errors.outlet_id" class="text-xs text-red-500 mt-1">{{ form.errors.outlet_id }}</p>
            </div>
            <div class="md:col-span-1">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan</label>
              <input
                v-model="form.notes"
                type="text"
                placeholder="Catatan opsional..."
                class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500"
              />
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
          <div class="flex items-center justify-between px-6 py-4 border-b bg-gradient-to-r from-amber-50 to-white">
            <div>
              <h2 class="text-lg font-semibold text-gray-800">Daftar Produk</h2>
              <p class="text-xs text-gray-500">Tambahkan produk yang direncanakan atau dilaporkan</p>
            </div>
            <button
              type="button"
              @click="addItem"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600 text-sm transition"
            >
              <i class="fa-solid fa-plus"></i>
              Tambah Produk
            </button>
          </div>

          <p v-if="form.errors.items" class="px-6 pt-4 text-sm text-red-500">{{ form.errors.items }}</p>

          <div class="hidden lg:grid lg:grid-cols-12 gap-3 px-6 py-3 bg-gray-50 border-b text-xs font-bold text-gray-600 uppercase">
            <div class="col-span-2">Product Name</div>
            <div class="col-span-1">Category</div>
            <div class="col-span-1">Dev. Date</div>
            <div class="col-span-2">Purpose</div>
            <div class="col-span-1">Launch Date</div>
            <div class="col-span-2">Area / Outlet</div>
            <div class="col-span-1">F&B Cost</div>
            <div class="col-span-1">Selling Price</div>
            <div class="col-span-1 text-center">Aksi</div>
          </div>

          <div v-if="form.items.length === 0" class="px-6 py-12 text-center text-gray-500">
            Belum ada produk. Klik "Tambah Produk" untuk memulai.
          </div>

          <div
            v-for="(item, index) in form.items"
            :key="index"
            class="px-6 py-4 border-b last:border-b-0 hover:bg-amber-50/30 transition-colors"
          >
            <div class="flex items-center gap-2 mb-3 lg:hidden">
              <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100 text-amber-700 text-xs font-bold">
                {{ index + 1 }}
              </span>
              <span class="text-sm font-semibold text-gray-700">Product #{{ index + 1 }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-3 items-start">
              <div class="lg:col-span-2">
                <label class="lg:hidden text-xs font-semibold text-gray-500 mb-1 block">Product Name *</label>
                <input
                  v-model="item.product_name"
                  type="text"
                  required
                  placeholder="Nama produk"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>
              <div class="lg:col-span-1">
                <label class="lg:hidden text-xs font-semibold text-gray-500 mb-1 block">Category</label>
                <input
                  v-model="item.category"
                  type="text"
                  placeholder="Food / Beverage"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>
              <div class="lg:col-span-1">
                <label class="lg:hidden text-xs font-semibold text-gray-500 mb-1 block">Development Date</label>
                <input
                  v-model="item.development_date"
                  type="date"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>
              <div class="lg:col-span-2">
                <label class="lg:hidden text-xs font-semibold text-gray-500 mb-1 block">Purpose *</label>
                <select
                  v-model="item.purpose"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                >
                  <option v-for="opt in purposeOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div class="lg:col-span-1">
                <label class="lg:hidden text-xs font-semibold text-gray-500 mb-1 block">Launch Date</label>
                <input
                  v-model="item.proposed_launch_date"
                  type="date"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>
              <div class="lg:col-span-2">
                <label class="lg:hidden text-xs font-semibold text-gray-500 mb-1 block">Area / Outlet</label>
                <input
                  v-model="item.proposed_launch_area_outlet"
                  type="text"
                  placeholder="Area atau outlet launch"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>
              <div class="lg:col-span-1">
                <label class="lg:hidden text-xs font-semibold text-gray-500 mb-1 block">F&B Cost</label>
                <input
                  v-model.number="item.fb_cost"
                  type="number"
                  min="0"
                  step="1"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>
              <div class="lg:col-span-1">
                <label class="lg:hidden text-xs font-semibold text-gray-500 mb-1 block">Selling Price</label>
                <input
                  v-model.number="item.selling_price"
                  type="number"
                  min="0"
                  step="1"
                  class="w-full rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                />
              </div>
              <div class="lg:col-span-1 flex lg:justify-center">
                <button
                  type="button"
                  @click="removeItem(index)"
                  :disabled="form.items.length === 1"
                  class="px-3 py-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 disabled:opacity-40 disabled:cursor-not-allowed transition text-sm"
                  title="Hapus baris"
                >
                  <i class="fa-solid fa-trash"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link
            :href="isEdit ? route('npd-plan-report.show', record.id) : route('npd-plan-report.index')"
            class="px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 transition"
          >
            Batal
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-5 py-2.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600 disabled:opacity-50 transition inline-flex items-center gap-2"
          >
            <i v-if="form.processing" class="fa fa-spinner fa-spin"></i>
            {{ isEdit ? 'Simpan Perubahan' : 'Simpan Draft' }}
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
  record: { type: Object, default: null },
  outlets: { type: Array, default: () => [] },
  purposeOptions: { type: Array, default: () => [] },
});

const isEdit = computed(() => Boolean(props.record?.id));

function emptyItem() {
  return {
    product_name: '',
    category: '',
    development_date: '',
    purpose: 'new_product',
    proposed_launch_date: '',
    proposed_launch_area_outlet: '',
    fb_cost: 0,
    selling_price: 0,
  };
}

function extractMonth(value) {
  if (!value) return new Date().toISOString().slice(0, 7);
  return String(value).slice(0, 7);
}

function mapRecordItems(items) {
  if (!items?.length) return [emptyItem()];
  return items.map((item) => ({
    product_name: item.product_name || '',
    category: item.category || '',
    development_date: item.development_date ? String(item.development_date).slice(0, 10) : '',
    purpose: item.purpose || 'new_product',
    proposed_launch_date: item.proposed_launch_date ? String(item.proposed_launch_date).slice(0, 10) : '',
    proposed_launch_area_outlet: item.proposed_launch_area_outlet || '',
    fb_cost: Number(item.fb_cost || 0),
    selling_price: Number(item.selling_price || 0),
  }));
}

const form = useForm({
  report_month: extractMonth(props.record?.report_month),
  outlet_id: props.record?.outlet_id || '',
  notes: props.record?.notes || '',
  items: mapRecordItems(props.record?.items),
});

function addItem() {
  form.items.push(emptyItem());
}

function removeItem(index) {
  if (form.items.length <= 1) return;
  form.items.splice(index, 1);
}

function submit() {
  if (isEdit.value) {
    form.put(route('npd-plan-report.update', props.record.id));
  } else {
    form.post(route('npd-plan-report.store'));
  }
}
</script>
