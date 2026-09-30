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
  - Answering **Yes** to the newsletter question also sends the "Please confirm your subscription" email. After the click, "New newsletter subscriber" says "Signed up from: Newcomer Information form". Answering **No** sends only the office email.
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
- [x] **Church Admin household registration** (Members pages, logged out)
  - The registrant gets a confirm-your-email message
  - After confirming, the "new household" email with the approval steps arrives (original recipient: `com@`)
  - Approving them in Church Admin sends the "user login created" email
- [ ] **Members page: login, hub and password reset**
  - Logged out, `/members/` shows the login form, "Lost your password?" and the registration form, with no member content
  - A wrong password gives an on-page error
  - A correct login shows the hub on the same page:
    - Sunday services
    - my serving schedule and dates I can't serve
    - upcoming events
    - the directory
  - The admin toolbar doesn't appear
  - "Edit my profile or password" works, and "Log out" goes to the homepage
  - "Lost your password?": the reset email arrives and its link works
  - Old addresses (`/member-login/`, `/member-home/`, `/login/`) go to `/members/`
- [ ] **Test with a real member login:** promote one of the test subscribers (alice fox or test Mctest) to **Member** in Church Admin, give them a login, and tick "show me". They should appear in the directory, and "My serving schedule" should work instead of saying the login isn't connected.
- [ ] **Four wrong passwords in a row** lock you out for 20 minutes. Afterwards, clear the lockout on the **Limit Login Attempts** screen in wp-admin.
- [ ] **Clean up afterwards:** delete test households, people and user accounts. Church Admin should be back to 65 people.
- [ ] **Google Maps: ask the church** (the intern is contacting them). Do they want maps, and who would own the Google account?
  - Church Admin can show a map on the registration form and in the member directory, but only with a Google Maps API key.
  - That needs a Google Cloud account with billing turned on; normal church use should stay within the free allowance.
  - If yes: restrict the key to the church's domain and paste it into **Church Admin → Settings**. Then test the registration form's address map, and turn the directory map on (`map="1"` in the address-list shortcode on the Members page).
  - Until then, Maps isn't loaded at all (`inc/members.php`), so there's no console warning and no request to Google.

- [ ] **Spreadsheets for certain members: ask the church** (the intern is finding the person who keeps them)
  - **Ask:** who needs to see them? View only, or editing too? What information is in them (giving by person, pledge totals, budget, attendance)? Where does it live now (Church Admin, Google Sheets, Excel)? How often is it updated? Who must *not* see it?
  - **Likely sources on the site:**
    - Church Admin **Giving**, which is already limited to the two accounts with Church Admin's Giving permission, `michelle.ziemann` and `18davm@gmail.com`. `/giving/` shows its totals to them.
    - TablePress tables. The four present are public meeting and document lists on Documents.
  - **Plan once known:** a members' hub section shown only to flagged people, either through Church Admin permissions or the unused WordPress **"finance"** role. It's left out on the server for everyone else, not just hidden on screen.
  - **Also:** a "WordPress dashboard" link on the hub for staff roles only (administrators, editors). Harlan Bloom's account is currently a **subscriber**; check whether he uses another login.

## 2. Before making the WPvivid backup

- [ ] Email back to save only: `docker compose run --rm -T wpcli option delete emerson_local_mail_redirect_to`
- [ ] Test data removed (see "Clean up afterwards" in section 1)
- [ ] No scheduled jobs in the past: `docker compose run --rm -T wpcli cron event list`. The overdue ones were rescheduled on 2026-09-29.
- [ ] Leadership has approved the Privacy Policy wording, including the newsletter, local-storage and login-protection sections
- [ ] **Church question: YouTube.** Confirm that **@EmersonUUChapel** is the current channel. Sunday Services links to it, but 33 of the 35 sermon videos are on the older **@emersonunitarianuniversali1222**. Also ask whoever uploads sermons to set each gallery video's date to the Sunday it was recorded, so Past Sermons stays in order.
- [ ] **Church decision: the member directory.** Only households that opted in ("show me") are listed, currently 2 of the 32 Members. Decide whether to ask members to opt in, for example with a note in the newsletter or Church Admin's "update your details" email.
- [ ] Optional: hide the Website and Biographical Info fields on Edit Profile (**Profile Builder → Form Fields**)
- [ ] The missing images from the sitemaster are added:
  - 3 on the Welcome! page
  - 5 on Serve Emerson Chapel: `service.png`, `women-together.jpeg`, `men-together.jpeg`, `circle-suppers.jpeg`, `board-meetings.jpg` (uploaded 2021/11 and 2022/07). Until then those banners are solid blue. See `CHANGELOG.md` for how to put the photos back.
- [ ] Decision on the mint green (`#00d084`), which has low contrast as text on white
- [ ] Someone monitors `office@emersonuuchapel.org` (newsletter and Newcomer form) and `com@emersonuuchapel.org` (Church Admin and the site admin)
- [ ] Optional: delete unused forms. That's 6 Calculated Fields demo forms that email `com@` and copy the visitor, the "Emerson 2023-24 Pledge Drive" WPForm and the "Simple Contact Form" WPForm.
- [ ] **To build: social media icons in the footer.** Use the links already on Ways to Connect:
  - Facebook `https://www.facebook.com/emersonuuchapel`
  - Instagram `https://instagram.com/emersonuuchapel`
  - Twitter/X `https://twitter.com/EmersonChapel`
  All three answered on 2026-09-29. Ask the church whether the Twitter/X account is still used before including it.
- [ ] Anything changed on the live site since 2026-09-24 has been copied into the local site (the restore replaces it)



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