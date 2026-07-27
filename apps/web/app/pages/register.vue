<script setup lang="ts">
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
    await navigateTo('/account')
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
  <main class="page-shell">
    <section class="auth-layout" aria-labelledby="register-title">
      <div class="auth-intro">
        <p class="eyebrow">
          Join the community
        </p>
        <h1 id="register-title" class="page-title">
          Create your cocktail profile
        </h1>
        <p class="page-copy">
          Your birth date is required so alcohol content stays properly restricted.
        </p>
      </div>

      <div class="auth-panel">
        <div class="panel-header">
          <h2 class="panel-title">
            New account
          </h2>
          <p class="panel-copy">
            Choose a public username and a secure password.
          </p>
        </div>

        <form class="form-stack" novalidate @submit.prevent="submitRegister">
          <CommonFormAlert v-if="formError" :message="formError" tone="error" />

          <CommonFormField id="register-email" v-slot="field" label="Email" :error="fieldErrors.email">
            <input
              id="register-email"
              v-model="form.email"
              v-bind="field"
              autocomplete="email"
              class="field-input"
              name="email"
              required
              type="email"
            >
          </CommonFormField>

          <CommonFormField
            id="register-username"
            v-slot="field"
            help="Letters, numbers, and underscores only."
            label="Public username"
            :error="fieldErrors.username"
          >
            <input
              id="register-username"
              v-model="form.username"
              v-bind="field"
              autocomplete="username"
              class="field-input"
              name="username"
              required
              type="text"
            >
          </CommonFormField>

          <CommonFormField id="register-birth-date" v-slot="field" label="Birth date" :error="fieldErrors.birthDate">
            <input
              id="register-birth-date"
              v-model="form.birthDate"
              v-bind="field"
              class="field-input"
              name="birthDate"
              required
              type="date"
            >
          </CommonFormField>

          <CommonFormField
            id="register-password"
            v-slot="field"
            help="Use at least 12 characters."
            label="Password"
            :error="fieldErrors.password"
          >
            <input
              id="register-password"
              v-model="form.password"
              v-bind="field"
              autocomplete="new-password"
              class="field-input"
              name="password"
              required
              type="password"
            >
          </CommonFormField>

          <CommonFormField id="register-bio" v-slot="field" label="Bio" optional :error="fieldErrors.bio">
            <textarea
              id="register-bio"
              v-model="form.bio"
              v-bind="field"
              class="field-textarea"
              name="bio"
              rows="4"
            />
          </CommonFormField>

          <button class="button button-primary button-full" :disabled="pending" type="submit">
            {{ pending ? 'Creating account...' : 'Create account' }}
          </button>
        </form>

        <p class="form-footer">
          Already have an account?
          <NuxtLink class="muted-link" to="/login">
            Log in
          </NuxtLink>
        </p>
      </div>
    </section>
  </main>
</template>
