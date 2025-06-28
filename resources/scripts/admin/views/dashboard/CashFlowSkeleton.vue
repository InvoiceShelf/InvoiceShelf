<template>
    <div class="bg-white p-2 sm:p-4 rounded-md sm:rounded-lg border border-gray-200 shadow-sm space-y-2 sm:space-y-4 min-w-0">
      <!-- Header -->
      <div class="flex items-start justify-between min-w-0">
        <div class="space-y-1 sm:space-y-2 flex-1 min-w-0">
          <div class="h-4 sm:h-5 w-32 sm:w-48 bg-gray-300 rounded animate-pulse"></div>
          <div class="h-2 sm:h-3 w-40 sm:w-56 bg-gray-200 rounded animate-pulse"></div>
        </div>
        <div class="flex space-x-1 sm:space-x-2 flex-shrink-0">
          <div class="h-6 sm:h-8 w-16 sm:w-20 bg-gray-200 border border-gray-300 rounded animate-pulse"></div>
          <div class="h-6 sm:h-8 w-20 sm:w-24 bg-gray-200 border border-gray-300 rounded animate-pulse"></div>
        </div>
      </div>
    
      <!-- Chart Area -->
      <div class="relative bg-gray-50 rounded p-2 sm:p-4 min-h-[140px] sm:min-h-[220px]">
        <!-- Y-axis labels -->
        <div class="absolute left-0 top-0 bottom-0 flex flex-col justify-between py-2 sm:py-4 text-xs">
          <div class="h-1.5 sm:h-2 w-3 sm:w-4 bg-gray-300 rounded animate-pulse"></div>
          <div class="h-1.5 sm:h-2 w-3 sm:w-4 bg-gray-300 rounded animate-pulse"></div>
          <div class="h-1.5 sm:h-2 w-3 sm:w-4 bg-gray-300 rounded animate-pulse"></div>
          <div class="h-1.5 sm:h-2 w-3 sm:w-4 bg-gray-300 rounded animate-pulse"></div>
          <div class="h-1.5 sm:h-2 w-3 sm:w-4 bg-gray-300 rounded animate-pulse"></div>
        </div>
    
        <!-- Chart content area -->
        <div class="ml-4 sm:ml-6 mr-2 sm:mr-4 h-full relative">
          <!-- Grid overlay: horizontales + verticales -->
          <div class="absolute inset-0 pointer-events-none">
            <!-- Líneas horizontales -->
            <div class="absolute inset-0 flex flex-col justify-between">
              <div class="h-px bg-gray-200"></div>
              <div class="h-px bg-gray-200"></div>
              <div class="h-px bg-gray-200"></div>
              <div class="h-px bg-gray-200"></div>
              <div class="h-px bg-gray-200"></div>
            </div>
            <!-- Líneas verticales -->
            <div class="absolute inset-0 flex">
              <div class="flex-1 border-l border-gray-200"></div>
              <div class="flex-1 border-l border-gray-200"></div>
              <div class="flex-1 border-l border-gray-200"></div>
              <div class="flex-1 border-l border-gray-200"></div>
              <div class="flex-1 border-l border-gray-200"></div>
            </div>
          </div>
    
          <!-- SVG Chart Lines -->
          <svg class="absolute inset-0 w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
            <!-- Expenses area -->
            <path 
              d="M 0 85 Q 25 82 50 80 Q 75 78 100 85 L 100 100 L 0 100 Z" 
              fill="rgb(254 202 202)" 
              fill-opacity="0.6"
              class="animate-pulse"
            />
            <!-- Real Income line (verde) -->
            <path 
              d="M 0 70 Q 15 65 25 45 Q 35 25 45 20 Q 55 30 65 35 Q 75 40 100 45" 
              stroke="rgb(34 197 94)" 
              stroke-width="1.5" 
              fill="none"
              class="animate-pulse"
            />
            <!-- Projected Income line (azul, dashed) -->
            <path 
              d="M 0 75 Q 15 70 25 50 Q 35 30 45 25 Q 55 20 65 15 Q 75 25 85 45 Q 95 65 100 85" 
              stroke="rgb(59 130 246)" 
              stroke-width="1.5" 
              stroke-dasharray="3,2"
              fill="none"
              class="animate-pulse"
            />
            <!-- Net Cash Flow line (morado) -->
            <path 
              d="M 0 65 Q 15 60 25 40 Q 35 20 45 15 Q 55 25 65 30 Q 75 35 85 50 Q 95 65 100 75" 
              stroke="rgb(147 51 234)" 
              stroke-width="1.5" 
              fill="none"
              class="animate-pulse"
            />
    
            <!-- Data points -->
            <!-- Verde -->
            <circle cx="0"   cy="70" r="1.5" fill="rgb(34 197 94)" class="animate-pulse"/>
            <circle cx="25"  cy="45" r="1.5" fill="rgb(34 197 94)" class="animate-pulse"/>
            <circle cx="45"  cy="20" r="1.5" fill="rgb(34 197 94)" class="animate-pulse"/>
            <circle cx="65"  cy="35" r="1.5" fill="rgb(34 197 94)" class="animate-pulse"/>
            <circle cx="100" cy="45" r="1.5" fill="rgb(34 197 94)" class="animate-pulse"/>
            <!-- Azul -->
            <circle cx="0"   cy="75" r="1.5" fill="rgb(59 130 246)" class="animate-pulse"/>
            <circle cx="25"  cy="50" r="1.5" fill="rgb(59 130 246)" class="animate-pulse"/>
            <circle cx="45"  cy="25" r="1.5" fill="rgb(59 130 246)" class="animate-pulse"/>
            <circle cx="65"  cy="15" r="1.5" fill="rgb(59 130 246)" class="animate-pulse"/>
            <circle cx="85"  cy="45" r="1.5" fill="rgb(59 130 246)" class="animate-pulse"/>
            <!-- Morado -->
            <circle cx="0"   cy="65" r="1.5" fill="rgb(147 51 234)" class="animate-pulse"/>
            <circle cx="25"  cy="40" r="1.5" fill="rgb(147 51 234)" class="animate-pulse"/>
            <circle cx="45"  cy="15" r="1.5" fill="rgb(147 51 234)" class="animate-pulse"/>
            <circle cx="65"  cy="30" r="1.5" fill="rgb(147 51 234)" class="animate-pulse"/>
            <circle cx="85"  cy="50" r="1.5" fill="rgb(147 51 234)" class="animate-pulse"/>
          </svg>
    
          <!-- Today indicator line -->
          <div class="absolute left-[60%] top-0 bottom-0 w-0.5 bg-orange-400 animate-pulse opacity-70">
            <div class="absolute -top-1 left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-2 border-r-2 border-b-2 border-transparent border-b-orange-400"></div>
          </div>
    
          <!-- X-axis labels -->
          <div class="absolute -bottom-4 sm:-bottom-6 left-0 right-0 flex justify-between text-xs">
            <div class="h-1.5 sm:h-2 w-4 sm:w-6 bg-gray-300 rounded animate-pulse"></div>
            <div class="h-1.5 sm:h-2 w-4 sm:w-6 bg-gray-300 rounded animate-pulse"></div>
            <div class="h-1.5 sm:h-2 w-4 sm:w-6 bg-gray-300 rounded animate-pulse"></div>
            <div class="h-1.5 sm:h-2 w-4 sm:w-6 bg-gray-300 rounded animate-pulse"></div>
            <div class="h-1.5 sm:h-2 w-4 sm:w-6 bg-gray-300 rounded animate-pulse"></div>
            <div class="h-1.5 sm:h-2 w-4 sm:w-6 bg-gray-300 rounded animate-pulse"></div>
          </div>
        </div>
    
        <!-- Today badge -->
        <div class="absolute bottom-1 sm:bottom-2 right-1 sm:right-2">
          <div class="h-4 sm:h-5 w-12 sm:w-16 bg-yellow-200 rounded animate-pulse"></div>
        </div>
      </div>
    
      <!-- Legend -->
      <div class="flex flex-wrap gap-2 sm:gap-4 justify-center pt-2 sm:pt-4">
        <div class="flex items-center space-x-1 sm:space-x-2">
          <div class="w-3 sm:w-4 h-0.5 bg-green-400 animate-pulse"></div>
          <div class="h-2 sm:h-3 w-12 sm:w-16 bg-gray-200 rounded animate-pulse"></div>
        </div>
        <div class="flex items-center space-x-1 sm:space-x-2">
          <div class="w-3 sm:w-4 h-0.5 bg-blue-400 animate-pulse border-dashed"></div>
          <div class="h-2 sm:h-3 w-16 sm:w-20 bg-gray-200 rounded animate-pulse"></div>
        </div>
        <div class="flex items-center space-x-1 sm:space-x-2">
          <div class="w-3 sm:w-4 h-2 sm:h-3 bg-red-200 animate-pulse"></div>
          <div class="h-2 sm:h-3 w-10 sm:w-14 bg-gray-200 rounded animate-pulse"></div>
        </div>
        <div class="flex items-center space-x-1 sm:space-x-2">
          <div class="w-3 sm:w-4 h-0.5 bg-purple-400 animate-pulse"></div>
          <div class="h-2 sm:h-3 w-14 sm:w-18 bg-gray-200 rounded animate-pulse"></div>
        </div>
      </div>
    </div>
    </template>
    
    <script setup>
    // Skeleton de “Predictive Cash Flow Analysis” con grid lines visibles
    </script>
    