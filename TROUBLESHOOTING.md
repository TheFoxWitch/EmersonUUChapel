# Troubleshooting — problems we hit and how they were fixed

Run commands from the project folder. If `docker` isn't found, run this first:

```bash
export PATH="$HOME/.docker/bin:$PATH"
```

---

## Browsers (local site)

### Open the local site on a phone (same Wi‑Fi)
`localhost` on the phone means the phone, not your Mac. Docker already listens on every interface (`0.0.0.0:8080`).

1. On the Mac, get the Wi‑Fi address: `ipconfig getifaddr en0` (today that is often something like `172.16.3.86`).
2. On the phone’s browser open **`http://THAT-IP:8080/`** (plain `http`, include the port).
3. `emerson-lan-preview.php` (mu-plugin) only rewrites URLs for private LAN IPs. Visits to `http://localhost:8080/` are left alone so CSS/JS keep the `:8080` port. An earlier version rewrote desktop `localhost:8080` links to `http://localhost/` (port 80) and the whole site lost its styles.

If the phone cannot connect: confirm both devices are on the same Wi‑Fi (not guest/client-isolation), and that the Mac firewall is not blocking Docker / incoming connections on port 8080.

### Safari still shows an old tab icon (or the WordPress “W”)
The files are the round green badge, built from `images/EmersonUUChapelLogoOriginal.png`. Every `<link rel="icon">` points at a **root** file (`/favicon.ico`, `/favicon-16.png`, `/favicon-32.png`, `/emerson-site-icon.png`, `/apple-touch-icon.png`, …) with `Cache-Control: max-age=0`. Do not use the copies under `wp-content/plugins/…` in the head — W3 Total Cache was giving those PNGs a one-year cache, so the tab flipped between the new badge and an old hands/drop mark.

**Gutenberg / wp-admin tab wrong or flipping (2026-11-08):** Core `wp_site_icon` was still hooked on `admin_head`, so the editor HTML listed Emerson’s icons and WordPress’s Site Icon tags together. Emerson now removes those core hooks on admin/login too. After updating, hard-refresh the editor tab (or close and reopen it).

Safari often ignores those tags and keeps the icon it already stored for `localhost`. A Private Window uses that same store, so it will not show the new logo either. Closing the window is not enough. Safari has to fully quit.

**To make Safari fetch the new icon:**

1. **Safari → Settings → Tabs** and turn on **Show website icons in tabs**. If this is off, no site (including this one) shows a tab icon.
2. Quit Safari completely (Cmd+Q).
3. In Finder, **Go → Go to Folder** (Cmd+Shift+G) and open `~/Library/Safari/Favicon Cache`. If that folder exists, move it to the Trash. Do the same for `~/Library/Safari/Touch Icons Cache`. On newer macOS those folders may instead be under `~/Library/Containers/com.apple.Safari/Data/Library/Caches`.
4. Reopen Safari and go to `http://localhost:8080/favicon.ico` (you should see the round green badge), then `http://localhost:8080/`.
5. `mask-icon` (`safari-pinned-tab.svg`) is only for a **pinned** tab. Regular tabs use the favicon files.

The live site is `https://emersonuuchapel.org`, a different address from `localhost`, so Safari treats it as a new site and should pick up the logo after launch.

### Firefox will not load localhost
Firefox is almost certainly opening `https://localhost:8080` (HTTPS-Only Mode). This site is plain `http://` in Docker; there is no certificate, so Firefox stops.

1. Type the full address: `http://localhost:8080/`
2. If Firefox still upgrades it: **Settings → Privacy & Security → HTTPS-Only Mode → Manage Exceptions…** and add `http://localhost:8080`.
3. Or, on the error page, choose to continue to the HTTP site if Firefox offers that.

`curl -I http://localhost:8080/` should return `200`. `curl -Ik https://localhost:8080/` failing with a TLS error is expected.

---

