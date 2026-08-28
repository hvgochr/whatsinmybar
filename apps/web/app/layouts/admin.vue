<script setup lang="ts">
import {
  Alert01Icon,
  ArrowLeft01Icon,
  BookOpen01Icon,
  Comment01Icon,
  DashboardSquare01Icon,
  Menu01Icon,
  MilkBottleIcon,
  SidebarLeft01Icon,
  Tag01Icon,
  UserGroupIcon
} from '@hugeicons/core-free-icons'
import { HugeiconsIcon } from '@hugeicons/vue'
import Wordmark from '../components/brand/Wordmark.vue'
import ThemeControl from '../components/navigation/ThemeControl.vue'
import UiButton from '../components/ui/button/Button.vue'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '../components/ui/sheet'

const route = useRoute()
const collapsed = useState('admin.sidebar.collapsed', () => false)
const navItems = [
  { icon: DashboardSquare01Icon, label: 'Dashboard', to: '/admin' },
  { icon: UserGroupIcon, label: 'Users', to: '/admin/users' },
  { icon: BookOpen01Icon, label: 'Recipes', to: '/admin/recipes' },
  { icon: MilkBottleIcon, label: 'Ingredients', to: '/admin/ingredients' },
  { icon: Tag01Icon, label: 'Categories', to: '/admin/categories' },
  { icon: Comment01Icon, label: 'Comments', to: '/admin/comments' },
  { icon: Alert01Icon, label: 'Reports', to: '/admin/reports' }
]
const currentItem = computed(() => [...navItems].reverse().find(item => route.path === item.to || (item.to !== '/admin' && route.path.startsWith(`${item.to}/`))) ?? navItems[0]!)

function active(to: string): boolean {
  return route.path === to || (to !== '/admin' && route.path.startsWith(`${to}/`))
}
</script>

<template>
  <div class="min-h-screen bg-muted/35">
    <aside class="fixed inset-y-0 left-0 z-40 hidden border-r bg-background lg:flex lg:flex-col" :class="collapsed ? 'w-16' : 'w-64'">
      <div class="flex h-14 items-center border-b px-4" :class="collapsed ? 'justify-center' : 'justify-between'">
        <NuxtLink v-if="!collapsed" to="/admin" aria-label="Administration home"><Wordmark /></NuxtLink>
        <NuxtLink v-else to="/admin" class="text-lg font-semibold" aria-label="Administration home">W</NuxtLink>
      </div>
      <nav class="flex-1 space-y-1 p-2" aria-label="Administration">
        <NuxtLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex min-h-10 items-center gap-3 rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground"
          :class="active(item.to) ? 'bg-accent text-foreground' : ''"
          :aria-label="collapsed ? item.label : undefined"
          :title="collapsed ? item.label : undefined"
        >
          <HugeiconsIcon :icon="item.icon" :size="18" :stroke-width="1.75" class="shrink-0" aria-hidden="true" />
          <span v-if="!collapsed">{{ item.label }}</span>
        </NuxtLink>
      </nav>
      <div class="border-t p-2">
        <NuxtLink to="/" class="flex min-h-10 items-center gap-3 rounded-md px-3 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground">
          <HugeiconsIcon :icon="ArrowLeft01Icon" :size="18" :stroke-width="1.75" aria-hidden="true" />
          <span v-if="!collapsed">Public website</span>
        </NuxtLink>
      </div>
    </aside>

    <div :class="collapsed ? 'lg:pl-16' : 'lg:pl-64'">
      <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b bg-background px-4 sm:px-6">
        <Sheet>
          <SheetTrigger as-child class="lg:hidden">
            <UiButton variant="ghost" size="icon" aria-label="Open administration navigation">
              <HugeiconsIcon :icon="Menu01Icon" :size="20" :stroke-width="1.75" />
            </UiButton>
          </SheetTrigger>
          <SheetContent side="left" class="w-[min(19rem,90vw)] p-0">
            <SheetHeader class="border-b p-5 text-left">
              <SheetTitle><Wordmark /></SheetTitle>
              <SheetDescription>Administration</SheetDescription>
            </SheetHeader>
            <nav class="space-y-1 p-3" aria-label="Mobile administration">
              <NuxtLink v-for="item in navItems" :key="item.to" :to="item.to" class="flex min-h-11 items-center gap-3 rounded-md px-3 text-sm font-medium hover:bg-accent" :class="active(item.to) ? 'bg-accent' : ''">
                <HugeiconsIcon :icon="item.icon" :size="18" :stroke-width="1.75" aria-hidden="true" />{{ item.label }}
              </NuxtLink>
            </nav>
          </SheetContent>
        </Sheet>
        <UiButton variant="ghost" size="icon" class="hidden lg:inline-flex" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" @click="collapsed = !collapsed">
          <HugeiconsIcon :icon="SidebarLeft01Icon" :size="18" :stroke-width="1.75" />
        </UiButton>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium">Administration</p>
          <p class="truncate text-xs text-muted-foreground">{{ currentItem.label }}</p>
        </div>
        <ThemeControl />
        <UiButton as-child variant="ghost" size="sm"><NuxtLink to="/logout">Log out</NuxtLink></UiButton>
      </header>
      <main id="main-content" class="p-4 sm:p-6 lg:p-8"><slot /></main>
    </div>
  </div>
</template>
