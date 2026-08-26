<script setup lang="ts">
import {
  Add01Icon,
  BookOpen01Icon,
  DashboardSquare01Icon,
  FavouriteIcon,
  Logout01Icon,
  Menu01Icon,
  PaintBrush01Icon,
  Search01Icon,
  Settings01Icon,
  UserCircleIcon
} from '@hugeicons/core-free-icons'
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
const mobileOpen = ref(false)
const isAdmin = computed(() => auth.currentUser.value?.roles.includes('ROLE_ADMIN') ?? false)
const profileInitial = computed(() => auth.currentUser.value?.username.slice(0, 1).toUpperCase() ?? '')
const profilePath = computed(() => auth.currentUser.value ? `/users/${auth.currentUser.value.username}` : '/settings')

watch(() => route.query.q, value => {
  query.value = typeof value === 'string' ? value : ''
})

async function search() {
  mobileOpen.value = false
  await navigateTo({ path: '/recipes', query: query.value.trim() ? { q: query.value.trim() } : {} })
}

const profileLinks = computed(() => [
  { icon: UserCircleIcon, label: 'View profile', to: profilePath.value },
  { icon: Settings01Icon, label: 'Settings', to: '/settings' },
  { icon: BookOpen01Icon, label: 'My recipes', to: `${profilePath.value}#my-recipes` },
  { icon: Add01Icon, label: 'Create recipe', to: '/recipes/new' },
  { icon: FavouriteIcon, label: 'My favorites', to: `${profilePath.value}#favorites` },
  { icon: PaintBrush01Icon, label: 'Appearance', to: '/settings#appearance' }
])
</script>