## Docker

### `docker-credential-desktop: executable file not found`
Homebrew's standalone `docker` was shadowing Docker Desktop's CLI.

**Fix:**
1. `brew uninstall docker`
2. Put `~/.docker/bin` first on your PATH (in `~/.zprofile` and `~/.zshrc`).

### WP-CLI says the upload limit is 2 MB
Only the `wpcli` container reports this. `uploads.ini` (64 MB) is mounted into the `wordpress` container only, so uploads through wp-admin are fine.

On the live site, the limit comes from the host's PHP settings. Check it under **Media → Add New** or **Tools → Site Health → Info → Media Handling**. Change it in cPanel's MultiPHP INI Editor or a `.user.ini`.

---

### Calendar "This calendar PDF" gives a server error (`cal_days_in_month()` undefined)
Church Admin needs PHP's `calendar` extension, which the stock `wordpress` Docker image doesn't include. The project `Dockerfile` adds it (`docker-php-ext-install calendar`). If the error comes back, check `docker compose exec wordpress php -m | grep calendar`. If it's missing, run `docker compose build wordpress && docker compose up -d wordpress`.

### Typed Event location on a one-off calendar event does not save
Premium’s event form has a location **dropdown** (`location`) and a free-text box (`location1`). The typed box is the right place for “Rotating location — check This Week at Emerson” when it is not already in the dropdown. Upstream Premium then copies `location1` with `==` instead of `=`, so the text never lands in the database. Emerson Chapel fixes that on `admin_init` (`emerson_calendar_fix_premium_location_post` in `inc/calendar-listed-events.php`). Do not patch Church Admin for this.

### Homepage sermon stuck on an old video (e.g. 2024 04 14)
The player prefers a **YouTube channel sync cache**, then Video Gallery. If the Atom feeds fail (both chapel channel IDs were returning 404 from `feeds/videos.xml` in Nov 2026), sync stops updating and an old cached title can sit in `emerson_youtube_homepage_latest` forever.

**Check:** `docker compose run --rm -T wpcli eval 'print_r(get_option("emerson_youtube_sermons_sync_log")); print_r(emerson_latest_sermon_resolved());'`

**Fix:** Emerson now picks the **newer** of cache vs gallery by the dated title, and clears the cache when a sync sees no feed videos. Hard-refresh the homepage. To force gallery-only immediately: `docker compose run --rm -T wpcli eval 'delete_option("emerson_youtube_homepage_latest");'`.

Channels themselves may still need new IDs later if YouTube feeds stay broken; the gallery copy of newer sermons (May/July 2026) is enough for the homepage until then.

### Church Admin “Create user” made a random login, not the person I meant
**People → Create user** (and “create users for GDPR confirmed”) is a **bulk** tool. It builds a username from the person’s name, sets a **random password**, emails it, and does not ask you to pick email/password. Use it only when you intentionally want mass account creation.

For **one** new signup: **Church Admin → People** → open that person → under WordPress user, type a username in **Or create a new Wordpress User** (or link an existing account from **Users → Add New**) → **Save**. Then set their WordPress role under **Users** if they need **Finance** or similar.

Confirming the register email only adds a Church Admin directory row. It does **not** create a row under **Users** until an admin issues the login (the thank-you page says that on purpose).

### "My password is right but I still can't log in" (even after a reset)
Usually it's the **Limit Login Attempts** lockout, not the database. Four failed tries lock that connection out for 20 minutes, and during that time even the correct password is refused. A password reset doesn't lift it.
- **Check:** `docker compose run --rm -T wpcli eval 'print_r(get_option("limit_login_lockouts")); print_r(get_option("limit_login_logged"));'`. The log shows the *username that was typed*.
  - On 2026-10-01 the log showed a shortened name, but that admin account's username is its full email address.
