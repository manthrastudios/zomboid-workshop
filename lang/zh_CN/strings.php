<?php

return [
    'nav_label' => '创意工坊模组',
    'title' => 'Steam创意工坊',

    'tabs' => [
        'mods' => '我的模组',
        'search' => '浏览创意工坊',
    ],

    'badges' => [
        'total' => '列表中',
        'active' => '已启用',
        'loose' => '未匹配的Mod ID',
    ],

    'columns' => [
        'mod' => '模组',
        'mod_ids' => 'Mod ID',
        'active' => '启用',
        'none_detected' => '尚未检测到',
        'workshop' => '创意工坊 :id',
    ],

    'row' => [
        'enable' => '启用',
        'disable' => '禁用',
        'move_up' => '上移（更早加载）',
        'move_down' => '下移（更晚加载）',
        'edit_ids' => '编辑Mod ID',
        'rescan' => '从已下载文件检测Mod ID',
        'remove' => '从列表移除',
        'add' => '添加',
        'in_list' => '已在列表中',
    ],

    'actions' => [
        'add_by_url' => '通过URL/ID添加',
        'import_collection' => '导入合集',
        'import_ini' => '导入当前配置',
        'apply' => '保存到服务器',
        'restart' => '重启服务器',
    ],

    'forms' => [
        'url_label' => '创意工坊URL或ID',
        'collection_label' => '合集URL或ID',
        'selected_label' => '检测到的Mod ID（勾选的将被加载）',
        'manual_label' => '手动添加Mod ID',
        'manual_placeholder' => '输入后按回车',
    ],

    'filters' => [
        'button' => '筛选',
        'sort' => '排序方式',
        'sort_trend' => '热门',
        'sort_relevance' => '相关性',
        'sort_newest' => '最新',
        'sort_top' => '好评最多',
        'period' => '热门时间段',
        'period_day' => '今天',
        'period_week' => '本周',
        'period_month' => '本月',
        'period_year' => '今年',
        'build' => '游戏版本',
        'category' => '分类',
    ],

    'modals' => [
        'remove_heading' => '移除模组',
        'remove_description' => '将“:title”从列表中移除？文件仍保留在服务器上，保存后它只是不再加载。',
        'import_ini_heading' => '导入当前配置',
        'import_ini_description' => '读取服务器已在使用的模组并据此生成列表。已在列表中的内容将被保留。',
        'apply_heading' => '将模组列表保存到服务器？',
        'apply_description' => '将把:enabled个已启用的模组（列表共:total个）写入服务器配置。更改将在下次重启后生效。',
        'restart_heading' => '重启服务器？',
        'restart_description' => '当前在线的玩家将被断开连接。带模组的服务器需要几分钟才能恢复。',
    ],

    'notifications' => [
        'already_in_list' => '该模组已在你的列表中',
        'added' => '模组已添加',
        'added_body' => ':title — Mod ID: :ids',
        'added_no_ids' => '已添加，但尚未检测到Mod ID',
        'added_no_ids_body' => '无法自动识别Mod ID。下次服务器重启后，使用“:rescan”即可从已下载文件中检测。',
        'invalid_url' => '这似乎不是创意工坊的URL或ID',
        'steam_error' => '无法连接Steam',
        'steam_error_body' => 'Steam没有响应。请稍等片刻后重试。',
        'collection_empty' => '合集为空或未找到',
        'collection_imported' => '合集已导入：新增:count个模组',
        'ini_no_items' => '你的服务器尚未配置创意工坊模组',
        'ini_imported' => '已从服务器配置导入:count个模组',
        'ini_imported_extras' => '（另有:count个无对应条目的Mod ID也已保留）',
        'ini_imported_disabled' => ' — :count mod(s) switched off (not on the server)',
        'ids_updated' => 'Mod ID已更新',
        'rescan_empty' => '磁盘上尚未找到任何内容',
        'rescan_empty_body' => '服务器还没有下载该模组——重启一次后再试。',
        'rescan_found' => '找到的Mod ID：:ids',
        'removed' => '已从列表移除',
        'applied' => '模组列表已保存！',
        'applied_body' => '已配置:workshop个模组、:ids个Mod ID。准备好后重启服务器即可生效。',
        'apply_failed' => '无法保存列表',
        'search_unavailable' => '搜索不可用',
        'search_needs_key' => '面板内搜索需要Steam API密钥。管理员可在 Admin → Plugins → Zomboid Workshop Mods 中配置。',
        'restart_sent' => '正在重启……服务器几分钟后恢复',
        'restart_failed' => '无法重启服务器',
    ],

    'settings' => [
        'api_key' => 'Steam Web API密钥',
        'api_key_help' => '仅在面板内搜索时需要。前往 steamcommunity.com/dev/apikey 获取。',
        'nav_sort' => '菜单位置',
        'saved' => '设置已保存',
    ],
];
