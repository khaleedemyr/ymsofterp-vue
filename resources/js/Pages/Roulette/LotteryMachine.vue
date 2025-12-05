<template>
  <div class="min-h-screen bg-gradient-to-br from-red-900 via-purple-900 to-blue-900 flex items-center justify-center p-4">
    <div class="max-w-7xl w-full">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-4xl font-bold text-white mb-2">
          <i class="fa-solid fa-dice text-yellow-400 mr-3"></i>
          YMSoft LOTTERY MACHINE
        </h1>
        <p class="text-yellow-200 text-lg">Total Peserta: {{ roulettes.length.toLocaleString() }} orang</p>
        <p class="text-blue-200 text-sm">Menampilkan {{ displayCount }} bola dalam mesin</p>
      </div>

      <!-- Lottery Machine Container -->
      <div class="flex justify-center mb-8">
        <div class="bg-black bg-opacity-50 rounded-3xl p-8 shadow-2xl backdrop-blur-sm border-4 border-yellow-400">
          <!-- Lottery Machine Frame -->
          <div class="lottery-machine-frame bg-gradient-to-b from-gray-800 to-gray-900 rounded-2xl p-6 border-2 border-yellow-300">
            <!-- Balls Container -->
            <div class="balls-container h-96 w-full bg-gradient-to-b from-blue-900 to-purple-900 rounded-xl p-4 border-2 border-yellow-200 relative overflow-hidden">
                             <!-- Balls -->
               <div 
                 v-for="(ball, index) in displayBalls" 
                 :key="ball.id"
                 class="ball absolute rounded-full text-white flex items-center justify-center text-xs font-bold border-2 border-white shadow-lg transition-all duration-300"
                 :class="{ 
                   'winner-ball': winner && winner.id === ball.id && !isSpinning,
                   'spinning-ball': isSpinning
                 }"
                 :style="isSpinning ? getAnimatedBallStyle(index) : getBallStyle(index)"
               >
                <div class="text-center">
                  <div class="truncate px-1">{{ ball.nama }}</div>
                  <div v-if="ball.no_hp" class="text-xs opacity-75 truncate px-1">{{ ball.no_hp }}</div>
                </div>
              </div>
              
              <!-- Air Bubbles Effect -->
              <div class="absolute inset-0 pointer-events-none">
                <div 
                  v-for="i in 20" 
                  :key="i"
                  class="bubble absolute bg-white bg-opacity-20 rounded-full animate-bubble"
                  :style="getBubbleStyle(i)"
                ></div>
              </div>
            </div>
            
            <!-- Machine Controls -->
            <div class="text-center mt-6">
              <button 
                @click="startLottery" 
                :disabled="isSpinning"
                class="bg-gradient-to-r from-red-500 to-pink-600 text-white px-12 py-4 rounded-full text-2xl font-bold shadow-2xl hover:shadow-3xl transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed transform hover:scale-105"
              >
                <i class="fa-solid fa-play mr-3"></i>
                {{ isSpinning ? 'DRAWING...' : 'DRAW WINNER!' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Winner Display -->
      <div v-if="winner" class="text-center mb-8">
        <div class="bg-black bg-opacity-50 rounded-2xl p-6 shadow-2xl max-w-2xl mx-auto backdrop-blur-sm border-2 border-yellow-400">
          <h2 class="text-3xl font-bold text-yellow-400 mb-4">
            <i class="fa-solid fa-trophy mr-3"></i>
            CONGRATULATIONS!
          </h2>
          <div class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white p-6 rounded-xl border-2 border-yellow-300">
            <h3 class="text-2xl font-bold mb-2">{{ winner.nama }}</h3>
            <p v-if="winner.email" class="text-lg text-yellow-100">{{ winner.email }}</p>
            <p v-if="winner.no_hp" class="text-lg text-yellow-100">{{ winner.no_hp }}</p>
          </div>
        </div>
      </div>

      <!-- All Winners List -->
      <div v-if="allWinners.length > 0" class="bg-black bg-opacity-50 rounded-2xl p-6 shadow-2xl backdrop-blur-sm border-2 border-yellow-400">
        <h3 class="text-2xl font-bold text-yellow-400 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-trophy"></i>
          Semua Pemenang ({{ allWinners.length }})
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 max-h-96 overflow-y-auto">
          <div 
            v-for="(winner, index) in allWinners" 
            :key="winner.id"
            class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white p-3 rounded-lg border border-yellow-300 relative"
          >
            <div class="absolute -top-1 -right-1 bg-yellow-500 text-black rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold">
              {{ index + 1 }}
            </div>
            <h4 class="font-bold text-sm mb-1 truncate">{{ winner.nama }}</h4>
            <p v-if="winner.no_hp" class="text-xs text-yellow-100 truncate">{{ winner.no_hp }}</p>
          </div>
        </div>
      </div>

      <!-- Reset Button -->
      <div class="text-center mt-8">
        <button 
          @click="resetGame" 
          class="bg-gray-600 hover:bg-gray-700 text-white px-8 py-3 rounded-xl font-semibold transition-all border-2 border-gray-500"
        >
          <i class="fa-solid fa-redo mr-2"></i>
          Reset Game
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';

const props = defineProps({
  roulettes: Array,
});

const isSpinning = ref(false);
const winner = ref(null);
const allWinners = ref([]);
const displayCount = ref(0); // Will be set to all participants
const displayBalls = ref([]);

// Initialize display balls
const initializeBalls = () => {
  if (!props.roulettes || props.roulettes.length === 0) {
    console.log('No roulettes data available');
    return;
  }
  
  console.log('Initializing lottery machine with', props.roulettes.length, 'participants');
  
  // Set display count to all participants
  displayCount.value = props.roulettes.length;
  
  // Shuffle all participants
  const shuffled = [...props.roulettes].sort(() => Math.random() - 0.5);
  displayBalls.value = shuffled;
  
  console.log('Display balls initialized with', displayBalls.value.length, 'balls');
};

// Get random ball style
const getBallStyle = (index) => {
  const colors = [
    'bg-red-500', 'bg-blue-500', 'bg-green-500', 'bg-yellow-500', 
    'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-orange-500',
    'bg-teal-500', 'bg-cyan-500', 'bg-lime-500', 'bg-emerald-500'
  ];
  
  // Smaller size for more balls
  const size = Math.random() * 30 + 40; // 40-70px
  const x = Math.random() * 85 + 7.5; // 7.5-92.5%
  const y = Math.random() * 85 + 7.5; // 7.5-92.5%
  const color = colors[index % colors.length];
  
  return {
    width: `${size}px`,
    height: `${size}px`,
    left: `${x}%`,
    top: `${y}%`,
    backgroundColor: color.replace('bg-', '').split('-')[0],
    zIndex: Math.floor(Math.random() * 10) + 1
  };
};

// Get animated ball style during spinning
const getAnimatedBallStyle = (index) => {
  const colors = [
    'bg-red-500', 'bg-blue-500', 'bg-green-500', 'bg-yellow-500', 
    'bg-purple-500', 'bg-pink-500', 'bg-indigo-500', 'bg-orange-500',
    'bg-teal-500', 'bg-cyan-500', 'bg-lime-500', 'bg-emerald-500'
  ];
  
  const size = Math.random() * 30 + 40;
  const color = colors[index % colors.length];
  
  // Create realistic lottery ball movement
  const time = Date.now() * 0.002;
  const radius = 25 + Math.random() * 15;
  const speed = 0.8 + Math.random() * 0.4;
  const offset = index * 0.2;
  
  // Add some randomness to make it more chaotic
  const randomX = Math.sin(time * 0.5 + offset) * 10;
  const randomY = Math.cos(time * 0.7 + offset) * 10;
  
  const centerX = 50 + Math.sin(time * speed + offset) * radius + randomX;
  const centerY = 50 + Math.cos(time * speed + offset) * radius + randomY;
  
  return {
    width: `${size}px`,
    height: `${size}px`,
    left: `${Math.max(5, Math.min(95, centerX))}%`,
    top: `${Math.max(5, Math.min(95, centerY))}%`,
    backgroundColor: color.replace('bg-', '').split('-')[0],
    zIndex: Math.floor(Math.random() * 10) + 1
  };
};

// Get bubble style for animation
const getBubbleStyle = (index) => {
  const size = Math.random() * 20 + 10;
  const x = Math.random() * 100;
  const delay = Math.random() * 5;
  
  return {
    width: `${size}px`,
    height: `${size}px`,
    left: `${x}%`,
    bottom: '-20px',
    animationDelay: `${delay}s`
  };
};

// Start lottery
const startLottery = () => {
  if (isSpinning.value || !props.roulettes || props.roulettes.length === 0) {
    console.log('Cannot start lottery');
    return;
  }
  
  console.log('Starting lottery with', props.roulettes.length, 'participants');
  
  isSpinning.value = true;
  winner.value = null;
  
  // Randomly select winner from ALL participants
  const randomIndex = Math.floor(Math.random() * props.roulettes.length);
  const selectedWinner = props.roulettes[randomIndex];
  
  console.log('Selected winner:', selectedWinner.nama);
  
  // Start continuous animation for 4 seconds
  setTimeout(() => {
    // Stop spinning and show winner
    isSpinning.value = false;
    
    // Final shuffle and show winner
    const shuffled = [...props.roulettes].sort(() => Math.random() - 0.5);
    displayBalls.value = shuffled;
    
    // Find the winner in display balls
    const winnerBallIndex = displayBalls.value.findIndex(ball => ball.id === selectedWinner.id);
    
    if (winnerBallIndex !== -1) {
      // Move winner ball to center
      displayBalls.value[winnerBallIndex] = {
        ...selectedWinner,
        style: {
          width: '120px',
          height: '120px',
          left: '50%',
          top: '50%',
          transform: 'translate(-50%, -50%)',
          backgroundColor: '#fbbf24',
          zIndex: 100
        }
      };
    }
    
    setTimeout(() => {
      winner.value = selectedWinner;
      allWinners.value.push(selectedWinner);
      console.log('Lottery completed');
    }, 1000);
    
  }, 4000); // Spin for 4 seconds
};

// Reset game
const resetGame = () => {
  isSpinning.value = false;
  winner.value = null;
  allWinners.value = [];
  initializeBalls();
};

// Initialize on mount
onMounted(() => {
  console.log('LotteryMachine mounted with', props.roulettes?.length, 'participants');
  initializeBalls();
});

// Watch for props changes
watch(() => props.roulettes, (newRoulettes) => {
  console.log('Roulettes data changed:', newRoulettes?.length, 'participants');
  if (newRoulettes && newRoulettes.length > 0) {
    initializeBalls();
  }
}, { immediate: true });
</script>

<style scoped>
.lottery-machine-frame {
  box-shadow: 
    0 0 50px rgba(255, 215, 0, 0.3),
    inset 0 0 50px rgba(0, 0, 0, 0.5);
}

.balls-container {
  box-shadow: inset 0 0 30px rgba(0, 0, 0, 0.8);
  background: radial-gradient(circle at center, #1e3a8a 0%, #7c3aed 50%, #1e40af 100%);
}

.ball {
  transition: all 0.3s ease;
  cursor: pointer;
}

.ball:hover {
  transform: scale(1.1);
  box-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
}

.spinning-ball {
  transition: none !important;
}

.winner-ball {
  animation: winnerPulse 1s ease-in-out infinite;
  box-shadow: 0 0 30px rgba(251, 191, 36, 1);
}



@keyframes winnerPulse {
  0%, 100% {
    box-shadow: 0 0 30px rgba(251, 191, 36, 1);
    transform: scale(1.1);
  }
  50% {
    box-shadow: 0 0 50px rgba(251, 191, 36, 1.5);
    transform: scale(1.2);
  }
}

@keyframes bubble {
  0% {
    transform: translateY(0) scale(0);
    opacity: 0;
  }
  50% {
    opacity: 1;
  }
  100% {
    transform: translateY(-400px) scale(1);
    opacity: 0;
  }
}

.animate-bubble {
  animation: bubble 6s linear infinite;
}

/* Custom scrollbar for winners list */
.overflow-y-auto::-webkit-scrollbar {
  width: 8px;
}

.overflow-y-auto::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
  background: rgba(251, 191, 36, 0.8);
  border-radius: 4px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: rgba(251, 191, 36, 1);
}
</style> 