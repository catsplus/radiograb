<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    header('Location: /login.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed.');
    }

    // Update settings
    $settings_to_update = [
        'site_title' => $_POST['site_title'],
        'site_tagline' => $_POST['site_tagline'],
        'brand_color' => $_POST['brand_color'],
        'footer_text' => $_POST['footer_text'],
    ];

    foreach ($settings_to_update as $name => $value) {
        updateSiteSetting($name, $value);
    }

    // Handle logo upload
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == 0) {
        $logo_path = handle_logo_upload($_FILES['site_logo']);
        if ($logo_path) {
            updateSiteSetting('site_logo', $logo_path);
        }
    }

    header('Location: /admin/settings.php?success=1');
    exit;
}

function updateSiteSetting($name, $value) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_name = ?");
    $stmt->execute([$value, $name]);
}

function handle_logo_upload($file) {
    $target_dir = "/Users/mjb9/scripts/radiograb-public/frontend/assets/images/";
    $target_file = $target_dir . basename($file["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if image file is a actual image or fake image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return false;
    }

    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
    && $imageFileType != "gif" ) {
        return false;
    }

    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return '/assets/images/' . basename($file["name"]);
    } else {
        return false;
    }
}

$settings = getAllSiteSettings();

?>

<div class="container mt-4">
    <h2>Admin Settings</h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Settings updated successfully!</div>
    <?php endif; ?>

    <form action="/admin/settings.php" method="post" enctype="multipart/form-data">
        <?php echo get_csrf_input(); ?>
        <div class="form-group">
            <label for="site_title">Site Title</label>
            <input type="text" class="form-control" id="site_title" name="site_title" value="<?php echo htmlspecialchars($settings['site_title']); ?>">
        </div>
        <div class="form-group">
            <label for="site_tagline">Site Tagline</label>
            <input type="text" class="form-control" id="site_tagline" name="site_tagline" value="<?php echo htmlspecialchars($settings['site_tagline']); ?>">
        </div>
        <div class="form-group">
            <label for="site_logo">Site Logo</label>
            <input type="file" class="form-control-file" id="site_logo" name="site_logo">
            <small class="form-text text-muted">Current logo: <img src="<?php echo htmlspecialchars($settings['site_logo']); ?>" alt="Site Logo" style="max-height: 40px;"></small>
        </div>
        <div class="form-group">
            <label for="brand_color">Brand Color</label>
            <input type="color" class="form-control" id="brand_color" name="brand_color" value="<?php echo htmlspecialchars($settings['brand_color']); ?>">
        </div>
        <div class="form-group">
            <label for="footer_text">Footer Text</label>
            <input type="text" class="form-control" id="footer_text" name="footer_text" value="<?php echo htmlspecialchars($settings['footer_text']); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>