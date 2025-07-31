<?php
/**
 * RadioGrab - Custom Feeds Management
 * Interface for creating and managing custom RSS feeds
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Handle feed actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token');
        header('Location: /custom-feeds.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_feed') {
        try {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $slug = generateSlug($name);
            $custom_title = trim($_POST['custom_title']);
            $custom_description = trim($_POST['custom_description']);
            $custom_image_url = trim($_POST['custom_image_url']);
            $selected_shows = $_POST['selected_shows'] ?? [];
            
            if (empty($name)) {
                throw new Exception('Feed name is required');
            }
            
            // Check if slug already exists
            $existing = $db->fetchOne("SELECT id FROM custom_feeds WHERE slug = ?", [$slug]);
            if ($existing) {
                $slug = $slug . '-' . time();
            }
            
            // Create custom feed
            $feed_id = $db->insert('custom_feeds', [
                'name' => $name,
                'description' => $description,
                'slug' => $slug,
                'custom_title' => $custom_title ?: null,
                'custom_description' => $custom_description ?: null,
                'custom_image_url' => $custom_image_url ?: null,
                'feed_type' => 'custom'
            ]);
            
            // Add selected shows
            if (!empty($selected_shows)) {
                $sort_order = 0;
                foreach ($selected_shows as $show_id) {
                    $db->insert('custom_feed_shows', [
                        'custom_feed_id' => $feed_id,
                        'show_id' => (int)$show_id,
                        'sort_order' => $sort_order++
                    ]);
                }
            }
            
            setFlashMessage('success', 'Custom feed created successfully');
            header('Location: /custom-feeds.php');
            exit;
            
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to create custom feed: ' . $e->getMessage());
        }
    }
    elseif ($action === 'delete_feed') {
        try {
            $feed_id = (int)$_POST['feed_id'];
            
            // Delete custom feed (cascade will handle feed_shows)
            $db->delete('custom_feeds', 'id = ? AND feed_type = ?', [$feed_id, 'custom']);
            
            setFlashMessage('success', 'Custom feed deleted successfully');
            header('Location: /custom-feeds.php');
            exit;
            
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to delete custom feed: ' . $e->getMessage());
        }
    }
    
    header('Location: /custom-feeds.php');
    exit;
}

// Get all custom feeds
try {
    $custom_feeds = $db->fetchAll("
        SELECT cf.*, 
               GROUP_CONCAT(s.name ORDER BY cfs.sort_order SEPARATOR ', ') as show_names,
               COUNT(cfs.show_id) as show_count
        FROM custom_feeds cf
        LEFT JOIN custom_feed_shows cfs ON cf.id = cfs.custom_feed_id
        LEFT JOIN shows s ON cfs.show_id = s.id
        WHERE cf.feed_type = 'custom'
        GROUP BY cf.id
        ORDER BY cf.name
    ");
    
    // Get available shows for feed creation
    $available_shows = $db->fetchAll("
        SELECT s.id, s.name, st.name as station_name, st.call_letters
        FROM shows s
        JOIN stations st ON s.station_id = st.id
        WHERE s.active = 1 AND s.show_type != 'playlist'
        ORDER BY st.name, s.name
    ");
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $custom_feeds = [];
    $available_shows = [];
}

function generateSlug($name) {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9\-_]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// Set page variables
$page_title = 'Custom Feeds';
$active_nav = 'feeds';

require_once '../includes/header.php';
?>

    <!-- Flash Messages -->
    <?php foreach (getFlashMessages() as $flash): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
            <?= h($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1><i class="fas fa-rss"></i> Custom RSS Feeds</h1>
                <p class="text-muted">Create custom podcast feeds by selecting specific shows</p>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFeedModal">
                    <i class="fas fa-plus"></i> Create Custom Feed
                </button>
            </div>
        </div>

        <!-- Custom Feeds List -->
        <?php if (empty($custom_feeds)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-rss fa-3x text-muted mb-3"></i>
                    <h3>No custom feeds yet</h3>
                    <p class="text-muted mb-4">Create your first custom RSS feed by selecting specific shows.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFeedModal">
                        <i class="fas fa-plus"></i> Create Your First Custom Feed
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($custom_feeds as $feed): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1"><?= h($feed['name']) ?></h5>
                                        <small class="text-muted">
                                            <?= $feed['show_count'] ?> show<?= $feed['show_count'] != 1 ? 's' : '' ?>
                                        </small>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <button class="dropdown-item delete-feed" 
                                                        data-feed-id="<?= $feed['id'] ?>"
                                                        data-feed-name="<?= h($feed['name']) ?>">
                                                    <i class="fas fa-trash text-danger"></i> Delete
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <?php if ($feed['description']): ?>
                                    <p class="card-text text-muted small"><?= h($feed['description']) ?></p>
                                <?php endif; ?>
                                
                                <?php if ($feed['show_names']): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <strong>Shows:</strong> <?= h($feed['show_names']) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row text-center mb-3">
                                    <div class="col">
                                        <div class="fw-bold"><?= $feed['show_count'] ?></div>
                                        <small class="text-muted">Shows</small>
                                    </div>
                                    <div class="col">
                                        <div class="fw-bold"><?= $feed['is_public'] ? 'Public' : 'Private' ?></div>
                                        <small class="text-muted">Visibility</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-footer bg-transparent">
                                <div class="d-grid gap-2">
                                    <a href="/api/enhanced-feeds.php?type=custom&slug=<?= urlencode($feed['slug']) ?>" 
                                       class="btn btn-outline-primary btn-sm" target="_blank">
                                        <i class="fas fa-rss"></i> View RSS Feed
                                    </a>
                                    <button type="button" class="btn btn-outline-secondary btn-sm copy-feed-url"
                                            data-feed-url="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=custom&slug=<?= urlencode($feed['slug']) ?>">
                                        <i class="fas fa-copy"></i> Copy Feed URL
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Feed Modal -->
    <div class="modal fade" id="createFeedModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Custom RSS Feed</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Feed Name *</label>
                                <input type="text" class="form-control" name="name" required 
                                       placeholder="My Custom Feed">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Custom Title</label>
                                <input type="text" class="form-control" name="custom_title" 
                                       placeholder="Custom RSS title (optional)">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2" 
                                          placeholder="Describe this custom feed"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Custom Description</label>
                                <textarea class="form-control" name="custom_description" rows="2" 
                                          placeholder="Custom RSS description (optional)"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Custom Image URL</label>
                                <input type="url" class="form-control" name="custom_image_url" 
                                       placeholder="https://example.com/image.jpg (optional)">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Select Shows *</label>
                                <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                    <?php if (empty($available_shows)): ?>
                                        <p class="text-muted mb-0">No shows available</p>
                                    <?php else: ?>
                                        <?php 
                                        $current_station = '';
                                        foreach ($available_shows as $show): 
                                            if ($current_station !== $show['station_name']):
                                                if ($current_station !== '') echo '</div>';
                                                $current_station = $show['station_name'];
                                                echo '<div class="mb-3">';
                                                echo '<h6 class="text-primary">' . h($show['station_name']) . '</h6>';
                                            endif;
                                        ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="selected_shows[]" value="<?= $show['id'] ?>" 
                                                       id="show<?= $show['id'] ?>">
                                                <label class="form-check-label" for="show<?= $show['id'] ?>">
                                                    <?= h($show['name']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ($current_station !== '') echo '</div>'; ?>
                                    <?php endif; ?>
                                </div>
                                <small class="form-text text-muted">Select one or more shows to include in this feed</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="create_feed">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Feed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Custom Feed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the custom feed <strong id="feedName"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        This action cannot be undone. The RSS feed URL will stop working.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete_feed">
                        <input type="hidden" name="feed_id" id="deleteFeedId">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Feed
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php
$additional_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Delete feed buttons
    document.querySelectorAll(".delete-feed").forEach(btn => {
        btn.addEventListener("click", function() {
            const feedId = this.dataset.feedId;
            const feedName = this.dataset.feedName;
            
            document.getElementById("deleteFeedId").value = feedId;
            document.getElementById("feedName").textContent = feedName;
            
            const modal = new bootstrap.Modal(document.getElementById("deleteModal"));
            modal.show();
        });
    });
    
    // Copy feed URL buttons
    document.querySelectorAll(".copy-feed-url").forEach(btn => {
        btn.addEventListener("click", async function() {
            const feedUrl = this.dataset.feedUrl;
            
            try {
                await navigator.clipboard.writeText(feedUrl);
                
                const originalHTML = this.innerHTML;
                this.innerHTML = \'<i class="fas fa-check"></i> Copied!\';
                this.classList.remove("btn-outline-secondary");
                this.classList.add("btn-success");
                
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                    this.classList.remove("btn-success");
                    this.classList.add("btn-outline-secondary");
                }, 2000);
                
            } catch (err) {
                console.error("Failed to copy: ", err);
                alert("Feed URL: " + feedUrl);
            }
        });
    });
});
</script>';

require_once '../includes/footer.php';
?>