<script setup lang="ts">
import UiLabel from '../ui/label/Label.vue'

defineProps<{
  error?: string
  help?: string
  id: string
  label: string
  optional?: boolean
}>()
</script>

<template>
  <div class="grid gap-2">
    <UiLabel :for="id">
      {{ label }}
      <span v-if="optional" class="font-semibold text-muted-foreground">optional</span>
    </UiLabel>
    <slot
      :aria-describedby="[
        help ? `${id}-help` : null,
        error ? `${id}-error` : null
      ].filter(Boolean).join(' ') || undefined"
      :aria-invalid="Boolean(error)"
    />
    <p v-if="help" :id="`${id}-help`" class="text-sm text-muted-foreground">
      {{ help }}
    </p>
    <p v-if="error" :id="`${id}-error`" class="text-sm font-bold text-destructive">
      {{ error }}
    </p>
  </div>
</template>
