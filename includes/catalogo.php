<?php
// Funciones de catálogo para empresas. Las que escriben en la base se llaman DENTRO de una transacción.

function empresa_del_usuario(): ?array {
    $emps = q('SELECT e.* FROM empresa_usuarios eu JOIN empresas e ON e.id_emp=eu.id_emp WHERE eu.id_usr=?', [$_SESSION['id_usr']])->fetchAll();
    if (!$emps) return null;
    $emp = $emps[0];
    foreach ($emps as $x) if (isset($_GET['emp']) && $x['id_emp'] == (int)$_GET['emp']) $emp = $x;
    return $emp;
}
function pedir_admin(string $txt): string {
    return es_admin() ? '<a href="admin_catalogo.php">Crear ahora (administrador)</a>'
        : '<a target="_blank" href="https://wa.me/' . WHATSAPP_ADMIN . '?text=' . rawurlencode('Hola, quiero solicitar: ' . $txt) . '">Solicítalo al administrador</a>';
}
function opciones_variantes(): array {
    $atrs = q('SELECT id_atr, nom_atr FROM atributos ORDER BY id_atr')->fetchAll(); $vals = [];
    foreach (q('SELECT id_atv,id_atr,valor_atv,hex_atv FROM atributo_valores ORDER BY valor_atv')->fetchAll() as $v) $vals[$v['id_atr']][] = $v;
    return [$atrs, $vals];
}
function es_unico_vendedor(int $idProd, int $idEmp): bool {
    return !q('SELECT 1 FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var WHERE v.id_prod=? AND o.id_emp<>? LIMIT 1', [$idProd, $idEmp])->fetch();
}
// Claves de las variantes que la empresa ya vende: id_prod => ['3_12', '0', ...]  (ids de opción ordenados por atributo)
function claves_que_vende(int $idEmp): array {
    $r = [];
    foreach (q("SELECT v.id_prod, (SELECT GROUP_CONCAT(vv.id_atv ORDER BY vv.id_atr SEPARATOR '_') FROM variante_valores vv WHERE vv.id_var=v.id_var) AS k
                FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var WHERE o.id_emp=?", [$idEmp])->fetchAll() as $x) $r[$x['id_prod']][] = $x['k'] ?? '0';
    return $r;
}
function crear_producto(string $nom, int $cat, string $desc): int {
    global $pdo;
    if ($nom === '' || mb_strlen($nom) > 150 || !q("SELECT 1 FROM categorias WHERE id_cat=? AND est_cat='A'", [$cat])->fetch()) throw new RuntimeException('Escribe el nombre del producto y elige una categoría.');
    $base = substr(slugify($nom) ?: 'producto', 0, 150); $slug = $base; $n = 2;
    while (q('SELECT 1 FROM productos WHERE slug_prod=?', [$slug])->fetch()) $slug = $base . '-' . $n++;
    q('INSERT INTO productos (id_cat,nom_prod,slug_prod,desc_prod) VALUES (?,?,?,?)', [$cat, $nom, $slug, $desc ?: null]);
    return (int)$pdo->lastInsertId();
}

// Crea UNA variante + oferta + fotos por cada fila v[clave] del formulario (clave = ids de opciones unidos con "_", "0" = sin variante)
function publicar_combinaciones(int $idEmp, int $idProd, array $post, array $files): int {
    global $pdo;
    $rows = $post['v'] ?? [];
    if (!is_array($rows) || !$rows) throw new RuntimeException('Marca al menos una variante (o "Producto sin variantes") y escribe su precio.');
    if (count($rows) > 60) throw new RuntimeException('Máximo 60 variantes por publicación.');
    $n = 0;
    foreach ($rows as $key => $r) {
        $key = (string)$key;
        if (!preg_match('/^\d+(_\d+)*$/', $key) || !is_array($r)) throw new RuntimeException('Variante no válida.');
        $sel = [];                                              // id_atr => id_atv, solo opciones que ya existen
        if ($key !== '0') foreach (explode('_', $key) as $atv) {
            $at = q('SELECT id_atr FROM atributo_valores WHERE id_atv=?', [(int)$atv])->fetchColumn();
            if (!$at || isset($sel[(int)$at])) throw new RuntimeException('Opción de variante no válida.');
            $sel[(int)$at] = (int)$atv;
        }
        $label = $sel ? implode(' / ', array_map(fn($a) => q('SELECT valor_atv FROM atributo_valores WHERE id_atv=?', [$a])->fetchColumn(), $sel)) : 'Única';
        $precio = (float)str_replace(',', '.', $r['precio'] ?? 0);
        $desc = (float)str_replace(',', '.', $r['desc'] ?? 0) ?: null;
        $stock = max(0, min(999999, (int)($r['stock'] ?? 0)));
        if ($precio <= 0 || $precio > 99999) throw new RuntimeException("Indica el precio de «{$label}».");
        if ($desc !== null && $desc >= $precio) throw new RuntimeException("La promoción de «{$label}» debe ser menor a su precio.");

        $cond = '(SELECT COUNT(*) FROM variante_valores x WHERE x.id_var=v.id_var) = ?'; $par = [$idProd, count($sel)];
        if ($sel) {
            $cond .= ' AND (SELECT COUNT(*) FROM variante_valores x WHERE x.id_var=v.id_var AND x.id_atv IN (' . implode(',', array_fill(0, count($sel), '?')) . ')) = ?';
            array_push($par, ...array_values($sel)); $par[] = count($sel);
        }
        $idVar = q("SELECT v.id_var FROM producto_variantes v WHERE v.id_prod=? AND $cond LIMIT 1", $par)->fetchColumn();
        if (!$idVar) {
            q('INSERT INTO producto_variantes (id_prod) VALUES (?)', [$idProd]); $idVar = (int)$pdo->lastInsertId();
            foreach ($sel as $at => $vl) q('INSERT INTO variante_valores (id_var,id_atr,id_atv) VALUES (?,?,?)', [$idVar, $at, $vl]);
        }
        if (q('SELECT 1 FROM ofertas WHERE id_emp=? AND id_var=?', [$idEmp, $idVar])->fetch()) throw new RuntimeException("Ya vendes «{$label}» en este producto.");
        q('INSERT INTO ofertas (id_emp,id_var,precio_ofe,precio_desc_ofe,stock_ofe) VALUES (?,?,?,?,?)', [$idEmp, $idVar, $precio, $desc, $stock]);
        if ($stock > 0) q("INSERT INTO inventario_movimientos (id_ofe,id_usr,tipo_mov,cant_mov,stock_res_mov,nota_mov) VALUES (?,?, 'entrada',?,?, 'Alta de oferta')",
                          [$pdo->lastInsertId(), $_SESSION['id_usr'], $stock, $stock]);
        guardar_fotos($idProd, (int)$idVar, $idEmp, $files['fotos_' . $key] ?? [], 5);
        $n++;
    }
    return $n;
}

// ===== BORRADO =====
// Con pedidos previos no se puede borrar (historial): se pausa. Sin pedidos se borra y se limpia lo que quede huérfano.
function eliminar_oferta(int $idOfe, int $idEmp): string {
    $o = q('SELECT * FROM ofertas WHERE id_ofe=? AND id_emp=? FOR UPDATE', [$idOfe, $idEmp])->fetch();
    if (!$o) throw new RuntimeException('Oferta no encontrada.');
    q('DELETE FROM carrito WHERE id_ofe=?', [$idOfe]);
    if (q('SELECT 1 FROM pedido_items WHERE id_ofe=? LIMIT 1', [$idOfe])->fetch()) { q("UPDATE ofertas SET est_ofe='I' WHERE id_ofe=?", [$idOfe]); return 'pausada'; }
    q('DELETE FROM oferta_costos WHERE id_ofe=?', [$idOfe]);
    q('DELETE FROM inventario_movimientos WHERE id_ofe=?', [$idOfe]);
    q('DELETE FROM ofertas WHERE id_ofe=?', [$idOfe]);
    limpiar_variante((int)$o['id_var']);
    return 'borrada';
}
function limpiar_variante(int $idVar): void {                  // si nadie la vende, se borra la variante y sus fotos
    if (q('SELECT 1 FROM ofertas WHERE id_var=? LIMIT 1', [$idVar])->fetch()) return;
    $idProd = (int)q('SELECT id_prod FROM producto_variantes WHERE id_var=?', [$idVar])->fetchColumn();
    foreach (q('SELECT url_img FROM producto_imagenes WHERE id_var=?', [$idVar])->fetchAll(PDO::FETCH_COLUMN) as $u) borrar_archivo_foto($u);
    q('DELETE FROM producto_imagenes WHERE id_var=?', [$idVar]);
    q('DELETE FROM variante_valores WHERE id_var=?', [$idVar]);
    q('DELETE FROM producto_variantes WHERE id_var=?', [$idVar]);
    if ($idProd) limpiar_producto($idProd);
}
function limpiar_producto(int $idProd): void {                 // sin variantes: se borra (o se oculta si tiene opiniones)
    if (q('SELECT 1 FROM producto_variantes WHERE id_prod=? LIMIT 1', [$idProd])->fetch()) return;
    foreach (q('SELECT url_img FROM producto_imagenes WHERE id_prod=?', [$idProd])->fetchAll(PDO::FETCH_COLUMN) as $u) borrar_archivo_foto($u);
    q('DELETE FROM producto_imagenes WHERE id_prod=?', [$idProd]);
    if (q('SELECT 1 FROM resenas WHERE id_prod=? LIMIT 1', [$idProd])->fetch()) { q("UPDATE productos SET est_prod='I' WHERE id_prod=?", [$idProd]); return; }
    q('DELETE FROM producto_atributos WHERE id_prod=?', [$idProd]);
    q('DELETE FROM productos WHERE id_prod=?', [$idProd]);
}
function borrar_archivo_foto(string $url): void {
    if (str_starts_with($url, 'imagen/productos/') && !str_contains($url, '..')) @unlink(__DIR__ . '/../' . $url);
}
function eliminar_producto_empresa(int $idProd, int $idEmp): array {   // [borradas, pausadas]
    $b = $p = 0;
    foreach (q('SELECT o.id_ofe FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var WHERE o.id_emp=? AND v.id_prod=?', [$idEmp, $idProd])->fetchAll(PDO::FETCH_COLUMN) as $id)
        eliminar_oferta((int)$id, $idEmp) === 'borrada' ? $b++ : $p++;
    return [$b, $p];
}
function msg_borrado(int $b, int $p): string {
    return ($b ? "Se eliminaron $b variante(s). " : '') . ($p ? "$p variante(s) ya tenían pedidos: se pausaron para conservar el historial." : '');
}

// ===== FORMULARIO: marcar varias variantes y llenar precio/stock/fotos de cada combinación =====
function form_variantes(array $yaVende, ?int $prodFijo = null): void {
    [$atrs, $vals] = opciones_variantes();
    $j = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT; ?>
<div id="picker"><label><b>Variantes que vas a vender</b></label>
<p class="hint" style="margin-top:0">Marca todas las que tengas: se crea una variante por cada combinación (ej. Rojo + Azul con 250 ml + 500 ml = 4), cada una con su precio, stock y fotos.</p>
<?php foreach ($atrs as $at): ?><div class="var-grupo"><small><b><?= h($at['nom_atr']) ?></b></small><div class="var-opts">
  <?php foreach ($vals[$at['id_atr']] ?? [] as $v): ?><label class="opt"><input type="checkbox" class="pick" data-atr="<?= $at['id_atr'] ?>" data-nom="<?= h($v['valor_atv']) ?>" value="<?= $v['id_atv'] ?>">
    <?php if ($v['hex_atv']): ?><span class="sw" style="background:#<?= h($v['hex_atv']) ?>"></span><?php endif; ?><?= h($v['valor_atv']) ?></label><?php endforeach; ?>
  <?php if (empty($vals[$at['id_atr']])): ?><small>Sin opciones todavía.</small><?php endif; ?></div></div><?php endforeach; ?>
<label class="opt" style="margin:6px 0"><input type="checkbox" id="sin_var"> Producto sin variantes (una sola presentación)</label>
<p class="hint">¿Falta una opción (otro color, otra talla)? <?= pedir_admin('una nueva opción de variante') ?></p>
<div class="gen-fila"><b>Para todas:</b>
  <input id="gen_precio" placeholder="Precio" size="7"><input id="gen_desc" placeholder="Promo" size="7"><input id="gen_stock" type="number" min="0" placeholder="Stock" value="0" style="width:80px"></div>
<table id="combos" style="display:none"><thead><tr><th>Variante</th><th>Precio</th><th>Promo</th><th>Stock</th><th>Fotos (1ª = vista previa, hasta 5)</th></tr></thead><tbody></tbody></table>
</div>
<script>
(function () {
  const YA = <?= json_encode($yaVende ?: new stdClass, $j) ?>, FIJO = <?= $prodFijo === null ? 'null' : (int)$prodFijo ?>;
  const picks = [...document.querySelectorAll('#picker .pick')], sinVar = document.getElementById('sin_var');
  const body = document.querySelector('#combos tbody'), selProd = document.getElementById('selProd');
  const gen = { precio: document.getElementById('gen_precio'), desc: document.getElementById('gen_desc'), stock: document.getElementById('gen_stock') };
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const prodActual = () => FIJO !== null ? String(FIJO) : (selProd ? selProd.value : '0');
  const cartesiano = arrs => arrs.reduce((acc, a) => acc.flatMap(x => a.map(y => [...x, y])), [[]]);
  function render() {
    const keep = {};
    body.querySelectorAll('tr[data-key]').forEach(tr => { const p = tr.querySelector('.r-precio'); if (p) keep[tr.dataset.key] = [p.value, tr.querySelector('.r-desc').value, tr.querySelector('.r-stock').value]; });
    const g = {}; picks.filter(p => p.checked).forEach(p => (g[p.dataset.atr] = g[p.dataset.atr] || []).push(p));
    const arrs = Object.keys(g).map(Number).sort((a, b) => a - b).map(k => g[k]);
    let combos = arrs.length ? cartesiano(arrs) : (sinVar.checked ? [[]] : []);
    const ya = new Set((YA[prodActual()] || []).map(String));
    body.innerHTML = '';
    combos.forEach(c => {
      const key = c.length ? c.map(x => x.value).join('_') : '0', label = c.length ? c.map(x => x.dataset.nom).join(' / ') : 'Única (sin variante)';
      const tr = document.createElement('tr'); tr.dataset.key = key;
      if (ya.has(key)) tr.innerHTML = `<td>${esc(label)}</td><td colspan="4"><i>✓ Ya la vendes (edítala desde la lista de tus variantes)</i></td>`;
      else { const k = (keep[key] || [gen.precio.value, gen.desc.value, gen.stock.value]).map(esc);
        tr.innerHTML = `<td><b>${esc(label)}</b></td><td><input class="r-precio" name="v[${key}][precio]" value="${k[0]}" size="7" required></td>
          <td><input class="r-desc" name="v[${key}][desc]" value="${k[1]}" size="7"></td><td><input class="r-stock" type="number" min="0" name="v[${key}][stock]" value="${k[2]}"></td>
          <td><input type="file" name="fotos_${key}[]" accept="image/*" multiple></td>`; }
      body.appendChild(tr);
    });
    document.getElementById('combos').style.display = combos.length ? 'table' : 'none';
  }
  picks.forEach(p => p.addEventListener('change', () => { if (p.checked) sinVar.checked = false; render(); }));
  sinVar.addEventListener('change', () => { if (sinVar.checked) picks.forEach(p => p.checked = false); render(); });
  ['precio', 'desc', 'stock'].forEach(n => gen[n].addEventListener('input', () => body.querySelectorAll('.r-' + n).forEach(i => i.value = gen[n].value)));
  if (selProd) selProd.addEventListener('change', render);
})();
</script>
<?php }
