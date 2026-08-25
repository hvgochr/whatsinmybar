import type {
  Category,
  IngredientUnit,
  RecipeAggregatePayload,
  RecipeIngredient,
  RecipeIngredientPayload,
  RecipeResource,
  RecipeStep,
  RecipeStepPayload
} from '../types/api'
import { categorySlug, ingredientSlug } from './public-content'

export interface RecipeStepFormRow {
  id?: number
  instruction: string
}

export interface RecipeIngredientFormRow {
  id?: number
  ingredientSlug: string
  note: string
  quantity: string
  unit: IngredientUnit
}

export interface RecipeFormState {
  categories: string[]
  description: string
  difficulty: 'easy' | 'medium' | 'hard'
  ingredients: RecipeIngredientFormRow[]
  preparationTimeMinutes: number
  servings: number
  steps: RecipeStepFormRow[]
  title: string
}

export const ingredientUnitOptions: Array<{ label: string, value: IngredientUnit }> = [
  { label: 'ml', value: 'ml' },
  { label: 'cl', value: 'cl' },
  { label: 'oz', value: 'oz' },
  { label: 'dash', value: 'dash' },
  { label: 'bar spoon', value: 'bar_spoon' },
  { label: 'tsp', value: 'tsp' },
  { label: 'tbsp', value: 'tbsp' },
  { label: 'drop', value: 'drop' },
  { label: 'piece', value: 'piece' },
  { label: 'slice', value: 'slice' },
  { label: 'wedge', value: 'wedge' },
  { label: 'leaf', value: 'leaf' },
  { label: 'sprig', value: 'sprig' },
  { label: 'pinch', value: 'pinch' },
  { label: 'to taste', value: 'to_taste' }
]

export function createEmptyRecipeForm(): RecipeFormState {
  return {
    categories: [],
    description: '',
    difficulty: 'easy',
    ingredients: [createEmptyIngredientRow()],
    preparationTimeMinutes: 5,
    servings: 1,
    steps: [createEmptyStepRow()],
    title: ''
  }
}

export function createEmptyStepRow(): RecipeStepFormRow {
  return {
    instruction: ''
  }
}

export function createEmptyIngredientRow(): RecipeIngredientFormRow {
  return {
    ingredientSlug: '',
    note: '',
    quantity: '',
    unit: 'ml'
  }
}

export function recipeToForm(recipe: RecipeResource): RecipeFormState {
  return {
    categories: (recipe.categories ?? []).map(categorySlug),
    description: recipe.description ?? '',
    difficulty: difficultyToFormValue(recipe.difficulty),
    ingredients: normalizeRecipeIngredients(recipe.recipeIngredients),
    preparationTimeMinutes: recipe.preparationTimeMinutes ?? 5,
    servings: recipe.servings ?? 1,
    steps: normalizeRecipeSteps(recipe.steps),
    title: recipe.title
  }
}

export function buildRecipePayload(form: RecipeFormState): RecipeAggregatePayload {
  return {
    categories: form.categories.map(categoryIri),
    description: form.description.trim(),
    difficulty: form.difficulty,
    ingredients: form.ingredients
      .filter(row => row.ingredientSlug && row.quantity !== '')
      .map(row => ({
        ingredient: ingredientIri(row.ingredientSlug),
        note: row.note.trim() || null,
        quantity: row.quantity,
        unit: row.unit
      })),
    preparationTimeMinutes: Number(form.preparationTimeMinutes),
    servings: Number(form.servings),
    steps: form.steps
      .map(row => row.instruction.trim())
      .filter(Boolean)
      .map(instruction => ({ instruction })),
    title: form.title.trim()
  }
}

export function buildStepPayloads(form: RecipeFormState, recipeSlug: string): RecipeStepPayload[] {
  return form.steps
    .map(row => row.instruction.trim())
    .filter(Boolean)
    .map((instruction, index) => ({
      instruction,
      position: index + 1,
      recipe: recipeIri(recipeSlug)
    }))
}

export function buildIngredientPayloads(form: RecipeFormState, recipeSlug: string): RecipeIngredientPayload[] {
  return form.ingredients
    .filter(row => row.ingredientSlug && row.quantity !== '')
    .map((row, index) => ({
      ingredient: ingredientIri(row.ingredientSlug),
      note: row.note.trim() || null,
      position: index + 1,
      quantity: row.quantity,
      recipe: recipeIri(recipeSlug),
      unit: row.unit
    }))
}

export function categoryIri(slug: string): string {
  return `/api/categories/${slug}`
}

export function ingredientIri(slug: string): string {
  return `/api/ingredients/${slug}`
}

export function recipeIri(slug: string): string {
  return `/api/recipes/${slug}`
}

export function categoryChecked(form: RecipeFormState, category: Category): boolean {
  return form.categories.includes(category.slug)
}

export function toggleCategory(form: RecipeFormState, category: Category, checked: boolean): void {
  form.categories = checked
    ? Array.from(new Set([...form.categories, category.slug]))
    : form.categories.filter(slug => slug !== category.slug)
}

function normalizeRecipeSteps(steps: RecipeStep[] | undefined): RecipeStepFormRow[] {
  const rows = [...(steps ?? [])]
    .sort((a, b) => a.position - b.position)
    .map(step => ({
      id: step.id,
      instruction: step.instruction
    }))

  return rows.length > 0 ? rows : [createEmptyStepRow()]
}

function normalizeRecipeIngredients(recipeIngredients: RecipeIngredient[] | undefined): RecipeIngredientFormRow[] {
  const rows = [...(recipeIngredients ?? [])]
    .sort((a, b) => a.position - b.position)
    .map(recipeIngredient => ({
      id: recipeIngredient.id,
      ingredientSlug: ingredientSlug(recipeIngredient.ingredient),
      note: recipeIngredient.note ?? '',
      quantity: recipeIngredient.quantity ?? '',
      unit: recipeIngredient.unit
    }))

  return rows.length > 0 ? rows : [createEmptyIngredientRow()]
}

function difficultyToFormValue(value: string | null): RecipeFormState['difficulty'] {
  return value === 'medium' || value === 'hard' ? value : 'easy'
}
