<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();

$outputLog = '';
$success = false;
$startTime = microtime(true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        die('Invalid CSRF token.');
    }

    $buildScript = dirname(__DIR__) . '/build.php';
    if (!file_exists($buildScript)) {
        $outputLog = "Error: build.php not found at {$buildScript}";
    } else {
        // Execute build.php via CLI
        $cmd = 'php ' . escapeshellarg($buildScript) . ' 2>&1';
        $outputLines = [];
        $returnCode = 0;
        exec($cmd, $outputLines, $returnCode);

        $outputLog = implode("\n", $outputLines);
        $success = ($returnCode === 0);

        // Also ensure index.html in root is synced with public/index.html
        if ($success && file_exists(dirname(__DIR__) . '/public/index.html')) {
            @copy(dirname(__DIR__) . '/public/index.html', dirname(__DIR__) . '/index.html');
        }
    }

    $duration = round((microtime(true) - $startTime) * 1000, 2);

    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode([
            'success'  => $success,
            'log'      => $outputLog,
            'duration' => "{$duration}ms"
        ]);
        exit;
    }
} else {
    // If accessed via GET, show status of last build manifest
}

$manifestFile = dirname(__DIR__) . '/public/build-manifest.json';
$manifest = [];
if (file_exists($manifestFile)) {
    $manifest = json_decode((string)file_get_contents($manifestFile), true) ?: [];
}

$pageTitle = 'Static Site Rebuilder';
require_once __DIR__ . '/header.php';
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">⚡ Static Site Rebuilder</h1>
    <p class="admin-page-desc">Recompile all public HTML pages, structured JSON-LD data, sitemaps, and manifests from the database.</p>
  </div>
  <div>
    <form method="POST" action="<?= htmlspecialchars(app_url('/admin/rebuild.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="display:inline;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
      <button type="submit" class="btn-primary-action">
        ⚡ Run Full Rebuild Now
      </button>
    </form>
  </div>
</div>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <?php if ($success): ?>
    <div class="admin-alert admin-alert-success">
      <span>✅</span>
      <div>
        <strong>Site Rebuild Succeeded!</strong> Compiled in <?= htmlspecialchars((string)$duration) ?>ms. All public static HTML files and sitemaps are live.
      </div>
    </div>
  <?php else: ?>
    <div class="admin-alert admin-alert-error">
      <span>❌</span>
      <div>
        <strong>Site Rebuild Failed.</strong> See the execution output below for diagnostic details.
      </div>
    </div>
  <?php endif; ?>

  <div class="admin-card">
    <div class="admin-card-header">
      <h2 class="admin-card-title">Compilation Log Output</h2>
      <a href="<?= htmlspecialchars(app_url('/admin/index.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-secondary-action">&larr; Return to Dashboard</a>
    </div>
    <pre style="background:#0f172a; color:#38bdf8; padding:1.25rem; border-radius:6px; overflow-x:auto; font-size:0.85rem; line-height:1.5; font-family:monospace;"><?= htmlspecialchars($outputLog) ?></pre>
  </div>
<?php else: ?>
  <div class="admin-card">
    <div class="admin-card-header">
      <h2 class="admin-card-title">Current Build Status</h2>
      <span class="badge badge-completed">Manifest Active</span>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
      <div style="background:#f8fafc; padding:1rem; border-radius:6px; border:1px solid #e2e8f0;">
        <div style="font-size:0.8rem; color:#64748b;">LAST BUILD TIMESTAMP</div>
        <div style="font-size:1.1rem; font-weight:700; color:#0f172a; margin-top:0.25rem;">
          <?= htmlspecialchars($manifest['generated_at'] ?? 'Never') ?>
        </div>
      </div>

      <div style="background:#f8fafc; padding:1rem; border-radius:6px; border:1px solid #e2e8f0;">
        <div style="font-size:0.8rem; color:#64748b;">TOTAL PRE-RENDERED PAGES</div>
        <div style="font-size:1.1rem; font-weight:700; color:#0f172a; margin-top:0.25rem;">
          <?= htmlspecialchars((string)($manifest['total_pages'] ?? '0')) ?> Pages
        </div>
      </div>

      <div style="background:#f8fafc; padding:1rem; border-radius:6px; border:1px solid #e2e8f0;">
        <div style="font-size:0.8rem; color:#64748b;">CATALOG PRODUCTS</div>
        <div style="font-size:1.1rem; font-weight:700; color:#0f172a; margin-top:0.25rem;">
          <?= htmlspecialchars((string)($manifest['products_count'] ?? '0')) ?> Active Jams
        </div>
      </div>
    </div>

    <form method="POST" action="<?= htmlspecialchars(app_url('/admin/rebuild.php'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
      <p style="font-size:0.9rem; color:#475569; margin-bottom:1.25rem;">
        Clicking the rebuild button executes <code>build.php</code>. It extracts all active products, prices, variants, delivery parameters, and company settings from the MySQL database and statically renders flat HTML files into <code>/public/</code> for sub-millisecond SEO page loads.
      </p>
      <button type="submit" class="btn-primary-action">
        ⚡ Recompile Entire Website Now
      </button>
    </form>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
