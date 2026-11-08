---
version: alpha
name: KujangGate Supabase Dark
description: Dark-mode-native cryptographic license operator console built on the Supabase design system.
colors:
  primary: "#3ecf8e"
  secondary: "#00c573"
  surface-void: "#0f0f0f"
  surface-canvas: "#171717"
  surface-elevated: "#1f1f1f"
  border-subtle: "#242424"
  border-default: "#2e2e2e"
  border-strong: "#363636"
  text-primary: "#fafafa"
  text-secondary: "#b4b4b4"
  text-muted: "#898989"
  status-warning: "#f59e0b"
  status-danger: "#ef4444"
typography:
  display-hero:
    fontFamily: Inter
    fontSize: 2.25rem
    fontWeight: 500
    lineHeight: 1.0
    letterSpacing: "-0.02em"
    fontFeature: "'cv01', 'ss03'"
  heading-panel:
    fontFamily: Inter
    fontSize: 1.125rem
    fontWeight: 500
    lineHeight: 1.33
    letterSpacing: "-0.01em"
  body-md:
    fontFamily: Inter
    fontSize: 0.875rem
    fontWeight: 400
    lineHeight: 1.5
  mono-code:
    fontFamily: JetBrains Mono
    fontSize: 0.75rem
    fontWeight: 400
    lineHeight: 1.4
    letterSpacing: "0.02em"
    fontFeature: "'tnum', 'zero'"
  mono-label:
    fontFamily: Source Code Pro
    fontSize: 0.75rem
    fontWeight: 500
    lineHeight: 1.33
    letterSpacing: "0.08em"
rounded:
  sm: 6px
  md: 8px
  lg: 12px
  pill: 9999px
spacing:
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
components:
  button-primary:
    backgroundColor: "{colors.surface-void}"
    textColor: "{colors.text-primary}"
    typography: "{typography.body-md}"
    rounded: "{rounded.pill}"
    padding: 8px
  button-primary-hover:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface-void}"
  button-secondary:
    backgroundColor: "{colors.surface-canvas}"
    textColor: "{colors.text-secondary}"
    rounded: "{rounded.sm}"
    padding: 8px
  card-panel:
    backgroundColor: "{colors.surface-canvas}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.md}"
    padding: 16px
  badge-verified:
    backgroundColor: "{colors.surface-void}"
    textColor: "{colors.primary}"
    typography: "{typography.mono-label}"
    rounded: "{rounded.pill}"
    padding: 4px
---

## Overview

KujangGate adopts the **Supabase Design System** (`#0f0f0f` void surfaces, `#171717` canvas, `#2e2e2e` 1px structural borders, and `#3ecf8e` PostgreSQL-emerald signals) tailored for a **Command / Inspect + Operate** surface archetype. Zero shadows, zero marketing fluff, and strict tabular-numeric alignment for Ed25519 public keys, SHA-256 hardware fingerprints, and seat telemetry.

## Colors

- **Primary (`#3ecf8e`)**: Supabase Emerald — reserved strictly for cryptographic verification pass states, active key indicators, and primary border highlights.
- **Secondary (`#00c573`)**: Interactive emerald for links and active filter states.
- **Surface Void (`#0f0f0f`) & Canvas (`#171717`)**: Deep terminal-native surfaces separated by `#242424` and `#2e2e2e` hairline borders rather than drop shadows.
- **Text Hierarchy (`#fafafa`, `#b4b4b4`, `#898989`)**: High-contrast primary ink paired with restrained neutral grays for metadata.

## Typography

- **Inter (`cv01`, `ss03`)**: Used at weights 400 and 500 only. Hierarchy comes from scale and contrast, never heavy 700 bold weights.
- **JetBrains Mono / Source Code Pro (`tabular-nums`)**: Used for Ed25519 hex/base64url signatures, `KGATE-v1` certificates, SHA-256 machine fingerprints, and uppercase telemetry labels.

## Layout

Committed to the **Operate + Command/Inspect** surface archetype:
1. **Left Navigation Rail (220px)**: Product namespaces, signing key ring, webhook endpoints, and environment switcher.
2. **Top Telemetry Bar**: Real-time `tabular-nums` counters for Active Licenses, Bound Hardware Seats, Offline Verifications, and Key Rotation Status.
3. **Split Workspace**: Left pane for Ed25519 Certificate Minting & Offline Token Inspector; Right pane for Hardware Seat Activation Simulator, Webhook Replay Guard, and Live License Ledger.

## Elevation & Depth

Zero drop-shadows. Depth is expressed exclusively via surface luminance steps (`#0f0f0f` -> `#171717` -> `#1f1f1f`) and border contrast (`#242424` -> `#2e2e2e` -> `#363636` -> `rgba(62, 207, 142, 0.3)`).

## Shapes

- **Pill (`9999px`)**: Primary action triggers and cryptographic status badges.
- **Standard (`6px` - `8px`)**: Inspector panels, code blocks, inputs, and ledger tables.

## Components

- `button-primary`: Full-pill (`9999px`) button with `#0f0f0f` background and `1px solid #3ecf8e` or `#fafafa` border.
- `card-panel`: Flat `#171717` container with `1px solid #2e2e2e` border and header strip.
- `badge-verified`: Monospace pill indicator showing `ED25519_VALID` or `SEAT_BOUND`.

## Do's and Don'ts

- **Do** use 1px solid borders (`#2e2e2e`) to divide dense operator panels.
- **Do** keep font weights to 400 and 500 across the UI.
- **Don't** use indigo/violet gradients, glassmorphism blur cards, or centered marketing hero stacks.
