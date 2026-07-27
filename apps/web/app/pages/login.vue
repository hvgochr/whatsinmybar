<script setup lang="ts">
import UiButton from '../components/ui/button/Button.vue'
import UiInput from '../components/ui/input/Input.vue'
import { toFormErrors } from '../utils/api-errors'

const auth = useAuth()

const form = reactive({
  email: '',
  password: ''
})
const fieldErrors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)
const pending = ref(false)

useSeoMeta({
  title: 'Log in | What\'s In My Bar',
  description: 'Log in to your What\'s In My Bar account.'
})

async function submitLogin() {
  if (pending.value) {
    return
  }

  pending.value = true
  fieldErrors.value = {}
  formError.value = null

  try {
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
    <section class="auth-layout" aria-labelledby="login-title">
      <div class="auth-intro">
        <p class="eyebrow">
          Welcome back
        </p>
        <h1 id="login-title" class="page-title">
          Log in to your bar
        </h1>
        <p class="page-copy">
          Keep your saved recipes, profile details, and cocktail notes within reach.
        </p>
      </div>

      <div class="auth-panel">
        <div class="panel-header">
          <h2 class="panel-title">
            Account access
          </h2>
          <p class="panel-copy">
            Use the email address attached to your account.
          </p>
        </div>

        <form class="form-stack" novalidate @submit.prevent="submitLogin">
          <CommonFormAlert v-if="formError" :message="formError" tone="error" />

          <CommonFormField id="login-email" v-slot="field" label="Email" :error="fieldErrors.email">
            <UiInput
              id="login-email"
              v-model="form.email"
              v-bind="field"
              autocomplete="email"
              name="email"
              required
              type="email"
            />
          </CommonFormField>

          <CommonFormField id="login-password" v-slot="field" label="Password" :error="fieldErrors.password">
            <UiInput
              id="login-password"
              v-model="form.password"
              v-bind="field"
              autocomplete="current-password"
              name="password"
              required
              type="password"
            />
          </CommonFormField>

          <UiButton class="w-full" :disabled="pending" type="submit">
            {{ pending ? 'Logging in...' : 'Log in' }}
          </UiButton>
        </form>

        <p class="form-footer">
          New here?
          <NuxtLink class="muted-link" to="/register">
            Create an account
          </NuxtLink>
        </p>
      </div>
    </section>
  </main>
</template>
