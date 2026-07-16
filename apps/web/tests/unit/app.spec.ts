import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import App from '../../app/app.vue'

describe('App', () => {
  it('renders the application name', () => {
    const wrapper = mount(App, {
      global: {
        stubs: {
          NuxtRouteAnnouncer: true
        }
      }
    })

    expect(wrapper.get('h1').text()).toBe('whatsinmybar')
  })
})
