<script setup lang="ts">
import UiButton from './components/ui/button/Button.vue'

const auth = useAuth()
const isAdmin = computed(() => auth.currentUser.value?.roles.includes('ROLE_ADMIN') ?? false)
</script>

<template>
  <div class="min-h-screen">
    <NuxtRouteAnnouncer />

    <header class="border-b border-border bg-background/92 backdrop-blur-lg">
      <nav class="mx-auto flex min-h-18 w-[min(1120px,calc(100%_-_2rem))] items-center justify-between gap-4 md:w-[min(1120px,calc(100%_-_3rem))]" aria-label="Main navigation">
        <NuxtLink class="wordmark text-lg font-black text-foreground md:text-xl" to="/" aria-label="What's In My Bar home">
          What's In My Bar
        </NuxtLink>

        <div class="flex items-center gap-2">
          <div class="hidden items-center gap-2 sm:flex">
            <NuxtLink class="min-h-11 px-1 py-3 font-bold text-muted-foreground hover:text-foreground" to="/recipes">
              Recipes
            </NuxtLink>
            <NuxtLink class="min-h-11 px-1 py-3 font-bold text-muted-foreground hover:text-foreground" to="/categories">
              Categories
            </NuxtLink>
          </div>
          <NuxtLink v-if="auth.isAuthenticated.value" class="min-h-11 px-1 py-3 font-bold text-muted-foreground hover:text-foreground" to="/account">
            Account
          </NuxtLink>
          <NuxtLink v-if="isAdmin" class="min-h-11 px-1 py-3 font-bold text-muted-foreground hover:text-foreground" to="/admin">
            Admin
          </NuxtLink>
          <UiButton v-if="auth.isAuthenticated.value" as-child variant="outline" size="sm">
            <NuxtLink to="/logout">
              Log out
            </NuxtLink>
          </UiButton>
          <NuxtLink v-if="!auth.isAuthenticated.value" class="min-h-11 px-1 py-3 font-bold text-muted-foreground hover:text-foreground" to="/login">
            Log in
          </NuxtLink>
          <UiButton v-if="!auth.isAuthenticated.value" as-child size="sm">
            <NuxtLink to="/register">
              Join
            </NuxtLink>
          </UiButton>
        </div>
      </nav>
    </header>

    <NuxtPage />
  </div>
</template>
