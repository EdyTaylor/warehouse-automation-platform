<footer class="footer" style="background: var(--primary-color); color: white; padding: 2rem 0; margin-top: 3rem;">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <p class="mb-0">© 2026 Склад пленок. Все права защищены.</p>
                    <p class="mb-0 text-muted" style="color: rgba(255,255,255,0.7) !important;">Версия 2.0 | Интеграция с Битрикс24</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="api/bitrix/sync_stock.php?push=1" class="btn btn-outline btn-sm" target="_blank">📤 Синхронизировать остатки</a>
                    <a href="api/sync_prices.php?action=to_b24" class="btn btn-outline btn-sm" target="_blank">💰 Синхронизировать цены</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Активная навигация
        document.addEventListener('DOMContentLoaded', function() {
            window.setUiTheme = function (theme) {
                var next = theme === 'dark' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                try { localStorage.setItem('ui_theme', next); } catch (_e) {}
            };

            document.body.addEventListener('click', function (e) {
                var btn = e.target.closest('.js-theme-toggle');
                if (!btn) return;
                e.preventDefault();
                var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                window.setUiTheme(current === 'dark' ? 'light' : 'dark');
            });

            var devToggleBtn = document.getElementById('friendcrm-dev-toggle');
            if (devToggleBtn && typeof window.setFriendcrmDevTools === 'function') {
                window.setFriendcrmDevTools(document.documentElement.getAttribute('data-dev-tools') === '1');
                devToggleBtn.addEventListener('click', function () {
                    var on = document.documentElement.getAttribute('data-dev-tools') === '1';
                    window.setFriendcrmDevTools(!on);
                    try {
                        if (!on && window.location && String(window.location.pathname).indexOf('sync_monitor.php') !== -1) {
                            var anchor = document.getElementById('friendcrm-dev-blocks');
                            if (anchor && typeof anchor.scrollIntoView === 'function') {
                                anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }
                    } catch (_sc) {}
                });
            }

            function ensureSyncModal() {
                var existing = document.getElementById('sync-modal-overlay');
                if (existing) return existing;

                var overlay = document.createElement('div');
                overlay.id = 'sync-modal-overlay';
                overlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;padding:20px;box-sizing:border-box;';
                overlay.innerHTML = ''
                    + '<div style="max-width:720px;margin:8vh auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,.25);">'
                    + '  <div style="padding:12px 16px;background:#f5f7fb;display:flex;justify-content:space-between;align-items:center;">'
                    + '    <strong id="sync-modal-title">Синхронизация</strong>'
                    + '    <button type="button" id="sync-modal-close" style="border:none;background:transparent;font-size:22px;line-height:1;cursor:pointer;">×</button>'
                    + '  </div>'
                    + '  <div id="sync-modal-body" style="padding:14px 16px;max-height:60vh;overflow:auto;white-space:pre-wrap;font-family:Consolas, monospace;font-size:13px;"></div>'
                    + '  <div style="padding:10px 16px;background:#f5f7fb;text-align:right;">'
                    + '    <button type="button" id="sync-modal-ok" class="btn btn-light btn-sm">Ок</button>'
                    + '  </div>'
                    + '</div>';
                document.body.appendChild(overlay);

                var close = function() { overlay.style.display = 'none'; };
                overlay.querySelector('#sync-modal-close').addEventListener('click', close);
                overlay.querySelector('#sync-modal-ok').addEventListener('click', close);
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) close();
                });
                return overlay;
            }

            function showSyncModal(title, content) {
                var modal = ensureSyncModal();
                var titleEl = modal.querySelector('#sync-modal-title');
                var bodyEl = modal.querySelector('#sync-modal-body');
                titleEl.textContent = title || 'Синхронизация';
                bodyEl.textContent = content || '';
                modal.style.display = 'block';
            }

            window.showFriendCrmSyncModal = showSyncModal;

            function isSyncLink(link) {
                if (!link || !link.getAttribute) return false;
                var href = link.getAttribute('href') || '';
                return href.indexOf('api/bitrix/sync_stock.php?push=1') !== -1
                    || href.indexOf('api/sync_prices.php?action=to_b24') !== -1
                    || href.indexOf('api/bitrix/import_products.php') !== -1;
            }

            async function runSyncRequest(link) {
                var href = link.getAttribute('href');
                var caption = (link.textContent || '').trim();
                link.setAttribute('data-old-text', caption);
                link.textContent = '⏳ Выполняется...';
                link.style.pointerEvents = 'none';

                try {
                    var responses = [];
                    var currentUrl = href;
                    var safety = 0;

                    while (currentUrl && safety < 50) {
                        safety++;
                        var resp = await fetch(currentUrl, { method: 'GET', credentials: 'same-origin' });
                        var text = await resp.text();
                        var parsed = null;
                        try { parsed = JSON.parse(text); } catch (_e) {}

                        responses.push({
                            status: resp.status,
                            text: text,
                            json: parsed
                        });

                        if (!parsed || !parsed.partial || parsed.next_offset === null || typeof parsed.next_offset === 'undefined') {
                            break;
                        }

                        var u = new URL(currentUrl, window.location.origin);
                        u.searchParams.set('offset', String(parsed.next_offset));
                        currentUrl = u.pathname + '?' + u.searchParams.toString();
                    }

                    var output = [];
                    output.push('Запросов выполнено: ' + responses.length);
                    var last = responses[responses.length - 1];
                    output.push('HTTP: ' + last.status);

                    var totalProcessed = 0;
                    var totalCount = null;
                    var errors = 0;
                    for (var i = 0; i < responses.length; i++) {
                        var j = responses[i].json;
                        if (j && typeof j.processed !== 'undefined') {
                            totalProcessed += Number(j.processed || 0);
                        }
                        if (j && typeof j.total_count !== 'undefined') {
                            totalCount = Number(j.total_count);
                        }
                        if (j && Array.isArray(j.items)) {
                            for (var k = 0; k < j.items.length; k++) {
                                if (j.items[k].bitrix_status === 'error') errors++;
                            }
                        }
                    }

                    if (totalCount !== null) {
                        output.push('Обработано товаров: ' + totalProcessed + ' / ' + totalCount);
                    } else {
                        output.push('Обработано товаров: ' + totalProcessed);
                    }
                    output.push('Ошибок отправки: ' + errors);

                    if (last.json) {
                        output.push('\nПоследний ответ:\n' + JSON.stringify(last.json, null, 2));
                    } else {
                        output.push('\nОтвет:\n' + last.text);
                    }

                    showSyncModal('Синхронизировано: ' + (caption || 'операция'), output.join('\n'));
                } catch (e) {
                    showSyncModal(
                        'Ошибка синхронизации',
                        (e && e.message ? e.message : 'Не удалось выполнить запрос')
                    );
                } finally {
                    link.textContent = link.getAttribute('data-old-text') || caption;
                    link.style.pointerEvents = '';
                }
            }

            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPath.split('/').pop()) {
                    link.style.background = 'rgba(255, 255, 255, 0.2)';
                }
            });

            // Плавная прокрутка
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });

            // Алерты закрываются вручную по крестику (без быстрого авто-скрытия)
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.getAttribute('data-dismissible') === 'ready') {
                    return;
                }
                alert.setAttribute('data-dismissible', 'ready');
                alert.style.position = alert.style.position || 'relative';
                alert.style.paddingRight = alert.style.paddingRight || '2.25rem';

                const closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.setAttribute('aria-label', 'Закрыть уведомление');
                closeBtn.innerHTML = '&times;';
                closeBtn.style.cssText = 'position:absolute;top:6px;right:8px;border:none;background:transparent;font-size:18px;line-height:1;cursor:pointer;opacity:.75;';
                closeBtn.addEventListener('mouseenter', function() { closeBtn.style.opacity = '1'; });
                closeBtn.addEventListener('mouseleave', function() { closeBtn.style.opacity = '.75'; });
                closeBtn.addEventListener('click', function() {
                    alert.style.transition = 'opacity .2s ease';
                    alert.style.opacity = '0';
                    setTimeout(function () { alert.remove(); }, 220);
                });
                alert.appendChild(closeBtn);
            });

            // Синхронизация без открытия новой вкладки + диалог результата
            document.body.addEventListener('click', function(e) {
                var link = e.target.closest('a');
                if (!isSyncLink(link)) return;
                e.preventDefault();
                runSyncRequest(link);
            });
        });
    </script>
<?php
if (isset($friendcrm_footer_append_html) && is_string($friendcrm_footer_append_html) && $friendcrm_footer_append_html !== '') {
    echo $friendcrm_footer_append_html;
}
?>
</body>
</html>
