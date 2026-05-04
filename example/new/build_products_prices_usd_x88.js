/**
 * USD price columns from unpacked LLumar.xlsx -> UPDATE `products` in KGS (som).
 * Matching: exact + fuzzy; ambiguous / weak matches -> review CSV (no auto-UPDATE).
 *
 * Run: node example/new/build_products_prices_usd_x88.js
 *
 * Разовый большой приход для api/create_receipt_json.php:
 *   node example/new/build_products_prices_usd_x88.js --bulk-receipt
 * Берёт из листа LLumar колонки: B/C название, D длина рулона, E/F закуп USD, L остаток (число рулонов),
 * матчит к products.sql → b24_product_id (+ опционально product_id), пишет bulk_receipt_from_llumar.generated.json
 * В каждой строке есть product_name (матч из прайса) — для подписи и восстановления названий в приложении/CRM при приходе или отдельным CLI.
 *
 * Если JSON уже есть без имён — подставить product_name только из локального dump products.sql в этой же папке:
 *   node example/new/build_products_prices_usd_x88.js --bulk-receipt-enrich-names
 *
 * Дополнительно всегда пишется products_import_retail_price_per_meter_kgs_x88.sql —
 * только UPDATE price_per_meter (колонка G в LLumar: USD за метр × 88 → сомы).
 * Нужны products.sql и LLumar.xlsx в example/new/ (или уже распакованная папка llumar_xlsx/).
 */
var fs = require('fs');
var path = require('path');
var child_process = require('child_process');

var ROOT = __dirname;
var RATE_KGS_PER_USD = 88;
var LLUMAR_XLSX = path.join(ROOT, 'LLumar.xlsx');
var SHEET_PATH = path.join(ROOT, 'llumar_xlsx', 'xl', 'worksheets', 'sheet1.xml');
var SST_PATH = path.join(ROOT, 'llumar_xlsx', 'xl', 'sharedStrings.xml');
var PRODUCTS_SQL = path.join(ROOT, 'products.sql');
var OUT_SQL = path.join(ROOT, 'products_prices_usd_x88_updates.sql');
var OUT_FULL_IMPORT_SQL = path.join(ROOT, 'products_full_import_prices_usd_x88.sql');
var OUT_RETAIL_METER_KGS_SQL = path.join(
  ROOT,
  'products_import_retail_price_per_meter_kgs_x88.sql'
);
var OUT_REVIEW_CSV = path.join(ROOT, 'products_prices_usd_x88_review.csv');
var OUT_COMPARE_CSV = path.join(
  ROOT,
  'products_usd_x88_comparison_for_feedback.csv'
);
/** Excel (прайс) строки, для которых нет товара в БД с матчем >= MIN_SCORE_UPDATE */
var OUT_EXCEL_NOT_IN_DB_CSV = path.join(
  ROOT,
  'products_usd_x88_excel_rows_not_in_database.csv'
);
/** Тело POST для api/create_receipt_json.php — строки только с матчем >= MIN_SCORE_UPDATE и с b24 */
var OUT_BULK_RECEIPT_JSON = path.join(ROOT, 'bulk_receipt_from_llumar.generated.json');
var OUT_BULK_RECEIPT_SKIPPED_CSV = path.join(
  ROOT,
  'bulk_receipt_from_llumar_skipped.csv'
);

/** Min composite score to allow any UPDATE */
var MIN_SCORE_UPDATE = 0.74;
/** Score at/above = "high confidence" (no review row unless ambiguous) */
var HIGH_SCORE = 0.88;
/** If top-2 gap smaller than this and both >= MIN_SCORE_UPDATE -> ambiguous, no UPDATE */
var AMBIGUOUS_GAP = 0.032;
/** Show in CSV as "weak candidate" when best score in (WEAK_FLOOR, MIN_SCORE_UPDATE) */
var WEAK_FLOOR = 0.5;

/**
 * Нужны xl/worksheets/sheet1.xml и sharedStrings.xml. Если папки нет — распаковать LLumar.xlsx в llumar_xlsx/.
 */
function ensureLlumarWorkbookExtracted() {
  if (fs.existsSync(SHEET_PATH) && fs.existsSync(SST_PATH)) {
    return true;
  }
  if (!fs.existsSync(LLUMAR_XLSX)) {
    console.error(
      'Не найден ни LLumar.xlsx, ни распакованный llumar_xlsx/. Положите LLumar.xlsx в example/new/'
    );
    return false;
  }
  var outDir = path.join(ROOT, 'llumar_xlsx');
  if (fs.existsSync(outDir)) {
    try {
      fs.rmSync(outDir, { recursive: true, force: true });
    } catch (rmErr) {
      console.error('Не удалось очистить llumar_xlsx:', rmErr.message);
      return false;
    }
  }
  fs.mkdirSync(outDir, { recursive: true });
  var zipPath = LLUMAR_XLSX;
  try {
    child_process.execSync(
      'tar -xf "' + zipPath.replace(/"/g, '\\"') + '" -C "' + outDir.replace(/"/g, '\\"') + '"',
      { stdio: 'pipe', cwd: ROOT }
    );
  } catch (tarErr) {
    try {
      if (process.platform === 'win32') {
        var tmpZip = path.join(outDir, '_llumar_unpack.zip');
        fs.copyFileSync(zipPath, tmpZip);
        var psCmd =
          'Expand-Archive -LiteralPath ' +
          JSON.stringify(tmpZip) +
          ' -DestinationPath ' +
          JSON.stringify(outDir) +
          ' -Force';
        child_process.execFileSync(
          'powershell',
          ['-NoProfile', '-Command', psCmd],
          { stdio: 'inherit', cwd: ROOT }
        );
        try {
          fs.unlinkSync(tmpZip);
        } catch (u) {}
      } else {
        child_process.execSync('unzip -o -qq "' + zipPath + '" -d "' + outDir + '"', {
          stdio: 'inherit',
          cwd: ROOT
        });
      }
    } catch (e2) {
      console.error(
        'Не удалось распаковать LLumar.xlsx (tar/unzip/powershell). Распакуйте вручную в example/new/llumar_xlsx/'
      );
      console.error(String(e2.message || e2));
      return false;
    }
  }
  return fs.existsSync(SHEET_PATH) && fs.existsSync(SST_PATH);
}

function decodeXmlEntities(s) {
  return String(s || '')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#([0-9]+);/g, function (_, n) {
      return String.fromCharCode(parseInt(n, 10));
    });
}

