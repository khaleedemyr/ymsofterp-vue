<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi, jaConfirmDelete, jaDelete, jaFormErrors } from '@/composables/useJustAcademyUi';

const props = defineProps({ categories: Object, filters: Object });
const search = ref(props.filters?.search || '');
const showModal = ref(false);
const editing = ref(null);

const form = useForm({
  name: '',
  description: '',
  sort_order: 0,
  is_active: true,
});

const debounced = debounce(() => {
  router.get(route('just-academy.categories.index'), { search: search.value || undefined }, { preserveState: true, replace: true });
}, 400);

watch(search, debounced);

function openCreate() {
  editing.value = null;
  form.reset();
  form.is_active = true;
  form.sort_order = 0;
  showModal.value = true;
}

function openEdit(cat) {
  editing.value = cat;
  form.name = cat.name;
  form.description = cat.description || '';
  form.sort_order = cat.sort_order ?? 0;
  form.is_active = !!cat.is_active;
  showModal.value = true;
}

function submit() {
  const opts = {
    onSuccess: () => { showModal.value = false; },
    onError: (errors) => jaFormErrors(errors),
  };
  if (editing.value) {
    form.put(route('just-academy.categories.update', editing.value.id), opts);
  } else {
    form.post(route('just-academy.categories.store'), { ...opts, onSuccess: () => { showModal.value = false; form.reset(); } });
  }
}

async function remove(cat) {
  const result = await jaConfirmDelete({
    title: 'Hapus method?',
    html: `Method <strong>${cat.name}</strong> akan dihapus permanen.`,
  });
  if (!result.isConfirmed) return;
  jaDelete(route('just-academy.categories.destroy', cat.id));
}
</script>

<template>
  <JaLayout title="Method" subtitle="Kelola metode / kategori program training" icon="fa-solid fa-folder-tree">
    <template #actions>
      <button type="button" :class="jaUi.btnPrimary" @click="openCreate">
        <i class="fa-solid fa-plus text-xs" /> Method Baru
      </button>
    </template>

    <input v-model="search" type="text" placeholder="Cari method..." :class="[jaUi.search, 'mb-5']" @input="debounced" />

    <div :class="jaUi.tableWrap">
      <table :class="jaUi.table">
        <thead :class="jaUi.thead">
          <tr>
            <th :class="jaUi.th">Nama</th>
            <th :class="jaUi.th">Deskripsi</th>
            <th :class="jaUi.th">Urutan</th>
            <th :class="jaUi.th">Program</th>
            <th :class="jaUi.th">Status</th>
            <th :class="jaUi.th"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in categories.data" :key="c.id" :class="jaUi.tr">
            <td :class="[jaUi.td, 'font-semibold text-slate-800']">{{ c.name }}</td>
            <td :class="jaUi.td">{{ c.description || '—' }}</td>
            <td :class="jaUi.td">{{ c.sort_order }}</td>
            <td :class="jaUi.td">{{ c.programs_count }}</td>
            <td :class="jaUi.td">
              <span :class="c.is_active ? jaUi.badgeActive : jaUi.badgeInactive">{{ c.is_active ? 'Aktif' : 'Nonaktif' }}</span>
            </td>
            <td :class="[jaUi.td, 'text-right space-x-4']">
              <button type="button" :class="jaUi.btnLink" @click="openEdit(c)">Edit</button>
              <button type="button" :class="jaUi.btnDanger" @click="remove(c)">Hapus</button>
            </td>
          </tr>
          <tr v-if="!categories.data?.length">
            <td colspan="6" :class="jaUi.empty">Belum ada method.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showModal" :class="jaUi.modalOverlay" @click.self="showModal = false">
      <form :class="jaUi.modal" @submit.prevent="submit">
        <h2 class="text-lg font-bold text-slate-800">{{ editing ? 'Edit Method' : 'Method Baru' }}</h2>
        <div>
          <label :class="jaUi.label">Nama</label>
          <input v-model="form.name" :class="jaUi.input" required />
        </div>
        <div>
          <label :class="jaUi.label">Deskripsi</label>
          <textarea v-model="form.description" rows="2" :class="jaUi.input" />
        </div>
        <div>
          <label :class="jaUi.label">Urutan</label>
          <input v-model="form.sort_order" type="number" min="0" :class="jaUi.input" />
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-indigo-600" /> Aktif
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" :class="jaUi.btnSecondary" @click="showModal = false">Batal</button>
          <button type="submit" :class="jaUi.btnPrimary" :disabled="form.processing">Simpan</button>
        </div>
      </form>
    </div>
  </JaLayout>
</template>
