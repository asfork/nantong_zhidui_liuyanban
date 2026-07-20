# Design QA

## Comparison Target

- Source visual truth: `/Users/steve/Desktop/087A0E08-6E50-456A-A023-7333804B64E0_1_102_o.jpeg`
- Implemented route: `http://127.0.0.1:8088/liuyanban/`
- Desktop viewport: `864 × 900`
- Mobile viewport: `390 × 844`
- State: public message board, “全部” filter active, replies collapsed, empty submission form

## Evidence

- Desktop implementation: `artifacts/design-qa/01-desktop-viewport.png`
- Desktop top comparison: `artifacts/design-qa/09-top-comparison.png`
- Form implementation: `artifacts/design-qa/06-form-focused.png`
- Form comparison: `artifacts/design-qa/11-form-comparison.png`
- Mobile implementation: `artifacts/design-qa/03-mobile-390x844.png`

The full-view comparison uses the same 864-pixel desktop width and the same resting state. The source image is cropped to the corresponding top viewport because the requested removal of its header, navigation, search, and breadcrumb changes the page height. A focused form comparison is included because the form text and controls are too small to judge reliably in the full-page source image.

## Required Fidelity Surfaces

### Fonts and typography

- The implementation keeps the reference's Chinese sans-serif government-service tone.
- Body text is intentionally increased to 16px with a 1.65 line height to satisfy the accepted P2 accessibility recommendation.
- Headings and status labels preserve the reference hierarchy with stronger optical weight.
- No actionable wrapping, clipping, or truncation was found at desktop or mobile widths.

### Spacing and layout rhythm

- The blue-white card system, bordered list, left metadata column, status placement, and reply hierarchy match the reference language.
- Vertical spacing is intentionally more generous than the source because the accepted P2 work enlarged text and interaction targets.
- The mobile layout correctly changes message metadata and form controls to a single-column flow with no horizontal overflow.

### Colors and visual tokens

- The implementation retains the reference's dark blue, action blue, pale blue, neutral border, green replied state, and warm waiting state.
- Contrast is strengthened for body copy, timestamps, borders, and status labels.
- Status meaning is communicated by text as well as color.

### Image quality and asset fidelity

- The top decorative header, navigation icons, search icon, and user avatar were removed rather than approximated, as explicitly requested by the user and accepted in P0.
- The implementation contains no fake image placeholders, handcrafted SVGs, CSS-drawn icons, or substituted emoji.
- No remaining page section requires a raster image asset.

### Copy and content

- Contact name, telephone, and user ID collection are removed.
- A non-identifying message number replaces the masked user ID.
- The notice states that submissions enter review and that source IP is recorded only for security auditing.
- Public statuses clearly separate “待回复” from internal “待审核”.
- The form explicitly tells users not to submit personal information and that publication is not immediate.

## Interaction Verification

- “待回复” filter: passed; only three unreplied, approved messages remain visible.
- Reply disclosure: passed; the correct administrator reply opens and closes through a keyboard-operable native `details` control.
- Character counters: passed for title and content.
- Agreement checkbox: passed.
- Validation recovery: passed; an invalid security answer displays a field-related error while preserving title, content, and checkbox state.
- Successful pending write: passed in a rolled-back database transaction; new records receive `pending`, `visible`, and the source IP.
- Public data boundary: passed; seeded pending and hidden messages are absent from the DOM.
- Removed scope: passed; no top navigation, search input, contact field, phone field, or user ID is present.
- Browser console errors: none.
- Security challenge success was not submitted through the browser; the browser run did not solve the visible challenge. The server-side successful-write path was verified separately in a rolled-back transaction.

## Comparison History

### Iteration 1

Earlier P0/P1/P2 findings came from the approved audit of the source image:

- P0: remove navigation/search, contact details and masked user identity; enforce review-only public visibility; add IP/privacy notice.
- P1: add a visible “我要留言” action, explicit reply controls, consistent collapsed state, correct pagination, validation recovery and submission feedback.
- P2: increase typography and contrast, enlarge targets, support keyboard use, provide an accessible security challenge and reflow on mobile.

Fixes made:

- All P0/P1/P2 items above were implemented in the PHP page, CSS, JavaScript, database query and form handler.
- Post-fix evidence is recorded in the desktop, form, and mobile screenshots listed above.

Post-fix result:

- No actionable P0, P1, or P2 visual or interaction findings remain.

## Findings

No actionable P0/P1/P2 findings remain. The larger type, taller cards, removed imagery, and simplified top region are intentional outcomes of the approved P0/P1/P2 changes rather than fidelity regressions.

## Open Questions

- The real production footer ownership and support information remains unknown, so the implementation uses a neutral internal-service footer instead of template placeholders.
- Management-page visual QA remains outside this public-page build.

## Implementation Checklist

- [x] Keep the application rooted at `/liuyanban/`.
- [x] Preserve PHP 7.3.4 and MySQL 5.7.26 compatibility.
- [x] Keep public queries limited to approved, visible, non-deleted messages.
- [x] Preserve CSRF, rate limiting, output escaping, session isolation and source-IP recording.
- [x] Retain desktop and mobile screenshots for future regression checks.

## Follow-up Polish

- Production testing should replace the neutral footer with confirmed organization and support information.
- The management pages should reuse the same typography, status colors, spacing, and accessibility rules.

final result: passed
