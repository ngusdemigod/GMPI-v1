<?php
/**
 * Projects Management Page
 * Church Financial Partnership System
 * 
 * Redesigned with dark navy cards, gold accents, and cream background
 */

require_once __DIR__ . '/includes/config.php';

// Require authentication
$admin = requireAdmin();

// Set page variables for layout
$pageTitle = 'Projects';
$pageSubtitle = 'Manage fundraising projects and campaigs';
$activePage = 'projects';

$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';
$projectId = $_GET['id'] ?? null;
$successMessage = '';
$errorMessage = '';

function projectAdminDefaultCategories(): array
{
    return [
        ['name' => 'Building', 'slug' => 'building', 'display_order' => 1],
        ['name' => 'Missions', 'slug' => 'missions', 'display_order' => 2],
        ['name' => 'Outreach', 'slug' => 'outreach', 'display_order' => 3],
        ['name' => 'Scholarship', 'slug' => 'scholarship', 'display_order' => 4],
        ['name' => 'Seasonal', 'slug' => 'seasonal', 'display_order' => 5],
        ['name' => 'Tithe', 'slug' => 'tithe', 'display_order' => 6],
        ['name' => 'Offering', 'slug' => 'offering', 'display_order' => 7],
        ['name' => 'General', 'slug' => 'general', 'display_order' => 8],
        ['name' => 'Other', 'slug' => 'other', 'display_order' => 9],
    ];
}

function projectAdminSlugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'project-' . substr(md5($value . microtime(true)), 0, 8);
}

function projectAdminNormalizeCategory(string $category): string
{
    $category = trim($category);
    if ($category === '') {
        return 'General';
    }

    $defaults = projectAdminDefaultCategories();
    foreach ($defaults as $default) {
        if (strcasecmp($default['name'], $category) === 0 || strcasecmp($default['slug'], $category) === 0) {
            return $default['name'];
        }
    }

    $words = preg_split('/[\s\-_]+/', strtolower($category)) ?: [];
    $words = array_filter(array_map(static function ($word) {
        return ucfirst($word);
    }, $words));

    return $words ? implode(' ', $words) : 'General';
}

