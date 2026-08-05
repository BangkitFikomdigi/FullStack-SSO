<?php
session_start();

if (empty($_SESSION['sso_token'])) {
    header('Location: login.php');
    exit;
}

$apiBase = 'http://localhost:3000';
$token = $_SESSION['sso_token'];

$sessionRes = @file_get_contents($apiBase . '/auth/validate', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAccept: application/json\r\nAuthorization: Bearer {$token}\r\n",
        'content' => json_encode([])
    ]
]));

$sessionData = $sessionRes ? json_decode($sessionRes, true) : null;
$valid = ($sessionData['success'] ?? false) && ($sessionData['valid'] ?? false);
$modules = $sessionData['data']['user']['modul_akses'] ?? ($_SESSION['sso_modules'] ?? []);
$username = $_SESSION['sso_user'] ?? 'User';

if (!$valid) {
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}

// Tampilan (icon + warna) untuk tiap modul. Modul yang tidak terdaftar
// di sini akan memakai ikon & warna default di bawah.
$moduleStyles = [
    'SIMRS'        => ['icon' => 'fa-solid fa-hospital',        'color' => '#c0392b'],
    'AMINO_MOBILE' => ['icon' => 'fa-solid fa-mobile-screen',   'color' => '#2e86de'],
    'LAPOR_AMINO'  => ['icon' => 'fa-solid fa-bullhorn',        'color' => '#e67e22'],
    'WBS'          => ['icon' => 'fa-solid fa-shield-halved',   'color' => '#16a385'],
];
$defaultStyle = ['icon' => 'fa-solid fa-grip', 'color' => '#5b6b79'];

$initial = strtoupper(substr($username, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SSO Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="dashboard.css">
</head>
<body>
  <div class="topbar">
    <div class="topbar-title">RSJD dr. Amino Gondohutomo</div>
    <div class="topbar-right">
      <div class="topbar-tab"><i class="fa-solid fa-th-large"></i> Apps</div>
      <div class="user-menu">
        <button class="user-menu-btn" onclick="document.getElementById('userDropdown').classList.toggle('open')">
          <span class="user-avatar"><?php echo htmlspecialchars($initial); ?></span>
          <?php echo htmlspecialchars($username); ?>
          <i class="fa-solid fa-caret-down"></i>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <div class="user-dropdown-name"><?php echo htmlspecialchars($username); ?></div>
          <form method="POST" action="logout.php">
            <button type="submit"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="content-header">
      <h1>Portal Aplikasi</h1>
      <div class="search-box">
        <i class="fa-solid fa-search"></i>
        <input type="text" id="appSearch" placeholder="Search" autocomplete="off">
      </div>
    </div>

    <div class="apps-grid" id="appsGrid">
      <?php if (empty($modules)): ?>
        <p class="empty-state">Belum ada aplikasi yang dapat diakses.</p>
      <?php else: ?>
        <?php foreach ($modules as $module):
          $code = $module['code'] ?? '';
          $name = $module['name'] ?? $code ?: 'Module';
          $url = $module['url'] ?? '#';
          $style = $moduleStyles[$code] ?? $defaultStyle;
          
          // Tambahan: Menangkap data deskripsi dari backend. 
          // Jika backend belum mengirim deskripsi, teks cadangan akan otomatis muncul.
          $description = $module['description'] ?? 'Layanan sistem informasi dan portal internal.';
        ?>
          <a class="app-card" href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer" data-name="<?php echo htmlspecialchars(strtolower($name)); ?>">
            
            <div class="card-icon">
              <!-- Ikon tetap memanggil variabel style bawaanmu -->
              <i class="<?php echo htmlspecialchars($style['icon']); ?>"></i>
            </div>
            
            <h3 class="card-title"><?php echo htmlspecialchars($name); ?></h3>
            
            <p class="card-desc"><?php echo htmlspecialchars($description); ?></p>
            
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
</div>

  <script>
    // Tutup dropdown user saat klik di luar
    document.addEventListener('click', function (e) {
      const menu = document.getElementById('userDropdown');
      const btn = document.querySelector('.user-menu-btn');
      if (!menu.contains(e.target) && !btn.contains(e.target)) {
        menu.classList.remove('open');
      }
    });

    // Filter pencarian aplikasi
    document.getElementById('appSearch').addEventListener('input', function () {
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll('#appsGrid .app-tile').forEach(function (tile) {
        tile.style.display = tile.dataset.name.includes(q) ? '' : 'none';
      });
    });
  </script>
</body>
</html>