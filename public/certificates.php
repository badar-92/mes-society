<?php
$page_title = "Certificate Search";
require_once '../includes/header.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

$db = Database::getInstance()->getConnection();
$functions = new Functions();

// Search parameters
$search = $_GET['search'] ?? '';
$event_id = $_GET['event'] ?? '';
$competition_id = $_GET['competition'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$sql = "SELECT c.*, e.title as event_title, comp.title as competition_title 
        FROM certificates c
        LEFT JOIN events e ON c.event_id = e.id
        LEFT JOIN competitions comp ON c.competition_id = comp.id
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (c.name LIKE ? OR c.sap_id LIKE ? OR c.serial_number LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}
if (!empty($event_id)) {
    $sql .= " AND c.event_id = ?";
    $params[] = $event_id;
}
if (!empty($competition_id)) {
    $sql .= " AND c.competition_id = ?";
    $params[] = $competition_id;
}

// Count total for pagination
$countSql = "SELECT COUNT(*) FROM certificates c WHERE 1=1";
$countParams = $params;
if (!empty($search)) {
    $countSql .= " AND (c.name LIKE ? OR c.sap_id LIKE ? OR c.serial_number LIKE ?)";
}
if (!empty($event_id)) {
    $countSql .= " AND c.event_id = ?";
}
if (!empty($competition_id)) {
    $countSql .= " AND c.competition_id = ?";
}
$stmt = $db->prepare($countSql);
$stmt->execute($countParams);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Main query with LIMIT and OFFSET
$sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);

// Bind parameters explicitly
$paramIndex = 1;
foreach ($params as $param) {
    $stmt->bindValue($paramIndex++, $param, PDO::PARAM_STR);
}
// Bind LIMIT and OFFSET as integers
$stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);

$stmt->execute();
$certificates = $stmt->fetchAll();

// Fetch events and competitions for filters
$events = $db->query("SELECT id, title FROM events WHERE status = 'published' ORDER BY start_date DESC")->fetchAll();
$competitions = $db->query("SELECT id, title FROM competitions WHERE status = 'published' ORDER BY start_date DESC")->fetchAll();
?>

<div class="container py-5">
    <h1 class="text-center mb-5">Certificate Search</h1>

    <!-- Search Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search by name, SAP ID, or serial number" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="event">
                        <option value="">All Events</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event['id']; ?>" <?php echo $event_id == $event['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($event['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="competition">
                        <option value="">All Competitions</option>
                        <?php foreach ($competitions as $comp): ?>
                            <option value="<?php echo $comp['id']; ?>" <?php echo $competition_id == $comp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($comp['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-accent w-100">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <?php if (empty($certificates)): ?>
        <div class="alert alert-info text-center">No certificates found.</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($certificates as $cert): ?>
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-img-top text-center p-3" style="height: 180px; overflow: hidden;">
                            <?php if ($cert['thumbnail_path']): ?>
                                <img src="<?php echo SITE_URL . '/' . $cert['thumbnail_path']; ?>" alt="Certificate Thumbnail" class="img-fluid" style="max-height: 100%;">
                            <?php else: ?>
                                <i class="fas fa-file-pdf fa-4x text-danger"></i>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($cert['name']); ?></h5>
                            <p class="card-text">
                                <small class="text-muted">Serial: <?php echo htmlspecialchars($cert['serial_number']); ?></small><br>
                                <?php if ($cert['sap_id']): ?>
                                    <small>SAP ID: <?php echo htmlspecialchars($cert['sap_id']); ?></small><br>
                                <?php endif; ?>
                                <?php if ($cert['event_title']): ?>
                                    <span class="badge bg-info">Event: <?php echo htmlspecialchars($cert['event_title']); ?></span>
                                <?php elseif ($cert['competition_title']): ?>
                                    <span class="badge bg-warning">Competition: <?php echo htmlspecialchars($cert['competition_title']); ?></span>
                                <?php endif; ?>
                            </p>
                            <a href="<?php echo SITE_URL . '/' . $cert['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary w-100">Download</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>