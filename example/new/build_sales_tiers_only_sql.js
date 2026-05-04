/**
 * Writes products_full_import_prices_sales_tiers_only_usd_x88.sql
 *
 * 1) products_full_import_prices_usd_x88.sql — парсинг LLumar-прайса (если файл есть,
 *    сначала сгенерируйте: node example/new/build_products_prices_usd_x88.js).
 *    Пустой хвост H–K после последней числовой колонки = та же сумма что и последняя ступень;
 *    пустой префикс до первой числовой = первая ступень тянется влево.
 *    пустые 1–4 / 5–9 в начале = с первой имеющейся справа (как суффикс/префикс без «дырок» между числами).
 * 2) products.sql — товары, которых нет в (1): как раньше, только не-NULL + тот же fill по четырём ступеням.
 *
 * Никогда не пишем = NULL.
 *
 * По умолчанию в SET: price_per_meter + price_1_4 … price_20_plus (розница из LLumar; закупку не трогаем).
 * Не трогать цену за метр в файле (только ступени): node build_sales_tiers_only_sql.js --without-meter
 *
 * Товары, которых нет в LLumar и у которых в products.sql все тири NULL — по умолчанию в файл не попадают.
 * Тогда строки после импорта остаются NULL. Заполнить все четыре ступени оценкой «розница за рулон»:
 *   price_per_meter × roll_length из дампа (одинаково на всех ступенях — ориентир для CRM).
 *   node build_sales_tiers_only_sql.js --tier-fallback-from-meter-roll
 *
 * Только розничная цена за метр (отдельный файл для своего импорта в MySQL):
 *   node build_sales_tiers_only_sql.js --price-per-meter-only
 * → products_full_import_price_per_meter_only_usd_x88.sql (ступени не трогает).
 * Значения берутся только из products_full_import_prices_usd_x88.sql (дамп products.sql не подмешивается).
 * Если нужно старое поведение с подстановкой price_per_meter из дампа для id вне LLumar-файла:
 *   ... --price-per-meter-only --price-per-meter-fill-from-dump
 *
 * Usage: node build_sales_tiers_only_sql.js [--without-meter] [--tier-fallback-from-meter-roll] [--price-per-meter-only [--price-per-meter-fill-from-dump]]
 */
var fs = require('fs');
var path = require('path');

var argv = process.argv.slice(2);
var pricePerMeterOnly = argv.indexOf('--price-per-meter-only') >= 0;
var pricePerMeterFillFromDump =
  argv.indexOf('--price-per-meter-fill-from-dump') >= 0 && pricePerMeterOnly;
var includePricePerMeter = pricePerMeterOnly ? true : argv.indexOf('--without-meter') < 0;
var tierFallbackFromMeterRoll =
  argv.indexOf('--tier-fallback-from-meter-roll') >= 0 && !pricePerMeterOnly;

function parseTuple(line) {
  line = line.replace(/\)\s*[;,]\s*$/, ')'); // strip trailing ), or );
  line = line.replace(/\s+$/, '').replace(/^\(/, '').replace(/\)$/, '');
  var out = [];
  var cur = '';
  var i = 0;
  var inSq = false;
  while (i < line.length) {
    var c = line[i];
    if (inSq) {
      if (c === "'" && line[i + 1] === "'") {
        cur += "'";
        i += 2;
        continue;
      }
      if (c === "'") {
        inSq = false;
        i += 1;
        continue;
      }
      cur += c;
      i += 1;
    } else {
      if (c === "'") {
        inSq = true;
        i += 1;
        continue;
      }
      if (c === ',') {
        out.push(cur.trim());
        cur = '';
        i += 1;
        continue;
      }
      cur += c;
      i += 1;
    }
  }
  out.push(cur.trim());
  return out;
}

function isNullishDumpPrice(val) {
  return val === undefined || val === '' || /^NULL$/i.test(String(val));
}

/** Parsed decimal from dump cell or SQL literal */
function parsePriceNum(val) {
  if (isNullishDumpPrice(val)) {
    return null;
  }
  var t = String(val).trim();
  if (t.length >= 2 && t[0] === "'" && t[t.length - 1] === "'") {
    t = t.slice(1, -1);
  }
  var n = parseFloat(String(t).replace(',', '.'));
  return isNaN(n) ? null : n;
}

/**
 * Заполнение суффикса/префикса между первой и последней числовой ступенью в прайсе.
 * Если между двумя заданными ступенями в дампе остался null — не интерполируем.
 */
