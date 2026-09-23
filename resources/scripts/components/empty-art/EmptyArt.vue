<template>
  <!--
    Line illustrations for empty states, drawn in the logo's thin-stroke
    style. Every stroke and fill comes from theme tokens (see .empty-art in
    invoiceshelf.css), so they follow light and dark.
  -->
  <div class="empty-art" aria-hidden="true">
    <svg viewBox="0 0 120 92">
      <template v-if="name === 'invoice'">
        <rect x="36" y="8" width="52" height="72" rx="6" class="ea-paper" />
        <path d="M46 22h18" class="ea-ink" />
        <path d="M46 33h32M46 41h32M46 49h22" class="ea-faint" />
        <path d="M46 63h12M68 63h10" class="ea-ink" />
        <circle cx="88" cy="72" r="13" class="ea-badge" />
        <path d="M82.5 72l4 4 7-7.5" class="ea-mark" />
      </template>

      <template v-else-if="name === 'estimate'">
        <rect x="34" y="8" width="52" height="72" rx="6" class="ea-paper" />
        <path d="M44 22h18" class="ea-ink" />
        <path d="M44 33h32M44 41h26M44 49h30" class="ea-faint" stroke-dasharray="3 4" />
        <circle cx="88" cy="70" r="13" class="ea-badge" />
        <path d="M82 76l2-6 9-9 4 4-9 9z" class="ea-mark" />
      </template>

      <template v-else-if="name === 'recurring'">
        <rect x="30" y="8" width="50" height="68" rx="6" class="ea-paper" />
        <path d="M40 22h18" class="ea-ink" />
        <path d="M40 33h30M40 41h30M40 49h20" class="ea-faint" />
        <circle cx="84" cy="68" r="14" class="ea-badge" />
        <path d="M77.5 66a7 7 0 0 1 12-4.5M90.5 70a7 7 0 0 1-12 4.5" class="ea-mark" />
        <path d="M89.5 57.5v4h-4M78.5 78.5v-4h4" class="ea-mark" />
      </template>

      <template v-else-if="name === 'payment'">
        <rect x="22" y="22" width="70" height="42" rx="6" class="ea-paper" />
        <circle cx="57" cy="43" r="9" class="ea-ink" />
        <path d="M32 32h8M74 54h8" class="ea-faint" />
        <circle cx="90" cy="68" r="13" class="ea-badge" />
        <path d="M84.5 68l4 4 7-7.5" class="ea-mark" />
      </template>

      <template v-else-if="name === 'expense'">
        <path d="M38 8h46v70l-5.75-4-5.75 4-5.75-4-5.75 4-5.75-4-5.75 4-5.75-4-5.75 4z" class="ea-paper" />
        <path d="M48 22h26" class="ea-ink" />
        <path d="M48 32h26M48 40h18" class="ea-faint" />
        <path d="M48 54h10M66 54h8" class="ea-ink" />
        <circle cx="88" cy="68" r="13" class="ea-badge" />
        <circle cx="88" cy="68" r="7.5" class="ea-ink" />
        <path d="M88 64.5v7" class="ea-mark" />
      </template>

      <template v-else-if="name === 'customer'">
        <rect x="44" y="12" width="58" height="42" rx="6" class="ea-paper-back" />
        <rect x="22" y="28" width="64" height="46" rx="6" class="ea-paper" />
        <circle cx="40" cy="45" r="7" class="ea-ink" />
        <path d="M30 63c2.2-5 6-7.4 10-7.4s7.8 2.4 10 7.4" class="ea-ink" />
        <path d="M58 43h20" class="ea-ink" />
        <path d="M58 51h16M58 59h11" class="ea-faint" />
        <circle cx="88" cy="74" r="12" class="ea-badge" />
        <path d="M88 68.5v11M82.5 74h11" class="ea-mark" />
      </template>

      <template v-else-if="name === 'item'">
        <path d="M60 12l30 14v34L60 74 30 60V26z" class="ea-paper" />
        <path d="M30 26l30 14 30-14M60 40v34" class="ea-ink" />
        <path d="M45 19l30 14" class="ea-faint" />
        <circle cx="90" cy="70" r="12" class="ea-badge" />
        <path d="M90 64.5v11M84.5 70h11" class="ea-mark" />
      </template>

      <template v-else-if="name === 'member'">
        <circle cx="48" cy="34" r="10" class="ea-paper" />
        <path d="M28 70c3-11 11-16 20-16s17 5 20 16" class="ea-paper" />
        <circle cx="76" cy="38" r="8" class="ea-paper-back" />
        <path d="M66 56c3-2.4 6.4-3.6 10-3.6 7.4 0 13.6 4.4 16 13.6" class="ea-faint" />
        <circle cx="90" cy="72" r="12" class="ea-badge" />
        <path d="M90 66.5v11M84.5 72h11" class="ea-mark" />
      </template>

      <template v-else-if="name === 'tax'">
        <path d="M30 46l20-20h34a5 5 0 0 1 5 5v30a5 5 0 0 1-5 5H50z" class="ea-paper" />
        <circle cx="50" cy="46" r="3" class="ea-ink" />
        <circle cx="64" cy="38" r="3.5" class="ea-ink" />
        <circle cx="76" cy="54" r="3.5" class="ea-ink" />
        <path d="M78 36L62 56" class="ea-mark" />
      </template>

      <template v-else-if="name === 'note'">
        <path d="M34 10h52v52L70 78H34z" class="ea-paper" />
        <path d="M70 78V62h16" class="ea-ink" />
        <path d="M44 24h32M44 32h32M44 40h22" class="ea-faint" />
        <path d="M44 52h14" class="ea-ink" />
      </template>

      <template v-else-if="name === 'category'">
        <path d="M24 26a5 5 0 0 1 5-5h18l6 7h33a5 5 0 0 1 5 5v33a5 5 0 0 1-5 5H29a5 5 0 0 1-5-5z" class="ea-paper" />
        <path d="M24 38h67" class="ea-ink" />
        <path d="M36 50h22M36 58h14" class="ea-faint" />
        <circle cx="90" cy="72" r="12" class="ea-badge" />
        <path d="M90 66.5v11M84.5 72h11" class="ea-mark" />
      </template>

      <template v-else-if="name === 'field'">
        <rect x="26" y="14" width="68" height="16" rx="4" class="ea-paper" />
        <rect x="26" y="38" width="68" height="16" rx="4" class="ea-paper-back" />
        <path d="M32 22h18" class="ea-ink" />
        <path d="M32 46h12" class="ea-faint" />
        <rect x="26" y="62" width="40" height="14" rx="4" class="ea-paper" stroke-dasharray="3 4" />
        <circle cx="88" cy="70" r="12" class="ea-badge" />
        <path d="M88 64.5v11M82.5 70h11" class="ea-mark" />
      </template>

      <template v-else-if="name === 'module'">
        <path d="M34 30h14a7 7 0 1 1 14 0h14v14a7 7 0 1 1 0 14v14H62a7 7 0 1 0-14 0H34z" class="ea-paper" />
        <circle cx="88" cy="70" r="12" class="ea-badge" />
        <path d="M88 64.5v11M82.5 70h11" class="ea-mark" />
      </template>

      <template v-else-if="name === 'exchange'">
        <circle cx="46" cy="40" r="17" class="ea-paper" />
        <circle cx="74" cy="54" r="17" class="ea-paper-back" />
        <path d="M40 40h12M46 34v12" class="ea-ink" />
        <path d="M30 70a24 24 0 0 0 20 8M90 24a24 24 0 0 0-20-8" class="ea-mark" />
      </template>

      <template v-else-if="name === 'backup'">
        <ellipse cx="54" cy="24" rx="24" ry="8" class="ea-paper" />
        <path d="M30 24v36c0 4.4 10.7 8 24 8s24-3.6 24-8V24" class="ea-paper" />
        <path d="M30 42c0 4.4 10.7 8 24 8s24-3.6 24-8" class="ea-faint" />
        <circle cx="86" cy="68" r="13" class="ea-badge" />
        <path d="M86 74v-12M81 67l5-5 5 5" class="ea-mark" />
      </template>

      <template v-else-if="name === 'due'">
        <rect x="30" y="14" width="48" height="62" rx="6" class="ea-paper" />
        <path d="M40 28h16" class="ea-ink" />
        <path d="M40 38h28M40 46h20" class="ea-faint" />
        <circle cx="80" cy="64" r="14" class="ea-badge" />
        <path d="M80 57v7l5 3" class="ea-mark" />
      </template>

      <template v-else-if="name === 'activity'">
        <path d="M22 74h80" class="ea-faint" />
        <path d="M22 54h80M22 34h80" class="ea-faint" stroke-dasharray="2 5" />
        <path d="M26 66l16-8 14 5 16-17 18 6" class="ea-ink" />
        <circle cx="42" cy="58" r="3" class="ea-dot" />
        <circle cx="72" cy="46" r="3" class="ea-dot" />
        <circle cx="90" cy="52" r="3" class="ea-dot" />
      </template>

      <template v-else>
        <!-- search: also the fallback -->
        <rect x="30" y="16" width="60" height="52" rx="6" class="ea-paper-back" />
        <path d="M40 30h28M40 38h36M40 46h20" class="ea-faint" />
        <circle cx="66" cy="54" r="15" class="ea-paper" />
        <path d="M58.5 54h15" class="ea-ink" />
        <path d="M77 65l10 10" class="ea-mark" stroke-width="3" />
      </template>
    </svg>
  </div>
</template>

<script setup lang="ts">
export type EmptyArtName =
  | 'invoice' | 'estimate' | 'recurring' | 'payment' | 'expense' | 'customer' | 'item'
  | 'member' | 'tax' | 'note' | 'category' | 'field' | 'module' | 'exchange' | 'backup'
  | 'due' | 'activity' | 'search'

defineProps<{
  name: EmptyArtName | string
}>()
</script>
