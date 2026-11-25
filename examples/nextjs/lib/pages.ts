import type { CapellClient, NavigationLink } from '@capell/sdk'

/** API page identities are site-relative, even for path-prefixed Capell domains. */
export function pagePath(path: string): string | null {
    try {
        const decoded = decodeURIComponent(path)
        if (
            !path.startsWith('/') ||
            decoded.startsWith('//') ||
            /[?#]/.test(path) ||
            /[\\\u0000-\u001f\u007f]/.test(decoded)
        )
            return null
        const url = new URL(path, 'https://example.invalid')
        return url.origin === 'https://example.invalid' ? url.pathname : null
    } catch {
        return null
    }
}

export async function loadDirectory(
    client: Pick<CapellClient, 'navigation'>,
): Promise<NavigationLink[]> {
    const pages: NavigationLink[] = []
    const cursors = new Set<string>()
    let cursor: string | undefined
    do {
        const result = await client.navigation({
            cursor,
            signal: AbortSignal.timeout(15000),
        })
        pages.push(...result.data)
        cursor = result.meta.next_cursor ?? undefined
        if (cursor && cursors.has(cursor))
            throw new Error('The API repeated a pagination cursor.')
        if (cursor) cursors.add(cursor)
    } while (cursor)
    // A directory cannot navigate away from this consumer using an API-supplied URL.
    return pages.filter((page) => pagePath(page.url) !== null)
}
