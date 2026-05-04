/**
 * Read products.sql (phpMyAdmin dump) → products_full_import_prices_sales_tiers_only_usd_x88.sql
 * Usage: node build_sales_tiers_only_sql.js
 */
var fs = require('fs');
var path = require('path');

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

function formatPrice(val) {
  if (val === undefined || val === '' || /^NULL$/i.test(val)) {
    return 'NULL';
  }
  var s = String(val);
  return "'" + s.replace(/'/g, "''") + "'";
}

var srcPath = path.join(__dirname, 'products.sql');
var outPath = path.join(__dirname, 'products_full_import_prices_sales_tiers_only_usd_x88.sql');
var raw = fs.readFileSync(srcPath, 'utf8');
var lines = raw.split(/\r?\n/);
var byId = {};
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
    var id = parseInt(parts[0], 10);
    if (isNaN(id)) {
      continue;
    }
    var price14 = parts[5];
    var price59 = parts[6];
    var price1019 = parts[7];
    var price20p = parts[8];
    var priceMeter = parts[9];
    var setLine = 'UPDATE `products` SET '
      + '`price_1_4` = ' + formatPrice(price14) + ', '
      + '`price_5_9` = ' + formatPrice(price59) + ', '
      + '`price_10_19` = ' + formatPrice(price1019) + ', '
      + '`price_20_plus` = ' + formatPrice(price20p) + ', '
      + '`price_per_meter` = ' + formatPrice(priceMeter)
      + ' WHERE `id` = ' + id + ';';
    byId[id] = setLine;
  } catch (e) {
    process.stderr.write('skip line ' + (li + 1) + ': ' + String(e.message) + '\n');
  }
}

var ids = Object.keys(byId).map(Number).sort(function (a, b) { return a - b; });
var hdr = '-- Generated from example/new/products.sql (phpMyAdmin dump).'
  + '\n-- Updates price_1_4, price_5_9, price_10_19, price_20_plus, price_per_meter only.'
  + '\n-- Does NOT change purchase_price, delivery_price, b24 IDs, sync status, etc.'
  + '\n-- Rows: ' + ids.length + ' (products in dump)'
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
console.log('Wrote ' + ids.length + ' UPDATEs to products_full_import_prices_sales_tiers_only_usd_x88.sql');
