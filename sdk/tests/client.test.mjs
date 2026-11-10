import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import test from 'node:test'
import { CapellClient, CapellApiError } from '../src/index.ts'

const fixtures = JSON.parse(
    await readFile(new URL('../fixtures/api-v1.json', import.meta.url), 'utf8'),
)

test('reads real API response fixtures with typed resource methods', async () => {
    const requests = []
    const client = new CapellClient({
        origin: 'https://example.com',
        fetch: async (url, init) => {
            requests.push([url, init])
            const key = url.pathname.endsWith('/pages/resolve')
                ? 'page'
                : url.pathname.split('/').at(-1)
            return Response.json(fixtures[key])
        },
    })
    assert.deepEqual(await client.page('/'), fixtures.page)
    assert.deepEqual(await client.pages(), fixtures.pages)
    assert.deepEqual(await client.navigation(), fixtures.navigation)
    assert.deepEqual(await client.media(), fixtures.media)
    assert.deepEqual(await client.search('Welcome'), fixtures.search)
    assert.equal(requests[0][0].searchParams.get('url'), '/')
    assert.equal(requests[0][1].cache, 'no-store')
    assert.equal(requests[0][1].redirect, 'error')
})

test('encodes filters and scopes preview tokens without trusting caller URLs', async () => {
    let seen
    const client = new CapellClient({
        origin: 'https://example.com',
        token: 'secret',
        fetch: async (url, init) => {
            seen = { url, init }
            return Response.json(fixtures.pages)
        },
    })
    await client.collection('article', {
        preview: true,
        filter: { path: '/hello & world' },
        limit: 2,
    })
    assert.equal(seen.url.searchParams.get('filter[path]'), '/hello & world')
    assert.equal(seen.url.searchParams.get('preview'), '1')
    assert.equal(seen.init.headers.Authorization, 'Bearer secret')
    assert.throws(() => client.collection('../private'), TypeError)
    assert.throws(() => client.page('//evil.example'), TypeError)
    assert.throws(
        () => new CapellClient({ origin: 'https://user:pass@example.com' }),
        TypeError,
    )
})

test('iterates cursor pages and handles failed API requests without leaking bodies', async () => {
    let count = 0
    const client = new CapellClient({
        origin: 'https://example.com',
        fetch: async () => {
            count++
            return Response.json({
                data: [fixtures.pages.data[count - 1]],
                meta: { next_cursor: count === 1 ? 'opaque' : null },
            })
        },
    })
    const pages = []
    for await (const page of client.allPages()) pages.push(page)
    assert.deepEqual(pages, fixtures.pages.data)
    const failed = new CapellClient({
        origin: 'https://example.com',
        fetch: async () => new Response('sensitive', { status: 403 }),
    })
    await assert.rejects(
        failed.pages(),
        (error) =>
            error instanceof CapellApiError &&
            error.status === 403 &&
            !error.message.includes('sensitive'),
    )
})
