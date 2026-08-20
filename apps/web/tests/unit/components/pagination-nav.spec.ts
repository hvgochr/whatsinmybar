import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import PaginationNav from '../../../app/components/common/PaginationNav.vue'
import type { PaginationState } from '../../../app/utils/pagination'

describe('PaginationNav', () => {
  it('renders result context and both navigation destinations', () => {
    const wrapper = mountPagination({
      currentPage: 3,
      hasNextPage: true,
      hasPreviousPage: true,
      nextPage: 4,
      previousPage: 2,
      resultEnd: 30,
      resultStart: 21,
      totalItems: 42,
      totalPages: 5
    })
    const links = wrapper.findAllComponents({ name: 'NuxtLink' })

    expect(wrapper.attributes('aria-label')).toBe('User list pagination')
    expect(wrapper.text()).toContain('Showing 21-30 of 42 · Page 3 of 5')
    expect(links).toHaveLength(2)
    expect(links[0]?.props('to')).toEqual({ path: '/admin/users', query: { page: '2' } })
    expect(links[1]?.props('to')).toEqual({ path: '/admin/users', query: { page: '4' } })
  })

  it('renders useful total context for an empty out-of-range page', () => {
    const wrapper = mountPagination({
      currentPage: 6,
      hasNextPage: false,
      hasPreviousPage: true,
      nextPage: 7,
      previousPage: 5,
      resultEnd: 0,
      resultStart: 0,
      totalItems: 42,
      totalPages: 5
    })

    expect(wrapper.text()).toContain('42 total results · Page 6 of 5')
    expect(wrapper.findAllComponents({ name: 'NuxtLink' })).toHaveLength(1)
  })
})

function mountPagination(pagination: PaginationState) {
  return mount(PaginationNav, {
    props: {
      ariaLabel: 'User list pagination',
      nextTo: { path: '/admin/users', query: { page: '4' } },
      pagination,
      previousTo: { path: '/admin/users', query: { page: '2' } }
    },
    global: {
      stubs: {
        NuxtLink: {
          name: 'NuxtLink',
          props: ['to'],
          template: '<a><slot /></a>'
        },
        UiButton: {
          props: ['disabled'],
          template: '<button :disabled="disabled"><slot /></button>'
        }
      }
    }
  })
}
