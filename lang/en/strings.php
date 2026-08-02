<?php

return [
    'nav_label' => 'Workshop Mods',
    'title' => 'Steam Workshop',

    'tabs' => [
        'mods' => 'My mods',
        'search' => 'Browse workshop',
    ],

    'badges' => [
        'total' => 'In the list',
        'active' => 'Active',
        'loose' => 'Unmatched mod IDs',
    ],

    'columns' => [
        'mod' => 'Mod',
        'mod_ids' => 'Mod IDs',
        'active' => 'Active',
        'none_detected' => 'none detected yet',
        'workshop' => 'Workshop :id',
    ],

    'row' => [
        'enable' => 'Enable',
        'disable' => 'Disable',
        'move_up' => 'Move up (loads earlier)',
        'move_down' => 'Move down (loads later)',
        'edit_ids' => 'Edit mod IDs',
        'rescan' => 'Detect mod IDs from downloaded files',
        'remove' => 'Remove from list',
        'add' => 'Add',
        'in_list' => 'In your list',
    ],

    'actions' => [
        'add_by_url' => 'Add by URL/ID',
        'import_collection' => 'Import collection',
        'import_ini' => 'Import current setup',
        'apply' => 'Save to server',
        'restart' => 'Restart server',
    ],

    'forms' => [
        'url_label' => 'Workshop URL or ID',
        'collection_label' => 'Collection URL or ID',
        'selected_label' => 'Detected mod IDs (checked ones will be loaded)',
        'manual_label' => 'Add mod IDs manually',
        'manual_placeholder' => 'type and press Enter',
    ],

    'filters' => [
        'button' => 'Filters',
        'sort' => 'Sort by',
        'sort_trend' => 'Trending',
        'sort_relevance' => 'Relevance',
        'sort_newest' => 'Most recent',
        'sort_top' => 'Top rated',
        'period' => 'Trending period',
        'period_day' => 'Today',
        'period_week' => 'This week',
        'period_month' => 'This month',
        'period_year' => 'This year',
        'build' => 'Game build',
        'category' => 'Category',
    ],

    'modals' => [
        'remove_heading' => 'Remove mod',
        'remove_description' => 'Remove ":title" from your list? Its files stay downloaded; it just won\'t load anymore after you save.',
        'import_ini_heading' => 'Import current setup',
        'import_ini_description' => 'Reads the mods your server is already using and builds the list from them. Anything already in your list is kept.',
        'apply_heading' => 'Save mod list to server?',
        'apply_description' => ':enabled active mods (of :total in your list) will be written to the server configuration. Changes take effect the next time the server restarts.',
        'restart_heading' => 'Restart the server?',
        'restart_description' => 'Anyone playing right now will be disconnected. A modded server takes a few minutes to come back up.',
    ],

    'notifications' => [
        'already_in_list' => 'That mod is already in your list',
        'added' => 'Mod added',
        'added_body' => ':title — mod IDs: :ids',
        'added_no_ids' => 'Added, but no mod ID detected yet',
        'added_no_ids_body' => 'I couldn\'t identify the mod ID automatically. After the next server restart, use ":rescan" and it will be picked up from the downloaded files.',
        'invalid_url' => 'That doesn\'t look like a workshop URL or ID',
        'steam_error' => 'Couldn\'t reach Steam',
        'steam_error_body' => 'Steam didn\'t answer. Give it a moment and try again.',
        'collection_empty' => 'Collection is empty or not found',
        'collection_imported' => 'Collection imported: :count mods added',
        'ini_no_items' => 'Your server has no workshop mods configured yet',
        'ini_imported' => 'Imported :count mods from the server setup',
        'ini_imported_extras' => ' (:count mod IDs without a matching item were kept too)',
        'ids_updated' => 'Mod IDs updated',
        'rescan_empty' => 'Nothing found on disk yet',
        'rescan_empty_body' => 'The server hasn\'t downloaded this mod yet — restart it once and try again.',
        'rescan_found' => 'Mod IDs found: :ids',
        'removed' => 'Removed from your list',
        'applied' => 'Mod list saved!',
        'applied_body' => ':workshop mods with :ids mod IDs are set up. Restart the server whenever you\'re ready to make them live.',
        'apply_failed' => 'Couldn\'t save the list',
        'search_unavailable' => 'Search unavailable',
        'search_needs_key' => 'In-panel search needs a Steam API key. An administrator can set it up in Admin → Plugins → Zomboid Workshop Mods.',
        'restart_sent' => 'Restarting… the server will be back in a few minutes',
        'restart_failed' => 'Couldn\'t restart the server',
    ],

    'settings' => [
        'api_key' => 'Steam Web API Key',
        'api_key_help' => 'Only needed for in-panel search. Get yours at steamcommunity.com/dev/apikey.',
        'nav_sort' => 'Menu position',
        'saved' => 'Settings saved',
    ],
];
