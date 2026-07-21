# Newsletter

Public newsletter signups, double opt-in, plus the admin list behind them. An address is stored the moment somebody types it, but it is not a subscriber until the person who owns that mailbox confirms from an emailed link.

**Status:** ✅ Implemented — [roadmap](../roadmap.md) Phase 7.

This is the first domain in the API that **sends a link rather than a code**, and therefore the one that introduces `FRONTEND_URL`. Every earlier phase emailed a 6-digit OTP precisely because there was no address to send anyone to; a newsletter recipient may have no account and no screen open, so a code cannot work here.

## Endpoints

| Method | Path | Auth |
| --- | --- | --- |
| POST | `/api/v1/newsletter/subscribe` | public, `throttle:5,1` |
| POST | `/api/v1/newsletter/confirm/{token}` | public (the token is the proof), `throttle:10,1` |
| POST | `/api/v1/newsletter/unsubscribe/{token}` | public (the token is the proof), `throttle:10,1` |
| GET | `/api/v1/admin/newsletter` | `auth:sanctum` + `role:admin` |
| DELETE | `/api/v1/admin/newsletter/{subscriber}` | `auth:sanctum` + `role:admin` |

**`confirm` and `unsubscribe` are POST, deviating from the roadmap's `GET`.** MealHub used GET because a mail client opened those URLs directly. Here the emailed link lands on the React app, which then calls the API — so nothing requires these to be reachable by a browser following a URL, and the verb is free to be the correct one for a state change. A GET would additionally be confirmable by anything that follows a link: a crawler, a link preview, a mail-client prefetch.

**The admin `DELETE` takes an id and carries no Policy**, which is the documented exception rather than an oversight. A subscriber has no owner to check the caller against, so `role:admin` is the entire authorization question. Phase 10's CMS records are the same shape. Anything that *does* have an owner still needs a Policy — see [notifications](notifications.md).

## Request / Response

- Validated by: `App\Http\Requests\Newsletter\SubscribeRequest` (the two token endpoints take no body)
- Shaped by: `App\Http\Resources\NewsletterSubscriberResource`
- Business logic: `App\Services\NewsletterService`
- Data access: `App\Repositories\NewsletterSubscriberRepository`
- Mail: `App\Notifications\NewsletterConfirmationNotification`
- Controllers: `Api\V1\NewsletterController`, `Api\V1\Admin\NewsletterController` — **the first controller under `Api/V1/Admin/`**

`POST /newsletter/subscribe` deliberately returns **no data**, only a fixed message (see the oracle rule below). The two token endpoints answer with the subscriber; `GET /admin/newsletter` is a [paginated response](api-conventions.md) at 20 rows a page; `DELETE` answers `204` with an empty body.

One subscriber looks like this:

```json
{
  "id": 7,
  "email": "reader@example.com",
  "status": "confirmed",
  "is_mailable": true,
  "confirmed_at": "2026-07-21T09:14:22.000000Z",
  "unsubscribed_at": null,
  "created_at": "2026-07-21T09:12:03.000000Z"
}
```

- **`token` is never exposed**, on either surface. It is a bearer credential — whoever holds it can confirm or unsubscribe that address without proving anything else — so it belongs in the email it was mailed in and nowhere else. In the admin list it would be one screenshot away from leaking every address's credential. Two tests assert it never reaches the wire.
- **`status` and `is_mailable` are the model's derived attributes, not columns.** They are computed from the two timestamps so they cannot disagree with them, and so no client reimplements "confirmed but not since unsubscribed" and gets half of it right.
- **One Resource serves both surfaces.** The public caller already holds the token from the mailbox the address belongs to, so echoing that address back discloses nothing it did not arrive with.

## Business Rules & Edge Cases

