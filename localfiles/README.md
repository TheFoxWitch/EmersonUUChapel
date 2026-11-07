# Live host: `localfiles/` (private spreadsheets)

On hosting where everything must sit inside `htdocs` / `public_html`, create this folder in the **WordPress root** (sibling of `wp-content`), copy in the finance files, and keep the deny `.htaccess` here.

**Not Media / uploads.** Do not add `localfiles` as a WPvivid extra backup folder, and do not leave zip backups of these files in a public path.

## Setup

1. Create `localfiles/` next to `wp-content` (this folder’s contents are a template).
2. Keep `.htaccess` so Apache returns **403** for `https://yoursite/localfiles/` and any filename under it.
3. Prefer this line in live `wp-config.php` (above “That’s all, stop editing!”) when `wp-config.php` is in the web root:

   ```php
   define( 'EMERSON_PRIVATE_DIR', __DIR__ . '/localfiles' );
   ```

   If `wp-config.php` lives **above** the web root, point at the web root’s `localfiles` instead, for example `__DIR__ . '/public_html/localfiles'` (use the host’s real folder name).

4. Emerson also auto-discovers `ABSPATH . 'localfiles'` when that directory exists and `EMERSON_PRIVATE_DIR` is unset. The define is still the surest live setup.

## Local Docker

Project `localfiles/` is mounted at `/var/www/html/localfiles` (WordPress root). Emerson prefers that path when it exists, same as live. Optional fallback: project `private-files/` → `/var/emerson-private`. Put test/finance files in this folder.

## Access

Administrators and the **Finance** role see the list on Members. Direct URLs must fail; use the gated download links only.
