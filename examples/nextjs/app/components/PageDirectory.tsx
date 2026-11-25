import type { NavigationLink } from '@capell/sdk'
import { pagePath } from '../../lib/pages.ts'

export function PageDirectory({ pages }: { pages: NavigationLink[] }) {
    if (!pages.length)
        return (
            <p className="empty-state">There are no published pages to show.</p>
        )
    return (
        <ul
            className="page-directory"
            data-page-directory
        >
            {pages.map((page) => {
                const path = pagePath(page.url)
                return path === null ? null : (
                    <li key={path}>
                        <a href={path}>
                            <span>
                                <strong>
                                    {page.title?.trim() || 'Untitled page'}
                                </strong>
                                <span className="page-path">{path}</span>
                            </span>
                            <span
                                className="link-arrow"
                                aria-hidden="true"
                            >
                                →
                            </span>
                        </a>
                    </li>
                )
            })}
        </ul>
    )
}
