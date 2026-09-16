<script setup lang="ts">
import PublicPageHeader from '../components/common/PublicPageHeader.vue'
import { publicUrl } from '../utils/public-content'

const runtimeConfig = useRuntimeConfig()
const canonicalUrl = publicUrl(runtimeConfig.public.siteUrl, '/about')

useSeoMeta({
  title: "About the project | What's In My Bar",
  description: "A concise look at the product thinking, engineering boundaries, and quality goals behind What's In My Bar.",
  ogDescription: "A concise look at the product thinking, engineering boundaries, and quality goals behind What's In My Bar.",
  ogTitle: "About the project | What's In My Bar",
  ogType: 'website',
  ogUrl: canonicalUrl
})

useHead({ link: [{ href: canonicalUrl, rel: 'canonical' }] })

const foundations = [
  {
    title: 'Useful without an account',
    description: 'Public discovery, categories, profiles, and zero-proof recipes are server rendered and designed to work before sign-up.'
  },
  {
    title: 'Boundaries that hold',
    description: 'The API remains responsible for permissions, moderation state, and alcohol visibility across lists, direct links, and images.'
  },
  {
    title: 'Complete authoring flows',
    description: 'Measured ingredients, ordered steps, drafts, publication, images, comments, favorites, reports, and administration form one coherent workflow.'
  }
]
</script>

<template>
  <main class="page-main">
    <PublicPageHeader
      action-label="Explore recipes"
      action-to="/recipes"
      description="A social cocktail recipe application built as a production-minded full-stack portfolio project."
      eyebrow="Project"
      title="Thoughtful discovery, reliable boundaries."
    />

    <section class="grid gap-4 md:grid-cols-3" aria-label="Project foundations">
      <article v-for="foundation in foundations" :key="foundation.title" class="rounded-md border bg-card p-5 sm:p-6">
        <h2 class="text-lg font-semibold">{{ foundation.title }}</h2>
        <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ foundation.description }}</p>
      </article>
    </section>

    <section class="mt-14 grid gap-8 border-t pt-10 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.7fr)] lg:items-start" aria-labelledby="engineering-title">
      <div>
        <h2 id="engineering-title" class="section-heading">Engineering approach</h2>
        <p class="mt-4 max-w-3xl leading-7 text-muted-foreground">
          The application pairs a Nuxt and Vue interface with a Symfony and API Platform backend. PostgreSQL stores the domain model, Docker Compose keeps development reproducible, and automated checks cover contracts, permissions, forms, SSR, and browser journeys.
        </p>
        <p class="mt-4 max-w-3xl leading-7 text-muted-foreground">
          The monochrome interface keeps attention on recipe photography and clear content hierarchy. Error, empty, loading, restricted, and degraded-session states are treated as part of the product rather than exceptional afterthoughts.
        </p>
      </div>
      <aside class="rounded-md border bg-muted/40 p-5" aria-labelledby="review-title">
        <h2 id="review-title" class="text-base font-semibold">A useful review path</h2>
        <ol class="mt-3 grid gap-3 text-sm leading-6 text-muted-foreground">
          <li><span class="font-medium text-foreground">1.</span> Browse zero-proof recipes while signed out.</li>
          <li><span class="font-medium text-foreground">2.</span> Search by ingredient or category.</li>
          <li><span class="font-medium text-foreground">3.</span> Open a public profile and follow its published recipes.</li>
          <li><span class="font-medium text-foreground">4.</span> Use a demo account only when reviewing authoring or moderation.</li>
        </ol>
      </aside>
    </section>
  </main>
</template>
