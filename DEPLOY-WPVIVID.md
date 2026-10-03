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
| Email redirect mode (since 2026-09-29) | **On**: option `emerson_local_mail_redirect_to` = the intern's test address. Everything is sent for real, but only to that address. | Option doesn't exist | **You**: delete the option before the backup (step A3), so a personal address isn't copied to the live database. It would do nothing on live anyway. |
| WordPress calling itself (loopback) | Needs `mu-plugins/local-loopback.php`, because of Docker's port 8080 → 80 mapping | Works normally | Automatic. The file only runs when the site address is `localhost:8080`, so it can travel with the backup. |
| WPForms "Optimize Email Sending" | **On** since 2026-09-29 | Off (2026-09-24 backup) | **Keep it on**; it's intended for production. It depends on WP-Cron working on the live host. Check in step C5 that the Newcomer email arrives. |
| Limit Login Attempts Reloaded | **Installed** 2026-09-29 (default settings) | Not installed | Carried by the backup's plugins. After the restore, check that failed logins are counted per visitor, not all from one address (see step C6). |
| Members page login | Profile Builder form on `/members/`, which is never page-cached (`DONOTCACHEPAGE`) | `wp-login.php` link | Automatic. Test logging in and out in step C6. |
| Scheduled jobs (WP-Cron) | Zephyr jobs rescheduled on 2026-09-29, after being overdue since March 2025 | Live's own schedule | Nothing to undo. The restore brings the new, non-overdue times, so no backlog fires on live. |
| Serving dates (since 2026-10-02) | Members tick the dates they **can** serve (`inc/serving-dates.php`). The extra table `wp_emerson_serving_answers` holds their answers. Church Admin's `wp_church_admin_not_available` is filled with a "can't serve" row for every unanswered date, 6 months ahead, kept up to date by the daily job `emerson_serving_daily_sync`. | Church Admin's own "dates I can't serve" form; unanswered dates count as available | Automatic. The code, the table and the option `emerson_serving_db_version` all travel in the backup. Check after the restore (section 3 of `TEST-AND-LAUNCH-CHECKLIST.md`, "Serving dates still work after the restore"). If the live site's Church Admin data is brought over separately, reset the feature first (step A1.5). |
| WordPress version | 7.1.2 | 7.1.2 | Same. (The Docker image name says 6.8, but the core files are 7.1.2 from the restore.) |
| PHP / MySQL | 8.2 / MySQL 8.0 | 8.4.25 / MariaDB 11.4 | No action needed. |
| WPvivid remote storage and schedules | None | None | Nothing to reconnect. |

---

## A. Before making the backup (on the local site)

1. **Bring in what changed on the live site since 2026-09-24.** The restore replaces the live site's **whole database** with the local one. The local code does not merge anything into it. Anything created or edited on the live site after that date is lost unless it's copied in first:
   - new user accounts or password changes
   - Church Admin people, households, registrations, member-type changes and "show me" ticks
   - serving schedule (rota) edits and dates people marked as unavailable
   - new or edited pages, posts, sermons and calendar events
   - media uploads
   - plugin settings

   **Steps:**
   1. **Pick a launch date and ask for a freeze.** For a day or two before launch, nobody edits Church Admin, posts or pages on the live site. Anything that can't wait gets emailed to the intern instead, to be entered locally.
   2. **Get a fresh WPvivid backup from Harlan** (Database + Files) at the start of the freeze, and download every part. Keep it next to the 2026-09-24 backup in the project folder. It's also a rollback point.
   3. **Compare it with the local site.** Restore the fresh backup into a **separate** local copy, never over this one: a second Docker project with its own database volume. Then compare the two databases:
      - **Church Admin:** `wp_church_admin_people`, `_household`, `_people_meta`, `_new_rota`, `_not_available`, `_calendar_date`. Compare by `last_updated` and `first_registered` after 2026-09-24, and by row counts. Local counts on 2026-10-02: 70 people (65 once test data is removed), 38 households, 2,951 schedule entries.
        - **Leave out** the local `_not_available` rows the serving feature adds (see the table above). Compare only the live site's own unavailable dates.
      - **Logins:** `wp_users` (new accounts, `user_registered`; password changes show as a different `user_pass`).
      - **Content:** posts, pages, sermons and media with `post_modified` after 2026-09-24.
      - **Plugin settings:** spot-check anything the office mentions changing.
   4. **Copy the changes into the local site**, using the normal screens (Church Admin, Users, the editor). This is the usual case: a few weeks of church activity is normally a handful of edits.
      - **New logins:** re-create them locally and send a password reset after launch. WordPress can't copy a password across, so people get a "set your password" email instead.
      - **Unavailable dates people marked on the live site:** enter them through **Choose person** on the Members page as "can't serve". The other dates then count as unanswered, as for everyone else.
   5. **If the directory changed a lot instead** (many new people or households), bring their Church Admin data over in one go, **after** the restore in section C:
      1. Export from the fresh backup's database copy: `wp_church_admin_people`, `_household`, `_people_meta`, `_new_rota`, `_not_available`. Use `mysqldump` in the database container; see TROUBLESHOOTING → "Serving dates".
      2. Import them into the live database (phpMyAdmin on the host). WPvivid changes the prefix to `wptf_` on the way in, so rename the tables in the file first.
      3. Reset the serving feature so it converts their old-style dates on the next page load. Delete the option `emerson_serving_db_version`, and empty `wptf_emerson_serving_answers`. Their unavailable dates become real "can't serve" answers, and every other date counts as unanswered.
      4. **Check logins:** Church Admin people are linked to WordPress accounts by `user_id`. Anyone who created or changed a login on the live site after 2026-09-24 has to be re-linked or sent a password reset. That's why this route is the exception, not the rule.
   6. **Keep the freeze until the restore is done** (section C), so nothing new appears in between.
