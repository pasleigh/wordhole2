# Wordhole

- added thing with vale
## Groups

Several independent groups can share one install. Each group has its own people, rounds and results, and is
chosen with the "Select a group" dropdown (or a link ending `?g=<code>`).

Each `Round n` sheet of an uploaded workbook identifies its group:

| Cell | Holds |
|------|-------|
| A2   | the group's unique code (up to 40 letters, numbers, spaces, `.`, `-`, `_`; not case sensitive). Must be the same on every round sheet. |
| A3   | the group's display name (optional; the code is used if blank). |

A workbook with a new code creates a new group. Results that existed before groups were added belong to the group
set by `$legacy_group_code` / `$legacy_group_name` in `config.php`.

The database is migrated automatically the first time the site is loaded; a copy of the old file is kept as
`<database>.pre-groups-<timestamp>.bak`.
