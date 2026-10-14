# Test and launch checklist

Tick items off as you go. Deployment steps in detail: `[DEPLOY-WPVIVID.md](DEPLOY-WPVIVID.md)`. How to switch email modes: `[TROUBLESHOOTING.md](TROUBLESHOOTING.md#testing-real-email-locally-redirect-mode)`.

## 1. Local testing (email in redirect mode)

In redirect mode every email really goes out, but only to your test mailbox. The subject shows who it would have gone to, for example `[LOCAL TEST → office@emersonuuchapel.org] Newcomer information`.

Use a private window (Cmd+Shift+P) for anything that should be tested logged out.

- [x] **Newcomer Information form** (`/contact-newcomer-information/`)
  - "Thanks for contacting us!" appears within about a second
  - "Newcomer information" email arrives within a minute or so (original recipient: `office@`). It's sent as a background job.
  - Replying to it goes to the address typed in the form
  - The phone field accepts `(636) 555-0123`, `636-555-0123` and `6365550123`
  - Answering **Yes** to the newsletter question also sends the "Please confirm your subscription" email. After the click, "New newsletter subscriber" says "Signed up from: Newcomer Information form". Answering **No** sends only the office email. The name and email used for that sign-up are Name 1 and Email from the top of the form.
  - **What are you looking for** (added 2026-10-08): Inspirational Messages, Creativity, Ritual/practice, Other. Other opens a text box. Re-check that those answers appear in the office email.
- [x] **Newsletter pop-up** (homepage, logged out)
  - Opens after 5 seconds, fades in; ×, "No thanks", Esc and clicking outside all fade it out
  - Sign-up shows "Almost done!" within about a second (not about 10), then fades out after about 4 seconds
  - The confirmation email arrives a few seconds later. It's sent after the visitor has their answer.
  - "Please confirm your subscription" email arrives
  - The link shows the green "You're subscribed" notice
  - "New newsletter subscriber" email arrives (original recipient: `office@`)
  - The person appears in Church Admin as **Mailing List** and is hidden from the directory
  - Clicking the link a second time says it has expired or was already used
- [x] **Ways to Connect form** (`/ways-to-connect/`): same results as the pop-up
- [ ] **Sunday Location** (homepage + **Sunday Location** in the WordPress admin). How to update it is in the section after this one.
  - Homepage says **This week, meet us at 10 am, at the St. Charles YMCA located at …**, then moves on after that service
  - Marking a Sunday **Cancelled** or **Moved** updates the homepage and the Service Schedule
  - Anyone assigned that day, or who ticked they can serve, gets an email (redirected locally), and the office gets a copy
- [ ] **Sunday Schedule spreadsheet** (`/calendar/`, the homepage, and `/schedule/`). How to change the URL is in **Sunday Schedule spreadsheet — how to update the URL**. The homepage shows the same month grid under Purpose, Mission, and Vision.
  - Under the month name (where **This month’s PDF** used to be), October shows **This month’s theme: Courage**; November shows **Healing** after Next. **This calendar PDF** at the top of the page still downloads
  - Sunday boxes show holiday, title, and speaker from the sheet (for example October 4: The Main Thing / Rex Loy). Serving-role names stay off the public calendar
  - October also shows **Womyn's Web** on the 5th, **Men's Night** on the 2nd, and **Board Meeting** on the 19th (Engage meeting times). Clicking those days shows time, location, Engage, and **iCal download**, same as Sunday Service. Logged-in serving ticks, including Mondays, show **You can serve**
  - Logged in as a designated person (or an Administrator/Editor), clicking a Sunday on `/calendar/` or `/schedule/` shows liturgist, greeter, and the rest of that day’s sheet row. Logged out, that extra block is absent
  - **Sunday Schedule** in the WordPress admin can Refresh from the spreadsheet and change who can open the full row
- [x] **Church Admin household registration** (Members pages, logged out)
  - The registrant gets a confirm-your-email message
  - After confirming, the "new household" email with the approval steps arrives (original recipient: `com@`)
  - Approving them in Church Admin sends the "user login created" email
