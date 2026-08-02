<?php

return [
    'nav_label' => 'Mods de Workshop',
    'title' => 'Steam Workshop',

    'tabs' => [
        'mods' => 'Mis mods',
        'search' => 'Explorar workshop',
    ],

    'badges' => [
        'total' => 'En la lista',
        'active' => 'Activos',
        'loose' => 'Mod IDs sueltos',
    ],

    'columns' => [
        'mod' => 'Mod',
        'mod_ids' => 'Mod IDs',
        'active' => 'Activo',
        'none_detected' => 'ninguno detectado aún',
        'workshop' => 'Workshop :id',
    ],

    'row' => [
        'enable' => 'Activar',
        'disable' => 'Desactivar',
        'move_up' => 'Subir (carga antes)',
        'move_down' => 'Bajar (carga después)',
        'edit_ids' => 'Editar Mod IDs',
        'rescan' => 'Detectar Mod IDs desde los archivos descargados',
        'remove' => 'Quitar de la lista',
        'add' => 'Añadir',
        'in_list' => 'En tu lista',
    ],

    'actions' => [
        'add_by_url' => 'Añadir por URL/ID',
        'import_collection' => 'Importar colección',
        'import_ini' => 'Importar configuración actual',
        'apply' => 'Guardar en el servidor',
        'restart' => 'Reiniciar servidor',
    ],

    'forms' => [
        'url_label' => 'URL o ID de la workshop',
        'collection_label' => 'URL o ID de la colección',
        'selected_label' => 'Mod IDs detectados (los marcados se cargarán)',
        'manual_label' => 'Añadir Mod IDs manualmente',
        'manual_placeholder' => 'escribe y pulsa Enter',
    ],

    'filters' => [
        'button' => 'Filtros',
        'sort' => 'Ordenar por',
        'sort_trend' => 'Tendencias',
        'sort_relevance' => 'Relevancia',
        'sort_newest' => 'Más recientes',
        'sort_top' => 'Mejor valorados',
        'period' => 'Período de tendencias',
        'period_day' => 'Hoy',
        'period_week' => 'Esta semana',
        'period_month' => 'Este mes',
        'period_year' => 'Este año',
        'build' => 'Build del juego',
        'category' => 'Categoría',
    ],

    'modals' => [
        'remove_heading' => 'Quitar mod',
        'remove_description' => '¿Quitar ":title" de tu lista? Los archivos siguen descargados; simplemente dejará de cargarse cuando guardes.',
        'import_ini_heading' => 'Importar configuración actual',
        'import_ini_description' => 'Lee los mods que tu servidor ya usa y construye la lista a partir de ellos. Lo que ya está en tu lista se conserva.',
        'apply_heading' => '¿Guardar la lista de mods en el servidor?',
        'apply_description' => ':enabled mods activos (de :total en la lista) se escribirán en la configuración del servidor. Los cambios se aplican en el próximo reinicio.',
        'restart_heading' => '¿Reiniciar el servidor?',
        'restart_description' => 'Quien esté jugando ahora será desconectado. Un servidor con mods tarda unos minutos en volver.',
    ],

    'notifications' => [
        'already_in_list' => 'Ese mod ya está en tu lista',
        'added' => 'Mod añadido',
        'added_body' => ':title — Mod IDs: :ids',
        'added_no_ids' => 'Añadido, pero sin Mod ID detectado aún',
        'added_no_ids_body' => 'No pude identificar el Mod ID automáticamente. Tras el próximo reinicio del servidor, usa ":rescan" y se detectará desde los archivos descargados.',
        'invalid_url' => 'Eso no parece una URL o ID de la workshop',
        'steam_error' => 'No pude comunicarme con Steam',
        'steam_error_body' => 'Steam no respondió. Espera un momento e inténtalo de nuevo.',
        'collection_empty' => 'Colección vacía o no encontrada',
        'collection_imported' => 'Colección importada: :count mods añadidos',
        'ini_no_items' => 'Tu servidor aún no tiene mods de workshop configurados',
        'ini_imported' => 'Importados :count mods de la configuración del servidor',
        'ini_imported_extras' => ' (:count Mod IDs sin ítem correspondiente también se conservaron)',
        'ids_updated' => 'Mod IDs actualizados',
        'rescan_empty' => 'Nada encontrado en disco todavía',
        'rescan_empty_body' => 'El servidor aún no descargó este mod — reinícialo una vez e inténtalo de nuevo.',
        'rescan_found' => 'Mod IDs encontrados: :ids',
        'removed' => 'Quitado de tu lista',
        'applied' => '¡Lista de mods guardada!',
        'applied_body' => ':workshop mods con :ids Mod IDs configurados. Reinicia el servidor cuando quieras activarlos.',
        'apply_failed' => 'No pude guardar la lista',
        'search_unavailable' => 'Búsqueda no disponible',
        'search_needs_key' => 'La búsqueda en el panel necesita una clave de Steam API. Un administrador puede configurarla en Admin → Plugins → Zomboid Workshop Mods.',
        'restart_sent' => 'Reiniciando… el servidor vuelve en unos minutos',
        'restart_failed' => 'No pude reiniciar el servidor',
    ],

    'settings' => [
        'api_key' => 'Clave de Steam Web API',
        'api_key_help' => 'Solo necesaria para la búsqueda en el panel. Consigue la tuya en steamcommunity.com/dev/apikey.',
        'nav_sort' => 'Posición en el menú',
        'saved' => 'Configuración guardada',
    ],
];
