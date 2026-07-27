import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import App from '../../app/app.vue'

describe('App', () => {
  it('renders the application shell navigation', () => {
    const wrapper = mount(App, {
      global: {
        stubs: {
          NuxtLink: {
            props: ['to'],
            template: '<a><slot /></a>'
          },
          NuxtPage: true,
          NuxtRouteAnnouncer: true
        }
      }
    })

    expect(wrapper.get('.wordmark').text()).toBe("What's In My Bar")
    expect(wrapper.text()).toContain('Log in')
    expect(wrapper.text()).toContain('Join')
  })
})
