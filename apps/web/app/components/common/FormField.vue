<script setup lang="ts">
defineProps<{
  error?: string
  help?: string
  id: string
  label: string
  optional?: boolean
}>()
</script>

<template>
  <div class="form-field">
    <label class="field-label" :for="id">
      {{ label }}
      <span v-if="optional">optional</span>
    </label>
    <slot
      :aria-describedby="[
        help ? `${id}-help` : null,
        error ? `${id}-error` : null
      ].filter(Boolean).join(' ') || undefined"
      :aria-invalid="Boolean(error)"
    />
    <p v-if="help" :id="`${id}-help`" class="field-help">
      {{ help }}
    </p>
    <p v-if="error" :id="`${id}-error`" class="field-error">
      {{ error }}
    </p>
  </div>
</template>
