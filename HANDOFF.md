# Session handoff — UUA Church WordPress local dev

Last updated: 2026-10-30

**Full list of changes (and how to repeat each on the live site): [`CHANGELOG.md`](CHANGELOG.md).
Problems hit and their fixes: [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md).
Going live with a WPvivid backup of this local site: [`DEPLOY-WPVIVID.md`](DEPLOY-WPVIVID.md).** Add to these whenever something changes.

### Emerson Monday Filter (2026-10-29 / toggle 2026-10-30)
- **Why:** Serving-date checkboxes were listing Sundays *and* Mondays because of an orphan Church Admin service (Monday “Sunday Service” with no site). Sites & Services only shows site-linked services, so the orphan was invisible there. Calendar “You can serve” marks are Sunday-only, so about half of a member’s ticks (e.g. Alice Metz) looked missing.
- **What changed:** `emerson_monday_filter()` in `wp-content/plugins/emerson-chapel/inc/serving-dates.php` — form + sync use site-linked frequencies only; Members form strips non-allowed (Monday) checkboxes CA still outputs; wp-admin sidebar **Emerson Monday Filter** shows status/orphans and an **On / Off** control (default on, warning if turned off). Hook: `emerson_monday_filter`. Option: `emerson_monday_filter_on`.
- **Handoff line:** “You can turn it off under Emerson Monday Filter, but leave it on unless you fix the orphan Monday service — or add a real Monday service with a site.”
- **Docs:** `CHANGELOG.md`, `TROUBLESHOOTING.md` → Serving dates → Emerson Monday Filter.

### Alice / Fox Choose person labels (2026-10-30, local DB)
- WP + CA names were swapped: Choose person **Alice Metz** was person #84 (intern); **Fox Metz** was person #83 (Alice admin test login). Fixed to Alice=#83/#46, Fox=#84/#47.
- **Why:** Separate WP user vs CA person creation; profile names edited on the wrong login. Not a serving-answers sync failure.
- Playbook note: `~/.cursor/AGENT_MANUAL.md` (WordPress + Church Admin names). Project: `TROUBLESHOOTING.md`.
- Follow-up: My serving schedule summary stayed on the login (Alice’s 3) while Choose person edited Fox’s boxes — looked like Fox only had three dates. Form now shows the **selected** person’s summary too.

GitHub: `git@github.com:TheFoxWitch/EmersonUUChapel.git` (branch `main`). Contains the docs, the Docker setup, the child theme, the mail catcher and `backups/`. No plugins, uploads or site backups (see `.gitignore`).

> **Local email is in redirect mode (since 2026-09-29):** every email is really sent, through the church's NetSol account, but only to the intern's test mailbox. The subject shows the original recipient. A copy is also saved in `wp-content/local-mail/`. **Before the WPvivid backup**, switch back to save only with `docker compose run --rm -T wpcli option delete emerson_local_mail_redirect_to`. Every local-only setting and how to undo it is in the table at the top of [`DEPLOY-WPVIVID.md`](DEPLOY-WPVIVID.md). The testing list is in [`TEST-AND-LAUNCH-CHECKLIST.md`](TEST-AND-LAUNCH-CHECKLIST.md).

## Where we are

### Done
- Docker WordPress scaffold at **`/Users/kitsunearisu/Desktop/UUA Church and internship`**
- **Docker Desktop installed and working**
  - Fixed `docker-credential-desktop: executable file not found` by uninstalling Homebrew's standalone `docker` formula (it shadowed Docker Desktop's CLI)
  - `~/.docker/bin` is now first on PATH (`~/.zprofile` and `~/.zshrc`)
- `./bootstrap-wp-content.sh` run; `docker compose up -d` running (MySQL + WordPress + WP-CLI)
- WordPress installed at http://localhost:8080
- **Backup restored** with WPvivid Backup Migration from
  `www.emersonuuchapel.org_wpvivid-6e50ca57e89bc_2026-09-24-17-58_backup_all/`