function parseSharedStrings(xml) {
  var strings = [];
  var re = /<si>([\s\S]*?)<\/si>/g;
  var m;
  while ((m = re.exec(xml))) {
    var block = m[1];
    var texts = [];
    var mt;
    var tr = /<t[^>]*>([\s\S]*?)<\/t>/g;
    while ((mt = tr.exec(block))) {
      texts.push(decodeXmlEntities(mt[1]));
    }
    strings.push(texts.join(''));
  }
  return strings;
}

function parseRowCells(rowXml, strs) {
  var cells = {};
  var re = /<c r="([A-Z]+)\d+"([^>]*)>([\s\S]*?)<\/c>/g;
  var m;
  while ((m = re.exec(rowXml))) {
    var col = m[1];
    var attrs = m[2];
    var inner = m[3];
    var vm = inner.match(/<v>([\s\S]*?)<\/v>/);
    if (!vm) {
      continue;
    }
    var raw = vm[1].trim();
    if (attrs.indexOf('t="s"') >= 0) {
      var idx = parseInt(raw, 10);
      if (!isNaN(idx) && strs[idx] !== undefined) {
        cells[col] = strs[idx];
      }
    } else {
      var num = parseFloat(raw.replace(',', '.'));
      if (!isNaN(num)) {
        cells[col] = num;
      }
    }
  }
  return cells;
}

function normName(s) {
  return String(s || '')
    .trim()
    .replace(/\s+/g, ' ')
    .replace(/х/g, 'x')
    .replace(/Х/g, 'x')
    .replace(/,/g, '.')
    .toLowerCase();
}

function baseNameKey(s) {
  return normName(s)
    .replace(/\s+new\s*$/i, '')
    .replace(/\s+new\+$/i, '')
    .trim();
}

/** Latin look-alikes for vendor prefixes (subset) */
function stripKnownVendorPrefix(s) {
  var x = normName(s);
  var prefixes = [
    '^llumar\\s+',
    '^luxfil\\s+',
    '^hexis\\s+',
    '^aswf\\s+',
    '^oracal\\s+',
    '^olfa\\s+',
    '^mono\\s+',
    '^nexfil\\s+',
    '^versa\\s+',
    '^membrane\\s+',
    '^кпмф\\s+',
    '^праймер\\s+'
  ];
  prefixes.forEach(function (reSrc) {
    x = x.replace(new RegExp(reSrc, 'i'), '');
  });
  return x.trim();
}

function nameVariants(raw) {
  var v = {};
  function addKey(s, weight) {
    var k = normName(s);
    if (k.length) {
      if (v[k] === undefined || v[k] < weight) {
        v[k] = weight;
      }
    }
  }

  addKey(raw, 1);
  addKey(raw.replace(/\s+new\s*\+?\s*$/i, ''), 0.98);

  var b = baseNameKey(raw);
  addKey(b, 0.97);

  var sv = stripKnownVendorPrefix(raw);
  if (sv && sv !== normName(raw)) {
    addKey(sv, 0.92);
  }

  /** LLumar prefixed in DB vs short name in sheet */
  addKey('llumar ' + stripKnownVendorPrefix(raw), 0.91);
  addKey('luxfil ' + stripKnownVendorPrefix(raw), 0.9);

  return v;
}

/** Сырые группы цифр (05 и 5 считаются разными — разные SKU) */
function numberTokensRaw(raw) {
  return normName(raw).match(/\d+/g) || [];
}

/** Одинаковое число групп цифр, но отличаются строки — другой товар */
function numericCodeMismatch(pvKey, lbl) {
  var a = numberTokensRaw(pvKey);
  var b = numberTokensRaw(lbl);
  var i;
  if (a.length !== b.length || a.length === 0) {
    return false;
  }
  for (i = 0; i < a.length; i++) {
    if (a[i] !== b[i]) {
      return true;
    }
  }
  return false;
}

function excelLabels(row) {
  var b = (row.b || '').trim();
  var c = (row.c || '').trim();
  var nb = normName(b);
  var nc = normName(c);
  var nbc = nb && nc.indexOf(nb + ' ') !== 0 ? normName(b + ' ' + c) : nc;
  if (b && nc.indexOf(nb + ' ') === 0) {
    nbc = nc;
  }
  var lst = [{ s: nc, tag: 'C' }];
  if (nbc !== nc) {
    lst.push({ s: nbc, tag: 'B+C' });
  }
  return lst;
}

function levenshtein(a, b) {
  var al = a.length;
  var bl = b.length;
  if (!al) {
    return bl;
  }
  if (!bl) {
    return al;
  }
  var i;
  var j;
  var prev = [];
  var cur = [];
  for (j = 0; j <= bl; j++) {
    prev.push(j);
  }
  for (i = 1; i <= al; i++) {
    cur[0] = i;
    for (j = 1; j <= bl; j++) {
      var cost = a.charCodeAt(i - 1) === b.charCodeAt(j - 1) ? 0 : 1;
      cur[j] = Math.min(
        cur[j - 1] + 1,
        prev[j] + 1,
        prev[j - 1] + cost
      );
    }
    var swap = prev;
    prev = cur;
    cur = swap;
  }
  return prev[bl];
}

function levRatio(a, b) {
  if (!a.length && !b.length) {
    return 1;
  }
  if (!a.length || !b.length) {
    return 0;
  }
  var d = levenshtein(a, b);
  var mx = Math.max(a.length, b.length, 1);
  return Math.max(0, 1 - d / mx);
}

function containmentRatio(shorter, longer) {
  var s = shorter;
  var l = longer;
  if (s.length > l.length) {
    var t = s;
    s = l;
    l = t;
  }
  if (s.length < 4 || l.indexOf(s) < 0) {
    return 0;
  }
  return Math.min(1, s.length / Math.max(l.length, 1));
}

function containmentScore(pv, lbl) {
  var c = Math.max(containmentRatio(pv, lbl), containmentRatio(lbl, pv));
  if (c < 0.55) {
    return 0;
  }
  /** Cap so short substring alone never wins outright */
  return 0.55 + 0.4 * c;
}

function diceCoefficient(a, b) {
  if (a.length < 2 || b.length < 2) {
    return a === b ? 1 : 0;
  }
  var bg = {};
  var i;
  for (i = 0; i < a.length - 1; i++) {
    var xa = a.slice(i, i + 2);
    bg[xa] = (bg[xa] || 0) + 1;
  }
  var inter = 0;
  var total = Math.max(a.length - 1, 0);
  var jtotal = Math.max(b.length - 1, 0);
  for (i = 0; i < b.length - 1; i++) {
    var xb = b.slice(i, i + 2);
    if (bg[xb] > 0) {
      inter++;
      bg[xb]--;
    }
  }
  return (2 * inter) / Math.max(total + jtotal, 1);
}

