import { flushPromises, mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { afterEach, describe, expect, it, vi } from 'vitest'
import DestructiveConfirm from '../../../app/components/common/DestructiveConfirm.vue'
import ReportAction from '../../../app/components/social/ReportAction.vue'

mockNuxtImport('useAuth', () => () => ({ currentUser: { value: { username: 'jane' } }, isAuthenticated: { value: true } }))
mockNuxtImport('useApi', () => () => ({ reports: { create: vi.fn() } }))

afterEach(() => { document.body.innerHTML = '' })

describe('bounded action dialogs', () => {
  it('opens a report form in a dialog', async () => {
    const wrapper = mount(ReportAction, { attachTo: document.body, props: { targetId: 1, targetType: 'recipe' } })
    const trigger = wrapper.get('button[aria-label="Report this recipe"]')
    await trigger.trigger('click')
    await vi.waitFor(() => expect(document.body.querySelector('[role="dialog"]')?.textContent).toContain('Report this recipe'))
    document.body.querySelector<HTMLButtonElement>('[data-slot="dialog-close"]')?.click()
    await flushPromises()
    expect(document.activeElement).toBe(trigger.element)
    wrapper.unmount()
  })

  it('opens destructive confirmation in an alert dialog', async () => {
    const wrapper = mount(DestructiveConfirm, {
      attachTo: document.body,
      props: { confirmLabel: 'Delete recipe', description: 'This cannot be undone.', title: 'Delete this recipe?' },
      slots: { trigger: '<button type="button">Delete recipe</button>' }
    })
    const trigger = wrapper.get('button')
    await trigger.trigger('click')
    await vi.waitFor(() => expect(document.body.querySelector('[role="alertdialog"]')?.textContent).toContain('Delete this recipe?'))
    Array.from(document.body.querySelectorAll('button')).find(button => button.textContent === 'Cancel')?.click()
    await flushPromises()
    expect(document.activeElement).toBe(trigger.element)
    wrapper.unmount()
  })
})
