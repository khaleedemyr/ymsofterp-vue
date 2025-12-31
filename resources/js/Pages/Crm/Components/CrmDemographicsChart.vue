<script setup>
const props = defineProps({
  data: Object,
});

function formatNumber(number) {
  return number.toLocaleString('id-ID');
}

// Age group descriptions
const ageGroupDescriptions = {
  'Remaja': {
    range: '13 – 18 tahun',
    description: 'Suka makanan kekinian, sering datang berkelompok, tertarik dengan tempat yang Instagramable',
    color: 'bg-red-500'
  },
  'Dewasa Muda': {
    range: '19 – 30 tahun',
    description: 'Aktif kulineran, trend-aware, suka nongkrong, daya beli menengah, sangat terpengaruh review dan media sosial',
    color: 'bg-orange-500'
  },
  'Dewasa Produktif': {
    range: '31 – 45 tahun',
    description: 'Daya beli stabil, mencari kualitas & kenyamanan, sering makan bersama keluarga atau rekan kerja',
    color: 'bg-yellow-500'
  },
  'Dewasa Matang': {
    range: '46 – 59 tahun',
    description: 'Lebih memilih makanan sehat, tempat nyaman, kadang loyal pada brand tertentu',
    color: 'bg-green-500'
  },
  'Usia Tua': {
    range: '60 tahun ke atas',
    description: 'Lebih selektif (terutama kesehatan), jarang mencoba hal baru, cenderung makan bersama keluarga',
    color: 'bg-blue-500'
  },
  'Anak-anak': {
    range: 'Di bawah 13 tahun',
    description: 'Biasanya datang bersama keluarga, suka makanan manis dan menarik',
    color: 'bg-purple-500'
  },
  'Tidak Diketahui': {
    range: 'Tidak diketahui',
    description: 'Data tanggal lahir tidak tersedia',
    color: 'bg-gray-500'
  }
};
</script>

<template>
  <div>
    <!-- Gender Distribution -->
    <div class="mb-6">
      <h4 class="text-sm font-semibold text-gray-700 mb-3">Distribusi Jenis Kelamin</h4>
      <div class="space-y-3">
        <div v-for="(count, gender) in data.gender" :key="gender" class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div 
              class="w-4 h-4 rounded-full"
              :class="gender === 'Laki-laki' ? 'bg-blue-500' : gender === 'Perempuan' ? 'bg-pink-500' : 'bg-gray-500'"
            ></div>
            <span class="text-sm text-gray-700">{{ gender }}</span>
          </div>
          <div class="text-right">
            <span class="text-sm font-semibold text-gray-900">{{ formatNumber(count) }}</span>
            <span class="text-xs text-gray-500 ml-1">
              ({{ Math.round((count / Object.values(data.gender).reduce((a, b) => a + b, 0)) * 100) }}%)
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Age Distribution -->
    <div>
      <h4 class="text-sm font-semibold text-gray-700 mb-3">Distribusi Usia</h4>
      <div class="space-y-4">
        <div v-for="(count, ageGroup) in data.age" :key="ageGroup" class="bg-gray-50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-3">
              <div 
                class="w-4 h-4 rounded-full"
                :class="ageGroupDescriptions[ageGroup]?.color || 'bg-gray-500'"
              ></div>
              <div>
                <span class="text-sm font-semibold text-gray-900">{{ ageGroup }}</span>
                <span class="text-xs text-gray-500 ml-2">{{ ageGroupDescriptions[ageGroup]?.range || '' }}</span>
              </div>
            </div>
            <div class="text-right">
              <span class="text-sm font-semibold text-gray-900">{{ formatNumber(count) }}</span>
              <span class="text-xs text-gray-500 ml-1">
                ({{ Math.round((count / Object.values(data.age).reduce((a, b) => a + b, 0)) * 100) }}%)
              </span>
            </div>
          </div>
          <div class="text-xs text-gray-600 leading-relaxed">
            {{ ageGroupDescriptions[ageGroup]?.description || 'Deskripsi tidak tersedia' }}
          </div>
        </div>
      </div>
    </div>

    <!-- Occupation Distribution -->
    <div class="mt-6 pt-6 border-t border-gray-200">
      <h4 class="text-sm font-semibold text-gray-700 mb-3">Distribusi Pekerjaan</h4>
      <div class="space-y-3">
        <div v-for="(count, occupation) in (data.occupation || {})" :key="occupation" class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-4 h-4 rounded-full bg-indigo-500"></div>
            <span class="text-sm text-gray-700">{{ occupation }}</span>
          </div>
          <div class="text-right">
            <span class="text-sm font-semibold text-gray-900">{{ formatNumber(count) }}</span>
            <span class="text-xs text-gray-500 ml-1">
              ({{ Object.values(data.occupation || {}).reduce((a, b) => a + b, 0) > 0 ? Math.round((count / Object.values(data.occupation || {}).reduce((a, b) => a + b, 0)) * 100) : 0 }}%)
            </span>
          </div>
        </div>
        <div v-if="!data.occupation || Object.keys(data.occupation).length === 0" class="text-center py-4 text-gray-500 text-sm">
          Tidak ada data pekerjaan
        </div>
      </div>
    </div>

    <!-- Summary -->
    <div class="mt-6 pt-4 border-t border-gray-200">
      <div class="grid grid-cols-3 gap-4 text-center">
        <div class="bg-blue-50 rounded-lg p-3">
          <p class="text-sm text-blue-600 font-medium">Total Data Gender</p>
          <p class="text-lg font-bold text-blue-800">
            {{ Object.values(data.gender || {}).reduce((a, b) => a + b, 0).toLocaleString('id-ID') }}
          </p>
        </div>
        <div class="bg-green-50 rounded-lg p-3">
          <p class="text-sm text-green-600 font-medium">Total Data Usia</p>
          <p class="text-lg font-bold text-green-800">
            {{ Object.values(data.age || {}).reduce((a, b) => a + b, 0).toLocaleString('id-ID') }}
          </p>
        </div>
        <div class="bg-indigo-50 rounded-lg p-3">
          <p class="text-sm text-indigo-600 font-medium">Total Data Pekerjaan</p>
          <p class="text-lg font-bold text-indigo-800">
            {{ Object.values(data.occupation || {}).reduce((a, b) => a + b, 0).toLocaleString('id-ID') }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template> 