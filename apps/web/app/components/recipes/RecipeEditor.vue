<script setup lang="ts">
import FormAlert from '../common/FormAlert.vue'
import FormField from '../common/FormField.vue'
import RecipeImage from './RecipeImage.vue'
import UiButton from '../ui/button/Button.vue'
import UiInput from '../ui/input/Input.vue'
import UiTextarea from '../ui/textarea/Textarea.vue'
import type { Category, Ingredient, RecipeResource, RecipeWorkflow } from '../../types/api'
import { toFormErrors } from '../../utils/api-errors'
import {
  buildRecipePayload,
  categoryChecked,
  createEmptyIngredientRow,
  createEmptyRecipeForm,
  createEmptyStepRow,
  ingredientUnitOptions,
  recipeToForm,
  toggleCategory,
  type RecipeFormState,
  type RecipeIngredientFormRow,
  type RecipeStepFormRow
} from '../../utils/recipe-form'

const props = defineProps<{
  categories: Category[]
  ingredients: Ingredient[]
  initialRecipe?: RecipeResource | null
  mode: 'create' | 'edit'
}>()

const router = useRouter()
const api = useApi()

const form = reactive<RecipeFormState>(createEmptyRecipeForm())
const currentRecipe = ref<RecipeResource | null>(props.initialRecipe ?? null)
const fieldErrors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const selectedImage = ref<File | null>(null)
const pendingAction = ref<'archive' | 'delete' | 'publish' | 'remove-image' | 'save' | null>(null)

const isEdit = computed(() => props.mode === 'edit' || currentRecipe.value !== null)
const statusLabel = computed(() => currentRecipe.value?.status ?? 'draft')
const hasRecipeImage = computed(() => Boolean(currentRecipe.value?.imagePath))
const canPublish = computed(() => currentRecipe.value?.status !== 'published')
const canArchive = computed(() => isEdit.value && currentRecipe.value?.status !== 'archived')

watch(
  () => props.initialRecipe,
  (recipe) => {
    currentRecipe.value = recipe ?? null
    replaceForm(recipe ? recipeToForm(recipe) : createEmptyRecipeForm())
  },
  { immediate: true }
)

async function saveRecipe(action: 'publish' | 'save' = 'save') {
  if (pendingAction.value) {
    return
  }

  clearMessages()
  const validationErrors = validateForm()
  if (Object.keys(validationErrors).length > 0) {
    fieldErrors.value = validationErrors
    formError.value = 'Please complete the highlighted recipe details.'
    return
  }

  pendingAction.value = action
  let phase: 'image' | 'navigation' | 'publish' | 'recipe' = 'recipe'

  try {
    const creating = currentRecipe.value === null
    const savedRecipe = creating
      ? await api.recipes.create(buildRecipePayload(form))
      : await api.recipes.update(currentRecipe.value!.slug, buildRecipePayload(form))

    currentRecipe.value = savedRecipe
    replaceForm(recipeToForm(savedRecipe))

    if (selectedImage.value) {
      phase = 'image'
      await uploadSelectedImage(savedRecipe.slug)
    }

    if (action === 'publish') {
      phase = 'publish'
      applyWorkflow(await api.recipes.publish(savedRecipe.slug))
    }

    if (creating) {
      phase = 'navigation'
      await router.push(action === 'publish' ? `/recipes/${savedRecipe.slug}` : `/recipes/${savedRecipe.slug}/edit`)
      return
    }

    successMessage.value = action === 'publish'
      ? 'Recipe changes have been saved and published.'
      : 'Recipe changes have been saved.'
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    const detail = formErrors.message ?? Object.values(formErrors.fields)[0] ?? 'Something went wrong. Please try again.'

    if (phase === 'image' || phase === 'publish' || phase === 'navigation') {
      fieldErrors.value = {}
      formError.value = phase === 'image'
        ? `Recipe content was saved, but the image upload failed. ${detail}`
        : phase === 'publish'
          ? `Recipe changes were saved, but publication failed. ${detail}`
          : `Recipe changes were saved, but the next page could not be opened. ${detail}`
    } else {
      fieldErrors.value = formErrors.fields
      formError.value = `Recipe could not be saved. ${detail}`
    }
  } finally {
    pendingAction.value = null
  }
}

async function archiveRecipe() {
  if (!currentRecipe.value || pendingAction.value) {
    return
  }

  clearMessages()
  pendingAction.value = 'archive'

  try {
    const workflow = await api.recipes.archive(currentRecipe.value.slug)
    applyWorkflow(workflow)
    successMessage.value = 'Recipe has been archived.'
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    formError.value = formErrors.message
  } finally {
    pendingAction.value = null
  }
}

