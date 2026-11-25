import type { ReactNode } from 'react'
import './globals.css'

export const metadata = {
    title: 'Capell headless starter',
    description: 'Published content from Capell, rendered with Next.js.',
}

export default function Layout({ children }: { children: ReactNode }) {
    return (
        <html lang="en">
            <body>
                <a
                    className="skip-link"
                    href="#main-content"
                >
                    Skip to content
                </a>
                <div className="site-shell">
                    {children}
                    <footer className="site-footer">
                        <span>Capell · Next.js example</span>
                        <span>Published content. Your frontend.</span>
                    </footer>
                </div>
            </body>
        </html>
    )
}
