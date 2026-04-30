# B24 Funnel Bot Runbook

Цель: исключить потерю логики при длинных чатах и держать единый источник правил для воронки, ботов и склада.

## 1) Обязательные поля сделки

- `UF_CRM_STOCK_MODE` — режим: `reserve` / `sale`
- `UF_CRM_STOCK_STATUS` — складской статус: `new`, `reserved`, `picked`, `shipped`, `completed`, `cancelled`
- `UF_CRM_STOCK_REQUIRED_AT` — дата/время плановой отгрузки
- `UF_CRM_STOCK_WAREHOUSE_NOTE` — комментарий кладовщика
- `UF_CRM_STOCK_REQUEST_ID` — ID заявки в `b24_sale_requests` (идемпотентность)

## 2) Этапы воронки и складские статусы

1. Новая заявка -> `new`
2. Резерв подтвержден -> `reserved`
3. Подбор завершен -> `picked`
4. Отгружено -> `shipped`
5. Закрыто успешно -> `completed`
6. Отмена -> `cancelled`

## 3) Правила webhook-обработки

- На входящем webhook по сделке:
  - апсертом создать/обновить запись в `b24_sale_requests`
  - строки сделки обновлять в `b24_sale_lines`
  - использовать `UF_CRM_STOCK_REQUEST_ID` для идемпотентности

- При статусе `reserved`:
  - зарезервировать рулоны
  - обновить `rolls.reserved`, `rolls.deal_id`, `rolls.reserved_length`

- При статусе `shipped`:
  - списать фактические метры/рулоны
  - записать движения склада
  - синхронизировать строки сделки в B24 (`crm.deal.productrows.set/get` + verify)

- При статусе `cancelled`:
  - release резерва
  - очистить `reserved/deal_id/reserved_length`

## 4) UI-правило по разделам

- Операционная работа кладовщика -> `warehouse_orders.php`
- Технические ручные операции интеграции -> `b24_sales.php` (вход через `sync_monitor.php`)
- Отчет по фактическим продажам -> `sell.php`

## 5) Проверка перед релизом

- `sync_stock.php?push=1` проходит батчами без 504
- `sync_prices.php?action=to_b24` проходит батчами без таймаута
- Строки сделки после отгрузки совпадают в приложении и B24
- Отмена корректно снимает резерв

