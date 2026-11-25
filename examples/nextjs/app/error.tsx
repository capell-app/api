'use client'

import { SiteHeader } from './components/SiteHeader.tsx'

export default function ErrorPage({ reset }: { reset: () => void }) {
    // Never render error.message or stack: upstream errors can contain private paths.
    return (
        <>
            <SiteHeader />
            <main
                id="main-content"
                className="status-layout"
            >
                <p className="eyebrow">Content unavailable</p>
                <h1>We could not load this page</h1>
                <p className="lede">
                    The content service is unavailable right now. Try again in a
                    moment.
                </p>
                <button
                    className="button-link"
                    onClick={() => reset()}
                >
                    Try again
                </button>
            </main>
        </>
    )
}
