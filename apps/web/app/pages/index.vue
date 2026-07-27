<script setup lang="ts">
useSeoMeta({
  title: "What's In My Bar",
  description: 'Discover, create, and share cocktail recipes with a community of home bartenders.'
})
</script>

<template>
  <main class="page-shell">
    <section class="auth-layout" aria-labelledby="home-title">
      <div class="auth-intro">
        <p class="eyebrow">
          Cocktail community
        </p>
        <h1 id="home-title" class="page-title">
          What's In My Bar
        </h1>
        <p class="page-copy">
          Build your profile, save your favorite recipes, and start shaping a personal cocktail notebook.
        </p>
      </div>

      <div class="auth-panel">
        <div class="panel-header">
          <h2 class="panel-title">
            Start mixing
          </h2>
          <p class="panel-copy">
            Create an account or log back in to manage your bar profile.
          </p>
        </div>

        <div class="form-stack">
          <NuxtLink class="button button-primary button-full" to="/register">
            Create an account
          </NuxtLink>
          <NuxtLink class="button button-secondary button-full" to="/login">
            Log in
          </NuxtLink>
        </div>
      </div>
    </section>
  </main>
</template>
