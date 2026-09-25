<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/security.php';

$admin = require_admin_auth();

$imagesDir = dirname(__DIR__) . '/public/images';
$docsDir   = dirname(__DIR__) . '/public/documents';

if (!is_dir($imagesDir)) {
    @mkdir($imagesDir, 0755, true);
}
if (!is_dir($docsDir)) {
    @mkdir($docsDir, 0755, true);
}

$flash = '';
$flashType = 'success';
$activeTab = $_GET['tab'] ?? 'images';

// Handle Uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf_token();

    if ($_POST['action'] === 'upload_image') {
        $activeTab = 'images';
        if (isset($_FILES['image_file'])) {
            $res = sanitize_and_store_upload(
                $_FILES['image_file'],
                $imagesDir,
                ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
                10485760 // 10MB
            );
            if ($res['success']) {
                $flash = "Image '{$res['filename']}' uploaded successfully!";
            } else {
                $flash = "Image upload failed: " . $res['error'];
                $flashType = 'error';
            }
        }
    } elseif ($_POST['action'] === 'upload_doc') {
        $activeTab = 'documents';
        if (isset($_FILES['doc_file'])) {
            $res = sanitize_and_store_upload(
                $_FILES['doc_file'],
                $docsDir,
                ['application/pdf'],
                15728640 // 15MB
            );
            if ($res['success']) {
                $flash = "PDF document '{$res['filename']}' uploaded successfully!";
            } else {
                $flash = "Document upload failed: " . $res['error'];
                $flashType = 'error';
            }
        }
    } elseif ($_POST['action'] === 'delete_file') {
        $type = $_POST['file_type'] ?? '';
        $filename = basename((string)($_POST['filename'] ?? ''));
        $target = ($type === 'doc') ? $docsDir . '/' . $filename : $imagesDir . '/' . $filename;
        if (file_exists($target) && is_file($target)) {
            @unlink($target);
            $flash = "File '{$filename}' deleted.";
        }
    }
}

// Read image files
$imageFiles = [];
if (is_dir($imagesDir)) {
    $scanned = scandir($imagesDir);
    foreach ($scanned as $f) {
        if ($f === '.' || $f === '..' || str_starts_with($f, '.')) continue;
        $path = $imagesDir . '/' . $f;
        if (is_file($path)) {
            $imageFiles[] = [
                'name' => $f,
                'path' => app_url('/public/images/' . $f),
                'size' => round(filesize($path) / 1024, 1) . ' KB',
                'modified' => date('M j, Y g:i A', filemtime($path))
            ];
        }
    }
}

// Read document files
$docFiles = [];
if (is_dir($docsDir)) {
    $scanned = scandir($docsDir);
    foreach ($scanned as $f) {
        if ($f === '.' || $f === '..' || str_starts_with($f, '.')) continue;
        $path = $docsDir . '/' . $f;
        if (is_file($path)) {
            $docFiles[] = [
                'name' => $f,
                'path' => app_url('/public/documents/' . $f),
                'size' => round(filesize($path) / 1024, 1) . ' KB',
                'modified' => date('M j, Y g:i A', filemtime($path))
            ];
        }
    }
}

$pageTitle = 'Asset Managers';
require_once __DIR__ . '/header.php';
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">📁 Asset Managers</h1>
    <p class="admin-page-desc">Sanitized uploads and media management for product photography, gift packaging, and catering PDF documents.</p>
  </div>
</div>

<?php if (!empty($flash)): ?>
  <div class="admin-alert admin-alert-<?= $flashType ?>" role="status">
    <span><?= $flashType === 'success' ? '✅' : '⚠️' ?></span>
    <div><?= htmlspecialchars($flash) ?></div>
  </div>
<?php endif; ?>

<!-- Tabs -->
<div style="display:flex; gap:0.5rem; border-bottom:1px solid #e2e8f0; margin-bottom:1.5rem;">
  <a href="<?= htmlspecialchars(app_url('/admin/assets.php?tab=images'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-admin <?= $activeTab === 'images' ? 'btn-primary-action' : 'btn-secondary-action' ?>">
    🖼️ Image Assets (<?= count($imageFiles) ?>)
  </a>
  <a href="<?= htmlspecialchars(app_url('/admin/assets.php?tab=documents'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="btn-admin <?= $activeTab === 'documents' ? 'btn-primary-action' : 'btn-secondary-action' ?>">
    📄 PDF Documents (<?= count($docFiles) ?>)
  </a>
</div>

