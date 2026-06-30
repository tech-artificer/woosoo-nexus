import { ref } from 'vue'

const STORAGE_KEY = 'kds-fullscreen'

export function useKdsFullscreen() {
  const isFullscreen = ref(false)
  const wantsFullscreen = ref(true)

  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    if (stored !== null) {
      wantsFullscreen.value = stored !== '0'
    }
  } catch {
    // Storage is optional for kiosk browsers.
  }

  function onFullscreenChange(): void {
    isFullscreen.value = document.fullscreenElement != null
  }

  async function enterFullscreen(): Promise<void> {
    if (!document.fullscreenEnabled) {
      return
    }

    try {
      await document.documentElement.requestFullscreen()
    } catch {
      // Blocked without a user gesture — the toggle button provides the manual path.
    }
  }

  function exitFullscreen(): void {
    if (document.fullscreenElement) {
      document.exitFullscreen().catch(() => {})
    }
  }

  function toggleFullscreen(): void {
    const next = !isFullscreen.value
    wantsFullscreen.value = next

    try {
      window.localStorage.setItem(STORAGE_KEY, next ? '1' : '0')
    } catch {
      // Storage is optional for kiosk browsers.
    }

    if (next) {
      void enterFullscreen()
    } else {
      exitFullscreen()
    }
  }

  return { isFullscreen, wantsFullscreen, toggleFullscreen, onFullscreenChange, enterFullscreen }
}