- **The signup endpoint must never become a membership oracle.** New address, already pending, and already confirmed all answer *byte-identically* — same status, same message, no data. Anything that distinguishes them lets an anonymous caller ask "is this person subscribed?" about anybody. This is why `NewsletterService::subscribe()` returns `void`, why the message is a controller constant rather than three strings, and why `SubscribeRequest` carries no `unique` rule (a uniqueness error would announce membership as a validation failure).
- **Resubmitting a confirmed address sends nothing.** Otherwise anyone could mail an arbitrary confirmed address on demand just by resubmitting it. The response is still the same sentence.
- **Resubscribing after unsubscribing re-confirms from scratch.** The opt-out may well have been the address's real owner, so consent is asked for again rather than silently reinstated — `confirmed_at` and `unsubscribed_at` are both cleared and a fresh confirmation goes out.
- **Both token actions are idempotent, and neither timestamp ever moves.** Mail clients prefetch links and people forward them, so a second call is a normal event, not an error. `confirmed_at` records when consent was actually given; a retry must not overwrite it.
- **An unknown token is a `404`** carrying one message — "This link is not valid. It may have already been used, or never issued." — which says nothing about which of never-issued, mistyped or since-erased applies. It is a `DomainException` from the service, not an `abort()`.
- **The token is never cleared, including on confirm.** It serves the unsubscribe link that every subsequent mailing has to carry; clearing it on confirm would strand confirmed subscribers with no way out, which is exactly backwards.
- **Unsubscribing keeps the row; only an admin delete removes it.** Keeping it is what stops a later signup quietly resurrecting a membership the owner opted out of. Erasure is the separate, admin-only path, for someone asking to be forgotten entirely — after which a later signup for that address starts clean.
- **The unsubscribe link is offered in the confirmation email itself**, before confirming. The address may have been typed in by somebody else, and that person deserves a one-click way out that does not require opting in first.
- **There is deliberately no admin "add subscriber".** An admin typing in someone else's address is precisely the consent problem double opt-in exists to prevent; such a row would either bypass confirmation or send an unsolicited confirmation mail. Signups come from the person who owns the address, or not at all.
- **Email is lowercased in the service before lookup or insert**, matching the column's casing convention — it is what stops one address occupying two rows.
- **Throttling is per IP** (Laravel's default keying for guests). Signup is the tighter limit at 5/minute because it is the one that costs a real person an unsolicited email; guessing a 64-character token is not a threat the 10/minute on the other two is answering, repeated automated calls are. A throttled call comes back in the standard envelope with `retry_after`, from the handler in `bootstrap/app.php` — no per-route response shaping was needed, unlike MealHub's named limiter.
- **The admin list is ordered `latest()` in the repository**, not left to `BaseRepository::paginate()`. An unordered paginated list is not stable across pages: a row inserted mid-read can push another onto a page the client already fetched.
- **Tokens are minted in a uniqueness loop.** The column is unique, so a collision would surface as a database error on an otherwise valid signup. At 64 random characters that is vanishingly unlikely, but one indexed `exists()` removes the possibility.

### Configuration

`FRONTEND_URL` is where the React app is served, and every link this API puts in an email points there rather than at the API — a recipient who clicks an API address gets raw JSON and no way to act on it. The SPA renders a page for `/newsletter/confirm/{token}` and `/newsletter/unsubscribe/{token}` and calls the API from there.

```dotenv
FRONTEND_URL=http://localhost:5173
```

Read through `config('app.frontend_url')`, never `env()` directly, so a cached config still resolves it. It falls back to `app.url` when unset, so a same-origin deployment still produces an absolute link rather than a relative fragment no mail client can follow. Trailing slashes are stripped at the point of use.

## Tests

`tests/Feature/Newsletter/NewsletterSubscriptionTest.php` (17) — the pending-until-confirmed round trip, lowercasing, validation failures, the byte-identical repeat signup, silence on a confirmed resubmit, both idempotent token actions, `404` on an unknown token, resubscribe-after-unsubscribe, the token never reaching a response, the emailed links pointing at `FRONTEND_URL`, and the rate limit.

`tests/Feature/Newsletter/AdminNewsletterTest.php` (15) — the paginated list and its derived statuses, newest-first ordering, a query-count assertion, erase and its `404`, `401` unauthenticated on both endpoints, and `403` for each of the three non-admin roles on both endpoints.
