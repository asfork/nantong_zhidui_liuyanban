# Design QA — 公开留言板政务蓝 Hero

## Comparison Target

- Source visual truth: `artifacts/design-qa/07-reference-top.png`
- Source target crop: `artifacts/design-qa/12-reference-blue-header.png`
- Implemented route: `http://127.0.0.1:8088/liuyanban/`
- Desktop test viewport: requested `1440 × 900`; browser capture `1425 × 891`; Hero CSS box `1120 × 220`
- Mobile test viewport: requested `390 × 844`; effective content viewport `375 × 844`; capture `375 × 812`; Hero CSS box approximately `351 × 378`
- State: public board default filter with seven visible test messages

## Evidence

- Generated project asset: `public/assets/images/hero-government-blue-v2.jpg` (`1983 × 793`, progressive JPEG)
- Final desktop implementation: `artifacts/design-qa/15-page-blue-v2-desktop.jpg`
- Final desktop focused Hero: `artifacts/design-qa/17-hero-blue-v2-desktop.jpg`
- Final mobile implementation: `artifacts/design-qa/16-page-blue-v2-mobile.jpg`
- Combined source/implementation comparison: `artifacts/design-qa/14-hero-blue-comparison.jpg`
- Building-position before/after comparison: `artifacts/design-qa/18-hero-building-position-comparison.jpg`

The comparison image places the reference government-blue header crop above the implemented Hero. The implementation crop was normalized from `1120 × 220` to `864 × 170`; density was 1 CSS pixel per captured pixel. The reference crop remains `864 × 104`. The different heights are intentional because the existing Hero also contains two explanatory paragraphs and a primary action.

## Required Fidelity Surfaces

### Fonts and typography

- Existing Microsoft YaHei, PingFang SC and Noto Sans CJK SC fallbacks remain unchanged.
- White title and body copy preserve a clear hierarchy over the blue image; text shadow is restrained and used only to protect legibility.
- Desktop and mobile captures show no clipped headings or unintended wrapping.

### Spacing and layout rhythm

- Desktop Hero remains aligned to the existing `1120px` content grid and uses the established card radius.
- The action remains on the right at desktop width and becomes a full-width control below the copy on mobile.
- The mobile body has no horizontal overflow: document `scrollWidth` equals viewport width (`375px`).

### Colors and visual tokens

- The asset matches the reference's navy-to-cobalt government portal palette with restrained cyan highlights.
- White foreground text and the white primary action maintain strong contrast against the dark left and blue right regions.
- The previous white Hero surface and blue top border have been replaced by the requested image treatment.

### Image quality and asset fidelity

- A real local raster image is used rather than CSS illustration, SVG approximation or remote content.
- The image contains the required low-contrast city and civic architecture silhouette, quiet left text area and brighter right-side depth.
- In V2, the tallest rooftop begins around the image midpoint and the rendered skyline is concentrated along the Hero's bottom edge, keeping architecture out of the main reading area.
- The production asset is a progressive `1983 × 793` JPEG compressed to about `72KB`, suitable for offline intranet deployment and responsive cover cropping.
- Mobile background positioning keeps the copy over the darker portion of the image and the buildings below the copy.

### Copy and content

- Existing page title, legal notice, audit explanation and “我要留言” action are unchanged.
- Search, navigation and reference-site branding were not reintroduced, consistent with the earlier page scope.
- The generated background contains no text, logo, emblem, watermark or UI controls.

## Interaction Verification

- Background asset loaded from `/liuyanban/assets/images/hero-government-blue-v2.jpg`.
- “我要留言” resolves to exactly one link and scrolls to `#message-form`.
- Desktop and mobile rendering passed in the Codex in-app browser.
- Browser console errors: none.
- No horizontal overflow at the mobile breakpoint.

## Comparison History

### Iteration 1

- The first rendered desktop and mobile captures matched the target art direction.
- No P0, P1 or P2 differences were found for the requested background change.
- No follow-up visual correction was required.

### Iteration 2 — Building visual weight

Earlier finding:

- P2: the skyline occupied the center-right of the rendered Hero and competed with the notice copy and primary action.

Fix:

- Moved the full skyline and civic architecture group into the bottom third without changing the palette, lighting, left-side negative space or page layout.
- Switched the page to the non-destructive V2 asset and retained the original rendered capture for comparison.

Post-fix evidence:

- `18-hero-building-position-comparison.jpg` shows the original implementation above and V2 below at the same `1120 × 220` Hero size.
- `15-page-blue-v2-desktop.jpg` and `16-page-blue-v2-mobile.jpg` confirm that the building group no longer occupies the main reading region.

No actionable P0, P1 or P2 findings remain after the second pass.

## Findings

No actionable P0/P1/P2 visual or interaction findings remain. The generated skyline is slightly more detailed than the reference's translucent silhouette, but its lower position and reduced visible area prevent it from competing with the foreground copy.

## Open Questions

- None for this change.

## Implementation Checklist

- [x] Use a local offline image asset.
- [x] Preserve `/liuyanban/` asset resolution.
- [x] Match the government-blue reference art direction.
- [x] Preserve readable HTML copy and the existing action.
- [x] Verify desktop and mobile crops.
- [x] Verify the Hero anchor interaction and browser console.

## Follow-up Polish

- No required follow-up.

final result: passed
