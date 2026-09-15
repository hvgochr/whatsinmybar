import { describe, expect, it } from 'vitest'
import type { Comment, User } from '../../../app/types/api'
import { buildCommentTree, canManageComment, reportReasonOptions } from '../../../app/utils/social'

describe('social helpers', () => {
  it('builds sorted nested comment trees', () => {
    const comments = [
      comment({ id: 3, message: 'Second root', parentId: null, createdAt: '2026-07-20T12:03:00+00:00' }),
      comment({ id: 2, message: 'Reply', parentId: 1, createdAt: '2026-07-20T12:02:00+00:00' }),
      comment({ id: 1, message: 'First root', parentId: null, createdAt: '2026-07-20T12:01:00+00:00' }),
      comment({ id: 4, message: 'Nested reply', parentId: 2, createdAt: '2026-07-20T12:04:00+00:00' })
    ]

    expect(buildCommentTree(comments)).toMatchObject([
      {
        id: 1,
        replies: [
          {
            id: 2,
            replies: [
              { id: 4 }
            ]
          }
        ]
      },
      { id: 3, replies: [] }
    ])
  })

  it('allows comment authors and admins to manage comments', () => {
    const target = comment({ authorUsername: 'jane' })
    const author = user({ roles: ['ROLE_USER'], username: 'jane' })
    const admin = user({ roles: ['ROLE_ADMIN'], username: 'admin' })
    const other = user({ roles: ['ROLE_USER'], username: 'other' })

    expect(canManageComment(target, author)).toBe(true)
    expect(canManageComment(target, admin)).toBe(true)
    expect(canManageComment(target, other)).toBe(false)
    expect(canManageComment(target, null)).toBe(false)
  })

  it('keeps report reasons aligned with the public reporting contract', () => {
    expect(reportReasonOptions.map(option => option.value)).toEqual([
      'spam',
      'abuse',
      'illegal_content',
      'wrong_alcohol_classification',
      'copyright',
      'other'
    ])
  })
})

function comment(overrides: Partial<Comment>): Comment {
  return {
    authorUsername: 'jane',
    canReply: true,
    createdAt: '2026-07-20T12:00:00+00:00',
    deleted: false,
    depth: 1,
    id: 1,
    message: 'Comment',
    moderationStatus: 'visible',
    parentId: null,
    parentContext: null,
    recipeSlug: 'recipe',
    replyCount: 0,
    updatedAt: '2026-07-20T12:00:00+00:00',
    ...overrides
  }
}

function user(overrides: Partial<User>): User {
  return {
    avatarPath: null,
    bio: null,
    birthDate: '1990-01-01',
    createdAt: '2026-07-20T12:00:00+00:00',
    email: 'user@example.com',
    id: 1,
    roles: ['ROLE_USER'],
    updatedAt: '2026-07-20T12:00:00+00:00',
    username: 'jane',
    ...overrides
  }
}