<template>
  <header class="sticky top-0 z-40 border-b bg-background/95">
    <div class="container-page flex h-16 items-center justify-between gap-3 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(20rem,28rem)_minmax(0,1fr)]">
      <div class="flex min-w-0 items-center gap-3">
        <Sheet v-model:open="mobileOpen">
          <SheetTrigger as-child class="lg:hidden">
            <UiButton variant="ghost" size="icon" aria-label="Open navigation">
              <HugeiconsIcon :icon="Menu01Icon" :size="20" :stroke-width="1.75" aria-hidden="true" />
            </UiButton>
          </SheetTrigger>
          <SheetContent side="left" class="w-[min(23rem,92vw)] overflow-y-auto p-0">
            <SheetHeader class="border-b p-5 text-left">
              <SheetTitle><Wordmark /></SheetTitle>
              <SheetDescription>Discover and manage cocktail recipes.</SheetDescription>
            </SheetHeader>
            <div class="grid gap-5 p-4">
              <form role="search" class="grid gap-2" @submit.prevent="search">
                <label class="field-label" for="mobile-recipe-search">Search recipes</label>
                <div class="flex gap-2">
                  <div class="relative min-w-0 flex-1">
                    <HugeiconsIcon :icon="Search01Icon" :size="16" :stroke-width="1.75" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                    <UiInput id="mobile-recipe-search" v-model="query" class="pl-9" type="search" placeholder="Recipe name or ingredient" />
                  </div>
                  <UiButton type="submit" size="icon" aria-label="Search"><HugeiconsIcon :icon="Search01Icon" :size="18" :stroke-width="1.75" aria-hidden="true" /></UiButton>
                </div>
              </form>

              <nav class="grid gap-1" aria-label="Mobile navigation">
                <SheetClose as-child><NuxtLink class="rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/recipes">Explore recipes</NuxtLink></SheetClose>
                <SheetClose as-child><NuxtLink class="rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/categories">Categories</NuxtLink></SheetClose>
                <template v-if="auth.isAuthenticated.value">
                  <div class="my-2 border-t" />
                  <SheetClose as-child><NuxtLink class="flex min-h-11 items-center gap-2 rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/recipes/new"><HugeiconsIcon :icon="Add01Icon" :size="18" :stroke-width="1.75" aria-hidden="true" />Create recipe</NuxtLink></SheetClose>
                  <SheetClose v-for="item in profileLinks.filter(item => item.label !== 'Create recipe' && item.label !== 'Appearance')" :key="item.label" as-child>
                    <NuxtLink class="flex min-h-11 items-center gap-2 rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" :to="item.to"><HugeiconsIcon :icon="item.icon" :size="18" :stroke-width="1.75" aria-hidden="true" />{{ item.label }}</NuxtLink>
                  </SheetClose>
                  <SheetClose v-if="isAdmin" as-child><NuxtLink class="flex min-h-11 items-center gap-2 rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/admin"><HugeiconsIcon :icon="DashboardSquare01Icon" :size="18" :stroke-width="1.75" aria-hidden="true" />Administration</NuxtLink></SheetClose>
                  <SheetClose as-child><NuxtLink class="flex min-h-11 items-center gap-2 rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/logout"><HugeiconsIcon :icon="Logout01Icon" :size="18" :stroke-width="1.75" aria-hidden="true" />Log out</NuxtLink></SheetClose>
                </template>
                <template v-else>
                  <div class="my-2 border-t" />
                  <SheetClose as-child><NuxtLink class="rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/login">Log in</NuxtLink></SheetClose>
                  <SheetClose as-child><NuxtLink class="rounded-md px-3 py-3 text-sm font-medium hover:bg-accent" to="/register">Register</NuxtLink></SheetClose>
                </template>
              </nav>

              <div class="border-t pt-5"><ThemeControl inline /></div>
            </div>
          </SheetContent>
        </Sheet>

        <NuxtLink to="/" aria-label="WhatsInMyBar home" class="shrink-0"><Wordmark /></NuxtLink>
        <nav class="hidden items-center gap-1 lg:flex" aria-label="Primary navigation">
          <UiButton as-child variant="ghost" size="sm"><NuxtLink to="/recipes">Recipes</NuxtLink></UiButton>
          <UiButton as-child variant="ghost" size="sm"><NuxtLink to="/categories">Categories</NuxtLink></UiButton>
        </nav>
      </div>

      <form class="hidden w-full lg:block" role="search" @submit.prevent="search">
        <label class="relative block">
          <span class="sr-only">Search recipes</span>
          <HugeiconsIcon :icon="Search01Icon" :size="16" :stroke-width="1.75" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <UiInput v-model="query" class="pl-9" type="search" placeholder="Search recipes" />
        </label>
      </form>

      <div class="hidden min-w-0 items-center justify-end gap-2 lg:flex">
        <template v-if="auth.isAuthenticated.value">
          <UiButton as-child size="sm"><NuxtLink to="/recipes/new"><HugeiconsIcon :icon="Add01Icon" :size="16" :stroke-width="1.75" aria-hidden="true" />Create recipe</NuxtLink></UiButton>
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <UiButton variant="outline" size="icon" :aria-label="`Open profile menu for ${auth.currentUser.value?.username}`"><span class="text-xs font-semibold">{{ profileInitial }}</span></UiButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
              <DropdownMenuLabel>{{ auth.currentUser.value?.username }}</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem v-for="item in profileLinks" :key="item.label" as-child>
                <NuxtLink :to="item.to"><HugeiconsIcon :icon="item.icon" :size="16" :stroke-width="1.75" aria-hidden="true" />{{ item.label }}</NuxtLink>
              </DropdownMenuItem>
              <DropdownMenuSeparator v-if="isAdmin" />
              <DropdownMenuItem v-if="isAdmin" as-child><NuxtLink to="/admin"><HugeiconsIcon :icon="DashboardSquare01Icon" :size="16" :stroke-width="1.75" aria-hidden="true" />Administration</NuxtLink></DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem as-child><NuxtLink to="/logout"><HugeiconsIcon :icon="Logout01Icon" :size="16" :stroke-width="1.75" aria-hidden="true" />Log out</NuxtLink></DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </template>
        <template v-else>
          <UiButton as-child variant="ghost" size="sm"><NuxtLink to="/login">Log in</NuxtLink></UiButton>
          <UiButton as-child size="sm"><NuxtLink to="/register">Register</NuxtLink></UiButton>
        </template>
      </div>
    </div>
  </header>
</template>