- **Clear it:** wp-admin → **Limit Login Attempts**, or `update_option()` the options `limit_login_lockouts`, `limit_login_retries` and `limit_login_retries_valid` to `array()`.
- **Locally,** every visit comes through Docker's one internal address (`172.18.0.1`), so one person's lockout blocks every local login.

## Pages and styles

### Checking the whole site for broken links
Collect every `href` and `src` inside `<main>` on each published page, plus the header and footer once. Then test each unique address. Beware of two traps:
- macOS `python3` has no certificate bundle, so every `https` check fails (`CERTIFICATE_VERIFY_FAILED`). Set `SSL_CERT_FILE=/etc/ssl/cert.pem`, or check outside links with `curl`.
- Some sites (Cloudflare-protected ones, Poetry Foundation, Intuit) answer scripts with 403 or 429 even though they work in a browser. Treat those as "click to confirm", not broken.

### Every page except the homepage returns an Apache 404
The `.htaccess` file is missing. This happens after a restore or `docker compose down -v`. WP-CLI can't write it, because the `wpcli` container can't write to that location.

**Fix:** Write it from the `wordpress` container:

```bash
docker compose exec wordpress sh -c 'cat > /var/www/html/.htaccess <<"EOF"
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
EOF'
```

### CSS changes don't show up
Try these in order:
1. Hard-refresh the page (`Cmd + Shift + R`).
2. Clear W3 Total Cache. It serves saved copies of pages, which point at old CSS versions:
   ```bash
   docker compose run --rm wpcli w3-total-cache flush all
   ```
   Then empty `wp-content/cache/page_enhanced/`. Page caching is switched off locally, but a fresh restore turns it back on.
3. Check specificity. WordPress's global styles use zero-specificity `:where()` selectors, so `custom.css` normally wins. Inline `style="..."` attributes in page content don't lose to it, though: remove them from the block, or use `!important`.

### A new or renamed file in `patterns/` doesn't render
WordPress caches the pattern list.

**Fix:**
```bash
docker compose run --rm wpcli eval 'wp_get_theme()->delete_pattern_cache();'
```

### A YouTube link shows as plain text instead of a player
Embeds need the watch-page URL, `https://www.youtube.com/watch?v=VIDEO_ID`. They won't work with `/embed/VIDEO_ID` or URLs with extra `%3F...` junk on the end.

### A page's slug came out as `...-2`
Another post (often an old draft) already had that slug. Rename the other post's slug, then set the intended one.

### Community Opportunities used to be `/engage/`
The page slug is **`community-opportunities`**. Old bookmarks to `/engage/` get a 301 from `inc/navigation.php`. If that redirect stops working, check the file is still loaded and that page 68’s slug is still `community-opportunities`.

---

## Modals and Gutenberg Buttons (homepage CTAs)

Custom dialogs and the built-in pledge / recommitment / PayPal openers are driven by **Additional CSS class(es)** on a normal Button block. Do not paste `[emerson_modal …]` inside a Button.

### Gutenberg says the Button has unexpected / invalid content
- **Usual cause:** the CSS class is on the wrapper `<div class="wp-block-button …">` but missing from `<!-- wp:button {"className":…} -->` (or the two disagree). That often follows **Attempt recovery**, or a rename that only updated one side.
- **Looks like a hyphen problem.** Double hyphens (`emerson-modal--…`) are escaped in the block comment and commonly invalidate the block. Use a **single** hyphen: `emerson-modal-{slug}`.
- **Fix:** select the Button → sidebar **Advanced → Additional CSS class(es)** and set the class cleanly, then update/save. Prefer that over **Attempt recovery**. Saving the page also runs a repair that syncs `className` with the div (`inc/modals.php`).
- If the block won’t select (only the link text highlights), open **List View**, select the Button there, or fix the mismatch from the database / re-add the Button.

