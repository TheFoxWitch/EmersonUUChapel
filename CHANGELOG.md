# Changelog — emersonuuchapel.org local rebuild

Every change made to the local copy, newest first. Each entry says:

- **Where** the change lives: **File** (theme or plugin files, travels with GitHub / an upload of `wp-content/themes/emerson-uuchapel/`) or **Local DB** (the local database only, has to be redone on the live site or migrated with WPvivid).
- **Backup**: the original content, saved before the change.
- **On the live site**: how to repeat it there.

Problems we ran into and how they were fixed are in [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md).

---

## 2026-10-05

### Private spreadsheets (Finance role)
- Spreadsheets are **not** in Media. They go in `private-files/` on this Mac, mounted inside Docker at `/var/emerson-private` (outside the web root). A public URL cannot fetch them.
- The Members hub lists them only for **administrators** and the WordPress **Finance** role. Other logged-in members get no HTML for that section.
- Downloads go through a logged-in PHP handler. PDFs open in the browser. **Excel (.xlsx)** and CSV open as a table on a gated page, with a file download. An Apple **Numbers** package downloads as a zip.
- **Local DB:** `[emerson_private_files]` on page 32. Backup: `backups/members/members-32-before-private-files.html`.
- **File:** `inc/private-files.php`, `docker-compose.yml`, `.gitignore`. Test file `Magazines.numbers` is local only, not in git.
- **Grant access:** Users → that person → Role **Finance**.

### Who Are We: Covenant of Right Relations file
- The word **here** under Covenant of Right Relations now opens the church’s copy: `Covenant-of-Right-Relations.pdf` (Media). The `.docx` was replaced so the browser can display it.
- **Local DB:** page 190. Backup of the previous Word link: `backups/pages/page-190-who-are-we-before-covenant-pdf.html`. First (Squarespace) backup: `backups/pages/page-190-who-are-we-before-covenant-link.html`.
- **On the live site:** upload the same PDF in Media, then point that **here** link at it.

## 2026-10-04

### Serving dates use the account first and last name
- The heading “Tick the dates … can serve” was using the Church Admin first name, which on some linked accounts is the email-style username. It now uses the WordPress account’s first and last name (then display name, then the directory row).
- **Signed in as** on the Members hub uses the same name.
- **File:** `inc/members.php`, `inc/serving-dates.php`.

### Serving dates summary lists Available dates
- Above “Tick the dates … can serve”, the line still says how many of the listed dates they can do. It now follows with **Available:** and those dates, not **Not available:** and the rest.
- **File:** `inc/serving-dates.php`.

### Calendar PDF dropdown
- The **Choose a PDF** select sat 7px too low beside “Yearly Planner PDFs”. `margin-top: -7px` on `form[name="guideform"]` in `custom.css`.
- The list / Planning Calendar chooser table (`ca-calendar-list-chooser`) now has `margin-bottom: 2em` so it sits off the month grid.

### Header: About and Worship are labels, not links
- There is no About page and no Worship page. Those words were `#` custom links so they would look like the rest of the nav.
- They stay in the menu as labels. The drop-downs still open. Engage is unchanged (it has a real page).
- **File:** `parts/header.html` (empty URL), `inc/navigation.php` (render the parent as a `<span>` inside the `<li>` instead of an `<a>`).

### Worship Past Sermons: Load more
- Sunday Services (page 64) still shows three newest sermons first. A **Load more** pill under the grid loads the next three via AIOVG’s Ajax (no extra page). Each click adds another row until the gallery is done.
- **Local DB:** shortcode now includes `show_more="1" more_label="Load more"`. Backup: `backups/pages/page-64-worship-before-load-more.html`.
- **File:** pill is small light gray / black text; hover, focus, and click go black / white (`.page-id-64 .aiovg-link-more` in `custom.css`). The pill is hidden while the next row is fetching so it does not cover AIOVG’s loading spinner.

