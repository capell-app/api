import { CapellClient, CapellApiError, type NavigationLink } from '@capell/sdk'
import { notFound } from 'next/navigation'

export const dynamic = 'force-dynamic'

export default async function Page({
    params,
}: {
    params: Promise<{ path?: string[] }>
}) {
    const origin = process.env.CAPELL_ORIGIN
    if (!origin)
        throw new Error('Set CAPELL_ORIGIN to the seeded Capell site origin.')
    const capell = new CapellClient({ origin })
    const path =
        '/' + ((await params).path ?? []).map(encodeURIComponent).join('/')
    const navigation: NavigationLink[] = []
    let cursor: string | undefined
    do {
        const result = await capell.navigation({ cursor })
        navigation.push(...result.data)
        cursor = result.meta.next_cursor ?? undefined
    } while (cursor)
    try {
        const { data: page } = await capell.page(path)
        return (
            <>
                <nav aria-label="Site">
                    {navigation.map((link) => (
                        <a
                            key={link.url}
                            href={link.url}
                        >
                            {link.title ?? link.url}{' '}
                        </a>
                    ))}
                </nav>
                <main>
                    <h1>{page.title}</h1>
                    {typeof page.content === 'string' ? (
                        <div
                            dangerouslySetInnerHTML={{ __html: page.content }}
                        />
                    ) : (
                        <pre>{JSON.stringify(page.content, null, 2)}</pre>
                    )}
                </main>
            </>
        )
    } catch (error) {
        if (error instanceof CapellApiError && error.status === 404) notFound()
        throw error
    }
}
