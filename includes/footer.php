<footer><div class="footer-inner"><div class="footer-grid">
  <div><div class="footer-logo">Cana´an <span>Xu´x</span></div>
    <p class="footer-desc">Somos apicultores mexicanos. Ofrecemos miel de abeja pura y todos los productos derivados de la colmena.</p>
    <div class="footer-social"><a href="https://www.instagram.com/mielorogdl/" target="_blank" title="Instagram">📷</a><a href="https://www.facebook.com/mielorogdl" target="_blank" title="Facebook">f</a><a href="https://www.pinterest.es/mielorogdl/" target="_blank" title="Pinterest">P</a></div></div>
  <div class="footer-col"><h4>Tienda</h4><ul>
    <?php foreach (q("SELECT id_cat, nom_cat FROM categorias WHERE est_cat='A' ORDER BY nom_cat LIMIT 5")->fetchAll() as $c) echo '<li><a href="categoria.php?id=' . (int)$c['id_cat'] . '">' . h($c['nom_cat']) . '</a></li>'; ?></ul></div>
  <div class="footer-col"><h4>Experiencias</h4><ul>
    <li><a href="#">Apicultor por un día</a></li><li><a href="#">Cata de Hidromiel</a></li><li><a href="#">La ruta de las abejas</a></li><li><a href="#">Salva enjambres</a></li></ul></div>
  <div class="footer-col"><h4>Empresa</h4><ul>
    <li><a href="nosotros.php">Nosotros</a></li><li><a href="contacto.php">Contacto</a></li><li><a href="registro_empresa.php">Vende con nosotros</a></li></ul></div>
</div><div class="footer-bottom">© <?= date('Y') ?> · Todos los derechos reservados · Hecho en México</div></div></footer>
<div class="search-overlay"><form class="search-box" action="categoria.php" method="get">
  <button type="button" class="search-close">✕</button><input type="text" name="q" placeholder="Buscar productos..."></form></div>
<a href="https://wa.me/<?= WHATSAPP_ADMIN ?>" class="whatsapp-float" target="_blank" title="WhatsApp">💬</a>
<script src="js/app.js"></script></body></html>
