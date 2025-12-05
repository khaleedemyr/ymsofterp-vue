<template>
  <div class="min-h-screen bg-gradient-to-br from-purple-900 via-blue-900 to-indigo-900 flex items-center justify-center p-4">
    <div class="max-w-7xl w-full">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-4xl font-bold text-white mb-2">
          <i class="fa-solid fa-slot-machine text-yellow-400 mr-3"></i>
          YMSoft SLOT MACHINE
        </h1>
        <p class="text-yellow-200 text-lg">Total Peserta: {{ roulettes.length.toLocaleString() }} orang</p>
        <p class="text-blue-200 text-sm">Menampilkan {{ displayCount }} peserta per reel</p>
      </div>

      <!-- Slot Machine Container -->
      <div class="flex justify-center mb-8">
        <div class="bg-black bg-opacity-50 rounded-3xl p-8 shadow-2xl backdrop-blur-sm border-4 border-yellow-400">
          <!-- Slot Machine Frame -->
          <div class="slot-machine-frame bg-gradient-to-b from-gray-800 to-gray-900 rounded-2xl p-6 border-2 border-yellow-300">
            <!-- Reels Container -->
            <div class="flex gap-4 mb-6">
              <div 
                v-for="(reel, reelIndex) in reels" 
                :key="reelIndex"
                class="reel-container bg-gradient-to-b from-gray-700 to-gray-800 rounded-xl p-4 border-2 border-yellow-200 relative overflow-hidden"
              >
                <div class="reel-window h-64 w-32 bg-black rounded-lg overflow-hidden relative">
                  <!-- Reel Items -->
                  <div 
                    class="reel-items"
                    :class="{ 'spinning': isSpinning }"
                    :style="{ transform: `translateY(${reel.offset}px)` }"
                  >
                    <div 
                      v-for="(item, index) in reel.items" 
                      :key="`${reelIndex}-${index}`"
                      class="reel-item h-16 w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white flex items-center justify-center text-center p-2 border-b border-gray-600"
                      :class="{ 'winner-highlight': reel.winnerIndex === index && !isSpinning }"
                    >
                      <div class="text-xs font-bold">
                        <div class="truncate">{{ item.nama }}</div>
                        <div v-if="item.no_hp" class="text-xs opacity-75 truncate">{{ item.no_hp }}</div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Reel Indicator -->
                  <div class="absolute top-1/2 left-0 right-0 h-16 bg-yellow-400 bg-opacity-30 border-y-2 border-yellow-400 transform -translate-y-8 pointer-events-none"></div>
                </div>
                
                <!-- Reel Number -->
                <div class="text-center mt-2">
                  <span class="text-yellow-400 font-bold text-lg">REEL {{ reelIndex + 1 }}</span>
                </div>
              </div>
            </div>
            
            <!-- Slot Machine Controls -->
            <div class="text-center">
              <button 
                @click="spinSlotMachine" 
                :disabled="isSpinning"
                class="bg-gradient-to-r from-yellow-400 to-orange-500 text-black px-12 py-4 rounded-full text-2xl font-bold shadow-2xl hover:shadow-3xl transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed transform hover:scale-105"
              >
                <i class="fa-solid fa-play mr-3"></i>
                {{ isSpinning ? 'SPINNING...' : 'SPIN SLOT!' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Winners Display -->
      <div v-if="currentWinners.length > 0" class="text-center mb-8">
        <div class="bg-black bg-opacity-50 rounded-2xl p-6 shadow-2xl max-w-4xl mx-auto backdrop-blur-sm border-2 border-yellow-400">
          <h2 class="text-3xl font-bold text-yellow-400 mb-4">
            <i class="fa-solid fa-trophy mr-3"></i>
            JACKPOT WINNERS!
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div 
              v-for="(winner, index) in currentWinners" 
              :key="winner.id"
              class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white p-4 rounded-xl border-2 border-yellow-300 relative"
            >
              <div class="absolute -top-2 -right-2 bg-yellow-500 text-black rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold">
                {{ index + 1 }}
              </div>
              <h3 class="text-xl font-bold mb-2">{{ winner.nama }}</h3>
              <p v-if="winner.email" class="text-sm text-yellow-100">{{ winner.email }}</p>
              <p v-if="winner.no_hp" class="text-sm text-yellow-100">{{ winner.no_hp }}</p>
            </div>
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
const currentWinners = ref([]);
const allWinners = ref([]);
const displayCount = ref(20); // Number of items per reel
const reelCount = ref(3); // Number of reels

// Create reels data
const reels = ref([]);

// Initialize reels
const initializeReels = () => {
  reels.value = [];
  for (let i = 0; i < reelCount.value; i++) {
    reels.value.push({
      items: [],
      offset: 0,
      winnerIndex: -1,
      spinning: false
    });
  }
  populateReels();
};

// Populate reels with random participants
const populateReels = () => {
  if (!props.roulettes || props.roulettes.length === 0) {
    console.log('No roulettes data available');
    return;
  }
  
  console.log('Populating reels with', props.roulettes.length, 'participants');
  
  // Create a shuffled copy of all participants
  const shuffled = [...props.roulettes].sort(() => Math.random() - 0.5);
  
  reels.value.forEach((reel, reelIndex) => {
    // For each reel, take a different set of participants
    const startIndex = (reelIndex * displayCount.value) % shuffled.length;
    let reelItems = [];
    
    // Fill the reel with participants
    for (let i = 0; i < displayCount.value; i++) {
      const index = (startIndex + i) % shuffled.length;
      reelItems.push(shuffled[index]);
    }
    
    // Add some extra items for smooth spinning
    const extraCount = Math.min(10, shuffled.length);
    for (let i = 0; i < extraCount; i++) {
      const index = i % shuffled.length;
      reelItems.push(shuffled[index]);
    }
    
    reel.items = reelItems;
    console.log(`Reel ${reelIndex + 1} populated with ${reel.items.length} items`);
  });
};

// Spin slot machine
const spinSlotMachine = () => {
  if (isSpinning.value || !props.roulettes || props.roulettes.length === 0) {
    console.log('Cannot spin: isSpinning=', isSpinning.value, 'roulettes length=', props.roulettes?.length);
    return;
  }
  
  console.log('Starting spin with', props.roulettes.length, 'participants');
  
  isSpinning.value = true;
  currentWinners.value = [];
  
  // Reset reel positions only
  reels.value.forEach(reel => {
    reel.offset = 0;
    reel.winnerIndex = -1;
  });
  
  // Repopulate reels with new random data for each spin
  populateReels();
  
  // Animate each reel
  reels.value.forEach((reel, reelIndex) => {
    const spinDuration = 3000 + (reelIndex * 500); // Staggered spinning
    const finalOffset = Math.floor(Math.random() * displayCount.value) * 64; // 64px per item
    
    console.log(`Reel ${reelIndex + 1} spinning to offset ${finalOffset} with ${reel.items.length} items`);
    
    // Animate spinning
    const startTime = Date.now();
    const animate = () => {
      const elapsed = Date.now() - startTime;
      const progress = Math.min(elapsed / spinDuration, 1);
      
      // Easing function for realistic spinning
      const easeOut = 1 - Math.pow(1 - progress, 3);
      reel.offset = easeOut * (finalOffset + 1000); // Extra distance for spinning effect
      
      if (progress < 1) {
        requestAnimationFrame(animate);
      } else {
        // Set final position
        reel.offset = finalOffset;
        reel.winnerIndex = Math.floor(finalOffset / 64);
        
        // Add winner to current winners
        if (reel.items[reel.winnerIndex]) {
          currentWinners.value.push(reel.items[reel.winnerIndex]);
          console.log(`Reel ${reelIndex + 1} winner:`, reel.items[reel.winnerIndex].nama);
        }
        
        // Check if all reels finished
        if (reelIndex === reels.value.length - 1) {
          setTimeout(() => {
            isSpinning.value = false;
            // Add winners to all winners list
            allWinners.value.push(...currentWinners.value);
            console.log('Spin completed with', currentWinners.value.length, 'winners');
          }, 1000);
        }
      }
    };
    
    setTimeout(() => {
      animate();
    }, reelIndex * 200); // Staggered start
  });
};

// Reset game
const resetGame = () => {
  isSpinning.value = false;
  currentWinners.value = [];
  allWinners.value = [];
  initializeReels();
};

// Initialize on mount
onMounted(() => {
  console.log('SlotMachine mounted with', props.roulettes?.length, 'participants');
  initializeReels();
});

// Watch for props changes
watch(() => props.roulettes, (newRoulettes) => {
  console.log('Roulettes data changed:', newRoulettes?.length, 'participants');
  if (newRoulettes && newRoulettes.length > 0) {
    initializeReels();
  }
}, { immediate: true });
</script>

<style scoped>
.slot-machine-frame {
  box-shadow: 
    0 0 50px rgba(255, 215, 0, 0.3),
    inset 0 0 50px rgba(0, 0, 0, 0.5);
}

.reel-container {
  box-shadow: 
    0 0 20px rgba(255, 215, 0, 0.2),
    inset 0 0 20px rgba(0, 0, 0, 0.3);
}

.reel-window {
  box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.8);
}

.reel-items {
  transition: transform 0.1s linear;
}

.reel-items.spinning {
  transition: transform 0.05s linear;
}

.reel-item {
  transition: all 0.3s ease;
}

.winner-highlight {
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  box-shadow: 0 0 20px rgba(251, 191, 36, 0.8);
  transform: scale(1.05);
  animation: winnerPulse 1s ease-in-out infinite;
}

@keyframes winnerPulse {
  0%, 100% {
    box-shadow: 0 0 20px rgba(251, 191, 36, 0.8);
    transform: scale(1.05);
  }
  50% {
    box-shadow: 0 0 30px rgba(251, 191, 36, 1);
    transform: scale(1.1);
  }
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