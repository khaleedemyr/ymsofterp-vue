<template>
  <div class="w-20 h-20 flex items-center justify-center">
    <!-- Cerah (800) -->
    <svg v-if="type==='clear'" viewBox="0 0 64 64" class="w-full h-full animate-spin-slow">
      <circle cx="32" cy="32" r="14" fill="#FFC107" />
      <g stroke="#FF9800" stroke-width="3">
        <line x1="32" y1="4" x2="32" y2="16" />
        <line x1="32" y1="48" x2="32" y2="60" />
        <line x1="4" y1="32" x2="16" y2="32" />
        <line x1="48" y1="32" x2="60" y2="32" />
        <line x1="12" y1="12" x2="20" y2="20" />
        <line x1="44" y1="44" x2="52" y2="52" />
        <line x1="12" y1="52" x2="20" y2="44" />
        <line x1="44" y1="20" x2="52" y2="12" />
      </g>
    </svg>
    <!-- Berawan (801-804) -->
    <svg v-else-if="type==='clouds'" viewBox="0 0 64 64" class="w-full h-full">
      <ellipse cx="32" cy="40" rx="18" ry="10" fill="#757575">
        <animate attributeName="cx" values="32;36;32" dur="2s" repeatCount="indefinite" />
      </ellipse>
      <ellipse cx="44" cy="44" rx="10" ry="6" fill="#90A4AE">
        <animate attributeName="cx" values="44;40;44" dur="2s" repeatCount="indefinite" />
      </ellipse>
    </svg>
    <!-- Hujan (5xx) -->
    <svg v-else-if="type==='rain'" viewBox="0 0 64 64" class="w-full h-full">
      <ellipse cx="32" cy="40" rx="18" ry="10" fill="#757575" />
      <ellipse cx="44" cy="44" rx="10" ry="6" fill="#90A4AE" />
      <g>
        <line v-for="n in 4" :key="n" :x1="20+n*6" y1="50" :x2="18+n*6" y2="60" stroke="#1565C0" stroke-width="2">
          <animate attributeName="y1" values="50;60;50" :begin="(n-1)*0.3+'s'" dur="1.2s" repeatCount="indefinite" />
          <animate attributeName="y2" values="60;70;60" :begin="(n-1)*0.3+'s'" dur="1.2s" repeatCount="indefinite" />
        </line>
      </g>
    </svg>
    <!-- Petir (2xx) -->
    <svg v-else-if="type==='thunder'" viewBox="0 0 64 64" class="w-full h-full">
      <ellipse cx="32" cy="40" rx="18" ry="10" fill="#757575" />
      <ellipse cx="44" cy="44" rx="10" ry="6" fill="#90A4AE" />
      <polyline points="30,48 36,48 32,58 38,58" fill="none" stroke="#FFC107" stroke-width="4">
        <animate attributeName="stroke-opacity" values="1;0.2;1" dur="0.7s" repeatCount="indefinite" />
      </polyline>
    </svg>
    <!-- Salju (6xx) -->
    <svg v-else-if="type==='snow'" viewBox="0 0 64 64" class="w-full h-full">
      <ellipse cx="32" cy="40" rx="18" ry="10" fill="#757575" />
      <ellipse cx="44" cy="44" rx="10" ry="6" fill="#90A4AE" />
      <g>
        <circle v-for="n in 4" :key="n" :cx="20+n*8" cy="54" r="2" fill="#81D4FA">
          <animate attributeName="cy" values="54;64;54" :begin="(n-1)*0.3+'s'" dur="1.5s" repeatCount="indefinite" />
        </circle>
      </g>
    </svg>
    <!-- Kabut (7xx) -->
    <svg v-else-if="type==='mist'" viewBox="0 0 64 64" class="w-full h-full">
      <ellipse cx="32" cy="40" rx="18" ry="10" fill="#B0BEC5" />
      <ellipse cx="44" cy="44" rx="10" ry="6" fill="#CFD8DC" />
      <rect x="16" y="54" width="32" height="3" rx="2" fill="#B0BEC5">
        <animate attributeName="x" values="16;24;16" dur="2s" repeatCount="indefinite" />
      </rect>
      <rect x="20" y="59" width="24" height="2" rx="1" fill="#CFD8DC">
        <animate attributeName="x" values="20;28;20" dur="2s" repeatCount="indefinite" />
      </rect>
    </svg>
    <!-- Default -->
    <svg v-else viewBox="0 0 64 64" class="w-full h-full">
      <circle cx="32" cy="32" r="14" fill="#B0BEC5" />
    </svg>
  </div>
</template>

<script setup>
const props = defineProps({
  code: {
    type: Number,
    required: true
  }
});

// Mapping kode cuaca OWM ke tipe animasi
const getType = (code) => {
  if (code === 800) return 'clear';
  if (code >= 801 && code <= 804) return 'clouds';
  if (code >= 200 && code < 300) return 'thunder';
  if (code >= 300 && code < 400) return 'drizzle';
  if (code >= 500 && code < 600) return 'rain';
  if (code >= 600 && code < 700) return 'snow';
  if (code >= 700 && code < 800) return 'mist';
  return 'clouds';
};
const type = getType(props.code);
</script>

<style scoped>
@keyframes spin-slow {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
.animate-spin-slow {
  animation: spin-slow 8s linear infinite;
}
</style>