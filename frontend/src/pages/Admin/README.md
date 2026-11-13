# Staff tools

The frontend uses the existing `/api/admin` contracts. Laravel remains responsible for authorization and validation.

| Workflow | Frontend | Access |
| --- | --- | --- |
| Dashboard totals, selected-window comparisons, CSV | `/admin` | Admin, moderator |
| Search/filter users, roles, suspension/reinstatement, bulk suspension | `/admin/users` | Admin, moderator; role changes and deletion admin only |
| Member profile/activity, linked providers, sessions, timed suspension/reason, staff notes, sign out everywhere, avatar/bio removal, verification resend, rating purge | `/admin/users/:id` | Admin except self; moderators may act on plain members only |
| Review queue, author/film/media filters, bulk approval/rejection | `/admin/reviews` | Admin, moderator |
| Full review, author history, reports, spoiler flag, approval/rejection, permanent deletion | `/admin/reviews/:id` | Admin, moderator |
| Report queue, content/reporter/status/reason filters, resolution notes, dismiss/resolve one or all open reports on a target | `/admin/reports`, `/admin/reports/:id` | Admin, moderator |
| Star ratings, distribution, film summary and burst detection | `/admin/ratings`, `/admin/ratings/films/:id`, `/admin/ratings/bursts` | Admin, moderator |
| List search/detail, owner/visibility/report filters, make private, remove movie/TV title, delete list | `/admin/lists`, `/admin/lists/:id` | Admin, moderator; deletion admin only |
| Room search/detail, lock/report/visibility/creator/film filters, members/messages/reports, message deletion, lock/unlock, delete room | `/admin/rooms`, `/admin/rooms/:id` | Admin, moderator; deletion admin only |
| Movie/TV genre names, hidden flags, positions, resets | `/admin/genres` | Moderator read only; admin edits |
| Registration, review approval/classification, room creation, announcement message/level/expiry | `/admin/settings` | Admin only |
| Audit action, actor and subject filters; recorded changes | `/admin/audit-log` | Admin only |
| Existing movie catalogue editing and overrides | `/admin/movies` | Admin only |

Pagination, loading, empty and retry states are provided for the new queues. Bulk responses report individual outcomes, including skipped/failed items. Destructive actions require confirmation and submissions are disabled while pending. Private-room messages are available only while an open room/message report exists. List details show the backend's first 100 items; room details show up to 200 members.

The member room client consumes `message.deleted`, drops retained copies and prevents an older in-flight sync from restoring deleted text. It also displays staff locks and pauses posting until the lock is lifted.

## Verification

- `npm test`: includes route-permission, filter-encoding, report-destination, bulk-outcome, mutation-payload and room-deletion socket tests.
- `npm run build`: TypeScript and production bundling.
- `npm run test:admin-browser`: Chrome smoke tests against mocked API responses. Start Vite first (`npm run dev`). Uses an isolated temporary Chrome profile, intercepts API calls, renders all 18 admin routes, verifies moderation requests and moderator restrictions, and checks mobile overflow. It never changes backend data.
- Set `ADMIN_QA_URL` to use a different Vite URL and `CHROME_PATH` to use a different Chrome executable. The defaults are `http://localhost:5173` and the macOS Chrome location (or `google-chrome` on Linux). Screenshots are written to the OS temporary directory.
