import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import UserAvatar from '../../../app/components/common/UserAvatar.vue'

describe('UserAvatar', () => {
  it('falls back to the member initial when an avatar is broken', async () => {
    const wrapper = mount(UserAvatar, {
      props: { path: '/uploads/avatars/jane.png', username: 'jane_doe' }
    })

    expect(wrapper.get('img').attributes('width')).toBe('512')
    await wrapper.get('img').trigger('error')
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.text()).toBe('J')
  })

  it('resets the fallback when the image path changes', async () => {
    const wrapper = mount(UserAvatar, {
      props: { path: '/uploads/avatars/old.png', username: 'jane_doe' }
    })

    await wrapper.get('img').trigger('error')
    await wrapper.setProps({ path: '/uploads/avatars/new.png' })
    expect(wrapper.get('img').attributes('src')).toBe('/uploads/avatars/new.png')
  })
})
