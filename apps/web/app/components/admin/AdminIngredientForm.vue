<script setup lang="ts">
import type { Ingredient } from '../../types/api'
import { toFormErrors } from '../../utils/api-errors'
import FormAlert from '../common/FormAlert.vue'
import FormField from '../common/FormField.vue'
import UiButton from '../ui/button/Button.vue'
import UiInput from '../ui/input/Input.vue'

const props = defineProps<{ ingredient?: Ingredient | null, mode: 'create' | 'edit' }>()
const api = useApi()
const pending = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})
const form = reactive({ containsAlcohol: props.ingredient?.containsAlcohol ?? false, name: props.ingredient?.name ?? '', slug: props.ingredient?.slug ?? '' })

async function submit() {
  if (pending.value) return
  pending.value = true
  errorMessage.value = null
  fieldErrors.value = {}
  try {
    const payload = { containsAlcohol: form.containsAlcohol, name: form.name.trim(), slug: form.slug.trim() || undefined }
    if (props.mode === 'edit' && props.ingredient) await api.admin.ingredients.update(props.ingredient.slug, payload)
    else await api.admin.ingredients.create(payload)
    await navigateTo('/admin/ingredients')
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
      <FormField id="ingredient-name" v-slot="field" label="Name" :error="fieldErrors.name"><UiInput id="ingredient-name" v-model="form.name" v-bind="field" required /></FormField>
      <FormField id="ingredient-slug" v-slot="field" label="Slug" optional :error="fieldErrors.slug" help="Leave empty to generate it from the name."><UiInput id="ingredient-slug" v-model="form.slug" v-bind="field" /></FormField>
      <label class="flex min-h-11 items-center gap-3 rounded-md border bg-background px-3 text-sm font-medium"><input v-model="form.containsAlcohol" class="size-4 accent-primary" type="checkbox">Contains alcohol</label>
      <p class="text-sm text-muted-foreground">This classification affects every recipe that uses the ingredient.</p>
      <div class="flex flex-wrap gap-2"><UiButton type="submit" :disabled="pending || !form.name.trim()">{{ pending ? 'Saving...' : mode === 'create' ? 'Create ingredient' : 'Save ingredient' }}</UiButton><UiButton as-child variant="outline"><NuxtLink to="/admin/ingredients">Cancel</NuxtLink></UiButton></div>
    </div>
  </form>
</template>
