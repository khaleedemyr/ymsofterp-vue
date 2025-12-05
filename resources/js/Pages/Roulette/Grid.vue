<template>
  <div class="min-h-screen bg-gray-900 flex items-center justify-center p-4">
    <div class="max-w-6xl w-full">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-4xl font-bold text-white mb-2">
          <i class="fa-solid fa-th text-green-400 mr-3"></i>
          YMSoft GRID GAME
        </h1>
        <p class="text-green-200 text-lg">Total Peserta: {{ roulettes.length }} orang</p>
      </div>

                     <!-- Grid Container -->
       <div class="flex justify-center mb-8 gap-8">
         <div class="bg-black bg-opacity-30 rounded-2xl p-6 shadow-2xl backdrop-blur-sm">
           <div class="grid-container">
             <div 
               v-for="(roulette, index) in roulettes" 
               :key="roulette.id"
               v-show="!isAlreadyWinner(roulette.id) && (!winner || winner.id !== roulette.id || (isBlinking && winner && winner.id === roulette.id))"
               class="grid-item"
               :class="{ 
                 'active': activeIndex === index,
                 'winner': winner && winner.id === roulette.id,
                 'winner-blink': isBlinking && winner && winner.id === roulette.id,
                 'highlight': isHighlighted(index)
               }"
               @click="selectItem(index)"
             >
               <div class="item-content">
                 <div class="participant-name">{{ roulette.nama }}</div>
                 <div v-if="roulette.no_hp" class="participant-phone">{{ roulette.no_hp }}</div>
               </div>
             </div>
           </div>
        </div>
        
        <!-- Winners List -->
        <div v-if="winners.length > 0" class="bg-black bg-opacity-30 rounded-2xl p-6 shadow-2xl backdrop-blur-sm min-w-64">
          <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-trophy text-yellow-400"></i>
            Pemenang ({{ winners.length }})
          </h3>
          <div class="space-y-3 max-h-96 overflow-y-auto">
            <div 
              v-for="(winner, index) in winners" 
              :key="winner.id"
              class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white p-4 rounded-xl border border-yellow-300 relative"
            >
              <div class="absolute -top-2 -right-2 bg-yellow-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold">
                {{ index + 1 }}
              </div>
              <h4 class="font-bold mb-1">{{ winner.nama }}</h4>
              <p v-if="winner.email" class="text-sm text-yellow-100">{{ winner.email }}</p>
              <p v-if="winner.no_hp" class="text-sm text-yellow-100">{{ winner.no_hp }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Controls -->
      <div class="text-center mb-8">
        <button 
          @click="startGame" 
          :disabled="isPlaying"
          class="bg-gradient-to-r from-green-400 to-green-600 text-black px-8 py-4 rounded-full text-xl font-bold shadow-2xl hover:shadow-3xl transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <i class="fa-solid fa-play mr-2"></i>
          {{ isPlaying ? 'Memilih...' : 'MULAI GAME!' }}
        </button>
      </div>

             <!-- Result -->
       <div v-if="winner" class="text-center mb-8">
         <div class="bg-black bg-opacity-30 rounded-2xl p-6 shadow-2xl max-w-md mx-auto backdrop-blur-sm">
           <h2 class="text-2xl font-bold text-white mb-4">
             <i class="fa-solid fa-trophy text-green-400 mr-2"></i>
             PESERTA TERPILIH!
           </h2>
           <div class="bg-gradient-to-r from-green-500 to-green-700 text-white p-4 rounded-xl">
             <h3 class="text-xl font-bold mb-2">{{ winner.nama }}</h3>
             <p v-if="winner.email" class="text-green-100">{{ winner.email }}</p>
             <p v-if="winner.no_hp" class="text-green-100">{{ winner.no_hp }}</p>
           </div>
         </div>
       </div>

             <!-- Participants List -->
       <div class="bg-white rounded-2xl p-6 shadow-2xl">
         <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
           <i class="fa-solid fa-users text-green-500"></i>
           Daftar Peserta ({{ roulettes.length }})
         </h3>
         <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-h-96 overflow-y-auto">
           <div 
             v-for="roulette in roulettes" 
             :key="roulette.id"
             class="bg-gradient-to-r from-green-50 to-blue-50 p-4 rounded-xl border border-green-200 hover:shadow-lg transition-all relative"
             :class="{ 'ring-2 ring-green-400 bg-green-50': winner && winner.id === roulette.id }"
           >
             <div v-if="winner && winner.id === roulette.id" class="absolute -top-2 -right-2 bg-green-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold">
               <i class="fa-solid fa-trophy"></i>
             </div>
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

const isPlaying = ref(false);
const winner = ref(null);
const activeIndex = ref(-1);
const highlightHistory = ref([]);
const winners = ref([]);
const isBlinking = ref(false);

function startGame() {
  if (isPlaying.value || props.roulettes.length === 0) return;
  
  // Filter out participants who are already winners
  const eligibleParticipants = props.roulettes.filter(r => !winners.value.some(w => w.id === r.id));
  
  if (eligibleParticipants.length === 0) {
    // All participants are already winners
    alert('Semua peserta sudah menjadi pemenang!');
    return;
  }
  
  isPlaying.value = true;
  winner.value = null;
  highlightHistory.value = [];
  
  // Generate random winner from eligible participants
  const randomIndex = Math.floor(Math.random() * eligibleParticipants.length);
  const selectedWinner = eligibleParticipants[randomIndex];
  
  // Calculate total steps (more participants = more steps)
  const totalSteps = Math.max(50, eligibleParticipants.length * 8);
  let currentStep = 0;
  
  const interval = setInterval(() => {
    // Generate random index for highlighting from eligible participants
    const randomHighlight = Math.floor(Math.random() * eligibleParticipants.length);
    const selectedHighlight = eligibleParticipants[randomHighlight];
    
    // Find the original index in the full roulettes array
    const originalIndex = props.roulettes.findIndex(r => r.id === selectedHighlight.id);
    activeIndex.value = originalIndex;
    highlightHistory.value.push(originalIndex);
    
    currentStep++;
    
    // Stop when reaching total steps
    if (currentStep >= totalSteps) {
      clearInterval(interval);
      
      // Set final winner
      const finalOriginalIndex = props.roulettes.findIndex(r => r.id === selectedWinner.id);
      activeIndex.value = finalOriginalIndex;
      winner.value = selectedWinner;
      isPlaying.value = false;
      
      // Start blinking effect
      isBlinking.value = true;
      
      // After 10 seconds, add to winners list and hide from grid
      setTimeout(() => {
        if (winner.value) {
          winners.value.push(winner.value);
          winner.value = null;
        }
        isBlinking.value = false;
      }, 10000);
      
      // Play sound effect (optional)
      // new Audio('/sounds/grid-win.mp3').play();
    }
  }, 120); // Speed of highlighting - slower for longer game
}

function selectItem(index) {
  if (!isPlaying.value) {
    activeIndex.value = index;
  }
}

function isHighlighted(index) {
  // No highlighting at all - only active and winner states
  return false;
}

const isAlreadyWinner = (rouletteId) => winners.value.some(w => w.id === rouletteId);

function resetGame() {
  winner.value = null;
  isPlaying.value = false;
  activeIndex.value = -1;
  highlightHistory.value = [];
  winners.value = [];
  isBlinking.value = false;
}
</script>

<style scoped>
.grid-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  max-width: 800px;
  margin: 0 auto;
}