function projectAdminEnsureUniqueSlug(Database $db, string $slug, ?int $projectId = null): string
{
    $baseSlug = projectAdminSlugify($slug);
    $candidate = $baseSlug;
    $suffix = 2;

    while (true) {
        $params = ['slug' => $candidate];
        $sql = "SELECT project_id FROM projects WHERE slug = :slug";

        if ($projectId !== null) {
            $sql .= " AND project_id != :project_id";
            $params['project_id'] = $projectId;
        }

        $existing = $db->fetchOne($sql . " LIMIT 1", $params);
        if (!$existing) {
            return $candidate;
        }

        $candidate = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function projectAdminColumnExists(Database $db, string $table, string $column): bool
{
    $result = $db->fetchOne(
        "SELECT COUNT(*) AS column_count
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name",
        [
            'table_name' => $table,
            'column_name' => $column,
        ]
    );

    return !empty($result) && (int) ($result['column_count'] ?? 0) > 0;
}

function projectAdminEnsureSchema(Database $db): void
{
    if (!projectAdminColumnExists($db, 'projects', 'slug')) {
        $db->execute("ALTER TABLE projects ADD COLUMN slug VARCHAR(255) NULL AFTER title");
    }

    if (!projectAdminColumnExists($db, 'projects', 'short_description')) {
        $db->execute("ALTER TABLE projects ADD COLUMN short_description VARCHAR(500) NULL AFTER description");
    }

    $categoryColumn = $db->fetchOne("SHOW COLUMNS FROM projects LIKE 'category'");
    $categoryType = strtolower((string) ($categoryColumn['Type'] ?? ''));
    if (str_starts_with($categoryType, 'enum(')) {
        $db->execute("ALTER TABLE projects MODIFY COLUMN category VARCHAR(100) NOT NULL DEFAULT 'General'");
    }

    $db->execute(
        "CREATE TABLE IF NOT EXISTS project_categories (
            category_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            display_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_project_categories_name (name),
            UNIQUE KEY uq_project_categories_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $existingCategories = $db->fetchAll("SELECT DISTINCT TRIM(category) AS category_name FROM projects WHERE COALESCE(TRIM(category), '') != ''");
    $seedCategories = projectAdminDefaultCategories();

    foreach ($existingCategories as $existingCategory) {
        $name = projectAdminNormalizeCategory((string) ($existingCategory['category_name'] ?? ''));
        $seedCategories[] = [
            'name' => $name,
            'slug' => projectAdminSlugify($name),
            'display_order' => count($seedCategories) + 1,
        ];
    }

    $seenNames = [];
    foreach ($seedCategories as $index => $category) {
        $name = projectAdminNormalizeCategory((string) ($category['name'] ?? 'General'));
        $nameKey = strtolower($name);
        if (isset($seenNames[$nameKey])) {
            continue;
        }
        $seenNames[$nameKey] = true;

        $db->execute(
            "INSERT INTO project_categories (name, slug, display_order, is_active)
             VALUES (:name, :slug, :display_order, 1)
             ON DUPLICATE KEY UPDATE
                display_order = LEAST(display_order, VALUES(display_order)),
                slug = VALUES(slug)",
            [
                'name' => $name,
                'slug' => projectAdminSlugify((string) ($category['slug'] ?? $name)),
                'display_order' => (int) ($category['display_order'] ?? ($index + 1)),
            ]
        );
    }
}

function projectAdminGetCategories(Database $db): array
{
    return $db->fetchAll(
        "SELECT category_id, name, slug, display_order, is_active
         FROM project_categories
         ORDER BY is_active DESC, display_order ASC, name ASC"
    );
}

function projectAdminNormalizeMilestones(array $rawMilestones, float $goalAmount): array
{
    $normalized = [];

    foreach ($rawMilestones as $milestone) {
        $title = sanitizeInput((string) ($milestone['title'] ?? ''));
        $description = sanitizeInput((string) ($milestone['description'] ?? ''));
        $targetRaw = trim((string) ($milestone['target_amount'] ?? ''));

        if ($title === '' && $targetRaw === '') {
            continue;
        }

        if ($title === '' || $targetRaw === '') {
            throw new Exception('Each milestone must include both a title and a target amount.');
        }

        $targetAmount = (float) $targetRaw;
        if ($targetAmount <= 0) {
            throw new Exception('Milestone amounts must be greater than zero.');
        }

        if ($goalAmount > 0 && $targetAmount > $goalAmount) {
            throw new Exception('A milestone target cannot be greater than the project goal amount.');
        }

        $normalized[] = [
            'title' => $title,
            'description' => $description,
            'target_amount' => $targetAmount,
        ];
    }

    if (!empty($normalized) && $goalAmount <= 0) {
        throw new Exception('Set a valid project goal amount before adding milestones.');
    }

    usort($normalized, static function (array $left, array $right): int {
        return $left['target_amount'] <=> $right['target_amount'];
    });

    $previousTarget = 0.0;
    foreach ($normalized as $index => &$milestone) {
        if ($milestone['target_amount'] <= $previousTarget) {
            throw new Exception('Milestone amounts must be progressive and increase from one milestone to the next.');
        }

        $previousTarget = $milestone['target_amount'];
        $milestone['display_order'] = $index + 1;
        $milestone['is_goal_marker'] = $goalAmount > 0 && $milestone['target_amount'] >= $goalAmount;
    }
    unset($milestone);

    return $normalized;
}

projectAdminEnsureSchema($db);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $formAction = $_POST['action'] ?? 'create';
    $formProjectId = $_POST['id'] ?? null;
    $categoryId = $_POST['category_id'] ?? null;
    
    try {
        if ($formAction === 'create' || $formAction === 'edit') {
            $title = sanitizeInput($_POST['title'] ?? '');
            $slug = sanitizeInput($_POST['slug'] ?? '');
            $description = sanitizeInput($_POST['description'] ?? '');
            $shortDescription = sanitizeInput($_POST['short_description'] ?? '');
            $category = projectAdminNormalizeCategory(sanitizeInput($_POST['category'] ?? ''));
            $goalAmount = floatval($_POST['goal_amount'] ?? 0);
            $startDate = trim((string) ($_POST['start_date'] ?? '')) ?: null;
            $endDate = trim((string) ($_POST['end_date'] ?? '')) ?: null;
            $isActive = $formAction === 'create' ? !isset($_POST['is_active']) || $_POST['is_active'] : isset($_POST['is_active']);
            $displayOrder = intval($_POST['display_order'] ?? 0);
            $icon = sanitizeInput($_POST['icon'] ?? 'building');
            $slug = $slug !== '' ? projectAdminSlugify($slug) : projectAdminSlugify($title);
            $slug = projectAdminEnsureUniqueSlug(
                $db,
                $slug,
                $formAction === 'edit' && $formProjectId ? (int) $formProjectId : null
            );
            
            if (empty($title)) {
                throw new Exception('Project name is required');
            }

            $normalizedMilestones = projectAdminNormalizeMilestones(
                is_array($_POST['milestones'] ?? null) ? $_POST['milestones'] : [],
                $goalAmount
            );
            
            if ($formAction === 'create') {
                $db->execute(
                    "INSERT INTO projects (title, slug, description, short_description, category, goal_amount, current_amount, start_date, end_date, is_active, display_order, icon, created_at)
                     VALUES (:title, :slug, :description, :short_description, :category, :goal_amount, 0, :start_date, :end_date, :is_active, :display_order, :icon, NOW())",
                    [
                        'title' => $title,
                        'slug' => $slug,
                        'description' => $description,
                        'short_description' => $shortDescription ?: null,
                        'category' => $category,
                        'goal_amount' => $goalAmount,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'is_active' => $isActive,
                        'display_order' => $displayOrder,
                        'icon' => $icon,
                    ]
                );
                
                $formProjectId = $db->lastInsertId();
                logAdminAction($admin['admin_id'], 'PROJECT_CREATE', 'project', $formProjectId, $title);
                
                // Handle milestones if provided
                if (!empty($normalizedMilestones)) {
                    require_once __DIR__ . '/../src/models/Milestone.php';
                    $milestoneModel = new Milestone();
                    foreach ($normalizedMilestones as $milestone) {
                        $milestoneModel->create([
                            'project_id' => $formProjectId,
                            'title' => $milestone['title'],
                            'description' => $milestone['description'],
                            'target_amount' => $milestone['target_amount'],
                            'current_amount' => 0,
                            'is_active' => true,
                            'display_order' => $milestone['display_order']
                        ]);
                    }
                }
                
                header('Location: projects.php?success=created');
                exit;
            } else {
                $db->execute(
                    "UPDATE projects 
                     SET title = :title, slug = :slug, description = :description, short_description = :short_description, category = :category,
                         goal_amount = :goal_amount, start_date = :start_date, end_date = :end_date,
                         is_active = :is_active, display_order = :display_order, icon = :icon
                     WHERE project_id = :project_id",
                    [
                        'title' => $title,
                        'slug' => $slug,
                        'description' => $description,
                        'short_description' => $shortDescription ?: null,
                        'category' => $category,
                        'goal_amount' => $goalAmount,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'is_active' => $isActive,
                        'display_order' => $displayOrder,
                        'icon' => $icon,
                        'project_id' => $formProjectId,
                    ]
                );
                
                // Handle milestones update
                require_once __DIR__ . '/../src/models/Milestone.php';
                $milestoneModel = new Milestone();
                
                // Delete existing milestones for this project
                $existingMilestones = $milestoneModel->getByProject($formProjectId);
                foreach ($existingMilestones as $existing) {
                    $milestoneModel->delete($existing['milestone_id']);
                }

                // Add normalized milestones back in progressive order
                foreach ($normalizedMilestones as $milestone) {
                    $milestoneModel->create([
                        'project_id' => $formProjectId,
                        'title' => $milestone['title'],
                        'description' => $milestone['description'],
                        'target_amount' => $milestone['target_amount'],
                        'current_amount' => 0,
                        'is_active' => true,
                        'display_order' => $milestone['display_order']
                    ]);
                }
                
                logAdminAction($admin['admin_id'], 'PROJECT_UPDATE', 'project', $formProjectId, $title);
                header('Location: projects.php?success=updated');
                exit;
            }
        } elseif ($formAction === 'save_category') {
            $categoryName = projectAdminNormalizeCategory((string) ($_POST['category_name'] ?? ''));
            $categorySlug = projectAdminSlugify((string) ($_POST['category_slug'] ?? $categoryName));
            $displayOrder = (int) ($_POST['category_display_order'] ?? 0);
            $isActive = isset($_POST['category_is_active']) ? 1 : 0;

            if ($categoryName === '') {
                throw new Exception('Category name is required.');
            }

            $duplicateParams = [
                'name' => $categoryName,
                'slug' => $categorySlug,
            ];
            $duplicateSql = "SELECT category_id
                             FROM project_categories
                             WHERE (LOWER(name) = LOWER(:name) OR slug = :slug)";

            if ($categoryId !== null && $categoryId !== '') {
                $duplicateSql .= " AND category_id != :category_id";
                $duplicateParams['category_id'] = (int) $categoryId;
            }

            $duplicateSql .= " LIMIT 1";
            $duplicate = $db->fetchOne($duplicateSql, $duplicateParams);

            if ($duplicate) {
                throw new Exception('That category already exists.');
            }

            if ($categoryId) {
                $existingCategory = $db->fetchOne(
                    "SELECT * FROM project_categories WHERE category_id = :category_id",
                    ['category_id' => (int) $categoryId]
                );

                if (!$existingCategory) {
                    throw new Exception('The selected category no longer exists.');
                }

                $db->execute(
                    "UPDATE project_categories
                     SET name = :name, slug = :slug, display_order = :display_order, is_active = :is_active
                     WHERE category_id = :category_id",
                    [
                        'name' => $categoryName,
                        'slug' => $categorySlug,
                        'display_order' => $displayOrder,
                        'is_active' => $isActive,
                        'category_id' => (int) $categoryId,
                    ]
                );

                if (strcasecmp((string) $existingCategory['name'], $categoryName) !== 0) {
                    $db->execute(
                        "UPDATE projects SET category = :new_name WHERE category = :old_name",
                        [
                            'new_name' => $categoryName,
                            'old_name' => $existingCategory['name'],
                        ]
                    );
                }

                logAdminAction($admin['admin_id'], 'PROJECT_CATEGORY_UPDATE', 'project_category', (int) $categoryId, $categoryName);
            } else {
                $db->execute(
                    "INSERT INTO project_categories (name, slug, display_order, is_active)
                     VALUES (:name, :slug, :display_order, :is_active)",
                    [
                        'name' => $categoryName,
                        'slug' => $categorySlug,
                        'display_order' => $displayOrder,
                        'is_active' => $isActive,
                    ]
                );

                logAdminAction($admin['admin_id'], 'PROJECT_CATEGORY_CREATE', 'project_category', $db->lastInsertId(), $categoryName);
            }

            header('Location: projects.php?success=category_saved');
            exit;
        } elseif ($formAction === 'delete_category') {
            $existingCategory = $db->fetchOne(
                "SELECT * FROM project_categories WHERE category_id = :category_id",
                ['category_id' => (int) $categoryId]
            );

            if (!$existingCategory) {
                throw new Exception('The selected category no longer exists.');
            }

            $projectsUsingCategory = $db->fetchOne(
                "SELECT COUNT(*) AS project_count FROM projects WHERE category = :category_name",
                ['category_name' => $existingCategory['name']]
            );

            if ((int) ($projectsUsingCategory['project_count'] ?? 0) > 0) {
                throw new Exception('This category is still assigned to one or more projects. Reassign those projects before deleting it.');
            }

            $db->execute(
                "DELETE FROM project_categories WHERE category_id = :category_id",
                ['category_id' => (int) $categoryId]
            );

            logAdminAction($admin['admin_id'], 'PROJECT_CATEGORY_DELETE', 'project_category', (int) $categoryId, $existingCategory['name']);
            header('Location: projects.php?success=category_deleted');
            exit;
        } elseif ($formAction === 'delete') {
            $project = $db->fetchOne("SELECT title FROM projects WHERE project_id = :id", ['id' => $formProjectId]);
            if ($project) {
                $db->execute("DELETE FROM projects WHERE project_id = :id", ['id' => $formProjectId]);
                logAdminAction($admin['admin_id'], 'PROJECT_DELETE', 'project', $formProjectId, $project['title']);
                $success = 'Project deleted successfully';
                header('Location: projects.php?success=deleted');
                exit;
            }
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

$successMessageMap = [
    'created' => 'Project created successfully.',
    'updated' => 'Project saved successfully.',
    'deleted' => 'Project deleted successfully.',
    'category_saved' => 'Project category saved successfully.',
    'category_deleted' => 'Project category deleted successfully.',
];

if (isset($_GET['success']) && isset($successMessageMap[$_GET['success']])) {
    $successMessage = $successMessageMap[$_GET['success']];
}

// Get project data
$project = null;
if ($action === 'edit' && $projectId) {
    $project = $db->fetchOne(
        "SELECT * FROM projects WHERE project_id = :id",
        ['id' => $projectId]
    );
} elseif ($action === 'create') {
    $action = 'list';
}

// Get projects list
$projects = $db->fetchAll(
    "SELECT *, 
            CASE WHEN goal_amount > 0 THEN (current_amount / goal_amount) * 100 ELSE 0 END as progress_percentage
     FROM projects 
     ORDER BY display_order, created_at DESC"
);
$projectCategories = projectAdminGetCategories($db);
$projectCategoryUsage = [];
foreach ($projects as $listedProject) {
    $categoryName = projectAdminNormalizeCategory((string) ($listedProject['category'] ?? 'General'));
    if (!isset($projectCategoryUsage[$categoryName])) {
        $projectCategoryUsage[$categoryName] = 0;
    }
    $projectCategoryUsage[$categoryName]++;
}

// Include layout
require_once __DIR__ . '/includes/layout.php';

// Define custom styles for this page
function layoutCustomStyles() {
    ?>
    <style>
        .projects-page {
            background:
                radial-gradient(circle at top left, rgba(201, 162, 75, 0.07), transparent 26%),
                radial-gradient(circle at bottom right, rgba(91, 123, 106, 0.05), transparent 24%),
                #FAF6EF;
            min-height: 100vh;
            padding: 0;
        }

        .main-content{
            padding:30px;
        }

        .projects-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 26px;
            padding: 0;
        }

        .projects-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: clamp(34px, 4vw, 46px);
            font-weight: 600;
            line-height: 1.04;
            letter-spacing: -0.04em;
            color: #0F1B2D;
            margin: 0;
        }

        .projects-subtitle {
            color: rgba(15, 27, 45, 0.58);
            font-size: 16px;
            line-height: 1.6;
            margin-top: 10px;
            max-width: 520px;
        }

        .desktop-page-header {
            display: flex;
        }

        .projects-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn-category-manage {
            background: rgba(255, 255, 255, 0.92);
            color: #0F1B2D;
            border: 1px solid rgba(15, 27, 45, 0.12);
            padding: 13px 18px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            box-shadow: 0 10px 24px rgba(15, 27, 45, 0.08);
        }

        .btn-category-manage:hover {
            background: white;
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(15, 27, 45, 0.12);
        }

        .btn-new-project {
            background: #C9A24B;
            color: #122137;
            border: 1px solid rgba(201, 162, 75, 0.24);
            padding: 13px 22px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            box-shadow: 0 10px 24px rgba(201, 162, 75, 0.18);
            white-space: nowrap;
        }

        .btn-new-project:hover {
            background: #E7D9B4;
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(201, 162, 75, 0.28);
        }

        .project-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .category-overview-card {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(15, 27, 45, 0.08);
            border-radius: 24px;
            box-shadow: 0 10px 24px rgba(15, 27, 45, 0.07);
            padding: 22px;
            margin-bottom: 22px;
        }

        .category-overview-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }

        .category-overview-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 22px;
            color: #0F1B2D;
            margin: 0 0 6px;
            letter-spacing: -0.03em;
        }

        .category-overview-copy {
            margin: 0;
            color: rgba(15, 27, 45, 0.58);
            font-size: 14px;
            line-height: 1.55;
            max-width: 620px;
        }

        .category-chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .category-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: #F7F3EA;
            border: 1px solid rgba(201, 162, 75, 0.22);
            color: #0F1B2D;
            font-size: 13px;
            font-weight: 600;
        }

        .category-chip-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 22px;
            border-radius: 999px;
            background: rgba(15, 27, 45, 0.08);
            color: rgba(15, 27, 45, 0.74);
            font-size: 11px;
            font-weight: 700;
            padding: 0 6px;
        }

        .project-card {
            background: rgba(255, 255, 255, 0.92);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(15, 27, 45, 0.08);
            box-shadow: 0 10px 24px rgba(15, 27, 45, 0.07);
            transition: transform 0.28s ease, box-shadow 0.28s ease;
        }

        .project-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 42px rgba(15, 27, 45, 0.1);
        }

        .project-card-header {
            padding: 22px 22px 14px;
        }

        .project-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 20px;
        }

        .project-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            box-shadow: 0 8px 18px rgba(15, 27, 45, 0.14);
        }

        .project-category-badge {
            padding: 6px 11px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            background: #F1F3F6;
            color: rgba(15, 27, 45, 0.68);
            border: 1px solid rgba(15, 27, 45, 0.06);
            white-space: nowrap;
        }

        .project-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 20px;
            font-weight: 400;
            color: #0F1B2D;
            margin: 0 0 8px;
            line-height: 1.15;
            letter-spacing: 0em;
            cursor: pointer;
        }

        .project-title:hover {
            color: #C9A24B;
        }

        .project-description {
            font-size: 13px;
            color: rgba(15, 27, 45, 0.62);
            line-height: 1.55;
            margin: 0;
        }

        .project-card-body {
            padding: 0 22px 22px;
        }

        .project-stats-row {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            align-items: end;
            margin-bottom: 12px;
        }

        .project-stat-item {
            text-align: left;
            flex: 1;
            padding: 0;
            background: transparent;
            border: none;
        }

        .project-stat-value {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: -0.03em;
            color: #C9A24B;
        }

        .project-stat-label {
            font-size: 11px;
            color: rgba(15, 27, 45, 0.58);
            letter-spacing: 0.02em;
            margin-top: 4px;
            text-transform: none;
        }

        .project-stat-item:last-child {
            text-align: right;
        }

        .project-progress-section {
            margin-bottom: 14px;
        }

        .progress-bar-bg {
            height: 8px;
            background: #E4E7EC;
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #C9A24B 0%, #E7D9B4 100%);
            border-radius: 999px;
            transition: width 0.5s ease;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
            font-size: 11px;
        }

        .progress-percentage {
            color: rgba(15, 27, 45, 0.7);
            font-weight: 600;
        }

        .progress-goal {
            color: rgba(15, 27, 45, 0.54);
        }

        .project-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            flex: 1;
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-edit {
            background: #F8FAFC;
            color: #0F1B2D;
            border-color: rgba(15, 27, 45, 0.08);
        }

        .btn-edit:hover {
            background: #EEF2F6;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: rgba(220, 53, 69, 0.06);
            color: #B93243;
            border-color: rgba(220, 53, 69, 0.22);
        }

        .btn-delete:hover {
            background: rgba(220, 53, 69, 0.25);
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 70px 24px;
            background: rgba(255, 255, 255, 0.84);
            border-radius: 28px;
            border: 1px solid rgba(10, 17, 31, 0.06);
            box-shadow: 0 16px 40px rgba(10, 17, 31, 0.08);
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            border-radius: 24px;
            background: linear-gradient(145deg, #e7d9b4, #f7efe0);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: #C9A24B;
        }

        .empty-state h3 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 28px;
            color: #0F1B2D;
            margin: 0 0 8px;
            letter-spacing: -0.03em;
        }

        .empty-state p {
            color: rgba(15, 27, 45, 0.6);
            margin: 0 0 24px;
            font-size: 15px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 27, 45, 0.6);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
        }
        
        .modal-overlay.active { display: flex; }

        .modal {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(250, 246, 239, 0.98));
            border-radius: 28px;
            width: 95%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid rgba(15, 27, 45, 0.08);
            box-shadow: 0 24px 70px rgba(15, 27, 45, 0.28);
        }

        .modal.modal-wide {
            max-width: 860px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 28px;
            border-bottom: 1px solid rgba(15, 27, 45, 0.1);
        }

        .modal-title {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 30px;
            font-weight: 600;
            color: #0F1B2D;
            margin: 0;
            letter-spacing: -0.03em;
        }

        .modal-close {
            background: none;
            border: 1px solid rgba(15, 27, 45, 0.08);
            font-size: 24px;
            color: rgba(15, 27, 45, 0.5);
            cursor: pointer;
            padding: 4px;
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: rgba(15, 27, 45, 0.1);
            color: #0F1B2D;
        }

        .modal-body {
            padding: 28px;
        }

        .form-section-title {
            font-size: 12px;
            font-weight: 600;
            color: rgba(15, 27, 45, 0.5);
            text-transform: uppercase;
            letter-spacing: 0.16em;
            margin: 0 0 16px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #0F1B2D;
            margin-bottom: 6px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 13px 16px;
            border: 1px solid rgba(15, 27, 45, 0.12);
            border-radius: 16px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            background: rgba(255, 255, 255, 0.96);
            color: #0F1B2D;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #C9A24B;
            box-shadow: 0 0 0 3px rgba(201, 162, 75, 0.15);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .icon-picker {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .icon-option input[type="radio"] {
            display: none;
        }

        .icon-option label {
            width: 48px;
            height: 48px;
            border: 1px solid rgba(15, 27, 45, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            color: rgba(15, 27, 45, 0.5);
            font-size: 18px;
            background: rgba(255, 255, 255, 0.88);
        }

        .icon-option input:checked + label {
            border-color: #C9A24B;
            background: #C9A24B;
            color: #122137;
        }

        .icon-option label:hover {
            border-color: #C9A24B;
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid rgba(15, 27, 45, 0.1);
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .category-modal-layout {
            display: grid;
            grid-template-columns: minmax(0, 0.95fr) minmax(280px, 1.05fr);
            gap: 22px;
            align-items: start;
        }

        .category-form-card,
        .category-list-card {
            background: rgba(255, 255, 255, 0.72);
            border-radius: 22px;
            border: 1px solid rgba(15, 27, 45, 0.08);
            padding: 20px;
        }

        .category-list {
            display: grid;
            gap: 12px;
        }

        .category-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(15, 27, 45, 0.08);
            background: rgba(255, 255, 255, 0.92);
        }

        .category-row strong {
            display: block;
            color: #0F1B2D;
            margin-bottom: 3px;
            font-size: 14px;
        }

        .category-row-meta {
            color: rgba(15, 27, 45, 0.56);
            font-size: 12px;
        }

        .category-row-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn-category-action {
            border: 1px solid rgba(15, 27, 45, 0.1);
            background: white;
            color: #0F1B2D;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-category-action:hover {
            background: #F8FAFC;
        }

        .btn-category-delete {
            border-color: rgba(220, 53, 69, 0.2);
            color: #B93243;
            background: rgba(220, 53, 69, 0.06);
        }

        .btn-category-delete:hover {
            background: rgba(220, 53, 69, 0.14);
        }

        .form-hint-inline {
            margin-top: 8px;
            font-size: 12px;
            color: rgba(15, 27, 45, 0.55);
        }

        .btn-cancel {
            padding: 12px 24px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border: 1px solid rgba(15, 27, 45, 0.12);
            background: white;
            color: #0F1B2D;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: rgba(15, 27, 45, 0.05);
        }

        .btn-save {
            padding: 12px 24px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border: none;
            background: linear-gradient(180deg, #d6b361 0%, #c9a24b 100%);
            color: #122137;
            transition: all 0.2s;
            box-shadow: 0 12px 24px rgba(201, 162, 75, 0.16);
        }

        .btn-save:hover {
            background: #E7D9B4;
        }

        /* Transactions table styles */
        .txn-table {
            width: 100%;
            border-collapse: collapse;
        }
        .txn-table th {
            padding: 10px 8px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(15, 27, 45, 0.5);
            border-bottom: 1px solid rgba(15, 27, 45, 0.08);
        }
        .txn-table td {
            padding: 10px 8px;
            font-size: 13px;
            color: rgba(15, 27, 45, 0.7);
            border-bottom: 1px solid rgba(15, 27, 45, 0.05);
        }
        .txn-table .amount-cell {
            text-align: right;
            font-weight: 600;
            color: #C9A24B;
        }
        .txn-status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }
        .txn-summary {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid rgba(15, 27, 45, 0.08);
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: rgba(15, 27, 45, 0.6);
        }

        @media (max-width: 1360px) {
            .project-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .category-modal-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .desktop-page-header {
                display: none;
            }

            .projects-page {
                padding: 0;
            }

            .project-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .projects-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
                padding: 22px 20px;
            }

            .category-overview-card {
                padding: 20px;
            }

            .category-overview-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .project-card-header,
            .project-card-body,
            .modal-header,
            .modal-body,
            .modal-footer {
                padding-left: 20px;
                padding-right: 20px;
            }

            .project-stats-row {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: start;
            }

            .project-actions {
                flex-wrap: nowrap;
            }

            .project-stat-item:last-child {
                text-align: right;
            }
        }
    </style>
    <?php
}

// Define page actions for layout
function layoutPageActions() {
    ?>
    <button class="btn-category-manage" type="button" onclick="openCategoryModal()">
        <i class="fas fa-tags"></i>
        <span class="btn-text">Categories</span>
    </button>
    <button class="btn-new-project" onclick="openProjectModal(null)">
        <i class="fas fa-plus"></i>
        <span class="btn-text">New Project</span>
    </button>
    <?php
}

// Start the layout
layoutHeader();
?>

<div class="projects-page">
    <section class="projects-header desktop-page-header">
        <div>
            <h2 class="projects-title">Projects</h2>
            <p class="projects-subtitle">Manage fundraising projects, keep their categories clean, and make sure public project data stays accurate.</p>
        </div>
        <div class="projects-header-actions">
            <button class="btn-category-manage" type="button" onclick="openCategoryModal()">
                <i class="fas fa-tags"></i>
                <span>Manage Categories</span>
            </button>
            <button class="btn-new-project" type="button" onclick="openProjectModal(null)">
                <i class="fas fa-plus"></i>
                <span>New Project</span>
            </button>
        </div>
    </section>

    <section class="category-overview-card">
        <div class="category-overview-top">
            <div>
                <h3 class="category-overview-title">Project Categories</h3>
                <p class="category-overview-copy">Categories are now managed directly from this page. Use them to keep project lists tidy and to make sure admins are saving valid project metadata.</p>
            </div>
            <button class="btn-category-manage" type="button" onclick="openCategoryModal()">
                <i class="fas fa-pen"></i>
                <span>Edit Categories</span>
            </button>
        </div>
        <div class="category-chip-list">
            <?php foreach ($projectCategories as $category): ?>
                <?php if (empty($category['is_active'])) { continue; } ?>
                <span class="category-chip">
                    <?php echo e($category['name']); ?>
                    <span class="category-chip-count"><?php echo (int) ($projectCategoryUsage[$category['name']] ?? 0); ?></span>
                </span>
            <?php endforeach; ?>
        </div>
    </section>
    
    <!-- Project Grid -->
    <?php if (empty($projects)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-building"></i>
            </div>
            <h3>No Projects Yet</h3>
            <p>Create your first fundraising project to get started</p>
            <button class="btn-new-project" onclick="openProjectModal(null)">
                <i class="fas fa-plus"></i>
                <span>Create Project</span>
            </button>
        </div>
    <?php else: ?>
        <div class="project-grid">
            <?php foreach ($projects as $p): 
                $progress = min($p['progress_percentage'], 100);
                $iconColors = [
                    'building' => '#1a1a2e',
                    'globe' => '#C9A24B',
                    'star' => '#C9A24B',
                    'book' => '#5B7B6A',
                    'heart' => '#dc3545',
                    'cross' => '#1a1a2e',
                    'people' => '#17a2b8',
                    'leaf' => '#28a745',
                    'church' => '#6f42c1'
                ];
                $iconBg = $iconColors[$p['icon']] ?? '#1a1a2e';
            ?>
                <div class="project-card">
                    <div class="project-card-header">
                        <div class="project-card-top">
                            <div class="project-icon-wrapper" style="background: <?php echo $iconBg; ?>">
                                <i class="fas fa-<?php echo e($p['icon'] ?? 'building'); ?>"></i>
                            </div>
                            <span class="project-category-badge">
                                <?php echo e(ucfirst($p['category'] ?? 'General')); ?>
                            </span>
                        </div>
                        <h3 class="project-title" onclick="openProjectTransactions(<?php echo (int)$p['project_id']; ?>, '<?php echo addslashes($p['title']); ?>')"><?php echo e($p['title']); ?></h3>
                        <p class="project-description">
                            <?php echo e(substr($p['short_description'] ?? $p['description'], 0, 100)); ?>
                            <?php echo strlen($p['short_description'] ?? $p['description']) > 100 ? '...' : ''; ?>
                        </p>
                    </div>
                    <div class="project-card-body">
                        <div class="project-stats-row">
                            <div class="project-stat-item">
                                <div class="project-stat-value">$<?php echo number_format($p['current_amount'], 0); ?></div>
                                <div class="project-stat-label">Raised</div>
                            </div>
                            <div class="project-stat-item">
                                <div class="project-stat-value"><?php echo number_format($p['partner_count'] ?? 0); ?></div>
                                <div class="project-stat-label">Partners</div>
                            </div>
                        </div>
                        <div class="project-progress-section">
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <div class="progress-text">
                                <span class="progress-percentage"><?php echo number_format($progress, 1); ?>%</span>
                                <span class="progress-goal">of $<?php echo number_format($p['goal_amount'], 0); ?></span>
                            </div>
                        </div>
                        <div class="project-actions">
                            <button class="btn-action btn-edit" onclick="openProjectModal(<?php echo (int)$p['project_id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-action btn-delete" onclick="deleteProject(<?php echo (int)$p['project_id']; ?>, '<?php echo addslashes($p['title']); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create/Edit Project Modal -->
<div class="modal-overlay" id="projectModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">New Project</h3>
            <button class="modal-close" onclick="closeProjectModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" id="projectForm" action="projects.php">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="formId" value="">
            
            <div class="modal-body">
                <h4 class="form-section-title">Basic Information</h4>
                
                <div class="form-group">
                    <label class="form-label">Project Name <span style="color: #dc3545;">*</span></label>
                    <input type="text" class="form-input" id="title" name="title" required placeholder="e.g. House of Prayer Expansion">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">URL Slug</label>
                        <input type="text" class="form-input" id="slug" name="slug" placeholder="house-of-prayer">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category <span style="color: #dc3545;">*</span></label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="">Select category</option>
                            <?php foreach ($projectCategories as $categoryOption): ?>
                                <?php if (empty($categoryOption['is_active'])) { continue; } ?>
                                <option value="<?php echo e($categoryOption['name']); ?>">
                                    <?php echo e($categoryOption['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Short Description</label>
                    <input type="text" class="form-input" id="short_description" name="short_description" placeholder="One sentence vision statement">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Description</label>
                    <textarea class="form-textarea" id="description" name="description" rows="4" placeholder="Tell the full story..."></textarea>
                </div>

                <h4 class="form-section-title" style="margin-top: 24px;">Financials</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Goal Amount (₦)</label>
                        <input type="number" class="form-input" id="goal_amount" name="goal_amount" step="0.01" min="0" placeholder="5000000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" class="form-input" id="display_order" name="display_order" min="0" placeholder="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-input" id="start_date" name="start_date">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-input" id="end_date" name="end_date">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="is_active" name="is_active" checked style="width: auto;">
                        Active Project
                    </label>
                </div>
                
                <h4 class="form-section-title" style="margin-top: 24px;">Appearance</h4>
                
                <div class="form-group">
                    <label class="form-label">Icon</label>
                    <div class="icon-picker">
                        <?php $icons = ['building', 'globe', 'star', 'book', 'heart', 'cross', 'people', 'leaf', 'church']; ?>
                        <?php foreach ($icons as $icon): ?>
                            <div class="icon-option">
                                <input type="radio" name="icon" id="icon_<?php echo $icon; ?>" value="<?php echo e($icon); ?>" <?php echo $icon === 'building' ? 'checked' : ''; ?>>
                                <label for="icon_<?php echo $icon; ?>">
                                    <i class="fas fa-<?php echo e($icon); ?>"></i>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <h4 class="form-section-title" style="margin-top: 24px;">Financial Milestones</h4>
                <p style="font-size: 13px; color: rgba(15, 27, 45, 0.6); margin-bottom: 16px;">
                    Add financial milestones to track project progress. When a milestone is reached, all partners and admins will be notified via email.
                </p>
                
                <div id="milestonesContainer">
                    <!-- Milestones will be added here dynamically -->
                </div>
                
                <button type="button" class="btn-new-project" onclick="addMilestone()" style="margin-top: 12px; font-size: 13px; padding: 10px 16px;">
                    <i class="fas fa-plus"></i> Add Milestone
                </button>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeProjectModal()">Cancel</button>
                <button type="submit" class="btn-save">
                    <i class="fas fa-check"></i> Save Project
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Project Categories Modal -->
<div class="modal-overlay" id="categoryModal">
    <div class="modal modal-wide">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="categoryModalTitle">Manage Categories</h3>
                <p style="color: rgba(15, 27, 45, 0.56); font-size: 13px; margin-top: 6px;">Create, edit, or retire categories without leaving the projects page.</p>
            </div>
            <button class="modal-close" onclick="closeCategoryModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="category-modal-layout">
                <div class="category-form-card">
                    <h4 class="form-section-title" id="categoryFormHeading">New Category</h4>
                    <form method="POST" id="categoryForm" action="projects.php">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="save_category">
                        <input type="hidden" name="category_id" id="category_id" value="">

                        <div class="form-group">
                            <label class="form-label" for="category_name">Category Name</label>
                            <input type="text" class="form-input" id="category_name" name="category_name" placeholder="e.g. Youth Outreach" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="category_slug">Category Slug</label>
                            <input type="text" class="form-input" id="category_slug" name="category_slug" placeholder="youth-outreach">
                            <p class="form-hint-inline">This is used for internal admin management and can auto-generate from the name.</p>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="category_display_order">Display Order</label>
                                <input type="number" class="form-input" id="category_display_order" name="category_display_order" min="0" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="display: flex; align-items: center; gap: 8px; margin-top: 30px;">
                                    <input type="checkbox" id="category_is_active" name="category_is_active" checked style="width: auto;">
                                    Active Category
                                </label>
                            </div>
                        </div>

                        <div class="modal-footer" style="padding: 8px 0 0; border-top: 0; justify-content: flex-start;">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-check"></i> Save Category
                            </button>
                            <button type="button" class="btn-cancel" onclick="resetCategoryForm()">Clear</button>
                        </div>
                    </form>
                </div>

                <div class="category-list-card">
                    <h4 class="form-section-title">Existing Categories</h4>
                    <div class="category-list">
                        <?php foreach ($projectCategories as $category): ?>
                            <div class="category-row">
                                <div>
                                    <strong><?php echo e($category['name']); ?></strong>
                                    <div class="category-row-meta">
                                        <?php echo e($category['slug']); ?> · Order <?php echo (int) ($category['display_order'] ?? 0); ?> · <?php echo !empty($category['is_active']) ? 'Active' : 'Inactive'; ?>
                                    </div>
                                </div>
                                <div class="category-row-actions">
                                    <button
                                        type="button"
                                        class="btn-category-action"
                                        data-id="<?php echo (int) $category['category_id']; ?>"
                                        data-name="<?php echo e($category['name']); ?>"
                                        data-slug="<?php echo e($category['slug']); ?>"
                                        data-order="<?php echo (int) ($category['display_order'] ?? 0); ?>"
                                        data-active="<?php echo !empty($category['is_active']) ? '1' : '0'; ?>"
                                        onclick="editCategoryFromButton(this)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-category-action btn-category-delete"
                                        onclick="deleteCategory(<?php echo (int) $category['category_id']; ?>, '<?php echo addslashes($category['name']); ?>')"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Project Transactions Modal -->
<div class="modal-overlay" id="transactionsModal">
    <div class="modal">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">Project Transactions</h3>
                <p style="color: rgba(15, 27, 45, 0.5); font-size: 13px; margin-top: 4px;" id="transactionsModalSubtitle"></p>
            </div>
            <button class="modal-close" onclick="closeTransactionsModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="transactionsLoading" style="text-align: center; padding: 40px; color: rgba(15, 27, 45, 0.5);">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i>
                <p style="margin-top: 12px;">Loading transactions...</p>
            </div>
            <div id="transactionsContent" style="display: none;"></div>
        </div>
    </div>
</div>

<?php
// Define custom scripts for this page
function layoutCustomScripts() {
    global $projects;
    ?>
    <script>
        // Project data cache
        let projectDataCache = <?php 
            $cacheData = array_map(function($p) {
                return [
                    'project_id' => (int)($p['project_id'] ?? 0),
                    'title' => $p['title'] ?? '',
                    'slug' => $p['slug'] ?? '',
                    'description' => $p['description'] ?? '',
                    'short_description' => $p['short_description'] ?? '',
                    'category' => $p['category'] ?? '',
                    'goal_amount' => floatval($p['goal_amount'] ?? 0),
                    'current_amount' => floatval($p['current_amount'] ?? 0),
                    'start_date' => $p['start_date'] ?? '',
                    'end_date' => $p['end_date'] ?? '',
                    'is_active' => !empty($p['is_active']) ? true : false,
                    'display_order' => (int)($p['display_order'] ?? 0),
                    'icon' => $p['icon'] ?? 'building',
                ];
            }, $projects);
            echo json_encode($cacheData); 
        ?>;
        
        function openProjectModal(projectId = null) {
            const modal = document.getElementById('projectModal');
            const formAction = document.getElementById('formAction');
            const formId = document.getElementById('formId');
            const modalTitle = document.getElementById('modalTitle');
            
            const titleEl = document.getElementById('title');
            const slugEl = document.getElementById('slug');
            const categoryEl = document.getElementById('category');
            const shortDescEl = document.getElementById('short_description');
            const descEl = document.getElementById('description');
            const goalAmountEl = document.getElementById('goal_amount');
            const displayOrderEl = document.getElementById('display_order');
            const startDateEl = document.getElementById('start_date');
            const endDateEl = document.getElementById('end_date');
            const isActiveEl = document.getElementById('is_active');
            
            const projectData = projectDataCache.find(p => p.project_id == projectId);
            
            if (projectId && projectData) {
                if (formAction) formAction.value = 'edit';
                if (formId) formId.value = projectId;
                if (modalTitle) modalTitle.textContent = 'Edit Project';
                
                if (titleEl) titleEl.value = projectData.title || '';
                if (slugEl) slugEl.value = projectData.slug || '';
                if (categoryEl) categoryEl.value = projectData.category || '';
                if (shortDescEl) shortDescEl.value = projectData.short_description || '';
                if (descEl) descEl.value = projectData.description || '';
                if (goalAmountEl) goalAmountEl.value = projectData.goal_amount || '';
                if (displayOrderEl) displayOrderEl.value = projectData.display_order || '';
                if (startDateEl) startDateEl.value = projectData.start_date ? projectData.start_date.split(' ')[0] : '';
                if (endDateEl) endDateEl.value = projectData.end_date ? projectData.end_date.split(' ')[0] : '';
                if (isActiveEl) isActiveEl.checked = projectData.is_active;
                
                const iconRadios = document.getElementsByName('icon');
                iconRadios.forEach(radio => {
                    if (radio.value === (projectData.icon || 'building')) {
                        radio.checked = true;
                    }
                });
            } else {
                if (formAction) formAction.value = 'create';
                if (formId) formId.value = '';
                if (modalTitle) modalTitle.textContent = 'New Project';
                if (document.getElementById('projectForm')) document.getElementById('projectForm').reset();
                
                const defaultIcon = document.querySelector('input[name="icon"][value="building"]');
                if (defaultIcon) defaultIcon.checked = true;
            }
            
            if (modal) modal.classList.add('active');
        }
        
        function closeProjectModal() {
            document.getElementById('projectModal').classList.remove('active');
        }

        function openCategoryModal() {
            resetCategoryForm();
            document.getElementById('categoryModal')?.classList.add('active');
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal')?.classList.remove('active');
        }

        function resetCategoryForm() {
            const form = document.getElementById('categoryForm');
            if (form) form.reset();
            const idEl = document.getElementById('category_id');
            const headingEl = document.getElementById('categoryFormHeading');
            if (idEl) idEl.value = '';
            if (headingEl) headingEl.textContent = 'New Category';
            const activeEl = document.getElementById('category_is_active');
            if (activeEl) activeEl.checked = true;
        }

        function editCategoryFromButton(button) {
            if (!button) return;
            openCategoryModal();

            const idEl = document.getElementById('category_id');
            const nameEl = document.getElementById('category_name');
            const slugEl = document.getElementById('category_slug');
            const orderEl = document.getElementById('category_display_order');
            const activeEl = document.getElementById('category_is_active');
            const headingEl = document.getElementById('categoryFormHeading');

            if (idEl) idEl.value = button.dataset.id || '';
            if (nameEl) nameEl.value = button.dataset.name || '';
            if (slugEl) slugEl.value = button.dataset.slug || '';
            if (orderEl) orderEl.value = button.dataset.order || '0';
            if (activeEl) activeEl.checked = (button.dataset.active || '0') === '1';
            if (headingEl) headingEl.textContent = 'Edit Category';
        }

        function deleteCategory(categoryId, categoryName) {
            if (!confirm('Delete the "' + categoryName + '" category? This will fail if it is still assigned to any projects.')) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'projects.php';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?php echo generateCSRFToken(); ?>';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete_category';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'category_id';
            idInput.value = categoryId;

            form.appendChild(csrfInput);
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
        
        // Auto-slug
        document.getElementById('title')?.addEventListener('input', function() {
            const slug = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            document.getElementById('slug').value = slug;
        });

        document.getElementById('category_name')?.addEventListener('input', function() {
            const slugEl = document.getElementById('category_slug');
            if (!slugEl) return;
            slugEl.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        });
        
        // Close modal on overlay click
        document.getElementById('projectModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeProjectModal();
        });

        document.getElementById('categoryModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeCategoryModal();
        });
        
        // Delete project function
        function deleteProject(projectId, projectName) {
            if (confirm('Are you sure you want to delete "' + projectName + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'projects.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = projectId;
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?php echo generateCSRFToken(); ?>';
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                form.appendChild(csrfInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Project transactions modal
        function openProjectTransactions(projectId, projectName) {
            const modal = document.getElementById('transactionsModal');
            const subtitleEl = document.getElementById('transactionsModalSubtitle');
            const loadingEl = document.getElementById('transactionsLoading');
            const contentEl = document.getElementById('transactionsContent');

            if (subtitleEl) subtitleEl.textContent = projectName;
            if (loadingEl) loadingEl.style.display = 'block';
            if (contentEl) {
                contentEl.style.display = 'none';
                contentEl.innerHTML = '';
            }

            if (modal) modal.classList.add('active');

            fetch('./api/get-project-transactions.php?project_id=' + encodeURIComponent(projectId))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (contentEl) {
                        contentEl.style.display = 'block';
                        if (data.error) {
                            contentEl.innerHTML = '<p style="color: #dc3545; text-align: center;">' + data.error + '</p>';
                            return;
                        }
                        if (!data.transactions || data.transactions.length === 0) {
                            contentEl.innerHTML = '<p style="color: rgba(15, 27, 45, 0.5); text-align: center; padding: 30px;">No transactions found for this project.</p>';
                            return;
                        }
                        var html = '<div style="overflow-x: auto;"><table class="txn-table">';
                        html += '<thead><tr>';
                        html += '<th>Date</th>';
                        html += '<th>User</th>';
                        html += '<th>Reference</th>';
                        html += '<th style="text-align: right;">Amount</th>';
                        html += '<th style="text-align: center;">Status</th>';
                        html += '</tr></thead><tbody>';
                        data.transactions.forEach(function(txn) {
                            var statusColor = '#28a745';
                            var statusBg = 'rgba(40, 167, 69, 0.1)';
                            if (txn.status === 'pending') { statusColor = '#b7950b'; statusBg = 'rgba(255, 193, 7, 0.1)'; }
                            else if (txn.status === 'failed') { statusColor = '#dc3545'; statusBg = 'rgba(220, 53, 69, 0.1)'; }
                            html += '<tr>';
                            html += '<td>' + (txn.transaction_date ? new Date(txn.transaction_date).toLocaleDateString() : '-') + '</td>';
                            html += '<td style="color: #0F1B2D;">' + (txn.user_name || 'Anonymous') + '</td>';
                            html += '<td style="font-size: 12px; color: rgba(15, 27, 45, 0.5); font-family: monospace;">' + (txn.transaction_reference || '-') + '</td>';
                            html += '<td class="amount-cell">' + (txn.currency || 'NGN') + ' ' + Number(txn.amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</td>';
                            html += '<td style="text-align: center;"><span class="txn-status-badge" style="background: ' + statusBg + '; color: ' + statusColor + ';">' + (txn.status || 'unknown') + '</span></td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table></div>';
                        html += '<div class="txn-summary">';
                        html += '<span>' + data.transactions.length + ' transaction(s)</span>';
                        html += '<span>Total: <strong style="color: #C9A24B;">' + (data.total_currency || 'NGN') + ' ' + Number(data.total_amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</strong></span>';
                        html += '</div>';
                        contentEl.innerHTML = html;
                    }
                })
                .catch(function(error) {
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (contentEl) {
                        contentEl.style.display = 'block';
                        contentEl.innerHTML = '<p style="color: #dc3545; text-align: center;">Error loading transactions: ' + error.message + '</p>';
                    }
                });
        }

        function closeTransactionsModal() {
            document.getElementById('transactionsModal').classList.remove('active');
        }

        document.getElementById('transactionsModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeTransactionsModal();
        });

        // Milestone management
        let milestoneCounter = 0;

        function addMilestone(data = null) {
            milestoneCounter++;
            const container = document.getElementById('milestonesContainer');
            const milestoneDiv = document.createElement('div');
            milestoneDiv.className = 'milestone-item';
            milestoneDiv.style.cssText = 'background: rgba(255,255,255,0.6); border: 1px solid rgba(15,27,45,0.08); border-radius: 16px; padding: 16px; margin-bottom: 12px; position: relative;';
            milestoneDiv.id = 'milestone-' + milestoneCounter;

            const title = data ? data.title : '';
            const description = data ? data.description : '';
            const targetAmount = data ? data.target_amount : '';

            milestoneDiv.innerHTML = `
                <button type="button" onclick="removeMilestone(${milestoneCounter})" style="position: absolute; top: 12px; right: 12px; background: none; border: none; color: #dc3545; cursor: pointer; font-size: 16px; padding: 4px;">
                    <i class="fas fa-times"></i>
                </button>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Milestone Title</label>
                    <input type="text" class="form-input" name="milestones[${milestoneCounter}][title]" value="${escapeHtml(title)}" placeholder="e.g. Foundation Complete" required>
                </div>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Description</label>
                    <textarea class="form-textarea" name="milestones[${milestoneCounter}][description]" rows="2" placeholder="Brief description of this milestone...">${escapeHtml(description)}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Target Amount (₦)</label>
                    <input type="number" class="form-input" name="milestones[${milestoneCounter}][target_amount]" value="${escapeHtml(targetAmount)}" step="0.01" min="0" placeholder="500000" required>
                </div>
            `;

            container.appendChild(milestoneDiv);
        }

        function removeMilestone(id) {
            const milestone = document.getElementById('milestone-' + id);
            if (milestone) {
                milestone.remove();
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        // Load existing milestones when editing
        function loadProjectMilestones(projectId) {
            // Fetch milestones from API
            fetch('api/get-project-milestones.php?project_id=' + encodeURIComponent(projectId))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.milestones && data.milestones.length > 0) {
                        data.milestones.forEach(function(milestone) {
                            addMilestone(milestone);
                        });
                    }
                })
                .catch(function(error) {
                    console.log('Could not load milestones:', error);
                });
        }

        // Update openProjectModal to load milestones
        const originalOpenProjectModal = openProjectModal;
        openProjectModal = function(projectId = null) {
            // Clear existing milestones
            document.getElementById('milestonesContainer').innerHTML = '';
            milestoneCounter = 0;

            // Call original function
            originalOpenProjectModal(projectId);

            // Load milestones if editing
            if (projectId) {
                loadProjectMilestones(projectId);
            }
        };
    </script>
    <?php
}

// End the layout
layoutFooter();
?>
