<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  program: Object,
  categories: Array,
  libraryMaterials: Array,
  libraryQuizzes: Array,
  curriculum: Array,
});

const form = useForm({
  category_id: props.program?.category_id || '',
  code: props.program?.code || '',
  title: props.program?.title || '',
  description: props.program?.description || '',
  duration_hours: props.program?.duration_hours || '',
  status: props.program?.status || 'draft',
});

const curriculumItems = ref([...(props.curriculum || [])]);
const dragPayload = ref(null);
const dropTargetIndex = ref(null);
const isDropZoneActive = ref(false);
const curriculumForm = useForm({ items: [] });

function submit() {
  if (props.program) {
    form.put(route('just-academy.programs.update', props.program.id));
  } else {
    form.post(route('just-academy.programs.store'));
  }
}

function libraryItem(itemType, refItem) {
  return {
    item_type: itemType,
    ref_id: refItem.id,
    title: refItem.title,
    is_required: false,
  };
}

function addMaterial(m) {
  curriculumItems.value.push(libraryItem('material', m));
}

function addQuiz(q) {
  curriculumItems.value.push(libraryItem('quiz', q));
}

function removeItem(index) {
  curriculumItems.value.splice(index, 1);
}

function parseDragData(e) {
  try {
    return dragPayload.value || JSON.parse(e.dataTransfer.getData('text/plain') || 'null');
  } catch {
    return dragPayload.value;
  }
}

function onLibraryDragStart(e, itemType, refItem) {
  const payload = { source: 'library', item: libraryItem(itemType, refItem) };
  dragPayload.value = payload;
  e.dataTransfer.effectAllowed = 'copy';
  e.dataTransfer.setData('text/plain', JSON.stringify(payload));
}

function onCurriculumDragStart(e, index) {
  const payload = { source: 'curriculum', index };
  dragPayload.value = payload;
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', JSON.stringify(payload));
}

function onDragOver(e, targetIndex = null) {
  e.preventDefault();
  dropTargetIndex.value = targetIndex;
  isDropZoneActive.value = targetIndex === null;
  e.dataTransfer.dropEffect = dragPayload.value?.source === 'library' ? 'copy' : 'move';
}

function onDragLeave(e, targetIndex = null) {
  if (targetIndex === dropTargetIndex.value) {
    dropTargetIndex.value = null;
  }
  if (targetIndex === null) {
    isDropZoneActive.value = false;
  }
}

function insertLibraryItem(item, index) {
  curriculumItems.value.splice(index, 0, { ...item });
}

function moveCurriculumItem(from, to) {
  if (from === to) return;
  const list = [...curriculumItems.value];
  const [moved] = list.splice(from, 1);
  list.splice(to, 0, moved);
  curriculumItems.value = list;
}

function onDropAt(e, targetIndex) {
  e.preventDefault();
  const payload = parseDragData(e);
  if (!payload) return;

  if (payload.source === 'library' && payload.item) {
    insertLibraryItem(payload.item, targetIndex);
  } else if (payload.source === 'curriculum' && payload.index !== undefined) {
    moveCurriculumItem(payload.index, targetIndex);
  }

  resetDragState();
}

function onDropAtEnd(e) {
  e.preventDefault();
  const payload = parseDragData(e);
  if (!payload) return;

  if (payload.source === 'library' && payload.item) {
    curriculumItems.value.push({ ...payload.item });
  } else if (payload.source === 'curriculum' && payload.index !== undefined) {
    moveCurriculumItem(payload.index, curriculumItems.value.length - 1);
  }

  resetDragState();
}

function onDropEmpty(e) {
  e.preventDefault();
  const payload = parseDragData(e);
  if (payload?.source === 'library' && payload.item) {
    curriculumItems.value.push({ ...payload.item });
  }
  resetDragState();
}

function resetDragState() {
  dragPayload.value = null;
  dropTargetIndex.value = null;
  isDropZoneActive.value = false;
}

function saveCurriculum() {
  curriculumForm.items = curriculumItems.value.map(i => ({
    item_type: i.item_type,
    ref_id: i.ref_id,
    is_required: !!i.is_required,
  }));
  curriculumForm.put(route('just-academy.programs.curriculum.sync', props.program.id));
}
</script>