function fillTierQuartetFromPartial(p14raw, p59raw, p1019raw, p20praw) {
  var arr = [
    parsePriceNum(p14raw),
    parsePriceNum(p59raw),
    parsePriceNum(p1019raw),
    parsePriceNum(p20praw)
  ];
  var fi = -1;
  var li = -1;
  var i;
  for (i = 0; i < 4; i++) {
    if (arr[i] !== null && !isNaN(arr[i])) {
      fi = i;
      break;
    }
  }
  for (i = 3; i >= 0; i--) {
    if (arr[i] !== null && !isNaN(arr[i])) {
      li = i;
      break;
    }
  }
  if (fi < 0) {
    return [null, null, null, null];
  }
  var out = arr.slice();
  for (i = 0; i < fi; i++) {
    out[i] = out[fi];
  }
  for (i = li + 1; i < 4; i++) {
    out[i] = out[li];
  }
  return out;
}

function moneySql2(n) {
  if (n === null || n === undefined || isNaN(n)) {
    return null;
  }
  return (Math.round(n * 100) / 100).toFixed(2);
}

/** SQL literal for assigned value (never emit NULL assignments — они затирают живую БД). */
function formatPriceNonNull(val) {
  var s = String(val);
  return "'" + s.replace(/'/g, "''") + "'";
}

/** UPDATE ... SET ... WHERE `id` = N; → { id, price_1_4, ... } для полного импорта */
function parseFullImportUpdateLine(line) {
  if (line.indexOf('UPDATE `products`') < 0 || line.indexOf('WHERE `id`') < 0) {
    return null;
  }
  var idm = line.match(/WHERE\s+`id`\s*=\s*(\d+)\s*;/);
  if (!idm) {
    return null;
  }
  var id = parseInt(idm[1], 10);
  if (isNaN(id)) {
    return null;
  }
  function grab(col) {
    var re = new RegExp('`' + col + '`\\s*=\\s*(\'[^\']*\'|NULL)', 'i');
    var m = line.match(re);
    if (!m) {
      return null;
    }
    return m[1];
  }
  return {
    id: id,
    price_1_4: grab('price_1_4'),
    price_5_9: grab('price_5_9'),
    price_10_19: grab('price_10_19'),
    price_20_plus: grab('price_20_plus'),
    price_per_meter: grab('price_per_meter')
  };
}

function assignsFromFilledTiers(f14, f59, f1019, f20p, ppmRaw, withMeter) {
  var assigns = [];
  if (f14 !== null) {
    assigns.push('`price_1_4` = ' + formatPriceNonNull(moneySql2(f14)));
  }
  if (f59 !== null) {
    assigns.push('`price_5_9` = ' + formatPriceNonNull(moneySql2(f59)));
  }
  if (f1019 !== null) {
    assigns.push('`price_10_19` = ' + formatPriceNonNull(moneySql2(f1019)));
  }
  if (f20p !== null) {
    assigns.push('`price_20_plus` = ' + formatPriceNonNull(moneySql2(f20p)));
  }
  if (withMeter && !isNullishDumpPrice(ppmRaw)) {
    var pn = parsePriceNum(ppmRaw);
    if (pn !== null) {
      assigns.push('`price_per_meter` = ' + formatPriceNonNull(moneySql2(pn)));
    }
  }
  return assigns;
}

/**
 * Одно поле `price_per_meter` — для режима --price-per-meter-only.
 * Если в исходной строке UPDATE уже литерал `'12.34'` — подставляем как в LLumar-SQL без пересчёта.
 */
function assignsPricePerMeterOnly(ppmRaw) {
  var assigns = [];
  if (ppmRaw === null || ppmRaw === undefined) {
    return assigns;
  }
  var ts = String(ppmRaw).trim();
  if (/^NULL$/i.test(ts)) {
    return assigns;
  }
  if (ts.length >= 2 && ts[0] === "'" && ts[ts.length - 1] === "'") {
    assigns.push('`price_per_meter` = ' + ts);
    return assigns;
  }
  if (isNullishDumpPrice(ppmRaw)) {
    return assigns;
  }
  var pn = parsePriceNum(ppmRaw);
  if (pn === null || isNaN(pn)) {
    return assigns;
  }
  assigns.push('`price_per_meter` = ' + formatPriceNonNull(moneySql2(pn)));
  return assigns;
}

var idComments = {};

function recordUpdate(byIdObj, id, assigns, optionalComment) {
  if (!assigns || assigns.length === 0) {
    return;
  }
  var setLine = 'UPDATE `products` SET ' + assigns.join(', ') + ' WHERE `id` = ' + id + ';';
  byIdObj[id] = setLine;
  if (optionalComment && optionalComment !== '') {
    idComments[id] = optionalComment;
  }
}

var srcPath = path.join(__dirname, 'products.sql');
var fullImportPath = path.join(__dirname, 'products_full_import_prices_usd_x88.sql');
var outPath = pricePerMeterOnly
  ? path.join(__dirname, 'products_full_import_price_per_meter_only_usd_x88.sql')
  : path.join(__dirname, 'products_full_import_prices_sales_tiers_only_usd_x88.sql');
var byId = {};
var tierFallbackCount = 0;

if (fs.existsSync(fullImportPath)) {
  var fullRaw = fs.readFileSync(fullImportPath, 'utf8').split(/\r?\n/);
  var qi;
  for (qi = 0; qi < fullRaw.length; qi++) {
    var row = parseFullImportUpdateLine(fullRaw[qi]);
    if (!row) {
      continue;
    }
    if (pricePerMeterOnly) {
      var assignsPpmUsd = assignsPricePerMeterOnly(row.price_per_meter);
      recordUpdate(byId, row.id, assignsPpmUsd);
      continue;
    }
    var fq = fillTierQuartetFromPartial(
      row.price_1_4,
      row.price_5_9,
      row.price_10_19,
      row.price_20_plus
    );
    var assignsUsd = assignsFromFilledTiers(
      fq[0],
      fq[1],
      fq[2],
      fq[3],
      row.price_per_meter,
      includePricePerMeter
    );
    recordUpdate(byId, row.id, assignsUsd);
  }
  process.stderr.write(
    (pricePerMeterOnly ? 'Loaded price_per_meter from LLumar SQL: ' : 'Loaded tiers from LLumar SQL: ') +
      Object.keys(byId).length +
      ' ids.\n'
  );
} else {
  if (pricePerMeterOnly && !pricePerMeterFillFromDump) {
    process.stderr.write(
      'ERROR: не найден ' +
        fullImportPath +
        '\nРежим --price-per-meter-only без --price-per-meter-fill-from-dump берёт цены за м только оттуда.\n' +
        'Положите актуальный products_full_import_prices_usd_x88.sql в example/new/ или сгенерируйте: node build_products_prices_usd_x88.js\n'
    );
  } else {
    process.stderr.write(
      'WARN: не найден ' +
        fullImportPath +
        '\nБерём только dump products.sql: у строк с NULL в тирах в дампе в этом SQL не будет UPDATE по ступеням.\n' +
        'Положите products_full_import_prices_usd_x88.sql сюда (или сгенерируйте локально: node build_products_prices_usd_x88.js).' +
        '\nИли добавьте флаг --tier-fallback-from-meter-roll (ступени из price_per_meter×roll из дампа).\n'
    );
  }
}

if (pricePerMeterOnly && !pricePerMeterFillFromDump && !fs.existsSync(fullImportPath)) {
  process.stderr.write('Аборт: нет ' + path.basename(fullImportPath) + ' — нечего писать в meter-only.\n');
  process.exit(1);
}

var raw = '';
var lines = [];
var gotInsert = false;
if (!pricePerMeterOnly || pricePerMeterFillFromDump) {
  raw = fs.readFileSync(srcPath, 'utf8');
  lines = raw.split(/\r?\n/);
}

for (var li = 0; li < lines.length; li++) {
  var L = lines[li];
  if (L.indexOf('INSERT INTO `products`') !== -1 && L.indexOf('VALUES') !== -1) {
    gotInsert = true;
    continue;
  }
  if (gotInsert && /^\s*ALTER TABLE\s+`products`/i.test(L)) {
    break;
  }
  if (!gotInsert) {
    continue;
  }
  var t = L.trim();
  if (!/^\(\d+,/.test(t)) {
    continue;
  }

  try {
    var parts = parseTuple(t);
    if (parts.length < 10) {
      continue;
    }
    var dumpId = parseInt(parts[0], 10);
    if (isNaN(dumpId)) {
      continue;
    }
    if (byId[dumpId] !== undefined) {
      /** LLumar CSV/SQL уже задал этого id */


      continue;
    }
    var price14 = parts[5];
    var price59 = parts[6];
    var price1019 = parts[7];
    var price20p = parts[8];
    var priceMeter = parts[9];

    if (pricePerMeterOnly) {
      if (!pricePerMeterFillFromDump) {
        continue;
      }
      if (byId[dumpId] !== undefined) {
        continue;
      }
      recordUpdate(byId, dumpId, assignsPricePerMeterOnly(priceMeter));
      continue;
    }

    var fqDump = fillTierQuartetFromPartial(price14, price59, price1019, price20p);
    var hasTierFromDump =
      fqDump[0] !== null ||
      fqDump[1] !== null ||
      fqDump[2] !== null ||
      fqDump[3] !== null;
    var assignsDump = assignsFromFilledTiers(
      fqDump[0],
      fqDump[1],
      fqDump[2],
      fqDump[3],
      priceMeter,
      includePricePerMeter
    );

    var dumped = false;

    if (tierFallbackFromMeterRoll && !hasTierFromDump) {
      var ppmFb = parsePriceNum(priceMeter);
      var rlFb = parsePriceNum(parts[2]);
      if (
        ppmFb !== null &&
        !isNaN(ppmFb) &&
        ppmFb > 0 &&
        rlFb !== null &&
        !isNaN(rlFb) &&
        rlFb > 0
      ) {
        var rollRetailGuess = ppmFb * rlFb;
        assignsDump = assignsFromFilledTiers(
          rollRetailGuess,
          rollRetailGuess,
          rollRetailGuess,
          rollRetailGuess,
          priceMeter,
          includePricePerMeter
        );
        recordUpdate(
          byId,
          dumpId,
          assignsDump,
          'fallback tiers = price_per_meter×roll из products.sql'
        );
        tierFallbackCount++;
        dumped = true;
      }
    }

    if (!dumped && assignsDump && assignsDump.length > 0) {
      recordUpdate(byId, dumpId, assignsDump);
    }
  } catch (e) {
    process.stderr.write('skip line ' + (li + 1) + ': ' + String(e.message) + '\n');
  }
}

var ids = Object.keys(byId).map(Number).sort(function (a, b) {
  return a - b;
});
var hdr;
if (pricePerMeterOnly) {
  hdr =
    '-- Только розничная цена за метр: price_per_meter.\n' +
    '-- price_1_4 … price_20_plus, закуп и purchase_delivered_per_meter не трогаем.\n' +
    (pricePerMeterFillFromDump
      ? '-- Источник: products_full_import_prices_usd_x88.sql + id только из products.sql (цена за м из дампа).\n'
      : '-- Источник: только products_full_import_prices_usd_x88.sql (литерал price_per_meter как в LLumar-файле).\n') +
    '-- Применение: phpMyAdmin → SQL или mysql CLI на своей БД.\n' +
    '-- Regenerate: node example/new/build_sales_tiers_only_sql.js --price-per-meter-only [--price-per-meter-fill-from-dump]\n' +
    '-- Rows: ' +
    ids.length +
    ' UPDATE statements.\n' +
    '-- Generated: ' +
    new Date().toISOString() +
    '\n\nSET NAMES utf8mb4;\n' +
    'START TRANSACTION;\n\n';
} else {
  hdr =
    '-- Продажа: закупку, доставку, purchase_delivered_per_meter не трогаем.\n' +
    '-- Обновляет price_per_meter + price_1_4 … price_20_plus (если сборка без --without-meter).\n' +
    '-- Источник LLumar: products_full_import_prices_usd_x88.sql;\n' +
    '-- доп. строки из products.sql; без LLumar-файла фолбэк слабее.\n' +
    '-- Применение: только phpMyAdmin → SQL или mysql CLI на своей БД (сайт этот файл не выполняет сам).\n' +
    '-- Regenerate: node example/new/build_sales_tiers_only_sql.js [--without-meter] [--tier-fallback-from-meter-roll]\n' +
    '-- Rows: ' +
    ids.length +
    ' UPDATE statements.\n' +
    '-- Generated: ' +
    new Date().toISOString() +
    '\n\nSET NAMES utf8mb4;\n' +
    'START TRANSACTION;\n\n';
}

var body = [];
for (var j = 0; j < ids.length; j++) {
  var jid = ids[j];
  var cmt = idComments[jid] ? (' — ' + idComments[jid]) : '';
  body.push('-- id=' + jid + cmt);
  body.push(byId[jid]);
}

var footer = '\nCOMMIT;\n';
fs.writeFileSync(outPath, hdr + body.join('\n') + footer, 'utf8');
console.log(
  'Wrote ' +
    ids.length +
    ' UPDATEs to ' +
    path.basename(outPath) +
    (pricePerMeterOnly ? ' (price_per_meter only)' : includePricePerMeter ? ' (+ price_per_meter)' : ' (tiers without price_per_meter)')
);
if (tierFallbackCount > 0) {
  process.stderr.write('Tier fallback ppm×roll rows: ' + tierFallbackCount + '\n');
}
process.stderr.write(
  '\n=== ВАЖНО: MySQL сам по себе не обновляется. ===\n' +
    'Откройте phpMyAdmin → ваша БД → вкладка «SQL» → загрузите или вставьте файл:\n' +
    outPath +
    '\nи нажмите «Вперёд». Этот JS только пишет файл на диск.\n\n'
);
