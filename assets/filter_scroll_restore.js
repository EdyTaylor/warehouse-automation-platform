/**
 * Сохраняет вертикальный скролл перед переходами «фильтр внутри текущего PHP»
 * и восстанавливает один раз после загрузке (ключ sessionStorage по имени скрипта).
 *
 * Покрыто:
 * — любые <form method="get"> без class="fc-no-retain-scroll"
 * — любые ссылки на тот же *.php что и страница, с GET-параметрами (?…), без класса fc-no-retain-scroll
 *
 * Принудительно (ссылки с другими правилами): class="fc-retain-scroll".
 * Отключить ссылку: class="fc-no-retain-scroll"
 */
(function () {
    var STORAGE_PREFIX = 'fcRetainScroll_y_';

    function currentScriptFile() {
        var p = window.location.pathname || '';
        try {
            var seg = p.split('/').pop();
            return seg && seg.indexOf('.php') !== -1 ? seg : p || 'index.php';
        } catch (_e) {
            return '_';
        }
    }

    function storageKey() {
        return STORAGE_PREFIX + currentScriptFile();
    }

    function saveScrollY() {
        try {
            var y = window.scrollY || window.pageYOffset || 0;
            if (y < 0) {
                y = 0;
            }
            sessionStorage.setItem(storageKey(), String(y));
        } catch (_e) {}
    }

    function restoreScrollY() {
        try {
            var raw = sessionStorage.getItem(storageKey());
            sessionStorage.removeItem(storageKey());
            if (raw === null) {
                return;
            }
            var y = parseInt(raw, 10);
            if (isNaN(y) || y < 0) {
                return;
            }
            window.scrollTo(0, y);
            requestAnimationFrame(function () {
                window.scrollTo(0, y);
            });
            setTimeout(function () {
                window.scrollTo(0, y);
            }, 50);
        } catch (_e) {}
    }

    function samePagePhpLink(a) {
        if (!a || !a.getAttribute) {
            return false;
        }
        var hrefRaw = (a.getAttribute('href') || '').trim();
        if (hrefRaw === '' || hrefRaw.charAt(0) === '#') {
            return false;
        }
        if (hrefRaw.indexOf('javascript:') === 0) {
            return false;
        }
        if (a.getAttribute('target') === '_blank') {
            return false;
        }
        if (a.getAttribute('download')) {
            return false;
        }
        var u;
        try {
            u = new URL(a.href, window.location.href);
        } catch (_e) {
            return false;
        }
        if (!u.pathname || !u.pathname.toLowerCase || u.pathname.toLowerCase().indexOf('.php') === -1) {
            return false;
        }
        var base = currentScriptFile();
        try {
            var linkFile = u.pathname.split('/').pop();
            if (linkFile !== base) {
                return false;
            }
        } catch (_e2) {
            return false;
        }
        var q = u.search || '';
        if (!q || q === '?') {
            return false;
        }
        if (u.origin !== window.location.origin) {
            return false;
        }
        /* Не сохранять «хлебные крошки» навигации с query (редко) — только по явному классу через другой блок */
        if (a.closest && a.closest('.header .nav')) {
            return false;
        }
        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        restoreScrollY();

        document.addEventListener(
            'submit',
            function (ev) {
                var f = ev.target;
                if (!f || f.tagName !== 'FORM') {
                    return;
                }
                var method = (f.getAttribute('method') || 'get').toLowerCase();
                if (method !== 'get') {
                    return;
                }
                if (f.classList && f.classList.contains('fc-no-retain-scroll')) {
                    return;
                }
                saveScrollY();
            },
            true
        );

        document.addEventListener(
            'click',
            function (ev) {
                var a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
                if (!a) {
                    return;
                }
                if (a.classList.contains('fc-no-retain-scroll')) {
                    return;
                }
                if (a.classList.contains('fc-retain-scroll')) {
                    saveScrollY();
                    return;
                }
                if (samePagePhpLink(a)) {
                    saveScrollY();
                }
            },
            true
        );
    });
})();
