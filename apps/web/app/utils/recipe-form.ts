import type {
  Category,
  IngredientUnit,
  RecipeAggregatePayload,
  RecipeIngredient,
  RecipeResource,
  RecipeStep
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
  { label: 'l', value: 'l' },
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
      .filter(row => !ingredientRowIsEmpty(row))
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

export function ingredientRowIsEmpty(row: RecipeIngredientFormRow): boolean {
  return !row.ingredientSlug && !row.quantity.trim() && !row.note.trim()
}

export function ingredientRowError(row: RecipeIngredientFormRow): string | null {
  if (ingredientRowIsEmpty(row)) return null
  if (!row.ingredientSlug || !row.quantity.trim()) return 'Choose an ingredient and enter its quantity, or remove this row.'

  const quantity = Number(row.quantity)
  return Number.isFinite(quantity) && quantity > 0 ? null : 'Ingredient quantity must be greater than zero.'
}

export function categoryIri(slug: string): string {
  return `/api/categories/${slug}`
}

export function ingredientIri(slug: string): string {
  return `/api/ingredients/${slug}`
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
