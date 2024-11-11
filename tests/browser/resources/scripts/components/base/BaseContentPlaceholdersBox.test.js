import { h } from 'vue'
import { expect, test } from 'vitest'
import { render } from 'vitest-browser-vue'
import BaseContentPlaceholders from '@/scripts/components/base/BaseContentPlaceholders.vue'
import BaseContentPlaceholdersBox from '@/scripts/components/base/BaseContentPlaceholdersBox.vue'

test('BaseContentPlaceholdersBox animated, rounded, circle', async () => {
  const { getByTestId } = render(BaseContentPlaceholders, {
    props: {
      animated: false,
      'data-testid': 'box-placeholder',
      'class': 'flex flex-col gap-y-4'
    },
    slots: {
      default: () => [
        h(
          BaseContentPlaceholdersBox,
          {
            rounded: true,
            animated: true,
            'class': 'w-full h-24'
          }
        ),
        h(
          BaseContentPlaceholdersBox,
          {
            circle: true,
            'class': 'w-5 h-5'
          }
        ),
        h(
          BaseContentPlaceholdersBox,
          {
            rounded: true,
            'class': 'w-full h-24'
          }
        ),

      ]
    }
  })
  const boxPlaceholder = getByTestId('box-placeholder')
  await expect.element(boxPlaceholder).toBeInTheDocument()
  expect(boxPlaceholder).toMatchSnapshot()
})