async function deleteRecipe() {
  if (!currentRecipe.value || pendingAction.value || !window.confirm('Delete this recipe? This will remove it from public pages.')) {
    return
  }

  clearMessages()
  pendingAction.value = 'delete'

  try {
    await api.recipes.delete(currentRecipe.value.slug)
    await router.push('/account')
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    formError.value = formErrors.message
  } finally {
    pendingAction.value = null
  }
}

async function removeImage() {
  if (!currentRecipe.value || pendingAction.value) {
    return
  }

  clearMessages()
  pendingAction.value = 'remove-image'

  try {
    const imageState = await api.recipes.removeImage(currentRecipe.value.slug)
    currentRecipe.value = {
      ...currentRecipe.value,
      imagePath: imageState.imagePath
    }
    successMessage.value = 'Recipe image has been removed.'
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    formError.value = formErrors.message
  } finally {
    pendingAction.value = null
  }
}

function addStep() {
  form.steps.push(createEmptyStepRow())
}

function removeStep(index: number) {
  form.steps.splice(index, 1)
  if (form.steps.length === 0) {
    addStep()
  }
}

function addIngredient() {
  form.ingredients.push(createEmptyIngredientRow())
}

function removeIngredient(index: number) {
  form.ingredients.splice(index, 1)
  if (form.ingredients.length === 0) {
    addIngredient()
  }
}

function moveStep(index: number, direction: -1 | 1) {
  moveRow(form.steps, index, direction)
}

function moveIngredient(index: number, direction: -1 | 1) {
  moveRow(form.ingredients, index, direction)
}

function onImageChange(event: Event) {
  const input = event.target as HTMLInputElement
  selectedImage.value = input.files?.[0] ?? null
  successMessage.value = null
}

function onCategoryChange(category: Category, event: Event) {
  toggleCategory(form, category, (event.target as HTMLInputElement).checked)
}

async function uploadSelectedImage(recipeSlug: string) {
  if (!selectedImage.value) {
    return
  }

  const imageState = await api.recipes.image(recipeSlug, selectedImage.value)
  selectedImage.value = null

  if (currentRecipe.value?.slug === imageState.recipeSlug) {
    currentRecipe.value = {
      ...currentRecipe.value,
      imagePath: imageState.imagePath
    }
  }
}

function applyWorkflow(workflow: RecipeWorkflow) {
  if (!currentRecipe.value) {
    return
  }

  currentRecipe.value = {
    ...currentRecipe.value,
    moderationStatus: workflow.moderationStatus,
    publishedAt: workflow.publishedAt,
    status: workflow.status,
    updatedAt: workflow.updatedAt
  }
}

function validateForm(): Record<string, string> {
  const errors: Record<string, string> = {}

  if (!form.title.trim()) {
    errors.title = 'Recipe title is required.'
  }

  if (!form.description.trim()) {
    errors.description = 'Recipe description is required.'
  }

  if (Number(form.preparationTimeMinutes) < 1) {
    errors.preparationTimeMinutes = 'Preparation time must be at least 1 minute.'
  }

  if (Number(form.servings) < 1) {
    errors.servings = 'Servings must be at least 1.'
  }

  if (!form.steps.some(step => step.instruction.trim())) {
    errors.steps = 'Add at least one preparation step.'
  }

  if (!form.ingredients.some(recipeIngredient => recipeIngredient.ingredientSlug && recipeIngredient.quantity !== '')) {
    errors.ingredients = 'Add at least one measured ingredient.'
  }

  return errors
}

function clearMessages() {
  fieldErrors.value = {}
  formError.value = null
  successMessage.value = null
}

function replaceForm(nextForm: RecipeFormState) {
  form.categories = [...nextForm.categories]
  form.description = nextForm.description
  form.difficulty = nextForm.difficulty
  form.ingredients = nextForm.ingredients.map(row => ({ ...row }))
  form.preparationTimeMinutes = nextForm.preparationTimeMinutes
  form.servings = nextForm.servings
  form.steps = nextForm.steps.map(row => ({ ...row }))
  form.title = nextForm.title
}

function moveRow<T extends RecipeIngredientFormRow | RecipeStepFormRow>(rows: T[], index: number, direction: -1 | 1) {
  const targetIndex = index + direction
  if (targetIndex < 0 || targetIndex >= rows.length) {
    return
  }

  const row = rows[index]
  const targetRow = rows[targetIndex]
  if (!row || !targetRow) {
    return
  }

  rows[index] = targetRow
  rows[targetIndex] = row
}
</script>

