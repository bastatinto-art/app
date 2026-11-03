</main>
<footer class="footer">HomeRent &middot; Student housing in Barangay Mateuna</footer>
<script>
  var t = document.querySelector('.nav-toggle'), n = document.getElementById('nav');
  if (t && n) t.addEventListener('click', function () {
    var open = n.classList.toggle('open'); t.setAttribute('aria-expanded', open);
  });
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) { if (!confirm(f.dataset.confirm)) ev.preventDefault(); });
  });
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function () {
      var b = f.querySelector('button[type=submit]:not([data-nobusy])');
      if (b) setTimeout(function () { b.disabled = true; b.textContent = 'Please wait...'; }, 0);
    });
  });
</script>
</body>
</html>
