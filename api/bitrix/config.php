<?php

// Fill these values for your Bitrix24 portal.
// Keep methods configurable because portals may differ by enabled modules.
return [
    'webhook' => 'https://YOUR_DOMAIN.bitrix24.kz/rest/1/XXXXXXXX/',

    // Product available stock field in Bitrix product catalog.
    // Example: UF_CRM_STOCK_M
    'product_available_field' => 'UF_CRM_STOCK_M',
    'product_update_method' => 'crm.product.update',

    // Where movement log is sent in Bitrix (usually timeline comment in deal).
    // If deal_id is missing, movement sync will be skipped.
    'movement_timeline_method' => 'crm.timeline.comment.add'
];
