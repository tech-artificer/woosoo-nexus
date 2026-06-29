---
name: KDS Full Screen Mode
about: Request to make KDS always display in full screen
title: "KDS should always display in full screen mode"
labels: ["enhancement", "kds"]
---

## Summary
Implement full screen functionality for the Kitchen Display System to maximize screen real estate and eliminate browser UI chrome, improving kitchen staff focus and reducing visual distractions.

## Problem
Currently, the KDS can be viewed in windowed mode, which reduces visibility and kitchen staff focus. The display includes browser navigation, tabs, and other UI elements that occupy valuable screen space needed for ticket visibility.

## Proposed Solutions

### Option 1: Browser Fullscreen API (Recommended)
- Use the browser's native Fullscreen API to lock the application in full screen mode
- Automatically enter full screen on page load
- Prevent users from exiting full screen via keyboard shortcuts (F11, Escape)
- Best for: Displays that have this page pinned as the primary view

### Option 2: Kiosk Mode
- Configure the browser/device to boot directly into full screen
- Set KDS as the startup page
- Requires device-level configuration

### Option 3: Remove Window Controls
- Hide browser navigation and address bar
- Remove ability to access browser menus
- Minimize UI chrome while in windowed mode

### Option 4: Electron Desktop App
- Package KDS as a native desktop application
- Launch with full screen by default
- No browser chrome visible
- Best for: Dedicated kitchen wall displays

### Option 5: Keyboard Shortcut Lock
- Implement F11 full screen toggle
- Lock full screen after activation to prevent accidental exit
- Display a visual indicator that the app is in full screen mode

## Implementation Considerations
- Remember user's full screen preference (localStorage or session)
- Add a settings panel to toggle full screen on/off
- Display a persistent indicator showing full screen is active
- Ensure responsive design works at various screen sizes
- Test on actual kitchen display hardware/resolution

## Acceptance Criteria
- [ ] KDS automatically enters full screen on page load
- [ ] Full screen mode persists across page refreshes
- [ ] Browser UI chrome is completely hidden
- [ ] Kitchen staff can easily toggle full screen if needed
- [ ] Works across different browsers and devices
- [ ] No performance degradation

## Additional Context
The KDS is a critical display for kitchen operations and should minimize distractions and UI chrome. A full screen display improves staff focus and maximizes the ticket viewing area at peak service times (40+ tables).

**Related:** KDS v1.0 implementation plan (#137)
