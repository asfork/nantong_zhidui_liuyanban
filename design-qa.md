# Design QA — 留言管理页面

## Comparison Target

- Source visual truth: `audit/figma-liuyan-admin/01-admin-management.png`
- Batch-management workflow baseline: `artifacts/batch-management-audit/02-selected.png`
- Implemented route: `http://127.0.0.1:8088/liuyanban/admin/`
- Desktop viewport: `1536 × 1024`
- Additional responsive viewports: `820 × 900` and `390 × 844`
- State: authenticated administrator, normal-message filter, batch-selected messages #10 and #7, no unsaved reply text

## Evidence

- Final desktop implementation: `artifacts/admin-design-qa/06-admin-final-desktop-1536.png`
- Full desktop comparison: `artifacts/admin-design-qa/07-reference-final-comparison.png`
- Final detail and reply region: `artifacts/admin-design-qa/08-admin-final-detail-1536.png`
- Focused detail comparison: `artifacts/admin-design-qa/09-detail-comparison.png`
- Tablet implementation: `artifacts/admin-design-qa/04-admin-tablet-820.png`
- Mobile implementation: `artifacts/admin-design-qa/05-admin-mobile-390.png`
- Batch toolbar desktop initial state: `artifacts/batch-management-implementation/01-desktop-initial-1536.png`
- Batch toolbar desktop selected state: `artifacts/batch-management-implementation/02-desktop-selected-1536.png`
- Batch toolbar tablet selected state: `artifacts/batch-management-implementation/03-tablet-selected-820.png`
- Batch toolbar mobile selected state: `artifacts/batch-management-implementation/04-mobile-selected-390.png`
- Batch toolbar normalized selected state: `artifacts/batch-management-implementation/05-selected-1253x705.png`
- Batch toolbar full comparison: `artifacts/batch-management-implementation/06-full-comparison.png`
- Batch toolbar focused comparison: `artifacts/batch-management-implementation/07-toolbar-comparison.png`

The full-view comparison places the 1536 × 1024 source and implementation on one canvas. The implementation intentionally uses larger rows and controls, so the detail editor sits below the first desktop viewport; the focused comparison evaluates that region separately.

The batch-management comparison additionally places the before and after selected states side by side at the same 1253 × 705 viewport. The focused comparison isolates the toolbar so action visibility, selected-count hierarchy and danger styling can be judged directly.

## Required Fidelity Surfaces

### Fonts and typography

- The implementation preserves the source's Chinese sans-serif administrative tone through Microsoft YaHei, PingFang SC and Noto Sans CJK fallbacks.
- Table text remains compact but is larger than the source to improve legibility; headings, status labels and selected-row emphasis retain the original hierarchy.
- No clipped headings, broken control labels or unreadable status text were found at the tested widths.

### Spacing and layout rhythm

- The dark-blue header, pale-blue table head, thin table dividers and dense desktop grid match the source language.
- The desktop filter row was tightened after the first QA pass so all filters and actions fit on one row at 1536px.
- Larger targets and row spacing intentionally move the detail editor below the fold instead of reproducing the source's very small text and links.
- At 820px and 390px, the page body does not overflow horizontally; only the table's labeled scroll container scrolls horizontally.

### Colors and visual tokens

- Existing public-page blue, neutral, green, amber and red tokens are reused.
- Audit, reply, display and deletion states use both text and bordered background treatments, not color alone.
- Selected rows use a pale-blue fill and left accent consistent with the source's active treatment.
- Active batch mode adds a pale-blue sticky toolbar, while the destructive recycle-bin action remains red and all other states reuse the established administrative palette.

### Image quality and asset fidelity

- The source management page contains no required photographic or illustrative assets.
- No placeholder imagery, custom SVG, CSS illustration, emoji or approximated icon asset was introduced.
- Native form controls are used for date and selection behavior.

### Copy and content

- The original terms are retained where safe, while ambiguous or destructive copy is corrected: “删除” becomes “移至回收站”, “保存回复” becomes “保存草稿”, and the public condition is displayed explicitly.
- Audit, reply, display and deletion states are named independently.
- Reply help text clearly states that publishing a reply does not automatically change audit or display status.

## Interaction Verification

