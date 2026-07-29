<script setup lang="ts">
import type { ApiId, ReportTargetType } from '../../types/api'
import FormAlert from '../common/FormAlert.vue'
import UiButton from '../ui/button/Button.vue'
import ReportForm from './ReportForm.vue'

const props = withDefaults(defineProps<{
  compact?: boolean
  loginRedirect?: string
  targetId?: ApiId
  targetType: ReportTargetType
}>(), {
  compact: false,
  loginRedirect: '/',
  targetId: undefined
})

const auth = useAuth()
const open = ref(false)
const successMessage = ref<string | null>(null)

const canReport = computed(() => auth.isAuthenticated.value && Boolean(props.targetId))
const loginTo = computed(() => `/login?redirect=${encodeURIComponent(props.loginRedirect)}`)

onMounted(async () => {
  if (auth.currentUser.value) {
    return
  }

  try {
    await auth.restoreSession()
  } catch {
    // Public pages stay readable when session restoration fails.
  }
})
</script>

<template>
  <div class="grid gap-3">
    <FormAlert v-if="successMessage" :message="successMessage" tone="success" />

    <UiButton v-if="canReport" type="button" :size="compact ? 'sm' : 'default'" variant="outline" @click="open = !open">
      {{ open ? 'Cancel report' : 'Report' }}
    </UiButton>

    <UiButton v-else-if="auth.isAuthenticated.value" type="button" :size="compact ? 'sm' : 'default'" variant="outline" disabled>
      Report unavailable
    </UiButton>

    <UiButton v-else as-child :size="compact ? 'sm' : 'default'" variant="outline">
      <NuxtLink :to="loginTo">
        Log in to report
      </NuxtLink>
    </UiButton>

    <ReportForm
      v-if="open && targetId"
      :target-id="targetId"
      :target-type="targetType"
      @cancel="open = false"
      @submitted="() => {
        open = false
        successMessage = 'Report submitted for moderation.'
      }"
    />
  </div>
</template>
