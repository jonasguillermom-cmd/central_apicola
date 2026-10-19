<?php
require_once 'config.php';
if (!es_admin()) { http_response_code(403); exit('Acceso restringido.'); }
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_ok()) throw new RuntimeException('Sesión expirada, intenta de nuevo.');
        $a = $_POST['accion'] ?? '';
        if ($a === 'crear_cat') {
            $nom = trim($_POST['nom'] ?? '');
            if ($nom === '' || mb_strlen($nom) > 60) throw new RuntimeException('Escribe el nombre de la categoría (máx. 60).');
            if (q('SELECT 1 FROM categorias WHERE LOWER(nom_cat)=LOWER(?)', [$nom])->fetch()) throw new RuntimeException('Ya existe una categoría con ese nombre.');
            q('INSERT INTO categorias (nom_cat,desc_cat) VALUES (?,?)', [$nom, trim($_POST['desc'] ?? '') ?: null]);
            $msg = 'Categoría creada.';
        } elseif ($a === 'estado_cat') {
            q("UPDATE categorias SET est_cat=IF(est_cat='A','I','A') WHERE id_cat=?", [(int)($_POST['id'] ?? 0)]); $msg = 'Estado actualizado.';
        } elseif ($a === 'crear_atr') {
            $nom = trim($_POST['nom'] ?? '');
            if ($nom === '' || mb_strlen($nom) > 40) throw new RuntimeException('Escribe el nombre del atributo (ej. Color, Talla).');
            if (q('SELECT 1 FROM atributos WHERE LOWER(nom_atr)=LOWER(?)', [$nom])->fetch()) throw new RuntimeException('Ese atributo ya existe.');
            q('INSERT INTO atributos (nom_atr) VALUES (?)', [$nom]); $msg = 'Atributo creado.';
        } elseif ($a === 'crear_val') {
            $atr = (int)($_POST['id_atr'] ?? 0); $val = trim($_POST['valor'] ?? '');
            if (!q('SELECT 1 FROM atributos WHERE id_atr=?', [$atr])->fetch()) throw new RuntimeException('Elige un atributo.');
            if ($val === '' || mb_strlen($val) > 40) throw new RuntimeException('Escribe el valor (máx. 40).');
            $hex = !empty($_POST['con_color']) ? strtoupper(ltrim($_POST['hex'] ?? '', '#')) : null;
            if ($hex !== null && !preg_match('/^[0-9A-F]{6}$/', $hex)) throw new RuntimeException('Color no válido.');
            if (q('SELECT 1 FROM atributo_valores WHERE id_atr=? AND LOWER(valor_atv)=LOWER(?)', [$atr, $val])->fetch()) throw new RuntimeException('Ese valor ya existe en el atributo.');
            q('INSERT INTO atributo_valores (id_atr,valor_atv,hex_atv) VALUES (?,?,?)', [$atr, $val, $hex]); $msg = 'Valor agregado.';
        } elseif ($a === 'borrar_atr') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->beginTransaction();
            if (!q('SELECT 1 FROM atributos WHERE id_atr=? FOR UPDATE', [$id])->fetch()) throw new RuntimeException('Atributo no encontrado.');
            $uso = (int)q('SELECT COUNT(DISTINCT id_var) FROM variante_valores WHERE id_atr=?', [$id])->fetchColumn();
            if ($uso) throw new RuntimeException("No se puede borrar el atributo: $uso variante(s) de productos lo usan. Elimínalas primero en «Variantes».");
            q('DELETE FROM producto_atributos WHERE id_atr=?', [$id]);
            q('DELETE FROM atributo_valores WHERE id_atr=?', [$id]);
            q('DELETE FROM atributos WHERE id_atr=?', [$id]);
            $pdo->commit(); $msg = 'Atributo eliminado junto con sus opciones.';
        } elseif ($a === 'borrar_val') {
            $id = (int)($_POST['id'] ?? 0);
            if (!q('SELECT 1 FROM atributo_valores WHERE id_atv=?', [$id])->fetch()) throw new RuntimeException('Opción no encontrada.');
            $uso = (int)q('SELECT COUNT(*) FROM variante_valores WHERE id_atv=?', [$id])->fetchColumn();
            if ($uso) throw new RuntimeException("No se puede borrar esa opción: la usan $uso variante(s) de productos. Elimínalas primero en «Variantes».");
            q('DELETE FROM atributo_valores WHERE id_atv=?', [$id]); $msg = 'Valor eliminado.';
        }
    } catch (RuntimeException $x) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = $x->getMessage(); }
      catch (Throwable $x)        { if ($pdo->inTransaction()) $pdo->rollBack(); $err = 'No se pudo guardar.'; }
}
$cats = q("SELECT c.*, (SELECT COUNT(*) FROM productos x WHERE x.id_cat=c.id_cat) AS n FROM categorias c ORDER BY c.nom_cat")->fetchAll();
$atrs = q('SELECT * FROM atributos ORDER BY nom_atr')->fetchAll();
$vals = [];
foreach (q('SELECT * FROM atributo_valores ORDER BY valor_atv')->fetchAll() as $v) $vals[$v['id_atr']][] = $v;
$titulo = 'Admin - Catálogo'; require 'includes/header.php';
?>
<section class="sec"><h2>🗂️ CATÁLOGO</h2>
<p style="text-align:center"><a href="admin_empresas.php">← Empresas</a> · Las empresas solo pueden elegir lo que crees aquí · <a href="admin_variantes.php">🧩 Ver y eliminar variantes de productos</a></p>
<?= $msg ? '<p class="ok">' . h($msg) . '</p>' : '' ?><?= $err ? '<p class="error">' . h($err) . '</p>' : '' ?>

