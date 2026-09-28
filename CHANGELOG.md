# Changelog — emersonuuchapel.org local rebuild

Every change made to the local copy, newest first. Each entry says:

- **Where** the change lives: **File** (theme or plugin files, travels with GitHub / an upload of `wp-content/themes/emerson-uuchapel/`) or **Local DB** (the local database only, has to be redone on the live site or migrated with WPvivid).
- **Backup**: the original content, saved before the change.
- **On the live site**: how to repeat it there.

Problems we ran into and how they were fixed are in [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md).

---

## 2026-09-28

### Homepage "You Are Welcome!" heading colour
- **Where:** local DB, homepage (15), the heading block
- **What changed:** the hard-coded green `#318c35` is replaced by the palette colour **Vivid green cyan** (`var(--wp--preset--color--vivid-green-cyan)`, `#00d084`). It's saved in WordPress's palette format (`var:preset|color|vivid-green-cyan`) for the heading and its link colour, so the editor's colour picker shows the palette swatch instead of a custom colour.
- **Backup:** `backups/homepage/homepage-15-before-welcome-colour.html`
- **Accessibility note:** `#00d084` on white is about 2 : 1 contrast. WCAG asks for at least 3 : 1 for large headings, so this may be hard for some visitors to read. Worth a second look with the church.
- **On the live site:** carried by the WPvivid restore.

### Every other green on the site switched to Vivid green cyan
- **What was searched:** all published or draft pages and posts, templates, template parts, global styles, settings, post meta and the theme files. Searched for `#318c35` and any other green.
- **What changed:**
  - **Homepage (15), "August 9"** (`#275329`) and **"Vote on new meeting space"** (`#2c7530`): now `var:preset|color|vivid-green-cyan`, for both the text and the link colour. (Local DB)
  - **The homepage "2024-25 Pledge Form" button** (`custom.css`, `.pledge-button`): the background changed from `#2e7d5b` to `var(--wp--preset--color--vivid-green-cyan)`. The text changed from white to `#1e1e1e`, because white on mint is too faint to read (about 2 : 1). The dark text is about 9 : 1.
- **Left alone on purpose:**
  - The green in the newsletter's "Almost done" / "You're subscribed" messages (`#e7f3ec` / `#1d5b3f`). It's a success colour, paired with the red error colour.
  - Custom CSS left over from the old Magazine Pro theme (post 88). That theme isn't active, so it has no effect.
  - Old homepage revisions that still contain `#318c35`. They're history only and never shown.
- **Checked in the browser:** the only green left on the homepage is `rgb(0, 208, 132)` (`#00d084`).
- **Backup:** `backups/homepage/homepage-15-before-other-greens.html`

### Newsletter pop-up fades in as well
- **Where:** `open()` in `assets/js/newsletter.js`; `.emerson-nl-dialog.is-opening` in `custom.css`, which shares the fade-out's hidden style
- **What changed:** the pop-up and backdrop now fade in over the same 0.3 s when the pop-up opens, with the pop-up rising slightly into place. The cursor still goes straight to "First name".
- **Tests:**

  | Test | Result |
  |---|---|
  | Opens by itself after 5 seconds | Pass: fade animations running on the pop-up and backdrop |
  | Frozen partway through the fade-in | Pass: semi-transparent (screenshot) |
  | After the fade-in | Pass: fully visible; "No thanks" still fades out and saves "dismissed" |
  | Reduced motion switched on | Pass: appears instantly, no animation |

### Newsletter pop-up fades out instead of vanishing
- **Where:** `assets/js/newsletter.js` (`dismiss()`) and the "Newsletter" section of `custom.css` (`.emerson-nl-dialog.is-closing`)
- **What changed:**
  - Every way of closing (×, "No thanks", Esc, clicking outside) now fades the pop-up and the dark backdrop over 0.3 s. The pop-up also sinks slightly as it fades.
  - After a successful sign-up the "Almost done!" message stays up for 4 seconds, then the pop-up fades out by itself. The visitor can still close it sooner.
  - Visitors whose device is set to reduce motion get an instant close, with no animation.
