<script setup lang="ts">
import UiButton from '../components/ui/button/Button.vue'
import UiInput from '../components/ui/input/Input.vue'
import UiTextarea from '../components/ui/textarea/Textarea.vue'
import ThemeControl from '../components/navigation/ThemeControl.vue'
import { imageUrl } from '../utils/public-content'
import { toFormErrors } from '../utils/api-errors'
import { announceSessionChange } from '../services/session-events'

const api = useApi()
const auth = useAuth()
const notifications = useNotifications()
const runtimeConfig = useRuntimeConfig()

const loading = ref(true)
const loadError = ref<string | null>(null)

const profileForm = reactive({
  bio: '',
  birthDate: '',
  username: ''
})
const profileFieldErrors = ref<Record<string, string>>({})
const profileError = ref<string | null>(null)
const profilePending = ref(false)

const avatarFieldErrors = ref<Record<string, string>>({})
const avatarError = ref<string | null>(null)
const avatarPending = ref(false)
const avatarFile = ref<File | null>(null)
const avatarInput = ref<HTMLInputElement | null>(null)

const passwordForm = reactive({
  currentPassword: '',
  newPassword: ''
})
const passwordFieldErrors = ref<Record<string, string>>({})
const passwordError = ref<string | null>(null)
const passwordPending = ref(false)

const user = computed(() => auth.currentUser.value)
const avatarInitial = computed(() => user.value?.username.slice(0, 1).toUpperCase() ?? '?')
const avatarSrc = computed(() => imageUrl(user.value?.avatarPath, runtimeConfig.public.apiBaseUrl))

useSeoMeta({
  title: 'Settings | What\'s In My Bar',
  description: 'Manage your What\'s In My Bar profile and account security.'
})

watch(user, (currentUser) => {
  if (!currentUser) {
    return
  }

  profileForm.bio = currentUser.bio ?? ''
  profileForm.birthDate = currentUser.birthDate
  profileForm.username = currentUser.username
}, { immediate: true })

onMounted(async () => {
  try {
    const restoredUser = await auth.restoreSession()

    if (!restoredUser) {
      await navigateTo('/login', { replace: true })
      return
    }
  } catch {
    await navigateTo('/login', { replace: true })
    return
  } finally {
    loading.value = false
  }
})

async function submitProfile() {
  if (profilePending.value) {
    return
  }

  profilePending.value = true
  profileFieldErrors.value = {}
  profileError.value = null

  try {
    const updatedUser = await api.account.update({
      bio: profileForm.bio || null,
      birthDate: profileForm.birthDate,
      username: profileForm.username
    })
    auth.setCurrentUser(updatedUser)
    notifications.success('profile-updated', 'Profile updated.')
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    profileFieldErrors.value = formErrors.fields
    profileError.value = formErrors.message
  } finally {
    profilePending.value = false
  }
}

async function submitAvatar() {
  if (avatarPending.value || !avatarFile.value) {
    avatarFieldErrors.value = avatarFile.value ? {} : { avatar: 'Choose an image file.' }
    return
  }

  avatarPending.value = true
  avatarFieldErrors.value = {}
  avatarError.value = null

  try {
    const updatedUser = await api.account.avatar(avatarFile.value)
    auth.setCurrentUser(updatedUser)
    avatarFile.value = null
    if (avatarInput.value) avatarInput.value.value = ''
    notifications.success('avatar-updated', 'Avatar updated.')
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    avatarFieldErrors.value = formErrors.fields
    avatarError.value = formErrors.message
  } finally {
    avatarPending.value = false
  }
}

async function submitPassword() {
  if (passwordPending.value) {
    return
  }

  passwordPending.value = true
  passwordFieldErrors.value = {}
  passwordError.value = null

  try {
    await api.account.changePassword({
      currentPassword: passwordForm.currentPassword,
      newPassword: passwordForm.newPassword
    })
    passwordForm.currentPassword = ''
    passwordForm.newPassword = ''
    announceSessionChange('logout')
    notifications.success('password-updated', 'Password updated. Please log in again.')
    await navigateTo('/login', { replace: true })
  } catch (error: unknown) {
    const formErrors = toFormErrors(error)
    passwordFieldErrors.value = formErrors.fields
    passwordError.value = formErrors.message
  } finally {
    passwordPending.value = false
  }
}

function onAvatarChange(event: Event) {
  const input = event.target as HTMLInputElement
  avatarFile.value = input.files?.[0] ?? null
  avatarFieldErrors.value = {}
}
</script>

