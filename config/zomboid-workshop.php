<?php

return [
    // Chave da Steam Web API (https://steamcommunity.com/dev/apikey).
    // Necessária apenas para a busca dentro do painel; adicionar por URL/ID
    // e importar coleção funcionam sem chave.
    'steam_api_key' => env('STEAM_WEB_API_KEY'),

    // Posição do item no menu lateral do servidor.
    'nav_sort' => env('ZOMBOID_WORKSHOP_NAV_SORT', 11),
];