- **Tests:**

  | Test | Result |
  |---|---|
  | ×, "No thanks", backdrop, Esc | Pass: the fade animations run (pop-up and backdrop) and "dismissed" is saved |
  | × clicked twice quickly | Pass: closes once |
  | Frozen partway through the fade | Pass: semi-transparent, with the homepage showing through |
  | Valid sign-up | Pass: message still showing at 2 s, closed by 5.5 s, "subscribed" saved |
  | Reduced motion switched on | Pass: no animation, closes immediately |
- **Problem found:** the fade was sometimes skipped and the pop-up jumped straight to invisible. Browsers only animate from a style they've already worked out, and a freshly opened pop-up may not have one yet. Fixed by making the browser settle the pop-up's current style before the fade starts.
- **Test-tool note:** the Cursor test browser barely draws frames when it's in the background, so sampling the opacity mid-fade always read 1. The fade was verified by checking that the browser created the animations, and by freezing one halfway for a screenshot.

### Newsletter sign-up: homepage pop-up and Ways to Connect form
- **Where:**
  - **File:**
    - `inc/newsletter.php` (loaded from `functions.php`)
    - `assets/js/newsletter.js`
    - the "Newsletter" section of `custom.css`
  - **Local DB:**
    - Ways to Connect (218) published, with the `[emerson_newsletter_form]` shortcode
    - the homepage (15) "Sign up for our Newsletter!" link now points to `/ways-to-connect/`
    - Privacy Policy (3) updated
- **How it works:**
  - **Who sees the pop-up:** logged-out visitors to the homepage only. Logged-in users never get it; the server leaves it out of the page.
  - **When it opens:** after 5 seconds, or once the visitor has scrolled 40% of the page.
  - **Signing up:** first name (required), last name, email (required). The visitor gets a confirm-your-email link that works for 7 days. Nothing is saved to Church Admin until they click it.
  - **On confirmation:**
    - They're saved to Church Admin as member type **Mailing List**, with their own private household, hidden from the member directory (`show_me=0`).
    - The office gets "New newsletter subscriber: Name" at `office@emersonuuchapel.org`, with a reminder to add them in Mailchimp.
    - If the email is already in Church Admin, no duplicate is made, and the office email says who it matches.
  - **The browser note:** stored in `localStorage` under `emersonNewsletter`, never sent to the server. It holds `subscribed` (never show again) or `dismissed` plus the date (show again after 30 days). There's no name or email in it.
  - **Spam protection:**
    - a hidden trap field;
    - a minimum of 2 seconds between the form appearing and being sent;
    - at most 5 sign-up attempts per hour per connection;
    - no second confirmation email to the same address within 10 minutes;
    - the same "Almost done" reply whether or not someone is already subscribed, so the form can't be used to check who is on the list.
  - **Later:** a `do_action( 'emerson_newsletter_confirmed', $data )` hook fires on confirmation. A Mailchimp connection can go there once someone has the API key and audience ID.
- **Backup:** `backups/newsletter/` (pages 3, 15 and 218 before the change)
- **On the live site:** carried by the WPvivid restore. See `DEPLOY-WPVIVID.md` step C6 for the checks.

#### Test results (all local; the mail catcher captured every email)

| Test | Result |
|---|---|
| Pop-up on the homepage, logged out | Pass: hidden at 0.85 s, open after 5 s, cursor in "First name" |
| Pop-up for a logged-in user / on other pages | Pass: not in the page |
| "No thanks", ×, backdrop click, Esc key | Pass: closes and saves "dismissed" (after the fix below) |
| Reload after dismissing (waiting and scrolling) | Pass: stays hidden |
| Dismissed 29 days ago / 31 days ago | Pass: hidden / shows again |
| Browser: only a first name, then `browser@` | Pass: the browser blocks it; nothing is sent |
| Browser: `browser@example` (no dot in the domain) | Pass: the server's message "Please enter a valid email address" appears (after the fix below) |
| Browser: valid sign-up | Pass: "Almost done!", form hidden, button changes to "Close", browser stores "subscribed" |
| Confirmation link clicked in the browser | Pass: green "You're subscribed" notice. The pop-up is suppressed even on a browser that never saw it |
| Server: empty form; first name only; email only; `alice@` | Pass: specific error messages, HTTP 400, no email, nothing stored |
| Server: spaces-only name; 61-character name; 101-character email; `<script>` as name | Pass: rejected with a message |
| Server: hidden trap filled; sent 0 seconds after load | Pass: fake "Almost done", nothing stored or sent |
| Same email twice in a row (different capitalisation) | Pass: one email only |
| Sixth attempt within an hour | Pass: "Too many sign-up attempts", HTTP 429 |
| Without JavaScript (plain form post) | Pass: redirects back to the page with the message; an outside return address is refused and goes home instead |
| Link clicked twice; made-up, short, empty or junk link; link older than 7 days | Pass: "expired or already used", nothing saved |
| Existing Church Admin member confirms | Pass: no duplicate; the office email names the existing record |
| New subscriber in the member directory | Pass: not shown |
| Phone-sized screen (390 × 844) | Pass: 352 × 625 pop-up, fields stack |

