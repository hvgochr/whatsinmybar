import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import App from '../../app/app.vue'
import Wordmark from '../../app/components/brand/Wordmark.vue'

describe('App', () => {
  it('renders the current page through the selected layout', () => {
    const wrapper = mount(App, {
      global: {
        stubs: {
          NuxtLayout: { template: '<div data-testid="layout"><slot /></div>' },
          NuxtPage: { template: '<main data-testid="page" />' },
          NuxtRouteAnnouncer: { template: '<div data-testid="announcer" />' },
          UiSonner: { template: '<div data-testid="toaster" />' }
        }
      }
    })

    expect(wrapper.get('[data-testid="layout"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="page"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="announcer"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="toaster"]').exists()).toBe(true)
  })

  it('renders the exact reusable SVG wordmark', () => {
    const wrapper = mount(Wordmark)

    expect(wrapper.get('svg').attributes('aria-label')).toBe('WhatsInMyBar')
    expect(wrapper.get('text').text()).toBe('WhatsInMyBar')
    expect(wrapper.get('text').attributes('fill')).toBe('currentColor')
  })
})
