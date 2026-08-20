<script setup lang="ts">
import AdminBadge from '../../components/admin/AdminBadge.vue'
import AdminShell from '../../components/admin/AdminShell.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import FormAlert from '../../components/common/FormAlert.vue'
import PaginationNav from '../../components/common/PaginationNav.vue'
import UiButton from '../../components/ui/button/Button.vue'
import UiInput from '../../components/ui/input/Input.vue'
import { usePaginatedAdminList } from '../../composables/usePaginatedAdminList'
import { ApiRequestError } from '../../services/api-client'
import type { Ingredient } from '../../types/api'

await useRequireAdmin()

const api = useApi()
const { error, items: ingredients, nextTo, pagination, pending, previousTo, refresh } = await usePaginatedAdminList<Ingredient>(
  'admin:ingredients',
  '/admin/ingredients',
  api.admin.ingredients.list
)
const createForm = reactive({
  containsAlcohol: false,
  name: '',
  slug: ''
})
const createPending = ref(false)
const createError = ref<string | null>(null)
const createSuccess = ref<string | null>(null)
const rowPending = ref<Record<string, boolean>>({})
const rowError = ref<Record<string, string>>({})
const rowMessage = ref<Record<string, string>>({})

useSeoMeta({
  title: 'Admin ingredients | What\'s In My Bar',
  description: 'Create ingredients and maintain alcohol classification.'
})

async function createIngredient() {
  createPending.value = true
  createError.value = null
  createSuccess.value = null

  try {
    await api.admin.ingredients.create({
      containsAlcohol: createForm.containsAlcohol,
      name: createForm.name.trim(),
      slug: createForm.slug.trim() || undefined
    })
    await refresh()
    createForm.containsAlcohol = false
    createForm.name = ''
    createForm.slug = ''
    createSuccess.value = 'Ingredient created.'
  } catch (error: unknown) {
    createError.value = error instanceof ApiRequestError ? error.message : 'Ingredient could not be created.'
  } finally {
    createPending.value = false
  }
}

async function updateIngredient(ingredient: Ingredient, event: Event) {
  const form = new FormData(event.currentTarget as HTMLFormElement)
  rowPending.value[ingredient.slug] = true
  rowError.value[ingredient.slug] = ''
  rowMessage.value[ingredient.slug] = ''

  try {
    const updatedIngredient = await api.admin.ingredients.update(ingredient.slug, {
      containsAlcohol: form.get('containsAlcohol') === 'on',
      name: stringValue(form.get('name')),
      slug: stringValue(form.get('slug')) || undefined
    })
    ingredients.value = ingredients.value.map(currentIngredient => currentIngredient.slug === ingredient.slug ? updatedIngredient : currentIngredient)
    rowMessage.value[updatedIngredient.slug] = 'Ingredient updated.'
  } catch (error: unknown) {
    rowError.value[ingredient.slug] = error instanceof ApiRequestError ? error.message : 'Ingredient could not be updated.'
  } finally {
    rowPending.value[ingredient.slug] = false
  }
}

function stringValue(value: FormDataEntryValue | null): string {
  return typeof value === 'string' ? value.trim() : ''
}
</script>

<template>
  <AdminShell
    current="ingredients"
    description="Maintain ingredient naming and alcohol classification for recipe safety filters."
    title="Ingredients"
  >
    <section class="content-panel p-5">
      <h2 class="section-title">
        New ingredient
      </h2>
      <form class="mt-5 grid gap-4 lg:grid-cols-[minmax(180px,0.4fr)_minmax(160px,0.3fr)_minmax(160px,0.3fr)_auto]" @submit.prevent="createIngredient">
        <FormAlert v-if="createError" class="lg:col-span-4" :message="createError" tone="error" />
        <FormAlert v-if="createSuccess" class="lg:col-span-4" :message="createSuccess" tone="success" />
        <label class="grid gap-2">
          <span class="text-sm font-black">Name</span>
          <UiInput v-model="createForm.name" required />
        </label>
        <label class="grid gap-2">
          <span class="text-sm font-black">Slug <span class="font-semibold text-muted-foreground">optional</span></span>
          <UiInput v-model="createForm.slug" />
        </label>
        <label class="flex min-h-12 items-end gap-2 font-bold">
          <input v-model="createForm.containsAlcohol" class="mb-4 size-4 accent-primary" type="checkbox">
          Contains alcohol
        </label>
        <div class="grid items-end">
          <UiButton type="submit" :disabled="createPending || !createForm.name.trim()">
            {{ createPending ? 'Creating...' : 'Create' }}
          </UiButton>
        </div>
      </form>
    </section>

    <div v-if="pending" class="loading-panel mt-6">
      Loading ingredients...
    </div>

    <EmptyState
      v-else-if="error"
      action-label="Reload"
      action-to="/admin/ingredients"
      description="Ingredients are unavailable right now."
      title="Ingredients could not be loaded"
    />

    <section v-else class="mt-6 grid gap-4" aria-label="Ingredient list">
      <form v-for="ingredient in ingredients" :key="ingredient.slug" class="content-panel grid gap-4 p-4 lg:grid-cols-[minmax(180px,0.35fr)_minmax(160px,0.25fr)_minmax(160px,0.25fr)_auto]" @submit.prevent="updateIngredient(ingredient, $event)">
        <label class="grid gap-2">
          <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">Name</span>
          <UiInput name="name" :default-value="ingredient.name" required />
        </label>
        <label class="grid gap-2">
          <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">Slug</span>
          <UiInput name="slug" :default-value="ingredient.slug" />
        </label>
        <label class="flex min-h-12 items-center gap-2 font-bold">
          <input class="size-4 accent-primary" name="containsAlcohol" type="checkbox" :checked="ingredient.containsAlcohol">
          Contains alcohol
        </label>
        <div class="grid items-end">
          <UiButton type="submit" :disabled="rowPending[ingredient.slug]">
            {{ rowPending[ingredient.slug] ? 'Saving...' : 'Save' }}
          </UiButton>
        </div>
        <div class="lg:col-span-4">
          <AdminBadge :tone="ingredient.containsAlcohol ? 'warning' : 'success'">
            {{ ingredient.containsAlcohol ? 'alcoholic' : 'zero-proof' }}
          </AdminBadge>
          <FormAlert v-if="rowError[ingredient.slug]" class="mt-3" :message="rowError[ingredient.slug] ?? ''" tone="error" />
          <FormAlert v-else-if="rowMessage[ingredient.slug]" class="mt-3" :message="rowMessage[ingredient.slug] ?? ''" tone="success" />
        </div>
      </form>
      <p v-if="ingredients.length === 0" class="text-muted-foreground">
        No ingredients found.
      </p>
    </section>

    <PaginationNav
      v-if="!pending && !error"
      aria-label="Ingredient list pagination"
      :next-to="nextTo"
      :pagination="pagination"
      :previous-to="previousTo"
    />
  </AdminShell>
</template>
