<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-bell"></i> Kirim Notifikasi Member
        </h1>
        <Link
          href="/member-notification"
          class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition flex items-center gap-2"
        >
          <i class="fa-solid fa-list"></i> Daftar Notifikasi
        </Link>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
          <div class="text-sm text-gray-600 mb-1">Total Member Aktif</div>
          <div class="text-2xl font-bold text-gray-800">{{ stats.total_members }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="text-sm text-gray-600 mb-1">Member dengan Device Token</div>
          <div class="text-2xl font-bold text-green-600">{{ stats.members_with_tokens }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-purple-500">
          <div class="text-sm text-gray-600 mb-1">Total Device Token Aktif</div>
          <div class="text-2xl font-bold text-purple-600">{{ stats.total_device_tokens }}</div>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="sendNotification" class="bg-white rounded-xl shadow-lg p-6 space-y-6">
        <!-- Title -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Judul Notifikasi <span class="text-red-500">*</span>
          </label>
          <input
            type="text"
            v-model="form.title"
            required
            maxlength="255"
            placeholder="Masukkan judul notifikasi"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <div class="text-xs text-gray-500 mt-1">{{ form.title.length }}/255 karakter</div>
        </div>

        <!-- Message -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Pesan Notifikasi <span class="text-red-500">*</span>
          </label>
          <textarea
            v-model="form.message"
            required
            rows="4"
            placeholder="Masukkan pesan notifikasi"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
        </div>

        <!-- Target Type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Target Pengiriman <span class="text-red-500">*</span>
          </label>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 transition"
              :class="form.target_type === 'all' ? 'border-blue-500 bg-blue-50' : 'border-gray-300'">
              <input
                type="radio"
                v-model="form.target_type"
                value="all"
                class="mr-3 text-blue-600 focus:ring-blue-500"
              />
              <div>
                <div class="font-semibold">Semua Member</div>
                <div class="text-sm text-gray-600">Kirim ke semua member yang aktif</div>
              </div>
            </label>
            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 transition"
              :class="form.target_type === 'selected' ? 'border-blue-500 bg-blue-50' : 'border-gray-300'">
              <input
                type="radio"
                v-model="form.target_type"
                value="selected"
                class="mr-3 text-blue-600 focus:ring-blue-500"
              />
              <div>
                <div class="font-semibold">Pilih Member</div>
                <div class="text-sm text-gray-600">Pilih member secara manual</div>
              </div>
            </label>
            <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 transition"
              :class="form.target_type === 'filtered' ? 'border-blue-500 bg-blue-50' : 'border-gray-300'">
              <input
                type="radio"
                v-model="form.target_type"
                value="filtered"
                class="mr-3 text-blue-600 focus:ring-blue-500"
              />
              <div>
                <div class="font-semibold">Filter Member</div>
                <div class="text-sm text-gray-600">Filter berdasarkan kriteria</div>
              </div>
            </label>
          </div>
        </div>

        <!-- Selected Members -->
        <div v-if="form.target_type === 'selected'" class="border-t pt-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Pilih Member <span class="text-red-500">*</span>
          </label>
          <div class="space-y-4">
            <!-- Search Member -->
            <div class="relative">
              <input
                type="text"
                v-model="memberSearch"
                @input="searchMembers"
                placeholder="Cari member (nama, email, ID, atau telepon)..."
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
              <div v-if="memberSearchResults.length > 0 && memberSearch" 
                class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <div
                  v-for="member in memberSearchResults"
                  :key="member.id"
                  @click="selectMember(member)"
                  class="px-4 py-2 hover:bg-blue-50 cursor-pointer border-b last:border-b-0"
                >
                  <div class="font-medium">{{ member.nama_lengkap }}</div>
                  <div class="text-sm text-gray-600">{{ member.email }} - {{ member.member_level }}</div>
                </div>
              </div>
            </div>

            <!-- Selected Members List -->
            <div v-if="selectedMembers.length > 0" class="space-y-2">
              <div class="text-sm font-medium text-gray-700">Member Terpilih ({{ selectedMembers.length }})</div>
              <div class="flex flex-wrap gap-2">
                <div
                  v-for="member in selectedMembers"
                  :key="member.id"
                  class="flex items-center gap-2 bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm"
                >
                  <span>{{ member.nama_lengkap }}</span>
                  <button
                    type="button"
                    @click="removeMember(member.id)"
                    class="text-blue-600 hover:text-blue-800"
                  >
                    <i class="fa-solid fa-times"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filtered Members -->
        <div v-if="form.target_type === 'filtered'" class="border-t pt-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Member Level -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Member Level
              </label>
              <select
                v-model="form.member_level"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">Semua Level</option>
                <option v-for="level in memberLevels" :key="level" :value="level">
                  {{ level }}
                </option>
              </select>
            </div>

            <!-- Pekerjaan -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Pekerjaan
              </label>
              <select
                v-model="form.pekerjaan_id"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">Semua Pekerjaan</option>
                <option v-for="occupation in occupations" :key="occupation.id" :value="occupation.id">
                  {{ occupation.name }}
                </option>
              </select>
            </div>

            <!-- Exclusive Member -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Exclusive Member
              </label>
              <select
                v-model="form.is_exclusive_member"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option :value="null">Semua</option>
                <option :value="true">Ya</option>
                <option :value="false">Tidak</option>
              </select>
            </div>
          </div>

          <!-- Preview Filtered Members Count -->
          <div v-if="filteredMembersCount !== null" class="mt-4 p-3 bg-blue-50 rounded-lg">
            <div class="text-sm text-blue-800">
              <i class="fa-solid fa-info-circle mr-2"></i>
              Akan mengirim ke <strong>{{ filteredMembersCount }}</strong> member yang memenuhi kriteria
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end gap-4 pt-4 border-t">
          <button
            type="button"
            @click="resetForm"
            class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition"
          >
            Reset
          </button>
          <button
            type="submit"
            :disabled="loading || !canSubmit"
            class="px-6 py-2 bg-gradient-to-r from-blue-500 to-blue-700 text-white rounded-lg font-semibold hover:from-blue-600 hover:to-blue-800 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <i v-if="loading" class="fa fa-spinner fa-spin"></i>
            <i v-else class="fa-solid fa-paper-plane"></i>
            Kirim Notifikasi
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  memberLevels: Array,
  occupations: Array,
  stats: Object,
});

