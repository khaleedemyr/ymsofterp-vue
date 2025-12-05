<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Dialog, DialogPanel } from '@headlessui/vue';
import { useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  mode: String,
  warehouseOutlet: Object,
  outlets: Array,
});

const emit = defineEmits(['close', 'success']);

const form = useForm({
  code: '',
  name: '',
  outlet_id: '',
  location: '',
  status: 'active',
});

watch(() => props.warehouseOutlet, (newVal) => {
  if (newVal) {
    form.code = newVal.code;
    form.name = newVal.name;
    form.outlet_id = newVal.outlet_id;
    form.location = newVal.location;
    form.status = newVal.status;
  } else {
    form.reset();
  }
}, { immediate: true });

const isSubmitting = ref(false);

async function submit() {
  isSubmitting.value = true;
  if (props.mode === 'create') {
    form.post(route('warehouse-outlets.store'), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Gudang outlet berhasil ditambahkan!', 'success');
        form.reset();
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
      onFinish: () => isSubmitting.value = false,
    });
  } else if (props.mode === 'edit' && props.warehouseOutlet) {
    form._method = 'PUT';
    form.post(route('warehouse-outlets.update', props.warehouseOutlet.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Gudang outlet berhasil diupdate!', 'success');
        emit('success');
        emit('close');
      },
      onError: () => isSubmitting.value = false,
      onFinish: () => isSubmitting.value = false,
    });
  }
}
</script>

<template>
  <Dialog :open="show" @close="emit('close')" class="relative z-50">
    <div class="fixed inset-0 bg-black/30" aria-hidden="true" />
    <div class="fixed inset-0 flex items-center justify-center p-4">
      <DialogPanel class="mx-auto max-w-2xl w-full bg-white rounded-2xl shadow-2xl p-6">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold text-gray-800">
            {{ mode === 'create' ? 'Buat Gudang Outlet Baru' : 'Edit Gudang Outlet' }}
          </h2>
          <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Kode</label>
            <input
              v-model="form.code"
              type="text"
              class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              required
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Gudang</label>
            <input
              v-model="form.name"
              type="text"
              class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              required
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <select
              v-model="form.outlet_id"
              class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              required
            >
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
                {{ outlet.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
            <textarea
              v-model="form.location"
              rows="3"
              class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
              required
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select
              v-model="form.status"
              class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <button
              type="button"
              @click="emit('close')"
              class="px-4 py-2 text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-4 py-2 text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition disabled:opacity-50"
            >
              {{ mode === 'create' ? 'Simpan' : 'Update' }}
            </button>
          </div>
        </form>
      </DialogPanel>
    </div>
  </Dialog>
</template> 