### I changed a custom dialog’s style/label and the Button became PayPal Fees
- **Cause:** the Button still had class `paypal-fees-button` (often from duplicating the PayPal CTA and only changing the wording). On the front, that class always becomes the fees opener (PayPal text, blue look, fees dialog), so Modals style for the custom dialog never applies.
- **Fix:** set Additional CSS class(es) to `emerson-modal-{slug}` (for SNSD: `emerson-modal-snsd-survery`). Saving the dialog under **Modals** also heals Buttons that still show that dialog’s label but keep a PayPal/pledge class.
- Built-in CTA “learning” will not adopt an `emerson-modal-*` class as the PayPal class anymore.

### A normal link Button becomes the Pledge Card (wrong label / opens the Google form)
- **Cause:** `pledge-card` (and legacy `pledge-form`) is a **modal opener**, not a style-only class. On the front, Modals rewrites that Button into the annual pledge control (`data-emerson-pledge-form`), overwrites the label, and ignores the href. Same trap as `recommitment-form` / `paypal-fees-button`.
- **Why removing the class “breaks” the look:** the outline paint comes from `emerson-cta emerson-cta--outline`, which Modals adds when it rewrites — almost nothing styles bare `.pledge-card` alone.
- **Fix for a real page link with the same outline look:** Additional CSS class(es) = `emerson-cta emerson-cta--outline` (keep your own link + label). Blue fill for newcomer CTAs: `newcomer-button`.
- **Example (2026-11-08):** Community Opportunities (page 68) Newcomer Information Form had class `pledge-card`; switched to `emerson-cta emerson-cta--outline` so it goes to `/contact-newcomer-information/`.

### A Google Form iframe in a dialog is much slower than another
- Same lazy `data-src` pattern for every dialog — the difference is usually the **form URL**.
- A **`forms.gle/…` short link** forces an extra redirect inside the iframe and skips `embedded=true`. Prefer Google’s full **`docs.google.com/forms/…/viewform`** address (Send → `<>`), or paste the short link and let Modals resolve it on embed.
- Anything still slow after that is Google loading that form’s own content.

### Top-level Engage in the nav goes somewhere / submenu still says Engage
- Top-level **Engage** is a dropdown label only (like About / Worship), not a page link.
- The first submenu item is **Community Opportunities** → `/community-opportunities/`. Theme file: `parts/header.html`; live menu also lives in Navigation post **main** (id 710) — keep both in sync if you edit in the Site Editor.

---

## Calendar / Sunday Schedule sheet

### Calendar still shows an old Sunday title after I edit the spreadsheet
- **Usual cause:** fetch failed, so Emerson keeps the last-good cache (and a short cooldown). Check **Emerson → Sunday Schedule → Last fetch** for an error.
- **Missing `gid=`:** the tab id in the URL (`gid=2058833792` for Sunday Morning Schedule). Without it, Google’s CSV export used tab `0` and returned HTTP 400 on this workbook. Keep `gid=` when you paste; `/edit` in the path is fine. Emerson now falls back to that Sunday tab for this known file id if `gid` is omitted.
- **Fix:** paste the browser URL while that tab is open (with `gid=`), Save, then **Refresh from spreadsheet**.

### Members Upcoming events shows the YMCA address twice
- **Cause:** Emerson’s calendar-list decorate injected the Sunday place under the speaker, and Church Admin also printed `Location:` from the same CA `location` field. Not a master-calendar duplicate.
- **Fix (2026-11-08):** `emerson_schedule_decorate_list()` skips the under-speaker place when CA already has `Location:` / the place text (same idea as the month sidebar). Keep using Event Places / CA location as usual.

### Whole site takes ~10 seconds to load
Usually the Sunday Schedule Google Sheet fetch, not Docker RAM. Emerson pulls a CSV from `docs.google.com` for sermon titles / themes. If Google HTTPS times out, older builds waited ~10–15s on **every** page and never cached the failure.

