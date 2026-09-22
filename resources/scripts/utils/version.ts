/**
 * Just enough semver to run the client's version gate in both directions.
 *
 * Ordering follows the semver spec where it matters here: a pre-release sorts
 * below the release it leads to, numeric pre-release identifiers compare as
 * numbers (so `alpha.4` < `alpha.10`) and a shorter identifier list sorts
 * first. Build metadata is ignored, as the spec requires.
 */

const VERSION_PATTERN = /^v?(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:-([0-9A-Za-z.-]+))?(?:\+[0-9A-Za-z.-]+)?$/

export interface ParsedVersion {
  major: number
  minor: number
  patch: number
  /** Pre-release identifiers, empty for a final release. */
  pre: string[]
}

/**
 * Read a version string. Anything unparseable answers 0.0.0, which fails
 * every "new enough" test rather than passing one by accident.
 */
export function parseVersion(value: string): ParsedVersion {
  const match = VERSION_PATTERN.exec(String(value ?? '').trim())

  if (match === null) {
    return { major: 0, minor: 0, patch: 0, pre: [] }
  }

  return {
    major: Number(match[1]),
    minor: Number(match[2] ?? 0),
    patch: Number(match[3] ?? 0),
    pre: match[4] === undefined ? [] : match[4].split('.'),
  }
}

/** The major of a version, which is what decides whether two builds talk. */
export function majorOf(value: string): number {
  return parseVersion(value).major
}

/** -1, 0 or 1: `a` older than, equal to, or newer than `b`. */
export function compareVersions(a: string, b: string): number {
  const left = parseVersion(a)
  const right = parseVersion(b)

  const numeric: Array<keyof Pick<ParsedVersion, 'major' | 'minor' | 'patch'>> = ['major', 'minor', 'patch']

  for (const part of numeric) {
    if (left[part] !== right[part]) {
      return left[part] < right[part] ? -1 : 1
    }
  }

  return comparePreRelease(left.pre, right.pre)
}

/** True when `version` is `minimum` or newer. */
export function isAtLeast(version: string, minimum: string): boolean {
  return compareVersions(version, minimum) >= 0
}

function comparePreRelease(a: string[], b: string[]): number {
  if (a.length === 0 && b.length === 0) {
    return 0
  }

  // A release outranks any pre-release of the same numbers.
  if (a.length === 0) {
    return 1
  }

  if (b.length === 0) {
    return -1
  }

  const length = Math.max(a.length, b.length)

  for (let index = 0; index < length; index += 1) {
    const left = a[index]
    const right = b[index]

    if (left === undefined) {
      return -1
    }

    if (right === undefined) {
      return 1
    }

    const leftIsNumber = /^\d+$/.test(left)
    const rightIsNumber = /^\d+$/.test(right)

    if (leftIsNumber && rightIsNumber) {
      const difference = Number(left) - Number(right)

      if (difference !== 0) {
        return difference < 0 ? -1 : 1
      }

      continue
    }

    // Numeric identifiers always sort below alphanumeric ones.
    if (leftIsNumber !== rightIsNumber) {
      return leftIsNumber ? -1 : 1
    }

    if (left !== right) {
      return left < right ? -1 : 1
    }
  }

  return 0
}
