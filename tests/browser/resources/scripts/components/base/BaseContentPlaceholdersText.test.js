import { expect, test } from 'vitest'
import { render } from 'vitest-browser-vue'
import BaseContentPlaceholdersText from '@/scripts/components/base/BaseContentPlaceholdersText.vue'

test('BaseContentPlaceholdersText four line animated rounded', async () => {
  const { getByTestId } = render(BaseContentPlaceholdersText, {
    props: {
      animated: true,
      rounded: true,
      'data-testid': 'text-placeholder'
    },
  })
  const textPlaceholder = getByTestId('text-placeholder')
  await expect.element(textPlaceholder).toBeInTheDocument()
  await expect.element(textPlaceholder).toHaveClass('base-content-placeholders-is-animated')
  await expect.element(textPlaceholder).toHaveClass('base-content-placeholders-is-rounded')

  expect(textPlaceholder).toMatchSnapshot()
})
