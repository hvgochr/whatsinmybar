import { flushPromises, mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RecipeComments from '../../../app/components/social/RecipeComments.vue'
import type { Comment } from '../../../app/types/api'

const mocks = vi.hoisted(() => ({
  create: vi.fn(),
  delete: vi.fn(),
  list: vi.fn(),
  notifySuccess: vi.fn(),
  update: vi.fn()
}))

mockNuxtImport('useApi', () => () => ({ comments: { create: mocks.create, delete: mocks.delete, list: mocks.list, update: mocks.update } }))
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
    mocks.list.mockResolvedValueOnce(commentPage([comment({ id: 2, message: 'New comment.' })], 2))
      .mockResolvedValueOnce(commentPage([comment({ id: 3, message: 'A reply.', parentId: 1 })], 3))
    const wrapper = mountComments()

    await wrapper.get('#new-comment').setValue('New comment.')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    wrapper.getComponent({ name: 'CommentTreeItem' }).vm.$emit('reply', { message: 'A reply.', parentId: 1 })
    await flushPromises()

    expect(mocks.notifySuccess).toHaveBeenCalledWith('comment-create:negroni', 'Comment posted.')
    expect(mocks.notifySuccess).toHaveBeenCalledWith('comment-reply:1', 'Reply posted.')
    expect(mocks.list).toHaveBeenNthCalledWith(1, 'negroni', { around: 2 })
    expect(mocks.list).toHaveBeenNthCalledWith(2, 'negroni', { around: 3 })
  })

  it('resynchronizes the collection and locates comment 21 on page 2', async () => {
    const existingComments = Array.from({ length: 20 }, (_, index) => comment({ id: index + 1, message: `Comment ${index + 1}` }))
    const created = comment({ id: 21, message: 'Comment 21' })
    const focusedPage = commentPage([created], 21, 2)
    mocks.create.mockResolvedValue(created)
    mocks.list.mockResolvedValue(focusedPage)
    const wrapper = mountComments({
      comments: existingComments,
      pagination: pagination({ totalItems: 20, totalPages: 1, resultEnd: 20 })
    })

    await wrapper.get('#new-comment').setValue('Comment 21')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(mocks.list).toHaveBeenCalledWith('negroni', { around: 21 })
    expect(wrapper.text()).toContain('21 comments')
    expect(wrapper.text()).toContain('Comment 21')
    expect(wrapper.emitted('resynced')).toEqual([[{ comment: created, page: focusedPage }]])
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

  it('separates a failed load from an empty conversation and retries', async () => {
    const wrapper = mountComments({ comments: [], loadFailed: true })

    expect(wrapper.text()).toContain('Comments could not be loaded.')
    expect(wrapper.text()).not.toContain('No public comments yet.')
    const retry = wrapper.findAll('button').find(button => button.text() === 'Retry comments')
    await retry!.trigger('click')
    expect(wrapper.emitted('retry')).toHaveLength(1)
  })
})

function mountComments(overrides: Record<string, unknown> = {}) {
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
        },
        PaginationNav: {
          props: ['pagination'],
          template: '<nav>{{ pagination.totalItems }}</nav>'
        }
      }
    },
    props: {
      comments: [comment()],
      nextTo: { path: '/recipes/negroni', query: { commentsPage: '2' } },
      pagination: pagination(),
      previousTo: { path: '/recipes/negroni', query: {} },
      recipeSlug: 'negroni',
      ...overrides
    }
  })
}

function pagination(overrides: Record<string, number | boolean | null> = {}) {
  return {
    currentPage: 1,
    hasNextPage: false,
    hasPreviousPage: false,
    nextPage: 2,
    previousPage: 1,
    resultEnd: 1,
    resultStart: 1,
    totalItems: 1,
    totalPages: 1,
    ...overrides
  }
}

function commentPage(items: Comment[], totalItems: number, page = 1) {
  return {
    items,
    page,
    pageSize: 20,
    totalItems,
    totalPages: Math.ceil(totalItems / 20)
  }
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
    parentContext: null,
    recipeSlug: 'negroni',
    replyCount: 0,
    updatedAt: '2026-01-01T00:00:00+00:00',
    ...overrides
  }
}