- Authentication: passed with the PHP 7.3 bcrypt development account; unauthenticated management access redirects to login.
- Status filtering: passed; selecting “待审核” returns exactly the seeded pending message and preserves the selected filter in the URL.
- Current-row context: passed; the selected row and “正在处理 #ID” detail heading agree.
- Batch selection: passed; the selected count and row highlights update immediately, all relevant actions enable after selection, and “取消选择” clears the state.
- Batch action discoverability: passed; normal messages expose approve, reject, show, hide and recycle-bin actions as direct buttons, while the recycle-bin filter exposes only restore.
- Batch action submission: passed end to end; seeded message #8 was batch-shown and batch-hidden again, the final state is hidden, and both operations were written to the operation log.
- Static asset refresh: passed; CSS and JavaScript URLs include file modification versions so older cached batch scripts do not override the new interaction.
- Draft/publish separation: passed; a draft is excluded from the public “已回复” filter, while publishing makes the reply visible without automatically changing audit or display state.
- Soft deletion: passed; moving a test message to the recycle bin removes it from the public page and restoration clears `deleted_at`.
- Operation logging: passed for login, audit, reply, hide, soft-delete and restore paths.
- Responsive overflow: passed at 820px and 390px; the body width equals the viewport content width.
- Browser console errors: none.

## Comparison History

### Iteration 1

Findings:

- P0: the source did not represent audit state separately and treated deletion as irreversible.
- P0: reply publishing was coupled to external display.
- P1: row actions were crowded, current selection was unclear, batch selection had no count, and reply save/publish wording was ambiguous.
- P2: narrow-screen title layout was cramped and the initial desktop filter grid used unnecessary vertical space.

Fixes:

- Added independent audit, reply, display and deletion states, soft deletion/recovery, operation logs and explicit public eligibility.
- Reduced each row to a single “处理” action and moved state-changing actions into the selected detail region.
- Added selected-row styling, batch count/disabled states, reply drafts, reply history and message operation history.
- Tightened the desktop filter grid and stacked the title row on small screens.

### Iteration 2

Post-fix evidence:

- `06-admin-final-desktop-1536.png` shows the compact single-row desktop filters and clear selected-row state.
- `08-admin-final-detail-1536.png` shows independent state actions, source IP visibility, reply draft/publish controls and history panels.
- `04-admin-tablet-820.png` and `05-admin-mobile-390.png` show contained table overflow and usable filter controls.

No actionable P0, P1 or P2 findings remain.

### Iteration 3 — Batch management P0

Earlier finding:

- P1: the checkbox supported selection, but the actual actions were hidden in a low-emphasis select-and-apply control, so the workflow could be mistaken for unfinished functionality.

Fixes:

- Replaced the action dropdown with visible approve, reject, show, hide and recycle-bin buttons.
- Added an always-visible instruction, selected count, 100-item limit, active sticky treatment, selected-row treatment and cancel-selection action.
- Scoped normal-message and recycle-bin actions to the current data range.
- Added asset URL versioning to prevent stale JavaScript or CSS after deployment.

Post-fix evidence:

- `06-full-comparison.png` shows the complete before/after selected flow at the same viewport.
- `07-toolbar-comparison.png` shows that the selected count and available actions are now visible without opening another control.
- `03-tablet-selected-820.png` and `04-mobile-selected-390.png` show wrapped action buttons without body-level horizontal overflow.

No actionable P0, P1 or P2 findings remain after the batch-management pass.

## Findings

No actionable P0/P1/P2 visual or interaction findings remain. The detail editor being below the first viewport is an intentional accessibility trade-off caused by larger text, controls and table rows.

## Open Questions

- Production integration still requires the real `admin` field mapping, password algorithm and allowed `user_type` values.
- The final phpStudy port, extensions and Windows deployment smoke test remain deployment-stage checks.

## Implementation Checklist

- [x] Preserve `/liuyanban/` in routes, assets, forms, redirects and Session Cookie Path.
- [x] Keep PHP 7.3.4 and MySQL 5.7.26 compatibility.
- [x] Separate audit, reply, display and deletion state.
- [x] Use soft deletion and recovery.
- [x] Keep drafts out of public replies.
- [x] Protect writes with authentication, CSRF validation and prepared statements.
- [x] Record administrator operations.
- [x] Verify desktop, tablet and mobile rendering in the in-app browser.
- [x] Make batch actions directly discoverable after selection.
- [x] Restrict recycle-bin batch actions to restoration.
- [x] Version static asset URLs to invalidate stale browser caches.

## Follow-up Polish

- Confirm the production administrator adapter before deployment.

final result: passed
