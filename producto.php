<?php
require_once 'config.php';
$p = q("SELECT p.*, c.nom_cat FROM productos p JOIN categorias c ON c.id_cat=p.id_cat WHERE p.slug_prod=? AND p.est_prod='A'", [$_GET['slug'] ?? ''])->fetch();
if (!$p) { http_response_code(404); $titulo = 'No encontrado'; require 'includes/header.php';
    echo '<div class="container section"><div class="loading-prod">Producto no encontrado.</div></div>'; require 'includes/footer.php'; exit; }
$logged = !empty($_SESSION['id_usr']); $flash = ''; $errRes = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', ['resena', 'borrar_resena'], true)) {
    requiere_login();
    $uid = (int)$_SESSION['id_usr'];
    if (!csrf_ok()) $errRes = 'Sesión expirada, intenta de nuevo.';
    elseif ($_POST['accion'] === 'borrar_resena') {
        q('DELETE FROM resenas WHERE id_usr=? AND id_prod=?', [$uid, $p['id_prod']]);
        header('Location: producto.php?slug=' . urlencode($p['slug_prod']) . '#opiniones'); exit;
    } else {
        $cal = (int)($_POST['calif'] ?? 0); $txt = trim($_POST['texto'] ?? '');
        if ($cal < 1 || $cal > 5) $errRes = 'Elige una calificación de 1 a 5 estrellas.';
        elseif (mb_strlen($txt) > 500) $errRes = 'El comentario no puede pasar de 500 caracteres.';
        else {   // compra verificada: ya recibió este producto
            $pei = q("SELECT pi.id_pei FROM pedido_items pi JOIN pedidos_empresa pe ON pe.id_pem=pi.id_pem JOIN pedidos pd ON pd.id_ped=pe.id_ped
                      JOIN ofertas o ON o.id_ofe=pi.id_ofe JOIN producto_variantes v ON v.id_var=o.id_var
                      WHERE pd.id_usr=? AND v.id_prod=? AND pe.estado_pem='entregado' ORDER BY pi.id_pei DESC LIMIT 1", [$uid, $p['id_prod']])->fetchColumn() ?: null;
            q('INSERT INTO resenas (id_prod,id_usr,id_pei,calif_res,texto_res) VALUES (?,?,?,?,?)
               ON DUPLICATE KEY UPDATE calif_res=VALUES(calif_res), texto_res=VALUES(texto_res), id_pei=VALUES(id_pei)',
              [$p['id_prod'], $uid, $pei, $cal, $txt ?: null]);
            header('Location: producto.php?slug=' . urlencode($p['slug_prod']) . '&gracias=1#opiniones'); exit;
        }
    }
}
$miRes = $logged ? q('SELECT * FROM resenas WHERE id_usr=? AND id_prod=?', [$_SESSION['id_usr'], $p['id_prod']])->fetch() : null;
$allImgs = q('SELECT id_var, url_img FROM producto_imagenes WHERE id_prod=? ORDER BY orden_img, id_img', [$p['id_prod']])->fetchAll();
$ofertas = q("SELECT o.id_ofe, o.id_var, o.precio_ofe, o.precio_desc_ofe, o.stock_ofe, e.nom_emp,
      GROUP_CONCAT(av.valor_atv ORDER BY av.id_atr SEPARATOR ' / ') AS variante
    FROM ofertas o JOIN producto_variantes v ON v.id_var=o.id_var AND v.est_var='A'
    JOIN empresas e ON e.id_emp=o.id_emp AND e.est_emp='A'
    LEFT JOIN variante_valores vv ON vv.id_var=v.id_var LEFT JOIN atributo_valores av ON av.id_atv=vv.id_atv
    WHERE v.id_prod=? AND o.est_ofe='A' AND o.stock_ofe>0
    GROUP BY o.id_ofe, o.id_var, o.precio_ofe, o.precio_desc_ofe, o.stock_ofe, e.nom_emp
    ORDER BY COALESCE(o.precio_desc_ofe,o.precio_ofe)", [$p['id_prod']])->fetchAll();
// Fotos por variante: 1) las de la variante, 2) las de otra variante del mismo color, 3) las generales, 4) la primera que exista
$colorDe = [];
foreach (q('SELECT vv.id_var, vv.id_atv FROM variante_valores vv JOIN atributo_valores av ON av.id_atv=vv.id_atv AND av.hex_atv IS NOT NULL
            JOIN producto_variantes v ON v.id_var=vv.id_var WHERE v.id_prod=?', [$p['id_prod']])->fetchAll() as $r) $colorDe[(int)$r['id_var']] = (int)$r['id_atv'];
$imgsDe = function (int $idVar) use ($allImgs, $colorDe) {
    $r = array_filter($allImgs, fn($i) => $i['id_var'] !== null && (int)$i['id_var'] === $idVar);
    if (!$r && isset($colorDe[$idVar])) {
        $mismos = array_keys(array_filter($colorDe, fn($c) => $c === $colorDe[$idVar]));
        $r = array_filter($allImgs, fn($i) => $i['id_var'] !== null && in_array((int)$i['id_var'], $mismos, true));
    }
    if (!$r) $r = array_filter($allImgs, fn($i) => $i['id_var'] === null);
    if (!$r && $allImgs) $r = [$allImgs[0]];
    return array_values(array_column($r, 'url_img'));
};
$galerias = [];
foreach ($ofertas as $o) $galerias[$o['id_ofe']] = $imgsDe((int)$o['id_var']);
$g0 = $ofertas ? $galerias[$ofertas[0]['id_ofe']] : $imgsDe(0);
$rel = q(SQL_TARJETAS . ' WHERE d.id_prod<>? ORDER BY RAND() LIMIT 4', [$p['id_prod']])->fetchAll();
$res = q("SELECT r.*, u.nom_usr FROM resenas r JOIN usuarios u ON u.id_usr=r.id_usr WHERE r.id_prod=? AND r.est_res='A' ORDER BY r.fecha_res DESC LIMIT 20", [$p['id_prod']])->fetchAll();
$prom = $res ? array_sum(array_column($res, 'calif_res')) / count($res) : 0;
$estrellas = fn($n) => str_repeat('★', (int)$n) . str_repeat('☆', 5 - (int)$n);
$etq = function ($o) use ($ofertas) {   // nombre del botón: presentación (+ vendedor si se repite)
    $n = $o['variante'] ?: 'Única';
    foreach ($ofertas as $x) if ($x['id_ofe'] !== $o['id_ofe'] && ($x['variante'] ?: 'Única') === $n) return $n . ' · ' . $o['nom_emp'];
    return $n;
};
$titulo = $p['nom_prod'] . ' — Central Apícola del Usumacinta';
require 'includes/header.php';
$o0 = $ofertas[0] ?? null;
$fin = fn($o) => dinero($o['precio_desc_ofe'] ?? $o['precio_ofe']);
?>
<div class="breadcrumb" style="max-width:1200px;margin:0 auto;padding:16px 24px;font-size:13px">
  <a href="index.php">Inicio</a> <span class="sep">›</span> <a href="categoria.php?id=<?= (int)$p['id_cat'] ?>"><?= h($p['nom_cat']) ?></a> <span class="sep">›</span> <span class="current"><?= h($p['nom_prod']) ?></span></div>

<div class="product-wrap">
  <div class="gallery">
    <div class="gallery-main"><img id="mainImg" src="<?= h($g0[0] ?? 'imagen/logo_centro.png') ?>" alt="<?= h($p['nom_prod']) ?>" onerror="this.style.background='#f5f0e8'"></div>
    <div class="gallery-thumbs" id="thumbs"></div>
  </div>
  <div class="product-info">
    <p class="prod-brand">Central Apícola del Usumacinta</p>
    <h1 class="prod-title"><?= h($p['nom_prod']) ?></h1>
    <?php if ($o0): ?>
    <div class="prod-vendedor"><span class="icono-tienda">🏪</span><span>Vendido por <b id="vend-nombre"><?= h($o0['nom_emp']) ?></b></span></div>
    <div class="prod-price-wrap"><span class="price-main" id="price-main"><?= $fin($o0) ?></span>
      <s id="price-old" style="color:#999;margin-left:8px;font-size:15px"><?= $o0['precio_desc_ofe'] ? dinero($o0['precio_ofe']) : '' ?></s></div>
    <?php if ($p['desc_prod']): ?><p class="prod-desc"><?= nl2br(h($p['desc_prod'])) ?></p><?php endif; ?>
    <form method="post" action="carrito.php"><?= csrf() ?><input type="hidden" name="accion" value="agregar">
      <input type="hidden" name="id_ofe" id="id_ofe" value="<?= $o0['id_ofe'] ?>">
      <p class="var-label">Presentación</p>
      <div class="size-options"><?php foreach ($ofertas as $i => $o): ?>
        <button type="button" class="size-btn <?= $i === 0 ? 'active' : '' ?>" onclick="elegirOferta(this)" data-id="<?= $o['id_ofe'] ?>"
          data-precio="<?= h($fin($o)) ?>" data-normal="<?= $o['precio_desc_ofe'] ? h(dinero($o['precio_ofe'])) : '' ?>"
          data-emp="<?= h($o['nom_emp']) ?>" data-stock="<?= min(99, (int)$o['stock_ofe']) ?>"><?= h($etq($o)) ?></button><?php endforeach; ?></div>
      <p class="var-label">Cantidad</p>
      <div class="qty-wrap"><button type="button" class="qty-btn" onclick="cambiarQty(-1)">−</button>
        <input class="qty-input" id="qty" name="cant" type="number" value="1" min="1" max="<?= min(99, (int)$o0['stock_ofe']) ?>" readonly>
        <button type="button" class="qty-btn" onclick="cambiarQty(1)">+</button></div>
      <div class="cta-wrap"><button class="btn-carrito">Agregar al carrito</button></div>
    </form>
    <?php else: ?><p class="prod-desc" style="color:#a33;font-weight:700">Producto agotado por el momento.</p><?php endif; ?>
    <div class="accordion">
      <?php if ($p['desc_prod']): ?><div class="accordion-item open"><button class="accordion-header" onclick="this.closest('.accordion-item').classList.toggle('open')">Descripción <span class="accordion-icon">+</span></button>
        <div class="accordion-body"><p><?= nl2br(h($p['desc_prod'])) ?></p></div></div><?php endif; ?>
      <div class="accordion-item"><button class="accordion-header" onclick="this.closest('.accordion-item').classList.toggle('open')">Modo de uso <span class="accordion-icon">+</span></button>
        <div class="accordion-body"><p>Producto artesanal elaborado con ingredientes naturales. Conservar en lugar fresco y seco alejado de la luz directa.</p></div></div>
      <?php if (count($ofertas) > 1): ?><div class="accordion-item"><button class="accordion-header" onclick="this.closest('.accordion-item').classList.toggle('open')">Opciones disponibles <span class="accordion-icon">+</span></button>
        <div class="accordion-body"><ul><?php foreach ($ofertas as $o) echo '<li>' . h($o['variante'] ?: 'Única') . ' — ' . $fin($o) . ' (' . h($o['nom_emp']) . ')</li>'; ?></ul></div></div><?php endif; ?>
    </div>
  </div>
</div>

<?php if ($rel): ?><section style="max-width:1200px;margin:0 auto;padding:12px 24px 56px"><h2 class="section-title left">También te puede interesar</h2>
  <div class="prod-grid"><?php foreach ($rel as $r) echo tarjeta($r); ?></div></section><?php endif; ?>

<section id="opiniones" style="max-width:1200px;margin:0 auto;padding:12px 24px 64px"><h2 class="section-title left">Opiniones del producto</h2>
  <?php if (isset($_GET['gracias'])): ?><p class="ok" style="max-width:640px;margin:0 auto 16px">✓ ¡Gracias! Tu opinión fue guardada.</p><?php endif; ?>
  <?php if ($errRes): ?><p class="error" style="max-width:640px;margin:0 auto 16px"><?= h($errRes) ?></p><?php endif; ?>
  <?php if ($logged): ?>
  <form class="form-resena" method="post"><?= csrf() ?><input type="hidden" name="accion" value="resena">
    <b><?= $miRes ? 'Editar tu opinión' : 'Deja tu opinión' ?></b>
    <select name="calif" required><option value="">Calificación…</option>
      <?php foreach ([5 => 'Excelente', 4 => 'Muy bueno', 3 => 'Bueno', 2 => 'Regular', 1 => 'Malo'] as $n => $t) echo '<option value="' . $n . '"' . (($miRes['calif_res'] ?? 0) == $n ? ' selected' : '') . '>' . str_repeat('★', $n) . str_repeat('☆', 5 - $n) . ' — ' . $t . '</option>'; ?></select>
    <textarea name="texto" rows="3" maxlength="500" placeholder="Cuéntanos qué te pareció (opcional)"><?= h($miRes['texto_res'] ?? '') ?></textarea>
    <button class="btn"><?= $miRes ? 'Guardar cambios' : 'Publicar opinión' ?></button>
    <?php if ($miRes): ?><button class="btn" style="background:#a33" name="accion" value="borrar_resena" formnovalidate onclick="return confirm('¿Borrar tu opinión?')">Borrar</button><?php endif; ?>
  </form>
  <?php else: ?><p style="text-align:center;margin-bottom:18px"><a href="login.php?volver=<?= urlencode($_SERVER['REQUEST_URI']) ?>"><b>Inicia sesión</b></a> para dejar tu opinión.</p><?php endif; ?>
  <div class="resenas-wrap">
    <?php if ($res): ?><div class="resenas-resumen"><span class="resenas-prom"><?= number_format($prom, 1) ?></span>
      <div><div class="resenas-estrellas"><?= $estrellas(round($prom)) ?></div><div class="resenas-total"><?= count($res) ?> opiniones</div></div></div>
    <?php foreach ($res as $r): ?><div class="resena-item"><div class="resena-header">
      <div class="resena-avatar"><?= h(mb_strtoupper(mb_substr($r['nom_usr'], 0, 1))) ?></div>
      <div><div class="resena-nombre"><?= h($r['nom_usr']) ?><?php if ($r['id_pei']): ?><span class="resena-verificada">✓ Compra verificada</span><?php endif; ?></div><div class="resena-estrellas"><?= $estrellas($r['calif_res']) ?></div></div>
      <div class="resena-fecha"><?= h(date('d/m/Y', strtotime($r['fecha_res']))) ?></div></div>
      <?php if ($r['texto_res']): ?><div class="resena-cuerpo"><p class="resena-texto"><?= h($r['texto_res']) ?></p></div><?php endif; ?></div><?php endforeach; ?>
    <?php else: ?><p style="text-align:center;color:#7a7a6a;padding:20px">Aún no hay opiniones de este producto.</p><?php endif; ?>
  </div></section>
<script>
const GALERIAS = <?= json_encode($galerias ?: new stdClass, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
document.addEventListener('DOMContentLoaded', () => renderGaleria(<?= json_encode($g0, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>));
</script>
<?php require 'includes/footer.php'; ?>
