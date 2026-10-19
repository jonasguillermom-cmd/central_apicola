<?php
$titulo = 'CENTRAL APÍCOLA DEL USUMACINTA';
require 'includes/header.php';
$slides = q("SELECT titulo_blo AS t, img_blo AS img, link_blo AS link FROM index_bloques WHERE est_blo='A' ORDER BY orden_blo")->fetchAll();
if (!$slides) foreach (glob('imagen/inicio/carrusel*.png') as $f) $slides[] = ['t' => 'Banner', 'img' => $f, 'link' => ''];
$top = q(SQL_TARJETAS . " ORDER BY (SELECT COALESCE(SUM(pi.cant_pei),0) FROM pedido_items pi
        JOIN ofertas o ON o.id_ofe=pi.id_ofe JOIN producto_variantes v ON v.id_var=o.id_var
        WHERE v.id_prod=d.id_prod) DESC, d.id_prod DESC LIMIT 8")->fetchAll();
$otros = q(SQL_TARJETAS . " ORDER BY d.id_prod DESC LIMIT 4")->fetchAll();
$cs = q("SELECT id_cat FROM categorias WHERE est_cat='A' ORDER BY id_cat LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
$lnk = fn($i) => isset($cs[$i]) ? 'categoria.php?id=' . (int)$cs[$i] : 'categoria.php?q=';
?>
<?php if ($slides): ?>
<div class="hero">
  <?php foreach ($slides as $i => $s): ?><div class="hero-slide <?= $i === 0 ? 'active' : '' ?>">
    <?= $s['link'] ? '<a href="' . h($s['link']) . '">' : '' ?><img src="<?= h($s['img']) ?>" alt="<?= h($s['t']) ?>"><?= $s['link'] ? '</a>' : '' ?></div><?php endforeach; ?>
  <button class="hero-arrow prev" onclick="changeSlide(-1)">&#8592;</button><button class="hero-arrow next" onclick="changeSlide(1)">&#8594;</button>
  <div class="hero-nav"><?php foreach ($slides as $i => $s) echo '<button class="hero-dot ' . ($i === 0 ? 'active' : '') . '" onclick="goSlide(' . $i . ')"></button>'; ?></div>
</div>
<?php endif; ?>

<section class="section" style="background:#fff"><div class="container">
  <div style="text-align:center;margin-bottom:12px"><img src="imagen/logo_centro.png" alt="Abeja" style="height:56px;object-fit:contain"></div>
  <h2 class="section-title">¡LO MAS VENDIDO!</h2>
  <div class="prod-grid"><?php foreach ($top as $r) echo tarjeta($r); ?>
    <?php if (!$top) echo '<div style="text-align:center;padding:40px;color:#7a7a6a;grid-column:1/-1">Aún no hay productos disponibles.</div>'; ?></div>
  <div class="ver-todos-wrap"><a href="categoria.php?q=" class="ver-todos">Ver todos los productos</a></div>
</div></section>

<section class="section" style="background:var(--gris-claro)"><div class="container">
  <h2 class="section-title">DESCUBRE</h2>
  <div class="coll-grid">
    <a href="#" class="coll-card"><img src="imagen/descubre-historia.png" alt="Historia de la miel"><div class="coll-label">El inicio de la miel</div></a>
    <a href="<?= $lnk(0) ?>" class="coll-card"><img src="" alt=""><div class="coll-label">Productos de la Colmena</div></a>
    <a href="<?= $lnk(1) ?>" class="coll-card" style="background:#e8d5b0"><img src="" alt=""><div class="coll-label">Miel y Salud</div></a>
  </div>
</div></section>

<section class="section" style="background:#fff"><div class="container">
  <h2 class="section-title left">Otros Productos</h2>
  <div class="prod-grid"><?php foreach ($otros as $r) echo tarjeta($r); ?></div>
  <div class="ver-todos-wrap"><a href="categoria.php?q=" class="ver-todos">Ver todos los productos</a></div>
</div></section>

<div class="about-strip"><div class="about-inner">
  <div class="about-text">
    <h2>La miel pura de Cana'an Xu'x es extraída de colmenas en Tenosique, Tabasco.</h2>
    <p>Somos un grupo de apicultoras tenosiquenses dedicadas a la producción de miel pura y todos los productos derivados de la colmena, cuidando cada paso del proceso, de la flor a tu mesa.</p>
    <p>Brindamos pláticas, cursos de apicultura y asesorías, además de realizar labor de rescate de enjambres y abejas en nuestra región.</p>
    <a href="nosotros.php" class="btn-link">¡Conoce más sobre nosotras!</a></div>
  <div><p style="font-size:14px;color:var(--gris);line-height:1.8;margin-bottom:24px">En Cana'an Xu'x somos parte de ese sistema productivo de la miel que se inicia en el vuelo de las abejas de flor en flor, que pasa por un sistema de procesamiento y empaque y que termina en la puerta de tu casa o negocio.</p>
    <div class="icon-cats">
      <div class="icon-cat"><img src="https://fernandorueda.studio/demos/mielOro/Miel-a-Granel.png" alt=""><span>Miel a Granel</span></div>
      <div class="icon-cat"><img src="https://fernandorueda.studio/demos/mielOro/Todo-para-el-Apicultor.png" alt=""><span>Todo para el Apicultor</span></div>
      <div class="icon-cat"><img src="https://fernandorueda.studio/demos/mielOro/Productos-de-la-Colmena.png" alt=""><span>Productos de la Colmena</span></div>
    </div></div>
</div></div>

<div class="about-strip" id="bloque-aprendiendo" style="background:#fff"><div class="about-inner">
  <div class="about-text"><h2>Aprendiendo de las colmenas</h2>
    <p>En Central apícola del Usumacinta queremos fomentar la cultura del cuidado de las abejas y el medio ambiente desde los primeros años.</p>
    <p>Por ello impartimos pláticas, talleres y cursos donde las niñas y niños aprenden sobre la vida de las abejas y su cuidado.</p>
    <a href="contacto.php" class="btn-link">¿Quieres llevar a nuestras expertas a tu escuela?</a></div>
  <div class="about-imgs"><img src="imagen/yax-abeja.png" alt=""></div>
</div></div>
<?php require 'includes/footer.php'; ?>
