<script setup lang="ts">
import { toFormErrors } from '../../utils/api-errors'

const api = useApi()
const auth = useAuth()

const loading = ref(true)
const loadError = ref<string | null>(null)

const profileForm = reactive({
  bio: '',
  birthDate: '',
  username: ''
})
const profileFieldErrors = ref<Record<string, string>>({})
const profileError = ref<string | null>(null)
const profileSuccess = ref<string | null>(null)
const profilePending = ref(false)

const avatarFieldErrors = ref<Record<string, string>>({})
const avatarError = ref<string | null>(null)
const avatarSuccess = ref<string | null>(null)
const avatarPending = ref(false)
const avatarFile = ref<File | null>(null)

const passwordForm = reactive({
  currentPassword: '',
  newPassword: ''
})
const passwordFieldErrors = ref<Record<string, string>>({})
const passwordError = ref<string | null>(null)
const passwordSuccess = ref<string | null>(null)
const passwordPending = ref(false)

const user = computed(() => auth.currentUser.value)
const avatarInitial = computed(() => user.value?.username.slice(0, 1).toUpperCase() ?? '?')

useSeoMeta({
  title: 'Account | What\'s In My Bar',
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
  profileSuccess.value = null

  try {
    const updatedUser = await api.account.update({
      bio: profileForm.bio || null,
      birthDate: profileForm.birthDate,
      username: profileForm.username
    })
    auth.setCurrentUser(updatedUser)
    profileSuccess.value = 'Your profile has been updated.'
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
  avatarSuccess.value = null

  try {
    const updatedUser = await api.account.avatar(avatarFile.value)
    auth.setCurrentUser(updatedUser)
    avatarFile.value = null
    avatarSuccess.value = 'Your avatar has been updated.'
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
  passwordSuccess.value = null

  try {
    await api.account.changePassword({
      currentPassword: passwordForm.currentPassword,
      newPassword: passwordForm.newPassword
    })
    passwordForm.currentPassword = ''
    passwordForm.newPassword = ''
    passwordSuccess.value = 'Your password has been updated.'
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
  avatarSuccess.value = null
}
</script>

<template>
  <main class="page-shell">
    <section aria-labelledby="account-title">
      <p class="eyebrow">
        Account
      </p>
      <h1 id="account-title" class="page-title">
        Your profile
      </h1>
      <p class="page-copy">
        Keep your public profile and account security up to date.
      </p>
    </section>

    <section v-if="loading" class="content-panel loading-panel" aria-live="polite">
      Loading your account...
    </section>

    <section v-else-if="loadError" class="content-panel loading-panel" aria-live="polite">
      {{ loadError }}
    </section>

    <section v-else-if="user" class="account-grid" aria-label="Account settings">
      <aside class="content-panel profile-card" aria-label="Current profile">
        <div class="avatar-preview" aria-hidden="true">
          <img v-if="user.avatarPath" :alt="`${user.username} avatar`" :src="user.avatarPath">
          <span v-else>{{ avatarInitial }}</span>
        </div>

        <h2 class="profile-name">
          {{ user.username }}
        </h2>
        <p class="profile-meta">
          {{ user.email }}
        </p>
        <p class="profile-meta">
          Born {{ user.birthDate }}
        </p>
        <p v-if="user.bio" class="profile-bio">
          {{ user.bio }}
        </p>

        <div class="profile-actions">
          <NuxtLink class="button button-secondary button-full" :to="`/users/${user.username}`">
            View public profile
          </NuxtLink>
          <NuxtLink class="button button-danger button-full" to="/logout">
            Log out
          </NuxtLink>
        </div>
      </aside>

      <div class="account-sections">
        <section class="content-panel settings-section" aria-labelledby="profile-settings-title">
          <h2 id="profile-settings-title" class="section-title">
            Profile details
          </h2>
          <p class="section-copy">
            Your username and bio appear on your public profile.
          </p>

          <form class="form-stack" novalidate @submit.prevent="submitProfile">
            <FormAlert v-if="profileError" :message="profileError" tone="error" />
            <FormAlert v-if="profileSuccess" :message="profileSuccess" tone="success" />

            <FormField id="account-username" v-slot="field" label="Public username" :error="profileFieldErrors.username">
              <input
                id="account-username"
                v-model="profileForm.username"
                v-bind="field"
                autocomplete="username"
                class="field-input"
                name="username"
                required
                type="text"
              >
            </FormField>

            <FormField id="account-birth-date" v-slot="field" label="Birth date" :error="profileFieldErrors.birthDate">
              <input
                id="account-birth-date"
                v-model="profileForm.birthDate"
                v-bind="field"
                class="field-input"
                name="birthDate"
                required
                type="date"
              >
            </FormField>

            <FormField id="account-bio" v-slot="field" label="Bio" optional :error="profileFieldErrors.bio">
              <textarea
                id="account-bio"
                v-model="profileForm.bio"
                v-bind="field"
                class="field-textarea"
                name="bio"
                rows="4"
              />
            </FormField>

            <div class="inline-actions">
              <button class="button button-primary" :disabled="profilePending" type="submit">
                {{ profilePending ? 'Saving...' : 'Save profile' }}
              </button>
            </div>
          </form>
        </section>

        <section class="content-panel settings-section" aria-labelledby="avatar-settings-title">
          <h2 id="avatar-settings-title" class="section-title">
            Avatar
          </h2>
          <p class="section-copy">
            Upload a square image for the cleanest crop.
          </p>

          <form class="form-stack" novalidate @submit.prevent="submitAvatar">
            <FormAlert v-if="avatarError" :message="avatarError" tone="error" />
            <FormAlert v-if="avatarSuccess" :message="avatarSuccess" tone="success" />

            <FormField id="account-avatar" v-slot="field" label="Avatar image" :error="avatarFieldErrors.avatar">
              <input
                id="account-avatar"
                v-bind="field"
                accept="image/*"
                class="file-input"
                name="avatar"
                type="file"
                @change="onAvatarChange"
              >
            </FormField>

            <div class="inline-actions">
              <button class="button button-primary" :disabled="avatarPending" type="submit">
                {{ avatarPending ? 'Uploading...' : 'Upload avatar' }}
              </button>
            </div>
          </form>
        </section>

        <section class="content-panel settings-section" aria-labelledby="password-settings-title">
          <h2 id="password-settings-title" class="section-title">
            Password
          </h2>
          <p class="section-copy">
            Use at least 12 characters for your new password.
          </p>

          <form class="form-stack" novalidate @submit.prevent="submitPassword">
            <FormAlert v-if="passwordError" :message="passwordError" tone="error" />
            <FormAlert v-if="passwordSuccess" :message="passwordSuccess" tone="success" />

            <FormField
              id="account-current-password"
              v-slot="field"
              label="Current password"
              :error="passwordFieldErrors.currentPassword"
            >
              <input
                id="account-current-password"
                v-model="passwordForm.currentPassword"
                v-bind="field"
                autocomplete="current-password"
                class="field-input"
                name="currentPassword"
                required
                type="password"
              >
            </FormField>

            <FormField
              id="account-new-password"
              v-slot="field"
              label="New password"
              :error="passwordFieldErrors.newPassword"
            >
              <input
                id="account-new-password"
                v-model="passwordForm.newPassword"
                v-bind="field"
                autocomplete="new-password"
                class="field-input"
                name="newPassword"
                required
                type="password"
              >
            </FormField>

            <div class="inline-actions">
              <button class="button button-primary" :disabled="passwordPending" type="submit">
                {{ passwordPending ? 'Updating...' : 'Update password' }}
              </button>
            </div>
          </form>
        </section>
      </div>
    </section>
  </main>
</template>
