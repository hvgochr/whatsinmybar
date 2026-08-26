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
</script>

<template>
  <DropdownMenu>
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