**Check:** `docker compose run --rm -T wpcli option get emerson_schedule_sheet_meta` — look for `cURL error 28` or similar.  
**Fix (2026-11-07):** `inc/sunday-schedule-sheet.php` uses a 4s front-end timeout, a 5-minute failure cooldown, and the last good sheet (`emerson_schedule_sheet_last`). Admin **Refresh** still waits up to 15s. After a cooldown, pages should answer quickly even while Google is down (calendar titles may be stale or empty until the next successful fetch).

### Calendar missing Sunday titles even though Downdetector says Google Docs is up
Downdetector only means Google’s public status page looks fine. This Mac/Docker path can still hang on **TLS to `docs.google.com`** (TCP connects, Client Hello never finishes) while **`sheets.googleapis.com` works**. Emerson’s CSV export URL uses `docs.google.com`, so the calendar stays empty.

**Check from the host:**  
`curl -4 -v --max-time 8 -o /dev/null 'https://docs.google.com/'` — if it stalls after “TLS handshake, Client hello”, that is the block.  
`curl -4 --max-time 8 -o /dev/null -w '%{http_code}\n' 'https://sheets.googleapis.com/'` — a fast non-zero code means the API host is reachable.

**Fix (2026-11-08):** set a **Google Sheets API** key (API enabled in Google Cloud; sheet still Anyone-with-the-link) under **Sunday Schedule → Sheets API key**, or `EMERSON_GOOGLE_SHEETS_API_KEY` in `.env` / Docker. Emerson then reads the same spreadsheet through `sheets.googleapis.com`. Recent cooldown/timeout changes do **not** break a healthy docs export; they only stop a dead path from stalling every page.

---

## Email

### Local testing sends real email
The restored database has the church's real SMTP login in WP Mail SMTP.

`wp-content/mu-plugins/local-mail-catcher.php` stops all email when the site address is `localhost` and saves each message to `wp-content/local-mail/`. If real email ever goes out locally, check that the file is still there and that the site address is still `http://localhost:8080` (**Settings → General**).

### Marketing From overwritten by webmaster@
WP Mail SMTP forces From to `webmaster@emersonuuchapel.org`. Emerson marketing/welcome mail must go through `emerson_marketing_mail_send()` (or a `From: marketing@emersonuuchapel.org` header) in `inc/marketing-mail.php`, which re-points PHPMailer after WP Mail SMTP. Credentials: gitignored `.env` / Docker env (`EMERSON_MARKETING_SMTP_*`). If TLS fails on `smtp.emersonuuchapel.org`, use `netsol-smtp-oxcs.hostingplatform.com` (same cert issue as the branded host). IMAP for clients is `mail.emersonuuchapel.org` (prefer IMAP, not POP) — WordPress send does not use IMAP.

### Testing real email locally (redirect mode)
The catcher has two modes:

- **Save only** (default): nothing is sent.
- **Redirect**: email is sent for real (webmaster SMTP, or marketing SMTP when Emerson marks the send), but only to one test address. CC and BCC copies are removed, and the subject starts with `[LOCAL TEST → original recipient]`. A copy is still saved in `local-mail/`.

```bash
export PATH="$HOME/.docker/bin:$PATH"
# Redirect mode on (use your own address):
docker compose run --rm -T wpcli option update emerson_local_mail_redirect_to you@example.com
# Back to save only:
docker compose run --rm -T wpcli option delete emerson_local_mail_redirect_to
```

Switch back to save only before making the WPvivid backup. The mode would do nothing on the live site anyway, because the catcher only runs on `localhost`.

Before that switch, or before this database is restored onto the live site, clear the backlog of mail that never reached its real recipients. Action Scheduler, WPForms background email, and Church Admin or Zephyr jobs can still be waiting. Once the redirect is gone they go out to the original addresses, which would be a flood. Delete those pending jobs. The text files in `wp-content/local-mail/` are a record only. Do not send them.

