# Design QA — 公开留言回复箭头

## Comparison Target

- Source visual truth: `artifacts/design-qa/21-reply-toggle-reference.png`
- Implemented route: `http://127.0.0.1:8088/liuyanban/`
- Desktop viewport: requested `1440 × 900`; effective page width `1425px`; capture `1425 × 891`
- Mobile viewport: requested `390 × 844`; effective page width `375px`; capture `375 × 812`
- State: first公开留言的管理员回复已展开
- Scope: compare reply-toggle direction, placement, reply relationship and responsive behavior; message copy and IDs intentionally use local test data rather than the reference data

## Evidence

- Source reference: `artifacts/design-qa/21-reply-toggle-reference.png` (`1266 × 458`)
- Desktop closed state: `artifacts/design-qa/22-reply-chevron-closed-desktop.jpg`
- Desktop open state: `artifacts/design-qa/23-reply-chevron-open-desktop.jpg`
- Mobile open state: `artifacts/design-qa/24-reply-chevron-open-mobile.jpg`
- Normalized implementation crop: `artifacts/design-qa/25-reply-toggle-implementation-crop.jpg` (`1266 × 390`)
- Combined source/implementation comparison: `artifacts/design-qa/25-reply-toggle-comparison.jpg` (`1266 × 848`)

The combined comparison places the source above the implementation at the same `1266px` width. The focused implementation crop comes from the `1120px` public-board content region and was scaled to `1266px`; both captures use 1× browser density. The different crop heights reflect different message copy, not layout scaling.

## Required Fidelity Surfaces

### Fonts and typography

- The visible “查看回复（1）”“展开”“收起” copy has been removed as requested.
- Native `<details>` and `<summary>` semantics remain, with visually hidden “展开或收起管理员回复” text for assistive technology.
- Existing message, status and reply typography remains unchanged.

### Spacing and layout rhythm

- The arrow occupies a `44 × 44px` interaction target at the message body's lower-right edge.
- Desktop right inset is `30px`, matching the message-body padding; mobile right inset is `18px`, matching the mobile padding.
- The administrator reply appears immediately below the arrow and uses the full available message-body width.
- Mobile body width equals document scroll width (`375px`), so the change introduces no horizontal overflow.

### Colors and visual tokens

- The arrow uses the established government blue and existing focus color.
- Default state has no visible button border or fill; hover uses the existing pale-blue surface token.
- Keyboard focus retains the project's yellow high-contrast outline, which is intentionally visible in automated open-state captures.

### Image quality and asset fidelity

- The arrow is a real local transparent PNG: `public/assets/images/reply-chevron-down.png` (`48 × 48`).
- The visible chevron fills approximately `40 × 22px` of the source asset and is rendered inside a `20 × 20px` image box.
- Transparent corners and antialiased edges were verified; no CSS-drawn arrow, text glyph, inline SVG or remote asset is used.
- The same down-chevron image rotates 180 degrees in the native open state, ensuring identical weight and alignment in both directions.

### Copy and content

- No visible toggle copy remains.
- “管理员回复”, reply body and reply time remain unchanged.
- The toggle is only rendered when a published reply exists.

## Interaction Verification

- Four replied test messages expose four native reply toggles.
- Closed state: down arrow, `<details open>` is false and icon transform is none.
- First click: reply becomes visible, `<details open>` is true and icon rotates 180 degrees into an up arrow.
- Second click: reply closes and the icon returns to the down-arrow state.
- Summary retains a descriptive tooltip and hidden accessible name.
- Desktop and mobile console errors: none.
- PHP 7.3.4 syntax check, project smoke test and arrow HTTP loading check passed.

## Comparison History

### Iteration 1 — Icon scale

Finding:

- P2: the first transparent export retained excessive canvas padding, making the arrow too small at its 20px rendered size.

Fix:

- Trimmed transparent padding, resized the visible chevron to `40 × 22px`, then centered it in a `48 × 48px` transparent asset.

Post-fix evidence:

- `22-reply-chevron-closed-desktop.jpg` shows a clearly readable down arrow at the reference scale.

### Iteration 2 — Reply width

Finding:

- P2: the initial grid implementation reserved a separate arrow column and narrowed the reply box, especially on mobile.

Fix:

- Replaced the grid with normal block layout, kept the summary right-aligned with `margin-left: auto`, and restored the reply box to full body width.

Post-fix evidence:

- Mobile reply width is `313px` with matching `18px` left and right insets inside a `349px` message body.
- Desktop reply width is `908px`, equal to the body inner width, with matching `30px` insets.
- `23-reply-chevron-open-desktop.jpg` and `24-reply-chevron-open-mobile.jpg` show the corrected layout.

No actionable P0, P1 or P2 findings remain after the second pass.

## Findings

No actionable P0/P1/P2 visual, responsive, accessibility or interaction findings remain. The yellow circle visible in the automated open-state screenshots is the intentional keyboard focus outline, not permanent button chrome.

## Open Questions

- None for this change.

## Implementation Checklist

- [x] Use down arrow for closed state.
- [x] Rotate to up arrow for open state.
- [x] Position the toggle at the message lower-right.
- [x] Remove visible toggle copy.
- [x] Preserve native keyboard and assistive-technology semantics.
- [x] Preserve full-width reply layout.
- [x] Verify desktop and mobile behavior.

## Follow-up Polish

- No required follow-up.

final result: passed