const form = useForm({
  title: '',
  message: '',
  target_type: 'all',
  member_ids: [],
  member_level: '',
  pekerjaan_id: '',
  is_exclusive_member: null,
});

const loading = ref(false);
const memberSearch = ref('');
const memberSearchResults = ref([]);
const selectedMembers = ref([]);
const filteredMembersCount = ref(null);
let searchTimeout = null;

const canSubmit = computed(() => {
  if (!form.title || !form.message) return false;
  
  if (form.target_type === 'selected') {
    return selectedMembers.value.length > 0;
  }
  
  return true;
});

const searchMembers = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  
  searchTimeout = setTimeout(async () => {
    if (memberSearch.value.length < 2) {
      memberSearchResults.value = [];
      return;
    }
    
    try {
      const response = await axios.get('/member-notification/search-members', {
        params: { search: memberSearch.value }
      });
      memberSearchResults.value = response.data;
    } catch (error) {
      console.error('Error searching members:', error);
      memberSearchResults.value = [];
    }
  }, 300);
};

const selectMember = (member) => {
  if (!selectedMembers.value.find(m => m.id === member.id)) {
    selectedMembers.value.push(member);
    form.member_ids = selectedMembers.value.map(m => m.id);
  }
  memberSearch.value = '';
  memberSearchResults.value = [];
};

const removeMember = (memberId) => {
  selectedMembers.value = selectedMembers.value.filter(m => m.id !== memberId);
  form.member_ids = selectedMembers.value.map(m => m.id);
};

const getFilteredMembersCount = async () => {
  if (form.target_type !== 'filtered') {
    filteredMembersCount.value = null;
    return;
  }
  
  try {
    const params = {};
    if (form.member_level) params.member_level = form.member_level;
    if (form.pekerjaan_id) params.pekerjaan_id = form.pekerjaan_id;
    if (form.is_exclusive_member !== null) params.is_exclusive_member = form.is_exclusive_member;
    params.has_device_token = true;
    
    const response = await axios.get('/member-notification/get-members', { params });
    filteredMembersCount.value = response.data.count;
  } catch (error) {
    console.error('Error getting filtered members count:', error);
    filteredMembersCount.value = null;
  }
};

watch([() => form.target_type, () => form.member_level, () => form.pekerjaan_id, () => form.is_exclusive_member], () => {
  if (form.target_type === 'filtered') {
    getFilteredMembersCount();
  }
}, { deep: true });

const sendNotification = () => {
  if (!canSubmit.value) return;
  
  loading.value = true;
  
  form.post('/member-notification/send', {
    preserveScroll: true,
    onSuccess: () => {
      resetForm();
      router.visit('/member-notification');
    },
    onFinish: () => {
      loading.value = false;
    },
  });
};

const resetForm = () => {
  form.reset();
  form.target_type = 'all';
  selectedMembers.value = [];
  memberSearch.value = '';
  memberSearchResults.value = [];
  filteredMembersCount.value = null;
};
</script>

