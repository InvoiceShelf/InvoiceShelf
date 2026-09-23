#!/usr/bin/env node
/**
 * Turns a release tag into the two version fields an Android build needs.
 *
 *   node mobile/scripts/version-code.mjs 3.0.0-alpha.4
 *   versionCode=3000004
 *   versionName=3.0.0-alpha.4
 *
 * Written for `>> "$GITHUB_OUTPUT"`, but the format is plain enough to read in a
 * log or to eval in a shell.
 *
 * `versionName` is the tag with any leading `v` removed: the app version is the
 * server tag it was built from, and there is nothing to invent.
 *
 * `versionCode` is the awkward half. Play refuses an upload whose code is not
 * strictly greater than the last one, it is an integer with no notion of
 * pre-release ordering, and it is permanent: a code burned on a bad build is
 * gone. So it is computed, never typed, and the arithmetic has to make the
 * release sequence ascending on its own:
 *
 *   major * 1_000_000 + minor * 10_000 + patch * 100 + stage + n
 *
 * The last two digits are a patch's pre-release runway, split into bands that
 * cannot overlap:
 *
 *   alpha  0 + n   ->  0..29
 *   beta  30 + n   -> 30..59
 *   rc    60 + n   -> 60..89
 *   final 99       -> 99
 *
 * which is the whole reason `n` stops at 29. A 31st alpha would land on
 * `beta.1`, so it is refused here rather than silently shipped as one. The same
 * logic caps minor and patch at 99: `3.1.0` must not collide with `3.0.100`.
 *
 *   3.0.0-alpha.4 -> 3000004
 *   3.0.0-alpha.5 -> 3000005
 *   3.0.0-beta.1  -> 3000031
 *   3.0.0-rc.1    -> 3000061
 *   3.0.0         -> 3000099
 *   3.0.1         -> 3000199
 *
 * Every rejection is loud and fatal. The alternative is a workflow that invents
 * a plausible-looking code for a tag nobody meant to publish, and then that code
 * is spent.
 */

/** Pre-release words we accept, and the band each one opens. */
const STAGES = { alpha: 0, beta: 30, rc: 60 }

/** The band a tag with no pre-release suffix gets. `n` is 0 by definition. */
const FINAL_STAGE = 99

/** Highest `n` that still fits inside a 30-wide band. */
const MAX_N = 29

/**
 * Play rejects anything above 2_100_000_000, and major is multiplied by a
 * million, so the ceiling lands here. Far away, but a typo'd tag like
 * `30000.0.0` should say why rather than be refused by the store two steps
 * later.
 */
const MAX_MAJOR = 2099

const TAG_RE = /^v?(\d+)\.(\d+)\.(\d+)(?:-([a-z]+)\.(\d+))?$/

function fail(message) {
  console.error(`version-code: ${message}`)
  process.exit(1)
}

export function versionsFromTag(tag) {
  if (typeof tag !== 'string' || tag.trim() === '') {
    fail('no tag given. Usage: node mobile/scripts/version-code.mjs <tag>')
  }

  const versionName = tag.trim().replace(/^v/, '')
  const match = TAG_RE.exec(tag.trim())

  if (!match) {
    fail(
      `cannot parse "${tag}". Expected MAJOR.MINOR.PATCH, optionally ` +
        `-alpha.N, -beta.N or -rc.N, with an optional leading "v".`
    )
  }

  const [, majorRaw, minorRaw, patchRaw, word, nRaw] = match
  const major = Number(majorRaw)
  const minor = Number(minorRaw)
  const patch = Number(patchRaw)

  if (major > MAX_MAJOR) {
    fail(`major ${major} in "${tag}" exceeds ${MAX_MAJOR}; the versionCode would pass Play's 2100000000 ceiling.`)
  }
  if (minor > 99) {
    fail(`minor ${minor} in "${tag}" exceeds 99; the versionCode would carry into the major digits.`)
  }
  if (patch > 99) {
    fail(`patch ${patch} in "${tag}" exceeds 99; the versionCode would carry into the minor digits.`)
  }

  let stage = FINAL_STAGE
  let n = 0

  if (word !== undefined) {
    if (!(word in STAGES)) {
      fail(`unknown pre-release stage "${word}" in "${tag}". Known stages: ${Object.keys(STAGES).join(', ')}.`)
    }
    stage = STAGES[word]
    n = Number(nRaw)
    if (n > MAX_N) {
      fail(`${word}.${n} in "${tag}" exceeds ${word}.${MAX_N}; a higher n would collide with the next stage's band.`)
    }
  }

  const versionCode = major * 1_000_000 + minor * 10_000 + patch * 100 + stage + n

  return { versionCode, versionName }
}

const { versionCode, versionName } = versionsFromTag(process.argv[2])
console.log(`versionCode=${versionCode}`)
console.log(`versionName=${versionName}`)
