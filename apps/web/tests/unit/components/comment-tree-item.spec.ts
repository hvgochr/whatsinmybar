import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CommentTreeItem from '../../../app/components/social/CommentTreeItem.vue'
import type { CommentTreeNode, SocialUser } from '../../../app/utils/social'

const viewer: SocialUser = {
  avatarPath: '/uploads/avatars/jane.png',
  bio: null,
  birthDate: '1990-01-01',
  createdAt: '2026-01-01T00:00:00+00:00',
  email: 'jane@example.com',
  id: 1,
  roles: ['ROLE_USER'],
  updatedAt: '2026-01-01T00:00:00+00:00',
  username: 'jane_doe'
}

describe('CommentTreeItem', () => {
  it('links available authors, renders avatars, and groups actions in order', () => {
    const wrapper = mountComment({ authorAvatarPath: '/uploads/avatars/jane.png' })

    expect(wrapper.get('a[aria-label="View jane_doe\'s profile"]').attributes('href')).toBe('/users/jane_doe')
    expect(wrapper.get('img').attributes('src')).toBe('/uploads/avatars/jane.png')
    expect(wrapper.get('img').attributes('alt')).toBe("jane_doe's avatar")
    expect(wrapper.get('[aria-label="Comment actions"]').findAll('button').map(button => button.text())).toEqual(['Reply', 'Edit', 'Delete', 'Report'])
  })

  it('uses initials without a profile link for unavailable comments', () => {
    const wrapper = mountComment({ deleted: true, message: null })

    expect(wrapper.find('a[aria-label*="profile"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('J')
    expect(wrapper.text()).toContain('This comment is no longer visible.')
    expect(wrapper.find('[aria-label="Comment actions"]').exists()).toBe(false)
  })

  it('applies the same author treatment to nested replies', () => {
    const wrapper = mountComment({
      replies: [comment({ id: 2, parentId: 1, authorUsername: 'a_very_long_member_name' })]
    })

    expect(wrapper.findAll('a[href="/users/a_very_long_member_name"]').map(link => link.text())).toContain('a_very_long_member_name')
    expect(wrapper.text()).toContain('A')
  })

  it('does not offer replies at the maximum nesting depth', () => {
    const wrapper = mountComment({ canReply: false, depth: 3 })

    expect(wrapper.get('[aria-label="Comment actions"]').findAll('button').map(button => button.text())).toEqual(['Edit', 'Delete', 'Report'])
  })
})

function mountComment(overrides: Partial<CommentTreeNode> = {}) {
  return mount(CommentTreeItem, {
    global: {
      stubs: {
        NuxtLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
        ReportAction: { template: '<button type="button">Report</button>' }
      }
    },
    props: {
      currentUser: viewer,
      node: comment(overrides),
      pendingActionId: null
    }
  })
}

function comment(overrides: Partial<CommentTreeNode> = {}): CommentTreeNode {
  return {
    authorUsername: 'jane_doe',
    canReply: true,
    createdAt: '2026-01-01T00:00:00+00:00',
    deleted: false,
    depth: 1,
    id: 1,
    message: 'A useful note.',
    moderationStatus: 'visible',
    parentId: null,
    recipeSlug: 'negroni',
    replies: [],
    replyCount: 0,
    updatedAt: '2026-01-01T00:00:00+00:00',
    ...overrides
  }
}
