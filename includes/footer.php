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
        });
    </script>
</body>
</html>
