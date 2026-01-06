<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-exchange-alt text-blue-500"></i> Request Transfer Asset
        </h1>
      </div>

      <form @submit.prevent="submit" class="bg-white rounded-xl shadow-lg p-6 space-y-6">
        <!-- Asset Selection -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Asset <span class="text-red-500">*</span>
          </label>
          <select
            v-model="form.asset_id"
            required
            @change="onAssetChange"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Pilih Asset</option>
            <option v-for="asset in availableAssets" :key="asset.id" :value="asset.id">
              {{ asset.asset_code }} - {{ asset.name }} 
              <span v-if="asset.current_outlet">({{ asset.current_outlet.name }})</span>
            </option>
          </select>
        </div>

        <!-- From Outlet (Auto-filled) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">From Outlet</label>
          <input
            type="text"
            :value="selectedAsset?.current_outlet?.name || 'Tidak Terikat'"
            disabled
            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100"
          />
        </div>

        <!-- To Outlet -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            To Outlet <span class="text-red-500">*</span>
          </label>
          <select
            v-model="form.to_outlet_id"
            required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Pilih Outlet Tujuan</option>
            <option v-for="outlet in availableOutlets" :key="outlet.id" :value="outlet.id">
              {{ outlet.name }}
            </option>
          </select>
        </div>

        <!-- Transfer Date -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Transfer Date <span class="text-red-500">*</span>
          </label>
          <input
            type="date"
            v-model="form.transfer_date"
            required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <!-- Reason -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
          <textarea
            v-model="form.reason"
            rows="3"
            placeholder="Masukkan alasan transfer..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea
            v-model="form.notes"
            rows="3"
            placeholder="Masukkan catatan tambahan..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
          <Link
            href="/asset-management/transfers"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
          >
            Batal
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg flex items-center gap-2 disabled:opacity-50"
          >
            <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
            <span>{{ form.processing ? 'Mengirim...' : 'Request Transfer' }}</span>
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  assets: Array,
  outlets: Array,
});

const form = useForm({
  asset_id: '',
  from_outlet_id: '',
  to_outlet_id: '',
  transfer_date: new Date().toISOString().split('T')[0],
  reason: '',
  notes: '',
});

const selectedAsset = computed(() => {
  if (!form.asset_id) return null;
  return props.assets.find(a => a.id == form.asset_id);
});

const availableAssets = computed(() => {
  return props.assets.filter(a => a.status === 'Active');
});

const availableOutlets = computed(() => {
  if (!selectedAsset.value || !selectedAsset.value.current_outlet_id) {
    return props.outlets;
  }
  return props.outlets.filter(o => o.id != selectedAsset.value.current_outlet_id);
});

function onAssetChange() {
  if (selectedAsset.value) {
    form.from_outlet_id = selectedAsset.value.current_outlet_id || '';
  }
}

function submit() {
  form.post('/asset-management/transfers', {
    preserveScroll: true,
    onSuccess: () => {
      router.visit('/asset-management/transfers');
    },
  });
}
</script>