function tokenSortJoined(s) {
  return s.split(/\s+/).filter(Boolean).slice().sort().join(' ');
}

function tokenSortSimilarity(a, b) {
  var ta = tokenSortJoined(normName(a));
  var tb = tokenSortJoined(normName(b));
  if (!ta || !tb) {
    return 0;
  }
  return levRatio(ta, tb);
}

function pairScore(pv, lbl, variantWeight, labelWeight) {
  var p = pv;
  var l = lbl;
  if (!p || !l) {
    return 0;
  }
  var base = 1;
  if (p === l) {
    base = 1;
  } else {
    var rLev = levRatio(p, l);
    var rDice = diceCoefficient(p, l);
    var rTok = tokenSortSimilarity(p, l);
    var rContain = containmentScore(p, l);
    base = Math.max(rLev, rDice * 0.92, rTok * 0.95, rContain);
    /** Не брать «случайное» совпадение цифр при далёких строках (BLACK 03 vs HP 03) */
    if (Math.max(rLev, rDice) < 0.44) {
      base = Math.min(base, 0.58);
    }
  }
  if (numericCodeMismatch(p, l)) {
    base *= 0.22;
  }
  return base * variantWeight * labelWeight;
}

function scoreProductVsRow(pvariants, excelRow, labelBoost) {
  var labels = excelLabels(excelRow);
  var lb = typeof labelBoost === 'number' ? labelBoost : 1;
  var best = 0;
  for (var lk in pvariants) {
    if (!Object.prototype.hasOwnProperty.call(pvariants, lk)) {
      continue;
    }
    var wv = pvariants[lk];
    for (var li = 0; li < labels.length; li++) {
      var s = labels[li].s;
      var lw = labels[li].tag === 'C' ? 1 : 0.98;
      var sc = pairScore(lk, s, wv, lw * lb);
      if (sc > best) {
        best = sc;
      }
    }
  }
  return best;
}

function kgsMoney(usd) {
  if (usd === undefined || usd === null || usd === '') {
    return null;
  }
  var n = typeof usd === 'number' ? usd : parseFloat(String(usd).replace(',', '.'));
  if (isNaN(n)) {
    return null;
  }
  return Math.round(n * RATE_KGS_PER_USD * 100) / 100;
}

function kgsTier(usd) {
  if (usd === undefined || usd === null || usd === '') {
    return null;
  }
  var n = typeof usd === 'number' ? usd : parseFloat(String(usd).replace(',', '.'));
  if (isNaN(n) || n === 0) {
    return null;
  }
  return Math.round(n * RATE_KGS_PER_USD * 100) / 100;
}

function sqlNumOrNull(v) {
  if (v === null || v === undefined) {
    return 'NULL';
  }
  return "'" + v.toFixed(2) + "'";
}

function splitSqlFields(line) {
  var fields = [];
  var cur = '';
  var inQ = false;
  for (var i = 0; i < line.length; i++) {
    var ch = line[i];
    if (ch === "'") {
      if (inQ && line[i + 1] === "'") {
        cur += "'";
        i++;
      } else {
        inQ = !inQ;
      }
      continue;
    }
    if (ch === ',' && !inQ) {
      fields.push(cur.trim());
      cur = '';
      continue;
    }
    cur += ch;
  }
  fields.push(cur.trim());
  return fields;
}

function parseProductsSql(filepath) {
  var text = fs.readFileSync(filepath, 'utf8');
  var lines = text.split(/\r?\n/);
  var rows = [];
  for (var li = 0; li < lines.length; li++) {
    var line = lines[li].trim();
    if (line.length < 2 || line[0] !== '(') {
      continue;
    }
    line = line.replace(/\),?\s*$/, '').replace(/\)\s*;?\s*$/, '');
    if (line[line.length - 1] === ',') {
      line = line.slice(0, -1);
    }
    if (line[0] === '(') {
      line = line.slice(1);
    }
    var fields = splitSqlFields(line);
    if (fields.length < 10) {
      continue;
    }
    rows.push(fields);
  }
  return rows;
}

function stripQuotes(s) {
  var t = String(s || '').trim();
  if (t.length >= 2 && t[0] === "'" && t[t.length - 1] === "'") {
    return t.slice(1, -1).replace(/''/g, "'");
  }
  return t;
}

function rowKey(r) {
  return normName((r.b || '') + '|' + (r.c || ''));
}

function buildUsdRowPrices(row) {
  return {
    purchase: kgsMoney(row.usdPurchase),
    delivery: kgsMoney(row.usdDelivery),
    ppm: kgsMoney(row.usdMeter),
    p14: kgsTier(row.usd14),
    p59: kgsTier(row.usd59),
    p1019: kgsTier(row.usd1019),
    p20p: kgsTier(row.usd20p)
  };
}

function updateSqlParts(ex) {
  var setParts = [];
  setParts.push('`purchase_price` = ' + sqlNumOrNull(ex.purchase));
  setParts.push('`delivery_price` = ' + sqlNumOrNull(ex.delivery));
  setParts.push('`price_1_4` = ' + sqlNumOrNull(ex.p14));
  setParts.push('`price_5_9` = ' + sqlNumOrNull(ex.p59));
  setParts.push('`price_10_19` = ' + sqlNumOrNull(ex.p1019));
  setParts.push('`price_20_plus` = ' + sqlNumOrNull(ex.p20p));
  setParts.push('`price_per_meter` = ' + sqlNumOrNull(ex.ppm));
  return setParts;
}

function singleUpdateLine(id, excelRow) {
  var ex = buildUsdRowPrices(excelRow);
  return (
    'UPDATE `products` SET ' +
    updateSqlParts(ex).join(', ') +
    ' WHERE `id` = ' +
    id +
    ';'
  );
}

var SUGGEST_SQL_MIN_SCORE = 0.42;

function maybeSuggestSql(id, top) {
  if (!top || top.score < SUGGEST_SQL_MIN_SCORE) {
    return '';
  }
  return singleUpdateLine(id, top.row);
}

