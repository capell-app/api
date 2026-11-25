import type { Page } from '@capell/sdk'

export function PageContent({ content }: { content: Page['content'] }) {
    if (
        content === null ||
        (typeof content === 'string' && !content.trim()) ||
        (typeof content === 'object' && Object.keys(content).length === 0)
    )
        return (
            <p className="empty-state">This page has no published body yet.</p>
        )

    if (typeof content === 'string') {
        // Only this public, sanitised API field is trusted as HTML. Structured values
        // stay escaped by React; never render arbitrary fields as HTML.
        return (
            <div
                className="prose"
                data-page-content
                dangerouslySetInnerHTML={{ __html: content }}
            />
        )
    }
    return (
        <section
            className="structured-content"
            aria-labelledby="structured-title"
        >
            <h2 id="structured-title">Structured page content</h2>
            <p>
                This page uses structured blocks. Map these public fields to
                your own components when building your site.
            </p>
            <pre data-page-content>
                <code>{JSON.stringify(content, null, 2)}</code>
            </pre>
        </section>
    )
}
