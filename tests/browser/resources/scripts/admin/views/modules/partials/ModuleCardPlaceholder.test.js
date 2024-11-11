import { expect, test } from 'vitest'
import { render } from 'vitest-browser-vue'
import BaseContentPlaceholders from '@/scripts/components/base/BaseContentPlaceholders.vue'
import BaseContentPlaceholdersText from '@/scripts/components/base/BaseContentPlaceholdersText.vue'
import BaseContentPlaceholdersBox from '@/scripts/components/base/BaseContentPlaceholdersBox.vue'
import ModuleCardPlaceholder from '@/scripts/admin/views/modules/partials/ModuleCardPlaceholder.vue'

test('renders BaseContentPlaceholders with BaseContentPlaceholdersText using render function', async () => {
  const { getByTestId } = render(ModuleCardPlaceholder, {
    props: {
      'data-testid': 'modal-placeholder'
    },
    global: {
      components: {
        BaseContentPlaceholders,
        BaseContentPlaceholdersText,
        BaseContentPlaceholdersBox,
      },
    },
  })
  const modalPlaceholder = getByTestId('modal-placeholder')
  await expect.element(modalPlaceholder).toBeInTheDocument()
  expect(modalPlaceholder).toMatchSnapshot()
})


