<?php

// Fill these values for your Bitrix24 portal.
// Keep methods configurable because portals may differ by enabled modules.
return [
    // Fallback base URL (used only if exact method URL is absent in method_urls).
    'webhook' => 'https://llumar.bitrix24.kz/rest/13/ip8r13jt88ilpbs2/',

    // Method-specific incoming webhooks provided by Bitrix24.
    'method_urls' => [
        'crm.product.update' => 'https://llumar.bitrix24.kz/rest/13/ip8r13jt88ilpbs2/crm.product.update.json',
        'crm.timeline.comment.add' => 'https://llumar.bitrix24.kz/rest/13/gmtqmb1w19gtcs5l/crm.timeline.comment.add.json',
        'crm.deal.get' => 'https://llumar.bitrix24.kz/rest/13/d58tgvvw264z07u5/crm.deal.get.json',
        'crm.deal.productrows.get' => 'https://llumar.bitrix24.kz/rest/13/eovptmewpkx5dt7u/crm.deal.productrows.get.json',
        'crm.deal.productrows.set' => 'https://llumar.bitrix24.kz/rest/13/ev7sa3pxlnh2tn8g/crm.deal.productrows.set.json',
        'crm.deal.update' => 'https://llumar.bitrix24.kz/rest/13/35eizcjklzg4egue/crm.deal.update.json',
        'crm.product.list' => 'https://llumar.bitrix24.kz/rest/13/xpkt5d6cug7jxoz1/crm.product.list.json'
    ],

    // Product available stock field in Bitrix product catalog.
    // Example: UF_CRM_STOCK_M
    'product_available_field' => 'UF_CRM_STOCK_M',
    'product_update_method' => 'crm.product.update',
    'product_list_method' => 'crm.product.list',
    
    // Restrict product sync to specific B24 catalogs.
    // Keep empty array to sync all catalogs.
    // Example: [23] to sync only "Товары" and exclude "Услуги".
    'sync_catalog_ids' => [],

    // Where movement log is sent in Bitrix (usually timeline comment in deal).
    // If deal_id is missing, movement sync will be skipped.
    'movement_timeline_method' => 'crm.timeline.comment.add'
];
