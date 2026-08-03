# Zomboid Workshop Mods — Pelican Panel plugin

Manage Steam Workshop mods on **Project Zomboid** servers straight from the panel —
no more hand-editing `Mods=`/`WorkshopItems=` lines. Now with an optional
**staging workflow**: test candidate mods on a second server before they go live.

*[Português mais abaixo](#português-brasil)* 🇧🇷

## The workflow

The page is organized as a numbered pipeline:

1. **Find mods** — browse the workshop inside the panel: text search, sort
   (trending, top rated, most subscribed, recently updated…), filters by
   period/build/category, with star ratings, subscriber counts and last-update
   dates. Add by URL/ID or import a whole Steam collection. Every new mod
   lands in the test queue first.
2. **Test & deploy** — the queue of candidate mods. Pick a **staging server**
   (any other Zomboid server on your panel) and hit *Test on staging*: the
   active list + candidates are applied there and it restarts. Approve a mod
   to promote it to the live list.
3. **Deployed mods** — the list that counts on the server: toggle, reorder
   (PZ load order), remove, import the current setup, save to the config and
   restart.

Don't want staging? Just don't configure a staging server — approve mods
straight from the queue.

## Features

- **Rich workshop browsing**: ratings, votes, subscribers, update dates; six
  sort modes and period/build/category filters shown above the results
- **Add by URL or ID**, or **import an entire Steam collection** in order
- **Import your current setup** — builds the list from the server's existing config
- **Staging flow**: candidate queue, one-click test on a second server,
  approve to promote — candidates never leak into the live server's config
- **Start test server** button — bring the staging server up only when needed
- **Two-layer Mod ID detection**: workshop description parsing + reading the
  actual `mod.info` files already downloaded on the server (handles the
  Build 42 folder layouts, including multi-mod workshop items)
- **Save to server** rewrites `Mods=` and `WorkshopItems=` via Wings, with an
  optional restart button (mods load at boot)
- Localized in **7 languages**: en, pt_BR, es, fr, de, ja, zh_CN — follows the
  panel locale

The mod list is stored as `.zomboid-workshop.json` inside the server's own
volume, so it travels with backups and survives panel reinstalls. The staging
configuration lives in the same file.

## Requirements

- Pelican Panel with the plugin system (2026 builds)
- A Project Zomboid server using an egg whose name contains "zomboid"
  (or add the `zomboid_workshop` feature to your egg)
- Optional: a second Zomboid server on the same panel to use as staging
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

## Tips

- If a workshop item's Mod ID can't be parsed from its description, restart the
  server once (so it downloads) and hit the per-row **rescan** button — the IDs
  are then read from the downloaded `mod.info` files, which is authoritative.
- The staging server can be stopped whenever it's idle; the *Start test server*
  button brings it up on demand. Pair it with a host-side idle watchdog if you
  want it to shut itself down (e.g. cron + RCON `players` + a panel power stop).
- Golden rule of Zomboid multiplayer: **never remove a mod from an existing
  world** — the WorldDictionary can corrupt the save. Adding is safe; removing
  calls for a fresh world. Test on staging first.

---

## Português (Brasil)

Gerencie mods da Steam Workshop em servidores **Project Zomboid** direto do
painel — sem editar `Mods=`/`WorkshopItems=` na mão. Agora com um fluxo
opcional de **homologação**: teste os mods candidatos num segundo servidor
antes de irem pro ar.

O fluxo em três passos:

1. **Buscar mods** — busca rica na workshop (avaliação, assinantes, data de
   update, ordenações e filtros). Todo mod novo entra na fila de teste.
2. **Testar & publicar** — a fila de candidatos: escolha um servidor de
   testes, aplique tudo nele com um clique e aprove o que passar.
3. **No servidor** — a lista que vale: ligar/desligar, reordenar (ordem de
   load do PZ), remover, salvar no servidor e reiniciar.

Sem servidor de testes configurado, dá pra aprovar direto da fila.

- Detecção de Mod IDs em duas camadas (descrição + `mod.info` no disco, com
  suporte ao layout do Build 42)
- Candidato nunca vaza pra configuração do servidor principal
- Botão de ligar o servidor de testes só quando precisar
- 7 idiomas, seguindo o idioma do painel

A busca embutida precisa de uma [chave gratuita da Steam Web API](https://steamcommunity.com/dev/apikey)
(Admin → Plugins → Zomboid Workshop Mods). O resto funciona sem chave.

Regra de ouro do multiplayer: **nunca remova mod de um mundo existente** — o
WorldDictionary pode corromper o save. Adicionar é seguro; remover pede mundo
novo. Teste antes no servidor de homologação.

## License

[MIT](LICENSE)