### Submitting a form takes about 10 seconds
NetSol's SMTP server is slow: about 7 seconds to connect, encrypt and log in, then the message itself. Anything that waits for `wp_mail()` before answering the visitor feels frozen.

- **Newsletter:** it answers first and sends afterwards (`emerson_nl_release_visitor()` in `inc/newsletter.php`).
- **Newcomer form:** WPForms' "Optimize Email Sending" setting does the same through a background job.
- **For real speed everywhere:** switch WP Mail SMTP to a sending service (Brevo, SendLayer, etc.) instead of the NetSol mailbox.

To time the SMTP steps without sending anything, use WordPress's bundled `PHPMailer\PHPMailer\SMTP` class in `wp eval`. Time `connect()`, `hello()`, `startTLS()`, `authenticate()` and `quit()` separately.

### Background jobs never run locally (WP-Cron, WPForms background email, Site Health loopback)
The site address is `localhost:8080`, but inside Docker Apache listens on port 80, so WordPress can't reach itself. `mu-plugins/local-loopback.php` redirects those requests to the `wordpress` container. If background jobs stop again, check that the file is there and that the web service in `docker-compose.yml` is still called `wordpress`.

### Two caught emails, only one file
Fixed on 2026-09-29. The catcher used to name files by the second and the subject, so two emails with the same subject in the same second overwrote each other. It now adds `-2`, `-3` and so on.

### Testing email failures in "save only" mode
The catcher's `pre_wp_mail` filter always reports success. A test filter that makes email fail has to run **after** it (priority `PHP_INT_MAX`), or the catcher overrides it.

### Old scheduled emails fire all at once
The backup was made in March 2025, so WP-Cron jobs such as Zephyr reminders and the WPForms weekly summary were over a year overdue. They would have run the moment email worked. On 2026-09-29 they were rescheduled to run from that day on their normal timing. If an old backup is ever restored again, run `docker compose run --rm -T wpcli cron event list` and look for events dated in the past before turning email on.

---

## Newsletter sign-up

### The pop-up never appears
It's intentionally hidden:
- **when you're logged in**, so test in a private window;
- **on pages other than the homepage**;
- **for 30 days after you close it**, and **until the Cookies page clears the note after you sign up**. The browser remembers this under `localStorage` → `emersonNewsletter`.

**To reset it:** open DevTools (`Cmd + Option + I`) → Console → `localStorage.removeItem('emersonNewsletter')` → reload.

### "Too many sign-up attempts" while testing
Each connection gets 5 attempts per hour. Clear the limit:

```bash
docker compose run --rm wpcli eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"\\_transient\\_%emerson\\_nl\\_%\"");'
```

### Where did the test emails go?
Locally they're saved in `wp-content/local-mail/`. The confirmation link is inside the "Please confirm your subscription" file.

### JavaScript: `form.action` returns an element, not a URL
If a form contains an input named `action` (WordPress `admin-post.php` forms always do), then `form.action` returns that input. Use `form.getAttribute('action')`. The same applies to inputs named `method`, `submit`, `id` and so on.

---

## Serving dates (Members page)

Members tick the dates they **can** serve (`inc/serving-dates.php`). Church Admin itself only stores "can't serve" dates, so the theme keeps its table filled in: every date a person hasn't ticked, for the next 6 months, counts as can't serve.