<template>
  <AppLayout :title="program ? 'Edit Program' : 'Program Baru'">
    <div class="max-w-6xl mx-auto py-8 px-2 space-y-8">
      <h1 class="text-2xl font-bold">{{ program ? 'Edit Program' : 'Program Baru' }}</h1>

      <form class="bg-white rounded-2xl shadow p-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-medium mb-1">Judul</label>
          <input v-model="form.title" class="w-full border rounded-xl px-3 py-2" required />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Kode</label>
            <input v-model="form.code" class="w-full border rounded-xl px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Kategori</label>
            <select v-model="form.category_id" class="w-full border rounded-xl px-3 py-2">
              <option value="">— Pilih —</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Deskripsi</label>
          <textarea v-model="form.description" rows="3" class="w-full border rounded-xl px-3 py-2"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Durasi (jam)</label>
            <input v-model="form.duration_hours" type="number" step="0.5" class="w-full border rounded-xl px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <select v-model="form.status" class="w-full border rounded-xl px-3 py-2">
              <option value="draft">Draft</option>
              <option value="published">Published</option>
              <option value="archived">Archived</option>
            </select>
          </div>
        </div>
        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-xl" :disabled="form.processing">Simpan Program</button>
      </form>

      <div v-if="!program" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 text-sm text-amber-900">
        Simpan program dulu, lalu atur urutan materi & quiz dengan drag-and-drop di bawah.
      </div>

      <template v-if="program">
        <div class="grid lg:grid-cols-2 gap-6">
          <div class="bg-white rounded-2xl shadow p-6 space-y-4">
            <h2 class="font-semibold">Pustaka</h2>
            <p class="text-xs text-gray-500">Drag item ke kolom kanan, atau klik +</p>
            <div>
              <h3 class="text-sm font-medium text-gray-600 mb-2">Materi</h3>
              <ul class="space-y-2 max-h-48 overflow-y-auto">
                <li
                  v-for="m in libraryMaterials"
                  :key="'m'+m.id"
                  draggable="true"
                  class="flex justify-between items-center border rounded-lg px-3 py-2 text-sm cursor-grab active:cursor-grabbing hover:border-indigo-300 hover:bg-indigo-50/50"
                  @dragstart="onLibraryDragStart($event, 'material', m)"
                  @dragend="resetDragState"
                >
                  <span class="flex items-center gap-2 min-w-0">
                    <span class="text-gray-400 shrink-0">⠿</span>
                    <span class="truncate">{{ m.title }} <span class="text-gray-400">({{ m.type }})</span></span>
                  </span>
                  <button type="button" class="text-indigo-600 font-semibold shrink-0 ml-2" @click="addMaterial(m)">+</button>
                </li>
                <li v-if="!libraryMaterials?.length" class="text-sm text-gray-500">
                  Belum ada materi. <a :href="route('just-academy.materials.create')" class="underline">Buat materi</a>
                </li>
              </ul>
            </div>
            <div>
              <h3 class="text-sm font-medium text-gray-600 mb-2">Quiz</h3>
              <ul class="space-y-2 max-h-48 overflow-y-auto">
                <li
                  v-for="q in libraryQuizzes"
                  :key="'q'+q.id"
                  draggable="true"
                  class="flex justify-between items-center border rounded-lg px-3 py-2 text-sm cursor-grab active:cursor-grabbing hover:border-indigo-300 hover:bg-indigo-50/50"
                  @dragstart="onLibraryDragStart($event, 'quiz', q)"
                  @dragend="resetDragState"
                >
                  <span class="flex items-center gap-2 min-w-0">
                    <span class="text-gray-400 shrink-0">⠿</span>
                    <span class="truncate">{{ q.title }}</span>
                  </span>
                  <button type="button" class="text-indigo-600 font-semibold shrink-0 ml-2" @click="addQuiz(q)">+</button>
                </li>
                <li v-if="!libraryQuizzes?.length" class="text-sm text-gray-500">
                  Belum ada quiz. <a :href="route('just-academy.quizzes.create')" class="underline">Buat quiz</a>
                </li>
              </ul>
            </div>
          </div>

          <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="font-semibold mb-2">Urutan Program</h2>
            <p class="text-xs text-gray-500 mb-4">Drop dari pustaka atau drag item untuk ubah urutan.</p>

            <ul class="space-y-2 min-h-[12rem]">
              <li
                v-if="!curriculumItems.length"
                class="text-sm text-gray-400 text-center py-8 border border-dashed rounded-xl transition-colors"
                :class="isDropZoneActive ? 'border-indigo-400 bg-indigo-50 text-indigo-600' : ''"
                @dragover="onDragOver($event, null)"
                @dragleave="onDragLeave($event, null)"
                @drop="onDropEmpty"
              >
                Drag materi atau quiz ke sini
              </li>

              <li
                v-for="(item, index) in curriculumItems"
                :key="index + '-' + item.item_type + '-' + item.ref_id"
                draggable="true"
                class="flex items-center gap-3 border rounded-xl px-3 py-2 cursor-grab active:cursor-grabbing bg-gray-50 transition-colors"
                :class="dropTargetIndex === index ? 'border-indigo-400 bg-indigo-50 ring-2 ring-indigo-200' : ''"
                @dragstart="onCurriculumDragStart($event, index)"
                @dragover="onDragOver($event, index)"
                @dragleave="onDragLeave($event, index)"
                @drop="onDropAt($event, index)"
                @dragend="resetDragState"
              >
                <span class="text-gray-400 text-xs w-5">{{ index + 1 }}</span>
                <span class="text-lg">{{ item.item_type === 'material' ? '📄' : '❓' }}</span>
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-sm truncate">{{ item.title }}</p>
                  <p class="text-xs text-gray-500 capitalize">{{ item.item_type }}</p>
                </div>
                <label class="text-xs flex items-center gap-1 shrink-0" @click.stop>
                  <input v-model="item.is_required" type="checkbox" /> Wajib
                </label>
                <button type="button" class="text-red-600 text-sm" @click="removeItem(index)">×</button>
              </li>

              <li
                v-if="curriculumItems.length"
                class="text-xs text-center py-3 border border-dashed rounded-xl text-gray-400 transition-colors"
                :class="isDropZoneActive ? 'border-indigo-400 bg-indigo-50 text-indigo-600' : ''"
                @dragover="onDragOver($event, null)"
                @dragleave="onDragLeave($event, null)"
                @drop="onDropAtEnd"
              >
                Drop di sini untuk tambah di akhir
              </li>
            </ul>

            <button
              type="button"
              class="mt-4 bg-emerald-600 text-white px-4 py-2 rounded-xl w-full"
              :disabled="curriculumForm.processing"
              @click="saveCurriculum"
            >
              Simpan Urutan Curriculum
            </button>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>
