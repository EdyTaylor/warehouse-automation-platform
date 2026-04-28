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
                    var resp = await fetch(href, { method: 'GET', credentials: 'same-origin' });
                    var text = await resp.text();
                    var pretty = text;
                    try {
                        pretty = JSON.stringify(JSON.parse(text), null, 2);
                    } catch (_e) {}

                    showSyncModal(
                        'Синхронизировано: ' + (caption || 'операция'),
                        'HTTP ' + resp.status + '\n\n' + pretty
                    );
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

            // Автоматическое скрытие алертов
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
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
</body>
</html>
