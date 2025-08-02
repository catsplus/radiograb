<?php
/**
 * Submit Template Review API
 * Issue #38 Phase 2 - Enhanced Rating and Review System
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

$auth = new UserAuth($db);

// Require authentication
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$user_id = $auth->getCurrentUserId();
$template_id = (int)($_POST['template_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$review_text = trim($_POST['review_text'] ?? '');
$working_status = $_POST['working_status'] ?? '';

// Validate input
if (!$template_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Template ID is required']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
    exit;
}

if (!in_array($working_status, ['working', 'not_working', 'intermittent'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid working status']);
    exit;
}

// Validate review text length
if (strlen($review_text) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Review text must be 1000 characters or less']);
    exit;
}

try {
    // Check if template exists
    $template = $db->fetchOne("
        SELECT id, name FROM stations_master 
        WHERE id = ? AND is_active = 1
    ", [$template_id]);
    
    if (!$template) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Template not found']);
        exit;
    }
    
    // Check if user already reviewed this template
    $existing_review = $db->fetchOne("
        SELECT id FROM station_template_reviews 
        WHERE template_id = ? AND user_id = ?
    ", [$template_id, $user_id]);
    
    if ($existing_review) {
        // Update existing review
        $db->update('station_template_reviews', [
            'rating' => $rating,
            'review_text' => $review_text,
            'working_status' => $working_status,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $existing_review['id']]);
        
        $message = 'Review updated successfully';
    } else {
        // Create new review
        $db->insert('station_template_reviews', [
            'template_id' => $template_id,
            'user_id' => $user_id,
            'rating' => $rating,
            'review_text' => $review_text,
            'working_status' => $working_status,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        $message = 'Review submitted successfully';
    }
    
    // Get updated template statistics
    $stats = $db->fetchOne("
        SELECT 
            AVG(rating) as avg_rating,
            COUNT(*) as review_count,
            SUM(CASE WHEN working_status = 'working' THEN 1 ELSE 0 END) as working_count,
            SUM(CASE WHEN working_status = 'not_working' THEN 1 ELSE 0 END) as not_working_count,
            SUM(CASE WHEN working_status = 'intermittent' THEN 1 ELSE 0 END) as intermittent_count
        FROM station_template_reviews 
        WHERE template_id = ?
    ", [$template_id]);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'stats' => [
            'avg_rating' => $stats['avg_rating'] ? round($stats['avg_rating'], 1) : null,
            'review_count' => (int)$stats['review_count'],
            'working_percentage' => $stats['review_count'] > 0 ? round(($stats['working_count'] / $stats['review_count']) * 100, 1) : 0,
            'working_breakdown' => [
                'working' => (int)$stats['working_count'],
                'not_working' => (int)$stats['not_working_count'],
                'intermittent' => (int)$stats['intermittent_count']
            ]
        ]
    ]);

} catch (Exception $e) {
    error_log("Template review submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to submit review']);
}
?>