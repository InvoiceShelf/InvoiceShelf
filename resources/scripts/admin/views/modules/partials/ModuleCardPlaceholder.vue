<template>
  <BaseContentPlaceholders>
    <div
      class="
        overflow-hidden
        rounded-sm
        border border-gray-300
        bg-white
        shadow-sm
      "
    >
      <BaseContentPlaceholdersBox class="h-40 w-full" rounded />
      <div class="flex flex-col gap-3 p-4">
        <BaseContentPlaceholdersText class="h-5 w-4/5" :lines="1" rounded />
        <BaseContentPlaceholdersText class="h-4 w-1/2" :lines="1" rounded />
        <BaseContentPlaceholdersText class="mt-1 h-16 w-full" :lines="3" rounded />
        <div
          class="
            mt-2
            flex flex-col gap-3
            border-t border-gray-100
            pt-4
            sm:flex-row sm:items-center sm:justify-between
          "
        >
          <BaseContentPlaceholdersText class="h-4 w-24" :lines="1" rounded />
          <BaseContentPlaceholdersBox class="h-9 w-28 rounded-md" rounded />
        </div>
      </div>
    </div>
  </BaseContentPlaceholders>
</template>
