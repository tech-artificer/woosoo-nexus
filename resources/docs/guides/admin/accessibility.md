# How to Manage Accessibility Settings

The **Accessibility** settings allow you to customize the admin dashboard and system for users with visual, motor, or cognitive accessibility needs. This ensures all staff can use Woosoo comfortably and effectively.

---

## Why Accessibility Matters

Accessibility features help:
- **Visually impaired staff** → Larger text, high contrast, screen reader support
- **Motor impairment (tremors, limited dexterity)** → Larger buttons, keyboard navigation
- **Color blind staff** → High contrast, pattern-based indicators instead of color-only cues
- **Dyslexic staff** → Readable fonts, reduced clutter, clear language

---

## How to Access Accessibility Settings

### As an Admin (configure for all users)

1. Open Woosoo Nexus at `https://woosoo.local`
2. Sign in with admin credentials
3. Click **Accessibility** from the left menu (under Configuration)
4. The accessibility settings page appears

### As a Regular User (personal settings)

1. Click your **Profile icon** (top right)
2. Click **Settings**
3. Scroll to **Accessibility** section
4. Adjust personal settings (overrides admin defaults)

---

## Accessibility Features Available

### Visual Settings

#### Text Size

| Level | Usage | Examples |
|-------|-------|----------|
| **Small** | Default, compact layouts | Dashboard cards, tables |
| **Medium** | Easier to read without zooming | Recommended for most staff |
| **Large** | Significant vision challenges | Recommended for users with low vision or difficulty reading standard-sized text |
| **Extra Large** | Severe vision impairment | Works best with 1-2 columns instead of full width |

**How to adjust:**
1. Accessibility page → **Text Size** slider
2. **Large** is recommended starting point
3. Adjust until comfortable (test by viewing an Orders table)

#### High Contrast Mode

**What it does:**
- Makes text darker/bolder
- Increases button/link contrast
- Removes subtle gray backgrounds
- Uses stronger, more distinct colors

**When to enable:**
- Staff report difficulty reading text
- Working in bright sunlight or dim lighting
- Colorblind staff

**How to enable:**
1. Accessibility page → toggle **High Contrast Mode: ON**
2. Click "Preview" to see effect
3. Click **Save**

#### Font Family

| Font | Best For |
|------|----------|
| **Default (Inter)** | Clean, modern, universal |
| **Dyslexia-friendly (OpenDyslexic)** | Staff with dyslexia (recommended) |
| **Serif (Georgia)** | Easier to read for some older eyes |
| **Monospace (Courier)** | Data-heavy fields (codes, IDs) |

**How to adjust:**
1. Accessibility page → **Font Family** dropdown
2. Select "OpenDyslexic" if anyone has dyslexia
3. Test and save

#### Color Scheme

| Scheme | Best For |
|--------|----------|
| **Light** | Default, works well in bright environments |
| **Dark** | Reduces eye strain in dim lighting, easier for dyslexia |
| **High Contrast** | Colorblindness (red/green), severe vision impairment |

**How to adjust:**
1. Accessibility page → **Color Scheme** dropdown
2. Test by viewing different pages
3. Save

---

### Motor/Interaction Settings

#### Button & Interactive Element Size

| Level | Impact |
|-------|--------|
| **Normal** | Standard clickable areas (~44px height) |
| **Large** | Larger touch targets (~56px height) — for tremors or limited dexterity |
| **Extra Large** | Spacious layout (~64px+ height) — for severe motor impairment |

**When to enable:**
- Staff with hand tremors, arthritis, or limited dexterity
- Works on tablets used by staff (especially kitchen staff with wet hands)

**How to adjust:**
1. Accessibility page → **Interactive Element Size** slider
2. Set to "Large" or "Extra Large"
3. Test by clicking buttons (should feel comfortable)
4. Save

#### Focus Indicators

