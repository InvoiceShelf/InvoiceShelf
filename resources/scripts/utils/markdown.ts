import { marked } from 'marked'
import DOMPurify from 'dompurify'

/**
 * Render a markdown string to safe, sanitized HTML.
 *
 * Used by the AI chat drawer to render assistant responses. Even though
 * the AI provider controls the immediate source of the content, the model
 * can echo anything it's fed — including user input from earlier in the
 * conversation or tool results from the database. We therefore parse
 * markdown → HTML via marked and then sanitize the result with DOMPurify
 * before handing it to Vue's v-html.
 *
 * Marked is configured with:
 *   - gfm: true      — GitHub-flavored markdown (tables, fenced code,
 *                      strikethrough, task lists). Matches what users
 *                      already expect from any modern chat UI.
 *   - breaks: true   — newlines become <br> so a single user-typed line
 *                      break renders as a visual break without needing
 *                      two trailing spaces.
 *   - async: false   — force synchronous parsing so the caller doesn't
 *                      have to await; marked defaults to returning a
 *                      Promise when extensions are registered.
 *
 * DOMPurify is run in its default browser profile which strips <script>,
 * event handlers, javascript: URLs, and every other HTML vector. We do
 * NOT customize ALLOWED_TAGS because marked's output is already a
 * conservative subset of HTML.
 */
export function renderMarkdown(source: string): string {
  if (!source) {
    return ''
  }

  const rawHtml = marked.parse(source, {
    gfm: true,
    breaks: true,
    async: false,
  }) as string

  return DOMPurify.sanitize(rawHtml)
}

/**
 * Sanitize a raw HTML string with DOMPurify's default browser profile
 * (strips <script>, event handlers, javascript: URLs, and every other HTML
 * vector). Use this for HTML that originates from the server, a third-party
 * module registry, or the update server before binding it via v-html — the
 * `BaseSanitizedHtml` component wraps this so feature code never touches
 * v-html directly.
 */
export function sanitizeHtml(html: string | null | undefined): string {
  if (!html) {
    return ''
  }

  return DOMPurify.sanitize(html)
}
