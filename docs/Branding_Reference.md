# Woosoo Nexus — Branding Reference

## Fonts

| Token | Family | Usage |
|---|---|---|
| `--font-header` | **Raleway** | Headings, titles, buttons, labels (variable weight 100–900) |
| `--font-sans` | **Kanit** | Body text, paragraphs, table content (variable weight 100–900) |

Both loaded via Google Fonts (`resources/views/app.blade.php`).

---

## Brand Color Palette

| Token | Hex | Role |
|---|---|---|
| `--color-woosoo-accent` | `#F6B56D` | **Hero amber** — accent, dark-mode primary, ring, selection highlight |
| `--color-woosoo-primary-light` | `#FCD8BA` | Light-mode primary (buttons, actions) |
| `--color-woosoo-primary-dark` | `#B08047` | Dark amber — sidebar primary (light), focus ring |
| `--color-woosoo-dark-gray` | `#252525` | Foreground / text on light backgrounds |
| `--color-woosoo-white` | `#FFFFFF` | Base white |
| `--color-woosoo-blue` | `#2563EB` | Informational / link blue |
| `--color-woosoo-red` | `#DC2626` | Error / danger |
| `--color-woosoo-green` | `#16A34A` | Success |
| `--color-woosoo-green-200` | `#67EA98` | Light success variant |
| `--color-woosoo-green-100` | `#AAF3C6` | Lighter success variant |
| `--color-woosoo-orange` | `#F97316` | Warning / highlight orange |

---

## Semantic Theme Tokens

### Light Mode (`:root`)

| Token | Value |
|---|---|
| `--background` | `#FFFFFF` + warm gradient (`#f5ede3 → #fffaf5 → #ffffff → #f7f5f2`) |
| `--foreground` | `#252525` |
| `--primary` | `#FCD8BA` (primary-light) |
| `--primary-foreground` | `#252525` |
| `--accent` | `#F6B56D` (amber) |
| `--accent-foreground` | `#FFFFFF` |
| `--secondary` | `hsl(0 0% 92.1%)` |
| `--muted` | `hsl(0 0% 96.1%)` |
| `--muted-foreground` | `hsl(0 0% 38%)` |
| `--destructive` | `hsl(0 84.2% 60.2%)` |
| `--border` | `hsl(0 0% 89.8%)` |
| `--radius` | `0.5rem` |
| Sidebar background | `hsl(20 5% 10%)` (near-black) |
| Sidebar primary | `#B08047` |

### Dark Mode (`.dark`)

| Token | Value |
|---|---|
| `--background` | `hsl(20 6% 5%)` + dark gradient (`#17120f → #120f0d → #0e0c0a`) |
| `--foreground` | `hsl(30 10% 93%)` |
| `--primary` | `#F6B56D` (amber — flips from light-mode primary) |
| `--primary-foreground` | `#252525` |
| `--accent` | `#F6B56D` |
| `--ring` | `#F6B56D` |
| `--card` | `hsl(20 8% 13%)` |
| `--border` | `hsl(20 8% 18%)` |
| Sidebar background | `hsl(20 5% 8%)` |
| Sidebar primary | `#F6B56D` |

---

## Status / Semantic Colors

| Token | Value | Role |
|---|---|---|
| `--success` | `#16A34A` | Success states |
| `--warning` | `#F59E0B` | Warning states |
| `--error` | `#EF4444` | Error states |
| `--info` | `#3B82F6` | Informational states |

---

## Summary

Woosoo Nexus uses a **warm amber/brown brand palette** with `#F6B56D` as the hero color.
Light mode is bright white with a soft warm gradient background.
Dark mode is a near-black with warm brown tones, where amber takes over as the primary action color.
Typography: Raleway for headings, buttons, and labels; Kanit for body text.