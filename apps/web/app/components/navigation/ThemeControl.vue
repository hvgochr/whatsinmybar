<script setup lang="ts">
import { ComputerIcon, Moon02Icon, Sun03Icon } from '@hugeicons/core-free-icons'
import { HugeiconsIcon } from '@hugeicons/vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuTrigger
} from '../ui/dropdown-menu'
import UiButton from '../ui/button/Button.vue'

const theme = useTheme()
const notifications = useNotifications()
const icon = computed(() => theme.resolvedTheme.value === 'dark' ? Moon02Icon : Sun03Icon)

const props = withDefaults(defineProps<{
  inline?: boolean
  notify?: boolean
}>(), {
  inline: false,
  notify: false
})

const choices = [
  { icon: Sun03Icon, label: 'Light', value: 'light' },
  { icon: Moon02Icon, label: 'Dark', value: 'dark' },
  { icon: ComputerIcon, label: 'System', value: 'system' }
] as const

function selectTheme(value: 'light' | 'dark' | 'system') {
  theme.setTheme(value)
  if (props.notify) notifications.success('appearance-updated', 'Appearance updated.')
}
</script>

<template>
  <fieldset v-if="inline" class="grid gap-2">
    <legend class="mb-1 text-sm font-medium">Appearance</legend>
    <div class="grid grid-cols-3 gap-2">
      <UiButton
        v-for="choice in choices"
        :key="choice.value"
        type="button"
        size="sm"
        :variant="theme.preference.value === choice.value ? 'default' : 'outline'"
        :aria-pressed="theme.preference.value === choice.value"
        @click="selectTheme(choice.value)"
      >
        <HugeiconsIcon :icon="choice.icon" :size="16" :stroke-width="1.75" aria-hidden="true" />
        {{ choice.label }}
      </UiButton>
    </div>
  </fieldset>

  <DropdownMenu v-else>
    <DropdownMenuTrigger as-child>
      <UiButton variant="ghost" size="icon" aria-label="Choose color theme">
        <HugeiconsIcon :icon="icon" :size="18" :stroke-width="1.75" aria-hidden="true" />
      </UiButton>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-44">
      <DropdownMenuLabel>Theme</DropdownMenuLabel>
      <DropdownMenuRadioGroup :model-value="theme.preference.value" @update:model-value="selectTheme($event as 'light' | 'dark' | 'system')">
        <DropdownMenuRadioItem value="light"><HugeiconsIcon :icon="Sun03Icon" :size="16" :stroke-width="1.75" /> Light</DropdownMenuRadioItem>
        <DropdownMenuRadioItem value="dark"><HugeiconsIcon :icon="Moon02Icon" :size="16" :stroke-width="1.75" /> Dark</DropdownMenuRadioItem>
        <DropdownMenuRadioItem value="system"><HugeiconsIcon :icon="ComputerIcon" :size="16" :stroke-width="1.75" /> System</DropdownMenuRadioItem>
      </DropdownMenuRadioGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
