<script setup lang="ts">
const auth = useAuth()
</script>

<template>
  <div class="app-shell">
    <NuxtRouteAnnouncer />

    <header class="site-header">
      <nav class="site-nav" aria-label="Main navigation">
        <NuxtLink class="wordmark" to="/" aria-label="What's In My Bar home">
          What's In My Bar
        </NuxtLink>

        <div class="nav-actions">
          <NuxtLink v-if="auth.isAuthenticated.value" class="nav-link" to="/account">
            Account
          </NuxtLink>
          <NuxtLink v-if="auth.isAuthenticated.value" class="button button-secondary button-small" to="/logout">
            Log out
          </NuxtLink>
          <NuxtLink v-if="!auth.isAuthenticated.value" class="nav-link" to="/login">
            Log in
          </NuxtLink>
          <NuxtLink v-if="!auth.isAuthenticated.value" class="button button-primary button-small" to="/register">
            Join
          </NuxtLink>
        </div>
      </nav>
    </header>

    <NuxtPage />
  </div>
</template>
