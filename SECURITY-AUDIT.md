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
| 7 | Too many administrators | Church names who keep Admin | **Skipped for now** — see come-back list |
| 8 | TablePress caps on Author | Confirm with church, then trim if unwanted | **Skipped for now** |
| 9 | REST API auto-discover | Harden carefully after editor testing | **Skipped for now** |
| 10 | NextGEN odd capability names | Remove orphan `NextGEN …` caps (plugin not installed) | **Done** (2026-11-06) |
| 11 | Writable plugins/themes filesystem | Normal 755/644 only; **do not** set `DISALLOW_FILE_MODS` (blocks updates) | **Skip / closed** — see note below |

---

## Come back later (skipped)

Use this list when ready; do not mark Done until checked on local.

- [ ] **#7 Administrators** — Get a church decision on who stays **Administrator** vs **Editor** / **Finance** / **Subscriber**. Local count as of 2026-11-06: **6** administrator accounts (see session note / **Users → All Users** filtered by Administrator). Demote only with an explicit list.
- [ ] **#8 TablePress on Author** — Ask whether Authors should add/edit/delete TablePress tables. If no, strip `tablepress_*` caps from Author (keep on Editor if editors need them).
- [ ] **#9 REST API discovery** — Research a harden that does **not** break the block editor or Church Admin AJAX; test on local thoroughly before live.

~~#11~~ closed — writable plugins/themes is normal; keep dashboard updates. File Editor already disabled (#4). Do not use `DISALLOW_FILE_MODS`.

---

## Notes

- **Finance role** gates private spreadsheets/docs. Assign under **Users →** person → Role **Finance**.
- Do not commit personal member login emails into this file; look up current admins in wp-admin.
- Zephyr Manager may still exist for project use; it must not regain `manage_options` (Emerson strips on `init`).
- **#11 (2026-11-06):** skipped on purpose. Blocking file mods to “harden” the disk would block plugin/theme updates and can leave the site more vulnerable, not less.
