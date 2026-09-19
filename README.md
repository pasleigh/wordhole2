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

## Entering scores on the site

Anyone can look at a group's results. Changing them needs that group's password.

- **Switch editing on for a group** (once, from a terminal in this folder; a group made on the page already has its password): `php manage_groups.php set-password <group code>`.
  `php manage_groups.php list` shows which groups have a password. Use the same command to reset a forgotten one.
- **Log in** with the "Log in to edit" button. You stay logged in for 12 hours in that browser. Logging in to one group does not
  unlock any other group. Anyone logged in can change the password ("Change password"), which logs everyone else out of that group.
- **Enter scores** with the "Enter scores" button. Type in the scorecard (1 to 6, `X` = failed to complete in 6, `-` = did not send in a
  result; Enter or the arrow keys move between cells) or use "Daily entry" to pick a day and tap each player's score. The solution word
  is typed the same way. Totals and means are worked out for you (`X` and `-` count as 6.9999 and 7.0001 in the mean, as in the workbook).
  Nothing is stored until you press "Save changes".
- **New round**: the round number, first day, first Wordle number and par are filled in from the previous round (rounds are 21 days
  apart); tick who is playing and add new players one per line.
- **New group** (no workbook needed): the "New group" button asks for the **super admin password**, then a name, a unique code and
  a password for the group. You are logged in to the new group straight away and the New round dialog opens so you can add its first
  round and players.
- **Uploading a workbook** (`index.php?upload`) needs the group's password too. A workbook whose code is a new group creates that
  group, which needs the super admin password as well; the group password typed at upload becomes the new group's password.

## The super admin

Only the super admin can create groups (from the page or by uploading a workbook for a new one). There is one site-wide super admin
password, separate from every group password. Set it once, and reset it the same way, from a terminal in this folder:

    php manage_groups.php set-admin-password

Until it is set nobody can create a group (`php manage_groups.php list` shows whether it is set). It is asked for each time a
group is created and is not remembered by the browser. After ten wrong guesses in 15 minutes it is locked for a while.

The database is upgraded automatically on the first request, and needs write access for the web server (Apache's user, e.g.
`daemon`) to the database file and its folder. A copy of the old file is kept as `<database>.pre-upgrade-from-v<n>-<timestamp>.bak`.
