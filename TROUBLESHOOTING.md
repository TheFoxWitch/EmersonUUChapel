# Troubleshooting — problems we hit and how they were fixed

Run commands from the project folder. If `docker` isn't found, run this first:

```bash
export PATH="$HOME/.docker/bin:$PATH"
```

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

## Pages and styles

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
