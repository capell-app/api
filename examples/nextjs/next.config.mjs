import { fileURLToPath } from 'node:url'

// Keep the local SDK inside the traced example without adopting an outer app.
const packageRoot = fileURLToPath(new URL('../../', import.meta.url))

export default {
    outputFileTracingRoot: packageRoot,
    turbopack: { root: packageRoot },
}
