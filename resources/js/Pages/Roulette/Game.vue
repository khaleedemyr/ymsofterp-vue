<template>
  <div class="min-h-screen bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 flex items-center justify-center p-4">
    <div class="max-w-4xl w-full">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-4xl font-bold text-white mb-2">
          <i class="fa-solid fa-dice text-yellow-400 mr-3"></i>
          YMSoft ROULETTE
        </h1>
        <p class="text-purple-200 text-lg">Total Peserta: {{ roulettes.length }} orang</p>
      </div>

      <!-- Roulette Wheel -->
      <div class="flex justify-center mb-8">
        <div class="relative">
          <!-- Wheel Container -->
          <div 
            ref="wheelRef"
            class="roulette-wheel w-80 h-80 rounded-full border-8 border-yellow-400 relative overflow-hidden shadow-2xl"
            :style="{ 
              transform: `rotate(${wheelRotation}deg)`,
              transition: isSpinning ? `transform ${spinDuration}s cubic-bezier(0.25, 0.46, 0.45, 0.94)` : 'none'
            }"
          >
                         <!-- Wheel Segments -->
             <div 
               v-for="(roulette, index) in roulettes" 
               :key="roulette.id"
               class="absolute w-full h-full"
               :class="{ 'winner-segment': winner && winner.id === roulette.id }"
               :style="getSegmentStyle(index)"
             >
              <!-- Participant Name -->
              <div 
                class="absolute text-white font-bold text-sm text-center"
                :style="getNameStyle(index)"
              >
                {{ roulette.nama }}
              </div>
            </div>
            
            <!-- Center Circle -->
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-16 h-16 bg-white rounded-full border-4 border-yellow-400 shadow-lg flex items-center justify-center z-10">
              <i class="fa-solid fa-star text-yellow-500 text-xl"></i>
            </div>
          </div>
          
          <!-- Pointer (Fixed Position) -->
          <div class="absolute top-0 left-1/2 transform -translate-x-1/2 -translate-y-1 w-0 h-0 border-l-12 border-r-12 border-b-24 border-transparent border-b-red-500 z-50 shadow-2xl"></div>
        </div>
      </div>

      <!-- Controls -->
      <div class="text-center mb-8">
        <button 
          @click="spinWheel" 
          :disabled="isSpinning"
          class="bg-gradient-to-r from-yellow-400 to-yellow-600 text-black px-8 py-4 rounded-full text-xl font-bold shadow-2xl hover:shadow-3xl transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <i class="fa-solid fa-play mr-2"></i>
          {{ isSpinning ? 'Memutar...' : 'PUTAR ROULETTE!' }}
        </button>
      </div>

      <!-- Result -->
      <div v-if="winner" class="text-center mb-8">
        <div class="bg-white rounded-2xl p-6 shadow-2xl max-w-md mx-auto">
          <h2 class="text-2xl font-bold text-purple-800 mb-4">
            <i class="fa-solid fa-trophy text-yellow-500 mr-2"></i>
            PESERTA TERPILIH!
          </h2>
          <div class="bg-gradient-to-r from-purple-500 to-purple-700 text-white p-4 rounded-xl">
            <h3 class="text-xl font-bold mb-2">{{ winner.nama }}</h3>
            <p v-if="winner.email" class="text-purple-100">{{ winner.email }}</p>
            <p v-if="winner.no_hp" class="text-purple-100">{{ winner.no_hp }}</p>
          </div>
        </div>
      </div>

      <!-- Participants List -->
      <div class="bg-white rounded-2xl p-6 shadow-2xl">
        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-users text-purple-500"></i>
          Daftar Peserta ({{ roulettes.length }})
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-h-96 overflow-y-auto">
          <div 
            v-for="roulette in roulettes" 
            :key="roulette.id"
            class="bg-gradient-to-r from-purple-50 to-blue-50 p-4 rounded-xl border border-purple-200 hover:shadow-lg transition-all"
            :class="{ 'ring-2 ring-yellow-400 bg-yellow-50': winner && winner.id === roulette.id }"
          >
            <h4 class="font-bold text-gray-800 mb-1">{{ roulette.nama }}</h4>
            <p v-if="roulette.email" class="text-sm text-gray-600">{{ roulette.email }}</p>
            <p v-if="roulette.no_hp" class="text-sm text-gray-600">{{ roulette.no_hp }}</p>
          </div>
        </div>
      </div>

      <!-- Reset Button -->
      <div class="text-center mt-6">
        <button 
          @click="resetGame" 
          class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-xl font-semibold transition-all"
        >
          <i class="fa-solid fa-redo mr-2"></i>
          Reset Game
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  roulettes: Array,
});

