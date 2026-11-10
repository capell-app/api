import assert from 'node:assert/strict'
import test from 'node:test'
import { CapellClient, CapellApiError } from '../src/index.ts'

test('encodes a domain prefix as public context without changing the API route', async () => {
    const calls = []
    const client = new CapellClient({
        origin: 'https://example.com/en',
        token: 'preview',
        fetch: async (url, init) => {
            calls.push({ url, init })
            return Response.json({ data: [], meta: { next_cursor: null } })
        },
    })
    await client.pages({
        preview: true,
        filter: { path: '/some & path' },
        limit: 2,
    })
    assert.equal(calls[0].url.pathname, '/api/v1/pages')
    assert.equal(calls[0].url.searchParams.get('url'), '/en/')
    assert.equal(calls[0].url.searchParams.get('filter[path]'), '/some & path')
    assert.equal(calls[0].url.searchParams.get('preview'), '1')
    assert.equal(calls[0].init.headers.Authorization, 'Bearer preview')
    assert.equal(calls[0].init.redirect, 'error')
    assert.equal(calls[0].init.cache, 'no-store')
    await client.page('/about')
    assert.equal(calls[1].url.searchParams.get('url'), '/en/about')
})

test('rejects malformed origins, unsafe resource paths and unauthorised preview', async () => {
    assert.throws(
        () => new CapellClient({ origin: 'https://user:pass@example.com' }),
        TypeError,
    )
    assert.throws(
        () => new CapellClient({ origin: 'file:///private' }),
        TypeError,
    )
    const client = new CapellClient({ origin: 'https://example.com' })
    assert.throws(() => client.page('//evil.example'), TypeError)
    assert.throws(() => client.collection('../internal'), TypeError)
    await assert.rejects(client.pages({ preview: true }), TypeError)
})

test('does not leak an error body and detects repeated pagination cursors', async () => {
    const failed = new CapellClient({
        origin: 'https://example.com',
        fetch: async () => new Response('secret response', { status: 403 }),
    })
    await assert.rejects(
        failed.pages(),
        (error) =>
            error instanceof CapellApiError &&
            error.status === 403 &&
            !error.message.includes('secret'),
    )
    const looping = new CapellClient({
        origin: 'https://example.com',
        fetch: async () =>
            Response.json({ data: [], meta: { next_cursor: 'same' } }),
    })
    await assert.rejects(async () => {
        for await (const page of looping.allPages()) void page
    }, CapellApiError)
})