### Emerson Monday Filter
- **Symptom:** A member ticks many dates (or Mark all), saves, and only about half stay visible on the calendar / the list looks doubled with Mondays.
- **Cause:** `wp_church_admin_services` can hold an orphan **Sunday Service** on **weekly Monday** with no `site_id`. **Premium Church Admin → Sites & Services** only lists services joined to a site, so that row is invisible in the UI but was still feeding Emerson’s date list.
- **Fix in the plugin:** `emerson_monday_filter()` (hook `emerson_monday_filter`) limits serving form + sync dates to site-linked services, skips a “Sunday Service” with frequency `71`, and **removes** those Monday checkboxes from the Members page HTML (Church Admin still prints every service). wp-admin → **Emerson Monday Filter** shows frequencies, form dates, and orphan rows, and an **On / Off** checkbox (default on, with a warning). See `CHANGELOG.md` 2026-10-29 / 2026-10-30.
- **Turning it off:** only if the office accepts Mondays on the form again, or after fixing Church Admin. Prefer Sites & Services → real Monday service with a site.
- **Optional DB cleanup:** after a backup, delete the orphan row if it still exists (`service_id` 1 locally had `site_id` NULL). Not required for the form once the filter is live.
- **Check frequencies in use:** open **Emerson Monday Filter** in the admin sidebar, or `docker compose run --rm -T wpcli eval 'print_r(emerson_monday_filter());'`

