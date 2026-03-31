<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import Swal from 'sweetalert2';

defineOptions({ layout: AppLayout });

const props = defineProps({
  outlets: {
    type: Array,
    default: () => [],
  },
});

const form = useForm({
  name: '',
  email: '',
  password: '',
  kode_outlet: '',
  status: 'A',
});

const page = usePage();

watch(
  () => page.props.flash?.success,
  (message) => {
    if (message) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: message,
      });
    }
  },
  { immediate: true }
);

const submit = () => {
  form.post(route('external-report-users.store'));
};
</script>

<template>
  <Head title="Input User External" />
  <div class="max-w-3xl mx-auto py-8 px-4">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Input User External Report</h1>
      <Link
        :href="route('external-report-users.index')"
        class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200"
      >
        Lihat List
      </Link>
    </div>

    <form @submit.prevent="submit" class="bg-white shadow rounded-2xl p-6 space-y-4">
      <div>
        <label class="block text-sm font-medium mb-1">Nama</label>
        <input v-model="form.name" type="text" class="w-full rounded-lg border-gray-300" required />
        <p v-if="form.errors.name" class="text-red-600 text-sm mt-1">{{ form.errors.name }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input v-model="form.email" type="email" class="w-full rounded-lg border-gray-300" required />
        <p v-if="form.errors.email" class="text-red-600 text-sm mt-1">{{ form.errors.email }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Password</label>
        <input v-model="form.password" type="password" class="w-full rounded-lg border-gray-300" required />
        <p v-if="form.errors.password" class="text-red-600 text-sm mt-1">{{ form.errors.password }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Outlet</label>
        <select v-model="form.kode_outlet" class="w-full rounded-lg border-gray-300" required>
          <option value="">Pilih outlet</option>
          <option v-for="outlet in props.outlets" :key="outlet.qr_code" :value="outlet.qr_code">
            {{ outlet.nama_outlet }}
          </option>
        </select>
        <p v-if="form.errors.kode_outlet" class="text-red-600 text-sm mt-1">{{ form.errors.kode_outlet }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select v-model="form.status" class="w-full rounded-lg border-gray-300" required>
          <option value="A">Aktif</option>
          <option value="N">Non Aktif</option>
        </select>
        <p v-if="form.errors.status" class="text-red-600 text-sm mt-1">{{ form.errors.status }}</p>
      </div>

      <div class="pt-2">
        <button
          type="submit"
          :disabled="form.processing"
          class="px-5 py-2.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-60"
        >
          Simpan User External
        </button>
      </div>
    </form>
  </div>
</template>
