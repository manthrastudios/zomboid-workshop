# Changelog

All notable changes to this plugin are documented here.

## [0.8.0]

### Added

- **The world guard now covers every button, not just save.** A new "In the
  world" column marks the mods the current world already has baked in, so the
  risk is visible before you click anything. The column only ever lights up —
  a blank row is silence, not a claim that the mod is safe to take out.

- **Disabling a mod now asks for confirmation when it would break the world.**
  This was a single silent click, and it is exactly as destructive as removing:
  "Save to server" only writes enabled mods, so disabling takes the mod out of
  the world the same way. The dialog says so plainly, because the toggle looks
  like the cautious option and isn't.

  Enabling a mod, or disabling one the world does not use, stays a single click.
  Friction where there is no risk is noise, and noise teaches people to click
  through the warning that matters.

### Changed

- Removing a mod the world uses now leads with that, above the file deletion.

### Fixed

- **The world warnings no longer come out in three different colours.** A
  confirmation dialog inherits the colour of the button that opened it, so the
  same warning — this corrupts every player's save — was red on remove, amber on
  save and **green** on the toggle, where the confirm button read as the
  positive action. When the world is at risk all three are red now, because the
  colour has to follow the severity of what is being announced, not the state of
  the row.

## [0.7.0]

### Added

- **The save button now warns when it would break the world.** Taking a mod out
  of a world that is already using it corrupts the save for every player — the
  game cannot resolve what disappeared. Until now nothing in the panel said so.

  "Save to server" reads which mods the current world actually has baked in
  (from `WorldDictionaryReadable.lua`, which the game writes in plain text) and,
  if the write would stop loading any of them, leads the confirmation with their
  names and what it costs, instead of the usual mod count.

  This is deliberately at the save step rather than on each button: removing and
  disabling only edit the plugin's own list, and it is the save that writes
  `Mods=`. Guarding here also covers the paths that have no warning of their own
  — importing from the ini, editing mod ids, reordering — and catches mods that
  are in the world but were never in the list at all, which happens when the ini
  is edited outside the panel.

  When the world cannot be read (a server that has never booted, an unreadable
  save), the dialog stays as it was. It never claims a removal is safe: absence
  of evidence is not evidence of safety, and a mod that registers no content may
  not appear in the world data at all.

## [0.6.0]

### Changed

- **Removing a mod now deletes its files.** The trash button on the server list
  used to only drop the mod from the list, leaving the downloaded Workshop
  content on the volume — the label said "Remove from list", but the result was
  a mod that was gone from the panel and still occupying disk forever, with no
  way to clean it up from the UI.

  The button is now "Remove and delete files": the mod leaves the list *and*
  `steamapps/workshop/content/108600/<id>` is deleted from the server, using the
  same deletion path the staging-server reject flow already used.

  **If you only want a mod to stop loading, use the row toggle instead** — that
  is what it is for, and it keeps the files. The confirmation dialog now says
  this explicitly.

  Note that re-adding a removed mod means downloading it again from scratch.

### Fixed

- Confirmation dialog, button tooltip and success notification no longer claim
  the files stay on the server. Updated in all seven locales.

## [0.5.0]

- Homologation flow: search, stage to the test server, approve into the main
  list. Rejecting a candidate cleans up the staging server.
