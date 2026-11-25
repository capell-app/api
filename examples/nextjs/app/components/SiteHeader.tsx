export function SiteHeader({ current }: { current?: 'home' | 'directory' }) {
    return (
        <header className="site-header">
            <a
                className="brand"
                href="/"
                aria-label="Capell headless home"
            >
                <span
                    className="brand-mark"
                    aria-hidden="true"
                >
                    c.
                </span>
                <span>
                    Capell
                    <span className="brand-caption">Headless starter</span>
                </span>
            </a>
            <nav aria-label="Main">
                <a
                    href="/"
                    aria-current={current === 'home' ? 'page' : undefined}
                >
                    Home
                </a>
                <a
                    href="/?view=directory"
                    aria-current={current === 'directory' ? 'page' : undefined}
                >
                    Browse pages
                </a>
            </nav>
        </header>
    )
}
