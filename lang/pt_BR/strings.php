<?php

return [
    'nav_label' => 'Workshop Mods',
    'title' => 'Steam Workshop',

    'tabs' => [
        'mods' => 'Meus mods',
        'search' => 'Buscar na workshop',
    ],

    'badges' => [
        'total' => 'Na lista',
        'active' => 'Ativos',
        'loose' => 'Mod IDs avulsos',
    ],

    'columns' => [
        'mod' => 'Mod',
        'mod_ids' => 'Mod IDs',
        'active' => 'Ativo',
        'none_detected' => 'nenhum detectado ainda',
        'workshop' => 'Workshop :id',
    ],

    'row' => [
        'enable' => 'Ligar',
        'disable' => 'Desligar',
        'move_up' => 'Subir (carrega antes)',
        'move_down' => 'Descer (carrega depois)',
        'edit_ids' => 'Editar Mod IDs',
        'rescan' => 'Detectar Mod IDs pelos arquivos baixados',
        'remove' => 'Remover da lista',
        'add' => 'Adicionar',
        'in_list' => 'Na sua lista',
    ],

    'actions' => [
        'add_by_url' => 'Adicionar por URL/ID',
        'import_collection' => 'Importar coleção',
        'import_ini' => 'Importar configuração atual',
        'apply' => 'Salvar no servidor',
        'restart' => 'Reiniciar servidor',
    ],

    'forms' => [
        'url_label' => 'URL ou ID da workshop',
        'collection_label' => 'URL ou ID da coleção',
        'selected_label' => 'Mod IDs detectados (os marcados serão carregados)',
        'manual_label' => 'Adicionar Mod IDs manualmente',
        'manual_placeholder' => 'digite e aperte Enter',
    ],

    'filters' => [
        'button' => 'Filtros',
        'sort' => 'Ordenar por',
        'sort_trend' => 'Em alta',
        'sort_relevance' => 'Relevância',
        'sort_newest' => 'Mais recentes',
        'sort_top' => 'Mais votados',
        'period' => 'Período do "em alta"',
        'period_day' => 'Hoje',
        'period_week' => 'Esta semana',
        'period_month' => 'Este mês',
        'period_year' => 'Este ano',
        'build' => 'Build do jogo',
        'category' => 'Categoria',
    ],

    'modals' => [
        'remove_heading' => 'Remover mod',
        'remove_description' => 'Remover ":title" da sua lista? Os arquivos continuam baixados; ele só deixa de carregar depois que você salvar.',
        'import_ini_heading' => 'Importar configuração atual',
        'import_ini_description' => 'Lê os mods que o servidor já usa e monta a lista a partir deles. O que já está na sua lista é mantido.',
        'apply_heading' => 'Salvar a lista de mods no servidor?',
        'apply_description' => ':enabled mods ativos (de :total na lista) serão gravados na configuração do servidor. As mudanças entram em vigor na próxima reinicialização.',
        'restart_heading' => 'Reiniciar o servidor?',
        'restart_description' => 'Quem estiver jogando agora vai ser desconectado. Um servidor com mods leva alguns minutos pra voltar.',
    ],

    'notifications' => [
        'already_in_list' => 'Esse mod já está na sua lista',
        'added' => 'Mod adicionado',
        'added_body' => ':title — Mod IDs: :ids',
        'added_no_ids' => 'Adicionado, mas sem Mod ID detectado ainda',
        'added_no_ids_body' => 'Não consegui identificar o Mod ID automaticamente. Depois da próxima reinicialização do servidor, use ":rescan" que ele é detectado pelos arquivos baixados.',
        'invalid_url' => 'Isso não parece uma URL ou ID da workshop',
        'steam_error' => 'Não consegui falar com a Steam',
        'steam_error_body' => 'A Steam não respondeu. Espera um instante e tenta de novo.',
        'collection_empty' => 'Coleção vazia ou não encontrada',
        'collection_imported' => 'Coleção importada: :count mods adicionados',
        'ini_no_items' => 'Seu servidor ainda não tem mods da workshop configurados',
        'ini_imported' => 'Importados :count mods da configuração do servidor',
        'ini_imported_extras' => ' (:count Mod IDs sem item correspondente também foram preservados)',
        'ids_updated' => 'Mod IDs atualizados',
        'rescan_empty' => 'Nada encontrado no disco ainda',
        'rescan_empty_body' => 'O servidor ainda não baixou esse mod — reinicia ele uma vez e tenta de novo.',
        'rescan_found' => 'Mod IDs encontrados: :ids',
        'removed' => 'Removido da sua lista',
        'applied' => 'Lista de mods salva!',
        'applied_body' => ':workshop mods com :ids Mod IDs configurados. Reinicie o servidor quando quiser colocar no ar.',
        'apply_failed' => 'Não consegui salvar a lista',
        'search_unavailable' => 'Busca indisponível',
        'search_needs_key' => 'A busca no painel precisa de uma chave da Steam API. Um administrador pode configurar em Admin → Plugins → Zomboid Workshop Mods.',
        'restart_sent' => 'Reiniciando… o servidor volta em alguns minutos',
        'restart_failed' => 'Não consegui reiniciar o servidor',
    ],

    'settings' => [
        'api_key' => 'Chave da Steam Web API',
        'api_key_help' => 'Necessária só para a busca no painel. Crie a sua em steamcommunity.com/dev/apikey.',
        'nav_sort' => 'Posição no menu',
        'saved' => 'Configurações salvas',
    ],
];