- [x] **Members page: login, hub and password reset** (redirects re-checked 2026-09-30: all five old addresses give 301 to the right page)
  - Logged out, `/members/` shows the login form, "Lost your password?" and the registration form, with no member content
  - A wrong password gives an on-page error
  - A correct login shows the hub on the same page:
    - Sunday services
    - my serving schedule and dates I can serve
    - upcoming events
    - the directory
  - The admin toolbar doesn't appear
  - "Edit my profile or password" works, and "Log out" goes to the homepage
  - "Lost your password?": the reset email arrives and its link works
  - Old addresses (`/member-login/`, `/member-home/`, `/login/`) go to `/members/`
- [x] **Test with a real member login:** promote one of the test subscribers (alice fox, test Mctest or Test Testing) to **Member** in Church Admin, give them a login, and tick "show me". Church Admin route: **Church Admin Premium → People card → Search → Go**. Click the first name, then set **Member type: Member** and tick **"To show me on the password protected address list"**, then **Save Details**. Back in the search results, click **Create user account**. They should appear in the directory, and "My serving schedule" should work instead of saying the login isn't connected.
- [x] **Four wrong passwords in a row** lock you out for 20 minutes. Afterwards, clear the lockout on the **Limit Login Attempts** screen in wp-admin.
- [x] **"Dates I can serve"** (tested 2026-10-02 as Fox Metz, and as an admin using Choose person): boxes start unticked; Mark all and Clear all work; Save returns to the section with "Your serving dates are saved" and the summary ("You can serve 2 of the 26 dates listed. Available: …").
- [ ] **Clean up afterwards:** delete test households, people and user accounts. Church Admin should be back to 65 people.
  - Then remove their serving answers too: `docker compose run --rm -T wpcli eval 'global $wpdb; $wpdb->query("DELETE a FROM {$wpdb->prefix}emerson_serving_answers a LEFT JOIN {$wpdb->prefix}church_admin_people p ON p.people_id = a.people_id WHERE p.people_id IS NULL"); $wpdb->query("DELETE n FROM {$wpdb->prefix}church_admin_not_available n LEFT JOIN {$wpdb->prefix}church_admin_people p ON p.people_id = n.people_id WHERE p.people_id IS NULL");'`
  - On 2026-10-02 there are 70. The five extra people are #79 alice fox, #80 test Mctest, #82 Test Testing, #83 (auto-created for the intern admin login) and #84 Fox Metz.
  - #84 Fox Metz (household #46) was added by hand so the intern's Contributor login (user #47) could test serving dates. It's hidden from the directory, with all email and text settings off.
  - Delete the person and household, but **keep user #47**. That login was made in WordPress, not through sign-up.
