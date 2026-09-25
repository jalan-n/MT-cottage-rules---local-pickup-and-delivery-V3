</main>

<footer style="background:#ffffff; border-top:1px solid #e2e8f0; padding:1.5rem; text-align:center; font-size:0.82rem; color:#64748b; margin-top:auto;">
  <div style="max-width:1400px; margin:0 auto; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div>
      <strong><?= htmlspecialchars($brandName) ?></strong> &bull; Static Site Generator + Micro-API Engine
    </div>
    <div>
      <span>Environment: PHP <?= PHP_VERSION ?> &bull; PDO Active &bull; CSRF Enforced</span>
    </div>
  </div>
</footer>

<script>
// Simple auto-dismiss for alerts
document.addEventListener('DOMContentLoaded', () => {
  const alerts = document.querySelectorAll('.admin-alert');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    }, 6000);
  });
});
</script>

</body>
</html>
