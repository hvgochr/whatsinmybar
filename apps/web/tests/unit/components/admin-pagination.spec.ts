import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AdminPagination from '../../../app/components/admin/AdminPagination.vue'

describe('AdminPagination', () => {
  it('shows total context and previous and next destinations', () => {
    const wrapper = mountPagination({ page: 3, totalItems: 42, totalPages: 5 })
    const links = wrapper.findAllComponents({ name: 'NuxtLink' })

    expect(wrapper.text()).toContain('42 results · Page 3 of 5')
    expect(links).toHaveLength(2)
    expect(links[0]?.props('to')).toEqual({ path: '/admin/users', query: { page: '2' } })
    expect(links[1]?.props('to')).toEqual({ path: '/admin/users', query: { page: '4' } })
  })

  it('lets an administrator navigate back from an out-of-range page', () => {
    const wrapper = mountPagination({ page: 6, totalItems: 42, totalPages: 5 })
    const links = wrapper.findAllComponents({ name: 'NuxtLink' })

    expect(wrapper.text()).toContain('42 results · Page 6 of 5')
    expect(links).toHaveLength(1)
    expect(links[0]?.text()).toBe('Previous')
    expect(links[0]?.props('to')).toEqual({ path: '/admin/users', query: { page: '5' } })
  })
})

function mountPagination(props: { page: number, totalItems: number, totalPages: number }) {
  return mount(AdminPagination, {
    props: {
      ...props,
      path: '/admin/users'
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
