<script setup lang="ts">
import { Flag03Icon } from '@hugeicons/core-free-icons'
import { HugeiconsIcon } from '@hugeicons/vue'
import type { ApiId, ReportTargetType } from '../../types/api'
import UiButton from '../ui/button/Button.vue'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '../ui/dialog'
import ReportForm from './ReportForm.vue'

const props = withDefaults(defineProps<{
  loginRedirect?: string
  targetId?: ApiId
  targetType: ReportTargetType
}>(), {
  loginRedirect: '/',
  targetId: undefined
})

const auth = useAuth()
const notifications = useNotifications()
const open = ref(false)
const canReport = computed(() => auth.isAuthenticated.value && Boolean(props.targetId))
const loginTo = computed(() => `/login?redirect=${encodeURIComponent(props.loginRedirect)}`)
const targetLabel = computed(() => props.targetType === 'user' ? 'profile' : props.targetType)
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

function submitted() {
  open.value = false
  notifications.success(`report:${props.targetType}:${props.targetId}`, 'Report submitted.')
}
</script>

<template>
  <div class="inline-flex items-center gap-2">
    <Dialog v-if="canReport" v-model:open="open">
      <span ref="triggerContainer" class="contents">
        <UiButton type="button" size="icon" variant="ghost" aria-haspopup="dialog" :aria-expanded="open" :aria-label="`Report this ${targetLabel}`" :title="`Report this ${targetLabel}`" @click.prevent.stop="requestOpen"><HugeiconsIcon :icon="Flag03Icon" :size="18" :stroke-width="1.75" aria-hidden="true" /></UiButton>
      </span>
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Report this {{ targetLabel }}</DialogTitle>
          <DialogDescription>Tell us what needs attention. Your report will be reviewed.</DialogDescription>
        </DialogHeader>
        <ReportForm
          v-if="targetId"
          :target-id="targetId"
          :target-type="targetType"
          @cancel="open = false"
          @submitted="submitted"
        />
      </DialogContent>
    </Dialog>

    <UiButton v-else-if="auth.isAuthenticated.value" type="button" size="icon" variant="ghost" disabled :aria-label="`Reporting this ${targetLabel} is unavailable`" title="Reporting unavailable">
      <HugeiconsIcon :icon="Flag03Icon" :size="18" :stroke-width="1.75" aria-hidden="true" />
    </UiButton>

    <UiButton v-else as-child size="icon" variant="ghost" :aria-label="`Log in to report this ${targetLabel}`" :title="`Log in to report this ${targetLabel}`">
      <NuxtLink :to="loginTo"><HugeiconsIcon :icon="Flag03Icon" :size="18" :stroke-width="1.75" aria-hidden="true" /></NuxtLink>
    </UiButton>
  </div>
</template>
