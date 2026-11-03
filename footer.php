</main>
</div>
<script>
  var tg = document.querySelector('.side-toggle'), sd = document.getElementById('adminSide');
  if (tg && sd) tg.addEventListener('click', function () { var o = sd.classList.toggle('open'); tg.setAttribute('aria-expanded', o); });
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) { if (!confirm(f.dataset.confirm)) ev.preventDefault(); });
  });
</script>
</body>
</html>
