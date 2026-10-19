<?php
require_once 'config.php';
requiere_login();
$u = (int)$_SESSION['id_usr'];
$correo = strtolower((string)q('SELECT correo_usr FROM usuarios WHERE id_usr=?', [$u])->fetchColumn());
$lista = q("SELECT c.*, e.nom_emp FROM cupones c LEFT JOIN empresas e ON e.id_emp=c.id_emp
  WHERE c.est_cup='A' AND NOW() BETWEEN c.fecha_ini_cup AND c.fecha_fin_cup
    AND (c.usos_max_cup IS NULL OR c.usos_act_cup < c.usos_max_cup)
    AND (c.correo_dest_cup IS NULL OR LOWER(c.correo_dest_cup)=?)
    AND (c.vis_cup='S' OR LOWER(c.correo_dest_cup)=?)
    AND (c.id_emp IS NULL OR e.est_emp='A')
    AND NOT EXISTS (SELECT 1 FROM cupones_usos x WHERE x.id_cup=c.id_cup AND x.id_usr=?)
  ORDER BY c.fecha_fin_cup", [$correo, $correo, $u])->fetchAll();
$titulo = 'Mis cupones — Central Apícola del Usumacinta'; require 'includes/header.php';
?>
<div class="cupones-wrap"><div class="cupones-titulo">🎟️ Mis cupones</div>
  <div class="cupones-sub">Estos son los cupones que tienes disponibles. Úsalos en tu carrito.</div>
  <div class="cupon-grid">
  <?php foreach ($lista as $c): ?><div class="cupon-card">
    <div class="cupon-valor"><?= $c['tipo_desc_cup'] === 'porcentaje' ? h(rtrim(rtrim($c['valor_cup'], '0'), '.')) . '% OFF' : dinero($c['valor_cup']) . ' OFF' ?></div>
    <div class="cupon-titulo"><?= h($c['titulo_cup']) ?></div>
    <?php if ($c['desc_cup']): ?><div class="cupon-desc"><?= h($c['desc_cup']) ?></div><?php endif; ?>
    <div class="cupon-meta"><span><?= $c['monto_min_cup'] > 0 ? 'Compra mínima ' . dinero($c['monto_min_cup']) : 'Sin compra mínima' ?></span>
      <span>Vence: <?= h(date('d/m/Y', strtotime($c['fecha_fin_cup']))) ?></span>
      <span>Válido en: <?= $c['nom_emp'] ? h($c['nom_emp']) : 'toda la tienda' ?></span></div>
    <div class="cupon-codigo-row"><div class="cupon-codigo"><?= h($c['cod_cup']) ?></div>
      <button class="btn-copiar" onclick="copiarCupon('<?= h($c['cod_cup']) ?>',this)">Copiar</button></div>
  </div><?php endforeach; ?>
  <?php if (!$lista) echo '<div class="cupones-empty">Por ahora no tienes cupones disponibles.</div>'; ?>
  </div></div>
<?php require 'includes/footer.php'; ?>
