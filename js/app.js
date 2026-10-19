let cur = 0;
const slides = () => document.querySelectorAll('.hero-slide');
function goSlide(n) {
  const s = slides(); if (!s.length) return;
  cur = (n + s.length) % s.length;
  s.forEach((e, i) => e.classList.toggle('active', i === cur));
  document.querySelectorAll('.hero-dot').forEach((e, i) => e.classList.toggle('active', i === cur));
}
function changeSlide(d) { goSlide(cur + d); }
if (slides().length > 1) setInterval(() => changeSlide(1), 5000);

function toggleSearch() {
  const o = document.querySelector('.search-overlay'); o.classList.toggle('open');
  if (o.classList.contains('open')) o.querySelector('input').focus();
}
document.querySelector('.search-close')?.addEventListener('click', toggleSearch);
document.querySelector('.search-overlay')?.addEventListener('click', e => { if (e.target.classList.contains('search-overlay')) toggleSearch(); });

(function () {                      // menú móvil
  const btn = document.getElementById('hamburger-btn'), menu = document.getElementById('menu-desplegable');
  if (!btn || !menu) return;
  btn.addEventListener('click', e => { e.stopPropagation(); btn.classList.toggle('activo', menu.classList.toggle('abierto')); });
  document.addEventListener('click', e => { if (!menu.contains(e.target) && !btn.contains(e.target)) { menu.classList.remove('abierto'); btn.classList.remove('activo'); } });
})();

function switchImg(el, src) {       // galería de producto
  document.getElementById('mainImg').src = src;
  document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active')); el.classList.add('active');
}
function cambiarQty(d) {
  const i = document.getElementById('qty'), max = parseInt(i.max) || 99;
  i.value = Math.max(1, Math.min(max, (parseInt(i.value) || 1) + d));
}
function elegirOferta(btn) {        // presentación / vendedor
  document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active');
  document.getElementById('id_ofe').value = btn.dataset.id;
  if (typeof GALERIAS !== 'undefined') renderGaleria(GALERIAS[btn.dataset.id]);
  document.getElementById('price-main').textContent = btn.dataset.precio;
  const old = document.getElementById('price-old'); old.textContent = btn.dataset.normal || '';
  document.getElementById('vend-nombre').textContent = btn.dataset.emp;
  const q = document.getElementById('qty'); q.max = btn.dataset.stock; if (+q.value > +q.max) q.value = q.max;
}

function copiarCupon(codigo, btn) {
  navigator.clipboard.writeText(codigo).then(() => {
    const o = btn.textContent; btn.textContent = '✓ Copiado'; btn.classList.add('copiado');
    setTimeout(() => { btn.textContent = o; btn.classList.remove('copiado'); }, 1600);
  });
}

function toggleUserMenu(e) {          // menú del nombre de usuario
  e.preventDefault(); e.stopPropagation();
  const m = document.getElementById('user-menu'); const abierto = m.classList.toggle('open');
  m.querySelector('.user-menu-btn').setAttribute('aria-expanded', abierto);
}
document.addEventListener('click', e => { const m = document.getElementById('user-menu'); if (m && !m.contains(e.target)) m.classList.remove('open'); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.getElementById('user-menu')?.classList.remove('open'); });

function renderGaleria(urls) {       // foto principal + miniaturas de la variante elegida (color, etc.)
  const main = document.getElementById('mainImg'), box = document.getElementById('thumbs');
  if (!main || !box) return;
  urls = (urls && urls.length) ? urls : ['imagen/logo_centro.png'];
  main.src = urls[0]; box.innerHTML = '';
  if (urls.length > 1) urls.forEach((u, i) => {
    const d = document.createElement('div'); d.className = 'thumb' + (i === 0 ? ' active' : '');
    d.onclick = () => switchImg(d, u);
    const im = document.createElement('img'); im.src = u; im.alt = 'Vista ' + (i + 1); d.appendChild(im); box.appendChild(d);
  });
}
