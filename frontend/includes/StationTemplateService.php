<?php
/**
 * Station Template Service
 * Issue #38 - Station Template Sharing System
 * 
 * Handles all operations related to station templates:
 * - Browsing public templates
 * - Copying templates to user accounts
 * - Submitting stations as templates
 * - Template management and verification
 */

class StationTemplateService {
    private $db;
    private $user_id;
    
    public function __construct($database, $user_id = null) {
        $this->db = $database;
        $this->user_id = $user_id;
    }
    
    /**
     * Browse public station templates with filtering and search
     */
    public function browseTemplates($filters = []) {
        $where_conditions = ['sm.is_active = 1'];
        $params = [];
        
        // Search by name or call letters
        if (!empty($filters['search'])) {
            $where_conditions[] = "(sm.name LIKE ? OR sm.call_letters LIKE ? OR sm.description LIKE ?)";
            $search_param = "%{$filters['search']}%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Filter by genre
        if (!empty($filters['genre'])) {
            $where_conditions[] = "sm.genre = ?";
            $params[] = $filters['genre'];
        }
        
        // Filter by country
        if (!empty($filters['country'])) {
            $where_conditions[] = "sm.country = ?";
            $params[] = $filters['country'];
        }
        
        // Filter by category
        if (!empty($filters['category_id'])) {
            $where_conditions[] = "EXISTS (SELECT 1 FROM station_template_categories stc WHERE stc.template_id = sm.id AND stc.category_id = ?)";
            $params[] = $filters['category_id'];
        }
        
        // Filter by verified status
        if (isset($filters['verified_only']) && $filters['verified_only']) {
            $where_conditions[] = "sm.is_verified = 1";
        }
        
        // Exclude templates already copied by current user
        if ($this->user_id && isset($filters['exclude_copied']) && $filters['exclude_copied']) {
            $where_conditions[] = "NOT EXISTS (SELECT 1 FROM user_station_templates ust WHERE ust.template_id = sm.id AND ust.user_id = ?)";
            $params[] = $this->user_id;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Sorting
        $sort_column = $filters['sort'] ?? 'usage_count';
        $sort_order = ($filters['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        
        $valid_sorts = ['name', 'call_letters', 'usage_count', 'created_at', 'last_tested'];
        if (!in_array($sort_column, $valid_sorts)) {
            $sort_column = 'usage_count';
        }
        
        // Pagination
        $page = max(1, (int)($filters['page'] ?? 1));
        $per_page = min(50, max(10, (int)($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $per_page;
        
        try {
            // Get templates with additional info
            $templates = $this->db->fetchAll("
                SELECT sm.*,
                       u.username as created_by_username,
                       AVG(str.rating) as avg_rating,
                       COUNT(str.id) as review_count,
                       GROUP_CONCAT(tc.name SEPARATOR ', ') as categories
                FROM stations_master sm
                LEFT JOIN users u ON sm.created_by_user_id = u.id
                LEFT JOIN station_template_reviews str ON sm.id = str.template_id
                LEFT JOIN station_template_categories stc ON sm.id = stc.template_id
                LEFT JOIN template_categories tc ON stc.category_id = tc.id
                WHERE $where_clause
                GROUP BY sm.id
                ORDER BY sm.$sort_column $sort_order
                LIMIT $per_page OFFSET $offset
            ", $params);
            
            // Get total count for pagination
            $total_count = $this->db->fetchOne("
                SELECT COUNT(DISTINCT sm.id) as count
                FROM stations_master sm
                LEFT JOIN station_template_categories stc ON sm.id = stc.template_id
                WHERE $where_clause
            ", $params)['count'];
            
            // Check which templates current user has already copied
            $copied_template_ids = [];
            if ($this->user_id) {
                $copied = $this->db->fetchAll("
                    SELECT template_id 
                    FROM user_station_templates 
                    WHERE user_id = ?
                ", [$this->user_id]);
                $copied_template_ids = array_column($copied, 'template_id');
            }
            
            // Add copied status to each template
            foreach ($templates as &$template) {
                $template['already_copied'] = in_array($template['id'], $copied_template_ids);
                $template['avg_rating'] = $template['avg_rating'] ? round($template['avg_rating'], 1) : null;
            }
            
            return [
                'success' => true,
                'templates' => $templates,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_count' => $total_count,
                    'total_pages' => ceil($total_count / $per_page)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to browse templates: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Copy a template to user's station collection
     */
    public function copyTemplate($template_id, $custom_name = null) {
        if (!$this->user_id) {
            return ['success' => false, 'error' => 'Authentication required'];
        }
        
        try {
            // Get template details
            $template = $this->db->fetchOne("
                SELECT * FROM stations_master 
                WHERE id = ? AND is_active = 1
            ", [$template_id]);
            
            if (!$template) {
                return ['success' => false, 'error' => 'Template not found'];
            }
            
            // Check if user already copied this template
            $existing = $this->db->fetchOne("
                SELECT id FROM user_station_templates 
                WHERE user_id = ? AND template_id = ?
            ", [$this->user_id, $template_id]);
            
            if ($existing) {
                return ['success' => false, 'error' => 'You have already copied this template'];
            }
            
            // Begin transaction
            $pdo = $this->db->connect();
            $pdo->beginTransaction();
            
            // Create new station for user
            $station_name = $custom_name ?: $template['name'];
            $station_id = $this->db->insert('stations', [
                'user_id' => $this->user_id,
                'name' => $station_name,
                'call_letters' => $template['call_letters'],
                'stream_url' => $template['stream_url'],
                'website_url' => $template['website_url'],
                'logo_url' => $template['logo_url'],
                'calendar_url' => $template['calendar_url'],
                'timezone' => $template['timezone'],
                'description' => $template['description'],
                'genre' => $template['genre'],
                'language' => $template['language'],
                'country' => $template['country'],
                'is_private' => true, // User's copy is private
                'template_source_id' => $template_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Record the template copy relationship
            $this->db->insert('user_station_templates', [
                'user_id' => $this->user_id,
                'template_id' => $template_id,
                'station_id' => $station_id,
                'copied_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update template usage count
            $this->db->update('stations_master', [
                'usage_count' => $template['usage_count'] + 1
            ], 'id = :id', ['id' => $template_id]);
            
            // Copy any associated shows if they exist in the template
            // (This could be enhanced later to copy show templates too)
            
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => "Template '{$template['name']}' copied successfully as '{$station_name}'",
                'station_id' => $station_id
            ];
            
        } catch (Exception $e) {
            $pdo->rollback();
            return [
                'success' => false,
                'error' => 'Failed to copy template: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Submit a user's station as a public template
     */
    public function submitAsTemplate($station_id, $submission_data = []) {
        if (!$this->user_id) {
            return ['success' => false, 'error' => 'Authentication required'];
        }
        
        try {
            // Get station details and verify ownership
            $station = $this->db->fetchOne("
                SELECT * FROM stations 
                WHERE id = ? AND user_id = ?
            ", [$station_id, $this->user_id]);
            
            if (!$station) {
                return ['success' => false, 'error' => 'Station not found or not owned by you'];
            }
            
            // Check if station is already submitted as template
            if ($station['submitted_as_template']) {
                return ['success' => false, 'error' => 'This station has already been submitted as a template'];
            }
            
            // Check if template with same call letters already exists
            $existing = $this->db->fetchOne("
                SELECT id FROM stations_master 
                WHERE call_letters = ? AND is_active = 1
            ", [$station['call_letters']]);
            
            if ($existing) {
                return ['success' => false, 'error' => 'A template for this station already exists'];
            }
            
            // Begin transaction
            $pdo = $this->db->connect();
            $pdo->beginTransaction();
            
            // Create template entry
            $template_id = $this->db->insert('stations_master', [
                'name' => $submission_data['name'] ?? $station['name'],
                'call_letters' => $station['call_letters'],
                'stream_url' => $station['stream_url'],
                'website_url' => $station['website_url'],
                'logo_url' => $station['logo_url'],
                'calendar_url' => $station['calendar_url'],
                'timezone' => $station['timezone'],
                'description' => $submission_data['description'] ?? $station['description'],
                'genre' => $submission_data['genre'] ?? $station['genre'],
                'language' => $submission_data['language'] ?? $station['language'] ?? 'English',
                'country' => $submission_data['country'] ?? $station['country'] ?? 'United States',
                'bitrate' => $submission_data['bitrate'] ?? null,
                'format' => $submission_data['format'] ?? null,
                'created_by_user_id' => $this->user_id,
                'is_verified' => false, // Requires admin verification
                'is_active' => true,
                'usage_count' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Mark original station as submitted
            $this->db->update('stations', [
                'submitted_as_template' => true
            ], 'id = :id', ['id' => $station_id]);
            
            // Add to categories if specified
            if (!empty($submission_data['category_ids'])) {
                foreach ($submission_data['category_ids'] as $category_id) {
                    $this->db->insert('station_template_categories', [
                        'template_id' => $template_id,
                        'category_id' => $category_id
                    ]);
                }
            }
            
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Station submitted as template successfully. It will be reviewed by administrators before being made public.',
                'template_id' => $template_id
            ];
            
        } catch (Exception $e) {
            $pdo->rollback();
            return [
                'success' => false,
                'error' => 'Failed to submit template: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get template categories for filtering
     */
    public function getCategories() {
        try {
            return $this->db->fetchAll("
                SELECT * FROM template_categories 
                WHERE is_active = 1 
                ORDER BY sort_order, name
            ");
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get template details with reviews and categories
     */
    public function getTemplateDetails($template_id) {
        try {
            $template = $this->db->fetchOne("
                SELECT sm.*,
                       u.username as created_by_username,
                       AVG(str.rating) as avg_rating,
                       COUNT(str.id) as review_count
                FROM stations_master sm
                LEFT JOIN users u ON sm.created_by_user_id = u.id
                LEFT JOIN station_template_reviews str ON sm.id = str.template_id
                WHERE sm.id = ? AND sm.is_active = 1
                GROUP BY sm.id
            ", [$template_id]);
            
            if (!$template) {
                return ['success' => false, 'error' => 'Template not found'];
            }
            
            // Get categories
            $categories = $this->db->fetchAll("
                SELECT tc.* 
                FROM template_categories tc
                JOIN station_template_categories stc ON tc.id = stc.category_id
                WHERE stc.template_id = ?
                ORDER BY tc.sort_order
            ", [$template_id]);
            
            // Get recent reviews
            $reviews = $this->db->fetchAll("
                SELECT str.*, u.username
                FROM station_template_reviews str
                LEFT JOIN users u ON str.user_id = u.id
                WHERE str.template_id = ?
                ORDER BY str.created_at DESC
                LIMIT 10
            ", [$template_id]);
            
            $template['categories'] = $categories;
            $template['reviews'] = $reviews;
            $template['avg_rating'] = $template['avg_rating'] ? round($template['avg_rating'], 1) : null;
            
            return [
                'success' => true,
                'template' => $template
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to get template details: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Admin: Verify a template
     */
    public function verifyTemplate($template_id, $verified = true) {
        try {
            $this->db->update('stations_master', [
                'is_verified' => $verified ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $template_id]);
            
            return [
                'success' => true,
                'message' => $verified ? 'Template verified successfully' : 'Template verification removed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to update template verification: ' . $e->getMessage()
            ];
        }
    }
}