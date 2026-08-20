<script setup lang="ts">
import AdminShell from '../../components/admin/AdminShell.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import FormAlert from '../../components/common/FormAlert.vue'
import PaginationNav from '../../components/common/PaginationNav.vue'
import UiButton from '../../components/ui/button/Button.vue'
import UiInput from '../../components/ui/input/Input.vue'
import UiTextarea from '../../components/ui/textarea/Textarea.vue'
import { usePaginatedAdminList } from '../../composables/usePaginatedAdminList'
import { ApiRequestError } from '../../services/api-client'
import type { Category } from '../../types/api'

await useRequireAdmin()

const api = useApi()
const { error, items: categories, nextTo, pagination, pending, previousTo, refresh } = await usePaginatedAdminList<Category>(
  'admin:categories',
  '/admin/categories',
  api.admin.categories.list
)
const createForm = reactive({
  description: '',
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
  title: 'Admin categories | What\'s In My Bar',
  description: 'Create and edit recipe categories.'
})

async function createCategory() {
  createPending.value = true
  createError.value = null
  createSuccess.value = null

  try {
    await api.admin.categories.create({
      description: createForm.description.trim() || null,
      name: createForm.name.trim(),
      slug: createForm.slug.trim() || undefined
    })
    await refresh()
    createForm.description = ''
    createForm.name = ''
    createForm.slug = ''
    createSuccess.value = 'Category created.'
  } catch (error: unknown) {
    createError.value = error instanceof ApiRequestError ? error.message : 'Category could not be created.'
  } finally {
    createPending.value = false
  }
}

async function updateCategory(category: Category, event: Event) {
  const form = new FormData(event.currentTarget as HTMLFormElement)
  rowPending.value[category.slug] = true
  rowError.value[category.slug] = ''
  rowMessage.value[category.slug] = ''

  try {
    const updatedCategory = await api.admin.categories.update(category.slug, {
      description: stringValue(form.get('description')) || null,
      name: stringValue(form.get('name')),
      slug: stringValue(form.get('slug')) || undefined
    })
    categories.value = categories.value.map(currentCategory => currentCategory.slug === category.slug ? updatedCategory : currentCategory)
    rowMessage.value[updatedCategory.slug] = 'Category updated.'
  } catch (error: unknown) {
    rowError.value[category.slug] = error instanceof ApiRequestError ? error.message : 'Category could not be updated.'
  } finally {
    rowPending.value[category.slug] = false
  }
}

function stringValue(value: FormDataEntryValue | null): string {
  return typeof value === 'string' ? value.trim() : ''
}
</script>

<template>
  <AdminShell
    current="categories"
    description="Create and refine category labels used by public recipe discovery."
    title="Categories"
  >
    <section class="content-panel p-5">
      <h2 class="section-title">
        New category
      </h2>
      <form class="mt-5 grid gap-4 lg:grid-cols-[minmax(180px,0.4fr)_minmax(160px,0.3fr)_minmax(220px,1fr)_auto]" @submit.prevent="createCategory">
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
        <label class="grid gap-2">
          <span class="text-sm font-black">Description <span class="font-semibold text-muted-foreground">optional</span></span>
          <UiTextarea v-model="createForm.description" class="min-h-12" rows="1" />
        </label>
        <div class="grid items-end">
          <UiButton type="submit" :disabled="createPending || !createForm.name.trim()">
            {{ createPending ? 'Creating...' : 'Create' }}
          </UiButton>
        </div>
      </form>
    </section>

    <div v-if="pending" class="loading-panel mt-6">
      Loading categories...
    </div>

    <EmptyState
      v-else-if="error"
      action-label="Reload"
      action-to="/admin/categories"
      description="Categories are unavailable right now."
      title="Categories could not be loaded"
    />

    <section v-else class="mt-6 grid gap-4" aria-label="Category list">
      <form v-for="category in categories" :key="category.slug" class="content-panel grid gap-4 p-4 lg:grid-cols-[minmax(180px,0.35fr)_minmax(160px,0.25fr)_minmax(220px,1fr)_auto]" @submit.prevent="updateCategory(category, $event)">
        <label class="grid gap-2">
          <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">Name</span>
          <UiInput name="name" :default-value="category.name" required />
        </label>
        <label class="grid gap-2">
          <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">Slug</span>
          <UiInput name="slug" :default-value="category.slug" />
        </label>
        <label class="grid gap-2">
          <span class="text-xs font-black uppercase tracking-wide text-muted-foreground">Description</span>
          <UiTextarea name="description" :default-value="category.description ?? ''" rows="2" />
        </label>
        <div class="grid items-end">
          <UiButton type="submit" :disabled="rowPending[category.slug]">
            {{ rowPending[category.slug] ? 'Saving...' : 'Save' }}
          </UiButton>
        </div>
        <FormAlert v-if="rowError[category.slug]" class="lg:col-span-4" :message="rowError[category.slug] ?? ''" tone="error" />
        <FormAlert v-else-if="rowMessage[category.slug]" class="lg:col-span-4" :message="rowMessage[category.slug] ?? ''" tone="success" />
      </form>
      <p v-if="categories.length === 0" class="text-muted-foreground">
        No categories found.
      </p>
    </section>

    <PaginationNav
      v-if="!pending && !error"
      aria-label="Category list pagination"
      :next-to="nextTo"
      :pagination="pagination"
      :previous-to="previousTo"
    />
  </AdminShell>
</template>