<template>
  <main class="page-main">
    <section aria-labelledby="account-title">
      <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">Account</p>
      <h1 id="account-title" class="page-heading">
        Settings
      </h1>
      <p class="page-lead">
        Keep your public profile and account security up to date.
      </p>
    </section>

    <section v-if="loading" class="mt-8 grid min-h-48 place-items-center rounded-md border text-sm text-muted-foreground" aria-live="polite">
      Loading your settings...
    </section>

    <section v-else-if="loadError" class="mt-8 grid min-h-48 place-items-center rounded-md border text-sm text-muted-foreground" aria-live="polite">
      {{ loadError }}
    </section>

    <section v-else-if="user" class="mt-10 grid items-start gap-8 lg:grid-cols-[17rem_minmax(0,1fr)]" aria-label="Account settings">
      <aside class="rounded-md border bg-card p-5 lg:sticky lg:top-24" aria-label="Current profile">
        <div class="grid size-20 place-items-center overflow-hidden rounded-full border bg-muted text-2xl font-semibold">
          <img v-if="avatarSrc" class="h-full w-full object-cover" :alt="`${user.username}'s avatar`" :src="avatarSrc">
          <span v-else>{{ avatarInitial }}</span>
        </div>

        <h2 class="mt-4 text-lg font-semibold">
          {{ user.username }}
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ user.email }}
        </p>
        <p class="mt-1 text-sm text-muted-foreground">
          Born {{ user.birthDate }}
        </p>
        <p v-if="user.bio" class="mt-4 text-sm leading-6">
          {{ user.bio }}
        </p>

        <div class="mt-6 grid gap-2">
          <UiButton as-child class="w-full" variant="outline">
            <NuxtLink :to="`/users/${user.username}`">
              View public profile
            </NuxtLink>
          </UiButton>
          <UiButton as-child class="w-full" variant="outline"><NuxtLink :to="`/users/${user.username}#my-recipes`">My recipes</NuxtLink></UiButton>
        </div>
      </aside>

      <div class="grid gap-6">
        <section id="appearance" class="scroll-mt-24 rounded-md border bg-card p-5 sm:p-6" aria-labelledby="appearance-settings-title">
          <h2 id="appearance-settings-title" class="section-heading">Appearance</h2>
          <p class="section-description mb-5">Choose a light or dark interface, or follow your device setting.</p>
          <ThemeControl inline notify />
        </section>

        <section class="rounded-md border bg-card p-5 sm:p-6" aria-labelledby="profile-settings-title">
          <h2 id="profile-settings-title" class="section-heading">
            Profile details
          </h2>
          <p class="section-description mb-5">
            Your username and bio appear on your public profile.
          </p>

          <form class="grid gap-5" novalidate @submit.prevent="submitProfile">
            <CommonFormAlert v-if="profileError" :message="profileError" tone="error" />

            <CommonFormField id="account-username" v-slot="field" label="Public username" :error="profileFieldErrors.username">
              <UiInput
                id="account-username"
                v-model="profileForm.username"
                v-bind="field"
                autocomplete="username"
                name="username"
                required
                type="text"
              />
            </CommonFormField>

            <CommonFormField id="account-birth-date" v-slot="field" label="Birth date" :error="profileFieldErrors.birthDate">
              <UiInput
                id="account-birth-date"
                v-model="profileForm.birthDate"
                v-bind="field"
                name="birthDate"
                required
                type="date"
              />
            </CommonFormField>

            <CommonFormField id="account-bio" v-slot="field" label="Bio" optional :error="profileFieldErrors.bio">
              <UiTextarea
                id="account-bio"
                v-model="profileForm.bio"
                v-bind="field"
                name="bio"
                rows="4"
              />
            </CommonFormField>

            <div class="flex flex-wrap gap-3">
              <UiButton :disabled="profilePending" type="submit">
                {{ profilePending ? 'Saving...' : 'Save profile' }}
              </UiButton>
            </div>
          </form>
        </section>

        <section class="rounded-md border bg-card p-5 sm:p-6" aria-labelledby="avatar-settings-title">
          <h2 id="avatar-settings-title" class="section-heading">
            Avatar
          </h2>
          <p class="section-description mb-5">
            Upload a square image for the cleanest crop.
          </p>

          <form class="grid gap-5" novalidate @submit.prevent="submitAvatar">
            <CommonFormAlert v-if="avatarError" :message="avatarError" tone="error" />

            <CommonFormField id="account-avatar" v-slot="field" label="Avatar image" :error="avatarFieldErrors.avatar">
              <input
                id="account-avatar"
                ref="avatarInput"
                v-bind="field"
                accept="image/*"
                class="min-h-11 w-full rounded-md border border-dashed bg-background p-2 text-sm text-muted-foreground"
                name="avatar"
                type="file"
                @change="onAvatarChange"
              >
            </CommonFormField>

            <div class="flex flex-wrap gap-3">
              <UiButton :disabled="avatarPending" type="submit">
                {{ avatarPending ? 'Uploading...' : 'Upload avatar' }}
              </UiButton>
            </div>
          </form>
        </section>

        <section class="rounded-md border bg-card p-5 sm:p-6" aria-labelledby="password-settings-title">
          <h2 id="password-settings-title" class="section-heading">
            Password
          </h2>
          <p class="section-description mb-5">
            Use at least 12 characters for your new password.
          </p>

          <form class="grid gap-5" novalidate @submit.prevent="submitPassword">
            <CommonFormAlert v-if="passwordError" :message="passwordError" tone="error" />

            <CommonFormField
              id="account-current-password"
              v-slot="field"
              label="Current password"
              :error="passwordFieldErrors.currentPassword"
            >
              <UiInput
                id="account-current-password"
                v-model="passwordForm.currentPassword"
                v-bind="field"
                autocomplete="current-password"
                name="currentPassword"
                required
                type="password"
              />
            </CommonFormField>

            <CommonFormField
              id="account-new-password"
              v-slot="field"
              label="New password"
              :error="passwordFieldErrors.newPassword"
            >
              <UiInput
                id="account-new-password"
                v-model="passwordForm.newPassword"
                v-bind="field"
                autocomplete="new-password"
                name="newPassword"
                required
                type="password"
              />
            </CommonFormField>

            <div class="flex flex-wrap gap-3">
              <UiButton :disabled="passwordPending" type="submit">
                {{ passwordPending ? 'Updating...' : 'Update password' }}
              </UiButton>
            </div>
          </form>
        </section>
      </div>
    </section>
  </main>
</template>
