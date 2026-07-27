<template>
  <div class="app-shell">
    <NuxtRouteAnnouncer />

    <header class="site-header">
      <nav class="site-nav" aria-label="Main navigation">
        <NuxtLink class="wordmark" to="/" aria-label="What's In My Bar home">
          What's In My Bar
        </NuxtLink>

        <div class="nav-actions">
          <NuxtLink class="nav-link" to="/login">
            Log in
          </NuxtLink>
          <NuxtLink class="button button-primary button-small" to="/register">
            Join
          </NuxtLink>
        </div>
      </nav>
    </header>

    <NuxtPage />
  </div>
</template>
