import { flushPromises, mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RecipeComments from '../../../app/components/social/RecipeComments.vue'
import type { Comment } from '../../../app/types/api'

const mocks = vi.hoisted(() => ({
  create: vi.fn(),
  delete: vi.fn(),
  notifySuccess: vi.fn(),
  update: vi.fn()
}))

mockNuxtImport('useApi', () => () => ({ comments: { create: mocks.create, delete: mocks.delete, update: mocks.update } }))
mockNuxtImport('useAuth', () => () => ({
  currentUser: { value: { roles: ['ROLE_USER'], username: 'jane_doe' } },
  isAuthenticated: { value: true }
}))
mockNuxtImport('useNotifications', () => () => ({ error: vi.fn(), success: mocks.notifySuccess }))

describe('RecipeComments mutation feedback', () => {
  beforeEach(() => vi.clearAllMocks())

  it('notifies after posting a comment and a reply', async () => {
    mocks.create.mockResolvedValueOnce(comment({ id: 2, message: 'New comment.' }))
      .mockResolvedValueOnce(comment({ id: 3, message: 'A reply.', parentId: 1 }))
    const wrapper = mountComments()

    await wrapper.get('#new-comment').setValue('New comment.')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    wrapper.getComponent({ name: 'CommentTreeItem' }).vm.$emit('reply', { message: 'A reply.', parentId: 1 })
    await flushPromises()

    expect(mocks.notifySuccess).toHaveBeenCalledWith('comment-create:negroni', 'Comment posted.')
    expect(mocks.notifySuccess).toHaveBeenCalledWith('comment-reply:1', 'Reply posted.')
  })

  it('notifies after editing and deleting a comment', async () => {
    mocks.update.mockResolvedValue(comment({ message: 'Updated.' }))
    mocks.delete.mockResolvedValue(comment({ deleted: true, message: null, moderationStatus: 'removed' }))
    const wrapper = mountComments()
    const item = wrapper.getComponent({ name: 'CommentTreeItem' })

    item.vm.$emit('update', { comment: comment(), message: 'Updated.' })
    await flushPromises()
    item.vm.$emit('requestDelete', comment())
    await wrapper.vm.$nextTick()
    await wrapper.get('[data-testid="confirm-delete"]').trigger('click')
    await flushPromises()

    expect(mocks.notifySuccess).toHaveBeenCalledWith('comment-update:1', 'Comment updated.')
    expect(mocks.notifySuccess).toHaveBeenCalledWith('comment-delete:1', 'Comment deleted.')
  })
})

function mountComments() {
  return mount(RecipeComments, {
    global: {
      stubs: {
        CommentTreeItem: {
          name: 'CommentTreeItem',
          props: ['node'],
          emits: ['reply', 'requestDelete', 'update'],
          template: '<article>{{ node.message }}</article>'
        },
        DestructiveConfirm: {
          emits: ['confirm'],
          template: '<button data-testid="confirm-delete" type="button" @click="$emit(\'confirm\')">Confirm</button>'
        }
      }
    },
    props: {
      comments: [comment()],
      nextTo: { path: '/recipes/negroni', query: { commentsPage: '2' } },
      pagination: {
        currentPage: 1,
        hasNextPage: false,
        hasPreviousPage: false,
        nextPage: 2,
        previousPage: 1,
        resultEnd: 1,
        resultStart: 1,
        totalItems: 1,
        totalPages: 1
      },
      previousTo: { path: '/recipes/negroni', query: {} },
      recipeSlug: 'negroni'
    }
  })
}

function comment(overrides: Partial<Comment> = {}): Comment {
  return {
    authorUsername: 'jane_doe',
    canReply: true,
    createdAt: '2026-01-01T00:00:00+00:00',
    deleted: false,
    depth: 1,
    id: 1,
    message: 'Original.',
    moderationStatus: 'visible',
    parentId: null,
    recipeSlug: 'negroni',
    replyCount: 0,
    updatedAt: '2026-01-01T00:00:00+00:00',
    ...overrides
  }
}
