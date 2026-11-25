import { CapellClient, CapellApiError } from '@capell/sdk'
import { notFound } from 'next/navigation'
import { PageContent } from '../components/PageContent.tsx'
import { PageDirectory } from '../components/PageDirectory.tsx'
import { SiteHeader } from '../components/SiteHeader.tsx'
import { loadDirectory } from '../../lib/pages.ts'

export const dynamic = 'force-dynamic'

export default async function Page({
    params,
    searchParams,
}: {
    params: Promise<{ path?: string[] }>
    searchParams: Promise<{ view?: string | string[] }>
}) {
    const origin = process.env.CAPELL_ORIGIN
    if (!origin)
        throw new Error('Set CAPELL_ORIGIN to the seeded Capell site origin.')
    const capell = new CapellClient({ origin })
    const path =
        '/' + ((await params).path ?? []).map(encodeURIComponent).join('/')
    const isHome = path === '/'
    const isDirectory = isHome && (await searchParams).view === 'directory'

    if (isDirectory) {
        const pages = await loadDirectory(capell)
        return (
            <>
                <SiteHeader current="directory" />
                <main
                    id="main-content"
                    className="directory-layout"
                >
                    <div className="page-heading">
                        <p className="eyebrow">Page directory</p>
                        <h1>Browse published pages</h1>
                        <p className="lede">
                            Explore the pages available on this site. Each link
                            opens its published content here.
                        </p>
                    </div>
                    <div className="directory-meta">
                        <span>
                            {pages.length} published{' '}
                            {pages.length === 1 ? 'page' : 'pages'}
                        </span>
                        <span>From the Capell API</span>
                    </div>
                    <PageDirectory pages={pages} />
                </main>
            </>
        )
    }

    // Resolve first: a missing article must still be a 404 if navigation is unavailable.
    let page
    try {
        page = (await capell.page(path, { signal: AbortSignal.timeout(15000) }))
            .data
    } catch (error) {
        if (error instanceof CapellApiError && error.status === 404) notFound()
        throw error
    }
    const pages = isHome ? await loadDirectory(capell) : []
    return (
        <>
            <SiteHeader current={isHome ? 'home' : undefined} />
            <main
                id="main-content"
                className={isHome ? 'home-layout' : 'article-layout'}
            >
                <article>
                    <div className="page-heading">
                        <p className="eyebrow">
                            {isHome ? 'Welcome' : 'Published page'}
                        </p>
                        <h1>
                            {page.title?.trim() ||
                                (isHome ? 'Home' : 'Untitled page')}
                        </h1>
                    </div>
                    <PageContent content={page.content} />
                    <a
                        className="text-link"
                        href="/?view=directory"
                    >
                        Explore all pages <span aria-hidden="true">→</span>
                    </a>
                </article>
                {isHome && (
                    <aside
                        className="home-directory"
                        aria-labelledby="explore-title"
                    >
                        <div className="section-heading">
                            <h2 id="explore-title">Explore the site</h2>
                            <p>Published pages, ready to read.</p>
                        </div>
                        <PageDirectory
                            pages={pages
                                .filter((item) => item.url !== '/')
                                .slice(0, 5)}
                        />
                        <a
                            className="text-link"
                            href="/?view=directory"
                        >
                            View the full directory{' '}
                            <span aria-hidden="true">→</span>
                        </a>
                    </aside>
                )}
            </main>
        </>
    )
}