/** CSV escape RFC-style */
function csvEsc(s) {
  var z = String(s == null ? '' : s).replace(/\r?\n/g, ' ');
  if (/[",;]/.test(z)) {
    return '"' + z.replace(/"/g, '""') + '"';
  }
  return z;
}

function intId(s) {
  var n = parseInt(s, 10);
  return isNaN(n) ? 0 : n;
}

function findTopCandidates(pname, excelRows) {
  var pv = nameVariants(pname);
  var scored = [];
  for (var i = 0; i < excelRows.length; i++) {
    var r = excelRows[i];
    var sc = scoreProductVsRow(pv, r, 1);
    if (sc <= 0) {
      continue;
    }
    scored.push({ idx: i, row: r, score: sc, key: rowKey(r) });
  }
  scored.sort(function (a, b) {
    if (b.score !== a.score) {
      return b.score - a.score;
    }
    return ('' + a.row.c).length - ('' + b.row.c).length;
  });
  /** Dedupe keys keeping best score each */
  var seen = {};
  var dedup = [];
  for (var j = 0; j < scored.length; j++) {
    var k = scored[j].key;
    if (!seen[k]) {
      seen[k] = true;
      dedup.push(scored[j]);
    }
    if (dedup.length >= 8) {
      break;
    }
  }
  return dedup;
}

/** Длина рулона (м) из колонки D: число или строка вида "1.7 м" / "30" */
function parseLengthMeters(v) {
  if (typeof v === 'number' && !isNaN(v) && v > 0) {
    return v;
  }
  var s = String(v || '')
    .replace(/\u00a0/g, ' ')
    .replace(/,/g, '.')
    .trim();
  var m = s.match(/(\d+(?:\.\d+)?)/);
  if (m) {
    var n = parseFloat(m[1]);
    if (!isNaN(n) && n > 0) {
      return n;
    }
  }
  return 0;
}

/** Количество рулонов из колонки L «Остаток» */
function parseRollCount(v) {
  if (typeof v === 'number' && !isNaN(v) && v > 0) {
    return Math.round(v);
  }
  var s = String(v || '')
    .replace(/\u00a0/g, ' ')
    .replace(/,/g, '.')
    .trim();
  var m = s.match(/(\d+(?:\.\d+)?)/);
  if (m) {
    var n = parseFloat(m[1]);
    if (!isNaN(n) && n > 0) {
      return Math.round(n);
    }
  }
  return 0;
}

function intB24FromField(f11) {
  var t = stripQuotes(f11);
  var n = parseInt(t, 10);
  return isNaN(n) || n <= 0 ? 0 : n;
}

/** Лучшие кандидаты каталога для строки Excel (обратный поиск к findTopCandidates) */
function findTopProductsForExcelRow(exRow, productMetaList) {
  var scored = [];
  var i;
  for (i = 0; i < productMetaList.length; i++) {
    var pm = productMetaList[i];
    var sc = scoreProductVsRow(pm.variants, exRow, 1);
    if (sc <= 0) {
      continue;
    }
    scored.push({
      id: pm.id,
      pname: pm.pname,
      b24: pm.b24,
      score: sc
    });
  }
  scored.sort(function (a, b) {
    if (b.score !== a.score) {
      return b.score - a.score;
    }
    return ('' + a.pname).length - ('' + b.pname).length;
  });
  var seen = {};
  var dedup = [];
  for (var j = 0; j < scored.length; j++) {
    var idk = String(scored[j].id);
    if (!seen[idk]) {
      seen[idk] = true;
      dedup.push(scored[j]);
    }
    if (dedup.length >= 8) {
      break;
    }
  }
  return dedup;
}

/** b24_product_id → name из столбца name в этом же каталоге (products.sql, поле после id). При дубликатах b24 сохраняется первое имя в файле. */
function buildB24ToNameFromProductsSql() {
  if (!fs.existsSync(PRODUCTS_SQL)) {
    console.error(
      'Нет файла:',
      PRODUCTS_SQL,
      '(ожидается dump таблицы products рядом с bulk JSON)'
    );
    process.exit(1);
  }
  var productRows = parseProductsSql(PRODUCTS_SQL);
  var map = {};
  var seenConflict = {};
  var pi;
  for (pi = 0; pi < productRows.length; pi++) {
    var f = productRows[pi];
    if (f.length < 12) {
      continue;
    }
    var pname = stripQuotes(f[1]);
    var b24 = intB24FromField(f[11]);
    if (b24 <= 0 || !pname) {
      continue;
    }
    if (map[b24] === undefined) {
      map[b24] = pname;
    } else if (map[b24] !== pname && !seenConflict[b24]) {
      seenConflict[b24] = true;
      console.error(
        'Предупреждение: в products.sql несколько имён для b24',
        b24,
        '(оставляем первое в файле)'
      );
    }
  }
  return map;
}

/** Дописать product_name в bulk_receipt_from_llumar.generated.json по products.sql в той же папке example/new/. */
function runBulkReceiptEnrichNamesFromProductsSql() {
  var map = buildB24ToNameFromProductsSql();
  if (!fs.existsSync(OUT_BULK_RECEIPT_JSON)) {
    console.error(
      'Нет файла:',
      OUT_BULK_RECEIPT_JSON,
      '(сгенерируйте --bulk-receipt или загрузите JSON)'
    );
    process.exit(1);
  }
  var raw = fs.readFileSync(OUT_BULK_RECEIPT_JSON, 'utf8');
  var env = JSON.parse(raw);
  if (!env.lines || !Array.isArray(env.lines)) {
    console.error('Нет ключа lines[] в JSON');
    process.exit(1);
  }
  var filled = 0;
  var missingB24 = [];
  var noIdLines = 0;
  var li;
  for (li = 0; li < env.lines.length; li++) {
    var line = env.lines[li];
    if (!line || typeof line !== 'object') {
      continue;
    }
    var b24 = Number(line.b24_product_id || line.b24ProductId || 0);
    if (!(b24 > 0)) {
      noIdLines++;
      continue;
    }
    var nm = map[b24];
    if (!nm) {
      missingB24.push(b24);
      continue;
    }
    line.product_name = nm;
    filled++;
  }
  fs.writeFileSync(
    OUT_BULK_RECEIPT_JSON,
    JSON.stringify(env, null, 2) + '\n',
    'utf8'
  );
  console.log('Written:', OUT_BULK_RECEIPT_JSON);
  console.log(
    JSON.stringify(
      {
        lines_total: env.lines.length,
        product_name_set: filled,
        lines_without_b24: noIdLines,
        b24_not_found_in_products_sql_count: missingB24.length,
        b24_not_found_sample: missingB24.slice(0, 15)
      },
      null,
      2
    )
  );
}

function runBulkReceiptFromLlumar() {
  if (!ensureLlumarWorkbookExtracted()) {
    process.exit(1);
  }

  var sstXml = fs.readFileSync(SST_PATH, 'utf8');
  var sheetXml = fs.readFileSync(SHEET_PATH, 'utf8');
  var strs = parseSharedStrings(sstXml);

  var llumarRows = [];
  var rowParts = sheetXml.split(/<row\b/);
  var ri;
  for (ri = 1; ri < rowParts.length; ri++) {
    var chunk = '<row' + rowParts[ri];
    var end = chunk.indexOf('</row>');
    if (end < 0) {
      continue;
    }
    var rowXml = chunk.slice(0, end + 6);
    var cells = parseRowCells(rowXml, strs);
    var b = typeof cells.B === 'string' ? cells.B : '';
    var c = typeof cells.C === 'string' ? cells.C : '';
    if (!c || c.indexOf('Наименование') >= 0 || c === 'Производитель') {
      continue;
    }

    var lenM = parseLengthMeters(cells.D);
    var qtyRolls = parseRollCount(cells.L);
    var usdPurchase = typeof cells.E === 'number' ? cells.E : null;
    var usdDelivery = typeof cells.F === 'number' ? cells.F : null;

    var hasPurchase =
      usdPurchase !== null && !isNaN(usdPurchase) ? usdPurchase > 0 : false;
    var hasDel =
      usdDelivery !== null && !isNaN(usdDelivery) ? usdDelivery > 0 : false;
    if (!(hasPurchase || hasDel)) {
      continue;
    }
    if (qtyRolls <= 0 || lenM <= 0) {
      continue;
    }

    var exRow = {
      b: b,
      c: c,
      usdPurchase: typeof cells.E === 'number' ? cells.E : undefined,
      usdDelivery: typeof cells.F === 'number' ? cells.F : undefined,
      usdMeter: typeof cells.G === 'number' ? cells.G : undefined,
      usd14: typeof cells.H === 'number' ? cells.H : undefined,
      usd59: typeof cells.I === 'number' ? cells.I : undefined,
      usd1019: typeof cells.J === 'number' ? cells.J : undefined,
      usd20p: typeof cells.K === 'number' ? cells.K : undefined
    };

    llumarRows.push({
      exRow: exRow,
      lengthM: lenM,
      qtyRolls: qtyRolls,
      purchasePerRoll: hasPurchase ? usdPurchase : 0,
      deliveryPerRoll: hasDel ? usdDelivery : 0
    });
  }

  var productRows = parseProductsSql(PRODUCTS_SQL);
  var productMetaList = [];
  var pi;
  for (pi = 0; pi < productRows.length; pi++) {
    var f = productRows[pi];
    if (f.length < 12) {
      continue;
    }
    var pid = intId(stripQuotes(f[0]));
    var pname = stripQuotes(f[1]);
    var b24 = intB24FromField(f[11]);
    productMetaList.push({
      id: pid,
      pname: pname,
      b24: b24,
      variants: nameVariants(pname)
    });
  }

  var linesOut = [];
  var skipped = [];
  skipped.push([
    'reason',
    'excel_b',
    'excel_c',
    'qty_rolls_llumar',
    'length_m_llumar',
    'score_best',
    'score_runner_up',
    'matched_product_id',
    'matched_b24_product_id',
    'matched_name'
  ].join(';'));

  var included = 0;
  var ambig = 0;
  var lowScore = 0;
  var noB24 = 0;

  for (var li = 0; li < llumarRows.length; li++) {
    var lr = llumarRows[li];
    var candidates = findTopProductsForExcelRow(lr.exRow, productMetaList);
    var top = candidates[0];
    var second = candidates[1];
    var sc1 = top ? top.score : 0;
    var sc2 = second ? second.score : 0;

    var pushSkip = function (reason, extraB24, extraPid, extraName) {
      skipped.push([
        csvEsc(reason),
        csvEsc(lr.exRow.b || ''),
        csvEsc(lr.exRow.c || ''),
        lr.qtyRolls,
        lr.lengthM.toFixed(2),
        sc1 ? sc1.toFixed(3) : '0',
        sc2 ? sc2.toFixed(3) : '',
        extraPid != null ? extraPid : (top ? top.id : ''),
        extraB24 != null ? extraB24 : (top ? top.b24 : ''),
        csvEsc(extraName != null ? extraName : (top ? top.pname : ''))
      ].join(';'));
    };

    if (!top || top.score < MIN_SCORE_UPDATE) {
      pushSkip('below_threshold_no_update');
      lowScore++;
      continue;
    }

    var amb =
      !!second &&
      top.score >= MIN_SCORE_UPDATE &&
      second.score >= MIN_SCORE_UPDATE &&
      top.score - second.score < AMBIGUOUS_GAP;

    if (amb) {
      pushSkip('ambiguous_two_products');
      ambig++;
      continue;
    }

    if (!(top.b24 > 0)) {
      pushSkip('no_b24_product_id_in_products_sql');
      noB24++;
      continue;
    }

    linesOut.push({
      b24_product_id: top.b24,
      product_name: top.pname,
      qty_rolls: lr.qtyRolls,
      roll_length: lr.lengthM,
      purchase_per_roll: lr.purchasePerRoll,
      delivery_per_roll: lr.deliveryPerRoll,
      match_score_llumar_row: Number(sc1.toFixed(3)),
      matched_local_product_id: top.id
    });
    included++;
  }

  var today = new Date().toISOString().slice(0, 10);
  var envelope = {
    doc_number: 'PR-LLUMAR-BULK-' + today,
    supplier: 'LLumar',
    comment_text:
      'Авто из LLumar xlsx: остаток L (рулонов) × длина D (м), закуп E/F USD. См. bulk_receipt_from_llumar_skipped.csv',
    receipt_currency: 'USD',
    min_full: 0.5,
    lines: linesOut.map(function (x) {
      var ln = {
        b24_product_id: x.b24_product_id,
        product_name: x.product_name,
        qty_rolls: x.qty_rolls,
        roll_length: x.roll_length,
        purchase_per_roll: x.purchase_per_roll,
        delivery_per_roll: x.delivery_per_roll
      };
      if (x.matched_local_product_id > 0) {
        ln.product_id = x.matched_local_product_id;
      }
      return ln;
    })
  };

  fs.writeFileSync(
    OUT_BULK_RECEIPT_JSON,
    JSON.stringify(envelope, null, 2) + '\n',
    'utf8'
  );
  var bom = '\ufeff';
  fs.writeFileSync(
    OUT_BULK_RECEIPT_SKIPPED_CSV,
    bom + skipped.join('\r\n') + '\r\n',
    'utf8'
  );

  console.log('Written:', OUT_BULK_RECEIPT_JSON);
  console.log('Written:', OUT_BULK_RECEIPT_SKIPPED_CSV);
  console.log(
    JSON.stringify(
      {
        llumar_rows_with_stock_and_prices: llumarRows.length,
        lines_in_json: included,
        skipped_low_score: lowScore,
        skipped_ambiguous: ambig,
        skipped_no_b24: noB24
      },
      null,
      2
    )
  );
}

function writeRetailMeterKgsSqlFile(importRows) {
  var lines = [];
  var n = 0;
  var i;
  for (i = 0; i < importRows.length; i++) {
    var rec = importRows[i];
    var um = rec.usdMeter;
    if (typeof um !== 'number' || isNaN(um) || um <= 0) {
      continue;
    }
    var kgs = kgsMoney(um);
    if (kgs === null || kgs === undefined || isNaN(kgs)) {
      continue;
    }
    n++;
    lines.push(
      '-- id=' +
        intId(rec.id) +
        ' ' +
        rec.tier +
        ' score=' +
        rec.score.toFixed(3) +
        ' | G_usd_per_m=' +
        um
    );
    lines.push(
      'UPDATE `products` SET `price_per_meter` = \'' +
        kgs.toFixed(2) +
        '\' WHERE `id` = ' +
        intId(rec.id) +
        ';'
    );
  }
  var hdr = [];
  hdr.push(
    '-- Розничная цена за метр (сомы): LLumar.xlsx колонка G (USD за метр) × ' +
      RATE_KGS_PER_USD
  );
  hdr.push(
    '-- Матчинг: имя в БД (products.sql) ↔ колонки B/C прайса; только те же id, что в products_full_import_prices_usd_x88.sql (HIGH + MARGINAL).'
  );
  hdr.push('-- Если в строке Excel нет числа в G — UPDATE для этого id здесь не создаётся.');
  hdr.push('-- UPDATE с ценой за метр: ' + n + ' из ' + importRows.length + ' сопоставленных id.');
  hdr.push('');
  hdr.push('SET NAMES utf8mb4;');
  hdr.push('START TRANSACTION;');
  hdr.push('');
  var out = hdr.join('\r\n') + '\r\n' + lines.join('\r\n') + '\r\n\r\nCOMMIT;\r\n';
  fs.writeFileSync(OUT_RETAIL_METER_KGS_SQL, out, 'utf8');
}

function main() {
  if (!ensureLlumarWorkbookExtracted()) {
    process.exit(1);
  }

  var sstXml = fs.readFileSync(SST_PATH, 'utf8');
  var sheetXml = fs.readFileSync(SHEET_PATH, 'utf8');
  var strs = parseSharedStrings(sstXml);

  var excelRows = [];
  var rowParts = sheetXml.split(/<row\b/);
  for (var ri = 1; ri < rowParts.length; ri++) {
    var chunk = '<row' + rowParts[ri];
    var end = chunk.indexOf('</row>');
    if (end < 0) {
      continue;
    }
    var rowXml = chunk.slice(0, end + 6);
    var cells = parseRowCells(rowXml, strs);
    var b = typeof cells.B === 'string' ? cells.B : '';
    var c = typeof cells.C === 'string' ? cells.C : '';
    if (!c || c.indexOf('Наименование') >= 0 || c === 'Производитель') {
      continue;
    }

    var usdPurchase = cells.E;
    var usdDelivery = cells.F;
    var usdMeter = cells.G;
    var usd14 = cells.H;
    var usd59 = cells.I;
    var usd1019 = cells.J;
    var usd20p = cells.K;

    var hasUsd =
      typeof usdPurchase === 'number' ||
      typeof usdDelivery === 'number' ||
      typeof usdMeter === 'number' ||
      typeof usd14 === 'number' ||
      typeof usd59 === 'number' ||
      typeof usd1019 === 'number' ||
      typeof usd20p === 'number';

    if (!hasUsd) {
      continue;
    }

    excelRows.push({
      b: b,
      c: c,
      usdPurchase: usdPurchase,
      usdDelivery: usdDelivery,
      usdMeter: usdMeter,
      usd14: usd14,
      usd59: usd59,
      usd1019: usd1019,
      usd20p: usd20p
    });
  }

  var productRows = parseProductsSql(PRODUCTS_SQL);

  /** Предрасчёт вариантов имён БД — для обратной проверки «строка Excel не покрыта каталогом» */
  var dbNamePack = [];
  for (pi = 0; pi < productRows.length; pi++) {
    var packF = productRows[pi];
    dbNamePack.push({
      id: packF[0],
      pname: stripQuotes(packF[1]),
      variants: nameVariants(stripQuotes(packF[1]))
    });
  }

  var excelWithoutDbStrong = 0;
  var excelWithoutDbWeakOnly = 0;
  /** Excel: лучший score < WEAK_FLOOR */
  var excelNoDbCandidate = 0;
  var orphanCsv = [];
  orphanCsv.push(
    [
      'excel_vendor',
      'excel_name',
      'best_score_to_any_db_product',
      'nearest_db_id',
      'nearest_db_name',
      'below_min_score_update_' + MIN_SCORE_UPDATE + '_means_not_in_catalog'
    ].join(';')
  );
  for (var exi = 0; exi < excelRows.length; exi++) {
    var exRow = excelRows[exi];
    var bst = 0;
    var bstId = '';
    var bstPname = '';
    for (var dji = 0; dji < dbNamePack.length; dji++) {
      var dp = dbNamePack[dji];
      var scr = scoreProductVsRow(dp.variants, exRow, 1);
      if (scr > bst) {
        bst = scr;
        bstId = dp.id;
        bstPname = dp.pname;
      }
    }
    if (bst < WEAK_FLOOR) {
      excelNoDbCandidate++;
    } else if (bst < MIN_SCORE_UPDATE) {
      excelWithoutDbWeakOnly++;
    }
    if (bst < MIN_SCORE_UPDATE) {
      excelWithoutDbStrong++;
      orphanCsv.push(
        [
          csvEsc(exRow.b || ''),
          csvEsc(exRow.c || ''),
          bst.toFixed(3),
          bstId,
          csvEsc(bstPname),
          'YES'
        ].join(';')
      );
    }
  }

  var csvLines = [];
  /** { id, tier, pname, ups, score, eb, ec, score2 } */
  var importRows = [];
  /** rows for unified comparison CSV */
  var cmpRows = [];

  csvLines.push(
    [
      'reason',
      'product_id',
      'product_name',
      'match_score_best',
      'match_score_runner_up',
      'excel_mfr',
      'excel_name',
      'runner_up_hint',
      'suggested_note',
      'suggested_update_if_row_ok'
    ].join(';')
  );

  var stats = {
    confident: 0,
    marginalSql: 0,
    weakCsv: 0,
    noCsv: 0,
    ambiguousNoSql: 0
  };

  var pi;
  var id;
  var pname;
  var candidates;
  var top;
  var second;

  for (pi = 0; pi < productRows.length; pi++) {
    var f = productRows[pi];
    id = f[0];
    pname = stripQuotes(f[1]);

    candidates = findTopCandidates(pname, excelRows);
    top = candidates[0];
    second = candidates[1];

    var ups = '';

    /** No plausible row */
    if (!top || top.score < WEAK_FLOOR) {
      stats.noCsv++;
      csvLines.push(
        [
          'no_candidate',
          id,
          csvEsc(pname),
          top ? top.score.toFixed(3) : '0',
          second ? second.score.toFixed(3) : '',
          top ? csvEsc(top.row.b || '') : '',
          top ? csvEsc(top.row.c || '') : '',
          second
            ? csvEsc(
                second.row.b + ' | ' + second.row.c + ' (~' + second.score.toFixed(3) + ')'
              )
            : '',
          'В прайсе нет строки выше порога ' + WEAK_FLOOR.toFixed(2),
          csvEsc(maybeSuggestSql(id, top))
        ].join(';')
      );

      cmpRows.push({
        sortOrder: 5,
        id: id,
        groupRu: 'НЕТ_СОВПАДЕНИЯ',
        tierCode: 'NO_MATCH',
        pname: pname,
        eb: top ? top.row.b || '' : '',
        ec: top ? top.row.c || '' : '',
        sc1: top ? top.score : 0,
        sc2: second ? second.score : 0,
        inImport: false,
        upsMain: '',
        upsAlt:
          maybeSuggestSql(id, top) ||
          '',
        runnerHint: second
          ? second.row.b + ' | ' + second.row.c + ' (~' + second.score.toFixed(3) + ')'
          : ''
      });

      continue;
    }

    var amb =
      !!second &&
      top.score >= MIN_SCORE_UPDATE &&
      second.score >= MIN_SCORE_UPDATE &&
      top.score - second.score < AMBIGUOUS_GAP;

    if (amb) {
      stats.ambiguousNoSql++;
      csvLines.push(
        [
          'ambiguous',
          id,
          csvEsc(pname),
          top.score.toFixed(3),
          second.score.toFixed(3),
          csvEsc(top.row.b || ''),
          csvEsc(top.row.c || ''),
          csvEsc(
            second.row.b + ' | ' + second.row.c + ' (~' + second.score.toFixed(3) + ')'
          ),
          'Два очень похожих кандидата — выберите верный перед UPDATE',
          csvEsc(
            '-- opt1: ' +
              singleUpdateLine(id, top.row) +
              ' | -- opt2: ' +
              singleUpdateLine(id, second.row)
          )
        ].join(';')
      );

      cmpRows.push({
        sortOrder: 3,
        id: id,
        groupRu: 'НЕОДНОЗНАЧНО',
        tierCode: 'AMBIGUOUS',
        pname: pname,
        eb: top.row.b || '',
        ec: top.row.c || '',
        sc1: top.score,
        sc2: second.score,
        inImport: false,
        upsMain: '',
        upsAlt:
          singleUpdateLine(id, second.row),
        runnerHint: second.row.b + ' | ' + second.row.c
      });

      continue;
    }

    if (top.score < MIN_SCORE_UPDATE) {
      stats.weakCsv++;
      csvLines.push(
        [
          'below_threshold',
          id,
          csvEsc(pname),
          top.score.toFixed(3),
          second ? second.score.toFixed(3) : '',
          csvEsc(top.row.b || ''),
          csvEsc(top.row.c || ''),
          second ? csvEsc(second.row.b + ' | ' + second.row.c) : '',
          'Сходство ниже ' + MIN_SCORE_UPDATE + ' — не генерируется UPDATE',
          csvEsc(maybeSuggestSql(id, top))
        ].join(';')
      );

      cmpRows.push({
        sortOrder: 4,
        id: id,
        groupRu: 'СЛАБОЕ_NЕ_В_ИМПОРТЕ',
        tierCode: 'WEAK',
        pname: pname,
        eb: top.row.b || '',
        ec: top.row.c || '',
        sc1: top.score,
        sc2: second ? second.score : 0,
        inImport: false,
        upsMain: '',
        upsAlt: maybeSuggestSql(id, top) || '',
        runnerHint: second ? second.row.b + ' | ' + second.row.c : ''
      });

      continue;
    }

    /** OK to UPDATE */
    var ex = buildUsdRowPrices(top.row);
    var setParts = updateSqlParts(ex);
    ups =
      'UPDATE `products` SET ' +
      setParts.join(', ') +
      ' WHERE `id` = ' +
      id +
      ';';

    if (top.score >= HIGH_SCORE) {
      stats.confident++;
      importRows.push({
        sortOrder: 1,
        id: id,
        score: top.score,
        pname: pname,
        ups: ups,
        eb: top.row.b || '',
        ec: top.row.c || '',
        tier: 'HIGH',
        usdMeter: top.row.usdMeter
      });

      cmpRows.push({
        sortOrder: 1,
        id: id,
        groupRu: 'ХОРОШЕЕ_СОВПАДЕНИЕ',
        tierCode: 'HIGH',
        pname: pname,
        eb: top.row.b || '',
        ec: top.row.c || '',
        sc1: top.score,
        sc2: second ? second.score : 0,
        inImport: true,
        upsMain: ups,
        upsAlt:
          '',
        runnerHint: second
          ? second.row.b + ' | ' + second.row.c + ' (~' + second.score.toFixed(3) + ')'
          : ''
      });
    } else {
      stats.marginalSql++;
      importRows.push({
        sortOrder: 2,
        id: id,
        score: top.score,
        pname: pname,
        ups: ups,
        eb: top.row.b || '',
        ec: top.row.c || '',
        tier: 'MARGINAL',
        usdMeter: top.row.usdMeter
      });

      csvLines.push(
        [
          'marginal_match',
          id,
          csvEsc(pname),
          top.score.toFixed(3),
          second ? second.score.toFixed(3) : '',
          csvEsc(top.row.b || ''),
          csvEsc(top.row.c || ''),
          second ? csvEsc(second.row.b + ' | ' + second.row.c) : '',
          'UPDATE включён как мягкий; сверить название вручную',
          csvEsc(singleUpdateLine(id, top.row))
        ].join(';')
      );

      cmpRows.push({
        sortOrder: 2,
        id: id,
        groupRu: 'МИНИМАЛЬНОЕ_В_ИМПОРТЕ',
        tierCode: 'MARGINAL',
        pname: pname,
        eb: top.row.b || '',
        ec: top.row.c || '',
        sc1: top.score,
        sc2: second ? second.score : 0,
        inImport: true,
        upsMain: ups,
        upsAlt:
          second &&
          second.score >= SUGGEST_SQL_MIN_SCORE &&
          second.score <= top.score + 0.12
            ? singleUpdateLine(id, second.row)
            : '',
        runnerHint: second
          ? second.row.b + ' | ' + second.row.c + ' (~' + second.score.toFixed(3) + ')'
          : ''
      });
    }
  }

  importRows.sort(function (a, b) {
    return intId(a.id) - intId(b.id);
  });

  cmpRows.sort(function (x, y) {
    if (x.sortOrder !== y.sortOrder) {
      return x.sortOrder - y.sortOrder;
    }
    return intId(x.id) - intId(y.id);
  });

  /** Legacy split file (HIGH then MARGINAL) */
  var confidentLines = [];
  var marginalLines = [];
  importRows.forEach(function (rec) {
    if (rec.tier === 'HIGH') {
      confidentLines.push('-- id=' + rec.id + ' score=' + rec.score.toFixed(3));
      confidentLines.push(rec.ups);
    } else {
      marginalLines.push(
        '-- id=' +
          rec.id +
          ' score=' +
          rec.score.toFixed(3) +
          ' (marginal — проверьте)'
      );
      marginalLines.push(rec.ups);
    }
  });

  var header = [];
  header.push('-- FX: USD * ' + RATE_KGS_PER_USD + ' KGS; fuzzy match + review CSV');
  header.push('-- Confident (score>=' + HIGH_SCORE + '): ' + stats.confident);
  header.push(
    '-- Marginal SQL (score ' + MIN_SCORE_UPDATE + '..' + HIGH_SCORE + '): ' + stats.marginalSql
  );
  header.push('-- See products_prices_usd_x88_review.csv for ambiguous/weak/no_match');
  header.push('');
  header.push('-- === HIGH CONFIDENCE ===');
  header.push('');
  header.push('SET NAMES utf8mb4;');
  header.push('START TRANSACTION;');

  var blockConf = confidentLines.join('\r\n');
  var blockMar = marginalLines.join('\r\n');

  var body =
    '\r\n' +
    blockConf +
    '\r\n\r\n-- === MARGINAL (проверьте строки ниже перед COMMIT при необходимости) ===\r\n\r\n' +
    blockMar +
    '\r\n\r\nCOMMIT;\r\n\r\n-- Stats: ambiguous_skipped_sql=' +
    stats.ambiguousNoSql +
    ' weak_below_threshold_csv=' +
    stats.weakCsv +
    ' no_candidate_csv=' +
    stats.noCsv +
    '\r\n';

  fs.writeFileSync(OUT_SQL, header.join('\r\n') + body, 'utf8');

  /** Полный один блок: все UPDATE по id, без разрыва между «уверенными» и marginal */
  var fullHeader = [];
  fullHeader.push('-- Полный загружаемый скрипт: цены из LLumar.xlsx USD -> KGS, курс * ' + RATE_KGS_PER_USD);
  fullHeader.push('-- UPDATE для id: ' + importRows.length + ' строк каталога (остальные ' + stats.weakCsv + '+' + stats.noCsv + ' + ambiguous ' + stats.ambiguousNoSql + ' см. CSV comparison)');
  fullHeader.push('-- Только изменяются purchase_price .. price_per_meter (остальные поля products без изменений)');
  fullHeader.push('SET NAMES utf8mb4;');
  fullHeader.push('START TRANSACTION;');
  fullHeader.push('');
  var fullBodies = [];
  importRows.forEach(function (rec) {
    fullBodies.push('-- id=' + rec.id + ' ' + rec.tier + ' score=' + rec.score.toFixed(3));
    fullBodies.push(rec.ups);
  });
  fullBodies.push('');
  fullBodies.push('COMMIT;');
  fs.writeFileSync(OUT_FULL_IMPORT_SQL, fullHeader.join('\r\n') + '\r\n' + fullBodies.join('\r\n') + '\r\n', 'utf8');

  writeRetailMeterKgsSqlFile(importRows);

  /** Сравнение для обратной связи */
  var cm = [];
  cm.push(
    [
      'tier_group_ru',
      'tier_code',
      'included_in_full_import_sql',
      'product_id',
      'name_in_database',
      'excel_vendor_best',
      'excel_product_name_best',
      'match_score_best',
      'match_score_runner_up',
      'runner_up_text',
      'sql_applied_if_in_import',
      'sql_optional_alternate_if_similar_only',
      'your_feedback_edit_here'
    ].join(';')
  );
  cmpRows.forEach(function (r) {
    cm.push(
      [
        csvEsc(r.groupRu),
        csvEsc(r.tierCode),
        r.inImport ? 'YES' : 'NO',
        r.id,
        csvEsc(r.pname),
        csvEsc(r.eb),
        csvEsc(r.ec),
        typeof r.sc1 === 'number' ? r.sc1.toFixed(3) : '',
        typeof r.sc2 === 'number' && r.sc2 > 0 ? r.sc2.toFixed(3) : '',
        csvEsc(r.runnerHint),
        csvEsc(r.upsMain),
        csvEsc(r.upsAlt),
        ''
      ].join(';')
    );
  });

  /** UTF-8 BOM for Excel RU locale */
  var bom = '\ufeff';
  fs.writeFileSync(OUT_REVIEW_CSV, bom + csvLines.join('\r\n') + '\r\n', 'utf8');
  fs.writeFileSync(OUT_COMPARE_CSV, bom + cm.join('\r\n') + '\r\n', 'utf8');
  fs.writeFileSync(OUT_EXCEL_NOT_IN_DB_CSV, bom + orphanCsv.join('\r\n') + '\r\n', 'utf8');

  console.log('Written:', OUT_SQL);
  console.log('Written:', OUT_FULL_IMPORT_SQL);
  console.log('Written:', OUT_RETAIL_METER_KGS_SQL);
  console.log('Written:', OUT_REVIEW_CSV);
  console.log('Written:', OUT_COMPARE_CSV);
  console.log('Written:', OUT_EXCEL_NOT_IN_DB_CSV);
  console.log(
    JSON.stringify(
      {
        excel_rows_total_usd: excelRows.length,
        excel_rows_no_db_match_ge_min_update: excelWithoutDbStrong,
        excel_rows_best_match_weak_only_ge_weak_floor: excelWithoutDbWeakOnly,
        excel_rows_best_below_weak_floor: excelNoDbCandidate
      },
      null,
      2
    )
  );
  console.log(JSON.stringify(stats, null, 2));
}

if (process.argv.indexOf('--bulk-receipt-enrich-names') >= 0) {
  runBulkReceiptEnrichNamesFromProductsSql();
} else if (process.argv.indexOf('--bulk-receipt') >= 0) {
  runBulkReceiptFromLlumar();
} else {
  main();
}
