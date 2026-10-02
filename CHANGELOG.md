# Changelog — emersonuuchapel.org local rebuild

Every change made to the local copy, newest first. Each entry says:

- **Where** the change lives: **File** (theme or plugin files, travels with GitHub / an upload of `wp-content/themes/emerson-uuchapel/`) or **Local DB** (the local database only, has to be redone on the live site or migrated with WPvivid).
- **Backup**: the original content, saved before the change.
- **On the live site**: how to repeat it there.

Problems we ran into and how they were fixed are in [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md).

---

## 2026-10-02

### "Dates I can't serve" Save button: big Emerson-blue pill
- **CSS** (written by the intern): `custom.css`, the `.button-primary-calendar-save` rule. It sets Emerson blue, white text, 25 px rounded ends, padding `calc(0.667em + 2px) calc(1.111em + 2px)`, and a slight grow on hover.
  - Added to it: `font: inherit`, `font-size: var(--wp--preset--font-size--medium)`, `line-height: 1.6`, `cursor: pointer` and the transition.
  - Without a font setting the button kept the browser's 13 px Arial. Its `em` padding scaled down with it, so it came out 64 × 37 instead of 56 tall.
- **The class:** Church Admin prints the button itself (`display/not-available.php`, `<input type="submit" class="button" value="Save">`), so there was nowhere to add the class by hand. Editing the plugin file would be overwritten by the next Church Admin update.
  - A `do_shortcode_tag` filter in `inc/members.php` now adds `button-primary-calendar-save` to that one button. It matches the hidden `not-available` field just before it.
- **Result:** 81 × 56 px, 17.6 px site font, Emerson blue. Checked as test Mctest on the members' hub.
- **Method for future plugin buttons:** add the class in `inc/members.php` with a `do_shortcode_tag` filter, then style it in `custom.css`.

### "Dates I can't serve": saved dates listed, and Save returns to the section
- **Report:** after ticking a date and clicking Save, nothing seemed to change. The date *was* saved (checked: November 1, 2026 for test Mctest).
  - Church Admin's only feedback is that the box stays ticked among the next 26 service dates, plus a "Unavailable dates saved" heading.
  - The form reloaded at the top of the page, so that heading was out of sight.
