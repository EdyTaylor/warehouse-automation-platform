<?php

// Fill these values for your Bitrix24 portal.
// Keep methods configurable because portals may differ by enabled modules.
return [
    // Universal CRM webhook (crm scope).
    'webhook' => 'https://llumar.bitrix24.kz/rest/13/s845opt8ba3jchft/',
    // Universal Catalog+CRM webhook (catalog + crm scopes).
    'catalog_webhook' => 'https://llumar.bitrix24.kz/rest/13/8l0ds7zlh54wl1ou/',

    // Optional method-specific overrides. Keep empty when using universal webhooks above.
    'method_urls' => [],

    // Product available stock field in Bitrix product catalog.
    // Example: UF_CRM_STOCK_M
    'product_available_field' => 'UF_CRM_STOCK_M',
    'product_update_method' => 'crm.product.update',
    'product_list_method' => 'crm.product.list',
    
    // Restrict product sync to specific B24 catalogs.
    // Keep empty array to sync all catalogs.
    // Example: [23] to sync only "Товары" and exclude "Услуги".
    'sync_catalog_ids' => [],

    // Optional local labels for catalog tree rendering in products.php.
    // Example: 19 => 'Архитектурные', 20 => 'Авто'
    'catalog_labels' => [],

    // Where movement log is sent in Bitrix (usually timeline comment in deal).
    // If deal_id is missing, movement sync will be skipped.
    'movement_timeline_method' => 'crm.timeline.comment.add',

    // Очередь склада (api/webhook.php → queueDealForWarehouse): по умолчанию любая сделка с товарами.
    // Включите filter_enabled и задайте rules, чтобы заявки создавались только на нужных этапах воронок.
    // CATEGORY_ID и STAGE_ID возьмите из crm.deal.get или из карточки сделки (режим разработчика).
    //
    // Пример три воронок:
    //   'rules' => [
    //     ['category_ids' => [1], 'stages_exact' => ['C1:UC_READY_SERVICE']],           // продажа услуг → «Готовы»
    //     ['category_ids' => [2], 'stages_contains' => ['UC_INSPECTION']],              // выполнение — осмотр/работа
    //     ['category_ids' => [3], 'stages_exact' => ['C3:UC_PAID_OR_SHIP']],           // товары → оплачено/отгрузка
    //   ],
    'warehouse_queue' => [
        'filter_enabled' => false,
        'rules' => [],
    ],
];
