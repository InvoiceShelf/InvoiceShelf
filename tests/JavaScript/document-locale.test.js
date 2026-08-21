import assert from 'node:assert/strict'
import test from 'node:test'
import {
  getDocumentDirection,
  syncDocumentLocale,
} from '../../resources/scripts/helpers/document-locale.js'

test('returns RTL direction for supported RTL locales', () => {
  assert.equal(getDocumentDirection('ar'), 'rtl')
  assert.equal(getDocumentDirection('ar_LY'), 'rtl')
  assert.equal(getDocumentDirection('fa'), 'rtl')
})

test('returns LTR direction for other locales', () => {
  assert.equal(getDocumentDirection('en'), 'ltr')
  assert.equal(getDocumentDirection('zh_CN'), 'ltr')
})

test('synchronizes document language and direction', () => {
  const documentElement = {}

  syncDocumentLocale('ar_LY', documentElement)

  assert.deepEqual(documentElement, {
    dir: 'rtl',
    lang: 'ar-LY',
  })
})