<?php if ($activeTab === 'images'): ?>
  <!-- Images Manager -->
  <div class="form-grid" style="grid-template-columns: 1fr 2fr; gap:1.75rem;">
    <div>
      <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom:1rem;">Upload Image</h2>
        <form method="POST" action="<?= htmlspecialchars(app_url('/admin/assets.php?tab=images'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
          <input type="hidden" name="action" value="upload_image">

          <div class="form-group">
            <label for="image_file">Select Image File</label>
            <input type="file" id="image_file" name="image_file" required accept="image/jpeg,image/png,image/webp,image/svg+xml">
            <span class="form-help">Supported: JPEG, PNG, WebP, SVG &bull; Max 10MB</span>
          </div>

          <button type="submit" class="btn-primary-action" style="width:100%; justify-content:center; margin-top:0.75rem;">
            Upload Image &rarr;
          </button>
        </form>
      </div>
    </div>

    <div>
      <div class="admin-card">
        <div class="admin-card-header">
          <h2 class="admin-card-title">Stored Images (/public/images/)</h2>
          <span style="font-size:0.85rem; color:#64748b;"><?= count($imageFiles) ?> Files</span>
        </div>

        <?php if (empty($imageFiles)): ?>
          <p style="color:#64748b; padding:1.5rem 0;">No images uploaded to /public/images/ yet.</p>
        <?php else: ?>
          <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:1rem;">
            <?php foreach ($imageFiles as $img): ?>
              <div style="border:1px solid #e2e8f0; border-radius:6px; overflow:hidden; background:#ffffff; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                <div style="height:130px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                  <img src="<?= htmlspecialchars($img['path']) ?>" alt="<?= htmlspecialchars($img['name']) ?>" style="max-height:100%; max-width:100%; object-fit:contain;">
                </div>
                <div style="padding:0.75rem;">
                  <div style="font-size:0.8rem; font-weight:700; word-break:break-all;"><?= htmlspecialchars($img['name']) ?></div>
                  <div style="font-size:0.75rem; color:#64748b; margin-top:2px;"><?= $img['size'] ?> &bull; <?= $img['modified'] ?></div>
                  <div style="margin-top:0.6rem; display:flex; gap:0.4rem;">
                    <button type="button" class="btn-admin" style="font-size:0.75rem; padding:0.25rem 0.5rem; background:#f1f5f9; color:#1e293b;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($img['path']) ?>'); alert('Copied path: <?= htmlspecialchars($img['path']) ?>');">
                      Copy Path
                    </button>
                    <form method="POST" action="<?= htmlspecialchars(app_url('/admin/assets.php?tab=images'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="display:inline;" onsubmit="return confirm('Delete image?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete_file">
                      <input type="hidden" name="file_type" value="image">
                      <input type="hidden" name="filename" value="<?= htmlspecialchars($img['name']) ?>">
                      <button type="submit" class="btn-admin" style="font-size:0.75rem; padding:0.25rem 0.5rem; background:#fee2e2; color:#991b1b; border:none;">
                        Delete
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- Documents Manager -->
  <div class="form-grid" style="grid-template-columns: 1fr 2fr; gap:1.75rem;">
    <div>
      <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom:1rem;">Upload PDF Document</h2>
        <form method="POST" action="<?= htmlspecialchars(app_url('/admin/assets.php?tab=documents'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
          <input type="hidden" name="action" value="upload_doc">

          <div class="form-group">
            <label for="doc_file">Select PDF File</label>
            <input type="file" id="doc_file" name="doc_file" required accept="application/pdf">
            <span class="form-help">Strictly PDF &bull; Max 15MB</span>
          </div>

          <button type="submit" class="btn-primary-action" style="width:100%; justify-content:center; margin-top:0.75rem;">
            Upload PDF &rarr;
          </button>
        </form>
      </div>
    </div>

    <div>
      <div class="admin-card">
        <div class="admin-card-header">
          <h2 class="admin-card-title">Stored Documents (/public/documents/)</h2>
          <span style="font-size:0.85rem; color:#64748b;"><?= count($docFiles) ?> Files</span>
        </div>

        <?php if (empty($docFiles)): ?>
          <p style="color:#64748b; padding:1.5rem 0;">No PDF documents stored yet.</p>
        <?php else: ?>
          <div class="admin-table-container">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Document Name</th>
                  <th>File Size</th>
                  <th>Upload Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($docFiles as $doc): ?>
                  <tr>
                    <td>
                      <a href="<?= htmlspecialchars($doc['path']) ?>" target="_blank" style="font-weight:600; color:var(--admin-primary); text-decoration:none;">
                        📄 <?= htmlspecialchars($doc['name']) ?>
                      </a>
                    </td>
                    <td><?= $doc['size'] ?></td>
                    <td><?= $doc['modified'] ?></td>
                    <td>
                      <div style="display:flex; gap:0.4rem;">
                        <button type="button" class="btn-admin" style="background:#f1f5f9; color:#1e293b;" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($doc['path']) ?>'); alert('Copied path: <?= htmlspecialchars($doc['path']) ?>');">
                          Copy Link
                        </button>
                        <a href="<?= htmlspecialchars($doc['path']) ?>" target="_blank" class="btn-admin" style="background:#e0e7ff; color:#3730a3;">
                          Open
                        </a>
                        <form method="POST" action="<?= htmlspecialchars(app_url('/admin/assets.php?tab=documents'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="display:inline;" onsubmit="return confirm('Delete PDF document?');">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(get_csrf_token()) ?>">
                          <input type="hidden" name="action" value="delete_file">
                          <input type="hidden" name="file_type" value="doc">
                          <input type="hidden" name="filename" value="<?= htmlspecialchars($doc['name']) ?>">
                          <button type="submit" class="btn-admin" style="background:#fee2e2; color:#991b1b; border:none;">
                            Delete
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