**What it does:**
- Shows a clear outline around the currently focused element
- Helps keyboard-only navigation (staff who can't use mouse)

**When to enable:**
- Staff with motor impairment who navigate via keyboard
- Any staff preferring keyboard to mouse

**How to enable:**
1. Accessibility page → toggle **Visible Focus Indicators: ON**
2. Test by pressing Tab key (you'll see outlines as you tab)
3. Save

#### Reduce Motion

**What it does:**
- Removes animations and transitions
- Disables smooth scrolling
- Makes interactions instant

**When to enable:**
- Staff with vertigo or motion sensitivity
- Staff finding animations distracting

**How to enable:**
1. Accessibility page → toggle **Reduce Motion: ON**
2. All animations disappear
3. Save

---

### Cognitive & Reading Settings

#### Simplified Interface

**What it does:**
- Hides advanced options and less-used features
- Shows only essential buttons and fields
- Reduces visual clutter

**When to enable:**
- Staff new to the system
- Staff with cognitive disabilities
- Kitchen staff who need quick, focused workflows

**How to enable:**
1. Accessibility page → toggle **Simplified Interface: ON**
2. Dashboard and order pages now show only essential info
3. Advanced settings appear under "Advanced" button if needed
4. Save

#### Language & Terminology

| Setting | Purpose |
|---------|---------|
| **Simple Language** | Replace jargon with everyday words (e.g., "Stop order" instead of "Void") |
| **Language** | Change system language (English, Spanish, French) — if available |
| **Confirmation Dialogs** | Always show confirmation before destructive actions (delete, void) |

**How to adjust:**
1. Accessibility page → toggle **Simple Language: ON**
2. Toggle **Confirmation Dialogs: ON** (always good for safety)
3. Select **Language** from dropdown
4. Save

#### Reading Guide

**What it does:**
- Highlights the current line or paragraph you're reading
- Helps focus attention
- Useful for staff with ADHD or dyslexia

**When to enable:**
- Staff report difficulty focusing while reading tables
- Forms with many fields

**How to enable:**
1. Accessibility page → toggle **Reading Guide: ON**
2. A subtle highlight bar appears as you scroll through text
3. Save

---

## Common Accessibility Scenarios

### Scenario: An older staff member says text is too small

**Solution:**

1. Go to **Accessibility** page
2. Adjust **Text Size** to "Large"
3. Enable **High Contrast Mode** (helps eyes in restaurant lighting)
4. Click **Preview** to show staff the change
5. Save

**Test:** Open the Orders page — staff should say "Yes, that's better"

---

### Scenario: A bartender with hand tremors keeps clicking wrong buttons

**Solution:**

1. Go to **Accessibility** page
2. Set **Interactive Element Size** to "Large" or "Extra Large"
3. Enable **Visible Focus Indicators** (helps see what they're about to click)
4. Enable **Reduce Motion** (less distraction)
5. Save

**Test:** Staff tries to process an order — buttons should be easier to hit

---

### Scenario: A dyslexic staff member struggles to read menus and order details

**Solution:**

1. Go to **Accessibility** page
2. Change **Font Family** to "OpenDyslexic"
3. Enable **High Contrast Mode**
4. Enable **Dark Color Scheme** (easier on eyes)
5. Enable **Reading Guide** (helps focus line-by-line)
6. Save

**Test:** Staff reads an order detail — should be significantly easier

---

### Scenario: Staff get confused by too many options

**Solution:**

1. Go to **Accessibility** page
2. Enable **Simplified Interface**
3. Test with staff — they should see only essential buttons
4. Advanced options still available if needed
5. Save

---

## System-Wide Defaults (Admin Only)

As an admin, you can set defaults that apply to all users:

**On the Accessibility page:**

1. All settings have a "Default for all users" checkbox
2. Check it to apply this setting as the baseline
3. Individual users can still override in their personal settings
4. Example: Check "Default for all users" on High Contrast Mode → all staff get it by default

**Recommended defaults:**
- ✅ High Contrast Mode ON
- ✅ Confirmation Dialogs ON
- ⚠️ Text Size "Medium" (let staff adjust individually)
- ⚠️ Simplified Interface OFF (let staff opt-in if needed)

---

## Testing Accessibility

### Keyboard Navigation Test

1. Go to any page
2. Press Tab key repeatedly
3. You should see focus move through all interactive elements (buttons, links, inputs)
4. You should be able to interact using only keyboard (Enter to click, Arrow keys to select)

If this fails → accessibility is broken

### Screen Reader Test

1. **Windows:** Install NVDA (free)
2. **Mac:** Enable VoiceOver (System Settings → Accessibility) *(macOS Ventura and later; older versions: System Preferences → Accessibility)*
3. Open Woosoo Nexus
4. Navigate using screen reader commands
5. All text should be readable, buttons should announce their purpose

### Color Contrast Test

1. Enable **High Contrast Mode**
2. Open an Orders table
3. Try to distinguish:
   - Row backgrounds
   - Status badges
   - Error messages
4. All should be easily readable

---

## Best Practices

✅ **Ask staff about their needs** — don't assume; everyone is different.

✅ **Test settings before deploying** — verify with the staff member who will use them.

✅ **Configure settings per device or role profile** — avoid maintaining a per-individual list of accessibility settings, as recording a person’s disability needs constitutes sensitive personal data. Use device-level or role-level configuration instead.

✅ **Review quarterly** — as staff age or change roles, accessibility needs may change.

✅ **Lead by example** — if you don't have accessibility needs, still test features to find bugs.

---

## Troubleshooting

**Problem:** After enabling High Contrast Mode, some colors disappeared and the page looks broken.

**Diagnosis:**
1. This is normal — High Contrast removes subtle backgrounds
2. May need to adjust other settings (e.g., Font Family, Text Size) to compensate

**Solution:**
1. Enable **Simplified Interface** — reduces visual complexity
2. Try Dark Color Scheme instead of High Contrast
3. Clear browser cache: Ctrl+Shift+Delete

---

**Problem:** Text size change didn't apply to some pages (e.g., Reports page still shows small text).

**Diagnosis:**
1. Some report components may not respect global text size settings
2. Page may need to be refreshed

**Solution:**
1. Hard refresh: Ctrl+Shift+R
2. Clear cache and cookies for woosoo.local
3. Re-login

---

## Next Steps

- [Add User Guide](add-user.md) — when creating staff accounts, consider accessibility from the start
- [Monitoring Guide](monitoring.md) — ensure accessibility features don't impact system performance
- [Support Resources](#support-resources) — links to accessibility guidelines and tools

---

## Support Resources

- **WCAG 2.1 Guidelines:** https://www.w3.org/WAI/WCAG21/quickref/
- **Accessible Colors Tool:** https://www.color-blindness.com/coblis-color-blindness-simulator/
- **OpenDyslexic Font:** https://opendyslexic.org/
- **Screen Reader Tests:** https://www.nvaccess.org/ (NVDA for Windows)