.grid-item {
  aspect-ratio: 1;
  background: rgba(255, 255, 255, 0.1);
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  backdrop-filter: blur(10px);
}

.grid-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.grid-item.active {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  border-color: #047857;
  transform: scale(1.05);
  box-shadow: 0 0 30px rgba(16, 185, 129, 0.6);
  animation: pulse 0.5s ease-in-out;
}

.grid-item.winner {
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  border-color: #b45309;
  transform: scale(1.1);
  box-shadow: 0 0 40px rgba(245, 158, 11, 0.8);
  animation: winnerGlow 1s ease-in-out infinite;
}

.grid-item.highlight {
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  border-color: #1d4ed8;
  opacity: 0.7;
}

.item-content {
  text-align: center;
  color: white;
  font-weight: bold;
  z-index: 2;
}

.grid-item.active .item-content,
.grid-item.winner .item-content {
  color: white;
}

.participant-name {
  font-size: 14px;
  font-weight: bold;
  margin-bottom: 4px;
  line-height: 1.2;
}

.participant-phone {
  font-size: 12px;
  opacity: 0.8;
}

@keyframes pulse {
  0% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
  100% {
    transform: scale(1.05);
  }
}

@keyframes winnerGlow {
  0%, 100% {
    box-shadow: 0 0 40px rgba(245, 158, 11, 0.8);
  }
  50% {
    box-shadow: 0 0 60px rgba(245, 158, 11, 1);
  }
}

@keyframes winnerBlink {
  0%, 50% {
    opacity: 1;
    transform: scale(1.1);
    box-shadow: 0 0 40px rgba(245, 158, 11, 0.8);
  }
  25%, 75% {
    opacity: 0.7;
    transform: scale(1.05);
    box-shadow: 0 0 60px rgba(245, 158, 11, 1);
  }
  100% {
    opacity: 1;
    transform: scale(1.1);
    box-shadow: 0 0 40px rgba(245, 158, 11, 0.8);
  }
}

.grid-item.winner-blink {
  animation: winnerBlink 0.8s ease-in-out infinite;
}

.shadow-3xl {
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}
</style> 