<script setup lang="ts">
import { Add01Icon, FavouriteIcon, Logout01Icon, Menu01Icon, Search01Icon, Settings01Icon, UserCircleIcon } from '@hugeicons/core-free-icons'
import { HugeiconsIcon } from '@hugeicons/vue'
import Wordmark from '../brand/Wordmark.vue'
import UiButton from '../ui/button/Button.vue'
import UiInput from '../ui/input/Input.vue'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger
} from '../ui/dropdown-menu'
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '../ui/sheet'
import ThemeControl from './ThemeControl.vue'

const auth = useAuth()
const route = useRoute()
const query = ref(typeof route.query.q === 'string' ? route.query.q : '')
const isAdmin = computed(() => auth.currentUser.value?.roles.includes('ROLE_ADMIN') ?? false)
const profileInitial = computed(() => auth.currentUser.value?.username.slice(0, 1).toUpperCase() ?? '')

watch(() => route.query.q, value => {
  query.value = typeof value === 'string' ? value : ''
})

function search() {
  return navigateTo({ path: '/recipes', query: query.value.trim() ? { q: query.value.trim() } : {} })
}

const profileLinks = computed(() => {
  const username = auth.currentUser.value?.username
  return [
    { icon: UserCircleIcon, label: 'View profile', to: username ? `/users/${username}` : '/settings' },
    { icon: Settings01Icon, label: 'Settings', to: '/settings' },
    { label: 'My recipes', to: '/my-recipes' },
    { icon: Add01Icon, label: 'Create recipe', to: '/recipes/new' },
    { icon: FavouriteIcon, label: 'My favorites', to: '/favorites' }
  ]
})
</script>

<template>
  <header class="sticky top-0 z-40 border-b bg-background/95">
    <div class="container-page flex h-16 items-center gap-3">
      <Sheet>
        <SheetTrigger as-child class="lg:hidden">
          <UiButton variant="ghost" size="icon" aria-label="Open navigation">
            <HugeiconsIcon :icon="Menu01Icon" :size="20" :stroke-width="1.75" aria-hidden="true" />
          </UiButton>
        </SheetTrigger>
        <SheetContent side="left" class="w-[min(22rem,90vw)] p-0">
          <SheetHeader class="border-b p-5 text-left">
            <SheetTitle><Wordmark /></SheetTitle>
            <SheetDescription>Discover and manage cocktail recipes.</SheetDescription>
          </SheetHeader>
          <nav class="grid gap-1 p-3" aria-label="Mobile navigation">
            <SheetClose as-child><NuxtLink class="rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/recipes">Explore recipes</NuxtLink></SheetClose>
            <SheetClose as-child><NuxtLink class="rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/categories">Categories</NuxtLink></SheetClose>
            <template v-if="auth.isAuthenticated.value">
              <div class="my-2 border-t" />
              <SheetClose v-for="item in profileLinks" :key="item.label" as-child>
                <NuxtLink class="rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" :to="item.to">{{ item.label }}</NuxtLink>
              </SheetClose>
              <SheetClose v-if="isAdmin" as-child><NuxtLink class="rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/admin">Administration</NuxtLink></SheetClose>
              <SheetClose as-child><NuxtLink class="rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/logout">Log out</NuxtLink></SheetClose>
            </template>
            <template v-else>
              <div class="my-2 border-t" />
              <SheetClose as-child><NuxtLink class="rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/login">Log in</NuxtLink></SheetClose>
              <SheetClose as-child><NuxtLink class="rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/register">Register</NuxtLink></SheetClose>
            </template>
          </nav>
        </SheetContent>
      </Sheet>

      <NuxtLink to="/" aria-label="WhatsInMyBar home" class="shrink-0"><Wordmark /></NuxtLink>
      <nav class="hidden items-center gap-1 lg:flex" aria-label="Primary navigation">
        <UiButton as-child variant="ghost" size="sm"><NuxtLink to="/recipes">Recipes</NuxtLink></UiButton>
        <UiButton as-child variant="ghost" size="sm"><NuxtLink to="/categories">Categories</NuxtLink></UiButton>
      </nav>

      <form class="ml-auto hidden w-full max-w-sm md:block" role="search" @submit.prevent="search">
        <label class="relative block">
          <span class="sr-only">Search recipes</span>
          <HugeiconsIcon :icon="Search01Icon" :size="16" :stroke-width="1.75" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <UiInput v-model="query" class="pl-9" type="search" placeholder="Search recipes" />
        </label>
      </form>

      <UiButton as-child variant="ghost" size="icon" class="ml-auto md:ml-0 md:hidden" aria-label="Search recipes">
        <NuxtLink to="/recipes"><HugeiconsIcon :icon="Search01Icon" :size="20" :stroke-width="1.75" aria-hidden="true" /></NuxtLink>
      </UiButton>
      <ThemeControl />

      <DropdownMenu v-if="auth.isAuthenticated.value">
        <DropdownMenuTrigger as-child>
          <UiButton variant="outline" size="icon" :aria-label="`Open profile menu for ${auth.currentUser.value?.username}`"><span class="text-xs font-semibold">{{ profileInitial }}</span></UiButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
          <DropdownMenuLabel>{{ auth.currentUser.value?.username }}</DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuItem v-for="item in profileLinks" :key="item.label" as-child>
            <NuxtLink :to="item.to">
              <HugeiconsIcon v-if="item.icon" :icon="item.icon" :size="16" :stroke-width="1.75" aria-hidden="true" />
              {{ item.label }}
            </NuxtLink>
          </DropdownMenuItem>
          <DropdownMenuSeparator v-if="isAdmin" />
          <DropdownMenuItem v-if="isAdmin" as-child><NuxtLink to="/admin">Administration</NuxtLink></DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem as-child>
            <NuxtLink to="/logout"><HugeiconsIcon :icon="Logout01Icon" :size="16" :stroke-width="1.75" aria-hidden="true" /> Log out</NuxtLink>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>

      <div v-else class="hidden items-center gap-2 sm:flex">
        <UiButton as-child variant="ghost" size="sm"><NuxtLink to="/login">Log in</NuxtLink></UiButton>
        <UiButton as-child size="sm"><NuxtLink to="/register">Register</NuxtLink></UiButton>
      </div>
    </div>
  </header>
</template>
