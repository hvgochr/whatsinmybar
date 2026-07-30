import type { User } from '../types/api'

export type AdminSessionUser = Readonly<Omit<User, 'roles'> & { roles: readonly string[] }>

export async function useRequireAdmin(): Promise<AdminSessionUser> {
  const auth = useAuth()
  const user = auth.currentUser.value ?? await auth.restoreSession()

  if (!user) {
    throw createError({
      statusCode: 401,
      statusMessage: 'Authentication required'
    })
  }

  if (!user.roles.includes('ROLE_ADMIN')) {
    throw createError({
      statusCode: 403,
      statusMessage: 'Admin access required'
    })
  }

  return user
}
