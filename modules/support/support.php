<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Support.php';

Auth::requireLogin();
$userId = Auth::getUserId();

// Handle ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $support = new Support();
    $result = $support->createTicket(
        $userId,
        $_POST['subject'],
        $_POST['description'],
        $_POST['category']
    );
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

$support = new Support();
$myTickets = $support->getUserTickets($userId);

// Page Config
$pageTitle = 'Support Center - PlacementCode';
$additionalCSS = '
.container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }

.header-section { margin-bottom: 30px; }
.header-section h1 { font-size: 2rem; margin-bottom: 8px; color: #e4e4e7; }
.header-section p { color: #a1a1aa; font-size: 1rem; }

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 3px solid;
    font-weight: 500;
}
.alert.success { background: rgba(34, 197, 94, 0.15); color: #22c55e; border-left-color: #22c55e; }
.alert.error { background: rgba(239, 68, 68, 0.15); color: #ef4444; border-left-color: #ef4444; }

.grid-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }

.card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-radius: 12px;
    padding: 25px;
}
.card h3 { font-size: 1.2rem; margin-bottom: 20px; color: #e4e4e7; }

.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; color: #a1a1aa; font-size: 0.9rem; font-weight: 500; }
.form-control {
    width: 100%;
    background: #0f0f0f;
    border: 1px solid #2a2a2a;
    padding: 12px;
    border-radius: 8px;
    color: #e4e4e7;
    font-size: 0.95rem;
    font-family: inherit;
    transition: border-color 0.2s;
}
.form-control:focus {
    outline: none;
    border-color: #ffa116;
}
textarea.form-control { resize: vertical; min-height: 150px; }

.btn-submit {
    width: 100%;
    background: linear-gradient(135deg, #ffa116, #ff6b6b);
    color: #fff;
    padding: 14px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: opacity 0.2s;
}
.btn-submit:hover { opacity: 0.9; }

.empty-state {
    text-align: center; 
    padding: 40px; 
    color: #71717a;
}

.ticket-item {
    padding: 15px;
    background: #0f0f0f;
    border-radius: 8px;
    margin-bottom: 12px;
    border-left: 3px solid transparent;
    transition: background 0.2s;
}
.ticket-item:hover { background: #161616; }

.ticket-item.open { border-left-color: #fbbf24; }
.ticket-item.in_progress { border-left-color: #3b82f6; }
.ticket-item.resolved { border-left-color: #22c55e; }
.ticket-item.closed { border-left-color: #71717a; }

.ticket-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
.ticket-subject { font-weight: 600; color: #e4e4e7; }
.ticket-meta { font-size: 0.85rem; color: #71717a; }

.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}
.status-open { background: rgba(251, 191, 36, 0.15); color: #fbbf24; }
.status-in_progress { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.status-resolved { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
.status-closed { background: rgba(113, 113, 122, 0.15); color: #71717a; }

.admin-response {
    margin-top: 10px; 
    padding: 12px; 
    background: #1a1a1a; 
    border-radius: 6px; 
    font-size: 0.9rem;
    border: 1px solid #2a2a2a;
}

@media (max-width: 1024px) {
    .grid-layout { grid-template-columns: 1fr; }
}
';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<div class="container">
    
    <?php if (isset($message)): ?>
        <div class="alert <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="header-section">
        <h1>🎫 Support Center</h1>
        <p>Need help? Submit a ticket and our team will assist you</p>
    </div>

    <div class="grid-layout">
        
        <!-- Submit New Ticket -->
        <div class="card">
            <h3>Create New Ticket</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control" required>
                        <option value="general">General Inquiry</option>
                        <option value="technical">Technical Issue</option>
                        <option value="account">Account Problem</option>
                        <option value="feedback">Feedback/Suggestion</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" class="form-control" placeholder="Brief description of your issue" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" placeholder="Please provide detailed information about your issue..." required></textarea>
                </div>
                
                <button type="submit" name="submit_ticket" class="btn-submit">Submit Ticket</button>
            </form>
        </div>

        <!-- My Tickets -->
        <div class="card">
            <h3>My Tickets</h3>
            <?php if (empty($myTickets)): ?>
                <div class="empty-state">
                    <p>No tickets yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($myTickets as $ticket): ?>
                    <div class="ticket-item <?php echo $ticket['status']; ?>">
                        <div class="ticket-header">
                            <span class="ticket-subject"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                            <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                <?php echo str_replace('_', ' ', $ticket['status']); ?>
                            </span>
                        </div>
                        <div class="ticket-meta">
                            <?php echo ucfirst($ticket['category']); ?> • 
                            <?php echo date('M j, Y', strtotime($ticket['created_at'])); ?>
                        </div>
                        <?php if ($ticket['admin_response']): ?>
                            <div class="admin-response">
                                <strong style="color: #ffa116;">Admin Response:</strong><br>
                                <span style="color: #e4e4e7;"><?php echo htmlspecialchars($ticket['admin_response']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
