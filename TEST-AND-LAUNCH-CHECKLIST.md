# Test and launch checklist

Tick items off as you go. Deployment steps in detail: [`DEPLOY-WPVIVID.md`](DEPLOY-WPVIVID.md). How to switch email modes: [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md#testing-real-email-locally-redirect-mode).

## 1. Local testing (email in redirect mode)

In redirect mode every email really goes out, but only to your test mailbox. The subject shows who it would have gone to, for example `[LOCAL TEST → office@emersonuuchapel.org] Newcomer information`.

Use a private window (Cmd+Shift+P) for anything that should be tested logged out.

- [ ] **Newcomer Information form** (`/contact-newcomer-information/`)
  - "Newcomer information" email arrives (original recipient: `office@`)
  - Replying to it goes to the address typed in the form
  - The phone field accepts `(636) 555-0123`, `636-555-0123` and `6365550123`
- [ ] **Newsletter pop-up** (homepage, logged out)
  - Opens after 5 seconds, fades in; ×, "No thanks", Esc and clicking outside all fade it out
  - Sign-up shows "Almost done!" and fades out after about 4 seconds
  - "Please confirm your subscription" email arrives
  - The link shows the green "You're subscribed" notice
  - "New newsletter subscriber" email arrives (original recipient: `office@`)
  - The person appears in Church Admin as **Mailing List** and is hidden from the directory
  - Clicking the link a second time says it has expired or was already used
- [ ] **Ways to Connect form** (`/ways-to-connect/`): same results as the pop-up
- [ ] **Church Admin household registration** (Members pages, logged out)
  - The registrant gets a confirm-your-email message
  - After confirming, the "new household" email with the approval steps arrives (original recipient: `com@`)
  - Approving them in Church Admin sends the "user login created" email
- [ ] **Login and password reset** (Members page)
  - The reset email arrives and its link works
  - Only one login form shows on the page
- [ ] **Restricted pages** (for example Member Home) when logged out: redirect to the Members login, then back to the page after logging in
- [ ] **Clean up afterwards:** delete test households, people and user accounts. Church Admin should be back to 65 people.

## 2. Before making the WPvivid backup

- [ ] Email back to save only: `docker compose run --rm -T wpcli option delete emerson_local_mail_redirect_to`
- [ ] Test data removed (see the last item above)
- [ ] No scheduled jobs in the past: `docker compose run --rm -T wpcli cron event list`. The overdue ones were rescheduled on 2026-09-29.
- [ ] Leadership has approved the Privacy Policy wording, including the newsletter and local-storage sections
- [ ] The 3 missing Welcome! page images from the sitemaster are added
- [ ] Decision on the mint green (`#00d084`), which has low contrast as text on white
- [ ] Someone monitors `office@emersonuuchapel.org` (newsletter and Newcomer form) and `com@emersonuuchapel.org` (Church Admin and the site admin)
- [ ] Optional: delete unused forms. That's 6 Calculated Fields demo forms that email `com@` and copy the visitor, the "Emerson 2023-24 Pledge Drive" WPForm and the "Simple Contact Form" WPForm.
- [ ] Anything changed on the live site since 2026-09-24 has been copied into the local site (the restore replaces it)

## 3. After restoring to the live site

- [ ] W3 Total Cache: turn **page caching** back on, then **Performance → Purge All Caches**
- [ ] **WP Mail SMTP → Tools → Email Test** to your own address arrives
- [ ] No leftover `localhost:8080` links (spot-check the homepage, the menu and the footer)
- [ ] Logged out, the homepage pop-up still opens with page caching on; logged in, it never shows
- [ ] One real newsletter sign-up and one Newcomer form submission reach `office@`
- [ ] Mailchimp: the office adds new subscribers by hand until someone provides an API key and audience ID
- [ ] Remove your own test sign-up from Church Admin and Mailchimp afterwards
