<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <h1 class="text-3xl font-extrabold mb-6 flex items-center gap-2 text-blue-700 drop-shadow-sm">
        <i class="fa-solid fa-truck text-blue-500"></i> Edit Good Receive Outlet
      </h1>
      <div class="mb-4 bg-white rounded-xl shadow p-6 flex flex-col gap-2 border border-blue-100">
        <div class="font-bold text-lg">Nomor GR: {{ goodReceive?.number || '-' }}</div>
        <div class="text-sm text-gray-500">Tanggal: {{ this.formatDate(goodReceive?.receive_date) }}</div>
        <div class="text-sm text-gray-500">Outlet: {{ goodReceive?.outlet_name || '-' }}</div>
        <div class="text-sm text-gray-500">Warehouse Outlet: {{ goodReceive?.warehouse_outlet_name || '-' }}</div>
      </div>
      <div class="bg-white rounded-xl shadow p-6 mb-4 border border-blue-100 transition-all hover:shadow-lg">
        <div class="font-bold mb-2 text-blue-700 flex items-center gap-2"><i class="fa-solid fa-list-check"></i> List Item GR</div>
        <form @submit.prevent="submit">
          <table class="min-w-full text-sm">
            <thead>
              <tr>
                <th class="text-left">Item</th>
                <th class="text-left">Satuan</th>
                <th class="text-right">Qty</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td>{{ item.item_name }}</td>
                <td>{{ item.unit_name }}</td>
                <td class="text-right">
                  <input type="number" v-model.number="item.qty" min="0" step="any" class="form-input border rounded px-2 py-1 w-24 text-right" />
                </td>
              </tr>
            </tbody>
          </table>
          <div class="mt-6 flex gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-bold shadow">Simpan</button>
            <inertia-link :href="route('outlet-food-good-receives.index')" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-lg font-bold">Batal</inertia-link>
          </div>
          <div v-if="error" class="mt-4 text-red-600 font-semibold">{{ error }}</div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import axios from 'axios';
export default {
  props: {
    goodReceive: Object,
    items: Array,
  },
  components: { AppLayout },
  data() {
    return {
      error: null,
    };
  },
  methods: {
    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('id-ID');
    },
    async submit() {
      this.error = null;
      const confirmed = await Swal.fire({
        title: 'Update Qty GR?',
        text: 'Qty dan inventory akan diupdate. Lanjutkan?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, update',
        cancelButtonText: 'Batal',
      });
      if (!confirmed.isConfirmed) return;
      Swal.fire({
        title: 'Menyimpan...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });
      try {
        const res = await axios.put(`/outlet-food-good-receives/${this.goodReceive.id}`, {
          items: this.items.map(i => ({ id: i.id, qty: i.qty })),
        });
        Swal.close();
        if (res.data && res.data.success) {
          await Swal.fire('Berhasil', res.data.message || 'Qty GR berhasil diupdate', 'success');
          this.$inertia.visit(this.route('outlet-food-good-receives.index'));
        } else {
          throw new Error(res.data.message || 'Gagal update GR');
        }
      } catch (e) {
        Swal.close();
        this.error = e.response?.data?.message || e.message || 'Gagal update GR';
        Swal.fire('Gagal', this.error, 'error');
      }
    },
  },
};
</script> 