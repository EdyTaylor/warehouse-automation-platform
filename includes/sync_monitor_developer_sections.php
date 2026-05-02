    <details class="card integration-section" id="sec-sync-master">
        <summary class="integration-section-summary">Пауза всей синхронизации с Битрикс24</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Включите перед массовой очисткой таблиц (товары, рулоны, заявки B24, движения). После работы обязательно выключите,
                иначе склад и портал перестанут обмениваться данными. Обновление справочника воронок (только чтение из Б24) при паузе по-прежнему работает.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="save_sync_master_switch">
                <label style="display:flex;gap:10px;align-items:flex-start;max-width:42rem;cursor:pointer;">
                    <input type="checkbox" name="integration_all_sync_paused" value="1" <?= $integrationSyncPaused ? 'checked' : '' ?> style="margin-top:4px;">
                    <span><strong>Отключить синхронизацию</strong> — вебхуки Б24 без записи в <code>webhook_log</code>; cron/импорт и исходящие <code>sendToBitrix</code> блокируются. Через склад, дашборд и <code>add_stock</code> рулоны не добавить. Приход документом (один файл прихода + рулоны) при паузе создаётся <em>только если</em> включена галочка ниже и используется режим «только локально».</span>
                </label>
                <label style="display:flex;gap:10px;align-items:flex-start;max-width:42rem;cursor:pointer;margin-top:14px;">
                    <input type="checkbox" name="integration_allow_local_receipt_during_pause" value="1" <?= $integrationAllowLocalReceiptDuringPause ? 'checked' : '' ?> style="margin-top:4px;">
                    <span><strong>Разрешить локальный приход при паузе</strong> — можно проводить приход с <code>local_only</code> (форма «Только локально», JSON; раздел ниже для разработчиков). Без этой галочки при паузе новые рулоны из прихода не создаются.</span>
                </label>
                <p style="margin-top:12px;">
                    <button class="btn btn-warning" type="submit">Сохранить переключатель</button>
                </p>
            </form>
            <p class="text-muted" style="margin-top:12px;">
                Долгий приход один раз считает «номер конфигурации» (сейчас <strong><?= (int)$integrationStockAbortEpoch ?></strong>).
                Любое сохранение блока выше увеличивает номер и <strong>откатывает</strong> ещё выполняющийся приход (ролбэк всех уже созданных в этой транзакции рулонов этого запуска).
            </p>
            <form method="POST" style="margin-top:10px;">
                <input type="hidden" name="action" value="interrupt_running_receipt">
                <button class="btn btn-danger" type="submit">Только прервать выполняющийся приход</button>
                <span class="text-muted" style="margin-left:10px;">Без смены галочек паузы — только остановить «залипший» прогон.</span>
            </form>
            <div class="alert alert-secondary" role="note" style="margin-top:16px;">
                <strong>Жёсткий стоп создания рулонов:</strong> без FTP можно включить блокировку в базе —
                приложение перестанет создавать рулоны через приход (в т.ч. форму «Приход из JSON» ниже, даже если <code>api/create_receipt_json.php</code> удалён или переименован), склад, дашборд, конфликты продаж.
                <form method="POST" style="margin-top:10px;display:flex;align-items:center;flex-wrap:wrap;gap:10px;">
                    <input type="hidden" name="action" value="save_emergency_roll_block">
                    <label style="display:flex;gap:8px;align-items:center;cursor:pointer;">
                        <input type="checkbox" name="db_emergency_block_roll_creates" value="1" <?= $dbEmergencyRollBlockOn ? 'checked' : '' ?>>
                        Запретить создание новых рулонов (ключ <code>emergency_block_roll_creates</code> в app_settings)
                    </label>
                    <button type="submit" class="btn btn-outline-danger btn-sm">Сохранить</button>
                </form>
                <p class="text-muted" style="margin-top:12px;margin-bottom:0;">
                    По FTP: положите пустой файл <code>STOCK_CREATES_OFF</code> или <code>STOCK_CREATES_OFF.txt</code> рядом с <code>index.php</code> — действует тем же кодом приоритетно перед проверкой БД.
                    Уберите файл/галочку, когда нужно продолжить приходы.
                </p>
            </div>
        </div>
    </details>

    <details class="card integration-section" id="sec-bulk-receipt">
        <summary class="integration-section-summary">Массовый приход из прайса (Llumar / большой JSON)</summary>
        <div class="integration-section-body">
            <p class="text-muted" style="margin-top:0;">
                Один загружаемый <strong>.json</strong> в приложении даёт <strong>один документ прихода</strong> (<code>stock_operation_docs</code>):
                в нём много строк номенклатуры (<code>stock_operation_lines</code>) и отдельная запись в <code>rolls</code> на каждый рулон — все они ссылаются на этот документ (<code>receipt_doc_id</code>).
                Это не «много файлов прихода», а одна операция оприходования, пока вы не отправите другой файл или не смените <code>doc_number</code>.
            </p>
            <p class="text-muted">
                Если в JSON указан свой <code>doc_number</code> (например <code>PR-LLUMAR-BULK-2026-05-01</code>), повторная отправка <strong>того же номера и того же уже проведённого прихода</strong> будет пропущена как дубликат.
                Если <code>doc_number</code> пустой, номер считается от хеша тела запроса — повтор точно того же POST тоже идемпотентен.
            </p>
            <ol style="margin:12px 0;padding-left:1.35rem;line-height:1.55;">
                <li><strong>Сгенерировать JSON</strong> на ПК: в репозитории <code>example/new/build_products_prices_usd_x88.js</code> (Node.js) → файл вида <code>bulk_receipt_from_llumar.generated.json</code>.</li>
                <li><strong>Товары в Б24:</strong> заранее импорт из каталога (<a href="sync_monitor.php#sec-quick">«Импортировать товары из Б24» на странице «Настройки»</a>), чтобы совпали <code>b24_product_id</code> из JSON.</li>
                <li>
                    <strong>Запустить приход:</strong>
                    <a class="btn btn-primary btn-sm" href="sync_monitor_developers.php?bulk=1#sec-receipt-json" style="margin-left:8px;">Форма прихода с «Только локально» по умолчанию</a>
                    <span class="text-muted">В форме с <code>?bulk=1</code> стоят <strong>чанки</strong> (меньшие партии строк/рулонов на документ — меньше 504 и разрыва MySQL <code>server has gone away</code>). При ошибке 2006 уменьшайте оба числа или повторите — уже созданные части идемпотентны по <code>doc_number</code>.</span>
                    Через API то же самое — в корне JSON: <code>&quot;lines_per_chunk&quot;: 30</code>,
                    опционально <code>&quot;max_roll_units_per_chunk&quot;: 400</code>. Склад в Б24 после <code>local_only</code> можно подтянуть «Синхронизировать остатки».
                </li>
                <li>Снимите аварийные блокировки рулонов (флаг в БД / триггер / <code>STOCK_CREATES_OFF</code>), если включали для остановки дублей.</li>
                <li>
                    Один запрос, дождитесь сообщения об успехе. Если вкладка «крутится» долго без ответа: откройте эту вкладку <strong>Разработчикам</strong> в другом окне и нажмите
                    «<strong>Прервать выполняющийся приход</strong>» в блоке паузы — долгая транзакция откатится (ещё несколько секунд).
                    При обрыве по 504 проверьте в БД, создался ли документ прихода; не отправляйте второй раз с тем же <code>doc_number</code>, если первая попытка уже прошла.
                </li>
            </ol>
            <p class="text-muted" style="margin-bottom:0;">
                Альтернатива браузеру для очень больших файлов — тот же JSON через <code>POST api/create_receipt_json.php</code> (заголовок секрета, при необходимости <code>&quot;local_only&quot;: true</code> в теле): лимиты задаёт <code>upload_max_filesize</code> / прокси хостинга.
            </p>
        </div>
    </details>

    <details class="card integration-section" id="sec-receipt-json"<?= $bulkReceiptUiDefault ? ' open' : '' ?>>
        <summary class="integration-section-summary">Приход из JSON (без Postman)</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Формат как у <code>api/create_receipt_json.php</code>. По умолчанию локальный документ и синхронизация с Битрикс24.
                Для большого прихода включите ниже опцию «только локально» — один документ в приложении без вызовов Б24 (меньше вероятность 504 и дробления из‑за повторов после таймаута).
                В JSON можно добавить ключ <code>&quot;local_only&quot;: true</code>; галочка тоже задаёт режим локально только.
                При <strong>паузе синхронизации</strong> приход создаёт рулоны только если в блоке «Пауза» включено <strong>«Разрешить локальный приход при паузе»</strong> и здесь отмечено «только локально» (или в JSON есть <code>local_only</code>).
                Если у локальной позиции нет привязки к Битриксу (<code>b24_product_id</code>), перед созданием новой карточки приложение ищет в CRM товар с <strong>тем же именем</strong>, чтобы не плодить дубликаты; отключить: в <code>app_settings</code> ключ <code>stock_receipt_link_b24_by_exact_name</code> = <code>0</code>.
                Отдельно суффикс <strong>[stock]</strong>: раньше создавался при несовпадении типа товара в CRM со складским. Сейчас по умолчанию <strong>клоны отключены</strong> (<code>app_settings</code>): <code>stock_b24_clone_on_type_mismatch</code>=<code>0</code>; включить старое поведение → <code>1</code>. При проведении документа клон через «Неверный тип товара» выключен: <code>stock_b24_conduct_stock_clone_fallback</code>=<code>0</code> (включить → <code>1</code>).
                Розничную <strong>PRICE</strong> в карточке CRM при приходе по умолчанию <strong>не трогаем</strong> (<code>stock_receipt_push_crm_catalog_price</code>=<code>0</code>) — иначе закуп за метр подменял каталожную цену. Пушить цену из прихода снова → <code>1</code>.
                Имя <strong>NAME</strong> в CRM при приходе по умолчанию <strong>не перезаписывается</strong> (<code>stock_receipt_push_crm_catalog_name</code>=<code>0</code>); включить явный пуш названия из приложения → <code>1</code>. Перед большим импортом можно сделать бэкап имён каталога: CLI <code>php example/product_names_snapshot_cli.php snapshot МЕТКА</code> и при необходимости <code>restore-latest МЕТКА</code>.
                Имена из JSON LLumar (поле <code>product_name</code>) можно одним проходом применить к каталогу: <code>php example/sync_product_names_from_bulk_receipt_json_cli.php exec</code> и при необходимости <code>--push-b24</code>.
            </p>
            <?php if ($bulkReceiptUiDefault): ?>
                <div class="alert alert-info">
                    Открыто в режиме <strong>массового прихода</strong> (<code>?bulk=1</code>): галочка «Только локально» включена по умолчанию. Снимите её, если нужен один документ сразу в Битрикс24 (риск таймаута на большом файле).
                </div>
            <?php endif; ?>
            <?php if ($stockReceiptSecretStored === ''): ?>
                <div class="alert alert-warning">Секрет прихода ещё не задан — заполните блок «Склады, курс, лимиты и секрет JSON-прихода» выше на этой странице.</div>
            <?php endif; ?>
            <form id="integration-receipt-json-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="run_stock_receipt_json">
                <div class="form-group">
                    <label>Файл JSON</label>
                    <input class="input" type="file" name="receipt_json_file" accept=".json,application/json">
                </div>
                <div class="form-group">
                    <label>Или вставьте JSON целиком</label>
                    <textarea class="input" name="receipt_json_paste" rows="10" style="width:100%;max-width:100%;font-family:monospace;font-size:12px;" placeholder="{ &quot;doc_number&quot;: &quot;…&quot;, &quot;lines&quot;: [ … ] }"></textarea>
                </div>
                <div class="form-group">
                    <label>Секрет (<code>stock_receipt_api_secret</code>)</label>
                    <input class="input" type="password" name="receipt_run_secret" autocomplete="off" style="max-width:28rem;"
                        <?= $stockReceiptSecretStored !== '' ? 'required' : '' ?>>
                </div>
                <div class="form-group">
                    <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;">
                        <input type="checkbox" name="receipt_local_only" value="1" <?= $bulkReceiptUiDefault ? 'checked' : '' ?> style="margin-top:4px;">
                        <span><strong>Только локально</strong> — не звать Битрикс24 при этом приходе (<code>local_only</code>).</span>
                    </label>
                </div>
                <div class="form-group" style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-end;">
                    <div>
                        <label>Чанки: строк <code>lines</code> на один документ прихода</label>
                        <input class="input" type="number" name="receipt_lines_per_chunk" min="0" max="200" step="1"
                            value="<?= $bulkReceiptUiDefault ? '22' : '0' ?>"
                            title="0 — один документ на весь JSON (как раньше). 15–25 — безопаснее для Beget (MySQL gone away на длинном одном сеансе).">
                        <div class="text-muted" style="font-size:0.88rem;margin-top:4px;">или в JSON ключ <code>lines_per_chunk</code></div>
                    </div>
                    <div>
                        <label>Макс. сумма <code>qty_rolls</code> в партии</label>
                        <input class="input" type="number" name="receipt_max_roll_units" min="0" max="20000" step="1"
                            value="<?= $bulkReceiptUiDefault ? '280' : '0' ?>"
                            title="0 при ненулевых чанках = дефолт ~400 и дробление длинной строки. На Beget ставьте 200–320 при ошибке gone away / 504.">
                        <div class="text-muted" style="font-size:0.88rem;margin-top:4px;">или <code>max_roll_units_per_chunk</code></div>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit" id="integration-receipt-json-submit" <?= $stockReceiptSecretStored === '' ? 'disabled' : '' ?>>Запустить приход</button>
            </form>
            <p class="text-muted" style="margin-top:10px;font-size:0.9rem;">
                Если <strong>«Чанки: строк…» &gt; 0</strong>, приход в браузере идёт <strong>по одной части за раз</strong> (как модалка у «Импортировать товары из Б24»): виден прогресс, короче один HTTP-ответ, меньше 504 и <code>MySQL server has gone away</code>.
                Значение <strong>0</strong> — один запрос через эту же форму на сервер (без пошаговой модалки).
            </p>
            <p class="text-muted" style="margin-top:10px;font-size:0.9rem;">
                Ограничение размера файла задаётся в PHP (<code>upload_max_filesize</code> / <code>post_max_size</code> на сервере). Большие приходы удобнее грузить через тот же JSON по API после настройки HTTPS.
            </p>
        </div>
    </details>

    <details class="card integration-section" id="sec-tech">
        <summary class="integration-section-summary">Технический раздел Б24</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Сервисные операции интеграции: ручной резерв, синк product rows, диагностика. Для ежедневной работы —
                <strong>Место кладовщика</strong>.
            </p>
            <a class="btn btn-light" href="b24_sales.php">Открыть тех.раздел Б24</a>
        </div>
    </details>

    <details class="card integration-section" id="sec-funnels" open>
        <summary class="integration-section-summary">Воронки и стадии из Битрикс24</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Актуальные названия воронок (CATEGORY_ID), идентификаторы стадий (<code>STATUS_ID</code> в сделке —
                <code>STAGE_ID</code>) и подписи. Данные кэшируются в БД; обновите после изменений в Б24.
            </p>
            <form method="POST" style="margin-bottom:12px;">
                <input type="hidden" name="action" value="refresh_b24_funnels">
                <button class="btn btn-primary" type="submit">Обновить справочник из Б24</button>
            </form>
            <?php if ($funnelFetchedAt !== ''): ?>
                <p><strong>Последнее обновление:</strong> <?= htmlspecialchars($funnelFetchedAt) ?>
                <?php if ($funnelSnap !== null && !empty($funnelSnap['category_source'])): ?>
                    <span class="text-muted">(источник воронок: <?= htmlspecialchars((string)$funnelSnap['category_source']) ?>)</span>
                <?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if (count($funnelCats) === 0): ?>
                <div class="alert alert-warning">Справочник пуст. Нажмите «Обновить справочник из Б24» (нужен рабочий вебхук в <code>api/bitrix/config.php</code>).</div>
            <?php else: ?>
                <?php foreach ($funnelCats as $fcat): ?>
                    <?php
                    if (!is_array($fcat)) {
                        continue;
                    }
                    $fcid = isset($fcat['id']) ? (int)$fcat['id'] : 0;
                    $fname = isset($fcat['name']) ? (string)$fcat['name'] : ('Воронка #' . $fcid);
                    $fentity = isset($fcat['entity_id']) ? (string)$fcat['entity_id'] : '';
                    $fstages = isset($fcat['stages']) && is_array($fcat['stages']) ? $fcat['stages'] : array();
                    ?>
                    <details style="margin:10px 0;border:1px solid var(--border-color);border-radius:6px;padding:4px 10px;">
                        <summary style="cursor:pointer;font-weight:600;">
                            <?= htmlspecialchars($fname) ?> — <span class="text-muted">CATEGORY_ID = <?= (int)$fcid ?></span>
                            <?php if ($fentity !== ''): ?>
                                · <code><?= htmlspecialchars($fentity) ?></code>
                            <?php endif; ?>
                        </summary>
                        <?php if (count($fstages) === 0): ?>
                            <p class="text-muted">Стадий не получено.</p>
                        <?php else: ?>
                            <table class="integration-funnel-stage-table">
                                <tr>
                                    <th>Название</th>
                                    <th>STATUS_ID</th>
                                    <th>Семантика</th>
                                </tr>
                                <?php foreach ($fstages as $st): ?>
                                    <?php if (!is_array($st)) { continue; } ?>
                                    <tr>
                                        <td><?= htmlspecialchars(isset($st['NAME']) ? (string)$st['NAME'] : '') ?></td>
                                        <td><code><?= htmlspecialchars(isset($st['STATUS_ID']) ? (string)$st['STATUS_ID'] : '') ?></code></td>
                                        <td><?= htmlspecialchars(isset($st['SEMANTICS']) ? (string)$st['SEMANTICS'] : '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>

    <details class="card integration-section" id="sec-workflow" open>
        <summary class="integration-section-summary">Резерв (очередь кладовщика) и реализация (списание)</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                <strong>Резерв</strong> — когда вебхук ставит сделку в очередь <code>b24_sale_requests</code> (см. <code>api/webhook.php</code>).
                <strong>Реализация</strong> — когда сделка считается оплаченной/завершённой: статус заявки <code>completed</code>,
                проводки <code>sale_meter</code> «Сделка оплачена в Б24». Если включить фильтр реализации, используются только отмеченные стадии;
                если выключить — сохраняется прежняя логика (семантика успеха и известные <code>WON</code> / <code>FINAL_INVOICE</code> и т.д.).
            </p>
            <?php if ($storedReserveRaw === ''): ?>
                <p class="text-muted">Переопределения резерва в БД ещё не сохранялись — действуют правила из <code>api/bitrix/config.php</code> (<code>warehouse_queue</code>).</p>
            <?php endif; ?>
            <?php if ($storedRealRaw === ''): ?>
                <p class="text-muted">Переопределения реализации в БД ещё не сохранялись — действует блок <code>warehouse_realization</code> из config или эвристика по умолчанию.</p>
            <?php endif; ?>
            <?php if (count($funnelCats) === 0): ?>
                <div class="alert alert-warning">Сначала обновите справочник воронок выше — иначе нет списка стадий для галочек.</div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="save_workflow_gates">
                <p style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;">
                    <label style="display:inline-flex;gap:8px;align-items:center;">
                        <input type="checkbox" name="reserve_filter_enabled" value="1" <?= !empty($reserveGateMerged['filter_enabled']) ? 'checked' : '' ?>>
                        Ограничить <strong>резерв</strong> выбранными стадиями
                    </label>
                    <label style="display:inline-flex;gap:8px;align-items:center;">
                        <input type="checkbox" name="realization_filter_enabled" value="1" <?= !empty($realGateMerged['filter_enabled']) ? 'checked' : '' ?>>
                        Задавать <strong>реализацию</strong> только выбранными стадиями
                    </label>
                </p>
                <?php foreach ($funnelCats as $fcat): ?>
                    <?php
                    if (!is_array($fcat)) {
                        continue;
                    }
                    $fcid = isset($fcat['id']) ? (int)$fcat['id'] : 0;
                    $fname = isset($fcat['name']) ? (string)$fcat['name'] : ('Воронка #' . $fcid);
                    $fstages = isset($fcat['stages']) && is_array($fcat['stages']) ? $fcat['stages'] : array();
                    ?>
                    <fieldset style="border:1px solid var(--border-color);border-radius:8px;padding:10px;margin:12px 0;">
                        <legend><strong><?= htmlspecialchars($fname) ?></strong> <span class="text-muted">(<?= (int)$fcid ?>)</span></legend>
                        <?php if (count($fstages) === 0): ?>
                            <p class="text-muted">Нет стадий в кэше.</p>
                        <?php else: ?>
                            <table class="integration-funnel-stage-table">
                                <tr>
                                    <th>Стадия</th>
                                    <th>STATUS_ID</th>
                                    <th>Резерв</th>
                                    <th>Реализация</th>
                                </tr>
                                <?php foreach ($fstages as $st): ?>
                                    <?php
                                    if (!is_array($st)) {
                                        continue;
                                    }
                                    $sid = isset($st['STATUS_ID']) ? (string)$st['STATUS_ID'] : '';
                                    if ($sid === '') {
                                        continue;
                                    }
                                    $sname = isset($st['NAME']) ? (string)$st['NAME'] : '';
                                    $rOn = isset($reserveStageMap[$fcid][$sid]);
                                    $zOn = isset($realStageMap[$fcid][$sid]);
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($sname) ?></td>
                                        <td><code><?= htmlspecialchars($sid) ?></code></td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="reserve_stages[<?= (int)$fcid ?>][]" value="<?= htmlspecialchars($sid) ?>" <?= $rOn ? 'checked' : '' ?>>
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="checkbox" name="realization_stages[<?= (int)$fcid ?>][]" value="<?= htmlspecialchars($sid) ?>" <?= $zOn ? 'checked' : '' ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </fieldset>
                <?php endforeach; ?>
                <button class="btn btn-success" type="submit">Сохранить правила</button>
            </form>
        </div>
    </details>

    <details class="card integration-section" id="sec-autosync">
        <summary class="integration-section-summary">Автосинхронизация и контроль расхождений</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Рекомендуется запускать <code>api/bitrix/sync_cycle.php?chunk=<?= (int)$integrationSettings['sync_cycle_chunk'] ?></code> по cron каждые 2-5 минут.
                Цикл постепенно отправляет остатки/цены в Б24 и периодически проверяет изменения в Б24 на расхождения.
            </p>
            <?php if ($cycleLastRun !== ''): ?>
                <p><strong>Последний результат цикла:</strong></p>
                <pre style="white-space:pre-wrap;"><?= htmlspecialchars($cycleLastRun) ?></pre>
            <?php else: ?>
                <p class="text-muted">Цикл еще не запускался.</p>
            <?php endif; ?>
        </div>
    </details>

    <details class="card integration-section" id="sec-webhooks">
        <summary class="integration-section-summary">Вебхук-события Битрикс24</summary>
        <div class="integration-section-body">
            <p class="text-muted">
                Каждая строка — один POST от исходящего вебхука Б24 на <code>api/webhook.php</code>.
                Повторная доставка того же события помечается как <strong>duplicate_delivery_skipped</strong> (видно здесь же).
                Размер: <code>?limit=120</code> в адресной строке (до 500).
                Колонка <strong>Товар B24</strong>: для <code>ONCRMPRODUCT*</code> — из тела вебхука; для <code>ONCRMDEAL*</code> может подставиться
                первый каталожный <code>PRODUCT_ID</code>, если строки успешно загружены по REST после события. Итог обработки очереди/ошибки — в <strong>Итог обработки</strong>.
            </p>
            <?php if (empty($webhookRows)): ?>
                <div class="alert alert-warning">
                    Записей пока нет. Проверьте:<br>
                    • URL вебхука в Битрикс24 точно <code>http(s)://ваш-хост/api/webhook.php</code> и включены события <code>ONCRMDEALADD</code> / <code>ONCRMDEALUPDATE</code>.<br>
                    • JSON-диагностика БД без Битрикс: откройте <a href="api/webhook_ping.php" target="_blank" rel="noopener"><code>api/webhook_ping.php</code></a> — должен быть <code>webhook_log_rows</code> и при необходимости тестовая строка: <code>api/webhook_ping.php?write=1&amp;k=CHANGE_ME_FRIENDCRM_DIAG</code> (ключ задаётся в <code>api/webhook_ping.php</code>).<br>
                    • Если ping показывает строки, а после сделки их нет — до сайта из облака Б24 не добираются запросы (URL, HTTPS, блокировки).
                </div>
            <?php endif; ?>
            <div class="webhook-log-table-wrap" style="max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;box-sizing:border-box;">
            <table class="table webhook-log-table" style="margin-bottom:0;">
                <tr>
                    <th>ID</th>
                    <th>Событие</th>
                    <th>Итог обработки</th>
                    <th>Сделка</th>
                    <th>Товар B24</th>
                    <th>Payload</th>
                    <th>Время</th>
                </tr>
                <?php foreach ($webhookRows as $row): ?>
                    <?php
                    $wid = (int)$row['id'];
                    $snippet = '';
                    $rawPrev = isset($row['data_preview']) ? trim((string)$row['data_preview']) : '';
                    if ($rawPrev !== '') {
                        $decoded = json_decode($rawPrev, true);
                        if (function_exists('json_last_error') && json_last_error() === JSON_ERROR_NONE) {
                            $snippet = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                        if ($snippet === '') {
                            $snippet = $rawPrev;
                        }
                        if (strlen($snippet) > 2000) {
                            $snippet = substr($snippet, 0, 2000) . '…';
                        }
                    }
                    ?>
                    <tr class="webhook-event-row">
                        <td><?= $wid ?></td>
                        <td><code><?= htmlspecialchars((string)$row['event']) ?></code></td>
                        <td>
                            <?php
                            $hoc = (string)$row['handler_outcome'];
                            $hdl = isset($row['handler_detail']) ? trim((string)$row['handler_detail']) : '';
                            if ($hoc !== '') {
                                echo '<code>' . htmlspecialchars($hoc) . '</code>';
                                if ($hdl !== '') {
                                    echo '<details style="margin-top:6px;max-width:100%;"><summary style="cursor:pointer;font-size:12px;color:var(--text-muted,#6c757d);">Детали ошибки / пояснение</summary>'
                                        . '<div style="margin-top:6px;max-width:100%;overflow-x:auto;"><pre style="margin:0;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;font-size:11px;padding:8px;background:var(--card-background,#f1f3f5);border-radius:4px;border:1px solid rgba(127,127,127,0.25);">'
                                        . htmlspecialchars($hdl)
                                        . '</pre></div></details>';
                                }
                            } else {
                                echo '<span class="text-muted">—</span>';
                            }
                            ?>
                        </td>
                        <td><?= isset($row['entity_deal_id']) && intval($row['entity_deal_id']) > 0 ? intval($row['entity_deal_id']) : '—' ?></td>
                        <td><?= isset($row['entity_product_id']) && intval($row['entity_product_id']) > 0 ? intval($row['entity_product_id']) : '—' ?></td>
                        <td><?= isset($row['data_chars']) ? intval($row['data_chars']) . ' симв.' : '—' ?></td>
                        <td><?= htmlspecialchars((string)$row['created_at']) ?></td>
                    </tr>
                    <?php if ($snippet !== ''): ?>
                    <tr class="webhook-json-row">
                        <td colspan="7" style="max-width:100%;vertical-align:top;">
                            <details>
                                <summary style="cursor:pointer;">Показать тело события (до 1500 симв.; форматирование JSON)</summary>
                                <div style="max-width:100%;margin-top:8px;overflow-x:auto;overflow-y:hidden;box-sizing:border-box;">
                                    <pre style="margin:0;white-space:pre-wrap;overflow-wrap:anywhere;word-wrap:break-word;word-break:break-word;font-size:12px;padding:10px;background:var(--card-background,#f8f9fa);border-radius:6px;border:1px solid rgba(127,127,127,0.2);"><?= htmlspecialchars($snippet) ?></pre>
                                </div>
                            </details>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
            </div>
        </div>
    </details>
