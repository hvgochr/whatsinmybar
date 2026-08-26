<script setup lang="ts">
import FormAlert from './FormAlert.vue'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogTitle
} from '../ui/alert-dialog'

withDefaults(defineProps<{
  confirmLabel: string
  description: string
  error?: string | null
  pending?: boolean
  title: string
}>(), {
  error: null,
  pending: false
})

const emit = defineEmits<{ confirm: [] }>()
const open = defineModel<boolean>('open', { default: false })
const triggerContainer = ref<HTMLElement | null>(null)
let wasOpen = false

watch(open, async value => {
  if (wasOpen && !value) {
    await nextTick()
    triggerContainer.value?.querySelector<HTMLElement>('button, a[href]')?.focus()
  }
  wasOpen = value
})

function requestOpen() {
  window.setTimeout(() => { open.value = true }, 0)
}
</script>

<template>
  <AlertDialog v-model:open="open">
    <span v-if="$slots.trigger" ref="triggerContainer" class="contents" @click.prevent="requestOpen"><slot name="trigger" /></span>
    <AlertDialogContent>
      <div class="grid gap-2">
        <AlertDialogTitle>{{ title }}</AlertDialogTitle>
        <AlertDialogDescription>{{ description }}</AlertDialogDescription>
      </div>
      <FormAlert v-if="error" :message="error" tone="error" />
      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <AlertDialogCancel :disabled="pending">Cancel</AlertDialogCancel>
        <AlertDialogAction :disabled="pending" @click.prevent="emit('confirm')">
          {{ pending ? 'Deleting...' : confirmLabel }}
        </AlertDialogAction>
      </div>
    </AlertDialogContent>
  </AlertDialog>
</template>
