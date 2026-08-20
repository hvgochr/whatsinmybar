<script setup lang="ts">
import AdminBadge from '../../components/admin/AdminBadge.vue'
import AdminPagination from '../../components/admin/AdminPagination.vue'
import AdminShell from '../../components/admin/AdminShell.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import FormAlert from '../../components/common/FormAlert.vue'
import { ApiRequestError } from '../../services/api-client'
import type { AdminUser } from '../../types/api'
import { adminPageFromQuery } from '../../utils/admin-pagination'

await useRequireAdmin()

const api = useApi()
const route = useRoute()
const page = computed(() => adminPageFromQuery(route.query))
const { data, pending, error } = await useAsyncData(`admin:users:${route.fullPath}`, () => api.admin.users.list({ page: page.value }), { watch: [() => route.fullPath] })
const users = ref<AdminUser[]>([])
const rowPending = ref<Record<number, boolean>>({})
const rowMessage = ref<Record<number, string>>({})
const rowError = ref<Record<number, string>>({})

watch(data, (nextData) => {
  users.value = nextData?.items ?? []
}, { immediate: true })

useSeoMeta({
  title: 'Admin users | What\'s In My Bar',
  description: 'Manage What\'s In My Bar user roles and account state.'
})

async function updateUser(user: AdminUser, payload: Partial<AdminUser>) {
  rowPending.value[user.id] = true
  rowMessage.value[user.id] = ''
  rowError.value[user.id] = ''

  try {
    const updatedUser = await api.admin.users.update(user.id, payload)
    users.value = users.value.map(currentUser => currentUser.id === user.id ? { ...currentUser, ...updatedUser } : currentUser)
    rowMessage.value[user.id] = 'User updated.'
  } catch (error: unknown) {
    rowError.value[user.id] = error instanceof ApiRequestError ? error.message : 'User could not be updated.'
  } finally {
    rowPending.value[user.id] = false
  }
}

function setAdminRole(user: AdminUser, event: Event) {
  const checked = (event.target as HTMLInputElement).checked

  return updateUser(user, {
    roles: checked ? ['ROLE_ADMIN'] : []
  })
}

function setDeleted(user: AdminUser, event: Event) {
  return updateUser(user, {
    deleted: (event.target as HTMLSelectElement).value === 'deleted'
  })
}
</script>

<template>
  <AdminShell
    current="users"
    description="Review account state and grant or remove administrator access."
    title="Users"
  >
    <div v-if="pending" class="loading-panel">
      Loading users...
    </div>

    <EmptyState
      v-else-if="error"
      action-label="Reload"
      action-to="/admin/users"
      description="Users are unavailable right now."
      title="Users could not be loaded"
    />

    <div v-else class="content-panel overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-border text-sm">
          <thead class="bg-muted/50 text-left text-xs font-black uppercase tracking-wide text-muted-foreground">
            <tr>
              <th class="px-4 py-3">
                User
              </th>
              <th class="px-4 py-3">
                Roles
              </th>
              <th class="px-4 py-3">
                State
              </th>
              <th class="px-4 py-3">
                Created
              </th>
              <th class="px-4 py-3">
                Feedback
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr v-for="user in users" :key="user.id" class="align-top">
              <td class="px-4 py-4">
                <p class="font-black text-foreground">
                  {{ user.username }}
                </p>
                <p class="mt-1 text-muted-foreground">
                  {{ user.email }}
                </p>
              </td>
              <td class="px-4 py-4">
                <label class="flex min-h-11 items-center gap-2 font-bold">
                  <input
                    class="size-4 accent-primary"
                    type="checkbox"
                    :checked="user.roles.includes('ROLE_ADMIN')"
                    :disabled="rowPending[user.id]"
                    @change="setAdminRole(user, $event)"
                  >
                  Admin
                </label>
                <div class="mt-2 flex flex-wrap gap-1">
                  <AdminBadge v-for="role in user.roles" :key="role" tone="muted">
                    {{ role }}
                  </AdminBadge>
                </div>
              </td>
              <td class="px-4 py-4">
                <select
                  class="min-h-11 rounded-lg border border-input bg-background px-3 py-2 text-foreground"
                  :disabled="rowPending[user.id]"
                  :value="user.deleted ? 'deleted' : 'active'"
                  @change="setDeleted(user, $event)"
                >
                  <option value="active">
                    Active
                  </option>
                  <option value="deleted">
                    Deleted
                  </option>
                </select>
              </td>
              <td class="px-4 py-4 text-muted-foreground">
                {{ new Date(user.createdAt).toLocaleDateString('en') }}
              </td>
              <td class="px-4 py-4">
                <FormAlert v-if="rowError[user.id]" :message="rowError[user.id] ?? ''" tone="error" />
                <FormAlert v-else-if="rowMessage[user.id]" :message="rowMessage[user.id] ?? ''" tone="success" />
                <span v-else-if="rowPending[user.id]" class="text-sm font-bold text-muted-foreground">Saving...</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="users.length === 0" class="p-5 text-muted-foreground">
        No users found.
      </p>
    </div>

    <AdminPagination
      v-if="!pending && !error && data"
      :page="data.page"
      path="/admin/users"
      :total-items="data.totalItems"
      :total-pages="data.totalPages"
    />
  </AdminShell>
</template>
