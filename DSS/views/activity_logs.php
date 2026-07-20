<?php

require_once '../includes/header.php';

$logType = $_GET['type'] ?? 'activity';
$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(description LIKE :search OR user_role LIKE :search OR entity_type LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($actionFilter !== '') {
    $where[] = 'action_type = :action';
    $params[':action'] = $actionFilter;
}

$whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM activity_logs ' . $whereClause
);
$countStmt->execute($params);
$totalLogs = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($totalLogs / $perPage);

$logsStmt = $pdo->prepare(
    'SELECT log_id, user_id, user_role, action_type, entity_type, entity_id,
            description, ip_address, created_at
     FROM activity_logs
     ' . $whereClause . '
     ORDER BY created_at DESC, log_id DESC
     LIMIT :limit OFFSET :offset'
);

foreach ($params as $key => $value) {
    $logsStmt->bindValue($key, $value);
}
$logsStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$logsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$logsStmt->execute();
$logs = $logsStmt->fetchAll();

$actionTypesStmt = $pdo->query(
    'SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type ASC'
);
$actionTypes = $actionTypesStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<?php if ($sessionRole !== 'Admin'): ?>
    <div class="bento-card bento-span-full">
        <div class="bento-card-header" style="color: var(--critical);"><?= __('access_restricted') ?></div>
        <p class="bento-subtext"><?= __('no_permission_desc') ?></p>
    </div>
<?php else: ?>
<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">history</i> <?= htmlspecialchars(__('activity_feed'), ENT_QUOTES, 'UTF-8') ?></div>
    <p class="bento-subtext"><?= htmlspecialchars(__('activity_feed'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="action-button-row" style="margin-top: 1rem;">
            <a href="/dss/controllers/action_export_csv.php?type=activity" class="btn blue"><i class="material-icons left">file_download</i><?= __('export_activity_csv') ?></a>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">filter_list</i> <?= __('filter') ?></div>
        <form method="get" action="activity_logs.php" style="margin-top: 1rem;">
                <div class="row">
                    <div class="input-field col s12 m6">
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= __('search_logs') ?>">
                        <label for="search"><?= __('search') ?></label>
                    </div>
                    <div class="input-field col s12 m6">
                        <select id="action" name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actionTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" <?= $actionFilter === $type ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="action">Action</label>
                    </div>
                    <div class="col s12">
                        <button type="submit" class="btn blue"><?= __('search') ?></button>
                        <a href="activity_logs.php" class="btn grey" style="margin-left: 0.5rem;">Clear</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="bento-card bento-span-full">
        <div class="bento-card-header"><i class="material-icons">receipt_long</i> <?= __('activity_records') ?></div>
        <div class="bento-grid">
            <div class="bento-card" style="text-align: center;">
                <div class="bento-card-header">Total Records</div>
                <div class="bento-metric"><?= number_format($totalLogs) ?></div>
                <p class="bento-subtext"><?= __('activity_log_entries') ?></p>
            </div>
        </div>

            <?php if ($logs === []): ?>
                <div class="center-align" style="padding: 3rem 1rem;">
                    <i class="material-icons large grey-text text-lighten-2">history</i>
                    <h5 class="grey-text"><?= __('no_activity_logs') ?></h5>
                    <p class="grey-text"><?= __('no_activity_recorded') ?></p>
                </div>
            <?php else: ?>
                <div class="responsive-table-shell" style="margin-top: 1.5rem;">
                <table class="striped highlight responsive-table">
                    <thead>
                        <tr>
                            <th><?= __('date_time') ?></th>
                            <th><?= __('user') ?></th>
                            <th><?= __('role') ?></th>
                            <th><?= __('action') ?></th>
                            <th><?= __('entity') ?></th>
                            <th><?= __('description') ?></th>
                            <th><?= __('ip_address') ?></th>
                            <th><?= __('user_agent') ?></th>
                            <th><?= __('old_values') ?></th>
                            <th><?= __('new_values') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= $log['user_id'] !== null ? (int) $log['user_id'] : 'public' ?></td>
                                <td><?= htmlspecialchars($log['user_role'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($log['action_type']) ?>">
                                        <?= htmlspecialchars($log['action_type'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['entity_type'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                    <?= $log['entity_id'] !== null ? '#' . (int) $log['entity_id'] : '' ?></td>
                                <td><?= htmlspecialchars($log['description'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($log['user_agent'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($log['old_values'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($log['new_values'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="center-align" style="margin-top: 2rem;">
                        <?php if ($page > 1): ?>
                            <a href="?type=activity&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&action=<?= urlencode($actionFilter) ?>" class="btn blue"><?= __('previous') ?></a>
                        <?php endif; ?>
                        <span style="margin: 0 1rem; color: var(--text-secondary);">Page <?= $page ?> of <?= $totalPages ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="?type=activity&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&action=<?= urlencode($actionFilter) ?>" class="btn blue"><?= __('next') ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>