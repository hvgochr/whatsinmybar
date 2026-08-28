<script setup lang="ts">
import { ArrowDown01Icon, ArrowUp01Icon, Delete02Icon } from '@hugeicons/core-free-icons'
import { HugeiconsIcon } from '@hugeicons/vue'
import FormAlert from '../common/FormAlert.vue'
import FormField from '../common/FormField.vue'
import DestructiveConfirm from '../common/DestructiveConfirm.vue'
import RecipeImage from './RecipeImage.vue'
import UiButton from '../ui/button/Button.vue'
import UiInput from '../ui/input/Input.vue'
import UiTextarea from '../ui/textarea/Textarea.vue'
import { RadioGroup, RadioGroupItem } from '../ui/radio-group'
import type { Category, Ingredient, RecipeResource, RecipeWorkflow } from '../../types/api'
import { ApiRequestError } from '../../services/api-client'
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
const auth = useAuth()
const notifications = useNotifications()

const form = reactive<RecipeFormState>(createEmptyRecipeForm())
const currentRecipe = ref<RecipeResource | null>(props.initialRecipe ?? null)
const fieldErrors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)
const selectedImage = ref<File | null>(null)
const selectedImagePreview = ref<string | null>(null)
const imageInput = ref<HTMLInputElement | null>(null)
const imageUploadFailed = ref(false)
const createdDuringSession = ref(false)
const pendingAction = ref<'archive' | 'delete' | 'publish' | 'remove-image' | 'save' | null>(null)
const deleteDialogOpen = ref(false)
const removeImageDialogOpen = ref(false)
const destructiveError = ref<string | null>(null)

const isEdit = computed(() => props.mode === 'edit' || currentRecipe.value !== null)
const statusLabel = computed(() => currentRecipe.value?.status ?? 'draft')
const hasRecipeImage = computed(() => Boolean(currentRecipe.value?.imagePath))
const canPublish = computed(() => currentRecipe.value?.status !== 'published')
const canArchive = computed(() => isEdit.value && currentRecipe.value?.status !== 'archived')

const recipeImageTypes = ['image/jpeg', 'image/png', 'image/webp']
const recipeImageMaxSize = 5 * 1024 * 1024

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
    createdDuringSession.value ||= creating
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

    notifications.success(`recipe-save:${savedRecipe.slug}`, action === 'publish' ? 'Recipe published.' : 'Recipe saved.')
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    const detail = formErrors.message ?? Object.values(formErrors.fields)[0] ?? 'Something went wrong. Please try again.'

    if (phase === 'image' || phase === 'publish' || phase === 'navigation') {
      fieldErrors.value = phase === 'image' ? { image: recipeImageErrorMessage(error) } : {}
      imageUploadFailed.value = phase === 'image'
      formError.value = phase === 'image'
        ? 'Recipe saved, but the image could not be uploaded.'
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
    notifications.success(`recipe-archive:${currentRecipe.value.slug}`, 'Recipe archived.')
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    formError.value = formErrors.message
  } finally {
    pendingAction.value = null
  }
}

