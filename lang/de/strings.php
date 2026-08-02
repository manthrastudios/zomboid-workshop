<?php

return [
    'nav_label' => 'Workshop-Mods',
    'title' => 'Steam Workshop',

    'tabs' => [
        'mods' => 'Meine Mods',
        'search' => 'Workshop durchsuchen',
    ],

    'badges' => [
        'total' => 'In der Liste',
        'active' => 'Aktiv',
        'loose' => 'Lose Mod-IDs',
    ],

    'columns' => [
        'mod' => 'Mod',
        'mod_ids' => 'Mod-IDs',
        'active' => 'Aktiv',
        'none_detected' => 'noch keine erkannt',
        'workshop' => 'Workshop :id',
    ],

    'row' => [
        'enable' => 'Aktivieren',
        'disable' => 'Deaktivieren',
        'move_up' => 'Nach oben (lädt früher)',
        'move_down' => 'Nach unten (lädt später)',
        'edit_ids' => 'Mod-IDs bearbeiten',
        'rescan' => 'Mod-IDs aus heruntergeladenen Dateien erkennen',
        'remove' => 'Aus der Liste entfernen',
        'add' => 'Hinzufügen',
        'in_list' => 'In deiner Liste',
    ],

    'actions' => [
        'add_by_url' => 'Per URL/ID hinzufügen',
        'import_collection' => 'Kollektion importieren',
        'import_ini' => 'Aktuelle Konfiguration importieren',
        'apply' => 'Auf dem Server speichern',
        'restart' => 'Server neu starten',
    ],

    'forms' => [
        'url_label' => 'Workshop-URL oder -ID',
        'collection_label' => 'Kollektions-URL oder -ID',
        'selected_label' => 'Erkannte Mod-IDs (angehakte werden geladen)',
        'manual_label' => 'Mod-IDs manuell hinzufügen',
        'manual_placeholder' => 'eingeben und Enter drücken',
    ],

    'filters' => [
        'sort' => 'Sortieren nach',
        'sort_trend' => 'Im Trend',
        'sort_relevance' => 'Relevanz',
        'sort_newest' => 'Neueste',
        'sort_top' => 'Am besten bewertet',
        'period' => 'Trend-Zeitraum',
        'period_day' => 'Heute',
        'period_week' => 'Diese Woche',
        'period_month' => 'Diesen Monat',
        'period_year' => 'Dieses Jahr',
        'build' => 'Spiel-Build',
        'category' => 'Kategorie',
    ],

    'modals' => [
        'remove_heading' => 'Mod entfernen',
        'remove_description' => '„:title" aus deiner Liste entfernen? Die Dateien bleiben heruntergeladen; er wird nach dem Speichern nur nicht mehr geladen.',
        'import_ini_heading' => 'Aktuelle Konfiguration importieren',
        'import_ini_description' => 'Liest die Mods, die dein Server bereits nutzt, und baut daraus die Liste. Was schon in deiner Liste ist, bleibt erhalten.',
        'apply_heading' => 'Mod-Liste auf dem Server speichern?',
        'apply_description' => ':enabled aktive Mods (von :total in der Liste) werden in die Serverkonfiguration geschrieben. Die Änderungen gelten ab dem nächsten Neustart.',
        'restart_heading' => 'Server neu starten?',
        'restart_description' => 'Alle aktuellen Spieler werden getrennt. Ein Server mit Mods braucht ein paar Minuten, bis er wieder da ist.',
    ],

    'notifications' => [
        'already_in_list' => 'Dieser Mod ist bereits in deiner Liste',
        'added' => 'Mod hinzugefügt',
        'added_body' => ':title — Mod-IDs: :ids',
        'added_no_ids' => 'Hinzugefügt, aber noch keine Mod-ID erkannt',
        'added_no_ids_body' => 'Ich konnte die Mod-ID nicht automatisch erkennen. Nutze nach dem nächsten Server-Neustart „:rescan" — dann wird sie aus den heruntergeladenen Dateien erkannt.',
        'invalid_url' => 'Das sieht nicht nach einer Workshop-URL oder -ID aus',
        'steam_error' => 'Steam ist nicht erreichbar',
        'steam_error_body' => 'Steam hat nicht geantwortet. Warte kurz und versuch es erneut.',
        'collection_empty' => 'Kollektion leer oder nicht gefunden',
        'collection_imported' => 'Kollektion importiert: :count Mods hinzugefügt',
        'ini_no_items' => 'Dein Server hat noch keine Workshop-Mods konfiguriert',
        'ini_imported' => ':count Mods aus der Serverkonfiguration importiert',
        'ini_imported_extras' => ' (:count Mod-IDs ohne passendes Element wurden ebenfalls erhalten)',
        'ids_updated' => 'Mod-IDs aktualisiert',
        'rescan_empty' => 'Noch nichts auf der Festplatte gefunden',
        'rescan_empty_body' => 'Der Server hat diesen Mod noch nicht heruntergeladen — starte ihn einmal neu und versuch es dann erneut.',
        'rescan_found' => 'Gefundene Mod-IDs: :ids',
        'removed' => 'Aus deiner Liste entfernt',
        'applied' => 'Mod-Liste gespeichert!',
        'applied_body' => ':workshop Mods mit :ids Mod-IDs eingerichtet. Starte den Server neu, wann immer du sie live schalten willst.',
        'apply_failed' => 'Liste konnte nicht gespeichert werden',
        'search_unavailable' => 'Suche nicht verfügbar',
        'search_needs_key' => 'Die Suche im Panel benötigt einen Steam-API-Schlüssel. Ein Administrator kann ihn unter Admin → Plugins → Zomboid Workshop Mods einrichten.',
        'restart_sent' => 'Neustart läuft… der Server ist in wenigen Minuten zurück',
        'restart_failed' => 'Server konnte nicht neu gestartet werden',
    ],

    'settings' => [
        'api_key' => 'Steam Web API-Schlüssel',
        'api_key_help' => 'Nur für die Suche im Panel nötig. Hol dir deinen auf steamcommunity.com/dev/apikey.',
        'nav_sort' => 'Menüposition',
        'saved' => 'Einstellungen gespeichert',
    ],
];
