# Security audit tracker (local → live)

Started from the WordPress Site Health / security breakdown (2026-11-06). Work **one item at a time**; mark **Done** only after a check on this local site. Do not edit Church Admin plugins.

Related: Church Admin **People → Create user** is a bulk auto-login tool (random password). For one person, edit that directory entry and create/link the WordPress user there — see `TROUBLESHOOTING.md`.

---

## Checklist

| # | Issue (from audit) | Plan | Status |
|---|--------------------|------|--------|
| 1 | Zephyr roles hold `manage_options` | Strip from `zpm_manager` / `zpm_admin` via Emerson | **Done** |
| 2 | Subscriber can edit/delete AIO Video Gallery videos | Strip aiovg edit/delete from Subscriber | **Done** |
| 3 | XML-RPC enabled | 403 on XML-RPC boots | **Done** |
| 4 | Theme/plugin file editor | `DISALLOW_FILE_EDIT` in Emerson | **Done** |
| 5 | Unused Zephyr Administrator role | Remove when no holders | **Done** |
| 6 | Unused application passwords | Revoked never-used password | **Done** |
| 7 | Too many administrators | Leave current Admins as-is; church decides who to demote and does it in Users | **Deferred to church** (2026-11-09) |
| 8 | TablePress caps on Author | Strip `tablepress_*` from Author; keep on Admin/Editor | **Done** (2026-11-10) |
| 9 | REST API auto-discover | Remove front discovery; block anonymous `/wp/v2/users` | **Done** (2026-11-10) — verify Gutenberg after deploy |
| 10 | NextGEN odd capability names | Remove orphan `NextGEN …` caps (plugin not installed) | **Done** (2026-11-06) |
| 11 | Writable plugins/themes filesystem | Normal 755/644 only; **do not** set `DISALLOW_FILE_MODS` (blocks updates) | **Skip / closed** — see note below |

---

## Come back later

Use this list when ready; do not mark Done until checked on local.

- [x] **#7 Administrators (decision recorded 2026-11-09)** — Keep everyone who is Admin for now. Church will decide who to remove and change roles themselves in **Users**. Emerson will not demote accounts. Local count was **6** Administrators as of 2026-11-06.
- [x] **#8 TablePress on Author (2026-11-10)** — Church: **No**. Emerson strips `tablepress_*` from Author on `init` (`inc/security.php`). Administrators and Editors keep full TablePress access.
- [x] **#9 REST API discovery (2026-11-10)** — After backup: removed public REST discovery (`rest_output_link_wp_head`, `rest_output_link_header`, RSD) and blocked anonymous `/wp/v2/users` (enumeration). Did **not** disable REST for logged-in users. **Test:** open Gutenberg, save a page, Members calendar still works.

~~#11~~ closed — writable plugins/themes is normal; keep dashboard updates. File Editor already disabled (#4). Do not use `DISALLOW_FILE_MODS`.

### #8 explained (TablePress on Author) — done

**TablePress** stores HTML tables in the admin (not a normal page table block). Authors previously could add/edit/delete/import/export those tables site-wide. Church answer **No** (2026-11-10): only Administrators and Editors manage TablePress. Emerson removes Author’s `tablepress_*` caps on every `init` so a plugin update cannot quietly put them back.

---

## Notes

- **Finance role** gates private spreadsheets/docs. Assign under **Users →** person → Role **Finance**.
- Do not commit personal member login emails into this file; look up current admins in wp-admin.
- Zephyr Manager may still exist for project use; it must not regain `manage_options` (Emerson strips on `init`).
- **#11 (2026-11-06):** skipped on purpose. Blocking file mods to “harden” the disk would block plugin/theme updates and can leave the site more vulnerable, not less.