### Choose person shows the wrong name / “missing” serving dates
- **Symptom:** Choose person → **Alice Metz** only shows a few ticked Sundays; another test user has many more. Looks like the database did not save.
- **Cause (2026-10-30):** `emerson_account_name()` prefers **WordPress first + last name** when a CA person has a `user_id`. Local test logins had **swapped** profile names: Alice’s admin test login (user #46 ↔ person #83) was labeled Fox Metz; the intern Contributor (user #47 ↔ person #84) was labeled Alice Metz. Picking “Alice Metz” edited person **#84**’s answers. Person #83 really only had three Sundays as `can_serve = 1`.
- **Why it happened:** WP users and CA people were created separately (admin login auto-person vs hand-made “Fox Metz” test person). Friendly names were typed on the **wrong** WP profile later; CA directory names and WP names drifted.
- **Fix:** Align both sides (done locally 2026-10-30): user #46 / person #83 = **Alice Metz**; user #47 / person #84 = **Fox Metz**. Trust **login email** + `people_id`, not the dropdown label alone.
- **Playbook:** `~/.cursor/AGENT_MANUAL.md` → Local development → “WordPress + Church Admin: names on Choose person”.

### Choose person → Fox still shows “3 dates”
- **Symptom:** Logged in as Alice, Choose person → Fox Metz, the page still says only three Available dates.
- **Cause:** The block under **My serving schedule** is always the **logged-in** person by design. Fox’s ticks are on the checkboxes under **Tick the dates Fox Metz can serve**.
- **Fix (2026-10-30):** That form section now includes Fox’s own summary (and a note when you are editing someone else). Hard-refresh Members. Fox currently has **eight** Sundays checked in the DB (Mondays from the old duplicate service stay hidden by Emerson Monday Filter).

### Audit: other users with Alice/Fox-style name confusion
Ran 2026-10-30 over all CA people with a `user_id` (39 linked):
- **No** duplicate Choose person labels
- **No** WP first+last ≠ CA directory name mismatches
- **No** missing WP users for a linked `user_id`
- **No** empty labels
- **Orphan Monday answer rows** (cleaned locally 2026-10-30): deleted all `DAYOFWEEK(serve_date)=2` rows from `wp_emerson_serving_answers` and matching `wp_emerson_serving_roles`, then `emerson_serving_sync()`. Sunday ticks left as-is. If Mondays creep back before the orphan CA service is gone, repeat that delete + sync.
Re-check after adding test logins: compare WP profile first/last to CA `first_name`/`last_name` for the same `user_id`, and ensure Choose person labels are unique.

- **Someone says they ticked a date but auto-fill still skips them.** Check their answers: `docker compose run --rm -T wpcli eval 'global $wpdb; print_r($wpdb->get_results("SELECT * FROM {$wpdb->prefix}emerson_serving_answers WHERE people_id = 80"));'` (use their Church Admin person number). Then re-run the sync for everyone: `docker compose run --rm -T wpcli eval 'emerson_serving_sync();'`
- **An admin changed someone's dates on Church Admin's wp-admin screen (Schedules → Not available) and the change disappeared.** That screen edits Church Admin's table directly, and the daily sync puts it back to match the Members page answers. Use **Choose person** on the Members page instead.
- **To go back to Church Admin's normal "can't serve" form:** first run `docker compose run --rm -T wpcli eval 'echo emerson_serving_undo();'`. It removes the upcoming "not answered" rows, keeps every real "can't serve" answer, and stops the daily job. Then remove the `require_once` line for `serving-dates.php` from `functions.php`. If you skip the undo step, everyone stays blocked on every unanswered date.
- **Saving the database with `wp db export` fails** with `caching_sha2_password could not be loaded`: the wp-cli container's MySQL client can't log in to MySQL 8. Export from the database container instead: `docker compose exec -T db sh -c 'mysqldump --no-tablespaces -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" TABLE_NAME' > backup.sql`

## Editing content with WP-CLI

### zsh: a command stored in a variable doesn't run (`$W post get ...`)
zsh doesn't split variables into words. Use a function instead:

```bash
w(){ docker compose run --rm -T wpcli "$@"; }
```

### Updating page content from a file
```bash
w post get ID --field=post_content > backups/page-ID-before.html
# edit a copy, then:
w post update ID - < edited.html
```

**Always save a backup first.** WP-CLI edits run without the `unfiltered_html` permission, so WordPress can strip some HTML (forms, scripts, iframes). Check the page afterwards.

### `sed: RE error: illegal byte sequence`
Non-ASCII characters (curly quotes, em dashes) in page output upset macOS `sed`. Prefix the command with `LC_ALL=C`.

---

## Images

### A fixed image still looks wrong in one browser
Browsers don't load the full-size file. They pick one of WordPress's resized copies from the image's `srcset` (for example `-768x763.jpg`) and keep it in their cache. After you replace an image and run `wp media regenerate <id>`, a browser that viewed the page earlier can keep showing its old saved copy. Meanwhile a browser opening the page for the first time, such as Safari, shows the new one.
- **Check the files themselves first**, with no browser involved: see the GD pixel check in the CHANGELOG entry for "Love at the Center" (2026-09-30).
- **Then refresh the browser's copy:**
  - Safari, Chrome, Firefox: Cmd+Shift+R.
  - The Cursor browser: its cache can't be cleared from the agent tools. Re-downloading each `srcset` URL with `fetch(url, {cache: 'reload'})` and then reloading works.

### Re-uploading the same filename still shows the old picture
W3 Total Cache sets `Cache-Control: max-age=31536000` (one year) on files under `wp-content/uploads/`. Deleting and re-uploading `community_flame.png` keeps the same URL, so the browser is allowed to keep the old bytes for up to a year — it looks like WordPress “overwrote” your new file with the old one.
- **Fix for one image:** save under a new filename (for example `community_flame-v2.png`), regenerate thumbnails, and point the page at that file.
- **Ongoing:** Performance → Browser Cache → “Prevent caching after file change” for other/media (`browsercache.other.replace`) rewrites media URLs when the file’s mtime changes. Still prefer a new filename for a deliberate art swap so every visitor gets the new URL.
- Church Leadership’s flame graphic is attachment **1184** (`community_flame-v2.png`). Backup of the previous full file: `backups/images/community_flame-before-2026-10-26.png`.

### Images hotlinked from another site
Images loading from `images.squarespace-cdn.com` or the dead dev server `emersondev1.bloomenterprises.org` break when those sites go away. Import them into the Media Library instead (see `CHANGELOG.md`).

When downloading from Squarespace, add `-H 'Accept: image/jpeg'` to `curl` to get the original JPEG instead of WebP.

### Still broken
Three images on Welcome! (page 412) are on the dead dev server and can't be recovered locally:
- `emerson-building.jpg`
- `service.png`
- `hands-hearts-1024x432.png`

Waiting on the sitemaster for copies.
