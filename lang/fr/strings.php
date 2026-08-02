<?php

return [
    'nav_label' => 'Mods Workshop',
    'title' => 'Steam Workshop',

    'tabs' => [
        'mods' => 'Mes mods',
        'search' => 'Parcourir le workshop',
    ],

    'badges' => [
        'total' => 'Dans la liste',
        'active' => 'Actifs',
        'loose' => 'Mod IDs orphelins',
    ],

    'columns' => [
        'mod' => 'Mod',
        'mod_ids' => 'Mod IDs',
        'active' => 'Actif',
        'none_detected' => 'aucun détecté pour l\'instant',
        'workshop' => 'Workshop :id',
    ],

    'row' => [
        'enable' => 'Activer',
        'disable' => 'Désactiver',
        'move_up' => 'Monter (chargé plus tôt)',
        'move_down' => 'Descendre (chargé plus tard)',
        'edit_ids' => 'Modifier les Mod IDs',
        'rescan' => 'Détecter les Mod IDs depuis les fichiers téléchargés',
        'remove' => 'Retirer de la liste',
        'add' => 'Ajouter',
        'in_list' => 'Dans votre liste',
    ],

    'actions' => [
        'add_by_url' => 'Ajouter par URL/ID',
        'import_collection' => 'Importer une collection',
        'import_ini' => 'Importer la configuration actuelle',
        'apply' => 'Enregistrer sur le serveur',
        'restart' => 'Redémarrer le serveur',
    ],

    'forms' => [
        'url_label' => 'URL ou ID du workshop',
        'collection_label' => 'URL ou ID de la collection',
        'selected_label' => 'Mod IDs détectés (les cochés seront chargés)',
        'manual_label' => 'Ajouter des Mod IDs manuellement',
        'manual_placeholder' => 'saisissez puis appuyez sur Entrée',
    ],

    'filters' => [
        'sort' => 'Trier par',
        'sort_trend' => 'Tendances',
        'sort_relevance' => 'Pertinence',
        'sort_newest' => 'Plus récents',
        'sort_top' => 'Mieux notés',
        'period' => 'Période des tendances',
        'period_day' => "Aujourd'hui",
        'period_week' => 'Cette semaine',
        'period_month' => 'Ce mois-ci',
        'period_year' => 'Cette année',
        'build' => 'Build du jeu',
        'category' => 'Catégorie',
    ],

    'modals' => [
        'remove_heading' => 'Retirer le mod',
        'remove_description' => 'Retirer « :title » de votre liste ? Les fichiers restent téléchargés ; il ne sera simplement plus chargé après l\'enregistrement.',
        'import_ini_heading' => 'Importer la configuration actuelle',
        'import_ini_description' => 'Lit les mods que votre serveur utilise déjà et construit la liste à partir de ceux-ci. Ce qui est déjà dans votre liste est conservé.',
        'apply_heading' => 'Enregistrer la liste de mods sur le serveur ?',
        'apply_description' => ':enabled mods actifs (sur :total dans la liste) seront écrits dans la configuration du serveur. Les changements prennent effet au prochain redémarrage.',
        'restart_heading' => 'Redémarrer le serveur ?',
        'restart_description' => 'Les joueurs connectés seront déconnectés. Un serveur moddé met quelques minutes à revenir.',
    ],

    'notifications' => [
        'already_in_list' => 'Ce mod est déjà dans votre liste',
        'added' => 'Mod ajouté',
        'added_body' => ':title — Mod IDs : :ids',
        'added_no_ids' => 'Ajouté, mais aucun Mod ID détecté pour l\'instant',
        'added_no_ids_body' => 'Je n\'ai pas pu identifier le Mod ID automatiquement. Après le prochain redémarrage du serveur, utilisez « :rescan » : il sera détecté depuis les fichiers téléchargés.',
        'invalid_url' => 'Cela ne ressemble pas à une URL ou un ID du workshop',
        'steam_error' => 'Impossible de joindre Steam',
        'steam_error_body' => 'Steam n\'a pas répondu. Patientez un instant puis réessayez.',
        'collection_empty' => 'Collection vide ou introuvable',
        'collection_imported' => 'Collection importée : :count mods ajoutés',
        'ini_no_items' => 'Votre serveur n\'a pas encore de mods workshop configurés',
        'ini_imported' => ':count mods importés depuis la configuration du serveur',
        'ini_imported_extras' => ' (:count Mod IDs sans élément correspondant ont aussi été conservés)',
        'ids_updated' => 'Mod IDs mis à jour',
        'rescan_empty' => 'Rien trouvé sur le disque pour l\'instant',
        'rescan_empty_body' => 'Le serveur n\'a pas encore téléchargé ce mod — redémarrez-le une fois puis réessayez.',
        'rescan_found' => 'Mod IDs trouvés : :ids',
        'removed' => 'Retiré de votre liste',
        'applied' => 'Liste de mods enregistrée !',
        'applied_body' => ':workshop mods avec :ids Mod IDs configurés. Redémarrez le serveur quand vous voulez les mettre en ligne.',
        'apply_failed' => 'Impossible d\'enregistrer la liste',
        'search_unavailable' => 'Recherche indisponible',
        'search_needs_key' => 'La recherche dans le panneau nécessite une clé Steam API. Un administrateur peut la configurer dans Admin → Plugins → Zomboid Workshop Mods.',
        'restart_sent' => 'Redémarrage… le serveur revient dans quelques minutes',
        'restart_failed' => 'Impossible de redémarrer le serveur',
    ],

    'settings' => [
        'api_key' => 'Clé Steam Web API',
        'api_key_help' => 'Nécessaire uniquement pour la recherche dans le panneau. Obtenez la vôtre sur steamcommunity.com/dev/apikey.',
        'nav_sort' => 'Position dans le menu',
        'saved' => 'Paramètres enregistrés',
    ],
];
