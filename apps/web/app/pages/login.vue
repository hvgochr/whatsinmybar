<script setup lang="ts">
import UiButton from '../components/ui/button/Button.vue'
import UiInput from '../components/ui/input/Input.vue'
import { toFormErrors } from '../utils/api-errors'

const auth = useAuth()
const route = useRoute()

const form = reactive({
  email: '',
  password: ''
})
const fieldErrors = ref<Record<string, string>>({})
const formError = ref<string | null>(null)
const pending = ref(false)
const loginRedirect = computed(() => safeRedirect(route.query.redirect))

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
    await navigateTo(loginRedirect.value)
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    fieldErrors.value = formErrors.fields
    formError.value = formErrors.message
  } finally {
    pending.value = false
  }
}

function safeRedirect(value: unknown): string {
  const redirect = Array.isArray(value) ? value[0] : value

  return typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')
    ? redirect
    : '/settings'
}
</script>

<template>
  <main class="page-main grid min-h-[calc(100vh-14rem)] place-items-center">
    <section class="w-full max-w-md" aria-labelledby="login-title">
      <div class="mb-7 text-center">
        <h1 id="login-title" class="text-3xl font-semibold tracking-tight">Log in</h1>
        <p class="mt-2 text-sm text-muted-foreground">Use the email address attached to your account.</p>
      </div>
      <div class="rounded-md border bg-card p-6 sm:p-7">
        <form class="grid gap-5" novalidate @submit.prevent="submitLogin">
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

        <p class="mt-6 text-center text-sm text-muted-foreground">
          New here?
          <NuxtLink class="font-medium text-foreground underline-offset-4 hover:underline" to="/register">
            Create an account
          </NuxtLink>
        </p>
      </div>
    </section>
  </main>
</template>
