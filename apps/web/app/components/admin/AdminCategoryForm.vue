<script setup lang="ts">
import type { Category } from '../../types/api'
import { toFormErrors } from '../../utils/api-errors'
import FormAlert from '../common/FormAlert.vue'
import FormField from '../common/FormField.vue'
import UiButton from '../ui/button/Button.vue'
import UiInput from '../ui/input/Input.vue'
import UiTextarea from '../ui/textarea/Textarea.vue'

const props = defineProps<{ category?: Category | null, mode: 'create' | 'edit' }>()
const api = useApi()
const pending = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})
const form = reactive({ description: props.category?.description ?? '', name: props.category?.name ?? '', slug: props.category?.slug ?? '' })

async function submit() {
  if (pending.value) return
  pending.value = true
  errorMessage.value = null
  fieldErrors.value = {}
  try {
    const payload = { description: form.description.trim() || null, name: form.name.trim(), slug: form.slug.trim() || undefined }
    if (props.mode === 'edit' && props.category) await api.admin.categories.update(props.category.slug, payload)
    else await api.admin.categories.create(payload)
    await navigateTo('/admin/categories')
  } catch (caught: unknown) {
    const formErrors = toFormErrors(caught)
    errorMessage.value = formErrors.message
    fieldErrors.value = formErrors.fields
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <form class="max-w-2xl rounded-md border bg-card p-5 sm:p-6" @submit.prevent="submit">
    <FormAlert v-if="errorMessage" class="mb-5" :message="errorMessage" tone="error" />
    <div class="grid gap-5">
      <FormField id="category-name" v-slot="field" label="Name" :error="fieldErrors.name"><UiInput id="category-name" v-model="form.name" v-bind="field" required /></FormField>
      <FormField id="category-slug" v-slot="field" label="Slug" optional :error="fieldErrors.slug" help="Leave empty to generate it from the name."><UiInput id="category-slug" v-model="form.slug" v-bind="field" /></FormField>
      <FormField id="category-description" v-slot="field" label="Description" optional :error="fieldErrors.description"><UiTextarea id="category-description" v-model="form.description" v-bind="field" rows="5" /></FormField>
      <div class="flex flex-wrap gap-2"><UiButton type="submit" :disabled="pending || !form.name.trim()">{{ pending ? 'Saving...' : mode === 'create' ? 'Create category' : 'Save category' }}</UiButton><UiButton as-child variant="outline"><NuxtLink to="/admin/categories">Cancel</NuxtLink></UiButton></div>
    </div>
  </form>
</template>
