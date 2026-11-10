export interface Page {
    url: string
    title: string | null
    content: string | Record<string, unknown> | unknown[] | null
}
export interface NavigationLink {
    url: string
    title: string | null
}
export interface Media {
    url: string
    alt: string
    mime_type: string | null
}
export interface PageResult<T> {
    data: T[]
    meta: { next_cursor: string | null }
}
export interface ReadOptions {
    cursor?: string
    limit?: number
    filter?: { type?: string; path?: string }
    preview?: boolean
    signal?: AbortSignal
}
export interface ClientOptions {
    /** Origin of the Capell site, with an optional published domain prefix. */
    origin: string
    /** Preview tokens belong on the server only, never in browser bundles. */
    token?: string
    fetch?: typeof globalThis.fetch
}
export class CapellApiError extends Error {
    readonly status: number
    constructor(status: number, message: string) {
        super(message)
        this.status = status
    }
}
export class CapellClient {
    private readonly origin: URL
    private readonly transport: typeof globalThis.fetch
    private readonly token: string | undefined
    constructor(options: ClientOptions) {
        this.origin = new URL(
            options.origin.endsWith('/')
                ? options.origin
                : `${options.origin}/`,
        )
        if (
            !['http:', 'https:'].includes(this.origin.protocol) ||
            this.origin.username ||
            this.origin.password ||
            this.origin.search ||
            this.origin.hash
        ) {
            throw new TypeError('A plain HTTP(S) site origin is required.')
        }
        this.transport = options.fetch ?? globalThis.fetch
        this.token = options.token
    }
    pages(options: ReadOptions = {}): Promise<PageResult<Page>> {
        return this.read('pages', {}, options)
    }
    page(path: string, options: ReadOptions = {}): Promise<{ data: Page }> {
        if (!path.startsWith('/') || path.startsWith('//'))
            throw new TypeError('A site-relative page path is required.')
        return this.read('pages/resolve', { url: path }, options)
    }
    collection(
        type: string,
        options: ReadOptions = {},
    ): Promise<PageResult<Page>> {
        if (!/^[a-zA-Z0-9_-]+$/.test(type))
            throw new TypeError('A public page type key is required.')
        return this.read(`collections/${encodeURIComponent(type)}`, {}, options)
    }
    navigation(options: ReadOptions = {}): Promise<PageResult<NavigationLink>> {
        return this.read('navigation', {}, options)
    }
    media(options: ReadOptions = {}): Promise<PageResult<Media>> {
        return this.read('media', {}, options)
    }
    search(
        query: string,
        options: ReadOptions = {},
    ): Promise<PageResult<Page>> {
        return this.read('search', { q: query }, options)
    }
    async *allPages(
        options: Omit<ReadOptions, 'cursor'> = {},
    ): AsyncGenerator<Page> {
        let cursor: string | undefined
        const seen = new Set<string>()
        do {
            const result = await this.pages({ ...options, cursor })
            for (const page of result.data) yield page
            cursor = result.meta.next_cursor ?? undefined
            if (cursor && seen.has(cursor))
                throw new CapellApiError(
                    502,
                    'The API repeated a pagination cursor.',
                )
            if (cursor) seen.add(cursor)
        } while (cursor)
    }
    private async read<T>(
        resource: string,
        query: Record<string, string>,
        options: ReadOptions,
    ): Promise<T> {
        if (options.preview && !this.token)
            throw new TypeError('Preview requires a server-side token.')
        const url = new URL(`/api/v1/${resource}`, this.origin)
        const prefix = this.origin.pathname.replace(/\/$/, '')
        url.searchParams.set('url', prefix + (query.url ?? '/'))
        for (const [key, value] of Object.entries(query)) {
            if (key !== 'url') url.searchParams.set(key, value)
        }
        if (options.cursor !== undefined)
            url.searchParams.set('cursor', options.cursor)
        if (options.limit !== undefined)
            url.searchParams.set('limit', String(options.limit))
        if (options.preview) url.searchParams.set('preview', '1')
        for (const [key, value] of Object.entries(options.filter ?? {}))
            url.searchParams.set(`filter[${key}]`, value)
        const response = await this.transport(url, {
            headers: {
                Accept: 'application/json',
                ...(this.token
                    ? { Authorization: `Bearer ${this.token}` }
                    : {}),
            },
            cache: 'no-store',
            redirect: 'error',
            signal: options.signal,
        })
        if (!response.ok)
            throw new CapellApiError(
                response.status,
                `Capell API request failed (${response.status}).`,
            )
        return (await response.json()) as T
    }
}
