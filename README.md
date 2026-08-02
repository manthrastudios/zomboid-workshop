# Zomboid Workshop Mods — Pelican Panel plugin

Manage Steam Workshop mods on **Project Zomboid** servers straight from the panel —
no more hand-editing `Mods=`/`WorkshopItems=` lines.

*[Português mais abaixo](#português-brasil)* 🇧🇷

## Features

- **Browse the workshop** inside the panel (weekly trending + text search)
- **Add by URL or ID**, or **import an entire Steam collection** in order
- **Import your current setup** — builds the list from the server's existing config
- **Toggle mods on/off** and **reorder** them (PZ load order)
- **Two-layer Mod ID detection**: workshop description parsing + reading the
  actual `mod.info` files already downloaded on the server (handles the
  Build 42 folder layouts, including multi-mod workshop items)
- **Save to server** rewrites `Mods=` and `WorkshopItems=` via Wings, with an
  optional restart button (mods load at boot)
- Localized in **7 languages**: en, pt_BR, es, fr, de, ja, zh_CN — follows the
  panel locale

The mod list is stored as `.zomboid-workshop.json` inside the server's own
volume, so it travels with backups and survives panel reinstalls.

## Requirements

- Pelican Panel with the plugin system (2026 builds)
- A Project Zomboid server using an egg whose name contains "zomboid"
  (or add the `zomboid_workshop` feature to your egg)
- Optional: a free [Steam Web API key](https://steamcommunity.com/dev/apikey)
  for in-panel search (set it in Admin → Plugins → Zomboid Workshop Mods).
  Adding by URL/ID and importing collections work without a key.

## Installation

Recommended: install via the [Pelican Hub](https://hub.pelican.dev/plugins).

Manual:

```bash
cp -r zomboid-workshop /var/www/pelican/plugins/   # or your mounted plugins dir
php artisan p:plugin:install zomboid-workshop
```

The "Workshop Mods" page appears in the server panel for matching servers.

## How it works

1. Add mods (search, URL/ID, collection, or import your current setup)
2. Toggle and order them — nothing touches the server yet
3. **Save to server** writes the config; restart when you're ready

If a workshop item's Mod ID can't be parsed from its description, restart the
server once (so it downloads) and hit the per-row **rescan** button — the IDs
are then read from the downloaded `mod.info` files, which is authoritative.

---

## Português (Brasil)

Gerencie mods da Steam Workshop em servidores **Project Zomboid** direto do
painel — sem editar `Mods=`/`WorkshopItems=` na mão.

- Busca da workshop dentro do painel (populares da semana + busca por texto)
- Adicionar por URL/ID e importar coleções inteiras da Steam, na ordem
- Importar a configuração atual do servidor
- Ligar/desligar e reordenar (ordem de load do PZ)
- Detecção de Mod IDs em duas camadas (descrição + `mod.info` no disco, com
  suporte ao layout do Build 42)
- Salvar no servidor via Wings + botão de reiniciar
- 7 idiomas, seguindo o idioma do painel

A busca embutida precisa de uma [chave gratuita da Steam Web API](https://steamcommunity.com/dev/apikey)
(Admin → Plugins → Zomboid Workshop Mods). O resto funciona sem chave.

## License

[MIT](LICENSE)
