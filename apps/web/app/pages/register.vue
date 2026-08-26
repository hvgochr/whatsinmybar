<script setup lang="ts">
import UiButton from '../components/ui/button/Button.vue'
import UiInput from '../components/ui/input/Input.vue'
import UiTextarea from '../components/ui/textarea/Textarea.vue'
import { toFormErrors } from '../utils/api-errors'

const auth = useAuth()

const form = reactive({
  bio: '',
  birthDate: '',
  email: '',
  password: '',
  username: ''
})
const fieldErrors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)
const pending = ref(false)

useSeoMeta({
  title: 'Create account | What\'s In My Bar',
  description: 'Create your What\'s In My Bar profile.'
})

async function submitRegister() {
  if (pending.value) {
    return
  }

  pending.value = true
  fieldErrors.value = {}
  formError.value = null

  try {
    await auth.register({
      bio: form.bio || null,
      birthDate: form.birthDate,
      email: form.email,
      password: form.password,
      username: form.username
    })
    await auth.login({
      email: form.email,
      password: form.password
    })
    await navigateTo('/settings')
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    fieldErrors.value = formErrors.fields
    formError.value = formErrors.message
  } finally {
    pending.value = false
  }
}
</script>

<template>
  <main class="page-main grid place-items-center">
    <section class="w-full max-w-lg" aria-labelledby="register-title">
      <div class="mb-7 text-center">
        <h1 id="register-title" class="text-3xl font-semibold tracking-tight">Create an account</h1>
        <p class="mt-2 text-sm text-muted-foreground">Your birth date is required so the API can enforce alcohol visibility.</p>
      </div>
      <div class="rounded-md border bg-card p-6 sm:p-7">
        <form class="grid gap-5" novalidate @submit.prevent="submitRegister">
          <CommonFormAlert v-if="formError" :message="formError" tone="error" />

          <CommonFormField id="register-email" v-slot="field" label="Email" :error="fieldErrors.email">
            <UiInput
              id="register-email"
              v-model="form.email"
              v-bind="field"
              autocomplete="email"
              name="email"
              required
              type="email"
            />
          </CommonFormField>

          <CommonFormField
            id="register-username"
            v-slot="field"
            help="Letters, numbers, and underscores only."
            label="Public username"
            :error="fieldErrors.username"
          >
            <UiInput
              id="register-username"
              v-model="form.username"
              v-bind="field"
              autocomplete="username"
              name="username"
              required
              type="text"
            />
          </CommonFormField>

          <CommonFormField id="register-birth-date" v-slot="field" label="Birth date" :error="fieldErrors.birthDate">
            <UiInput
              id="register-birth-date"
              v-model="form.birthDate"
              v-bind="field"
              name="birthDate"
              required
              type="date"
            />
          </CommonFormField>

          <CommonFormField
            id="register-password"
            v-slot="field"
            help="Use at least 12 characters."
            label="Password"
            :error="fieldErrors.password"
          >
            <UiInput
              id="register-password"
              v-model="form.password"
              v-bind="field"
              autocomplete="new-password"
              name="password"
              required
              type="password"
            />
          </CommonFormField>

          <CommonFormField id="register-bio" v-slot="field" label="Bio" optional :error="fieldErrors.bio">
            <UiTextarea
              id="register-bio"
              v-model="form.bio"
              v-bind="field"
              name="bio"
              rows="4"
            />
          </CommonFormField>

          <UiButton class="w-full" :disabled="pending" type="submit">
            {{ pending ? 'Creating account...' : 'Create account' }}
          </UiButton>
        </form>

        <p class="mt-6 text-center text-sm text-muted-foreground">
          Already have an account?
          <NuxtLink class="font-medium text-foreground underline-offset-4 hover:underline" to="/login">
            Log in
          </NuxtLink>
        </p>
      </div>
    </section>
  </main>
</template>