const isSpinning = ref(false);
const winner = ref(null);
const wheelRef = ref(null);
const wheelRotation = ref(0);
const spinDuration = ref(0);

function getSegmentStyle(index) {
  const totalSegments = props.roulettes.length;
  const anglePerSegment = 360 / totalSegments;
  const startAngle = index * anglePerSegment;
  
  // Create conic gradient for each segment - ensure all colors are different
  const colors = [
    '#EF4444', // Red
    '#F59E0B', // Orange
    '#10B981', // Green
    '#3B82F6', // Blue
    '#8B5CF6', // Purple
    '#EC4899', // Pink
    '#F97316', // Orange-Red
    '#06B6D4', // Cyan
    '#84CC16', // Lime
    '#F43F5E', // Rose
    '#8B5A2B', // Brown
    '#6366F1', // Indigo
    '#14B8A6', // Teal
    '#F59E0B', // Amber
    '#EF4444'  // Red (fallback)
  ];
  const color = colors[index % colors.length];
  
  return {
    background: `conic-gradient(from ${startAngle}deg, ${color} 0deg, ${color} ${anglePerSegment}deg, transparent ${anglePerSegment}deg)`,
  };
}

function getNameStyle(index) {
  const totalSegments = props.roulettes.length;
  const anglePerSegment = 360 / totalSegments;
  const centerAngle = (index * anglePerSegment) + (anglePerSegment / 2);
  const radius = 120; // Distance from center
  
  // Calculate position
  const x = Math.cos((centerAngle - 90) * Math.PI / 180) * radius;
  const y = Math.sin((centerAngle - 90) * Math.PI / 180) * radius;
  
  return {
    left: `calc(50% + ${x}px)`,
    top: `calc(50% + ${y}px)`,
    transform: `translate(-50%, -50%) rotate(${centerAngle}deg)`,
    maxWidth: '80px',
    textShadow: '2px 2px 4px rgba(0,0,0,0.8)',
  };
}

function spinWheel() {
  if (isSpinning.value || props.roulettes.length === 0) return;
  
  isSpinning.value = true;
  winner.value = null;
  
  // Generate random number of full rotations (3-5 rotations)
  const rotations = 3 + Math.floor(Math.random() * 3);
  
  // Generate random final position (0 to number of segments)
  const randomIndex = Math.floor(Math.random() * props.roulettes.length);
  const anglePerSegment = 360 / props.roulettes.length;
  
  // Calculate final angle - pointer is at top (0 degrees), so we need to align segment with pointer
  // Each segment center is at (index * anglePerSegment) + (anglePerSegment / 2)
  const segmentCenterAngle = (randomIndex * anglePerSegment) + (anglePerSegment / 2);
  
  // We want the segment center to align with the pointer (top position = 0 degrees)
  // So we need to rotate the wheel so that the segment center moves to the top
  const finalAngle = 360 - segmentCenterAngle;
  
  // Calculate total rotation (full rotations + final position)
  const totalRotation = (rotations * 360) + finalAngle;
  
  // Set animation duration based on rotations (more rotations = longer duration)
  spinDuration.value = 3 + (rotations * 0.5); // 3-5.5 seconds
  
  // Apply the animation
  wheelRotation.value = totalRotation;
  
  // Set winner after animation completes
  setTimeout(() => {
    winner.value = props.roulettes[randomIndex];
    isSpinning.value = false;
    
    // Play sound effect (optional)
    // new Audio('/sounds/roulette-win.mp3').play();
  }, (spinDuration.value * 1000) + 100);
}

function resetGame() {
  winner.value = null;
  isSpinning.value = false;
  wheelRotation.value = 0;
}
</script>

<style scoped>
.roulette-wheel {
  transform-origin: center;
  will-change: transform;
}

.shadow-3xl {
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

@keyframes winnerBlink {
  0%, 50% {
    opacity: 1;
    filter: brightness(1) saturate(1);
  }
  25%, 75% {
    opacity: 0.8;
    filter: brightness(1.3) saturate(1.5);
  }
  100% {
    opacity: 1;
    filter: brightness(1) saturate(1);
  }
}

.winner-segment {
  animation: winnerBlink 0.8s ease-in-out infinite;
  z-index: 5;
}
</style> 