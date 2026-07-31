import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import UiInput from '../../../app/components/ui/input/Input.vue'
import UiTextarea from '../../../app/components/ui/textarea/Textarea.vue'

describe('ui form controls', () => {
  it('renders default values for uncontrolled inputs', () => {
    const wrapper = mount(UiInput, {
      attrs: {
        defaultValue: 'Classics',
        name: 'name'
      }
    })

    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Classics')
  })

  it('renders default values for uncontrolled textareas', () => {
    const wrapper = mount(UiTextarea, {
      attrs: {
        defaultValue: 'Timeless cocktail recipes.',
        name: 'description'
      }
    })

    expect((wrapper.find('textarea').element as HTMLTextAreaElement).value).toBe('Timeless cocktail recipes.')
  })

  it('keeps v-model input updates working', async () => {
    const updates: string[] = []
    const wrapper = mount(UiInput, {
      props: {
        modelValue: 'Classics',
        'onUpdate:modelValue': (value: string) => updates.push(value)
      }
    })

    await wrapper.find('input').setValue('Zero Proof')

    expect(updates).toEqual(['Zero Proof'])
  })
})
