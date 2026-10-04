# Troubleshooting — problems we hit and how they were fixed

Run commands from the project folder. If `docker` isn't found, run this first:

```bash
export PATH="$HOME/.docker/bin:$PATH"
```

---

## Browsers (local site)

### Safari still shows the WordPress “W” in the tab
Chrome and Firefox show the chalice. The files are correct (`/favicon.ico` and `/apple-touch-icon.png` both return the Emerson icon).

Safari’s **tab** icon is not the same as Chrome’s. Safari uses `<link rel="mask-icon">` with a one-colour SVG (`assets/images/safari-pinned-tab.svg`). The regular `.ico` / PNG only covers the address field and other browsers ([Stack Overflow](https://stackoverflow.com/questions/68885882/favicon-not-displaying-on-safari)).

Safari 27 on this Mac also loaded the homepage and **did not request any favicon URL**. It can keep the WordPress “W” it stored when `/favicon.ico` used to redirect to `w-logo-blue.png`. A Private Window uses that same store.

**To make Safari fetch the new icon:**

1. **Safari → Settings → Tabs** and turn on **Show website icons in tabs**. If this is off, no site (including this one) shows a tab icon. This is the usual reason wiping the cache does nothing.
2. Quit Safari completely (Cmd+Q).
3. In Finder, **Go → Go to Folder** (Cmd+Shift+G) and open `~/Library/Safari/Favicon Cache`. If that folder exists, move it to the Trash. Also check `~/Library/Safari/Touch Icons Cache`.
4. Reopen Safari and go to `http://localhost:8080/favicon.svg` (you should see the chalice), then `http://localhost:8080/`.
5. `mask-icon` is only for a **pinned** tab (drag the tab to the left of the tab bar). Regular tabs use `rel="icon"`.

If the cache folder is missing or macOS blocks it, the live HTTPS site is a new origin and should pick up the icon after launch. Local Safari may keep the “W” until Apple’s store expires. This is a Safari bug, not a missing file.

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

---

## Email

### Local testing sends real email
The restored database has the church's real SMTP login in WP Mail SMTP.

`wp-content/mu-plugins/local-mail-catcher.php` stops all email when the site address is `localhost` and saves each message to `wp-content/local-mail/`. If real email ever goes out locally, check that the file is still there and that the site address is still `http://localhost:8080` (**Settings → General**).

### Testing real email locally (redirect mode)
The catcher has two modes:

- **Save only** (default): nothing is sent.
- **Redirect**: email is sent for real through the church's NetSol account, but only to one test address. CC and BCC copies are removed, and the subject starts with `[LOCAL TEST → original recipient]`. A copy is still saved in `local-mail/`.

```bash
export PATH="$HOME/.docker/bin:$PATH"
# Redirect mode on (use your own address):
docker compose run --rm -T wpcli option update emerson_local_mail_redirect_to you@example.com
# Back to save only:
docker compose run --rm -T wpcli option delete emerson_local_mail_redirect_to
```

Switch back to save only before making the WPvivid backup. The mode would do nothing on the live site anyway, because the catcher only runs on `localhost`.

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
- **for 30 days after you close it**, and **for good after you sign up**. The browser remembers this under `localStorage` → `emersonNewsletter`.

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

### Images hotlinked from another site
Images loading from `images.squarespace-cdn.com` or the dead dev server `emersondev1.bloomenterprises.org` break when those sites go away. Import them into the Media Library instead (see `CHANGELOG.md`).

When downloading from Squarespace, add `-H 'Accept: image/jpeg'` to `curl` to get the original JPEG instead of WebP.

### Still broken
Three images on Welcome! (page 412) are on the dead dev server and can't be recovered locally:
- `emerson-building.jpg`
- `service.png`
- `hands-hearts-1024x432.png`

Waiting on the sitemaster for copies.