#### Problems found during testing (all fixed)
1. **No sign-up from the browser could ever work.** The form has a hidden field named `action` (WordPress requires it), and in JavaScript `form.action` returns that field, not the address. Every sign-up went to `/[object HTMLInputElement]` (404) and showed "Something went wrong". The direct server tests didn't catch it because they skip the JavaScript. Fixed by using `form.getAttribute('action')`. The script also now reports a clear error if the reply isn't JSON.
2. **Closing with Esc could forget the dismissal.** Newer Chrome closes a pop-up on a second Esc press even when the page tries to block it, and then the "dismissed" note wasn't saved, so the pop-up came back on the next visit. Now every way of closing runs through the pop-up's `close` event, and there's an explicit Esc handler.
3. **Opening the form's address directly** (a GET with no return address) redirected back to `admin-post.php` itself. It now falls back to the homepage.
4. **The Ways to Connect form was narrower than the text around it.** Now it lines up.

#### Known limits (not bugs)
- **Email link scanners.** Some work email systems (for example Outlook "Safe Links") open links in incoming mail to scan them, which could confirm a sign-up before the person clicks. Mailchimp's own confirmations work the same way.
- **One sign-up per browser.** The pop-up can't recognise someone who subscribed on another device until they confirm there or log in.
- **Browser-test tool limitation.** Its "press Esc" closes the pop-up without firing real keyboard events, so Esc was verified by sending a genuine keyboard event from inside the page instead.

### Deploy guide written
- **Where:** File, [`DEPLOY-WPVIVID.md`](DEPLOY-WPVIVID.md)
- **What:** Steps to back up the local site with WPvivid, restore it onto the live site, and reconnect what differs locally:
  - W3 Total Cache page cache
  - `.htaccess` caching rules
  - site address
  - email check

### Members page (32): duplicate login form removed
- **Where:** Local DB
- **Before:** Logged-out visitors saw two login forms. One came from the "not logged in" message block, the other from the "My schedule" (`my-rota`) block.
- **Now:** The message block has `login_form="0"`, so visitors see the message, the registration box and one login form. Logged-in members still see their household, "My Emerson experience" and their schedule.
- **Backup:** `backups/members-32-before-duplicate-login.html`
- **On the live site:** Carried by the WPvivid restore. By hand: edit the Members page and change `[church_admin type="not-logged-in"]` to `[church_admin type="not-logged-in" login_form="0"]`.

### Church Admin sign-up notification now includes approval steps
- **Where:** Local DB (option `church_admin_new_entry_admin_email`)
- **What:** The "new household has confirmed their email" message keeps its original link. It now also includes the person's details (`[HOUSEHOLD_DETAILS]`) and a numbered checklist:
  1. Set the Member type.
  2. Click **Create user account**.
  3. Change the role to **Author** only for people who should upload media.
