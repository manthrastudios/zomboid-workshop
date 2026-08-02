# Zomboid Workshop Mods — plugin para Pelican Panel

Gerencie mods da Steam Workshop em servidores **Project Zomboid** direto do painel,
sem editar `Mods=`/`WorkshopItems=` na mão.

## Funcionalidades

- **Buscar na workshop** dentro do painel (precisa de uma [Steam Web API key](https://steamcommunity.com/dev/apikey) gratuita, configurada em Admin → Plugins)
- **Adicionar por URL ou ID** da workshop (sem API key)
- **Importar coleção** da Steam inteira, na ordem da coleção (sem API key)
- **Importar do ini** atual — popula a lista a partir do que o servidor já usa
- **Ligar/desligar** cada mod e **reordenar** (ordem de load do PZ)
- **Detecção de Mod IDs** em duas camadas: descrição da workshop + leitura dos
  `mod.info` já baixados no volume (inclusive layout B42 com subpastas `42*/common`)
- **Aplicar**: reescreve `WorkshopItems=` e `Mods=` no ini via wings; botão de
  restart opcional (mods só valem no boot)

A lista fica em `.zomboid-workshop.json` no volume do servidor — viaja com backups
e sobrevive a reinstalação do painel.

## Instalação

```bash
cp -r zomboid-workshop /var/www/pelican/plugins/   # ou o plugins/ montado do container
php artisan p:plugin:install zomboid-workshop
```

A página "Workshop Mods" aparece em servidores cujo egg tem `zomboid` no nome
(ou o feature `zomboid_workshop`).

## Status

Em desenvolvimento, testado com Pelican `panel:latest` (ago/2026) e o egg
Project Zomboid do pelican-eggs, Build 42. Strings em pt-BR por enquanto.