- [ ] **Click once to confirm these outside links** (from the 2026-09-30 broken-link scan; these sites block automated checks, so they're probably fine): Holocaust Museum (`hmlc.org`), O.A.S.I.S. Food Pantry, KIND (`supportkind.org`), the Poetry Foundation's Ralph Waldo Emerson page (FAQ), and the Intuit Mailchimp privacy statement (Privacy Policy).
- [ ] **Questions for the church:** see section 4.
- [x] **Last before the WPvivid backup: tidy the Mac's Docker PATH lines.** Done 2026-10-04. Housekeeping only; nothing was broken.
  - Docker's command folder (`~/.docker/bin`) is added to the PATH **three times**: twice in `~/.zprofile` (lines 2 and 8) and once in `~/.zshrc` (line 2). Since the 2026-10-01 Docker Desktop settings fix, new terminals find `docker` on their own, so only one is needed.
  - **Steps:**
    1. Quit Terminal, and close Cursor's terminals.
    2. Open `~/.zprofile` in Cursor (Cmd+Shift+G in Finder, type `~/`, then show hidden files with Cmd+Shift+.).
      - Keep the block with the comment `# Docker Desktop CLI + credential helpers` and its line `export PATH="$HOME/.docker/bin:$PATH"`.
      - Delete line 2, `export PATH="$PATH:/Users/kitsunearisu/.docker/bin"`.
    3. Open `~/.zshrc` and delete its line `export PATH="$HOME/.docker/bin:$PATH"`. Keep the `fpath=(…/.docker/completions …)` line, which gives Docker tab-completion.
    4. Open a new Terminal and run `command -v docker`. It should print `/Users/kitsunearisu/.docker/bin/docker`. Then run `docker compose ps` in the project folder, and it should list `db` and `wordpress`.
  - **If** `docker` **isn't found afterwards,** put the deleted `.zprofile` line back.
  - **Not needed:** `brew uninstall docker`. Docker Desktop wasn't installed with Homebrew, which is why Homebrew said the cask "is not installed". Leave it alone.

## Sunday Location — how to update

The homepage heading **Sunday Service Location** can still be edited on the page itself. The sentence under it is filled in automatically. Do **not** type the time or address back into that page; it will be overwritten the next Sunday.

**Who can do this:** anyone who can edit pages (Administrator or Editor).

**Where:** WordPress admin (the left-hand menu) → **Sunday Location**. It is a calendar icon, below the usual Pages / Posts items.

The screen lists the next 12 Sundays. Each row has **Status**, **Time**, **At the** (the building name), **Located at** (the street address), **Note**, and **Signed up** (who would be emailed).

Leave a Sunday as **Regular** unless something is different that week. Regular Sundays use **10 am** at the **St. Charles YMCA**, **3900 Shady Springs Ln, St Peters, MO 63376**. After that Sunday's service, the homepage moves to the next one on its own.

### The usual place this week (nothing special)

Do nothing. The homepage already says:

> This week, meet us at 10 am, at the St. Charles YMCA located at 3900 Shady Springs Ln, St Peters, MO 63376

### The service moved (different time and/or building)

1. Open **Sunday Location**.
2. Find that Sunday.
3. Set **Status** to **Moved**.
4. Change **Time**, **At the**, and **Located at** as needed (for example 2:00 pm, Fifth Street chapel, 122 North Fifth Street, St Charles, MO 63301).
5. Optional: add a **Note** (“One-week location change”).
6. Click **Save Sundays**.

The homepage then uses the same sentence with those new blanks. The Service Schedule and the “dates I can serve” ticks show **(moved)**. Anyone assigned that day, or who ticked they can serve, is emailed, and the office gets a copy.

### The service is cancelled

1. Open **Sunday Location**.
2. Find that Sunday.
3. Set **Status** to **Cancelled**.
4. Optional: add a **Note** (“No service — building closed”).
5. Click **Save Sundays**.

The homepage says that Sunday is cancelled, then uses the “This week, meet us…” sentence for the *next* Sunday that is not cancelled. The Service Schedule and serving-date ticks show **(service cancelled)**. Anyone signed up to serve that day is emailed that they do not need to come, and the office gets a copy.

Check the **Signed up** column before you save, so you know who will get mail.

### Put it back to a normal Sunday

Set **Status** back to **Regular** and click **Save Sundays**. The homepage and schedule return to 10 am at the YMCA. That does **not** send another email.

Saving **Cancelled** or **Moved** a second time, with the same time and place, does not email people again. Changing the time or place on a Moved Sunday does.

### If the church’s usual meeting place changes for good

That is not a one-Sunday Moved. Ask the intern: the default YMCA line lives in `inc/sunday-location.php`, and Visit Us (page 58) still has to match (section 4).

## Sunday Schedule spreadsheet — how to update the URL

The public **Calendar** and **Service Schedule** read the monthly theme, holiday, title, and speaker from the church’s Sunday Morning Schedule Google Sheet. The church keeps editing that one spreadsheet. Same-file edits show on the site after about 30 minutes, or right away after **Refresh from spreadsheet**.

**Who can do this:** anyone who can edit pages (Administrator or Editor).

**Where:** WordPress admin (the left-hand menu) → **Sunday Schedule**. It is a spreadsheet icon, just under **Sunday Location**.

### They edited the same spreadsheet (same file, same tab)

Do nothing, or click **Refresh from spreadsheet** if you want the site to catch up now. You do **not** change the URL.

### They started a new spreadsheet, or a different tab

A new file (or a different tab) has a different address. The old URL will keep showing the old file.

1. Open the new spreadsheet in the browser. Stay on the Sunday Morning Schedule tab.
2. Copy the address from the address bar. It looks like `https://docs.google.com/spreadsheets/d/…/edit?gid=…`. The `gid=` number is the tab.
3. In WordPress, open **Sunday Schedule**.
4. Paste that address into **Spreadsheet URL**.
5. Click **Save URL**.
6. Confirm **Last fetch** shows a time, a row count, and no error. October should list **Courage** if that is still the October theme.

The sheet must be shared so **anyone with the link can view**. Editor access is not required for the site to read it. **Planning Calendar** on `/calendar/` is only shown to Administrators, Editors, and the designated Sunday Schedule list. Visitors do not get the spreadsheet URL.

Serving-role columns (liturgist, greeter, and so on) stay off the public calendar on purpose. The month PDF is **This calendar PDF** at the top of the Calendar page, not under the month name.

### Who can click a day for the full sheet row

Visitors see only theme, holiday, title, and speaker. A designated person who is logged in can click a Sunday on **Calendar** or **Service Schedule** and see the rest of that day’s row (liturgist, tech, greeter, hospitality, board person of the day, RE volunteers, RE class topic).

**Already included:** anyone who can edit pages (Administrator or Editor), plus the church list: PJ Pyrchla, Nathan Williams, Jenny Kerr, Sharon Swanson, Karyl Howard, Lauren Williams, Lana Michels, Marie Sebert, and Harlan Bloom — when that person has a WordPress login linked in Church Admin.

**Where to change the list:** **Sunday Schedule** → **Who can click a day for the full sheet row**. Tick or untick names, then **Save who can see the roster**.

If someone on the church list has **no login**, they cannot see the roster until Church Admin has a user account for them and they are ticked. The Sunday Schedule screen lists those names.

Do not put personal emails or phone numbers in the theme files or GitHub. The list is stored in the WordPress database.

## 2. Before making the WPvivid backup

- [ ] Email back to save only: `docker compose run --rm -T wpcli option delete emerson_local_mail_redirect_to`
- [ ] Test data removed (see "Clean up afterwards" in section 1)
- [ ] No scheduled jobs in the past: `docker compose run --rm -T wpcli cron event list`. The overdue ones were rescheduled on 2026-09-29.
- [ ] Every question in section 4 (questions for the church) is answered, and its change made or deliberately left for later
- [ ] Optional: hide the Website and Biographical Info fields on Edit Profile (**Profile Builder → Form Fields**)
- [ ] Someone monitors `office@emersonuuchapel.org` (newsletter and Newcomer form) and `com@emersonuuchapel.org` (Church Admin and the site admin)
- [ ] Optional: delete unused forms. That's 6 Calculated Fields demo forms that email `com@` and copy the visitor, the "Emerson 2023-24 Pledge Drive" WPForm and the "Simple Contact Form" WPForm.
- [x] **Social media icons in the footer** (added 2026-10-03, under the logo and the "A liberal, welcoming…" line). Official accounts from the church's Google listing, plus the congregation GroupMe (added 2026-10-08):
  - Facebook `https://www.facebook.com/emersonuuchapel`
  - Instagram `https://www.instagram.com/emersonuucommunity` (confirmed 2026-10-08; the Google listing had `emersonuuchapel`)
  - YouTube `https://www.youtube.com/@emersonunitarianuniversali1222`
  - GroupMe `https://groupme.com/join_group/32110283/HqzkKf` (general talk; also listed on Ways to Connect)
  Twitter/X was removed on 2026-10-03; they no longer use it.
- [ ] Anything changed on the live site since 2026-09-24 has been copied into the local site (the restore replaces it). Follow `DEPLOY-WPVIVID.md` step A1: compare Harlan's fresh backup with the local site, then copy the changes in. The note below is the serving-dates version of that compare.

### How to compare Harlan's backup with this site

Compare **Harlan's fresh live backup** to **this local site**, not to the WPvivid backup you will export later. The export is the *result* of this compare-and-copy. The restore then replaces the live database with yours, so anything the church did on live after **2026-09-24** is lost unless you copy it in first.

Restore Harlan's zip into a **second** Docker project with its own database. Never over this one.

**What to look at**

| Area | Tables / screens | What "changed on live" looks like |
|---|---|---|
| People | `people`, `household`, `people_meta` | New people, member-type, "show me", contact edits after 24 Sept (`last_updated` / `first_registered`) |
| Logins | `wp_users` | New accounts (`user_registered`); password changes (`user_pass` differs). Recreate locally; they get a reset after launch |
| Pages / sermons / media | posts | `post_modified` after 24 Sept |
| Calendar | `_calendar_date` | New or edited events |
| Rota (who is assigned) | `_new_rota` | Same meaning on both sides; compare normally |
| Plugin settings | as the office mentions | Spot-check |

Local counts from 2 Oct (after test people are gone): **65 people**, **38 households**, **2,951** schedule rows.

**Serving dates are the exception.** The two databases do **not** mean the same thing in `_not_available`.

- **Live (Harlan):** Church Admin's old form. A row = someone marked that date as **unavailable**. Unanswered dates count as available. There is **no** `emerson_serving_answers` table.
- **Local:** members tick dates they **can** serve. Those answers live in `wp_emerson_serving_answers`. `_not_available` is then filled with a "can't serve" row for every *other* date six months ahead, so Church Admin's auto-fill still works. That table will look huge. **Do not compare row counts.**

When you compare serving data:

1. Ignore local `_not_available` bulk rows.
2. On the Harlan copy, list only **their** unavailable dates (real ticks since 24 Sept, or any you care about).
3. Enter those on **this** site with **Choose person** as "can't serve". Everyone else stays unanswered until they save.

The new code, `emerson_serving_answers`, and `emerson_serving_db_version` go out with **your** WPvivid backup. Live picks them up on restore. After restore, check ticks and that `emerson_serving_daily_sync` is scheduled (section 3).

Only if the **whole directory** changed a lot do you use the bulk import (`DEPLOY-WPVIVID.md` step A1.5): dump Church Admin tables from Harlan's copy, import after restore, then delete `emerson_serving_db_version` and empty `emerson_serving_answers` so their old "can't serve" dates convert on the next page load. That's the exception.

So: Harlan's backup is a **diff of church activity since 24 Sept**. Your serving rewrite is already in this project and will overwrite live's old form. You only need to carry across any **real** unavailable dates (and rota / people / content edits) from that fresh copy.

## 3. After restoring to the live site

- [ ] W3 Total Cache: turn **page caching** back on, then **Performance → Purge All Caches**
- [ ] **WP Mail SMTP → Tools → Email Test** to your own address arrives
- [ ] No leftover `localhost:8080` links (spot-check the homepage, the menu and the footer)
- [ ] Logged out, the homepage pop-up still opens with page caching on; logged in, it never shows
- [ ] One real newsletter sign-up and one Newcomer form submission reach `office@`
  - Both should answer within about a second.
  - If the Newcomer email never arrives, see `DEPLOY-WPVIVID.md` step C5 (WP-Cron / loopback on the host).
- [ ] Optional: ask the church about a sending service (Brevo, SendLayer) instead of the NetSol mailbox, for faster and more reliable email everywhere
- [ ] Mailchimp: the office adds new subscribers by hand until someone provides an API key and audience ID
- [ ] Remove your own test sign-up from Church Admin and Mailchimp afterwards
- [ ] **Serving dates still work after the restore.** Logged in as a member, "Dates I can serve" shows their ticks. In `wp eval`, `wp_next_scheduled('emerson_serving_daily_sync')` returns a time (the daily job that keeps unanswered dates blocked for auto-fill). The `wp_emerson_serving_answers` table travels with the WPvivid database backup.
- [ ] **Calendar PDFs** (in Firefox or Safari; Cursor's browser doesn't show PDFs): **Yearly Planner PDFs** for 2026, 2027, or 2028 opens a 12-page calendar for that year. Logged out, October 2026 shows title and speaker (The Main Thing / Rex Loy), not only Sunday Service, plus Womyn's Web / Men's Night / Board Meeting. Logged in as someone serving that day, their role appears. Weekday ticks print **You can serve**. Logged in as an elevated person, the full sheet row appears. Months the sheet has not filled in yet still say Sunday Service. **This calendar PDF** is the current month.
- [ ] **Calendar → "This calendar PDF"** downloads a PDF.

## 4. Questions for the church

Ask these before the WPvivid backup where possible; section 2 checks they're answered. The intern is contacting the church.

- [ ] **Womyn’s Web, Men’s Night, and Board Meeting: still regular?** The Calendar now lists them from the Engage page times (first Monday 7 pm, first Friday 6–8 pm, third Monday 6:30 pm) and they are real Church Admin events, so a click offers an iCal download like Sunday Service.
  - **Ask:** are those still the meeting days? Men’s Night on Engage says first *or* second Friday — which Friday should the calendar use?
  - **Ask:** where does each group meet, and does the host rotate? Womyn’s Web and Men’s Night currently say **Rotating location — check This Week at Emerson**. Board Meeting says the chapel. To name this month’s host, edit that date in **Church Admin → Calendar** (location field). If they have a standing address or a better way to publish the rotation, update Engage and those calendar dates.
  - Circle Suppers stay off the grid on purpose (each host picks the date).
- [ ] **`office@emersonuuchapel.org`: who reads it, and should it forward?** The site sends newsletter sign-ups, the Newcomer form, and cancelled/moved Sunday copies there. The footer and Visit Us also list it. It is **not** a WordPress login (no user uses that address). Mail is sent *to* it through NetSol; WordPress cannot create the mailbox or set forwarding.
  - **Ask:** who opens that inbox today? If nobody does, either someone starts, or Network Solutions sets a **forward** from `office@` to the person who should get those messages (Harlan can do that in the NetSol email panel). The site does not need a code change for a host-level forward.
  - **Do not confuse with** `com@emersonuuchapel.org` (WordPress admin user #1, Church Admin “com User”, new-household notices, lockout alerts) or `webmaster@emersonuuchapel.org` (WP Mail SMTP From address — outgoing only).
- [ ] **Launch date and freeze.** Agree a launch date. For a day or two before it, nobody edits Church Admin, posts or pages on the live site; urgent changes are emailed to the intern instead. The restore replaces the live database, so anything changed during the freeze would be lost.
- [ ] **A fresh WPvivid backup from Harlan** (Database + Files) at the start of the freeze. It's used to find what changed on the live site since 2026-09-24 (`DEPLOY-WPVIVID.md` step A1), and it's a rollback point.

- [ ] **Serving schedule: Monday dates.** "Dates I can't serve" lists every Monday as well as every Sunday (October 5, 12…).
  - Church Admin has **two services, both called "Sunday Service" at 10:00 AM**. Service #1 is set to **Monday**, and service #2 has no day set.
  - **Ask:** is there a Monday service or gathering that needs volunteers? If not, the fix is to correct service #1 to Sunday and remove the duplicate (**Church Admin → Schedules → Services**). Back up the database first, and check which service the past schedules use before removing one.
  - Also: no one is scheduled on any future date yet. Who builds the serving schedule now, and do they use Church Admin for it?
  - The public **Service Schedule** page (437, `/schedule/`) is now its own month calendar of Sunday services from Church Admin's calendar, not the empty rota. Monday ticks on "Dates I can serve" do not appear there, because this calendar only lists Sundays.
- [ ] **Visit Us: which address?** The page's text says **122 North Fifth Street, St Charles, MO 63301**, but the footer, homepage and Sunday Services say services are at the **St. Charles YMCA, 3900 Shady Springs Ln, St Peters, MO 63376**. The new map (2026-10-02) shows the YMCA. Is Fifth Street still used for anything, such as an office or mail? Then update the text, or the map, to match. There's also an unpublished draft, "Come Visit Us" (page 220): keep or delete?
  - The **Service Schedule** page says **Emerson Chapel** and links that name to Visit Us. Update Visit Us when the address is confirmed; the schedule page follows it. Change the words "Emerson Chapel" only if the church wants a different label (`EMERSON_SERVICE_PLACE` in `inc/service-schedule.php`).
- [ ] **Visit Us: which map service?** The page uses an **OpenStreetMap** embed (added 2026-10-02): free, no account or API key, and no tracking cookies. Would the church prefer **Google Maps** instead?
  - **A plain Google map embed** needs no key either, but Google may set cookies, so the Privacy Policy would need a line about it.
  - **With their own Google Maps API key** (see "Google Maps" below), the same key would also turn on Church Admin's directory map and registration address lookup.
  - **To switch:** replace the `<iframe class="emerson-map">` on Visit Us (page 58) with Google's **Share → Embed a map** code. Keep `class="emerson-map"` so the styling stays.
- [ ] **Religious Education: photo of Lauren.** The board would like to add a photo of the RE teacher, if she agrees. Get her written OK and the photo, then add it near the teacher sentence at the top of the page.
- [ ] **Google Maps.** Do they want maps, and who would own the Google account?
  - Church Admin can show a map on the registration form and in the member directory, but only with a Google Maps API key.
  - That needs a Google Cloud account with billing turned on; normal church use should stay within the free allowance.
  - If yes: restrict the key to the church's domain and paste it into **Church Admin → Settings**. Then test the registration form's address map, and turn the directory map on (`map="1"` in the address-list shortcode on the Members page).
  - Until then, Maps isn't loaded at all (`inc/members.php`), so there's no console warning and no request to Google.
- [x] **Spreadsheets for certain members** (local test 2026-10-05). Files live in `private-files/` (not Media). The Members hub lists them only for **administrators** and the **Finance** role. Still confirm with the church who should have Finance, whether they need to *edit* as well as view, and what is in the files.
  - **Ask:** who needs to see them? View only, or editing too? What information is in them (giving by person, pledge totals, budget, attendance)? How often is it updated?
  - Church Admin **Giving** stays separate (`/giving/`, Church Admin permissions).
  - Apple Numbers is not Excel. Export to `.xlsx` or PDF from Numbers if people need to open the file without Numbers.
- [ ] **Spreadsheets on the live site.** The WPvivid restore carries the **code** and the Members shortcode. It does **not** carry the Excel files, and Docker's `private-files/` mount does not exist on the host. **Ask Harlan** to make a folder **next to** `public_html`, not inside it (for example `/home/ACCOUNT/emerson-private`), that PHP can read. Then copy the `.xlsx` files there (FTP or File Manager). Do **not** use Media or `uploads/` — those are public if someone has the URL.
  - **Who sees them (unchanged):** WordPress **administrators**, and anyone given the **Finance** role (**Users →** that person → **Role: Finance**). Finance currently has nobody assigned. Set it after the church confirms who, either locally before the backup (it then travels in the database) or on live after the restore.
  - The live PHP looks first for `/var/emerson-private` (the local Docker path; it will not be there), then `wp-content/emerson-private`. That `wp-content` fallback is only safe with an `.htaccess` that denies web access. Prefer Harlan's folder outside the web root, plus one line in `wp-config.php` pointing at it (WPvivid does not overwrite `wp-config.php`). Add that constant to the theme before launch if Harlan's path is known.
  - Skip putting personal test files (for example Magazines) on live unless the church wants them there.
  - **After restore:** log in as an admin, open Members, open a sheet. Log in as a normal member and confirm Spreadsheets is missing. Check **Tools → Site Health** that PHP's zip extension is on, so Excel preview still works. No extra WordPress page per file: drop files in that server folder and they appear on Members for Finance and admins.
- [ ] **Broken links from the 2026-09-30 scan**
  - [x] **Who Are We, "vision"** (in "Part of our vision is…") linked to `/our-vision`, which doesn't exist. **Answered 2026-10-02 (board member):** link removed; "vision" stays bold.
  - [x] **Who Are We (190), Covenant of Right Relations, the word "here".** Linked 2026-10-05 to Media `Covenant-of-Right-Relations.pdf`. The old Squarespace `/s/Covenant-of-Right-Relations.pdf` path is gone.
  - **Engage, Outreach Partners: three organisations have closed or gone offline.** Caminamos Juntos of San Miguel de Allende (`cjsma.org` no longer exists), Saint Louis Story Stitchers (site reports it's permanently gone) and the UU Trauma Response Ministry (`traumaministry.org` doesn't respond). Keep them as a historical list without links, remove them, or replace the links?
- [x] **YouTube.** Confirmed 2026-10-03 from the church's Google listing: the official channel is **@emersonunitarianuniversali1222** ("Emerson Unitarian Universalist Chapel"). Sunday Services, the footer and Ways to Connect now link there.
  - Of the 36 gallery videos, 34 are on that channel (2022–2024). The two newest (May and July 2026) are on a second channel, **@EmersonUUChapel**, with the same dated titles. Both are this church. The individual video embeds are unchanged.
  - Still ask whoever uploads sermons to set each gallery video's date to the Sunday it was recorded, so Past Sermons stays in order.
- [ ] **YouTube sermons: pull new videos onto the site automatically?** Sunday Services and Past Sermons only list videos that someone has already added in **Video Gallery** (paste the YouTube watch URL). A new upload on YouTube does **not** appear by itself.
  - **Ask:** do they want us to look into auto-import (a gallery add-on, or a small job that checks the official channel), or keep pasting each sermon by hand?
  - Auto-import needs **one official channel**. Right now sermons live on two. It also needs a rule for the gallery date (the Sunday in the title, not the weekday it was uploaded), or Past Sermons will sort wrong.
  - Until then: **Video Gallery → Add New** → paste the watch URL → set Publish date to that Sunday. The two pages then pick it up.
- [ ] **The member directory.** Only households that opted in ("show me") are listed, currently 2 of the 32 Members. Decide whether to ask members to opt in, for example with a note in the newsletter or Church Admin's "update your details" email.
- [ ] **Missing images (from the sitemaster)**
  - 3 on the Welcome! page
  - Serve Emerson Chapel and Engage banners: `service.png`, `women-together.jpeg`, `men-together.jpeg`, `circle-suppers.jpeg` and `board-meetings.jpg` (uploaded 2021/11 and 2022/07). Engage also needs `image.jpeg` (2022/07), the "Serve Our Wider Community" photo. Until then those banners are solid blue. See `CHANGELOG.md` for how to put the photos back.
- [x] **Twitter/X.** They no longer use it (2026-10-03). Removed from the footer, Ways to Connect, and two leftover empty links on Religious Education and Our Congregation.
- [ ] **Church Leadership: personal emails and extra contact details.** The page lists names and roles only. Two board members' personal emails were sent for a possible contact list; they are **not** on the page, and must not go into GitHub. Ask leadership whether they want personal emails (or other private contact details) on this public page. If yes: add them on the page only, not in the repo. If no: leave the names as they are.
- [ ] **2026 pledge form.** The homepage button still says **2024-25 Pledge Form** and goes to `https://forms.gle/PeAst4DMzSA9FKjG6`. Ask for this year's Google Form (or confirmation the old one is still current), then update the button label and that link. The old Welcome! page (412) still has an even older "Join The 2022-2023 Pledge Drive" link (`forms.gle/GfZt7bZsiYhPiFWX9`); update or remove that too if the page stays.
- [ ] **Mint green (`#00d084`).** It has low contrast as text on white. Keep it, or choose a darker green for text? The homepage **You Are Welcome!** heading is no longer mint (rainbow gradient, 2026-10-08). Mint is still the pledge / contribute button background.
- [ ] **Privacy Policy.** Leadership approves the wording, including the newsletter, local-storage and login-protection sections. Church Admin needs PHP's `calendar` extension. Locally it's added by the project `Dockerfile`; most hosts have it. If the live site gives a server error there, ask the host to enable it.

## 5. Later: a more modern look (not a launch item)

The President liked some newer themes the intern showed. **Launch on this free theme first** (`emerson-uuchapel`, child of Twenty Twenty-Three). Do not change **Appearance → Themes** to get a modern look. That is not a one-click swap.

- **What does travel** if you activate another theme: pages and posts (Gutenberg blocks in the database), media, and plugins (Church Admin, forms, and so on).
- **What stops working** unless you move it: almost everything we built. The Emerson-blue pills, calendar, footer icons and spacing live in `assets/css/custom.css`. The header and footer live in the theme patterns. Newsletter, members hub, serving dates, calendar PDFs and the Sunday schedule live in `inc/*.php` and are loaded from `functions.php`. A new theme does not load those files.
- **Unused themes do nothing.** Leftover Magazine Pro CSS is still in the database; that theme is not active, so it has no effect. A store theme's demo will not restyle Church Admin, the members hub, or classes such as `bylaws-button` / `newcomer-button`.
- **React kits** (for example Planes, shadcn) are for Next.js apps, not this PHP theme. Treat them as a mood board; rebuild a look in CSS if we want it.
- **Two sane later options,** once the site works:
  1. Stay on this child theme and modernise the CSS and templates. Same PHP, less risk.
  2. Pick a new block theme, then port `custom.css` and the header/footer patterns, and move `inc/*.php` into a small plugin so those features survive a theme change.

- [ ] **After launch only:** if leadership still wants a more modern look, choose option 1 or 2 above. Do not flip themes on the live site to try it.