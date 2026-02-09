<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Support.php';

Auth::requireLogin();

// Check if user is admin
$userId = Auth::getUserId();
$db = Database::getInstance();
$userRole = $db->fetchOne("SELECT role_id FROM users WHERE user_id = :uid", ['uid' => $userId])['role_id'];

if ($userRole != 2) {
    header('Location: ../../modules/dashboard/index.php');
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $support = new Support();
    $result = $support->updateTicketStatus(
        $_POST['ticket_id'],
        $_POST['status'],
        $_POST['admin_response'] ?? null
    );
    $message = $result['message'];
}

// Handle delete
if (isset($_POST['delete_ticket'])) {
    $support = new Support();
    $result = $support->deleteTicket($_POST['ticket_id']);
    $message = $result['message'];
}

$support = new Support();
$statusFilter = $_GET['status'] ?? 'all';
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$tickets = $support->getAllTickets($limit, $offset, $statusFilter);
$stats = [
    'total' => $support->getTicketCount('all'),
    'open' => $support->getTicketCount('open'),
    'in_progress' => $support->getTicketCount('in_progress'),
    'resolved' => $support->getTicketCount('resolved')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #0a0a0a;
            color: #e4e4e7;
            min-height: 100vh;
        }
        
        .top-nav {
            background: #1a1a1a;
            border-bottom: 1px solid #2a2a2a;
            padding: 12px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo { font-size: 1.3rem; font-weight: 700; color: #ef4444; }
        .nav-menu { display: flex; gap: 25px; }
        .nav-menu a { color: #a1a1aa; text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: color 0.2s; }
        .nav-menu a:hover, .nav-menu a.active { color: #fff; }
        
        .container { max-width: 1600px; margin: 0 auto; padding: 30px 20px; }
        
        .header-section { margin-bottom: 30px; }
        .header-section h1 { font-size: 2rem; margin-bottom: 8px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .stat-number { font-size: 2rem; font-weight: 700; color: #ef4444; margin-bottom: 8px; }
        .stat-label { color: #a1a1aa; font-size: 0.85rem; }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .filter-tab {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            padding: 10px 20px;
            border-radius: 8px;
            color: #a1a1aa;
            text-decoration: none;
            transition: all 0.2s;
        }
        .filter-tab:hover { background: #2a2a2a; }
        .filter-tab.active { background: #ef4444; color: #fff; border-color: #ef4444; }
        
        .tickets-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            overflow: hidden;
        }
        
        table { width: 100%; border-collapse: collapse; }
        thead { background: #0f0f0f; }
        th {
            text-align: left;
            padding: 16px 20px;
            font-size: 0.85rem;
            color: #71717a;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        tbody tr { border-bottom: 1px solid #2a2a2a; transition: background 0.15s; }
        tbody tr:hover { background: #1f1f1f; }
        td { padding: 16px 20px; font-size: 0.95rem; }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-open { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
        .status-in_progress { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .status-resolved { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .status-closed { background: rgba(113, 113, 122, 0.15); color: #71717a; }
        
        .action-btns { display: flex; gap: 10px; }
        .btn-reply, .btn-delete {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
        }
        .btn-reply { background: #2a2a2a; color: #e4e4e7; }
        .btn-delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
        }
        .modal-content h3 { margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; color: #a1a1aa; }
        .form-group select, .form-group textarea {
            width: 100%;
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            padding: 12px;
            border-radius: 8px;
            color: #e4e4e7;
            font-family: inherit;
        }
        .btn-submit { background: #ef4444; color: #fff; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-cancel { background: #2a2a2a; color: #e4e4e7; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; margin-left: 10px; }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="logo">⚙️ Admin Panel</div>
        <div class="nav-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="users.php">Users</a>
            <a href="problems.php">Problems</a>
            <a href="tests.php">Tests</a>
            <a href="tickets.php" class="active">Support</a>
        </div>
        <div style="color: #71717a; font-size: 0.9rem;">
            <a href="../dashboard/index.php" style="color: #ef4444; text-decoration: none;">Exit Admin →</a>
        </div>
    </nav>

    <div class="container">
        
        <?php if (isset($message)): ?>
            <div style="background: rgba(34, 197, 94, 0.15); color: #22c55e; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="header-section">
            <h1>🎫 Support Tickets</h1>
            <p style="color: #a1a1aa;">Manage user support requests</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Tickets</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['open']; ?></div>
                <div class="stat-label">Open</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['resolved']; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?status=open" class="filter-tab <?php echo $statusFilter === 'open' ? 'active' : ''; ?>">Open</a>
            <a href="?status=in_progress" class="filter-tab <?php echo $statusFilter === 'in_progress' ? 'active' : ''; ?>">In Progress</a>
            <a href="?status=resolved" class="filter-tab <?php echo $statusFilter === 'resolved' ? 'active' : ''; ?>">Resolved</a>
        </div>

        <!-- Tickets Table -->
        <div class="tickets-card">
            <?php if (empty($tickets)): ?>
                <div style="text-align: center; padding: 80px 20px; color: #71717a;">
                    <h3>No tickets found</h3>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td style="color: #71717a;">#<?php echo $ticket['ticket_id']; ?></td>
                            <td>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($ticket['full_name'] ?? 'N/A'); ?></div>
                                <small style="color: #71717a;"><?php echo htmlspecialchars($ticket['email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                            <td style="color: #a1a1aa; text-transform: capitalize;"><?php echo $ticket['category']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                    <?php echo str_replace('_', ' ', $ticket['status']); ?>
                                </span>
                            </td>
                            <td style="color: #a1a1aa; font-size: 0.85rem;">
                                <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-reply" onclick="openReplyModal(<?php echo htmlspecialchars(json_encode($ticket)); ?>)">Reply</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this ticket?');">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">
                                        <button type="submit" name="delete_ticket" class="btn-delete">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reply Modal -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <h3>Reply to Ticket</h3>
            <form method="POST" id="replyForm">
                <input type="hidden" name="ticket_id" id="modal_ticket_id">
                
                <div class="form-group">
                    <label>Ticket Subject</label>
                    <input type="text" id="modal_subject" disabled style="opacity: 0.6;">
                </div>
                
                <div class="form-group">
                    <label>User Message</label>
                    <textarea id="modal_description" disabled style="opacity: 0.6; min-height: 100px;"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Admin Response</label>
                    <textarea name="admin_response" placeholder="Your response to the user..." style="min-height: 120px;"></textarea>
                </div>
                
                <div>
                    <button type="submit" name="update_ticket" class="btn-submit">Update Ticket</button>
                    <button type="button" class="btn-cancel" onclick="closeReplyModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReplyModal(ticket) {
            document.getElementById('modal_ticket_id').value = ticket.ticket_id;
            document.getElementById('modal_subject').value = ticket.subject;
            document.getElementById('modal_description').value = ticket.description;
            document.getElementById('replyModal').classList.add('active');
        }
        
        function closeReplyModal() {
            document.getElementById('replyModal').classList.remove('active');
        }
    </script>

</body>
</html>
