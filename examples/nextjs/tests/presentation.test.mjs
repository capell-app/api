import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import test from 'node:test'
const require = createRequire(import.meta.url)
const { createElement } = require('react')
const { renderToStaticMarkup } = require('react-dom/server')
const { PageContent } =
    await import('../.test-build/app/components/PageContent.js')
const { pagePath, loadDirectory } = await import('../.test-build/lib/pages.js')

test('structured API content remains escaped while published HTML retains its markup', () => {
    const structured = renderToStaticMarkup(
        createElement(PageContent, {
            content: { text: '<script>steal()</script>', value: '&private' },
        }),
    )
    assert.ok(structured.includes('&lt;script&gt;steal()&lt;/script&gt;'))
    assert.ok(!structured.includes('<script>'))
    assert.ok(structured.includes('Structured page content'))
    const html = renderToStaticMarkup(
        createElement(PageContent, {
            content: '<p>Published <strong>guidance</strong>.</p>',
        }),
    )
    assert.ok(html.includes('<p>Published <strong>guidance</strong>.</p>'))
})

test('an empty API body explains the page state without printing null or empty JSON', () => {
    for (const content of [null, '', '  ', {}, []]) {
        const output = renderToStaticMarkup(
            createElement(PageContent, { content }),
        )
        assert.ok(output.includes('This page has no published body yet.'))
        assert.ok(!output.includes('<pre'))
    }
})

test('directory links stay on the Next site and preserve genuine CMS paths', () => {
    for (const path of [
        '//outside.test',
        '/\\outside.test',
        '/%5coutside.test',
        '/%2foutside.test',
        '/hello\nworld',
        'https://outside.test',
        '/?view=directory',
        '/#escape',
    ]) {
        assert.equal(pagePath(path), null, path)
    }
    assert.equal(pagePath('/'), '/')
    assert.equal(pagePath('/articles/a-real-page'), '/articles/a-real-page')
    assert.equal(pagePath('/caf%C3%A9'), '/caf%C3%A9')
    assert.equal(pagePath('/a%20real%20page'), '/a%20real%20page')
    assert.equal(pagePath('/a%3Fb%23c'), '/a%3Fb%23c')
})

test('the complete directory follows API cursors and refuses a repeating cursor instead of hanging', async () => {
    const seen = []
    const navigation = async ({ cursor }) => {
        seen.push(cursor)
        return cursor
            ? {
                  data: [{ url: '/second', title: 'Second' }],
                  meta: { next_cursor: null },
              }
            : {
                  data: [{ url: '/', title: 'Home' }],
                  meta: { next_cursor: 'next-page' },
              }
    }
    assert.deepEqual(
        (await loadDirectory({ navigation })).map((page) => page.url),
        ['/', '/second'],
    )
    assert.deepEqual(seen, [undefined, 'next-page'])
    await assert.rejects(
        loadDirectory({
            navigation: async () => ({
                data: [],
                meta: { next_cursor: 'loop' },
            }),
        }),
        /repeated/,
    )
})

test('the rendered unavailable state never discloses upstream errors or private origins', async () => {
    const { default: ErrorPage } = await import('../.test-build/app/error.js')
    const secret = 'https://private.invalid /private/app/vendor secret-api-key'
    const output = renderToStaticMarkup(
        createElement(ErrorPage, { error: new Error(secret), reset() {} }),
    )
    assert.ok(output.includes('We could not load this page'))
    assert.ok(output.includes('Try again'))
    assert.ok(!output.includes(secret))
    assert.ok(!output.includes('private.invalid'))
})