<h3>Categorías</h3>
<table><tr><th>Categoría</th><th>Productos</th><th>Estado</th><th></th></tr>
<?php foreach ($cats as $c): ?><tr><td><b><?= h($c['nom_cat']) ?></b><?= $c['desc_cat'] ? '<br><small>' . h($c['desc_cat']) . '</small>' : '' ?></td><td><?= (int)$c['n'] ?></td>
  <td><?= $c['est_cat'] === 'A' ? '✅ Activa' : '⛔ Inactiva' ?></td>
  <td><form method="post"><?= csrf() ?><input type="hidden" name="accion" value="estado_cat"><input type="hidden" name="id" value="<?= $c['id_cat'] ?>"><button class="btn"><?= $c['est_cat'] === 'A' ? 'Desactivar' : 'Activar' ?></button></form></td></tr>
<?php endforeach; if (!$cats) echo '<tr><td colspan="4">Aún no hay categorías.</td></tr>'; ?></table>
<form class="caja" style="margin:14px 0;max-width:520px" method="post"><?= csrf() ?><input type="hidden" name="accion" value="crear_cat">
  <label>Nueva categoría</label><input name="nom" maxlength="60" placeholder="Ej. Velas" required>
  <label>Descripción (opcional)</label><input name="desc" maxlength="255"><button class="btn">Crear categoría</button></form>

<h3 style="margin-top:36px">Variantes: atributos y sus opciones</h3>
<?php foreach ($atrs as $a): ?>
  <div class="resumen-card" style="max-width:none;margin-bottom:14px"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><b><?= h($a['nom_atr']) ?></b>
      <form method="post" onsubmit="return confirm('¿Eliminar el atributo «<?= h($a['nom_atr']) ?>» con todas sus opciones?')"><?= csrf() ?><input type="hidden" name="accion" value="borrar_atr"><input type="hidden" name="id" value="<?= $a['id_atr'] ?>">
        <button class="btn" style="background:#a33;padding:6px 12px;font-size:10px">🗑 Eliminar atributo</button></form></div>
    <div class="var-opts" style="margin-top:8px">
    <?php foreach ($vals[$a['id_atr']] ?? [] as $v): ?>
      <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar «<?= h($v['valor_atv']) ?>»?')"><?= csrf() ?><input type="hidden" name="accion" value="borrar_val"><input type="hidden" name="id" value="<?= $v['id_atv'] ?>">
        <span class="opt"><?php if ($v['hex_atv']): ?><span class="sw" style="background:#<?= h($v['hex_atv']) ?>"></span><?php endif; ?><?= h($v['valor_atv']) ?>
        <button title="Eliminar" style="border:0;background:none;color:#c0392b;cursor:pointer">✕</button></span></form>
    <?php endforeach; if (empty($vals[$a['id_atr']])) echo '<small>Sin opciones todavía.</small>'; ?></div></div>
<?php endforeach; if (!$atrs) echo '<p>Aún no hay atributos. Crea el primero (ej. Color, Presentación, Talla).</p>'; ?>
<div style="display:flex;gap:20px;flex-wrap:wrap">
<form class="caja" style="margin:10px 0;max-width:340px" method="post"><?= csrf() ?><input type="hidden" name="accion" value="crear_atr">
  <label>Nuevo atributo</label><input name="nom" maxlength="40" placeholder="Ej. Color, Talla, Aroma" required><button class="btn">Crear atributo</button></form>
<form class="caja" style="margin:10px 0;max-width:340px" method="post"><?= csrf() ?><input type="hidden" name="accion" value="crear_val">
  <label>Atributo</label><select name="id_atr" required><?php foreach ($atrs as $a) echo '<option value="' . $a['id_atr'] . '">' . h($a['nom_atr']) . '</option>'; ?></select>
  <label>Nueva opción</label><input name="valor" maxlength="40" placeholder="Ej. Rojo, 250 ml, Lavanda" required>
  <label class="opt" style="margin-bottom:10px"><input type="checkbox" name="con_color" value="1"> Mostrar con color</label>
  <input type="color" name="hex" value="#e8a317" style="height:38px;padding:2px">
  <button class="btn">Agregar opción</button></form></div>
</section>
<?php require 'includes/footer.php'; ?>
