/**
 * Центр интеграции: приход JSON с receipt_lines_per_chunk > 0 — пошагово в модалке (как синк товаров).
 */
(function () {
    function showModal(text) {
        if (typeof window.showFriendCrmSyncModal === 'function') {
            window.showFriendCrmSyncModal('Приход JSON по частям', text);
        } else {
            alert(text);
        }
    }

    function readJsonFromForm(form) {
        return new Promise(function (resolve, reject) {
            var ta = form.querySelector('textarea[name="receipt_json_paste"]');
            var fileIn = form.querySelector('input[name="receipt_json_file"]');
            var fromTa = ta && typeof ta.value === 'string' ? ta.value.trim() : '';
            if (fileIn && fileIn.files && fileIn.files.length > 0) {
                var fr = new FileReader();
                fr.onload = function () {
                    var s = typeof fr.result === 'string' ? fr.result.trim() : '';
                    if (s === '') {
                        reject(new Error('Файл JSON пуст.'));
                    } else {
                        resolve(s);
                    }
                };
                fr.onerror = function () {
                    reject(new Error('Не удалось прочитать файл.'));
                };
                fr.readAsText(fileIn.files[0], 'UTF-8');
                return;
            }
            if (fromTa !== '') {
                resolve(fromTa);
                return;
            }
            reject(new Error('Выберите файл .json или вставьте JSON.'));
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('integration-receipt-json-form');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function (ev) {
            var lpcEl = form.querySelector('input[name="receipt_lines_per_chunk"]');
            var lpc = lpcEl ? parseInt(String(lpcEl.value), 10) : 0;
            if (isNaN(lpc) || lpc <= 0) {
                return;
            }
            ev.preventDefault();

            var secretEl = form.querySelector('input[name="receipt_run_secret"]');
            var secret = secretEl ? String(secretEl.value || '').trim() : '';
            if (secret === '') {
                showModal('Укажите секрет прихода.');
                return;
            }

            var maxRollEl = form.querySelector('input[name="receipt_max_roll_units"]');
            var maxRoll = maxRollEl ? parseInt(String(maxRollEl.value), 10) : 0;
            if (isNaN(maxRoll)) {
                maxRoll = 0;
            }

            var localOnly = form.querySelector('input[name="receipt_local_only"]');
            var localOnlyOn = localOnly && localOnly.checked;

            var btn = document.getElementById('integration-receipt-json-submit');
            var oldBtnText = btn ? btn.textContent : '';

            readJsonFromForm(form).then(function (jsonText) {
                var data;
                try {
                    data = JSON.parse(jsonText);
                } catch (e1) {
                    showModal('Некорректный JSON: ' + (e1 && e1.message ? e1.message : ''));
                    return;
                }
                if (!data || typeof data !== 'object') {
                    showModal('JSON должен быть объектом с ключом lines.');
                    return;
                }
                data.lines_per_chunk = lpc;
                data.max_roll_units_per_chunk = maxRoll;
                if (localOnlyOn) {
                    data.local_only = true;
                }

                var planBody = JSON.stringify(data);
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = '⏳ План…';
                }

                showModal('Готовлю план частей…');

                fetch('api/receipt_prepare_chunks.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json; charset=utf-8',
                        'X-Stock-Receipt-Secret': secret
                    },
                    body: planBody,
                    credentials: 'same-origin'
                }).then(function (resp) {
                    return resp.text().then(function (txt) {
                        var parsed = null;
                        try {
                            parsed = JSON.parse(txt);
                        } catch (_e2) {
                        }
                        return { status: resp.status, text: txt, json: parsed };
                    });
                }).then(function (pack) {
                    if (!pack.json || !pack.json.ok) {
                        var em = (pack.json && pack.json.error) ? pack.json.error : pack.text;
                        showModal('План не построен (HTTP ' + pack.status + '):\n' + em);
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = oldBtnText;
                        }
                        return;
                    }
                    var payloads = pack.json.payloads;
                    var n = payloads.length;
                    var linesOut = [];
                    linesOut.push('Частей: ' + n + ' (строк/партия ≤ ' + pack.json.lines_per_chunk + ', рулонов в партии ≤ ' + pack.json.max_roll_units + ').');
                    showModal(linesOut.join('\n'));

                    if (btn) {
                        btn.textContent = '⏳ Части прихода…';
                    }

                    var i = 0;
                    function runNext() {
                        if (i >= n) {
                            linesOut.push('');
                            linesOut.push('Готово: все части отправлены.');
                            showModal(linesOut.join('\n'));
                            if (btn) {
                                btn.disabled = false;
                                btn.textContent = oldBtnText;
                            }
                            return;
                        }
                        var part = i + 1;
                        linesOut.push('');
                        linesOut.push('— Часть ' + part + '/' + n + ' …');
                        showModal(linesOut.join('\n'));

                        fetch('api/create_receipt_json.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json; charset=utf-8',
                                'X-Stock-Receipt-Secret': secret
                            },
                            body: JSON.stringify(payloads[i]),
                            credentials: 'same-origin'
                        }).then(function (r2) {
                            return r2.text().then(function (t2) {
                                var j2 = null;
                                try {
                                    j2 = JSON.parse(t2);
                                } catch (_e3) {
                                }
                                return { json: j2, text: t2 };
                            });
                        }).then(function (done) {
                            var j = done.json;
                            if (j && j.chunked && j.chunk_results) {
                                var cr = j.chunk_results;
                                for (var t = 0; t < cr.length; t++) {
                                    var one = cr[t];
                                    if (one && one.ok) {
                                        linesOut.push('  ok под-чанк сервера: док #' + (one.doc_id || '?'));
                                    } else if (one) {
                                        linesOut.push('  ОШИБКА под-чанк: ' + (one.error_message || ''));
                                    }
                                }
                                linesOut.push('  (сервер сгруппировал часть ' + part + ')');
                                showModal(linesOut.join('\n'));
                                i += 1;
                                runNext();
                                return;
                            }
                            if (j && j.ok) {
                                var sid = '';
                                if (typeof j.doc_id !== 'undefined' && j.doc_id !== null) {
                                    sid = ' локальный документ #' + j.doc_id;
                                }
                                if (j.duplicate_receipt_skip) {
                                    linesOut.push('  ok часть ' + part + ': дубликат пропущен' + sid);
                                } else {
                                    linesOut.push('  ok часть ' + part + ':' + sid);
                                }
                            } else {
                                var errTx = j && j.error_message
                                    ? j.error_message
                                    : (j && j.error ? j.error : done.text);
                                linesOut.push('  ОШИБКА часть ' + part + ': ' + errTx);
                                showModal(linesOut.join('\n'));
                                if (btn) {
                                    btn.disabled = false;
                                    btn.textContent = oldBtnText;
                                }
                                return;
                            }
                            i += 1;
                            runNext();
                        }).catch(function (e4) {
                            linesOut.push('  ОШИБКА часть ' + part + ': ' + (e4 && e4.message ? e4.message : 'сеть'));
                            showModal(linesOut.join('\n'));
                            if (btn) {
                                btn.disabled = false;
                                btn.textContent = oldBtnText;
                            }
                        });
                    }
                    runNext();
                }).catch(function (e5) {
                    showModal('Ошибка сети (план): ' + (e5 && e5.message ? e5.message : ''));
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = oldBtnText;
                    }
                });
            }).catch(function (e0) {
                showModal(e0 && e0.message ? e0.message : 'Ошибка');
            });
        });
    });
})();