- **Child theme `emerson-uuchapel` created and activated** (parent: Twenty Twenty-Three, a block theme)
- **Cursor workspace** `UUA-Church.local.code-workspace` created (child theme, parent theme, plugins, project root)
- Project rule `.cursor/rules/wordpress-local-editing.mdc` added with editing paths/conventions
- **Main menu moved into `parts/header.html`** (was the `wp_navigation` post "main", ID 710, in the DB). Home link label fixed (restore had broken it into literal `u003cspan...` text). URLs are relative so they work locally and on production.
- **Homepage (page 15) buttons** given classes `pledge-button` / `paypal-fees-button`; Paypal button's inline gradient/radius removed. Styled in `custom.css`. This is a **local DB edit only** — must be repeated on production (or content re-imported). Original content: `backups/homepage-15-before-button-classes.html`
- **New footer** (all pages): `parts/footer.html` → `patterns/footer.php`; styles in the "Footer" section of `custom.css`. Three columns (about, Sunday worship/location, contact) + bottom bar with auto-updating © year and Privacy Policy link (uses WordPress's privacy page setting).
- **Privacy Policy page** (ID 3, `/privacy-policy/`) written and published **locally only** — needs review by church leadership, then must be created/published on production. Text: `backups/privacy-policy-draft.html`; old default draft: `backups/privacy-policy-3-before.html`. Duplicate old draft (ID 90) renamed to slug `privacy-policy-old-draft`.
- **Headings centred site-wide** (page titles + content headings); paragraphs left-aligned except on the homepage. Rules at the top of `custom.css`.
- **W3 Total Cache page caching turned off locally** (`pgcache.enabled` = false) and stale files cleared from `wp-content/cache/page_enhanced/`. It was serving old copies of pages, hiding CSS changes. Leave it **on** in production. If pages look stale locally again: `docker compose run --rm wpcli w3-total-cache flush all` and empty `wp-content/cache/page_enhanced/`.
- **Image alt text written** for 6 images on pages 412 (Welcome!), 49 (Make A Contribution), 194 (FAQ), 203 (Religious Education), 207 (An Overview of Worship), 209 (Our Congregation). **Local DB only** — re-enter on production via each image's "Alternative text" in wp-admin. Originals: `backups/alt-text/`.
- **Known broken images:** 3 images on page 412 (Welcome!) load from the defunct dev server `emersondev1.bloomenterprises.org` (emerson-building.jpg, service.png, hands-hearts-1024x432.png) and don't load. The five Squarespace-hosted images have been imported into the Media Library (see `CHANGELOG.md`).
- Local `.htaccess` recreated inside the `wp_core` volume — every page except the homepage was returning Apache 404s. If that happens again (e.g. after `docker compose down -v`), recreate the standard WordPress `.htaccess` in `/var/www/html` in the `wordpress` container.

- 2026-09-28 (details in `CHANGELOG.md`):
  - Local email is caught instead of sent.
  - Newcomer form phone field fixed.
  - Member Home (706) restricted to logged-in users; logged-out visitors are sent to Members.
  - Duplicate Profile Builder Register pages drafted.
  - Approval checklist added to the Church Admin sign-up notification.
  - `.gitignore` ready for GitHub.
  - **Newsletter sign-up:**
    - homepage pop-up and a Ways to Connect form, with email confirmation;
    - confirmed subscribers saved to Church Admin as "Mailing List", and the office notified;
    - the pop-up comes back 30 days after it's closed;
    - Ways to Connect published, and the Privacy Policy updated.

    Fully tested, including half-finished entries. Three bugs were found and fixed (see `CHANGELOG.md`).

### In progress
- Making site edits in Cursor via the child theme
- **Sign-up and member access:** proposal drafted (single sign-up through Church Admin; access tiers). Waiting on church leadership decisions.
- **Testing forms and sign-ups with real email:** work through `TEST-AND-LAUNCH-CHECKLIST.md`, section 1.
- **Members area (2026-09-29):**
  - `/members/` is the login page when logged out and the members' hub when logged in (`inc/members.php`).
  - Login attempts are limited by the Limit Login Attempts Reloaded plugin.
  - Only 2 households have opted into the directory. That's a church decision.
  - The newsletter and the Newcomer form no longer wait about 10 seconds for NetSol; their emails are sent after the visitor has their answer.
  - `mu-plugins/local-loopback.php` lets WordPress's background jobs run locally.
- **Going live:** follow `DEPLOY-WPVIVID.md`. Before the backup, turn email redirect off. After the restore, turn W3 Total Cache page caching back on.

### Blocked / known issues
- Waiting on the sitemaster for the 3 missing Welcome! page images, and 5 banner photos for Serve Emerson Chapel (`service.png`, `women-together.jpeg`, `men-together.jpeg`, `circle-suppers.jpeg`, `board-meetings.jpg`). Those are also missing on the live site. The banners are solid blue for now.
- Mailchimp is still manual: the office adds each confirmed subscriber. It can be automated through the `emerson_newsletter_confirmed` hook once someone has the Mailchimp API key and audience ID.
- Sign-up notifications go to `com@emersonuuchapel.org` (probably the webmaster / group lead; fine as-is)
- The Docker image tag says WordPress 6.8, but the core files in the `wp_core` volume are 7.1.2 (from the restore), matching the live site. PHP is 8.2 locally vs 8.4 live.
- Since 2026-09-30 the web container is built from the project `Dockerfile` (the stock image plus PHP's `calendar` extension, for Church Admin's calendar PDF). `docker compose up -d` builds it the first time. After changing the Dockerfile, run `docker compose build wordpress && docker compose up -d wordpress`.

### How forms and sign-up work (findings 2026-09-28)
- **Newcomer Information form (WPForms 351, page 325):** emails `office@emersonuuchapel.org` only. Submissions aren't stored (WPForms Lite), no account is created, and visitors get no email.
- **Church Admin sign-up (Members page, `/register/`):** visitor confirms their email, office is notified, **admin approval required**, then they're added to the directory with a login. Member types: Mailing List, Visitor, Friend, Member, Staff, and others.
- **Profile Builder sign-up (`/register-2/`, `/register-3/`):** retired (set to Draft) on 2026-09-28. It gave Subscriber accounts with no approval.
- **Behind a login:** Address (member directory), Members page account and volunteer schedule, Member Home. Everything else is public.
- **Church Admin permissions** (who can manage Directory, Calendar, Rota, Giving, etc.) are set per person under Church Admin's settings, not by WordPress role.

---

## Where to edit what

| What | Where |
|------|-------|
| Chapel features (Recurring events, Documents, Modals, foil, members tools, …) | Plugin `wp-content/plugins/emerson-chapel/` — PHP in `inc/`, CSS/JS in `assets/` |
| Site-wide feature CSS | `wp-content/plugins/emerson-chapel/assets/css/custom.css` |
| Site-wide feature JS | `wp-content/plugins/emerson-chapel/assets/js/custom.js` |
| Theme layout / branding only | Child theme `wp-content/themes/emerson-uuchapel/` (patterns, parts, logo images). `functions.php` no longer loads feature code. |
| Page shell / header HTML | `wp-content/themes/emerson-uuchapel/templates/page.html`, `parts/header.html` |
| Other templates | Copy from `wp-content/themes/twentytwentythree/templates/` or `parts/` into the same path in the child theme, then edit |
| Header logo (links home; replaced site title + "Home" menu link) | Markup: `patterns/header-logo.php`; image: `assets/images/EmersonUUChapelLogo_original.png`; size: `.emerson-header-logo img` in the plugin `custom.css`. Footer uses the same file. After editing `patterns/`, clear the pattern cache: `docker compose run --rm wpcli eval 'wp_get_theme()->delete_pattern_cache();'` |
| Main navigation menu | `wp-content/themes/emerson-uuchapel/parts/header.html` (`wp:navigation-link` / `wp:navigation-submenu` blocks) |
| Page/post body content | **Database**, not files — wp-admin → edit page → **⋮ → Code editor**, or `docker compose run --rm wpcli post get <ID> --field=post_content` |

Do not edit `twentytwentythree/` directly — updates would overwrite it.

After saving CSS/JS, hard-refresh http://localhost:8080 (`Cmd + Shift + R`).

### Finding files in Cursor
- File tree (Explorer): `Cmd + Shift + E`; toggle sidebar: `Cmd + B`
- Optional focused view: **File → Open Workspace from File…** → `UUA-Church.local.code-workspace`

---

## Daily commands

| Task | Command |
|------|---------|
| Start | `docker compose up -d` |
| Stop | `docker compose down` |
| Status | `docker compose ps` |
| Logs | `docker compose logs -f wordpress` |
| WP-CLI | `docker compose run --rm wpcli <command>` |

If URLs point at production after a fresh restore:

```bash
docker compose run --rm wpcli search-replace 'https://www.emersonuuchapel.org' 'http://localhost:8080' --all-tables
docker compose run --rm wpcli search-replace 'http://www.emersonuuchapel.org' 'http://localhost:8080' --all-tables
docker compose run --rm wpcli rewrite flush
```

---

## Next session
1. Make sure Docker Desktop is running, then `docker compose up -d`
2. Open the child theme files in Cursor and continue edits
3. To publish: WPvivid backup of the local site, then restore onto the live site, following `DEPLOY-WPVIVID.md`
