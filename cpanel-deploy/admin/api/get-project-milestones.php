<?php
/**
 * Get Project Milestones API
 * Returns milestones for a given project
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../../src/models/Milestone.php';

header('Content-Type: application/json');

$projectId = $_GET['project_id'] ?? null;

if (!$projectId) {
    echo json_encode(['error' => 'Project ID is required']);
    exit;
}

try {
    $milestoneModel = new Milestone();
    $milestones = $milestoneModel->getByProject((int)$projectId);
    
    echo json_encode(['milestones' => $milestones]);
} catch (Throwable $e) {
    error_log('Project milestones API error: ' . $e->getMessage());
    echo json_encode(['milestones' => []]);
}