### Homepage meeting-location sentence
- Under **Meeting Location**, the time was sitting in the middle of the address (“located at 10 AM, 3900 Shady Springs…”). It now reads: “We are meeting at 10 am, at the St. Charles YMCA located at 3900 Shady Springs Ln, St Peters, MO 63376”.
- **Local DB:** page 15. Backup: `backups/homepage/homepage-15-before-meeting-location-wording.html`.
- Removed the green **August 9 / Vote on new meeting space** lines above the Pledge and PayPal Fees buttons (that date has passed). Backup: `backups/homepage/homepage-15-before-august9-vote.html`.
- **Checklist section 4:** ask the church whether to look into auto-importing new YouTube sermons, or keep adding each one in Video Gallery by hand.

### Site icon (favicon)
- Replaced the default WordPress “W” with the Emerson logo. Source file: `wp-content/themes/emerson-uuchapel/assets/images/emerson-site-icon.png` (512×512 PNG).
- **Local DB:** uploaded as Media attachment 999 and set as **Site Icon** (`site_icon`). WordPress now serves the tab icon from `uploads/2026/10/`.
- The theme PNG was enlarged so the chalice fills more of the 512×512 square (2026-10-04, second pass). Media 999 and the generated sizes were replaced from that file.
- Safari **tab** icon: `safari-pinned-tab.svg` now matches Apple’s spec (`viewBox="0 0 16 16"`, one layer) for **pinned** tabs. Regular tabs use `/favicon.svg` (Safari 26+) plus 16/32 PNG and `/favicon.ico`. Check **Safari → Settings → Tabs → Show website icons in tabs**.
- **On the live site:** the theme file travels with the theme upload; the attachment and option travel with the WPvivid database.

