# Deploying the local site with WPvivid

Plan: make a WPvivid backup of the **local** site (http://localhost:8080), then restore it onto the live site (or a fresh WordPress install on new hosting). The backup carries every change in [`CHANGELOG.md`](CHANGELOG.md): the theme files, the mail-catcher plugin and all the database edits. Nothing has to be re-entered by hand.

Written 2026-09-28, checked against the live site's 2026-09-24 WPvivid backup.

---

## What differs between local and live, and who fixes it

| Thing | Local | Live (2026-09-24) | Handled by |
|---|---|---|---|
| Site address | `http://localhost:8080` (263 references in the database) | `https://www.emersonuuchapel.org` | WPvivid replaces it during restore. Verify afterwards (step C4). |
| Database table prefix | `wp_` | `wptf_` | WPvivid converts to whatever the destination's `wp-config.php` uses. It did the reverse on the way in. |
| `wp-config.php` | Docker's own | Host's (DB login, salts, `WP_CACHE`) | Not overwritten. WPvivid keeps the destination's file. |
| `.htaccess` | Plain WordPress rules | W3 Total Cache rules + WordPress rules | Re-saving W3TC settings and permalinks regenerates it (steps C2–C3). A copy of the live file is saved in step B2. |
| W3 Total Cache page cache | **Off** | **On** (Disk: Enhanced) | **You**: turn it back on (step C3). Everything else in W3TC is identical. |
| Outgoing email | Caught by `mu-plugins/local-mail-catcher.php` | Sent through WP Mail SMTP (NetSol) | Automatic. The catcher only runs on `localhost`, and the SMTP settings travel in the database. Test in step C5. |
| WordPress version | 7.1.2 | 7.1.2 | Same. (The Docker image name says 6.8, but the core files are 7.1.2 from the restore.) |
| PHP / MySQL | 8.2 / MySQL 8.0 | 8.4.25 / MariaDB 11.4 | No action needed. |
| WPvivid remote storage and schedules | None | None | Nothing to reconnect. |

---

## A. Before making the backup (on the local site)

1. **Check the live site for changes made since 2026-09-24.** Anything created there after that date will be **replaced** by the restore:
   - new user accounts or password changes
   - Church Admin households and registrations
   - rota edits
   - new or edited pages and posts
   - media uploads
   - plugin settings

   Either copy those changes into the local site first, or re-enter them after the restore.
2. **Leadership sign-off.** The Privacy Policy page (3) is published locally and will go live with the restore.
3. **Optional tidy-up.** In **WPvivid → Backups**, delete the old 2026-09-24 backup from the list so the new one is easy to spot. A full copy stays in the project folder (`www.emersonuuchapel.org_wpvivid-…`).
4. **Make the backup.** **WPvivid Backup → Backup & Restore → Backup Now** → **Database + Files (WordPress Files)** → Save to local. Wait for it to finish.
5. **Download every part** of the new backup (WPvivid splits it into several zip files). Keep them together in one folder.

## B. Before restoring (on the destination site)

1. **Take a fresh WPvivid backup of the live site and download it.** This is your rollback.
2. **Save copies of the live `.htaccess` and `wp-config.php`** (cPanel → File Manager → site root → Download). A copy of the 2026-09-24 `.htaccess` is also inside the old backup's `*_backup_core.zip`.
3. **New hosting only:**
   - Install WordPress with PHP 8.4.
   - Install the **WPvivid Backup & Migration** plugin.
   - Point the domain at the new host and make sure SSL is issued.

## C. Restore, then reconnect

1. **Upload and restore.**
   - Upload: **WPvivid → Backup & Restore → Upload**, or put the zip files in `wp-content/wpvividbackups/` over FTP and click **Scan uploaded backup or received backup**.
   - Click **Restore**. WPvivid detects the different domain and migrates it.
   - When it finishes, log in again. The accounts and passwords are those in the backup (the same as the live site on 2026-09-24).
2. **Site address and permalinks.**
   - **Settings → General**: both addresses should be `https://www.emersonuuchapel.org`.
   - **Settings → Permalinks → Save Changes**, without changing anything. This rewrites the WordPress part of `.htaccess`.
3. **Caching (the main thing to reconnect).**
   - **Performance → General Settings → Page Cache**: tick **Enable**, method **Disk: Enhanced** → Save.
   - Then **Performance → Dashboard → Empty All Caches**.
   - Check that `.htaccess` has the `# BEGIN W3TC Browser Cache` and `# BEGIN W3TC Page Cache core` blocks again (compare with the copy from B2). Check that `wp-config.php` still has `define('WP_CACHE', true);`.
   - If old pages keep showing, delete `wp-content/cache/page_enhanced/` in File Manager.
4. **Leftover local addresses.** Search the database for `localhost` with the **Better Search Replace** plugin (dry run first), or view the source of a few pages. Places that had the local address:
   - the Members page links (Rota, My availability)
   - Church Admin's "new household" notification email
   - image URLs in page content

   Replace any leftovers with `https://www.emersonuuchapel.org`.
5. **Email.**
   - **WP Mail SMTP → Tools → Email Test** to your own address. It should arrive, which proves the local mail catcher isn't interfering.
   - Submit the Contact/Newcomer form once and confirm it reaches `office@emersonuuchapel.org`.
6. **Membership and access.**
   - While logged out, open `/member-home/`. It should send you to `/members/`.
   - Log in as a normal member: Members should show "My Emerson experience" and My schedule. This also confirms the Church Admin Premium licence.
   - `/register-2/` and `/register-3/` should be 404.
7. **Look over the pages.**
   - homepage buttons
   - footer and Privacy Policy link
   - the What is UU? video
   - images on Welcome!, FAQ, Religious Education, An Overview of Worship and Our Congregation
8. **Housekeeping.**
   - **Settings → Reading**: "Discourage search engines" should be **unticked**.
   - **Tools → Site Health**: no critical issues.
   - The mail-catcher file (`wp-content/mu-plugins/local-mail-catcher.php`) can stay. It does nothing unless the site address is `localhost`.

## Rolling back
Restore the live-site backup from step B1 with WPvivid, then repeat C3 (empty all caches).

---

## Not included in the WPvivid backup
The project-folder files outside `wp-content/`:
- docs
- `backups/` (content snapshots)
- `docker-compose.yml`

These are on GitHub: `git@github.com:TheFoxWitch/EmersonUUChapel.git`.
