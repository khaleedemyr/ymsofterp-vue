<template>
  <div
    class="fixed inset-0 z-[210] flex items-center justify-center bg-black/50 p-4"
    @click.self="$emit('close')"
  >
    <div class="w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-xl bg-white shadow-2xl">
      <div class="flex items-start justify-between border-b px-5 py-4">
        <div>
          <h3 class="text-lg font-bold text-gray-900">Config Posisi Rekrutmen</h3>
          <p class="text-sm text-gray-500">{{ vacancy?.position }} — {{ vacancy?.location }}</p>
          <p class="mt-1 text-xs text-gray-400">
            Angka progress funnel di dashboard dihitung otomatis dari stage tiap pelamar.
          </p>
        </div>
        <button type="button" class="text-2xl leading-none text-gray-400 hover:text-gray-700" @click="$emit('close')">
          &times;
        </button>
      </div>

      <form class="space-y-4 p-5" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">PIC</label>
            <input v-model="form.pic" class="w-full rounded border px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Kebutuhan</label>
            <input
              v-model.number="form.headcount_needed"
              type="number"
              min="0"
              class="w-full rounded border px-3 py-2 text-sm"
              :disabled="form.is_hold"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
              <input v-model="form.is_hold" type="checkbox" class="rounded border-gray-300" />
              HOLD (posisi ditunda)
            </label>
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Tgl Mulai Pencarian</label>
            <input v-model="form.search_start_date" type="date" class="w-full rounded border px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Tgl Target Fulfill</label>
            <input v-model="form.target_fulfill_date" type="date" class="w-full rounded border px-3 py-2 text-sm" />
          </div>
        </div>

        <div>
          <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Keterangan Lolos HR Interview</label>
          <textarea v-model="form.hr_interview_notes" rows="2" class="w-full rounded border px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Keterangan Lolos User Interview</label>
          <textarea v-model="form.user_interview_notes" rows="2" class="w-full rounded border px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="mb-1 block text-xs font-semibold uppercase text-gray-500">Keterangan Akhir</label>
          <textarea v-model="form.final_notes" rows="3" class="w-full rounded border px-3 py-2 text-sm" />
        </div>

        <div class="flex justify-end gap-2 border-t pt-4">
          <button type="button" class="rounded bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300" @click="$emit('close')">
            Batal
          </button>
          <button
            type="submit"
            class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
            :disabled="saving"
          >
            {{ saving ? 'Menyimpan...' : 'Simpan Config' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  vacancy: { type: Object, required: true },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);

const emptyForm = () => ({
  pic: '',
  headcount_needed: null,
  is_hold: false,
  search_start_date: '',
  target_fulfill_date: '',
  hr_interview_notes: '',
  user_interview_notes: '',
  final_notes: '',
});

const form = ref(emptyForm());

function toDateInput(value) {
  if (!value) return '';
  if (typeof value === 'string' && value.length >= 10) return value.slice(0, 10);
  return '';
}

function loadForm() {
  const c = props.vacancy?.recruitment_config || {};
  form.value = {
    pic: c.pic || '',
    headcount_needed: c.headcount_needed ?? null,
    is_hold: Boolean(c.is_hold),
    search_start_date: toDateInput(c.search_start_date),
    target_fulfill_date: toDateInput(c.target_fulfill_date),
    hr_interview_notes: c.hr_interview_notes || '',
    user_interview_notes: c.user_interview_notes || '',
    final_notes: c.final_notes || '',
  };
}

watch(() => props.vacancy, loadForm, { immediate: true });

function submit() {
  saving.value = true;
  router.patch(`/admin/job-vacancy/${props.vacancy.id}/recruitment-config`, form.value, {
    preserveScroll: true,
    onSuccess: () => {
      emit('saved');
      emit('close');
    },
    onFinish: () => {
      saving.value = false;
    },
  });
}
</script>
