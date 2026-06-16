import { ref } from 'vue'

const STORAGE_KEY = 'kds-chime-muted'

/**
 * Short synthesized new-order chime (Web Audio API, no binary asset).
 *
 * The AudioContext is created lazily and resumed on the first user gesture — browsers
 * block audio until the page has been interacted with (spec §9: tapping the mute toggle
 * is the unlock gesture). `muted` persists to localStorage like the density toggle.
 */
export function useKdsChime() {
  const muted = ref(false)

  try {
    muted.value = window.localStorage.getItem(STORAGE_KEY) === '1'
  } catch {
    // Storage is optional for kiosk browsers.
  }

  let audioContext: AudioContext | null = null

  function ensureContext(): AudioContext | null {
    if (typeof window === 'undefined') {
      return null
    }

    const Ctor = window.AudioContext ?? (window as any).webkitAudioContext
    if (!Ctor) {
      return null
    }

    if (!audioContext) {
      audioContext = new Ctor()
    }

    if (audioContext.state === 'suspended') {
      void audioContext.resume()
    }

    return audioContext
  }

  function play(): void {
    if (muted.value) {
      return
    }

    const ctx = ensureContext()
    if (!ctx) {
      return
    }

    const now = ctx.currentTime
    const oscillator = ctx.createOscillator()
    const gain = ctx.createGain()

    oscillator.type = 'sine'
    oscillator.frequency.setValueAtTime(880, now)

    // ~0.5s single tone with a quick attack and gentle decay to avoid a click.
    gain.gain.setValueAtTime(0.0001, now)
    gain.gain.exponentialRampToValueAtTime(0.2, now + 0.02)
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.5)

    oscillator.connect(gain)
    gain.connect(ctx.destination)

    oscillator.start(now)
    oscillator.stop(now + 0.52)
  }

  function toggleMuted(): void {
    muted.value = !muted.value

    // Tapping the toggle is a user gesture — prime the context so the next chime can play.
    ensureContext()

    try {
      window.localStorage.setItem(STORAGE_KEY, muted.value ? '1' : '0')
    } catch {
      // Storage is optional for kiosk browsers.
    }
  }

  return { muted, play, toggleMuted }
}
