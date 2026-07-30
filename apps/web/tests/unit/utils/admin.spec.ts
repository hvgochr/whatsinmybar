import { describe, expect, it } from 'vitest'
import type { AdminRecipe, AdminUser, Category, Ingredient, Report } from '../../../app/types/api'
import { adminDashboardStats, adminReportReasonLabel, adminStatusLabel, adminReportStatusOptions } from '../../../app/utils/admin'

describe('admin helpers', () => {
  it('summarizes admin dashboard counts', () => {
    expect(adminDashboardStats({
      categories: [category()],
      ingredients: [ingredient(), ingredient({ slug: 'lime' })],
      recipes: [recipe()],
      reports: [report({ status: 'open' }), report({ id: 2, status: 'resolved' })],
      users: [user(), user({ id: 2 })]
    })).toEqual([
      { label: 'Users', value: 2 },
      { label: 'Recipes', value: 1 },
      { label: 'Open reports', value: 1 },
      { label: 'Taxonomy', value: 3 }
    ])
  })

  it('labels report reasons and statuses', () => {
    expect(adminReportReasonLabel('wrong_alcohol_classification')).toBe('Wrong alcohol classification')
    expect(adminStatusLabel(adminReportStatusOptions, 'reviewing')).toBe('Reviewing')
  })
})

function user(overrides: Partial<AdminUser> = {}): AdminUser {
  return {
    avatarPath: null,
    bio: null,
    birthDate: '1990-01-01',
    createdAt: '2026-07-20T12:00:00+00:00',
    deleted: false,
    deletedAt: null,
    email: 'user@example.com',
    id: 1,
    roles: ['ROLE_USER'],
    updatedAt: '2026-07-20T12:00:00+00:00',
    username: 'jane',
    ...overrides
  }
}

function recipe(overrides: Partial<AdminRecipe> = {}): AdminRecipe {
  return {
    authorUsername: 'jane',
    containsAlcohol: true,
    createdAt: '2026-07-20T12:00:00+00:00',
    deleted: false,
    deletedAt: null,
    favoriteCount: 3,
    id: 1,
    moderationStatus: 'visible',
    publishedAt: '2026-07-20T12:00:00+00:00',
    slug: 'negroni',
    status: 'published',
    title: 'Negroni',
    updatedAt: '2026-07-20T12:00:00+00:00',
    ...overrides
  }
}

function category(overrides: Partial<Category> = {}): Category {
  return {
    description: null,
    name: 'Classics',
    slug: 'classics',
    ...overrides
  }
}

function ingredient(overrides: Partial<Ingredient> = {}): Ingredient {
  return {
    containsAlcohol: true,
    name: 'Gin',
    slug: 'gin',
    ...overrides
  }
}

function report(overrides: Partial<Report> = {}): Report {
  return {
    createdAt: '2026-07-20T12:00:00+00:00',
    id: 1,
    message: null,
    reason: 'spam',
    reporterUsername: 'jane',
    reviewedAt: null,
    reviewedByUsername: null,
    status: 'open',
    targetId: 1,
    targetType: 'recipe',
    updatedAt: '2026-07-20T12:00:00+00:00',
    ...overrides
  }
}