2. **Leadership sign-off.** The Privacy Policy page (3) is published locally and will go live with the restore.
3. **Email back to save only, and test data removed.** Run `docker compose run --rm -T wpcli option delete emerson_local_mail_redirect_to`. Delete test households and people from Church Admin, and any test user accounts. See [`TEST-AND-LAUNCH-CHECKLIST.md`](TEST-AND-LAUNCH-CHECKLIST.md).
4. **Optional tidy-up.** In **WPvivid → Backups**, delete the old 2026-09-24 backup from the list so the new one is easy to spot. A full copy stays in the project folder (`www.emersonuuchapel.org_wpvivid-…`).
5. **Make the backup.** **WPvivid Backup → Backup & Restore → Backup Now** → **Database + Files (WordPress Files)** → Save to local. Wait for it to finish.
6. **Download every part** of the new backup (WPvivid splits it into several zip files). Keep them together in one folder.

## B. Before restoring (on the destination site)

1. **Take a fresh WPvivid backup of the live site and download it.** This is your rollback. If the freeze from step A1 has held since Harlan's backup, that backup will do; otherwise take a new one now.
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
     - "Thanks for contacting us!" should appear within about a second, and the email should follow within a minute or two. It's sent as a background job.
     - If the email never arrives, the host is probably blocking WordPress from calling itself. Check **Tools → Site Health** for a "loopback request" or "scheduled event" problem. As a quick fix, turn off **WPForms → Settings → Email → Optimize Email Sending**. The form goes back to waiting about 10 seconds, but the email is sent reliably.
6. **Membership and access.**
   - While logged out, open `/member-home/`. It should send you to `/members/`.
   - On `/members/`, log in as a normal member with the form on the page.
     - The members' hub should appear on the same page, without the admin toolbar.
     - "Log out" should return you to the homepage, logged out.
   - Enter a wrong password once.
     - Then open **Settings → Limit Login Attempts** (or its dashboard). The failed attempt should be listed under **your** IP address.
     - If every attempt shows the same server or proxy address instead, the host sits behind a proxy. Set the plugin's "trusted IP origins" (for example `HTTP_X_FORWARDED_FOR`), or one person's typos would lock everyone out.
   - Log in as a normal member: Members should show "My Emerson experience" and My schedule. This also confirms the Church Admin Premium licence.
   - `/register-2/` and `/register-3/` should be 404.
   - **Newsletter:**
     1. In a private window, open the homepage. The "Stay connected" pop-up should appear after about 5 seconds.
     2. Sign up with your own email. "Almost done!" should appear within about a second.
        - If the spinner and "Still working on it" appear instead, the host can't answer before the email is sent. Sign-up still works, it just takes about 10 seconds.
        - Then click the confirmation link in the email.
     3. Check that `office@` receives "New newsletter subscriber", and that the person appears in Church Admin as "Mailing List".
     4. Delete that test entry afterwards.

     With page caching on, the pop-up is part of the cached homepage. That's intended, because the decision to show it is made in the visitor's browser. Confirmation links bypass the cache because they have a `?` in the address.
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
