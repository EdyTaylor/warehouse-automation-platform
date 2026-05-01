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

    // Очередь склада (api/webhook.php → queueDealForWarehouse).
    //
    // Портал llumar.bitrix24.kz — справочно:
    //   CATEGORY_ID 0 — «Продажа услуг» (основная), ENTITY_ID DEAL_STAGE — STAGE_ID без префикса C0:.
    //   CATEGORY_ID 1 — «Выполнение услуг», ENTITY_ID DEAL_STAGE_1 — C1:…
    //   CATEGORY_ID 3 — «Продажа товаров», ENTITY_ID DEAL_STAGE_3 — C3:…
    //   Рассылка (5) — не включать здесь (отдельная автоматизация).
    //
    // Стадии «Готовы на услуги» = PREPAYMENT_INVOICE при CATEGORY_ID 0 (проверено crm.status.list).
    //
    // filter_enabled=false — заявка на склад при любом ONCRMDEALUPDATE с товарами.
    // filter_enabled=true — только если сделка в одной из rules (первая подошедшая).
    'warehouse_queue' => array(
        'filter_enabled' => true,
        'rules' => array(
            array(
                'category_ids' => array(0),
                'stages_exact' => array('PREPAYMENT_INVOICE'),
            ),
            // Раскомментируйте при необходимости:
            // array('category_ids' => array(1), 'stages_exact' => array('C1:NEW')),               // После туннеля «Записаны»
            // array('category_ids' => array(1), 'stages_exact' => array('C1:EXECUTING')),       // Выполнение — «В работе»
            // array('category_ids' => array(3), 'stages_exact' => array('C3:PREPAYMENT_INVOICE')), // Товары — «Оплачены»
            // array('category_ids' => array(3), 'stages_exact' => array('C3:EXECUTING')),         // Товары — «Отгружены»
        ),
    ),
];
