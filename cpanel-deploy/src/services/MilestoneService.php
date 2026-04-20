<?php
/**
 * Milestone Service
 * Handles milestone tracking, progress updates, and notifications
 * Church Financial Partnership System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Milestone.php';
require_once __DIR__ . '/../models/Project.php';
require_once __DIR__ . '/../services/EmailService.php';

class MilestoneService {
    private $db;
    private $milestoneModel;
    private $projectModel;
    private $emailService;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->milestoneModel = new Milestone();
        $this->projectModel = new Project();
        $this->emailService = new EmailService();
    }
    
    /**
     * Check all milestones and send notifications for completed ones
     */
    public function checkAllMilestones() {
        $pendingMilestones = $this->milestoneModel->getPendingNotifications();
        $notificationsSent = [];
        
        foreach ($pendingMilestones as $milestone) {
            if ($this->isMilestoneComplete($milestone)) {
                if ($this->sendMilestoneNotification($milestone)) {
                    $this->milestoneModel->markNotificationSent($milestone['milestone_id']);
                    $notificationsSent[] = $milestone;
                }
            }
        }
        
        return $notificationsSent;
    }
    
    /**
     * Check if a milestone is complete
     */
    private function isMilestoneComplete($milestone) {
        return (float) $milestone['current_amount'] >= (float) $milestone['target_amount'];
    }
    
    /**
     * Send milestone notification to all involved parties
     */
    public function sendMilestoneNotification($milestone) {
        $project = $this->projectModel->getById($milestone['project_id']);
        if (!$project) {
            return false;
        }
        
        $milestoneData = [
            'milestone_id' => $milestone['milestone_id'],
            'title' => $milestone['title'],
            'description' => $milestone['description'],
            'target_amount' => $milestone['target_amount'],
            'current_amount' => $milestone['current_amount'],
            'progress_percentage' => $this->milestoneModel->getProgressPercentage($milestone['milestone_id']),
            'project_title' => $project['title'],
            'project_description' => $project['description']
        ];
        
        // Get all users who have contributed to this project
        $participants = $this->getProjectParticipants($milestone['project_id']);
        
        // Get all admin emails
        $adminEmails = $this->getAdminEmails();
        
        // Send to participants
        foreach ($participants as $participant) {
            $this->emailService->sendMilestoneReached(
                $participant['email'],
                $milestoneData,
                $participant['first_name'],
                $participant['last_name']
            );
        }
        
        // Send to admins
        foreach ($adminEmails as $adminEmail) {
            $this->emailService->sendMilestoneAdminNotification(
                $adminEmail,
                $milestoneData
            );
        }
        
        return true;
    }
    
    /**
     * Get all participants who have contributed to a project
     */
    private function getProjectParticipants($projectId) {
        $sql = "SELECT DISTINCT u.user_id, u.email, u.first_name, u.last_name
                FROM users u
                INNER JOIN project_participants pp ON u.user_id = pp.user_id
                WHERE pp.project_id = :project_id AND pp.is_active = TRUE";
        
        return $this->db->fetchAll($sql, ['project_id' => (int)$projectId]);
    }
    
    /**
     * Get all admin emails
     */
    private function getAdminEmails() {
        $sql = "SELECT u.email
                FROM admin_users au
                INNER JOIN users u ON au.user_id = u.user_id
                WHERE u.is_active = TRUE";
        
        $admins = $this->db->fetchAll($sql, []);
        return array_column($admins, 'email');
    }
    
    /**
     * Update milestone progress when a donation is made
     */
    public function updateMilestoneProgressOnDonation($projectId, $amount, $userId) {
        $milestones = $this->milestoneModel->getByProject($projectId);
        $updatedMilestones = [];
        
        foreach ($milestones as $milestone) {
            // Check if this donation should be allocated to this milestone
            // For now, allocate proportionally based on remaining amount
            if (!$this->isMilestoneComplete($milestone)) {
                $remaining = (float) $milestone['target_amount'] - (float) $milestone['current_amount'];
                
                if ($remaining > 0) {
                    // Allocate donation proportionally
                    $allocation = min($amount, $remaining);
                    
                    if ($this->milestoneModel->updateProgress($milestone['milestone_id'], $allocation)) {
                        $updatedMilestones[] = $milestone;
                        
                        // Check if milestone is now complete
                        if ($this->isMilestoneComplete($milestone)) {
                            $this->sendMilestoneNotification($milestone);
                            $this->milestoneModel->markNotificationSent($milestone['milestone_id']);
                        }
                    }
                }
            }
        }
        
        return $updatedMilestones;
    }
    
    /**
     * Get milestone status summary for a project
     */
    public function getMilestoneSummary($projectId) {
        $milestones = $this->milestoneModel->getByProject($projectId);
        
        $summary = [
            'total_milestones' => count($milestones),
            'completed_milestones' => 0,
            'pending_milestones' => 0,
            'total_target' => 0,
            'total_raised' => 0,
            'overall_progress' => 0
        ];
        
        foreach ($milestones as $milestone) {
            $summary['total_target'] += (float) $milestone['target_amount'];
            $summary['total_raised'] += (float) $milestone['current_amount'];
            
            if ($this->isMilestoneComplete($milestone)) {
                $summary['completed_milestones']++;
            } else {
                $summary['pending_milestones']++;
            }
        }
        
        if ($summary['total_target'] > 0) {
            $summary['overall_progress'] = round(
                ($summary['total_raised'] / $summary['total_target']) * 100, 
                1
            );
        }
        
        return $summary;
    }
    
    /**
     * Get upcoming milestones (not yet completed)
     */
    public function getUpcomingMilestones($projectId) {
        $milestones = $this->milestoneModel->getByProject($projectId);
        $upcoming = [];
        
        foreach ($milestones as $milestone) {
            if (!$this->isMilestoneComplete($milestone)) {
                $milestone['progress_percentage'] = $this->milestoneModel->getProgressPercentage($milestone['milestone_id']);
                $milestone['remaining'] = (float) $milestone['target_amount'] - (float) $milestone['current_amount'];
                $upcoming[] = $milestone;
            }
        }
        
        return $upcoming;
    }
}