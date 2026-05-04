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
 * По умолчанию в SET только ступени price_1_4 … price_20_plus (цена за метр в приложении может идти
 * из прихода/Б24 — не перетирать пачкой из Excel). Колонку price_per_meter добавить в импорт:
 *   node build_sales_tiers_only_sql.js --with-meter
 *
 * Usage: node build_sales_tiers_only_sql.js [--with-meter]
 */
var fs = require('fs');
var path = require('path');

var argv = process.argv.slice(2);
var includePricePerMeter = argv.indexOf('--with-meter') >= 0;

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

function recordUpdate(byIdObj, id, assigns) {
  if (!assigns || assigns.length === 0) {
    return;
  }
  var setLine = 'UPDATE `products` SET ' + assigns.join(', ') + ' WHERE `id` = ' + id + ';';
  byIdObj[id] = setLine;
}

var srcPath = path.join(__dirname, 'products.sql');
var fullImportPath = path.join(__dirname, 'products_full_import_prices_usd_x88.sql');
var outPath = path.join(__dirname, 'products_full_import_prices_sales_tiers_only_usd_x88.sql');
var byId = {};

if (fs.existsSync(fullImportPath)) {
  var fullRaw = fs.readFileSync(fullImportPath, 'utf8').split(/\r?\n/);
  var qi;
  for (qi = 0; qi < fullRaw.length; qi++) {
    var row = parseFullImportUpdateLine(fullRaw[qi]);
    if (!row) {
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
  process.stderr.write('Loaded tiers from LLumar SQL: ' + Object.keys(byId).length + ' ids.\n');
} else {
  process.stderr.write(
    'WARN: не найден ' +
      fullImportPath +
      ' — генерируйте только из products.sql; для ступеней 10–19/20+ сначала запустите build_products_prices_usd_x88.js\r\n'
  );
}

var raw = fs.readFileSync(srcPath, 'utf8');
var lines = raw.split(/\r?\n/);
var gotInsert = false;

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

    var fqDump = fillTierQuartetFromPartial(price14, price59, price1019, price20p);
    var assignsDump = assignsFromFilledTiers(
      fqDump[0],
      fqDump[1],
      fqDump[2],
      fqDump[3],
      priceMeter,
      includePricePerMeter
    );

    recordUpdate(byId, dumpId, assignsDump);
  } catch (e) {
    process.stderr.write('skip line ' + (li + 1) + ': ' + String(e.message) + '\n');
  }
}

var ids = Object.keys(byId).map(Number).sort(function (a, b) {
  return a - b;
});
var hdr =
  '-- Продажа (только рулоны): закупку, доставку, purchase_delivered_per_meter не трогаем.\n' +
  '-- По умолчанию обновляются только price_1_4 … price_20_plus (цена за метр — отдельно в приложении/приходе).\n' +
  '-- Чтобы включить price_per_meter из LLumar как раньше: node build_sales_tiers_only_sql.js --with-meter\n' +
  '-- Источник LLumar: products_full_import_prices_usd_x88.sql (node build_products_prices_usd_x88.js);\n' +
  '-- пустые J/K → хвост ступени как последняя заданная колонка H–K.\n' +
  '-- Остальные id — из products.sql; = NULL не пишем.\n'
  + '-- products.php: пустой POST числовых полей не перезаписывает значение через COALESCE(?, column).\n'
  + '-- Regenerate: node example/new/build_sales_tiers_only_sql.js [--with-meter]'
  + '\n-- Rows: ' + ids.length + ' UPDATE statements.'
  + '\n-- Generated: ' + new Date().toISOString()
  + '\n\nSET NAMES utf8mb4;'
  + '\nSTART TRANSACTION;\n\n';

var body = [];
for (var j = 0; j < ids.length; j++) {
  body.push('-- id=' + ids[j]);
  body.push(byId[ids[j]]);
}

var footer = '\nCOMMIT;\n';
fs.writeFileSync(outPath, hdr + body.join('\n') + footer, 'utf8');
console.log(
  'Wrote ' +
    ids.length +
    ' UPDATEs to products_full_import_prices_sales_tiers_only_usd_x88.sql' +
    (includePricePerMeter ? ' (with price_per_meter)' : ' (tiers only, no price_per_meter)')
);
