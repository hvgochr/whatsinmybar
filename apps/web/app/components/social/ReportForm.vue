<script setup lang="ts">
import { ApiRequestError } from '../../services/api-client'
import type { ApiId, Report, ReportReason, ReportTargetType } from '../../types/api'
import { reportReasonOptions } from '../../utils/social'
import FormAlert from '../common/FormAlert.vue'
import UiButton from '../ui/button/Button.vue'
import UiTextarea from '../ui/textarea/Textarea.vue'

const props = defineProps<{
  targetId: ApiId
  targetType: ReportTargetType
}>()

const emit = defineEmits<{
  cancel: []
  submitted: [report: Report]
}>()

const api = useApi()
const reason = ref<ReportReason>('spam')
const message = ref('')
const pending = ref(false)
const errorMessage = ref<string | null>(null)

const selectClass = 'min-h-12 rounded-lg border border-input bg-background px-3.5 py-3 text-foreground shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background'

async function submitReport() {
  pending.value = true
  errorMessage.value = null

  try {
    const report = await api.reports.create({
      message: message.value.trim() || null,
      reason: reason.value,
      targetId: props.targetId,
      targetType: props.targetType
    })

    message.value = ''
    reason.value = 'spam'
    emit('submitted', report)
  } catch (error: unknown) {
    errorMessage.value = error instanceof ApiRequestError
      ? error.message
      : 'Report could not be submitted.'
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <form class="grid gap-3 rounded-lg border border-border bg-background p-4" @submit.prevent="submitReport">
    <FormAlert v-if="errorMessage" :message="errorMessage" tone="error" />

    <label class="grid gap-2">
      <span class="text-sm font-black">Reason</span>
      <select v-model="reason" :class="selectClass">
        <option v-for="option in reportReasonOptions" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>
    </label>

    <label class="grid gap-2">
      <span class="text-sm font-black">Details <span class="font-semibold text-muted-foreground">optional</span></span>
      <UiTextarea v-model="message" rows="3" placeholder="Add context for moderation" />
    </label>

    <div class="flex flex-wrap justify-end gap-2">
      <UiButton type="button" variant="outline" :disabled="pending" @click="emit('cancel')">
        Cancel
      </UiButton>
      <UiButton type="submit" :disabled="pending">
        {{ pending ? 'Reporting...' : 'Submit report' }}
      </UiButton>
    </div>
  </form>
</template>
