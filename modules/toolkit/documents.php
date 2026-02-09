<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Toolkit.php';

Auth::requireLogin();
$userId = Auth::getUserId();

$toolkit = new Toolkit();

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $result = $toolkit->uploadDocument(
        $userId,
        $_FILES['document'],
        $_POST['category'],
        $_POST['description'] ?? ''
    );
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// Handle delete
if (isset($_POST['delete_doc'])) {
    $result = $toolkit->deleteDocument($_POST['document_id'], $userId);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

$category = $_GET['category'] ?? null;
$documents = $toolkit->getUserDocuments($userId, $category);
$stats = $toolkit->getDocumentStats($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Locker - PlacementCode</title>
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
        .logo { font-size: 1.3rem; font-weight: 700; color: #ffa116; }
        .nav-menu { display: flex; gap: 25px; }
        .nav-menu a { color: #a1a1aa; text-decoration: none; font-size: 0.95rem; font-weight: 500; transition: color 0.2s; }
        .nav-menu a:hover, .nav-menu a.active { color: #fff; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }
        
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .btn-upload {
            background: linear-gradient(135deg, #ffa116, #ff6b6b);
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
        .stat-number { font-size: 2rem; font-weight: 700; color: #ffa116; margin-bottom: 8px; }
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
        .filter-tab.active { background: #ffa116; color: #000; border-color: #ffa116; }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .document-card {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 20px;
            transition: transform 0.2s;
        }
        .document-card:hover { transform: translateY(-4px); }
        
        .doc-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            text-align: center;
        }
        .doc-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: #e4e4e7;
            word-break: break-word;
        }
        .doc-meta {
            font-size: 0.85rem;
            color: #71717a;
            margin-bottom: 15px;
        }
        
        .doc-actions {
            display: flex;
            gap: 10px;
        }
        .btn-download, .btn-delete {
            flex: 1;
            padding: 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            border: none;
            font-weight: 500;
            text-align: center;
        }
        .btn-download { background: #2a2a2a; color: #e4e4e7; text-decoration: none; }
        .btn-delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        
        .upload-modal {
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
        .upload-modal.active { display: flex; }
        .modal-content {
            background: #1a1a1a;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
        }
        .modal-content h3 { margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; color: #a1a1aa; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            background: #0f0f0f;
            border: 1px solid #2a2a2a;
            padding: 12px;
            border-radius: 8px;
            color: #e4e4e7;
            font-family: inherit;
        }
        .btn-submit { background: #ffa116; color: #000; padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-cancel { background: #2a2a2a; color: #e4e4e7; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; margin-left: 10px; }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert.success { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .alert.error { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="logo">⚡ PlacementCode</div>
        <div class="nav-menu">
            <a href="../dashboard/index.php">Dashboard</a>
            <a href="resume-builder.php">Resume Builder</a>
            <a href="documents.php" class="active">My Documents</a>
        </div>
    </nav>

    <div class="container">
        
        <?php if (isset($message)): ?>
            <div class="alert <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="header-section">
            <div>
                <h1>🔒 Document Locker</h1>
                <p style="color: #a1a1aa;">Securely store your important documents</p>
            </div>
            <button class="btn-upload" onclick="openUploadModal()">📤 Upload Document</button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total Documents</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['certificates'] ?? 0; ?></div>
                <div class="stat-label">Certificates</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['resumes'] ?? 0; ?></div>
                <div class="stat-label">Resumes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo round(($stats['total_size'] ?? 0) / 1024 / 1024, 2); ?> MB</div>
                <div class="stat-label">Total Storage</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-tabs">
            <a href="?" class="filter-tab <?php echo is_null($category) ? 'active' : ''; ?>">All</a>
            <a href="?category=resume" class="filter-tab <?php echo $category === 'resume' ? 'active' : ''; ?>">Resumes</a>
            <a href="?category=certificate" class="filter-tab <?php echo $category === 'certificate' ? 'active' : ''; ?>">Certificates</a>
            <a href="?category=other" class="filter-tab <?php echo $category === 'other' ? 'active' : ''; ?>">Others</a>
        </div>

        <!-- Documents Grid -->
        <?php if (empty($documents)): ?>
            <div style="text-align: center; padding: 80px 20px; color: #71717a;">
                <h3>No documents yet</h3>
                <p>Upload your first document to get started</p>
            </div>
        <?php else: ?>
            <div class="documents-grid">
                <?php foreach ($documents as $doc): ?>
                    <div class="document-card">
                        <div class="doc-icon">
                            <?php
                            $ext = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
                            echo $ext === 'pdf' ? '📄' : ($ext === 'doc' || $ext === 'docx' ? '📃' : '🖼️');
                            ?>
                        </div>
                        <div class="doc-name"><?php echo htmlspecialchars($doc['file_name']); ?></div>
                        <div class="doc-meta">
                            <?php echo ucfirst($doc['category']); ?> • 
                            <?php echo round($doc['file_size'] / 1024, 2); ?> KB<br>
                            <?php echo date('M j, Y', strtotime($doc['uploaded_at'])); ?>
                        </div>
                        <?php if ($doc['description']): ?>
                            <p style="font-size: 0.85rem; color: #a1a1aa; margin-bottom: 15px;">
                                <?php echo htmlspecialchars($doc['description']); ?>
                            </p>
                        <?php endif; ?>
                        <div class="doc-actions">
                            <a href="../../uploads/documents/<?php echo $doc['file_path']; ?>" download class="btn-download">Download</a>
                            <form method="POST" style="flex: 1;" onsubmit="return confirm('Delete this document?');">
                                <input type="hidden" name="document_id" value="<?php echo $doc['document_id']; ?>">
                                <button type="submit" name="delete_doc" class="btn-delete">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="upload-modal">
        <div class="modal-content">
            <h3>Upload Document</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <option value="resume">Resume/CV</option>
                        <option value="certificate">Certificate</option>
                        <option value="other">Other Document</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>File * (PDF, DOC, DOCX, JPG, PNG)</label>
                    <input type="file" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Optional description..."></textarea>
                </div>
                
                <div>
                    <button type="submit" class="btn-submit">Upload</button>
                    <button type="button" class="btn-cancel" onclick="closeUploadModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUploadModal() {
            document.getElementById('uploadModal').classList.add('active');
        }
        
        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('active');
        }
    </script>

</body>
</html>
