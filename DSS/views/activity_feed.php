<?php

require_once '../includes/header.php';

$activityType = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[] = '(description LIKE :search OR user_role LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($activityType !== '') {
    $where[] = 'action_type = :action_type';
    $params[':action_type'] = $activityType;
}

$whereClause = implode(' AND ', $where);

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM activity_logs WHERE ' . $whereClause);
$countStmt->execute($params);
$totalLogs = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($totalLogs / $perPage);

$logsStmt = $pdo->prepare(
    'SELECT log_id, user_id, user_role, action_type, entity_type, entity_id,
            description, ip_address, created_at
     FROM activity_logs
     WHERE ' . $whereClause . '
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

$actionTypesStmt = $pdo->query('SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type ASC');
$actionTypes = $actionTypesStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">history</i> <?= htmlspecialchars(__('activity_feed'), ENT_QUOTES, 'UTF-8') ?></div>
    <p class="bento-subtext"><?= htmlspecialchars(__('activity_feed'), ENT_QUOTES, 'UTF-8') ?></p>
</div>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">filter_list</i> Filter Activity</div>
    <form method="get" action="/dss/views/activity_feed.php" style="margin-top: 1rem;">
        <div class="row">
            <div class="input-field col s12 m6">
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search activity...">
                <label for="search">Search</label>
            </div>
            <div class="input-field col s12 m6">
                <select id="type" name="type">
                    <option value="">All Activity Types</option>
                    <?php foreach ($actionTypes as $type): ?>
                        <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" <?= $activityType === $type ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="type">Activity Type</label>
            </div>
            <div class="col s12" style="margin-top: 0.5rem;">
                <button type="submit" class="btn blue"><i class="material-icons left">search</i>Filter</button>
                <?php if ($search !== '' || $activityType !== ''): ?>
                    <a href="/dss/views/activity_feed.php" class="btn grey" style="margin-left: 0.5rem;"><i class="material-icons left">clear</i>Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="bento-card bento-span-full">
    <div class="bento-card-header"><i class="material-icons">list_alt</i> Recent Activity</div>
    <?php if ($logs === []): ?>
        <div class="center-align" style="padding: 3rem 1rem;">
            <i class="material-icons large grey-text text-lighten-2">inbox</i>
            <h5 class="grey-text">No Activity Found</h5>
            <p class="grey-text">There is no activity matching your filters.</p>
        </div>
    <?php else: ?>
        <div class="responsive-table-shell" style="margin-top: 1.5rem;">
            <table class="striped highlight responsive-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>Entity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?= htmlspecialchars(date('M j, g:i a', strtotime($log['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($log['user_role'] ?? 'System', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $log['action_type'])) ?>"><?= htmlspecialchars($log['action_type'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($log['description'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($log['entity_type'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="center-align" style="margin-top: 1.5rem;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $activityType !== '' ? '&type=' . urlencode($activityType) : '' ?>" class="btn <?= $page === $i ? 'blue' : 'grey' ?>" style="margin: 0 0.25rem;"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
