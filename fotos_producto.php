<?php
require_once 'config.php';
requiere_login();
$emps = q('SELECT e.* FROM empresa_usuarios eu JOIN empresas e ON e.id_emp=eu.id_emp WHERE eu.id_usr=?', [$_SESSION['id_usr']])->fetchAll();
if (!$emps) { header('Location: registro_empresa.php'); exit; }
$emp = $emps[0];
foreach ($emps as $x) if (isset($_GET['emp']) && $x['id_emp'] == (int)$_GET['emp']) $emp = $x;
$idEmp = (int)$emp['id_emp'];
$SQL_VAR = "(SELECT GROUP_CONCAT(av.valor_atv ORDER BY av.id_atr SEPARATOR ' / ') FROM variante_valores vv JOIN atributo_valores av ON av.id_atv=vv.id_atv WHERE vv.id_var=v.id_var)";
$ofe = q("SELECT o.id_ofe, o.id_var, v.id_prod, p.nom_prod, $SQL_VAR AS variante
          FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var JOIN productos p ON p.id_prod=v.id_prod
          WHERE o.id_ofe=? AND o.id_emp=?", [(int)($_GET['ofe'] ?? 0), $idEmp])->fetch();
if (!$ofe || $emp['est_emp'] !== 'A') { header('Location: panel_empresa.php?emp=' . $idEmp); exit; }
$propia = fn($url) => str_starts_with(basename($url), 'e' . $idEmp . '_');
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_ok()) throw new RuntimeException('Sesión expirada, intenta de nuevo.');
        $a = $_POST['accion'] ?? '';
        $pdo->beginTransaction();
        if ($a === 'subir') {
            $n = guardar_fotos((int)$ofe['id_prod'], (int)$ofe['id_var'], $idEmp, $_FILES['fotos'] ?? [], 5);
            if (!$n) throw new RuntimeException('Elige al menos una imagen.');
            $msg = "Se agregaron $n foto(s).";
        } else {
            $img = q('SELECT * FROM producto_imagenes WHERE id_img=? AND id_var=?', [(int)($_POST['id_img'] ?? 0), $ofe['id_var']])->fetch();
            if (!$img || !$propia($img['url_img'])) throw new RuntimeException('Solo puedes modificar las fotos que subió tu empresa.');
            if ($a === 'borrar') {
                q('DELETE FROM producto_imagenes WHERE id_img=?', [$img['id_img']]);
                if (str_starts_with($img['url_img'], 'imagen/productos/') && !str_contains($img['url_img'], '..')) @unlink(__DIR__ . '/' . $img['url_img']);
                $msg = 'Foto eliminada.';
            } elseif ($a === 'principal') {
                q('UPDATE producto_imagenes SET orden_img = LEAST(orden_img + 1, 250) WHERE id_prod=? AND id_var=? AND id_img<>?', [$ofe['id_prod'], $ofe['id_var'], $img['id_img']]);
                q('UPDATE producto_imagenes SET orden_img=0 WHERE id_img=?', [$img['id_img']]);
                $msg = 'Ahora es la foto principal (la que se ve primero).';
            }
        }
        $pdo->commit();
    } catch (RuntimeException $x) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = $x->getMessage(); }
      catch (Throwable $x)        { if ($pdo->inTransaction()) $pdo->rollBack(); $err = 'No se pudo guardar.'; }
}
$fotos = q('SELECT * FROM producto_imagenes WHERE id_prod=? AND id_var=? ORDER BY orden_img, id_img', [$ofe['id_prod'], $ofe['id_var']])->fetchAll();
$hermanas = q("SELECT o.id_ofe, $SQL_VAR AS variante, (SELECT COUNT(*) FROM producto_imagenes i WHERE i.id_var=o.id_var) AS n
               FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var WHERE o.id_emp=? AND v.id_prod=?", [$idEmp, $ofe['id_prod']])->fetchAll();
$titulo = 'Fotos — ' . $ofe['nom_prod']; require 'includes/header.php';
?>
<section class="sec"><h2>📷 FOTOS: <?= h(mb_strtoupper($ofe['nom_prod'])) ?> · <?= h($ofe['variante'] ?: 'Única') ?></h2>
<p style="text-align:center"><a href="editar_producto.php?emp=<?= $idEmp ?>&id_prod=<?= (int)$ofe['id_prod'] ?>">← Volver al producto</a></p>
<?= $msg ? '<p class="ok">' . h($msg) . '</p>' : '' ?><?= $err ? '<p class="error">' . h($err) . '</p>' : '' ?>
<?php if (count($hermanas) > 1): ?><p style="text-align:center">Ver fotos de otra variante:
  <?php foreach ($hermanas as $hm) echo '<a class="opt" style="margin:2px" href="?emp=' . $idEmp . '&ofe=' . $hm['id_ofe'] . '">' . h($hm['variante'] ?: 'Única') . ' (' . (int)$hm['n'] . ')</a> '; ?></p><?php endif; ?>
<p style="text-align:center;font-size:13px;color:#666;max-width:680px;margin:0 auto 18px">Estas fotos aparecen cuando el cliente elige <b><?= h($ofe['variante'] ?: 'esta variante') ?></b>. La primera es la <b>principal (vista previa)</b>; las demás son las otras vistas. Si una variante no tiene fotos, se usan las de otra variante del mismo color.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px;margin-bottom:26px">
<?php foreach ($fotos as $i => $f): ?>
  <div class="resumen-card" style="padding:10px">
    <img src="<?= h($f['url_img']) ?>" alt="" style="width:100%;height:160px;object-fit:cover;border-radius:4px">
    <p style="font-size:12px;margin:6px 0"><?= $i === 0 ? '⭐ <b>Principal</b>' : 'Vista ' . ($i + 1) ?><?= $propia($f['url_img']) ? '' : ' · <i>otra tienda</i>' ?></p>
    <?php if ($propia($f['url_img'])): ?><form method="post" style="display:flex;gap:6px;flex-wrap:wrap"><?= csrf() ?><input type="hidden" name="id_img" value="<?= $f['id_img'] ?>">
      <?php if ($i > 0): ?><button class="btn" name="accion" value="principal" style="padding:6px 10px;font-size:10px">Hacer principal</button><?php endif; ?>
      <button class="btn" name="accion" value="borrar" style="background:#a33;padding:6px 10px;font-size:10px" onclick="return confirm('¿Eliminar esta foto?')">Eliminar</button></form><?php endif; ?>
  </div>
<?php endforeach; if (!$fotos) echo '<p>Esta variante aún no tiene fotos propias.</p>'; ?></div>
<?php if (count($fotos) < 5): ?>
<form class="caja" style="margin:0" method="post" enctype="multipart/form-data"><?= csrf() ?><input type="hidden" name="accion" value="subir">
  <label>Agregar fotos (hasta <?= 5 - count($fotos) ?> más · jpg, png o webp · máx. 2 MB c/u)</label>
  <input type="file" name="fotos[]" accept="image/*" multiple required><button class="btn">Subir fotos</button></form>
<?php else: ?><p style="text-align:center;color:#666">Ya llegaste al máximo de 5 fotos para esta variante.</p><?php endif; ?>
</section>
<?php require 'includes/footer.php'; ?>