- **Recipient:** `com@emersonuuchapel.org` (Church Admin's default "from" address), not office@.
- **Backup:** `backups/church-admin/new-entry-admin-email-before.html`. New text: `…-after.html`
- **On the live site:** **Church Admin → Settings → Automations / Email templates → New entry admin email**. Keep the live site's own first paragraph and link, and paste the rest from the `-after.html` file.

### Duplicate Profile Builder "Register" pages retired
- **Where:** Local DB
- **What:** Pages 906 (`/register-2/`) and 911 (`/register-3/`) are set to **Draft**. They let anyone create an account with no approval and no directory entry. Nothing linked to them. Sign-up now goes through Church Admin only, on the Members page and `/register/`. The Profile Builder login page `/log-in/` is kept.
- **Undo:** Republish the pages.
- **On the live site:** **Pages** → trash or draft both "Register" pages that use the Profile Builder register block or shortcode. Keep Church Admin's `/register/`.

### Logged-out visitors to restricted pages go to the Members login page
- **Where:** Local DB (option `ps_simple_par_settings`)
- **What:** Simple Page Access Restriction now redirects to the Members page (32) instead of showing a 404. It passes `redirect_to`, but the Members page's login form always returns people to Members, not to the page they asked for.
- **On the live site:** **Settings → Simple Page Access Restriction** → Redirect to page: **Members**.

### `.gitignore` ready for a GitHub backup
- **Where:** File, `.gitignore`
- **What:** Excludes everything private, generated or third-party:
  - WPvivid and Backuply backups and the XML export (these contain member emails, password hashes and the SMTP password)
  - `.env`
  - the Media Library
  - caches
  - plugins, and all themes except `emerson-uuchapel`
  - personal photos and screenshots in `images/` (the logo is kept)
- **Result:** 44 files (about 430 KB): docs, Docker setup, `backups/`, the mail-catcher plugin and the child theme.

### Member Home page (706) now requires a login
- **Where:** Local DB
- **Before:** Advanced Access Manager blocked the **Subscriber** role from the page, but logged-out visitors could still open it. That is backwards, since most members are Subscribers.
- **Now:** The Advanced Access Manager rule is removed. The page is set to "restricted" in Simple Page Access Restriction, so any logged-in user can see it and logged-out visitors can't.
- Logged-out visitors are sent to the Members login page (see the entry above).
- **Backup:** `backups/access/`
- **On the live site:**
  1. **Users → Roles/Access (AAM) → Subscriber → Posts & Terms → Member Home**: reset the rule.
  2. Edit Member Home and tick **Restrict access** in the Simple Page Access Restriction box.

### Newcomer form (WPForms 351): phone field accepts normal phone numbers
- **Where:** Local DB
- **Before:** "Phone Number" was a *Numbers* field, which rejects `(636) 555-0123` and dashes.
- **Now:** It's a *Single Line Text* field with the placeholder `(636) 555-0123`. (The dedicated Phone field is a WPForms Pro feature.)
- **Backup:** `backups/forms/newcomer-form-351-before-phone.json`
- **On the live site:** **WPForms → edit "Newcomer Information Form"** → delete the Phone Number field, add a *Single Line Text* field labeled "Phone Number", and save. (Or switch the field type if the builder offers it.)

### Local email switched off
- **Where:** File, `wp-content/mu-plugins/local-mail-catcher.php`
- **Why:** The restored database has the church's real SMTP login, NetSol, set in WP Mail SMTP. Form tests, registrations and password resets on the local copy were sending real email to the office and to members.
- **Now:** When the site address is `localhost` / `127.0.0.1`, all email is stopped and saved as text files in `wp-content/local-mail/` instead. The plugin does nothing on the live site.
- **On the live site:** Nothing to do. Don't upload `wp-content/mu-plugins/local-mail-catcher.php` there. It's harmless if uploaded, but it isn't needed.

---

## 2026-09-25 to 2026-09-27

### Hotlinked Squarespace images imported into the Media Library
- **Where:** Local DB and uploads
- **What:** Five images that loaded from the old Squarespace site now live in the Media Library, with alt text:

  | Page | Attachment | File |
  |---|---|---|
  | 412 Welcome! | 926 | `emerson-chapel-building.jpg` |
  | 194 FAQ | 928 | `ralph-waldo-emerson-portrait.jpg` |
  | 203 Religious Education | 930 | `singing-bowl.jpg` |
  | 207 An Overview of Worship | 932 | `worship-hymn-singing.jpg` |
  | 209 Our Congregation | 934 | `worship-leader-lectern.jpg` |

- **Backup:** `backups/image-import/`
- **On the live site:** Migrate with WPvivid, or upload each image and swap it into the image block on its page.

### Image alt text written
- **Where:** Local DB
- **What:** Descriptive alt text for six images on pages 412, 49 (the PayPal QR code), 194, 203, 207 and 209.
- **Backup:** `backups/alt-text/`
- **On the live site:** Select each image and fill in **Alternative text** in the block sidebar.

### Images centred site-wide
- **Where:** File, `custom.css`
- **What:** Image blocks inside page content are centred unless they're explicitly aligned left or right.

### Heading styles matched; bold removed from headings site-wide
- **Where:** File, `custom.css`
- **What:** Bold text inside headings inherits the heading's normal weight. On Religious Education (page 203), the section headings share one size.

### Link colour changed from green to the logo blue
- **Where:** File, `custom.css`
- **What:** Links in page content use `--emerson-blue` (`#3f4fa0`), and a darker blue (`#2e3a78`) on hover.

### YouTube video embedded on "What is UU?" (page 196)
- **Where:** Local DB (content) and File (spacing in `custom.css`)
- **What:** The embed used a `/embed/` URL, which WordPress can't turn into a player. It's changed to `https://www.youtube.com/watch?v=-3UYWnngiEo`. The paragraph under the video is centred and has extra space above it.
- **Backup:** `backups/what-is-uu-196-before-youtube.html`
- **On the live site:** Edit the page and replace the YouTube block's URL with the `watch?v=` link.

### W3 Total Cache page caching off locally
- **Where:** Local DB
- **What:** `pgcache.enabled` set to false, and `wp-content/cache/page_enhanced/` emptied. It was serving stale pages that hid CSS changes.
- **On the live site:** Leave caching **on**. Purge the cache after every deploy.

### Headings centred site-wide
- **Where:** File, `custom.css`
- **What:** Page titles and content headings are centred. Paragraphs stay left-aligned except on the homepage, where all text is centred.

### Privacy Policy page (3)
- **Where:** Local DB
- **What:** A church-specific privacy policy, published at `/privacy-policy/`. The old duplicate draft (90) was renamed to `privacy-policy-old-draft`.
- **Needs:** Review by church leadership. It should also mention the Church Admin member directory.
- **Backup:** `backups/privacy-policy-3-before.html`. New text: `backups/privacy-policy-draft.html`
- **On the live site:** Paste the approved text into the Privacy Policy page, then publish it under **Settings → Privacy**.

### New footer
- **Where:** File
  - `parts/footer.html`
  - `patterns/footer.php`
  - the "Footer" section of `custom.css`
- **What:**
  - Columns for Sunday worship, contact, and the logo with the church description.
  - A bottom bar with an auto-updating © year and a Privacy Policy link.

### Typography scale and line wrapping
- **Where:** File, `custom.css`
- **What:** Heading sizes scale smoothly with screen width. `text-wrap: balance` and `pretty` stop single words from sitting alone on a line.

### Cache-busting for theme CSS/JS
- **Where:** File, `functions.php`
- **What:** `custom.css` and `custom.js` use the file's modified time as their version number, so browsers pick up edits right away.

### Homepage (15) button styles
- **Where:** Local DB (classes) and File (styles in `custom.css`)
- **What:** The pledge button got the class `pledge-button` and the PayPal-fees button got `paypal-fees-button`. The PayPal button's inline gradient and corner radius were removed.
- **Backup:** `backups/homepage-15-before-button-classes.html`
- **On the live site:** Select each button → **Advanced → Additional CSS class(es)**. Clear the PayPal button's custom colours.

### Logo replaces the text site title and "Home" link
- **Where:** File
  - `patterns/header-logo.php`
  - `assets/images/EmersonUUchapel.png` (a trimmed copy of `images/EmersonUUchapel.png`)
  - `parts/header.html`

### Main menu moved into the theme
- **Where:** File, `parts/header.html`
- **What:** The menu was the `wp_navigation` post "main" (710) in the database. It's now written directly in the header template, so it can be edited in Cursor. The restore had broken the Home link's label, and URLs are now relative.
- **On the live site:** Upload the child theme. Menu edits in wp-admin's Navigation screen will no longer affect the header.

### Project setup
- Docker Compose stack (MySQL 8, WordPress 6.8 / PHP 8.2, WP-CLI) at http://localhost:8080.
- Live-site backup restored with WPvivid (from the 2026-09-24 backup).
- Child theme `emerson-uuchapel` created and activated (parent: Twenty Twenty-Three).
- Cursor workspace and project rule added.
