<?php
/**
 * Milestone Model
 * Handles milestone management and tracking
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../config/database.php';

class Milestone {
    private $db;
    private static $tableReady = false;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->ensureTableExists();
    }

    private function ensureTableExists(): void
    {
        if (self::$tableReady) {
            return;
        }

        try {
            $this->db->execute(
                "CREATE TABLE IF NOT EXISTS project_milestones (
                    milestone_id INT AUTO_INCREMENT PRIMARY KEY,
                    project_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT NULL,
                    target_amount DECIMAL(15,2) NOT NULL,
                    current_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                    notification_sent TINYINT(1) NOT NULL DEFAULT 0,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    display_order INT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_project_id (project_id),
                    INDEX idx_active (is_active),
                    INDEX idx_display_order (display_order),
                    CONSTRAINT fk_project_milestones_project
                        FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            self::$tableReady = true;
        } catch (Throwable $e) {
            error_log('Milestone table bootstrap failed: ' . $e->getMessage());
        }
    }

    private function isMissingTableException(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'project_milestones')
            && (str_contains($message, 'base table') || str_contains($message, 'doesn\'t exist'));
    }

    private function retryAfterBootstrap(callable $callback, $fallback)
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            if (!$this->isMissingTableException($e)) {
                throw $e;
            }

            $this->ensureTableExists();

            try {
                return $callback();
            } catch (Throwable $retryException) {
                error_log('Milestone query fallback failed: ' . $retryException->getMessage());
                return $fallback;
            }
        }
    }

    public function annotateForProjectProgress(array $milestones, float $projectCurrentAmount, float $projectGoalAmount): array
    {
        foreach ($milestones as &$milestone) {
            $targetAmount = (float) ($milestone['target_amount'] ?? 0);
            $effectiveTarget = $targetAmount;

            if ($projectGoalAmount > 0 && ($targetAmount <= 0 || $targetAmount > $projectGoalAmount)) {
                $effectiveTarget = $projectGoalAmount;
            }

            $isGoalReachedMilestone = $projectGoalAmount > 0 && $targetAmount >= $projectGoalAmount;
            $isComplete = $effectiveTarget > 0 && $projectCurrentAmount >= $effectiveTarget;
            $progress = $effectiveTarget > 0
                ? round(min(($projectCurrentAmount / $effectiveTarget) * 100, 100), 1)
                : 0;

            $milestone['effective_target_amount'] = $effectiveTarget;
            $milestone['progress_percentage'] = $progress;
            $milestone['is_complete'] = $isComplete;
            $milestone['is_goal_reached_milestone'] = $isGoalReachedMilestone;
            $milestone['display_title'] = $isGoalReachedMilestone ? 'Goals Reached' : ($milestone['title'] ?? 'Milestone');
            $milestone['display_label'] = $isGoalReachedMilestone && $isComplete
                ? 'Goals Reached'
                : ($isComplete ? 'Reached' : ($progress > 0 ? $progress . '% Complete' : 'Upcoming'));
        }
        unset($milestone);

        return $milestones;
    }
    
    /**
     * Get all milestones for a project
     */
    public function getByProject($projectId) {
        return $this->retryAfterBootstrap(
            function () use ($projectId) {
                return $this->db->fetchAll(
                    "SELECT * FROM project_milestones 
                     WHERE project_id = :project_id AND is_active = TRUE 
                     ORDER BY display_order, milestone_id ASC",
                    ['project_id' => (int)$projectId]
                );
            },
            []
        );
    }
    
    /**
     * Get milestone by ID
     */
    public function getById($milestoneId) {
        return $this->retryAfterBootstrap(
            function () use ($milestoneId) {
                return $this->db->fetchOne(
                    "SELECT * FROM project_milestones WHERE milestone_id = :milestone_id",
                    ['milestone_id' => (int)$milestoneId]
                );
            },
            false
        );
    }
    
    /**
     * Get milestones that need notification (not yet sent)
     */
    public function getPendingNotifications($projectId = null) {
        $sql = "SELECT * FROM project_milestones 
                WHERE notification_sent = FALSE AND is_active = TRUE";
        $params = [];
        
        if ($projectId) {
            $sql .= " AND project_id = :project_id";
            $params['project_id'] = (int)$projectId;
        }
        
        $sql .= " ORDER BY display_order, milestone_id ASC";
        
        return $this->retryAfterBootstrap(
            function () use ($sql, $params) {
                return $this->db->fetchAll($sql, $params);
            },
            []
        );
    }
    
    /**
     * Create a new milestone
     */
    public function create($data) {
        $result = $this->retryAfterBootstrap(
            function () use ($data) {
                return $this->db->execute(
                    "INSERT INTO project_milestones 
                     (project_id, title, description, target_amount, current_amount, is_active, display_order)
                     VALUES (:project_id, :title, :description, :target_amount, :current_amount, :is_active, :display_order)",
                    [
                        'project_id' => (int)$data['project_id'],
                        'title' => $data['title'],
                        'description' => $data['description'] ?? null,
                        'target_amount' => floatval($data['target_amount']),
                        'current_amount' => floatval($data['current_amount'] ?? 0),
                        'is_active' => !empty($data['is_active']) ? true : false,
                        'display_order' => (int)($data['display_order'] ?? 0)
                    ]
                );
            },
            0
        );

        if (!$result) {
            return false;
        }

        return $this->db->lastInsertId();
    }
    
    /**
     * Update a milestone
     */
    public function update($milestoneId, $data) {
        $updates = [];
        $params = ['milestone_id' => (int)$milestoneId];
        
        $allowedFields = ['title', 'description', 'target_amount', 'current_amount', 'is_active', 'display_order'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = :{$field}";
                $params[$field] = match($field) {
                    'is_active' => !empty($data[$field]) ? true : false,
                    'display_order' => (int)$data[$field],
                    'target_amount', 'current_amount' => floatval($data[$field]),
                    default => $data[$field]
                };
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $updates[] = 'updated_at = NOW()';
        
        $sql = "UPDATE project_milestones SET " . implode(', ', $updates) . " WHERE milestone_id = :milestone_id";
        
        return $this->retryAfterBootstrap(
            function () use ($sql, $params) {
                return $this->db->execute($sql, $params) > 0;
            },
            false
        );
    }
    
    /**
     * Delete a milestone
     */
    public function delete($milestoneId) {
        return $this->retryAfterBootstrap(
            function () use ($milestoneId) {
                return $this->db->execute(
                    "DELETE FROM project_milestones WHERE milestone_id = :milestone_id",
                    ['milestone_id' => (int)$milestoneId]
                ) > 0;
            },
            false
        );
    }
    
    /**
     * Mark milestone as notification sent
     */
    public function markNotificationSent($milestoneId) {
        return $this->retryAfterBootstrap(
            function () use ($milestoneId) {
                return $this->db->execute(
                    "UPDATE project_milestones SET notification_sent = TRUE WHERE milestone_id = :milestone_id",
                    ['milestone_id' => (int)$milestoneId]
                ) > 0;
            },
            false
        );
    }
    
    /**
     * Update milestone progress (add to current amount)
     */
    public function updateProgress($milestoneId, $amount) {
        $milestone = $this->getById($milestoneId);
        if (!$milestone) {
            return false;
        }
        
        $newCurrentAmount = $milestone['current_amount'] + $amount;
        $targetAmount = $milestone['target_amount'];
        
        // Check if milestone is now complete
        $notificationSent = $milestone['notification_sent'];
        if ($newCurrentAmount >= $targetAmount && !$milestone['notification_sent']) {
            $notificationSent = true;
        }
        
        return $this->retryAfterBootstrap(
            function () use ($milestoneId, $newCurrentAmount, $notificationSent) {
                return $this->db->execute(
                    "UPDATE project_milestones 
                     SET current_amount = :current_amount, notification_sent = :notification_sent 
                     WHERE milestone_id = :milestone_id",
                    [
                        'milestone_id' => (int)$milestoneId,
                        'current_amount' => $newCurrentAmount,
                        'notification_sent' => $notificationSent
                    ]
                ) > 0;
            },
            false
        );
    }
    
    /**
     * Get milestone progress percentage
     */
    public function getProgressPercentage($milestoneId) {
        $milestone = $this->getById($milestoneId);
        if (!$milestone || $milestone['target_amount'] == 0) {
            return 0;
        }
        
        return round(($milestone['current_amount'] / $milestone['target_amount']) * 100, 1);
    }
    
    /**
     * Check if milestone is complete
     */
    public function isComplete($milestoneId) {
        $milestone = $this->getById($milestoneId);
        if (!$milestone) {
            return false;
        }
        
        return $milestone['current_amount'] >= $milestone['target_amount'];
    }
    
    /**
     * Get milestones by project with progress info
     */
    public function getWithProgress($projectId) {
        $milestones = $this->getByProject($projectId);

        $project = $this->db->fetchOne(
            "SELECT current_amount, goal_amount FROM projects WHERE project_id = :project_id LIMIT 1",
            ['project_id' => (int) $projectId]
        );

        if (!$project) {
            foreach ($milestones as &$milestone) {
                $milestone['progress_percentage'] = $this->getProgressPercentage($milestone['milestone_id']);
                $milestone['is_complete'] = $this->isComplete($milestone['milestone_id']);
                $milestone['effective_target_amount'] = (float) ($milestone['target_amount'] ?? 0);
                $milestone['is_goal_reached_milestone'] = false;
                $milestone['display_title'] = $milestone['title'] ?? 'Milestone';
                $milestone['display_label'] = $milestone['is_complete'] ? 'Reached' : ($milestone['progress_percentage'] > 0 ? $milestone['progress_percentage'] . '% Complete' : 'Upcoming');
            }
            unset($milestone);
            return $milestones;
        }

        return $this->annotateForProjectProgress(
            $milestones,
            (float) ($project['current_amount'] ?? 0),
            (float) ($project['goal_amount'] ?? 0)
        );
    }
    
    /**
     * Get all milestones across all projects
     */
    public function getAll() {
        return $this->retryAfterBootstrap(
            function () {
                return $this->db->fetchAll(
                    "SELECT pm.*, p.title as project_title 
                     FROM project_milestones pm 
                     LEFT JOIN projects p ON pm.project_id = p.project_id 
                     ORDER BY pm.display_order, pm.milestone_id ASC",
                    []
                );
            },
            []
        );
    }
}
