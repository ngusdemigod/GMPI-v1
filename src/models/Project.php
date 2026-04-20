<?php
/**
 * Project Model
 * Handles project management and tracking
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../config/database.php';

class Project {
    private $db;
    private static $schemaReady = false;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        try {
            $slugColumn = $this->db->fetchOne(
                "SELECT COUNT(*) AS column_count
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'projects'
                   AND COLUMN_NAME = 'slug'"
            );

            if ((int) ($slugColumn['column_count'] ?? 0) === 0) {
                $this->db->execute("ALTER TABLE projects ADD COLUMN slug VARCHAR(255) NULL AFTER title");
            }

            self::$schemaReady = true;
        } catch (Throwable $e) {
            error_log('Project schema bootstrap failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all active projects
     * Fixed: Ensure all active projects are returned regardless of display_order
     */
    public function getActiveProjects() {
        return $this->db->fetchAll(
            "SELECT *, project_id as id FROM projects 
             WHERE is_active = TRUE 
             ORDER BY COALESCE(display_order, 0), end_date ASC",
            []
        );
    }
    
    /**
     * Get project by ID
     */
    public function getById($projectId) {
        return $this->db->fetchOne(
            "SELECT *, project_id as id FROM projects WHERE project_id = :project_id",
            ['project_id' => (int)$projectId]
        );
    }

    /**
     * Get project by slug.
     */
    public function getBySlug(string $slug) {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        try {
            return $this->db->fetchOne(
                "SELECT *, project_id as id FROM projects WHERE slug = :slug LIMIT 1",
                ['slug' => $slug]
            );
        } catch (Throwable $e) {
            error_log('Project slug lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get milestones for a project (from new project_milestones table)
     */
    public function getMilestones($projectId) {
        $milestoneModel = new Milestone();
        return $milestoneModel->getWithProgress($projectId);
    }
    
    /**
     * Get active projects with milestones included
     */
    public function getActiveProjectsWithMilestones() {
        $projects = $this->getActiveProjects();

        try {
            $milestoneModel = new Milestone();
        } catch (Throwable $e) {
            error_log("Milestone model initialization failed: " . $e->getMessage());
            foreach ($projects as &$project) {
                $project['milestones'] = [];
            }
            unset($project);
            return $projects;
        }

        foreach ($projects as &$project) {
            try {
                $project['milestones'] = $milestoneModel->getWithProgress($project['project_id']);
            } catch (Throwable $e) {
                error_log(
                    "Milestone load failed for project {$project['project_id']}: " . $e->getMessage()
                );
                $project['milestones'] = [];
            }
        }

        unset($project);
        return $projects;
    }
    
    /**
     * Get project by ID with milestones
     */
    public function getByIdWithMilestones($projectId) {
        $project = $this->getById($projectId);
        
        if (!$project) {
            return null;
        }
        
        $milestoneModel = new Milestone();
        $project['milestones'] = $milestoneModel->getWithProgress($projectId);
        
        return $project;
    }

    /**
     * Build the public project detail URL using slug when available.
     */
    public static function buildDetailUrl(array $project): string
    {
        $slug = trim((string) ($project['slug'] ?? ''));
        if ($slug !== '') {
            return 'project.php?slug=' . urlencode($slug);
        }

        $projectId = (int) ($project['project_id'] ?? $project['id'] ?? 0);
        return 'project.php?id=' . $projectId;
    }

    /**
     * Get recent completed project contributions with donor details.
     */
    public function getRecentContributions($projectId, $limit = 5) {
        return $this->db->fetchAll(
            "SELECT
                t.transaction_id,
                t.amount,
                t.transaction_date,
                t.project_id,
                u.first_name,
                u.last_name,
                pp.total_contributed,
                pp.contribution_count
             FROM transactions t
             LEFT JOIN users u ON t.user_id = u.user_id
             LEFT JOIN project_participants pp
                ON pp.project_id = t.project_id
               AND pp.user_id = t.user_id
             WHERE t.project_id = :project_id
               AND t.status = 'completed'
             ORDER BY t.transaction_date DESC
             LIMIT :limit",
            ['project_id' => (int) $projectId, 'limit' => (int) $limit]
        );
    }

    /**
     * Get top project participants from project_participants.
     */
    public function getTopParticipants($projectId, $limit = 4) {
        return $this->db->fetchAll(
            "SELECT
                pp.user_id,
                pp.total_contributed,
                pp.contribution_count,
                pp.last_contribution_date,
                u.first_name,
                u.last_name
             FROM project_participants pp
             INNER JOIN users u ON pp.user_id = u.user_id
             WHERE pp.project_id = :project_id
               AND pp.is_active = TRUE
             ORDER BY pp.total_contributed DESC, pp.last_contribution_date DESC
             LIMIT :limit",
            ['project_id' => (int) $projectId, 'limit' => (int) $limit]
        );
    }
    
    /**
     * Get project progress details
     */
    public function getProgress($projectId) {
        $project = $this->getById($projectId);
        
        if (!$project) {
            return null;
        }
        
        $percentage = $project['goal_amount'] > 0 
            ? round(($project['current_amount'] / $project['goal_amount']) * 100, 1) 
            : 0;
        
        $daysRemaining = 0;
        if ($project['end_date']) {
            $end = new DateTime($project['end_date']);
            $now = new DateTime();
            $daysRemaining = $end->diff($now)->days;
        }
        
        return [
            'project' => $project,
            'percentage' => $percentage,
            'daysRemaining' => $daysRemaining,
            'daysOverdue' => $daysRemaining < 0 ? abs($daysRemaining) : 0
        ];
    }
    
    /**
     * Get projects by category
     */
    public function getByCategory($category) {
        return $this->db->fetchAll(
            "SELECT *, project_id as id FROM projects 
             WHERE category = :category AND is_active = TRUE 
             ORDER BY display_order",
            ['category' => $category]
        );
    }
    
    /**
     * Get urgent projects (ending soon)
     */
    public function getUrgentProjects($daysThreshold = 30) {
        $thresholdDate = date('Y-m-d', strtotime("+{$daysThreshold} days"));
        
        return $this->db->fetchAll(
            "SELECT *, project_id as id FROM projects 
             WHERE is_active = TRUE 
             AND end_date <= :threshold_date
             ORDER BY end_date ASC",
            ['threshold_date' => $thresholdDate]
        );
    }
    
    /**
     * Add contribution to project
     */
    public function addContribution($projectId, $userId, $amount, $transactionReference) {
        $this->db->beginTransaction();
        
        try {
            // Update project amount
            $this->db->execute(
                "UPDATE projects SET current_amount = current_amount + :amount WHERE project_id = :project_id",
                ['amount' => $amount, 'project_id' => $projectId]
            );
            
            // Update or insert project participant
            $participant = $this->db->fetchOne(
                "SELECT participant_id FROM project_participants 
                 WHERE project_id = :project_id AND user_id = :user_id",
                ['project_id' => $projectId, 'user_id' => $userId]
            );
            
            if ($participant) {
                $this->db->execute(
                    "UPDATE project_participants 
                     SET total_contributed = total_contributed + :amount,
                         contribution_count = contribution_count + 1,
                         last_contribution_date = :transaction_date,
                         is_active = TRUE
                     WHERE participant_id = :participant_id",
                    [
                        'amount' => $amount,
                        'transaction_date' => date('Y-m-d'),
                        'participant_id' => $participant['participant_id']
                    ]
                );
            } else {
                $this->db->execute(
                    "INSERT INTO project_participants (project_id, user_id, total_contributed, contribution_count, first_contribution_date, last_contribution_date)
                     VALUES (:project_id, :user_id, :amount, 1, :transaction_date, :transaction_date)",
                    [
                        'project_id' => $projectId,
                        'user_id' => $userId,
                        'amount' => $amount,
                        'transaction_date' => date('Y-m-d')
                    ]
                );
                
                // Update partner count
                $this->db->execute(
                    "UPDATE projects SET partner_count = partner_count + 1 WHERE project_id = :project_id",
                    ['project_id' => $projectId]
                );
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Project contribution failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get project statistics
     */
    public function getStatistics() {
        return $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_projects,
                SUM(goal_amount) as total_goal,
                SUM(current_amount) as total_raised,
                SUM(partner_count) as total_partners
             FROM projects WHERE is_active = TRUE"
        );
    }
    
    /**
     * Create a new project
     */
    public function create($data) {
        $this->db->execute(
            "INSERT INTO projects (title, description, category, goal_amount, start_date, end_date, icon)
             VALUES (:title, :description, :category, :goal_amount, :start_date, :end_date, :icon)",
            [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'],
                'goal_amount' => $data['goal_amount'],
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'icon' => $data['icon'] ?? 'default'
            ]
        );
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Update project
     */
    public function update($projectId, $data) {
        $updates = [];
        $params = ['project_id' => $projectId];
        
        $allowedFields = ['title', 'description', 'goal_amount', 'end_date', 'is_active', 'display_order'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $updates[] = 'updated_at = NOW()';
        
        $sql = "UPDATE projects SET " . implode(', ', $updates) . " WHERE project_id = :project_id";
        
        return $this->db->execute($sql, $params) > 0;
    }
    
    /**
     * Get category tags
     */
    public static function getCategories() {
        return ['Urgent', 'Missions', 'Seasonal', 'Scholarship', 'Building', 'General'];
    }
}