<template>
  <form class="grid gap-6" @submit.prevent="saveRecipe('save')">
    <FormAlert v-if="formError" :message="formError" tone="error" />
    <FormAlert v-if="successMessage" :message="successMessage" tone="success" />

    <section class="content-panel settings-section" aria-labelledby="recipe-basics-title">
      <div class="panel-header">
        <p class="eyebrow">
          {{ isEdit ? `Status: ${statusLabel}` : 'Draft first' }}
        </p>
        <h2 id="recipe-basics-title" class="panel-title">
          Recipe details
        </h2>
        <p class="panel-copy">
          Write the public title, summary, serving format, and alcohol visibility.
        </p>
      </div>

      <div class="form-stack">
        <FormField id="recipe-title" v-slot="field" label="Title" :error="fieldErrors.title">
          <UiInput
            id="recipe-title"
            v-model="form.title"
            v-bind="field"
            autocomplete="off"
            name="title"
            required
            type="text"
          />
        </FormField>

        <FormField id="recipe-description" v-slot="field" label="Description" :error="fieldErrors.description">
          <UiTextarea
            id="recipe-description"
            v-model="form.description"
            v-bind="field"
            name="description"
            required
            rows="5"
          />
        </FormField>

        <div class="grid gap-4 md:grid-cols-3">
          <FormField id="recipe-difficulty" label="Difficulty">
            <select id="recipe-difficulty" v-model="form.difficulty" class="min-h-12 w-full rounded-lg border border-input bg-background px-3.5 py-3 text-foreground" name="difficulty">
              <option value="easy">
                Easy
              </option>
              <option value="medium">
                Medium
              </option>
              <option value="hard">
                Hard
              </option>
            </select>
          </FormField>

          <FormField id="recipe-prep-time" v-slot="field" label="Preparation time" :error="fieldErrors.preparationTimeMinutes">
            <UiInput
              id="recipe-prep-time"
              v-model="form.preparationTimeMinutes"
              v-bind="field"
              min="1"
              name="preparationTimeMinutes"
              required
              type="number"
            />
          </FormField>

          <FormField id="recipe-servings" v-slot="field" label="Servings" :error="fieldErrors.servings">
            <UiInput
              id="recipe-servings"
              v-model="form.servings"
              v-bind="field"
              min="1"
              name="servings"
              required
              type="number"
            />
          </FormField>
        </div>

      </div>
    </section>

    <section class="content-panel settings-section" aria-labelledby="recipe-image-title">
      <div class="panel-header">
        <h2 id="recipe-image-title" class="panel-title">
          Main image
        </h2>
        <p class="panel-copy">
          Upload one recipe image for cards, detail pages, and OpenGraph previews.
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-[220px_minmax(0,1fr)] md:items-start">
        <div v-if="currentRecipe" class="overflow-hidden rounded-lg border border-border bg-muted">
          <RecipeImage :recipe="currentRecipe" />
        </div>
        <div v-else class="grid aspect-[4/3] place-items-center rounded-lg border border-dashed border-border bg-muted text-center text-sm font-bold text-muted-foreground">
          Image preview after save
        </div>

        <div class="grid gap-3">
          <FormField id="recipe-image" label="Image file" optional>
            <input id="recipe-image" accept="image/*" class="file-input" name="image" type="file" @change="onImageChange">
          </FormField>
          <p v-if="selectedImage" class="text-sm font-bold text-muted-foreground">
            Selected: {{ selectedImage.name }}
          </p>
          <UiButton v-if="isEdit && hasRecipeImage" type="button" variant="outline" :disabled="Boolean(pendingAction)" @click="removeImage">
            {{ pendingAction === 'remove-image' ? 'Removing...' : 'Remove image' }}
          </UiButton>
        </div>
      </div>
    </section>

    <section class="content-panel settings-section" aria-labelledby="recipe-categories-title">
      <div class="panel-header">
        <h2 id="recipe-categories-title" class="panel-title">
          Categories
        </h2>
        <p class="panel-copy">
          Attach the recipe to the public shelves where it belongs.
        </p>
      </div>

      <div v-if="categories.length > 0" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <label
          v-for="category in categories"
          :key="category.slug"
          class="flex min-h-12 items-center gap-3 rounded-lg border border-border bg-background px-3.5 py-3 font-bold"
        >
          <input
            :checked="categoryChecked(form, category)"
            class="size-4 accent-primary"
            type="checkbox"
            :value="category.slug"
            @change="onCategoryChange(category, $event)"
          >
          {{ category.name }}
        </label>
      </div>
      <p v-else class="text-muted-foreground">
        No categories are available yet.
      </p>
    </section>

    <section class="content-panel settings-section" aria-labelledby="recipe-ingredients-title">
      <div class="panel-header">
        <h2 id="recipe-ingredients-title" class="panel-title">
          Measured ingredients
        </h2>
        <p class="panel-copy">
          Keep ingredients ordered and dosed so the method stays easy to scan.
        </p>
      </div>

      <FormAlert v-if="fieldErrors.ingredients" :message="fieldErrors.ingredients" tone="error" />

      <div class="grid gap-3">
        <div
          v-for="(recipeIngredient, index) in form.ingredients"
          :key="`ingredient-${index}`"
          class="grid gap-3 rounded-lg border border-border bg-background p-3 lg:grid-cols-[minmax(170px,1fr)_120px_130px_minmax(160px,1fr)_auto]"
        >
          <select v-model="recipeIngredient.ingredientSlug" class="min-h-12 rounded-lg border border-input bg-background px-3.5 py-3 text-foreground" :aria-label="`Ingredient ${index + 1}`">
            <option value="">
              Choose ingredient
            </option>
            <option v-for="ingredient in ingredients" :key="ingredient.slug" :value="ingredient.slug">
              {{ ingredient.name }}
            </option>
          </select>
          <UiInput v-model="recipeIngredient.quantity" :aria-label="`Quantity ${index + 1}`" min="0" step="0.01" type="number" />
          <select v-model="recipeIngredient.unit" class="min-h-12 rounded-lg border border-input bg-background px-3.5 py-3 text-foreground" :aria-label="`Unit ${index + 1}`">
            <option v-for="unit in ingredientUnitOptions" :key="unit.value" :value="unit.value">
              {{ unit.label }}
            </option>
          </select>
          <UiInput v-model="recipeIngredient.note" :aria-label="`Ingredient note ${index + 1}`" type="text" />
          <div class="flex flex-wrap items-center gap-2">
            <UiButton type="button" variant="outline" size="sm" :disabled="index === 0" @click="moveIngredient(index, -1)">
              Up
            </UiButton>
            <UiButton type="button" variant="outline" size="sm" :disabled="index === form.ingredients.length - 1" @click="moveIngredient(index, 1)">
              Down
            </UiButton>
            <UiButton type="button" variant="ghost" size="sm" @click="removeIngredient(index)">
              Remove
            </UiButton>
          </div>
        </div>
      </div>

      <UiButton class="mt-4" type="button" variant="outline" @click="addIngredient">
        Add ingredient
      </UiButton>
    </section>

    <section class="content-panel settings-section" aria-labelledby="recipe-steps-title">
      <div class="panel-header">
        <h2 id="recipe-steps-title" class="panel-title">
          Preparation steps
        </h2>
        <p class="panel-copy">
          Add each instruction in the order readers should follow.
        </p>
      </div>

      <FormAlert v-if="fieldErrors.steps" :message="fieldErrors.steps" tone="error" />

      <div class="grid gap-3">
        <div
          v-for="(step, index) in form.steps"
          :key="`step-${index}`"
          class="grid gap-3 rounded-lg border border-border bg-background p-3 md:grid-cols-[3rem_minmax(0,1fr)_auto]"
        >
          <span class="grid size-10 place-items-center rounded-full bg-primary text-sm font-black text-primary-foreground">
            {{ index + 1 }}
          </span>
          <UiTextarea v-model="step.instruction" :aria-label="`Step ${index + 1}`" class="min-h-24" rows="3" />
          <div class="flex flex-wrap items-start gap-2">
            <UiButton type="button" variant="outline" size="sm" :disabled="index === 0" @click="moveStep(index, -1)">
              Up
            </UiButton>
            <UiButton type="button" variant="outline" size="sm" :disabled="index === form.steps.length - 1" @click="moveStep(index, 1)">
              Down
            </UiButton>
            <UiButton type="button" variant="ghost" size="sm" @click="removeStep(index)">
              Remove
            </UiButton>
          </div>
        </div>
      </div>

      <UiButton class="mt-4" type="button" variant="outline" @click="addStep">
        Add step
      </UiButton>
    </section>

    <section class="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-card p-4 shadow-sm">
      <UiButton type="submit" :disabled="Boolean(pendingAction)">
        {{ pendingAction === 'save' ? 'Saving...' : isEdit ? 'Save changes' : 'Save draft' }}
      </UiButton>
      <UiButton v-if="!isEdit || canPublish" type="button" variant="secondary" :disabled="Boolean(pendingAction)" @click="saveRecipe('publish')">
        {{ pendingAction === 'publish' ? 'Publishing...' : isEdit ? 'Save and publish' : 'Create and publish' }}
      </UiButton>
      <UiButton v-if="canArchive" type="button" variant="outline" :disabled="Boolean(pendingAction)" @click="archiveRecipe">
        {{ pendingAction === 'archive' ? 'Archiving...' : 'Archive recipe' }}
      </UiButton>
      <UiButton v-if="isEdit" type="button" variant="destructive" :disabled="Boolean(pendingAction)" @click="deleteRecipe">
        {{ pendingAction === 'delete' ? 'Deleting...' : 'Delete recipe' }}
      </UiButton>
      <UiButton v-if="currentRecipe" as-child type="button" variant="ghost">
        <NuxtLink :to="`/recipes/${currentRecipe.slug}`">
          View public page
        </NuxtLink>
      </UiButton>
    </section>
  </form>
</template>
