import { h } from 'vue'
import { expect, test } from 'vitest'
import { render } from 'vitest-browser-vue'
import BaseContentPlaceholders from '@/scripts/components/base/BaseContentPlaceholders.vue'
import BaseContentPlaceholdersText from '@/scripts/components/base/BaseContentPlaceholdersText.vue'
import BaseContentPlaceholdersBox from '@/scripts/components/base/BaseContentPlaceholdersBox.vue'

test('BaseContentPlaceholders animated with BaseContentPlaceholdersText', async () => {
  const { getByTestId } = render(BaseContentPlaceholders, {
      props: { animated: true, 'data-testid': 'base-placeholder', 'class': 'flex flex-col gap-y-4' },
      slots: {
        default: () => [
          h(
            BaseContentPlaceholdersText,
            {
              'data-testid': 'text-placeholder'
            }
          ),
          h(
            BaseContentPlaceholdersBox,
            {
              rounded: true,
              'class': 'w-full h-24'
            }
          ),
          h(
            BaseContentPlaceholdersBox,
            {
              circle: true,
              'class': 'w-5 h-5'
            }
          )
        ],
      }
  })
  const basePlaceholder = getByTestId('base-placeholder')
  await expect.element(basePlaceholder).toBeInTheDocument()
  await expect.element(basePlaceholder).toBeVisible()

  await expect.element(basePlaceholder).toHaveClass('base-content-placeholders-is-animated')

  expect(basePlaceholder).toMatchSnapshot()
})

