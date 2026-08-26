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
const icon = computed(() => theme.resolvedTheme.value === 'dark' ? Moon02Icon : Sun03Icon)

withDefaults(defineProps<{
  inline?: boolean
}>(), {
  inline: false
})

const choices = [
  { icon: Sun03Icon, label: 'Light', value: 'light' },
  { icon: Moon02Icon, label: 'Dark', value: 'dark' },
  { icon: ComputerIcon, label: 'System', value: 'system' }
] as const
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
        @click="theme.setTheme(choice.value)"
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
      <DropdownMenuRadioGroup :model-value="theme.preference.value" @update:model-value="theme.setTheme($event as 'light' | 'dark' | 'system')">
        <DropdownMenuRadioItem value="light"><HugeiconsIcon :icon="Sun03Icon" :size="16" :stroke-width="1.75" /> Light</DropdownMenuRadioItem>
        <DropdownMenuRadioItem value="dark"><HugeiconsIcon :icon="Moon02Icon" :size="16" :stroke-width="1.75" /> Dark</DropdownMenuRadioItem>
        <DropdownMenuRadioItem value="system"><HugeiconsIcon :icon="ComputerIcon" :size="16" :stroke-width="1.75" /> System</DropdownMenuRadioItem>
      </DropdownMenuRadioGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