### Docker PATH tidy (this Mac only)
- Removed the extra `~/.docker/bin` lines from `~/.zprofile` (Docker Desktop's auto-added block) and `~/.zshrc`. One line remains in `.zprofile`, ahead of Homebrew. Completions in `.zshrc` are unchanged.
- Checked in a clean login shell: `command -v docker` is `/Users/kitsunearisu/.docker/bin/docker` once, and `docker compose ps` still shows `db` and `wordpress`.
- **Where:** this Mac's shell files, not the repo. Checklist section 1 is ticked.
- **Checklist section 2:** added the Harlan-backup compare note (what to look at, and why `_not_available` row counts must not be compared).

---

## 2026-10-03

### Twitter/X removed; Church Leadership list updated
- They no longer use X/Twitter. The footer icon is gone. Ways to Connect (218) now lists Facebook, Instagram and YouTube only. Two leftover empty Twitter links on Religious Education (203) and Our Congregation (209) are removed.
- **Church Leadership (192):** officers and ministry leads updated from the board's list (Nancy Fish is no longer president). Personal emails stay off the page until leadership confirms they want them public (checklist, section 4). Backup: `backups/pages/page-192-leadership-before-board-update.html`.
- **"View the Constitution and Bylaws"** is now a centred Emerson-blue pill button (`bylaws-button`, same style as Newcomer / Newsletter). The PDF address is unchanged. Backup: `backups/pages/page-192-leadership-before-bylaws-button.html`.
  - The buttons block has `margin-top: 70px` and `margin-bottom: -41px`, giving **70 px above and 70 px below** (same as Serve / Engage / Sunday Services). Backup: `backups/pages/page-192-leadership-before-button-spacing.html`.
- **Covenant of Right Relations:** linked 2026-10-05. The **here** on **Who Are We (190)** now points at Media `Covenant-of-Right-Relations.pdf`.
- **Pledge form question** added to the checklist: the homepage button still says 2024-25 and uses last year's Google Form.
- **Checklist section 5:** switching themes later is not a one-click swap. Launch on `emerson-uuchapel` first; custom CSS, header/footer patterns and `inc/*.php` have to be ported (or kept) if a new theme is chosen.

### Footer social icons, and the official YouTube channel
- **YouTube check:** the 36 sermon videos on the site are this church. 34 are on **@emersonunitarianuniversali1222** ("Emerson Unitarian Universalist Chapel", 2022–2024, including the four St. Louis UU churches service and named Emerson speakers). The two newest (19 July and 24 May 2026) are on **@EmersonUUChapel**, with the same "YYYY MM DD" titles. The church's Google listing names the first channel as official.
- **Sunday Services (64):** "Our YouTube channel" now goes to `https://www.youtube.com/@emersonunitarianuniversali1222` instead of `@EmersonUUChapel`. Backup: `backups/pages/page-64-worship-before-official-youtube.html`.
- **Ways to Connect (218):** the social sentence now also links YouTube. Instagram stays `emersonuuchapel` (the listing). The typed handle `emersonuucommunity` is Emerson UU Church in Canoga Park, California, not this chapel. Backup: `backups/newsletter/ways-to-connect-218-before-youtube.html`.
- **Footer** (`patterns/footer.php`): Facebook, Instagram and YouTube icons sit under the logo and the "A liberal, welcoming…" line. Style: `.emerson-footer__social` in `custom.css` (Emerson blue, 24 px, centred on phones). After editing a pattern file, the pattern cache was cleared.

## 2026-10-02

### Service Schedule (437): Sunday calendar instead of an empty rota
- The page only had Church Admin's `[church_admin type="rota"]`. That shortcode lists **who is assigned** to greeter, liturgist and the other jobs. Nobody is assigned on any date after 29 September 2024, so it printed "No schedule for this month".
- **The Sundays themselves were already in Church Admin's calendar** (category "Sunday Service", 10:00 am, through April 2029). The public page just wasn't reading them.
- **New shortcode** `[emerson_service_schedule]` (`inc/service-schedule.php`): a month grid like the Calendar page, with ← / → for the previous and next month (`?month=YYYY-MM`). Each Sunday shows **10:00 am**, **Sunday Service**, and **Emerson Chapel** linking to Visit Us (`/visit-us/`), where the address and map live. Change Visit Us when the church confirms the address; this page follows it. The question is in the checklist, section 4.
- **Logged-in members** see an outlined **You can serve** mark on Sundays they ticked on the Members page. If the office later assigns them a job on the rota, that job name is shown instead (solid blue **You're serving: Greeter**). Guests, and members who haven't ticked any dates, see the same calendar without marks. Monday ticks from the serving form are not shown, because this calendar only lists Sunday services.
- Church Admin's rota shortcode is still on the page underneath. The empty "No schedule for this month" output is hidden; if the office starts assigning people, that roster will appear under the calendar.
- Style: `custom.css`, the `.emerson-schedule` rules. On phones (under 600 px) the grid becomes a list of the Sundays only.
- Backup: `backups/pages/page-437-schedule-before-calendar.html`.

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

### Sunday Services (64): Order of Service columns in Emerson blue
- The two **Order of Service** columns were light grey (`#e7e7e7`) with black text. They're now **Emerson blue `#3f4fa0` with white text**, set in each column block's colour settings. Headings, verses and the attribution inherit the white; I checked that none stays black.
- White on Emerson blue has a contrast of about 7.6 : 1, which passes WCAG AA and AAA for normal text.
- Only these two columns changed. The coloured columns on the homepage (15) are separate blocks and keep their colours.
- Backup: `backups/pages/page-64-worship-before-blue-columns.html`.

### Visit Us (58): map added (OpenStreetMap, no API key)
- The placeholder text "Embed Google Map" is replaced by an **OpenStreetMap** map with a pin on the **St. Charles YMCA, 3900 Shady Springs Ln, St Peters** (38.80098, −90.57080, an exact house-level match from OpenStreetMap's address search), plus a "View larger map" link underneath.
- **Why not Google:** a plain map embed needs no API key with either service, and OpenStreetMap sets no tracking cookies, so the Privacy Policy needs no change. The **Google Maps API key** in the checklist is still only needed for Church Admin's own maps (the directory map and the registration address lookup). Google has no test or dummy key.
- **Style:** `iframe.emerson-map` in `custom.css`: full column width, 4:3, rounded corners. The frame has a descriptive title for screen readers, loads lazily, and sends no referrer.
- **Map service question:** OpenStreetMap or Google (a plain embed, or using the church's own API key)? It's in the checklist's church questions, with the steps to switch.
- **Address question:** the text beside the map still says 122 North Fifth Street, St Charles, as the intern chose. That's in the checklist's church questions, together with the unpublished "Come Visit Us" draft (220).
- Backup: `backups/pages/page-58-visit-us-before-map.html`.

### Serve Emerson Chapel (344): bottom buttons centred between the text and the footer
- The **Make a Contribution** and **Newcomer Information Form** buttons sat 28 px below the last link ("Click here to view minutes from previous board meetings") and 122 px above the footer line.
- Moving the buttons down alone can't centre them, because the footer moves with them: the gap below is the site's normal spacing above the footer.
- **Fix on this page only:** that buttons block has `margin-top: 66px` and `margin-bottom: -52px` (block spacing settings). That gives **70 px above and 70 px below**, measured in the browser. Other pages are unchanged.
- Backup: `backups/pages/page-344-serve-before-button-spacing.html`.
- **Same fix on Engage (68)**, whose bottom buttons are **Make a Contribution** and **Sign up for Newsletter**, under the same board-minutes link. It had the same 28 / 122 px gaps, and is now 70 / 70 px. Backup: `backups/pages/page-68-engage-before-button-spacing.html`.
- **Same fix on Sunday Services (64, `/worship/`):** the bottom buttons are **All past sermons** and **Our YouTube channel**, under the Past Sermons video previews.
  - They sat only 11 px below the last preview line, so this page uses `margin-top: 83px` (and the same `margin-bottom: -52px`). That gives 70 px above and 70 px below.
  - Backup: `backups/pages/page-64-worship-before-button-spacing.html`, taken from WordPress's revision of the page just before the change.
- **Tip:** the Cursor browser sometimes shows an older copy straight after a save. If a change seems missing, reload once more.

### Religious Education (203): teacher sentence added
- At the end of the first paragraph, in bold: "Our Religious Education teacher, Lauren, is a paid and certified educationalist who designs the curriculum serving Emerson, and its community of kids." The note's typo "communinty" was corrected to "community".
- Backup: `backups/pages/page-203-religious-education-before-teacher.html`.
- **Then cleaned up the rest of the page** (backup: `backups/pages/page-203-religious-education-before-cleanup.html`):
  - **Children:** "Emerson's volunteer teachers are members of the congregation who are trained to use the UUA's curricula and programs." now reads "Lauren leads the program, drawing on the UUA's curriculum and programs, with help from trained volunteers in the congregation." The UUA link is unchanged; "curricula" was changed to "curriculum" at the intern's request.
  - **Infants and Toddlers:** "qualifiedchildcare" → "qualified childcare".
- **Possibly to come:** a photo of Lauren, if she agrees (in the checklist's questions for the church).

### Git history cleaned: a member's personal email removed (public repository)
- **What:** a church member's personal email and a member's login name had been committed in `TEST-AND-LAUNCH-CHECKLIST.md` (commit "Polish Who We Are, sermons and the members area", 2026-09-30). The repository is public.
- **Done on 2026-10-02:**
  1. Removed both from the current files: "Michelle Z.", and "one other member's personal-email login".
  2. Took a full mirror backup of the repository, outside the project: `~/Desktop/EmersonUUChapel-git-backup-before-history-rewrite-2026-10-02.git`. **It still contains the email; keep it local and delete it once GitHub has purged the old data.**
  3. Rewrote all history with `git filter-repo --replace-text`, replacing the two items with "[member email removed]" and "Michelle Z.".
     - All 22 commits are kept, with the same content, messages, authors and dates, but new commit IDs.
     - The latest files are byte-for-byte identical to before.
  4. Force-pushed `main`, with a lease guard so it would fail if GitHub had changed. Deleted the 9 merged `cursor/` work branches from GitHub, so only `main` remains.
- **Still to do: ask GitHub Support to purge.** GitHub keeps pull request copies, which we can't delete, and cached views of old commits.
  - **Pull request #1** points to old commit `e6ec437`, which still contains the email.
  - Use the form at https://support.github.com/request (the "Remove sensitive data" topic). Give the repository `TheFoxWitch/EmersonUUChapel`, the affected file `TEST-AND-LAUNCH-CHECKLIST.md`, the old commits (the first containing it was `9b9ca9a`) and PR #1. Ask them to remove cached views and the PR references.
- **Any other clone** of this repository (another computer) must be deleted and cloned again. Pushing an old copy would bring the email back.
- **From now on:** every git suite scans for personal data before committing, and these are never committed: member emails, login names, passwords and keys.

### Calendar PDFs fixed (year planner, "This calendar PDF", "This month's PDF")
- **Report:** choosing a Yearly Planner PDF, or clicking "This calendar PDF", showed nothing.
- **Why nothing appeared:** the server did send a PDF each time. Cursor's built-in browser simply doesn't open or download PDFs, so test PDFs in Firefox or Safari.
- **But the PDFs themselves were wrong.** These are bugs in Church Admin Premium 5.7.19 that are still there after updating to **5.8.0**:
  1. **Year planner:** all 12 calendars were the current month ("October 2026" ×12), whichever year was chosen.
  2. **Monthly PDF, months ending on a Saturday** (such as October 2026): an extra grey row didn't fit, so the page spilled onto a second page and every event was printed there instead of in its day.
  3. **Monthly PDF, January–September:** dates were built without the leading zero ("2026-9-06"), so no events were found.
  4. **"This month's PDF"** (in the month grid) linked to `/ca_download=…`, an address that doesn't exist. The monthly PDF also ignored which month was asked for.
- **Fix:**
  - **New `inc/calendar-pdfs.php`:** it answers the same Church Admin download links first (on `init`, before Church Admin) with corrected versions of both PDFs. It uses Church Admin's own PDF library and fonts, and the same security token for the year planner.
    - The year planner shows January to December of the chosen year, with event days in their category colour, and a key that fits.
    - The monthly PDF uses the month asked for (`start_date`), on one page, with up to three events in each day's box, then "More events…".
    - Files are named, for example `Emerson-calendar-2026-11.pdf` and `Emerson-year-planner-2027.pdf`.
  - **`assets/js/custom.js`:** corrects the "This month's PDF" address when it's clicked. The grid is redrawn by Ajax, so this is done on click rather than in PHP.
- **Church Admin updated 5.7.19 → 5.8.0** locally, as part of checking for an upstream fix. It travels to the live site with the WPvivid backup.
  - Backups, git-ignored because the plugin is a paid licence and the database contains member data:
    - `backups/plugins/church-admin-premium-5.7.19.tar.gz`
    - `backups/plugins/database-before-church-admin-5.8.0.sql.gz`
  - **After the update:** the site, the Calendar page and every Members hub feature still work (serving dates, the Save button, the directory search).
- **Tested** by rendering the PDFs:
  - the 2027 planner shows January–December 2027;
  - October 2026 has all four Sunday Services in their boxes, on one page;
  - January 2027 shows its events, so the missing zero is fixed;
  - clicking "This month's PDF" from November's grid returns the November PDF;
  - a forged year-planner token is still refused.

### Header menu: drop-downs no longer cut off on the left
- **Problem:** the drop-downs ran off the left edge of the screen: About between 844 and 965 px wide, and About and Worship between 966 and 1124 px.
  - The header navigation block is set to **justify items right**, so WordPress opens every drop-down leftward, lining up its right edge with the menu item.
  - At those widths the menu wraps onto its own line under the logo, which puts About and Worship near the left edge, so their 202 px drop-downs ran off it. At 1050 px, About's drop-down started at −114 px.
- **Fix:** one rule in `custom.css`, under the logo styles. The top-level drop-downs open rightward (`left: 0; right: auto`). There are no second-level drop-downs.
- **Tested:** I measured every drop-down open at 600, 700, 843, 844, 900, 965, 966, 1050, 1124, 1125, 1280, 1440 and 1920 px, and all fit on screen. The rightmost, Engage at 1920 px, ends at 1017 px. In the phone menu (390 px) drop-downs sit inside the full-screen menu, so they aren't affected.

### Calendar: month grid's Prev / Next are now ← → arrows (board member's notes)
- The month grid's **Prev** and **Next** buttons were too big for the cell beside the month name.
  - First they were shrunk to a third of their size, which made the text unreadable.
  - They're now plain blue **←** and **→** arrows (28 px, no button background), with a darker blue on hover.
- **How:** CSS only, in `custom.css` under `button.ca-calendar-nav.btn-info`. Church Admin marks the grid's Prev and Next with `btn-info`, and Today with `btn-warning`.
  - The words "Prev" and "Next" stay in the button at font size 0, so screen readers still announce them. The arrow is drawn by `::before`, with empty alternative text (`content: "\2190" / ""`) so it isn't read out as well.
  - The word itself wasn't swapped, because Church Admin's "Next" text is also used by other buttons, such as registration's Next. The grid is also loaded by JavaScript, outside the shortcode filters.
- **Vertical centring:** the arrows sat about 3 px lower than the middle of the month text, measured on the visible ink, not the boxes. The arrow glyph is now lifted 3 px (`position: relative; top: -3px` on `::before`), and the two are level to within 0.1 px.
- **Tested:** clicking → loads November 2026, and the arrows stay after the reload.
- **Not changed:** **Today**, and the list view's **Previous** and **Next** (Calendar page, and Upcoming events on the Members hub). A scan of all 96 published pages, posts and sermons found no other Previous or Next controls.

### Who Are We (190): "vision" no longer a broken link (board member's notes)
- In **Our Purpose, Mission, and Vision**, the third paragraph's "Part of our **vision** is…" linked to `/our-vision`, which has never existed (found in the 2026-09-30 link scan). The link is removed, and "vision" stays bold.
- Backup: `backups/pages/page-190-who-are-we-before-vision-link.html`. The matching church question in the checklist is ticked.
- **Then:**
  - "vision" is **bold italic**, to match "purpose" and "mission" in the paragraphs above.
  - **Unison Affirmation** added inside the Covenant of Right Relations section, between the heading and the existing Covenant paragraph:
    - an h3 heading, written the same way as the Covenant h2 (a heading block with `<strong>`), one level down because it sits inside that section;
    - the eight-line affirmation, centred and italic, one line per line.
  - Backup: `backups/pages/page-190-who-are-we-before-affirmation.html`.
- **Church Leadership (192):** waiting for the corrected officer list. The "View the Constitution and Bylaws" link at the bottom already works (`Emerson_Bylaws_July_2019_ApprovedAmendedVersion.pdf`) and stays as it is.

### Seven Principles page (199) published (board member's notes)
- **What was wrong:** "Click here to read about our Seven Principles" on **What is Unitarian Universalism?** goes to `/seven-principles`. The real page (199) had been a draft since 2023, so that address showed the **attachment page** WordPress makes for every upload. The Seven Principles image (957, uploaded 2026-09 for Who Are We) had taken the slug `seven-principles`, so it showed a 300 px copy, left-aligned under the headline.
- **Changes:**
  - The attachment's slug is now `seven-principles-image`. Nothing links to it.
  - Page 199 is published at `/seven-principles/` and contains:
    - the image at full resolution (889 × 893 file, shown at the 650 px content width), centred under the headline;
    - a centred Emerson blue **Learn more** button (`newcomer-button`) that opens https://www.uua.org/beliefs/what-we-believe/principles in a new tab.
  - The draft's old text (an intro sentence and the 1985 wording of the principles, which differs from the image's newer wording) was left out.
  - Backups of both, including the old text: `backups/pages/page-199-seven-principles-draft-before.json` and `backups/pages/attachment-957-slug-before.txt`.
- The page title is still "Seven Principles of Unitarian Universalism"; change it in the editor if the board prefers just "Seven Principles".
- **Then added** (board member's second note), between the Seven Principles image and the button:
  - a centred sentence: "The Unitarian Universalist Association officially replaced its traditional Seven Principles and six sources in 2024 with a new framework of interconnected shared values centered around love.";
  - the **Love at the Center** image (attachment 959, `love_at_the_center.jpg`), centred at full resolution with the same description for screen readers as on Who Are We.
  - **Learn more** stays at the bottom.
  - Backup: `backups/pages/page-199-seven-principles-before-love.html`.

### Serving dates: members now tick the dates they CAN serve
- **Why:** "tick the dates you can't serve" read backwards to the intern. The church also wants a date nobody has answered to count as **can't serve**, rather than Church Admin's default of "available".
- **How it works** (new `inc/serving-dates.php`; the earlier summary code moved there from `inc/members.php`):
  - **What members see:** "Dates I can serve" (page heading renamed; backup `backups/members/members-32-before-can-serve.html`), "Tick the dates you can serve", **Mark all** / **Clear all**, and a summary such as "You can serve 24 of the 26 dates listed. Not available: October 4, 2026, October 11, 2026." Boxes are ticked **only** for dates the member has saved as "can serve". With nothing ticked: "Until you do, you won't be put on the serving schedule."
  - **Admins** still use **Choose person** and see the same wording with that person's name.
  - **New table `wp_emerson_serving_answers`** (people_id, date, can_serve 1 or 0). It records every Save.
  - **Church Admin's table is kept in step** (`church_admin_not_available`, which its auto-fill, assignment warnings, clash emails and app read): a "can't serve" row for every person and every service date over the next 6 months (auto-fill's furthest reach) **unless** they've ticked "can serve". A daily job (`emerson_serving_daily_sync`) adds new weeks as they come into range.
- **Existing data:** test Mctest's two saved dates (November 1 and December 6) were kept as real "can't serve" answers. As chosen, everyone starts fresh: nothing is ticked until they save. Backup of the table before the change: `backups/serving/church_admin_not_available-before-can-serve.sql`.
- **Numbers on 2026-10-02:** 70 people × 52 service dates (Sundays and Mondays to March 29) = 3,640 "can't serve" rows before anyone answered.
- **Limits:**
  - Church Admin's own wp-admin screen (**Schedules → Not available**) and its app still edit the plugin table directly; the daily sync overrides those edits. Use the Members page.
  - Only weekly services are covered beyond the 26 dates on the form. Both current services are weekly.
  - **To undo** (see TROUBLESHOOTING): run `emerson_serving_undo()` before removing the file.
- **Tested** as Fox Metz: Mark all, untick October 4 and 11, Save. Results: 24 "can serve" and 2 "can't serve" answers; Church Admin blocks October 4 and 11 plus the unanswered January–March dates; auto-fill sees Fox Metz available on October 18. As admin, Choose person → test Mctest shows nothing ticked.

### Questions for the church gathered in one place
- `TEST-AND-LAUNCH-CHECKLIST.md` has a new **section 4, Questions for the church**. The Google Maps, spreadsheets, broken-link, YouTube, directory, missing images, Twitter/X, mint green and Privacy Policy questions moved there; section 2 now has one item checking they're answered.
- **New question:** the Monday dates in the serving list. Church Admin has two "Sunday Service" entries at 10:00 AM; service #1 is set to Monday, and service #2 has no day set. Also, nobody is scheduled on any future date.

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
  - Who Are We Covenant "here" (linked 2026-10-05 to the PDF)
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
