<script setup lang="ts">
import AdminBadge from '../../components/admin/AdminBadge.vue'
import AdminListToolbar from '../../components/admin/AdminListToolbar.vue'
import AdminShell from '../../components/admin/AdminShell.vue'
import AdminTableShell from '../../components/admin/AdminTableShell.vue'
import DestructiveConfirm from '../../components/common/DestructiveConfirm.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import FormAlert from '../../components/common/FormAlert.vue'
import PaginationNav from '../../components/common/PaginationNav.vue'
import UiButton from '../../components/ui/button/Button.vue'
import { usePaginatedAdminList } from '../../composables/usePaginatedAdminList'
import { ApiRequestError } from '../../services/api-client'
import type { AdminUser } from '../../types/api'

definePageMeta({ layout: 'admin' })
await useRequireAdmin()

const api = useApi()
const notifications = useNotifications()
const { error, items: users, nextTo, pagination, pending, previousTo } = await usePaginatedAdminList<AdminUser>('admin:users', '/admin/users', api.admin.users.list)
const search = ref('')
const stateFilter = ref<'active' | 'all' | 'deleted'>('all')
const rowPending = ref<Record<number, boolean>>({})
const rowError = ref<Record<number, string>>({})
const deleteOpen = ref<Record<number, boolean>>({})
const filteredUsers = computed(() => {
  const query = search.value.trim().toLowerCase()
  return users.value.filter(user => {
    const matchesQuery = !query || `${user.username} ${user.email}`.toLowerCase().includes(query)
    const matchesState = stateFilter.value === 'all' || (stateFilter.value === 'deleted' ? user.deleted : !user.deleted)
    return matchesQuery && matchesState
  })
})

useSeoMeta({ title: 'Admin users | What\'s In My Bar', description: 'Manage user roles and account state.' })

async function updateUser(user: AdminUser, payload: Partial<AdminUser>) {
  rowPending.value[user.id] = true
  rowError.value[user.id] = ''
  try {
    const updatedUser = await api.admin.users.update(user.id, payload)
    users.value = users.value.map(current => current.id === user.id ? { ...current, ...updatedUser } : current)
    notifications.success(`admin-user:${user.id}`, payload.deleted === true ? 'User deleted.' : payload.deleted === false ? 'User restored.' : 'User updated.')
    if (payload.deleted) deleteOpen.value[user.id] = false
  } catch (caught: unknown) {
    rowError.value[user.id] = caught instanceof ApiRequestError ? caught.message : 'User could not be updated.'
  } finally {
    rowPending.value[user.id] = false
  }
}

function setAdminRole(user: AdminUser, event: Event) {
  return updateUser(user, { roles: (event.target as HTMLInputElement).checked ? ['ROLE_ADMIN'] : [] })
}
</script>

<template>
  <AdminShell current="users" description="Review account state and grant or remove administrator access." title="Users">
    <AdminListToolbar v-model:search="search" placeholder="Username or email">
      <label class="grid gap-2"><span class="field-label">Account state</span><select v-model="stateFilter" class="control min-w-40"><option value="all">All states</option><option value="active">Active</option><option value="deleted">Deleted</option></select></label>
    </AdminListToolbar>

    <div v-if="pending" class="grid min-h-48 place-items-center rounded-md border bg-card text-sm text-muted-foreground">Loading users...</div>
    <EmptyState v-else-if="error" action-label="Reload" action-to="/admin/users" description="Users are unavailable right now." title="Users could not be loaded" />

    <AdminTableShell v-else :empty="filteredUsers.length === 0" empty-message="No users match the current filters on this page." label="Users">
      <thead><tr><th>User</th><th>Roles</th><th>State</th><th>Created</th><th class="text-right">Actions</th></tr></thead>
      <tbody>
        <tr v-for="user in filteredUsers" :key="user.id">
          <td><p class="font-medium">{{ user.username }}</p><p class="mt-1 text-muted-foreground">{{ user.email }}</p></td>
          <td>
            <label class="flex min-h-10 items-center gap-2 font-medium"><input class="size-4 accent-primary" type="checkbox" :checked="user.roles.includes('ROLE_ADMIN')" :disabled="rowPending[user.id]" @change="setAdminRole(user, $event)">Admin</label>
            <div class="mt-1 flex flex-wrap gap-1"><AdminBadge v-for="role in user.roles" :key="role" tone="muted">{{ role }}</AdminBadge></div>
          </td>
          <td><AdminBadge :tone="user.deleted ? 'danger' : 'success'">{{ user.deleted ? 'deleted' : 'active' }}</AdminBadge></td>
          <td class="text-muted-foreground">{{ new Date(user.createdAt).toLocaleDateString('en') }}</td>
          <td>
            <div class="flex justify-end gap-2">
              <UiButton v-if="user.deleted" size="sm" variant="outline" :disabled="rowPending[user.id]" @click="updateUser(user, { deleted: false })">Restore</UiButton>
              <DestructiveConfirm v-else v-model:open="deleteOpen[user.id]" confirm-label="Delete user" :description="`The account “${user.username}” will be disabled and signed out on all devices.`" :error="rowError[user.id]" :pending="rowPending[user.id]" title="Delete this user?" @confirm="updateUser(user, { deleted: true })">
                <template #trigger><UiButton size="sm" variant="outline">Delete</UiButton></template>
              </DestructiveConfirm>
            </div>
            <FormAlert v-if="rowError[user.id] && !deleteOpen[user.id]" class="mt-2" :message="rowError[user.id] ?? ''" tone="error" />
          </td>
        </tr>
      </tbody>
    </AdminTableShell>

    <PaginationNav v-if="!pending && !error" aria-label="User list pagination" :next-to="nextTo" :pagination="pagination" :previous-to="previousTo" />
  </AdminShell>
</template>
