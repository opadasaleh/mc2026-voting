import { useSyncExternalStore } from "react"

const subscribe = () => () => {}

/**
 * false during server rendering and hydration, true afterwards. Lets a page
 * read browser-only state (localStorage, the URL) during render without a
 * hydration mismatch.
 */
export function useHydrated(): boolean {
    return useSyncExternalStore(subscribe, () => true, () => false)
}