async function deleteRecipe() {
  if (!currentRecipe.value || pendingAction.value) {
    return
  }

  clearMessages()
  pendingAction.value = 'delete'

  try {
    await api.recipes.delete(currentRecipe.value.slug)
    deleteDialogOpen.value = false
    await router.push(auth.currentUser.value ? `/users/${auth.currentUser.value.username}#my-recipes` : '/recipes')
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    formError.value = formErrors.message
    destructiveError.value = formErrors.message
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
    notifications.success(`recipe-image-remove:${currentRecipe.value.slug}`, 'Recipe image removed.')
    removeImageDialogOpen.value = false
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    formError.value = formErrors.message
    destructiveError.value = formErrors.message
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
  const file = input.files?.[0] ?? null
  clearSelectedImage(false)
  fieldErrors.value = { ...fieldErrors.value, image: '' }
  imageUploadFailed.value = false

  if (!file) return

  const validationError = validateRecipeImage(file)
  if (validationError) {
    fieldErrors.value = { ...fieldErrors.value, image: validationError }
    input.value = ''
    return
  }

  selectedImage.value = file
  selectedImagePreview.value = URL.createObjectURL(file)
}

function clearSelectedImage(resetInput = true) {
  if (selectedImagePreview.value) URL.revokeObjectURL(selectedImagePreview.value)
  selectedImagePreview.value = null
  selectedImage.value = null
  imageUploadFailed.value = false
  if (resetInput && imageInput.value) imageInput.value.value = ''
}

function onCategoryChange(category: Category, event: Event) {
  toggleCategory(form, category, (event.target as HTMLInputElement).checked)
}

async function uploadSelectedImage(recipeSlug: string) {
  if (!selectedImage.value) {
    return
  }

  const imageState = await api.recipes.image(recipeSlug, selectedImage.value)
  clearSelectedImage()

  if (currentRecipe.value?.slug === imageState.recipeSlug) {
    currentRecipe.value = {
      ...currentRecipe.value,
      imagePath: imageState.imagePath
    }
  }
}

async function retryImageUpload() {
  if (!currentRecipe.value || !selectedImage.value || pendingAction.value) return

  pendingAction.value = 'save'
  formError.value = null
  fieldErrors.value = { ...fieldErrors.value, image: '' }

  try {
    const slug = currentRecipe.value.slug
    await uploadSelectedImage(slug)
    notifications.success(`recipe-image:${slug}`, 'Recipe image uploaded.')
    if (createdDuringSession.value) await router.replace(`/recipes/${slug}/edit`)
  } catch (error: unknown) {
    imageUploadFailed.value = true
    fieldErrors.value = { ...fieldErrors.value, image: recipeImageErrorMessage(error) }
    formError.value = 'Recipe saved, but the image could not be uploaded.'
  } finally {
    pendingAction.value = null
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

function validateRecipeImage(file: File): string | null {
  if (!recipeImageTypes.includes(file.type)) return 'Choose a JPEG, PNG, or WebP image.'
  if (file.size <= 0 || file.size > recipeImageMaxSize) return 'Image must be 5 MB or smaller.'
  return null
}

function recipeImageErrorMessage(error: unknown): string {
  if (error instanceof ApiRequestError) {
    if (/size/i.test(error.message)) return 'Image must be 5 MB or smaller.'
    if (/JPEG|PNG|WebP|image file is required/i.test(error.message)) return 'Choose a JPEG, PNG, or WebP image.'
  }

  return 'The image could not be uploaded. Try again.'
}

onBeforeUnmount(() => clearSelectedImage(false))

function clearMessages() {
  fieldErrors.value = {}
  formError.value = null
  destructiveError.value = null
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

    <div class="grid gap-6 lg:grid-cols-[13rem_minmax(0,1fr)] lg:items-start">
      <aside class="sticky top-16 z-20 -mx-4 overflow-x-auto border-y bg-background px-4 py-3 lg:top-20 lg:mx-0 lg:rounded-md lg:border lg:p-3">
        <nav class="flex min-w-max gap-1 lg:grid lg:min-w-0" aria-label="Recipe form sections">
          <a
            v-for="item in [
            { id: 'recipe-basics', label: 'Basic information' },
            { id: 'recipe-image-section', label: 'Image' },
            { id: 'recipe-categories', label: 'Categories' },
            { id: 'recipe-ingredients', label: 'Ingredients' },
            { id: 'recipe-steps', label: 'Instructions' },
            { id: 'recipe-publication', label: 'Publication' }
            ]"
            :key="item.id"
            class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground"
            :href="`#${item.id}`"
          >{{ item.label }}</a>
        </nav>
      </aside>

      <div class="grid gap-6">

    <section id="recipe-basics" class="scroll-mt-36 rounded-md border bg-card p-5 sm:p-6" aria-labelledby="recipe-basics-title">
      <div class="mb-5">
        <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
          {{ isEdit ? `Status: ${statusLabel}` : 'Draft first' }}
        </p>
        <h2 id="recipe-basics-title" class="section-heading">
          Basic information
        </h2>
        <p class="section-description">
          Set the public title, summary, difficulty, preparation time, and yield.
        </p>
      </div>

      <div class="grid gap-5">
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

        <div class="grid max-w-xl gap-5">
          <fieldset class="grid gap-2">
            <legend class="field-label mb-1">Difficulty</legend>
            <RadioGroup v-model="form.difficulty" class="grid grid-cols-3 gap-2" name="difficulty">
              <label v-for="difficulty in ['easy', 'medium', 'hard'] as const" :key="difficulty" class="flex min-h-10 cursor-pointer items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium capitalize has-[[data-state=checked]]:bg-primary has-[[data-state=checked]]:text-primary-foreground">
                <RadioGroupItem :id="`recipe-difficulty-${difficulty}`" :value="difficulty" class="border-current data-[state=checked]:bg-current" />
                {{ difficulty }}
              </label>
            </RadioGroup>
          </fieldset>

          <FormField id="recipe-prep-time" v-slot="field" label="Preparation time" help="Minutes from start to finish." :error="fieldErrors.preparationTimeMinutes">
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

          <FormField id="recipe-servings" v-slot="field" label="Servings" help="Number of servings the recipe makes." :error="fieldErrors.servings">
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

    <section id="recipe-image-section" class="scroll-mt-36 rounded-md border bg-card p-5 sm:p-6" aria-labelledby="recipe-image-title">
      <div class="mb-5">
        <h2 id="recipe-image-title" class="section-heading">
          Main image
        </h2>
        <p class="section-description">
          Upload one recipe image for cards, detail pages, and OpenGraph previews.
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-[220px_minmax(0,1fr)] md:items-start">
        <div v-if="selectedImagePreview" class="aspect-[4/3] overflow-hidden rounded-md border bg-muted">
          <img :src="selectedImagePreview" alt="Selected recipe image preview" class="size-full object-cover">
        </div>
        <div v-else-if="currentRecipe" class="overflow-hidden rounded-md border bg-muted">
          <RecipeImage :recipe="currentRecipe" />
        </div>
        <div v-else class="grid aspect-[4/3] place-items-center rounded-md border border-dashed bg-muted text-center text-sm text-muted-foreground">
          Image preview after save
        </div>

        <div class="grid gap-3">
          <FormField id="recipe-image" label="Image file" optional :error="fieldErrors.image">
            <input id="recipe-image" ref="imageInput" accept="image/jpeg,image/png,image/webp" class="min-h-11 w-full rounded-md border border-dashed bg-background p-2 text-sm text-muted-foreground" name="image" type="file" @change="onImageChange">
          </FormField>
          <p v-if="selectedImage" class="text-sm text-muted-foreground">
            Selected: {{ selectedImage.name }}
          </p>
          <div v-if="selectedImage" class="flex flex-wrap gap-2">
            <UiButton type="button" size="sm" variant="outline" :disabled="Boolean(pendingAction)" @click="clearSelectedImage()">Clear selection</UiButton>
            <UiButton v-if="imageUploadFailed" type="button" size="sm" :disabled="Boolean(pendingAction)" @click="retryImageUpload">{{ pendingAction === 'save' ? 'Uploading...' : 'Retry image upload' }}</UiButton>
          </div>
          <DestructiveConfirm
            v-if="isEdit && hasRecipeImage"
            v-model:open="removeImageDialogOpen"
            confirm-label="Remove image"
            description="The current recipe image will be permanently removed. You can upload a replacement after saving."
            :error="destructiveError"
            :pending="pendingAction === 'remove-image'"
            title="Remove this image?"
            @confirm="removeImage"
          >
            <template #trigger><UiButton type="button" variant="outline" :disabled="Boolean(pendingAction)" @click="destructiveError = null">Remove image</UiButton></template>
          </DestructiveConfirm>
        </div>
      </div>
    </section>

    <section id="recipe-categories" class="scroll-mt-36 rounded-md border bg-card p-5 sm:p-6" aria-labelledby="recipe-categories-title">
      <div class="mb-5">
        <h2 id="recipe-categories-title" class="section-heading">
          Categories
        </h2>
        <p class="section-description">
          Attach the recipe to the public shelves where it belongs.
        </p>
      </div>

      <div v-if="categories.length > 0" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <label
          v-for="category in categories"
          :key="category.slug"
          class="flex min-h-11 items-center gap-3 rounded-md border bg-background px-3 py-2 text-sm font-medium"
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

    <section id="recipe-ingredients" class="scroll-mt-36 rounded-md border bg-card p-5 sm:p-6" aria-labelledby="recipe-ingredients-title">
      <div class="mb-5">
        <h2 id="recipe-ingredients-title" class="section-heading">
          Measured ingredients
        </h2>
        <p class="section-description">
          Keep ingredients ordered and dosed so the method stays easy to scan.
        </p>
      </div>

      <FormAlert v-if="fieldErrors.ingredients" :message="fieldErrors.ingredients" tone="error" />

      <div class="grid gap-3">
        <div
          v-for="(recipeIngredient, index) in form.ingredients"
          :key="`ingredient-${index}`"
          class="grid gap-3 rounded-md border bg-background p-3 md:grid-cols-2 xl:grid-cols-[minmax(170px,1fr)_100px_120px_minmax(150px,1fr)_132px] xl:items-center"
        >
          <select v-model="recipeIngredient.ingredientSlug" class="control" :aria-label="`Ingredient ${index + 1}`">
            <option value="">
              Choose ingredient
            </option>
            <option v-for="ingredient in ingredients" :key="ingredient.slug" :value="ingredient.slug">
              {{ ingredient.name }}
            </option>
          </select>
          <UiInput v-model="recipeIngredient.quantity" :aria-label="`Quantity ${index + 1}`" min="0" step="0.01" type="number" />
          <select v-model="recipeIngredient.unit" class="control" :aria-label="`Unit ${index + 1}`">
            <option v-for="unit in ingredientUnitOptions" :key="unit.value" :value="unit.value">
              {{ unit.label }}
            </option>
          </select>
          <UiInput v-model="recipeIngredient.note" :aria-label="`Ingredient note ${index + 1}`" type="text" />
          <div class="flex items-center justify-end gap-1 md:col-span-2 xl:col-span-1">
            <UiButton type="button" variant="outline" size="icon" :disabled="index === 0" :aria-label="`Move ingredient ${index + 1} up`" @click="moveIngredient(index, -1)"><HugeiconsIcon :icon="ArrowUp01Icon" :size="17" :stroke-width="1.75" aria-hidden="true" /></UiButton>
            <UiButton type="button" variant="outline" size="icon" :disabled="index === form.ingredients.length - 1" :aria-label="`Move ingredient ${index + 1} down`" @click="moveIngredient(index, 1)"><HugeiconsIcon :icon="ArrowDown01Icon" :size="17" :stroke-width="1.75" aria-hidden="true" /></UiButton>
            <UiButton type="button" variant="ghost" size="icon" :aria-label="`Remove ingredient ${index + 1}`" @click="removeIngredient(index)"><HugeiconsIcon :icon="Delete02Icon" :size="17" :stroke-width="1.75" aria-hidden="true" /></UiButton>
          </div>
        </div>
      </div>

      <UiButton class="mt-4" type="button" variant="outline" @click="addIngredient">
        Add ingredient
      </UiButton>
    </section>

    <section id="recipe-steps" class="scroll-mt-36 rounded-md border bg-card p-5 sm:p-6" aria-labelledby="recipe-steps-title">
      <div class="mb-5">
        <h2 id="recipe-steps-title" class="section-heading">
          Preparation steps
        </h2>
        <p class="section-description">
          Add each instruction in the order readers should follow.
        </p>
      </div>

      <FormAlert v-if="fieldErrors.steps" :message="fieldErrors.steps" tone="error" />

      <div class="grid gap-3">
        <div
          v-for="(step, index) in form.steps"
          :key="`step-${index}`"
          class="grid gap-3 rounded-md border bg-background p-3 md:grid-cols-[2rem_minmax(0,1fr)_auto]"
        >
          <span class="pt-2 text-sm font-semibold text-muted-foreground">
            {{ index + 1 }}.
          </span>
          <UiTextarea v-model="step.instruction" :aria-label="`Step ${index + 1}`" class="min-h-24" rows="3" />
          <div class="flex items-start gap-1">
            <UiButton type="button" variant="outline" size="icon" :disabled="index === 0" :aria-label="`Move step ${index + 1} up`" @click="moveStep(index, -1)"><HugeiconsIcon :icon="ArrowUp01Icon" :size="17" :stroke-width="1.75" aria-hidden="true" /></UiButton>
            <UiButton type="button" variant="outline" size="icon" :disabled="index === form.steps.length - 1" :aria-label="`Move step ${index + 1} down`" @click="moveStep(index, 1)"><HugeiconsIcon :icon="ArrowDown01Icon" :size="17" :stroke-width="1.75" aria-hidden="true" /></UiButton>
            <UiButton type="button" variant="ghost" size="icon" :aria-label="`Remove step ${index + 1}`" @click="removeStep(index)"><HugeiconsIcon :icon="Delete02Icon" :size="17" :stroke-width="1.75" aria-hidden="true" /></UiButton>
          </div>
        </div>
      </div>

      <UiButton class="mt-4" type="button" variant="outline" @click="addStep">
        Add step
      </UiButton>
    </section>

    <section id="recipe-publication" class="scroll-mt-36 rounded-md border bg-card p-5 sm:p-6" aria-labelledby="recipe-publication-title">
      <h2 id="recipe-publication-title" class="section-heading">Publication and final review</h2>
      <p class="section-description">Saving keeps the recipe private as a draft. Publishing happens only after all recipe content and any selected image have saved successfully.</p>
      <div class="mt-5 flex flex-wrap items-center gap-3">
      <UiButton type="submit" :disabled="Boolean(pendingAction)">
        {{ pendingAction === 'save' ? 'Saving...' : isEdit ? 'Save changes' : 'Save draft' }}
      </UiButton>
      <UiButton v-if="!isEdit || canPublish" type="button" variant="secondary" :disabled="Boolean(pendingAction)" @click="saveRecipe('publish')">
        {{ pendingAction === 'publish' ? 'Publishing...' : isEdit ? 'Save and publish' : 'Create and publish' }}
      </UiButton>
      <UiButton v-if="canArchive" type="button" variant="outline" :disabled="Boolean(pendingAction)" @click="archiveRecipe">
        {{ pendingAction === 'archive' ? 'Archiving...' : 'Archive recipe' }}
      </UiButton>
      <DestructiveConfirm
        v-if="isEdit"
        v-model:open="deleteDialogOpen"
        confirm-label="Delete recipe"
        :description="`“${currentRecipe?.title ?? 'This recipe'}” will be removed from public and personal collections. This cannot be undone.`"
        :error="destructiveError"
        :pending="pendingAction === 'delete'"
        title="Delete this recipe?"
        @confirm="deleteRecipe"
      >
        <template #trigger><UiButton type="button" variant="destructive" :disabled="Boolean(pendingAction)" @click="destructiveError = null">Delete recipe</UiButton></template>
      </DestructiveConfirm>
      <UiButton v-if="currentRecipe" as-child type="button" variant="ghost">
        <NuxtLink :to="`/recipes/${currentRecipe.slug}`">
          View public page
        </NuxtLink>
      </UiButton>
      </div>
    </section>

      </div>
    </div>
  </form>
</template>
