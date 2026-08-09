# Changelog

All notable changes to this plugin are documented here.

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