- **Changes** (`inc/members.php`, a `do_shortcode_tag` filter on Church Admin's `not-available` output):
  - **A summary above the checkboxes:** "Dates you've marked as unavailable: **November 1, 2026**", or "You haven't marked any dates as unavailable."
    - It reads Church Admin's own table (`church_admin_not_available`) at page time, so it reflects a save straight away. Only today's date and later are listed.
    - When an admin has picked someone with **Choose person**, it names that person instead.
  - **Return to the section:** the "Dates I can't serve" heading on the Members page has the anchor `#dates-unavailable` (backup: `backups/members/members-32-before-dates-anchor.html`). Both Church Admin forms in that section now post to `/members/#dates-unavailable`.
- **Tested** as test Mctest: the summary showed November 1. Ticking December 6 and saving landed on the section with "Unavailable dates saved", and the summary listed both dates. December 6 was then removed again.

### Logins with no directory entry
- **Report:** the intern's Contributor login (WordPress user #47) saw no serving dates, only "Your login is not connected to a directory entry".
- **Cause:** serving schedules and unavailable dates belong to a Church Admin **person**, not to a WordPress login.
  - Logins made through the Members page sign-up get a person automatically. This one was made directly in WordPress, so it had none.
  - Real members can hit the same message if the office creates their login in WordPress, or if their login email doesn't match their directory entry.
- **Friendlier message** (`inc/members.php`): "Your login isn't linked to a directory entry yet, so serving dates can't be shown. Contact the office at office@emersonuuchapel.org and they'll connect it."
- **Local data:** added person #84 "Fox Metz" (household #46) for user #47.
  - Mailing List type, hidden from the directory, with all email and text settings off.
  - Listed in the checklist's test-data cleanup.

## 2026-10-01

### "Choose person" button: big Emerson-blue pill
- **Where:** `custom.css`, `.wp-block-post-content input.button-primary` (and `button.button-primary`).
- **The button:** Church Admin's **"Choose person"** button sits under **Dates I can't serve** on the members' hub, and appears only for users with Church Admin's Rota permission, such as administrators. It used wp-admin's small `button-primary` style.
- **The new style** matches the Newcomer / Newsletter buttons:
  - Emerson blue `#3f4fa0` with white text
  - the theme's medium font size (about 17.6 px)
  - `calc(0.667em + 2px) calc(1.333em + 2px)` padding, 25 px rounded ends, and a slight grow on hover
  - It measures 56 px tall. The dropdown beside it is unchanged.
- **Only this button is affected:** "Choose person" is the only `button-primary` in page content. Profile Builder's **Log In** also carries `button-primary`, but its own, more specific rule still wins, so it's unchanged (150 × 50, dark grey). Checked by measuring copies of both buttons on the live page.

### Real member login tested; "user login created" email tidied
- **Checklist test:** the intern promoted test Mctest to **Member** in Church Admin, ticked "To show me on the password protected address list", clicked **Create user account** and logged in as `testmctest`. The serving schedule worked, and test Mctest appeared in the member directory once the "show me" box was ticked.
  - Route: **Church Admin Premium → People card → Search → Go → first name → Edit person**.
- **The login email** sent by **Create user account** (`church_admin_user_created_email`, local DB) ran together: "The web team athttp://… have just created a user login for you.Your username is …Your password is …". The stored template had no spaces or line breaks, and Church Admin only adds paragraphs at blank lines.
  - It's now separate paragraphs, with "has just created a member login".
  - It adds a line asking new members to choose their own password through **Edit my profile or password**.
  - The username and password are in bold, as before.
  - Backup: `backups/church-admin/user-created-email-before.json`.
- **Church Admin's own closing sentence** said "…privacy settings **at at** …", a plugin typo. It's corrected through a `gettext` filter in `inc/members.php`, so the plugin files aren't edited.
- **Lockout during testing:** four logins with a shortened username, when the admin account's username is its full email address, locked the connection out for 20 minutes, and even the new password was refused. Cleared, and documented in `TROUBLESHOOTING.md`.

## 2026-09-30

### Member directory search: larger box and button, "Member Name" placeholder
- **Where:** `custom.css`, `input.ca-search-field` and `input.ca-search-submit`. The placeholder change is in `inc/members.php` (a `do_shortcode_tag` filter on Church Admin's output).
- Church Admin's search form was unstyled: 13 px Arial, 22 px tall (box 161 × 22, button 58 × 22), with sunken and raised borders.
- **The button** is now 50% larger: 20 px site font, 34 px tall, light grey (`#efefef`), flat with 3 px corners. **The box** matches it: same height and font, 1 px grey border, 3 px corners.
- **The placeholder** "Search" is now **"Member Name"**. The box also gets `aria-label="Search the member directory by name"`, because a placeholder isn't a label for screen readers.
- **Tested** with a temporary member account, deleted afterwards: both 34 px and level, and a search for "Bloom" still finds Harlan Bloom.
- **"Show the full directory" after a search.** Church Admin shows only the results (`Your search for "…" yielded N result(s)`) or `"…" not found`, with no way back to the full list. A light grey button matching Search now follows either message and reloads the directory without the search. It doesn't appear when no search has been made.
- **Lands at the directory:** the Members page's "Member directory" heading has the anchor `#member-directory` (backup: `backups/members/members-32-before-directory-anchor.html`). The search form goes to `/members/#member-directory`, so results open at the directory instead of the top of the hub.
- **Bug fixed the same evening:** the button first linked to the same `/members/#member-directory` address the search lands on. The browser treated that as a jump within the page, so it only scrolled and never reloaded, and the search stayed. The button now links to `/members/?directory=all#member-directory`, which forces a real reload. (The `directory=all` part does nothing else.)
- **Tested in the browser** with a temporary member account, deleted afterwards:
  - search "Zzyzx": "not found", with the button
  - clicked: the page reloaded, the message and button were gone, the full list was back, and the page opened at the Member directory heading
- **Also tested on the server:** "Bloom" (2 results) shows the button too, and no search shows no button.

### Calendar Previous / Next buttons: Emerson blue (intern's edit)
- The intern changed the calendar buttons' colours in `custom.css` (`input.calendar-date-switcher, button.ca-calendar-nav`): background `#3f4fa0` (Emerson blue), white text, and 20 px text instead of 26 px.
- The hover colour below it is still the light grey `#e0e0e0`, so the buttons turn light grey on hover. Use the darker blue `#2e3a78` instead if that's not wanted.

### Calendar Previous / Next buttons about twice as big
- **Where:** `custom.css`, `input.calendar-date-switcher` and `button.ca-calendar-nav`.
- Church Admin leaves these buttons unstyled, so they showed as the browser default: 13 px Arial, about 22 px tall, raised border.
  - The `calendar-date-switcher` buttons are Previous / Next on the "Upcoming events" list (members' hub and Calendar page).
  - The `ca-calendar-nav` buttons are Prev / Next / Today in the Calendar page's day panel.
- They're now about twice the size: 26 px site font, at least 44 px tall (Previous 68 × 22 to 142 × 47, Next 43 × 22 to 99 × 47). They use the same light grey, borderless, 3 px-corner look as the other secondary buttons.
- **Checked:** the day panel's buttons still fit inside it, with Prev and Next on one row and Today below.

### Site-wide broken link scan, and fixes
- **Scan:** all 61 published pages and posts, every link, button and image inside the content, plus the header and footer once. That's 236 unique addresses.
  - Pages on this site were checked for 404s. Outside sites were checked with a browser-like request.
  - **Buttons without a link: 0.**
- **Fixed** (page backups in `backups/links/`):
  - **Engage and Serve Emerson Chapel:**
    - "Worship" (in "Worship Team") went to `/how-we-worship` (404). It now goes to **An Overview of Worship** (`/an-overview-of-worship/`).
    - "lay-led congregation" went to `/layled-congregation` (404). It now goes to **Our Congregation** (`/our-congregation/`).
  - **Engage, Outreach Partners:** two links pointed at pages that have moved.
    - Youth In Need Street Outreach now goes to `https://www.youthinneed.org/`.
    - Planned Parenthood of the St. Louis Region now goes to `https://www.plannedparenthood.org/planned-parenthood-great-rivers`, the affiliate's current name.
  - **Welcome! (412):**
    - "Newcomer Information Form" went to `/newcomer-information-form` (404). It now goes to `/contact-newcomer-information/`.
    - "Learn More…" went to the dead dev site (`emersondev1.bloomenterprises.org/about/`). It now goes to **Who Are We**.
  - **Church Leadership (192):** "View the Constitution and Bylaws" pointed at an old Squarespace `/s/…pdf` address. It now uses the same PDF from the Media Library (`2023/04/Emerson_Bylaws_July_2019_ApprovedAmendedVersion.pdf`, attachment 482).
  - **Giving (621)** changed to a **draft**. It showed visitors a Church Admin "Please setup payment gateway" message with a broken setup link, and nothing links to it since the footer change.
- **Waiting on the church** (in the checklist):
  - Who Are We "vision" (`/our-vision` doesn't exist)
  - Who Are We Covenant "here" (the PDF isn't on the site)
  - three Outreach Partners that closed: Caminamos Juntos, Story Stitchers, UU Trauma Response Ministry
  - five sites that block automated checks
  - the 3 Welcome! images, which are still loaded from the dead dev site
- **Scanning note:** macOS `python3` has no CA bundle by default, so every `https` check failed with `CERTIFICATE_VERIFY_FAILED` until `SSL_CERT_FILE=/etc/ssl/cert.pem` was set. Cross-check outside links with `curl`, which uses the system certificates.

### Local Docker: PHP calendar extension added (Calendar PDF works)
- The Calendar page's "This calendar PDF" link gave HTTP 500 locally: `Call to undefined function cal_days_in_month()`. Church Admin needs PHP's `calendar` extension, which the stock `wordpress` image lacks.
- **New `Dockerfile`:** `FROM wordpress:6.8-php8.2-apache` plus `docker-php-ext-install calendar`. `docker-compose.yml` now builds it (`build: .`, image `emerson-wordpress:6.8-php8.2-apache-calendar`).
  - The `wp_core` and `db_data` volumes are unchanged, so no site data was touched.
- **Test:** the PDF downloads (HTTP 200, `application/pdf`, 24 KB).
- **On the live site:** nothing travels with WPvivid here, because this is the server's PHP setup. The checklist has a post-restore check.

### Engage (68): missing banner photos replaced with blue banners
- The same treatment as Serve Emerson Chapel: the six cover blocks now use a solid logo-blue background (`#3f4fa0`, class `emerson-banner`, 12 rem, rounded) instead of photos that are missing everywhere, live site included (404).
  - The banners are "Serve Our Wider Community" (`2022/07/image.jpeg`, attachment 136), "Serve Emerson Chapel", "Womyn's Web", "Men's Night", "Circle Suppers" and "Board Meetings".
- All six were kept. On Engage both "Serve…" banners are section headings, not a repeat of the page title.
- A leftover `id="yui_3_17_2_…"` (from the old Squarespace site) was removed from the first banner.
- **Backup:** `backups/engage/engage-68-before.html`
- **Bottom of the page:** the plain "Make a Contribution" line and the unlinked "Sign up for Newsletter" button became a centred pair of buttons:
  - **Make a Contribution:** Emerson green (Vivid green cyan `#00d084`, dark text, class `contribute-button`), linking to `/make-a-contribution/`.
  - **Sign up for Newsletter:** Emerson blue (`#3f4fa0`, white text, new class `newsletter-button`, added to the blue button rule in `custom.css`), linking to `/ways-to-connect/`.
  - Backup before this step: `backups/engage/engage-68-before-buttons.html`.

### Serve Emerson Chapel (344): first banner removed by the intern
The "Serve Emerson Chapel" banner at the top of the page was removed in the editor, 2026-09-30 13:27, because it only repeated the page title. Four banners remain.

### Members' hub: "WordPress dashboard" and "Church Admin" links for the people who use them
- **Where:** `[emerson_member_links]` in `inc/members.php`, which is the "Signed in as …" line at the top of the hub.
- **"WordPress dashboard"** (`/wp-admin/`) shows only to users who can edit posts: administrators, editors, authors and contributors.
- **"Church Admin"** (`admin.php?page=premium_church_admin`) shows to administrators and anyone listed in **Church Admin → Settings → Permissions** (`church_admin_user_permissions`).
  - That's currently 10 accounts. Most are regular subscribers, for example Harlan Bloom (directory, rota, calendar, sermons…) and Michelle Z. (directory, giving).
  - Church Admin's menu needs only `read`, and then applies its own per-area permissions.
- **Regular members** see neither link.
- **Checked** as an administrator, an editor, a contributor, Harlan, Michelle and a plain subscriber.
- **Why it matters:** since 2026-09-29, members who log in land on `/members/` instead of wp-admin. That includes those subscribers with Church Admin permissions. This link is now their way into Church Admin.

### Edit Profile: "Cancel / Return" button back to the members' area
- **Where:** `inc/members.php` (Profile Builder hooks) and `custom.css` (`.wppb-user-forms a.emerson-profile-back`).
- A light grey button now sits beside **Update**, the same size (150 × 50 px). It's a plain link to `/members/`, so it never submits or saves anything.
  - It reads **Cancel** when the page is opened.
  - It reads **Return** after Profile Builder reports a successful save (`wppb_edit_profile_success`, "Your profile has been successfully updated!").
  - If you start editing again after saving, it switches back to **Cancel**, because those changes aren't saved.
- **Why:** after saving there was no way back to the members' area except the menu.
- **Tested** with a temporary member account, deleted afterwards:
  - opened: "Cancel"
  - Update with no changes: success message and "Return"
  - typed in Website: "Cancel"
  - clicked: landed on the members' hub, and the typed change wasn't saved
- Profile Builder's own **"User to edit"** dropdown, shown only to administrators (`edit_users`) and listing non-admin accounts, is built into the plugin. It was left unchanged on purpose.

### Google Maps no longer loaded while there's no API key
- **The warning:** the Cursor browser console showed a Google Maps "no API key" warning on the Members page.
  - Church Admin's registration shortcode loads the Maps API with `key=` left empty, because `church_admin_google_api_key` isn't set, locally or in the live backup. So the live site shows the same warning.
  - Maps is loaded only on `/members/`.
- **Fix** (`inc/members.php`): while that setting is blank, the `church_admin_premium_google_maps_api` script tag and WordPress's `dns-prefetch` hint for `maps.googleapis.com` are left out. Visitors' browsers no longer contact Google on that page.
  - As soon as a key is entered in **Church Admin → Settings**, both come back on their own. Tested with a temporary key, which was removed afterwards.
- **Registration tested without Maps:**
  - Step 1 (email, "Next") leads to step 2, the full form with 32 fields.
  - The address field works, and there are no JavaScript errors.
  - The test stopped before "Save", so no records were created.
- **Browser-test note:** Church Admin's step 1 rejects a submission made within 1.5 s of the page loading ("Alright sparky!"). A form that seems to just reload during automated testing may simply have been checked before the next page loaded.

### Members page: registration "Next" button sized like "Log In"
- **Where:** `custom.css`, `#ca-first-step input[type="submit"]`.
- Church Admin's email-first registration button had no styling, so it showed as the browser's default (43 × 22 px, 13 px Arial, raised border).
- It now matches the Profile Builder "Log In" button: at least 150 × 50 px, 0 15 px padding, 16 px site font, 3 px corners, no border. It keeps its light grey (`#efefef`, darker `#e0e0e0` on hover) with dark text (`#1e1e1e`).
- **Correction to 2026-09-29:** the blue pill style added for Profile Builder buttons (`.wppb-user-forms input[type="submit"]`) never took effect. Profile Builder's own rule, `.wppb-user-forms input[type="submit"]:not(.wppb-delete-account)`, is more specific. So "Log In" is still Profile Builder's dark grey (`#333`). That was left as is on purpose.

### Who Are We (190): Our Values updated
- **New sentence** after "…each principle is drawn from many sources.": "The Unitarian Universalist Association officially replaced its traditional Seven Principles and six sources in 2024 with a new framework of interconnected shared values centered around love."
- **Links fixed:** both used to point at pages that don't exist on this site (404). Both now open in a new tab, like the page's other outside links.
  - **"Seven Principles"** was `/seven-principles/`. It now goes to `https://www.uua.org/beliefs/what-we-believe/principles`.
  - **"click here"** was `/roots-of-unitarian-universalism/`. It now goes to `https://www.uua.org/beliefs/who-we-are/history`.
- **Image:** `images/Seven_principles.jpg` was uploaded to the Media Library (attachment 957, 889 × 893). It's placed centred under the paragraph at 480 px wide.
  - The alt text reproduces the image's words: the heading and all seven principles.
- **Backup:** `backups/who-we-are/who-are-we-190-before.html`
- **On the live site:** carried by the WPvivid restore. The image is in `uploads/`, which is in the backup, not in GitHub.
- **Later the same day:**
  - The intern removed a stray purple block from `Seven_principles.jpg` in Photoshop and replaced the Media Library file. Its thumbnails were regenerated (`wp media regenerate 957`).
  - Per the church's notes, **`images/love_at_the_center.jpg`** was added (attachment 959, 1080 × 1080, alt text describing the love-at-the-centre shared-values graphic).
  - Both images now sit **side by side** under the Our Values paragraph, in a two-column block (class `emerson-values-images`, vertically centred). Seven Principles is on the left and Love at the Center on the right.
    - On desktop they're 313 px each. Below 782 px wide, including phones, they stack.
  - Backup before this step: `backups/who-we-are/who-are-we-190-before-side-by-side.html`.
  - **Dark bar under Love at the Center (fixed):** it looked like part of the heading below, but it was in the image file itself.
    - Checks: the heading, image, figure and columns have no border, shadow or background. The source file's bottom 6 pixel rows were 100% dark.
    - The bottom 7 rows were trimmed with WordPress's image editor (GD, `crop(0, 0, 1080, 1073)`, quality 92). The file is now 1080 × 1073, and thumbnails were regenerated.
    - `images/love_at_the_center.jpg` is the trimmed version. The untouched original is `images/love_at_the_center-original.jpg`.
    - **Note:** macOS `sips --cropToHeightWidth … --cropOffset 0 0` crops from the **centre**, not the top-left. The first attempt took 3 px off the top and left 3 dark rows at the bottom. Use WordPress's editor or Photoshop for edge trims.

## 2026-09-29

### Sunday Services (64): padding in the grey Order of Service boxes
- **Where:** `custom.css`. The rule is general: any `.wp-block-column.has-background` gets 1.5 rem × 1.75 rem of padding and 12 px corners, and the first and last items in it lose their outer margins.
- Only this page used background colours on columns. Future grey boxes get the same spacing automatically.
- Paragraphs inside those boxes (the quoted readings and the attribution) are centred to match the centred headings.

### YouTube sermons: checked 2026-09-29
- **The site has no link to a YouTube channel.** The Sunday Services page's "Past Sermons" section contains only the placeholder text "display recent sermons from YouTube".
- **Past Sermons page (213):** shows `[aiovg_videos limit="15" orderby="title"]`, from the All-in-One Video Gallery plugin.
  - There are 35 published videos, and all 35 still play.
  - 33 are on **@emersonunitarianuniversali1222** ("Emerson Unitarian Universalist Chapel", 2023–2024).
  - 2 are on **@EmersonUUChapel** (May and July 2026). That one looks like the church's current channel.
- Both channels are public.
- **Changes made:**
  - **Sunday Services (64):** the placeholder is replaced by "Missed a Sunday? Here are our most recent services." It's followed by the 3 newest sermon videos (`[aiovg_videos limit="3" columns="3" orderby="date" order="desc" …]`) and two buttons: **All past sermons** (`/past-sermons/`) and **Our YouTube channel** (`https://www.youtube.com/@EmersonUUChapel`, opens in a new tab).
  - **Past Sermons (213):**
    - The page now sorts newest first (`orderby="date" order="desc"`).
    - It has **page numbers** (`show_pagination="1"`). Before, only the first 15 of 36 videos could be reached.
    - An empty `<h1>` was removed.
  - **Video dates:** all 36 gallery videos have their date set to the sermon date in their title, at 10:00. Upload dates were batchy, so 8 would have been out of order, including the top two.
    - Original dates: `backups/sermons/video-dates-before.json`.
    - **For future uploads,** set the video's date (Publish → date) to the Sunday it was recorded, or it will sort by upload day.
- **Backups:** `backups/sermons/sunday-services-64-before.html`, `backups/sermons/past-sermons-213-before.html`
- **How the videos get onto the site:** no YouTube account, login or API key is involved.
  - Each sermon is a post in the **All-in-One Video Gallery** plugin (wp-admin → **Video Gallery**), where someone at the church pasted the video's YouTube link.
  - The pages play those links through YouTube's normal public embed, and the thumbnails come from YouTube's public image server.
  - The channel names came from YouTube's public lookup for those video links. The "Our YouTube channel" button is new; no channel had been linked on the site before.
- **Not changed:** the gallery shows its own view counter under each video ("1 views"). It counts plays on this site only, not on YouTube. It can be turned off in **Video Gallery → Settings**.

### Members page (32) is now the login page and the members' hub
- **What was wrong before:**
  - The menu's "Member Login" linked to `wp-login.php`, the WordPress admin screen.
  - "Member Home" (706) was only a placeholder.
  - The "Rota" and "My availability to serve" links pointed at pages that don't exist.
- **Logged out,** the page shows:
  - the **Profile Builder login form**, in the site's design, with errors shown on the page and "Lost your password?" linking to `/password-reset/`;
  - the existing Church Admin **registration** form, which still needs office approval.
- **Logged in,** the same page shows the **hub**:
  - "Signed in as … · Edit my profile or password · Log out"
  - **Sunday services:** Zoom, service schedule, order of service, past sermons
  - **My serving schedule** (`my-rota`) and **Dates I can't serve** (`not-available`)
  - **Upcoming events** (the next 28 days) and a link to the full calendar
  - the **member directory** (Church Admin address list, map off because no Google Maps key is set)
- **How it's built** (`inc/members.php`, new):
  - `[emerson_visitors_only]` and `[emerson_members_only]` wrap the two halves, and can span several blocks.
  - `[emerson_member_links]` prints the signed-in line.
  - The logged-out half sets `DONOTCACHEPAGE`, so W3 Total Cache never caches the login form. Its security nonce would expire in a cached copy.
- **Logging in and out:**
  - Members, meaning anyone who can't edit posts, who log in through `wp-login.php` land on `/members/`. Staff still get wp-admin.
  - Log out goes to the homepage.
- **Church Admin bug worked around:** `[church_admin type="my-rota"]` adds an extra `</div>` when a login isn't linked to a directory person. That pushed the rest of the page out of the content column. Church Admin shortcode output now goes through `force_balance_tags()`.
- **Old pages** changed to drafts, with 301 redirects:
  - `/member-login/`, `/member-home/`, `/login/` and `/log-in/` redirect to `/members/`
  - `/edit-profile-2/` redirects to `/edit-profile/`
- **Menu:** "Members" is now a plain link. Its only sub-item was "Member Login".
- **Styling:** the Profile Builder buttons use the blue pill style.
- **Backup:** `backups/members/members-32-before-hub.html`
- **The directory shows only households that opted in** (`show_me`). Right now that's 2 of the 32 Members. Whether and how to ask members to opt in is a church decision.
- **Tests:**
  - logged-out view (no hub content leaks)
  - a wrong password gives an on-page error and the limiter counts it
  - correct login shows the hub, with no admin bar
  - "Edit my profile" loads, and "Log out" lands on the homepage, logged out
  - `wp-login.php` sends a member to `/members/`
  - old URLs redirect
  - A temporary test account was created and deleted afterwards.

### Login attempt limiting: Limit Login Attempts Reloaded 3.3.10 installed
- **Defaults kept:**
  - 4 wrong passwords lock that address out for 20 minutes.
  - 4 lockouts mean 24 hours.
  - After 3 lockouts the site admin (`com@`) is emailed.
- It covers `wp-login.php` and the Profile Builder form, because both use WordPress's own login check.
- **Privacy Policy (3):** new sentence about recording the IP address and username on failed logins. Backup: `backups/newsletter/privacy-policy-3-before-login-limits.html`. **Needs the church's review** with the rest of the policy.

### Newcomer form hand-off now finds its own fields
- The name, email and newsletter question are found by type and wording: the first Name field, the first Email field, and the multiple-choice question whose label contains "newsletter". The fixed field IDs are gone.
- If the form is deleted, or the question disappears or loses its "Yes" choice, administrators see a **warning at the top of wp-admin** and the error log records it.
- **Tested:**
  - on the real form, it finds name 0, email 1 and answer 6, with no warning
  - with the question deleted or renamed, it's reported as missing
  - with the question recreated as a new field, it's still found

### Newcomer form: "Yes" to the newsletter starts the same email confirmation
- **Where:** `inc/newsletter.php`, a `wpforms_process_complete` hook for form 351. The field IDs are constants at the top: name 0, email 1, newsletter answer 6.
  - The form, field and answer checks are exact. If the form is rebuilt or the question is renamed or given new IDs, update those constants.
- **What happens now:** a "Yes" answer does exactly what the pop-up does.
  - A waiting sign-up is created (7 days).
  - The "Please confirm your subscription" email is sent as an **Action Scheduler background job**, so the form's "Thanks" isn't held up by NetSol.
  - After the click, the person is saved to Church Admin as Mailing List, and the office gets "New newsletter subscriber" with **Signed up from: Newcomer Information form**.
  - The office still gets the usual "Newcomer information" email too.
- **"No", or no answer:** nothing extra happens.
- **Shared code:** the pop-up, Ways to Connect and the Newcomer form now use the same `emerson_nl_start_pending()` and `emerson_nl_send_confirmation()`. The 10-minute resend wait, the lower-casing and the failure clean-up apply to all three.
- **Record labels:** Church Admin's recorded reason now names the source in words, for example `Confirmed newsletter sign-up (Newcomer Information form)` or `(homepage pop-up)`. Earlier test records still say `(website popup)` / `(website page)`.
- **Thank-you message:** form 351's confirmation text gained "If you asked to receive our newsletter, please check your inbox for an email from us and click the link inside to confirm." Backup: `backups/forms/wpforms-351-before-newsletter-confirmation.json`.
- **Tests** (save-only mode):
  - **"Yes":** confirmation email "Hi Nora", sent by a background job. The capitalised address was lower-cased. The link showed "You're subscribed", and the Church Admin record and office email were correct.
  - **"No":** only the office email.
  - **Pop-up:** still answers in 0.19 s.
- The Privacy Policy wording already covers any website newsletter sign-up, so no change was needed.

### Main menu: "Ways to Connect" added under Engage; dead "Engage" item fixed
- **Where:** `parts/header.html`. The header has no override saved in the database, so the theme file is what shows.
- **Ways to Connect (218)** was only reachable through inline links: one on the homepage, five each on Engage and Serve Emerson Chapel. It's now the third item in the Engage dropdown, after Engage and Serve Emerson Chapel.
- **Why it isn't redundant with the pop-up or the Newcomer form:**
  - The pop-up is homepage-only, is hidden from logged-in members, and stays away for 30 days after "No thanks", so it isn't a place people can come back to.
  - The Newcomer form's newsletter question only emails the office. It skips the confirmation email and Church Admin's Mailing List.
  - Ways to Connect is also where the social media links live.
- **The "Engage" item inside the Engage dropdown** linked to `#`, so it just reloaded the current page. It now goes to the Engage page (68).

### Serve Emerson Chapel page (344): missing photos and the two "forms"
- **What was wrong:** the live site has the same problems; our copy didn't cause them.
  - **Five banner photos are missing.** They're gone from the Media Library (attachments 25, 140–143), the uploads folder, the 2026-09-24 live backup and the live server (404), and the Wayback Machine never saved them. They came from the old dev site `emersondev1.bloomenterprises.org`, which no longer responds. Each showed as a 430 px grey box with a broken-image icon.
  - **"Make a Contribution" and "Newcomer Information Form"** at the bottom were only plain text, never forms. The March 2023 revision already looked like this.
- **What changed:**
  - **Banners:** the five cover blocks now have a solid logo-blue (`#3f4fa0`) background instead of the missing photo. They use the new class `emerson-banner` (12 rem tall, rounded corners, in `custom.css`) and keep the same white titles.
  - **Buttons:** the two text lines became buttons, matching the homepage pair.
    - **"Make a Contribution"** (mint, class `contribute-button`) links to `/make-a-contribution/`, which has the PayPal Donate button.
    - **"Newcomer Information Form"** (blue, class `newcomer-button`) links to `/contact-newcomer-information/`.
    - Both reuse the homepage button styles in `custom.css`.
- **To put the photos back later:** the original addresses are in `backups/serve/serve-344-before.html`. They are `2021/11/service-1024x407.png` (page banner), `2022/07/women-together-1024x682.jpeg`, `2022/07/men-together-1024x628.jpeg`, `2022/07/circle-suppers-1024x683.jpeg` and `2022/07/board-meetings-1024x684.jpg`. Once uploaded, pick each one as the cover block's image in the editor and set the overlay back to about 50 %.
- **Backup:** `backups/serve/serve-344-before.html`

### Footer "Giving" link now goes to Make A Contribution
`patterns/footer.php`. It used to point to `/giving/`, which is a Church Admin giving report. Logged-out visitors got "Only users with giving permission have access" and a login box, not a way to give.

### "Pledges-Testing" page (417) changed to a draft
It was a published test page showing only a login box. Nothing linked to it. It's still there in **Pages → Drafts** if anyone needs it.

### Sign-ups no longer wait about 10 seconds for email
- **Why it was slow:** NetSol's SMTP server takes about 10 seconds per message. Measured without sending: connect 1.9 s, TLS 1.2 s, login 2.3 s, plus the message itself. Every form waited for its email before answering the visitor.
- **Newsletter** (`inc/newsletter.php`):
  - The sign-up and the confirmation link now answer the browser first, then send their email. The new `emerson_nl_release_visitor()` handles PHP-FPM, LiteSpeed and Apache mod_php.
  - If the confirmation email fails, the waiting sign-up and the 10-minute resend wait are removed, so the person can retry at once. The failure goes to the PHP error log.
  - The resend wait now starts when the sign-up is accepted, so a double-click can't send two emails.
  - The "mailfail" message was removed, because the visitor has already had their answer by the time sending could fail.
- **Newsletter button** (`newsletter.js`, `custom.css`): a spinner shows while sending. After 2 seconds the note "Still working on it. This can take a few seconds…" appears. It's a fallback in case the live host can't answer early.
- **Newcomer form:** **WPForms → Settings → Email → Optimize Email Sending** is turned **on** (`wpforms_settings['email-async']`, local DB). WPForms sends the notification as a background job.
- **Tests**, with an artificial 8-second delay added to every email and nothing sent:

  | Test | Before | After |
  |---|---|---|
  | Newsletter sign-up, with JavaScript | ~10 s | 0.25 s; email sent 8 s later in the background |
  | Newsletter sign-up, without JavaScript | ~10 s | 0.24 s |
  | Confirmation link | ~10 s | 0.24 s; Church Admin record and office email follow |
  | Confirmation link used twice | n/a | "expired or already used" |
  | Email fails after the answer | n/a | waiting sign-up removed, retry allowed, error logged |
  | Slow reply (4 s, forced) | n/a | spinner at 1 s, "Still working" note at 2.5 s, then "Almost done!" |
  | Newcomer form | ~10 s | 0.25 s; background job sent the office email with Reply-To set to the newcomer |

### Local loopback fix (new `mu-plugins/local-loopback.php`)
- **Problem:** locally, WordPress couldn't call itself. The site address uses the Mac's port 8080, but Apache inside Docker listens on port 80. So WP-Cron, WPForms background email, Action Scheduler and the Site Health loopback test never ran.
- **Fix:** requests to `http://localhost:8080/` from inside Docker are sent to the web container (`http://wordpress/`) with the original Host header. It does nothing unless the site address is `localhost:8080`.
- **Side effect:** scheduled jobs (Zephyr, WPForms summaries) now run locally on their normal schedule. With redirect mode on, anything they send goes only to the test mailbox.

### Local mail catcher: file names no longer collide
Two emails with the same subject in the same second used to overwrite each other in `local-mail/`. A `-2`, `-3` and so on is now added instead.

### Local email can now be sent for real, to one test address only
- **Where:** `wp-content/mu-plugins/local-mail-catcher.php`. The switch is the option `emerson_local_mail_redirect_to` in the local DB.
- **What changed:** the catcher has a second mode.
  - **No address set** (default): it saves only, as before.
  - **Address set:** email goes out through the original WP Mail SMTP (NetSol) settings, but every message is sent only to that address. To, CC and BCC are replaced, and the subject is prefixed with `[LOCAL TEST → original recipient]`.
  - It still does nothing on the live site.
- **Why this approach:** the local database has the real members and users. A redirect guarantees that no test, bulk email or password reset can reach them.
- **Checked:** every mail-sending plugin (Church Admin, WPForms, Profile Builder, Zephyr, Calculated Fields) goes through `wp_mail()`, so the redirect covers them all. Church Admin's "SMTP server" setting doesn't bypass it. Both NetSol SMTP hosts answer from the Docker container on port 587.
- **How to switch:** see `TROUBLESHOOTING.md`, "Testing real email locally".
- **Current state:** redirect mode **on**, set to the intern's personal test address. The address is deliberately left out of these files.
  - Test email sent on 2026-09-29; NetSol accepted it.
  - **Undo for production:** delete the option before the WPvivid backup (`DEPLOY-WPVIVID.md` step A3). It's listed in the local-vs-live table in that file.

### Overdue scheduled jobs rescheduled
- **What changed:** the Zephyr jobs (hourly, daily and weekly, including task notifications) had been overdue since March 2025. So had the WPForms weekly summary. They would have fired as soon as email worked, locally and again on the live site after the restore. The Zephyr jobs now run on their normal timing from 2026-09-29. WPForms rescheduled its own summary for 2026-10-05.

### Test and launch checklist
- **Where:** `TEST-AND-LAUNCH-CHECKLIST.md` (new). `DEPLOY-WPVIVID.md` step A3 now says to switch email back to save only and remove test data before the backup.

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
