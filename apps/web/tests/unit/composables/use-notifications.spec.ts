import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useNotifications } from '../../../app/composables/useNotifications'

const mocks = vi.hoisted(() => ({ error: vi.fn(), success: vi.fn() }))

vi.mock('vue-sonner', () => ({ toast: mocks }))

describe('useNotifications', () => {
  beforeEach(() => vi.clearAllMocks())

  it('uses stable ids so repeated mutation feedback is deduplicated', () => {
    const notifications = useNotifications()

    notifications.success('favorite:negroni', 'Added to favorites.')
    notifications.success('favorite:negroni', 'Removed from favorites.')
    notifications.error('favorite:negroni', 'Favorite could not be updated.')

    expect(mocks.success).toHaveBeenNthCalledWith(1, 'Added to favorites.', { id: 'favorite:negroni' })
    expect(mocks.success).toHaveBeenNthCalledWith(2, 'Removed from favorites.', { id: 'favorite:negroni' })
    expect(mocks.error).toHaveBeenCalledWith('Favorite could not be updated.', { id: 'favorite:negroni' })
  })
})
