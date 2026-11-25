import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import { fileURLToPath } from 'node:url'

// Uses the monorepo's existing browser tooling; no runtime dependency is added.
const require = createRequire(
    new URL('../../../../../package.json', import.meta.url),
)
const { chromium } = require('playwright')
const origin = process.env.EXAMPLE_ORIGIN
const apiOrigin = process.env.CAPELL_ORIGIN
const articlePath = process.env.EXAMPLE_PAGE_PATH
assert.ok(
    origin && apiOrigin && articlePath,
    'Set EXAMPLE_ORIGIN, CAPELL_ORIGIN and EXAMPLE_PAGE_PATH to an isolated installed site.',
)
const { CapellClient } = await import(
    fileURLToPath(new URL('../../../sdk/dist/index.js', import.meta.url))
)
const client = new CapellClient({ origin: apiOrigin })
const browser = await chromium.launch({ headless: true })
const errors = []
try {
    const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
    })
    const page = await context.newPage()
    page.setDefaultTimeout(15000)
    page.setDefaultNavigationTimeout(60000)
    page.on('pageerror', (error) => errors.push(error.message))
    const responsive = async () => {
        for (const width of [320, 768, 1024, 1440]) {
            await page.setViewportSize({ width, height: 900 })
            assert.ok(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
                `No horizontal overflow at ${width}px on ${page.url()}`,
            )
            assert.ok(
                await page
                    .getByRole('navigation', { name: 'Main' })
                    .isVisible(),
            )
        }
    }
    const home = await client.page('/', { signal: AbortSignal.timeout(15000) })
    await page.goto(origin, { waitUntil: 'networkidle' })
    await page
        .getByRole('heading', { level: 1, name: home.data.title, exact: true })
        .waitFor()
    await page.keyboard.press('Tab')
    assert.equal(await page.locator(':focus').textContent(), 'Skip to content')
    await responsive()
    const browse = page
        .getByRole('navigation', { name: 'Main' })
        .getByRole('link', { name: 'Browse pages', exact: true })
    await browse.click()
    assert.equal(new URL(page.url()).searchParams.get('view'), 'directory')
    await page
        .getByRole('heading', {
            level: 1,
            name: 'Browse published pages',
            exact: true,
        })
        .waitFor()
    const links = page.locator('[data-page-directory] a')
    assert.ok(
        (await links.count()) > 0,
        'The real API directory must have published pages.',
    )
    const urls = await links.evaluateAll((nodes) =>
        nodes.map((node) => node.getAttribute('href')),
    )
    assert.ok(
        urls.includes(articlePath),
        'The seeded published page must be reachable from the directory.',
    )
    await responsive()
    const article = await client.page(articlePath, {
        signal: AbortSignal.timeout(15000),
    })
    await links.nth(urls.indexOf(articlePath)).click()
    await page.waitForURL(new URL(articlePath, origin).href)
    await page
        .getByRole('heading', {
            level: 1,
            name: article.data.title,
            exact: true,
        })
        .waitFor()
    if (typeof article.data.content === 'string') {
        const expected = await page.evaluate(
            (html) =>
                new DOMParser().parseFromString(html, 'text/html').body
                    .textContent,
            article.data.content,
        )
        assert.equal(
            (await page.locator('[data-page-content]').textContent())
                .replace(/\s+/g, ' ')
                .trim(),
            expected.replace(/\s+/g, ' ').trim(),
        )
    }
    await responsive()
    const missing = await page.goto(
        new URL('/capell-example-missing-page-7e3ad', origin).href,
        { waitUntil: 'networkidle' },
    )
    assert.equal(missing.status(), 404, 'Missing pages must retain HTTP 404.')
    await page
        .getByRole('heading', { level: 1, name: 'Page not found', exact: true })
        .waitFor()
    assert.ok(
        (await page
            .getByRole('link', { name: 'Browse pages', exact: true })
            .count()) > 0,
    )
    assert.deepEqual(errors, [])
    console.log(
        'Installed browser proof passed: real home, directory, article HTML, four widths and missing page.',
    )
} finally {
    await browser.close()
}
