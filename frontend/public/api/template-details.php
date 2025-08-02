<?php
/**
 * Template Details API
 * Issue #38 - Station Template Sharing System
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/StationTemplateService.php';

header('Content-Type: application/json');

$auth = new UserAuth($db);

// Require authentication
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$user_id = $auth->getCurrentUserId();
$templateService = new StationTemplateService($db, $user_id);

// Get template ID
$template_id = (int)($_GET['id'] ?? 0);

if (!$template_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Template ID required']);
    exit;
}

// Get template details
$result = $templateService->getTemplateDetails($template_id);

if (!$result['success']) {
    http_response_code(404);
    echo json_encode($result);
    exit;
}

// Add formatted data for display
$template = $result['template'];

// Format the response for frontend display
$response = [
    'success' => true,
    'template' => [
        'id' => $template['id'],
        'name' => $template['name'],
        'call_letters' => $template['call_letters'],
        'description' => $template['description'],
        'genre' => $template['genre'],
        'country' => $template['country'],
        'language' => $template['language'],
        'stream_url' => $template['stream_url'],
        'website_url' => $template['website_url'],
        'logo_url' => $template['logo_url'],
        'calendar_url' => $template['calendar_url'],
        'timezone' => $template['timezone'],
        'bitrate' => $template['bitrate'],
        'format' => $template['format'],
        'is_verified' => (bool)$template['is_verified'],
        'usage_count' => (int)$template['usage_count'],
        'avg_rating' => $template['avg_rating'],
        'review_count' => (int)$template['review_count'],
        'created_by_username' => $template['created_by_username'],
        'created_at' => $template['created_at'],
        'last_tested' => $template['last_tested'],
        'last_test_result' => $template['last_test_result'],
        'categories' => $template['categories'],
        'reviews' => array_map(function($review) {
            return [
                'id' => $review['id'],
                'rating' => (int)$review['rating'],
                'review_text' => $review['review_text'],
                'working_status' => $review['working_status'],
                'username' => $review['username'],
                'created_at' => $review['created_at']
            ];
        }, $template['reviews'])
    ]
];

echo json_encode($response);
?>