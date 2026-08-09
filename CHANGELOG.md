# Changelog

All notable changes to this plugin are documented here.

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
