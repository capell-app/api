import { SiteHeader } from './components/SiteHeader.tsx'
export default function NotFound() {
    return (
        <>
            <SiteHeader />
            <main
                id="main-content"
                className="status-layout"
            >
                <p className="eyebrow">404</p>
                <h1>Page not found</h1>
                <p className="lede">
                    This address does not have a published page. It may have
                    moved or no longer be available.
                </p>
                <a
                    className="button-link"
                    href="/?view=directory"
                >
                    Browse pages
                </a>
            </main>
        </>
    )
}
