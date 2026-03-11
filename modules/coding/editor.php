<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Compiler.php';
require_once __DIR__ . '/../../classes/FocusMode.php';

Auth::requireLogin();

$problemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($problemId <= 0) {
    header('Location: problems.php');
    exit;
}

$compiler = new Compiler();
$problem = $compiler->getProblem($problemId);

if (!$problem) {
    header('Location: problems.php');
    exit;
}

$supportedLanguages = array_values(array_filter(
    $compiler->getSupportedLanguages(),
    static function ($language) {
        $code = strtolower(trim((string)($language['language_code'] ?? '')));
        return in_array($code, ['python', 'cpp', 'c', 'java', 'javascript'], true);
    }
));

if (empty($supportedLanguages)) {
    $supportedLanguages = [
        ['language_id' => 71, 'language_name' => 'Python', 'language_code' => 'python'],
        ['language_id' => 54, 'language_name' => 'C++', 'language_code' => 'cpp'],
        ['language_id' => 50, 'language_name' => 'C', 'language_code' => 'c'],
        ['language_id' => 62, 'language_name' => 'Java', 'language_code' => 'java'],
        ['language_id' => 63, 'language_name' => 'JavaScript', 'language_code' => 'javascript']
    ];
}

$defaultLanguageId = (string)($supportedLanguages[0]['language_id'] ?? '71');
foreach ($supportedLanguages as $language) {
    if (strtolower((string)($language['language_code'] ?? '')) === 'python') {
        $defaultLanguageId = (string)$language['language_id'];
        break;
    }
}

$languageCatalog = [];
foreach ($supportedLanguages as $language) {
    $languageCatalog[(string)$language['language_id']] = [
        'language_id' => (string)$language['language_id'],
        'language_name' => (string)$language['language_name'],
        'language_code' => strtolower((string)$language['language_code'])
    ];
}

$problemTags = array_values(array_filter(array_map(
    'trim',
    explode(',', (string)($problem['tags'] ?? ''))
)));

// Initialize Focus Mode Session
$focusMode = new FocusMode();
$userId = Auth::getUserId();
$focusSession = $focusMode->startSession($userId, 'coding', $problemId);
$sessionId = (!empty($focusSession['success']) && isset($focusSession['session_id']))
    ? (int)$focusSession['session_id']
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($problem['title']); ?> - Code Editor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #1e1e1e; color: #d4d4d4; }
        
        .header {
            background: #2d2d30;
            padding: 10px 20px;
            border-bottom: 1px solid #3e3e42;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 60px;
            height: auto;
            flex-wrap: wrap;
            gap: 10px;
        }
        .header h2 { color: #fff; font-size: 1.1rem; display: flex; align-items: center; gap: 15px; white-space: nowrap; }
        
        .timer-badge {
            background: #333;
            padding: 5px 12px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #ffa116;
            font-weight: bold;
            border: 1px solid #444;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .header { padding: 10px; }
            .header h2 { font-size: 1rem; }
            .violation-counter { padding: 4px 8px; font-size: 0.8rem; margin-right: 10px; }
            .header-buttons button span { display: none; } /* Hide text, show icon only if added */
        }

        .header-buttons button {
            padding: 8px 16px;
            margin-left: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-run { background: #2ea043; color: white; }
        .btn-submit { background: #667eea; color: white; }
        .btn-submit:disabled { background: #555; cursor: not-allowed; opacity: 0.7; }
        .btn-back { background: #444; color: #ccc; }
        .btn-download { background: #007acc; color: white; }
        
        .btn-run:hover, .btn-submit:hover, .btn-download:hover { filter: brightness(1.1); }
        
        /* Layout */
        .layout {
            display: flex;
            height: calc(100vh - 60px);
            position: relative;
            overflow: hidden;
        }
        
        .problem-panel {
            width: 40%;
            background: #252526;
            padding: 20px;
            overflow-y: auto;
            min-width: 300px;
        }
        
        .gutter {
            width: 8px;
            background: #1e1e1e;
            border-left: 1px solid #333;
            border-right: 1px solid #333;
            cursor: col-resize;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .gutter:hover, .gutter.dragging { background: #667eea; border-color: #667eea; }
        .gutter::after {
            content: '⋮';
            color: #555;
            font-size: 14px;
        }

        .editor-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 400px;
        }
        
        /* Difficulty Badges */
        .difficulty-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .difficulty-badge.easy { background: rgba(46, 160, 67, 0.15); color: #2ea043; border: 1px solid rgba(46, 160, 67, 0.4); }
        .difficulty-badge.medium { background: rgba(251, 133, 0, 0.15); color: #fb8500; border: 1px solid rgba(251, 133, 0, 0.4); }
        .difficulty-badge.hard { background: rgba(218, 54, 51, 0.15); color: #da3633; border: 1px solid rgba(218, 54, 51, 0.4); }
        .problem-meta { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 18px; }
        .problem-meta span {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            background: #1d1d20;
            border: 1px solid #33363d;
            border-radius: 999px;
            font-size: 0.82rem;
            color: #c7c9d1;
        }
        
        /* Content Styling */
        .problem-panel h3 { color: #e4e4e7; margin: 20px 0 10px; font-size: 1.1rem; border-bottom: 1px solid #333; padding-bottom: 5px; }
        .problem-panel p, .problem-panel li { line-height: 1.6; color: #d4d4d4; font-size: 0.95rem; margin-bottom: 10px; }
        .problem-panel pre { background: #1e1e1e; padding: 10px; border-radius: 6px; overflow-x: auto; border: 1px solid #333; }
        
        .sample-case { background: #1e1e1e; padding: 15px; border-radius: 6px; margin: 15px 0; border: 1px solid #333; }
        .sample-case strong { color: #888; display: block; margin-bottom: 5px; font-size: 0.85rem; text-transform: uppercase; }
        
        /* Editor Controls */
        .editor-controls {
            background: #252526;
            padding: 8px 15px;
            border-bottom: 1px solid #333;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .control-group { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
        .editor-controls select, .editor-controls input {
            background: #3c3c3c; border: 1px solid #444; color: #ddd; padding: 4px 8px; border-radius: 4px; outline: none;
        }
        .editor-status {
            margin-left: auto;
            font-size: 0.82rem;
            color: #a1a1aa;
        }
        .editor-status strong { color: #f4f4f5; }
        
        .btn-control {
            background: transparent; border: 1px solid transparent; color: #ccc; padding: 4px 8px; border-radius: 4px; cursor: pointer; transition: all 0.2s; font-size: 0.9rem;
        }
        .btn-control:hover { background: #333; color: white; }
        
        .CodeMirror { flex: 1; font-family: 'Fira Code', 'Consolas', monospace; line-height: 1.5; }
        
        /* Output Panel */
        .output-panel {
            height: 200px; /* Initial height */
            background: #1e1e1e;
            border-top: 1px solid #333;
            display: flex;
            flex-direction: column;
            transition: height 0.3s;
        }
        .output-panel.collapsed { height: 35px; }
        
        .output-header {
            padding: 8px 15px;
            background: #252526;
            border-bottom: 1px solid #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }
        .output-header h4 { font-size: 0.9rem; color: #ccc; display: flex; align-items: center; gap: 8px; }
        .toggle-icon { transition: transform 0.3s; }
        .collapsed .toggle-icon { transform: rotate(-90deg); }
        
        .output-content {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #ccc;
            white-space: pre-wrap;
        }
        .output-success { color: #81c784; }
        .output-error { color: #e57373; }

        /* Custom Input */
        .custom-input { display: none; padding: 10px; background: #252526; border-bottom: 1px solid #333; }
        .custom-input.active { display: block; }
        .custom-input textarea { width: 100%; background: #1e1e1e; color: #ddd; border: 1px solid #333; padding: 10px; font-family: monospace; height: 80px; }

        /* Overlays (Keep existing styles mostly) */
        .fullscreen-entry-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; display: flex; justify-content: center; align-items: center; }
        .fullscreen-entry-overlay.hidden { display: none; }
        .fullscreen-entry-content { background: #2d2d30; padding: 40px; border-radius: 12px; text-align: center; max-width: 600px; border: 1px solid #444; }
        .btn-enter-fullscreen { padding: 12px 30px; background: #667eea; color: white; border: none; border-radius: 6px; font-size: 1.1rem; cursor: pointer; margin-top: 20px; font-weight: bold; }
        
        .header-buttons { display: flex; align-items: center; gap: 10px; }
        .execution-group { display: flex; gap: 5px; background: #333; padding: 4px; border-radius: 6px; border: 1px solid #444; }
        
        .header-buttons button {
            padding: 8px 14px;
            margin-left: 0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
        }
        
        .btn-run { background: #2ea043; color: white; border-radius: 4px; }
        .btn-verify { background: #8e44ad; color: white; border-radius: 4px; }
        .btn-submit { background: #667eea; color: white; border-radius: 4px; }
        .btn-back { background: #444; color: #ccc; }
        .btn-download { background: #333; color: #ccc; padding: 8px 12px; }
        
        /* Violation Counter (Integrated) */
        .violation-counter { 
            background: rgba(220, 38, 38, 0.15); 
            color: #ef5350; 
            padding: 6px 14px; 
            border-radius: 20px; 
            font-weight: bold; 
            margin-right: 20px;
            border: 1px solid rgba(220, 38, 38, 0.3);
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            animation: fadeIn 0.5s;
        }
        
        .focus-warning { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10000; background: rgba(30, 30, 30, 0.95); padding: 30px; border-radius: 12px; text-align: center; color: white; display: none; border: 1px solid #dc2626; box-shadow: 0 0 50px rgba(220, 38, 38, 0.2); }
        .focus-warning.show { display: block; animation: shake 0.5s; }
        .custom-confirm-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.78);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10010;
            backdrop-filter: blur(2px);
        }
        .custom-confirm-backdrop.show { display: flex; }
        .custom-confirm-dialog {
            width: min(92vw, 520px);
            background: #1f1f22;
            border: 1px solid #3a3a3f;
            border-radius: 12px;
            padding: 22px;
            color: #e4e4e7;
            box-shadow: 0 10px 36px rgba(0, 0, 0, 0.45);
        }
        .custom-confirm-title {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #fff;
        }
        .custom-confirm-message {
            color: #c8c8ce;
            line-height: 1.5;
            margin-bottom: 18px;
            white-space: pre-wrap;
        }
        .custom-confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-confirm-cancel, .btn-confirm-ok {
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 8px 14px;
            font-weight: 600;
            cursor: pointer;
            color: #fff;
            transition: transform 0.15s, filter 0.15s;
        }
        .btn-confirm-cancel { background: #3a3a3f; border-color: #4a4a50; }
        .btn-confirm-ok { background: #667eea; border-color: #7389ec; }
        .btn-confirm-cancel:hover, .btn-confirm-ok:hover { filter: brightness(1.07); }
        .btn-confirm-cancel:active, .btn-confirm-ok:active { transform: translateY(1px); }
        .fullscreen-notice { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #667eea; padding: 8px 20px; border-radius: 20px; font-size: 0.9rem; color: white; z-index: 10000; }
        
        @keyframes shake { 0%, 100% { transform: translate(-50%, -50%); } 25% { transform: translate(-52%, -50%); } 75% { transform: translate(-48%, -50%); } }
        @media (max-width: 760px) {
            .header { align-items: flex-start; }
            .header h2 { white-space: normal; }
            .layout { flex-direction: column; height: auto; }
            .problem-panel { width: 100%; min-width: 0; }
            .gutter { display: none; }
            .editor-panel { min-width: 0; min-height: 70vh; }
            .editor-status { width: 100%; margin-left: 0; }
        }
    </style>
</head>
<body>
    <!-- Fullscreen Entry Overlay (Must click to proceed) -->
    <div class="fullscreen-entry-overlay" id="fullscreenEntryOverlay">
        <div class="fullscreen-entry-content">
            <h2>🔒 Secure Coding Environment</h2>
            <p><strong>Before you begin, please read the rules carefully:</strong></p>
            <ul>
                <li>✅ This is a secure coding session</li>
                <li>🖥️ You must work in <strong>FULLSCREEN MODE</strong></li>
                <li>⚠️ <strong>Maximum 3 violations allowed</strong></li>
                <li>🚫 Right-click is disabled</li>
                <li>🔒 Developer tools are blocked</li>
                <li>👁️ Tab switching/window blur will be tracked</li>
                <li>⚡ 3 violations = <strong>AUTO-SUBMIT</strong></li>
            </ul>
            <p style="color: #dc2626; font-weight: bold;">Click below to enter fullscreen and start coding:</p>
            <button class="btn-enter-fullscreen" onclick="requestFullscreenEntry()">
                🚀 Enter Fullscreen & Start Coding
            </button>
        </div>
    </div>
    
    <!-- Focus Mode Warning Overlay -->
    <div class="focus-warning" id="focusWarning">
        <h2>⚠️ VIOLATION DETECTED!</h2>
        <p id="warningMessage">Focus violation detected!</p>
        <p style="font-size: 0.9rem; opacity: 0.9;">Remaining chances: <span id="remainingChances">3</span></p>
    </div>
    
    <div class="custom-confirm-backdrop" id="customConfirmBackdrop" aria-hidden="true">
        <div class="custom-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="customConfirmTitle">
            <div class="custom-confirm-title" id="customConfirmTitle">Confirm Action</div>
            <div class="custom-confirm-message" id="customConfirmMessage">Are you sure you want to continue?</div>
            <div class="custom-confirm-actions">
                <button type="button" class="btn-confirm-cancel" id="customConfirmCancel">Cancel</button>
                <button type="button" class="btn-confirm-ok" id="customConfirmOk">Continue</button>
            </div>
        </div>
    </div>

    <!-- Fullscreen Notice -->
    <div class="fullscreen-notice" id="fullscreenNotice">
        🔒 Secure Mode Active - Press ESC to exit fullscreen (will count as violation)
    </div>
    
    <div class="header">
        <h2>
            <?php echo htmlspecialchars($problem['title']); ?>
            <span class="timer-badge" id="codingTimer">00:00:00</span>
        </h2>
        
        <div style="display:flex; align-items:center;">
            <!-- Violation Counter (Integrated) -->
            <div class="violation-counter" id="violationCounter">
                ⚠️ Violations: <span id="violationCount">0</span>/3
            </div>

            <div class="header-buttons">
                <button class="btn-download" onclick="saveCode()" title="Download Code">💾</button>
                <div class="execution-group">
                    <button class="btn-run" onclick="runCode()">▶ Run</button>
                    <!-- <button class="btn-verify" onclick="verifyWithAI()" title="Test with random AI input">🤖 AI Check</button> -->
                    <button class="btn-verify" onclick="verifyWithAI()" title="Test with random AI input">✨ AI Verify</button>
                    <button class="btn-submit" id="btnSubmit" onclick="submitCode()" disabled title="Run code successfully to enable submission">✓ Submit</button>
                </div>
                <button class="btn-back" onclick="location.href='problems.php'">Exit</button>
            </div>
        </div>
    </div>
    
    <div class="layout" id="mainLayout">
        <div class="problem-panel" id="problemPanel">
            <span class="difficulty-badge <?php echo strtolower($problem['difficulty']); ?>">
                <?php echo $problem['difficulty']; ?>
            </span>
            <div class="problem-meta">
                <span>Problem #<?php echo (int)$problem['problem_id']; ?></span>
                <span>Time Limit: <?php echo (int)($problem['time_limit_ms'] ?? 2000); ?> ms</span>
                <span>Memory Limit: <?php echo (int)($problem['memory_limit_mb'] ?? 256); ?> MB</span>
                <?php foreach ($problemTags as $tag): ?>
                    <span>#<?php echo htmlspecialchars($tag); ?></span>
                <?php endforeach; ?>
            </div>
            
            <h3>Problem Description</h3>
            <p><?php echo nl2br(htmlspecialchars($problem['description'])); ?></p>
            
            <?php if (!empty($problem['input_format'])): ?>
                <h3>Input Format</h3>
                <p><?php echo nl2br(htmlspecialchars($problem['input_format'])); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($problem['output_format'])): ?>
                <h3>Output Format</h3>
                <p><?php echo nl2br(htmlspecialchars($problem['output_format'])); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($problem['sample_cases'])): ?>
                <h3>Sample Test Cases</h3>
                <?php foreach ($problem['sample_cases'] as $index => $testCase): ?>
                    <div class="sample-case">
                        <strong>Sample Input <?php echo $index + 1; ?>:</strong>
                        <pre><?php echo htmlspecialchars($testCase['input_data']); ?></pre>
                        <strong>Sample Output <?php echo $index + 1; ?>:</strong>
                        <pre><?php echo htmlspecialchars($testCase['expected_output']); ?></pre>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($problem['constraints'])): ?>
                <h3>Constraints</h3>
                <p><?php echo nl2br(htmlspecialchars($problem['constraints'])); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="gutter" id="resizeGutter"></div>
        
        <div class="editor-panel">
            <div class="editor-controls">
                <div class="control-group">
                    <label>Language:</label>
                    <select id="languageSelect" onchange="changeLanguage()">
                        <?php foreach ($supportedLanguages as $language): ?>
                            <option value="<?php echo htmlspecialchars((string)$language['language_id']); ?>" <?php echo (string)$language['language_id'] === $defaultLanguageId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$language['language_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="control-group">
                    <label>Theme:</label>
                    <select id="themeSelect" onchange="changeTheme()">
                        <option value="monokai">Monokai</option>
                        <option value="dracula">Dracula</option>
                        <option value="material">Material</option>
                        <option value="eclipse">Eclipse</option>
                        <option value="nord">Nord</option>
                    </select>
                </div>
                
                <div class="control-group">
                    <label>Font:</label>
                    <input type="number" id="fontSize" value="14" min="10" max="24" style="width: 50px;" onchange="changeFontSize()">
                </div>
                
                <button class="btn-control" onclick="toggleCustomInput()">📝 Input</button>
                <button class="btn-control" onclick="resetCode()">🔄 Reset</button>
                <button class="btn-control" onclick="showShortcuts()">⌨️ Keys</button>
            </div>
            
            <div class="custom-input" id="customInputPanel">
                <label style="color: #d4d4d4; padding: 10px; display: block;">Custom Test Input:</label>
                <textarea id="customInput" rows="4" placeholder="Enter your test input here..."></textarea>
            </div>
            
            <textarea id="codeEditor"># Write your code here
def solution():
    pass

solution()</textarea>
            
            <div class="output-panel" id="outputPanel">
                <div class="output-header" onclick="toggleOutput()">
                    <h4><span class="toggle-icon">▼</span> Console Output</h4>
                    <span style="font-size:0.8rem; color:#888;">Click to toggle</span>
                </div>
                <div class="output-content" id="output">Run your code to see output...</div>
            </div>
        </div>
    </div>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/dracula.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/material.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/eclipse.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/nord.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/closebrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/matchbrackets.min.js"></script>
    
    <script>
        const languageCatalog = <?php echo json_encode($languageCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const defaultLanguageId = <?php echo json_encode($defaultLanguageId); ?>;

        const languagePresets = {
            python: {
                mode: 'python',
                extension: 'py',
                template: `# Python Solution
class Solution:
    def solve(self, input_data):
        # input_data is a string or parsed input
        # Your code here
        
        return "result"`
            },
            cpp: {
                mode: 'text/x-c++src',
                extension: 'cpp',
                template: `// C++ Solution
#include <iostream>
#include <string>
#include <vector>
#include <algorithm>
using namespace std;

class Solution {
public:
    // Change return type and parameters as needed
    string solve(string input) {
        // Your code here
        
        return "result";
    }
};`
            },
            c: {
                mode: 'text/x-csrc',
                extension: 'c',
                template: `// C Solution
#include <stdio.h>
#include <stdlib.h>
#include <string.h>

// Function to solve problem
// Change return type and parameters as needed
char* solve(char* input) {
    // Your code here
    
    return "result";
}`
            },
            java: {
                mode: 'text/x-java',
                extension: 'java',
                template: `// Java Solution
import java.util.*;

class Solution {
    public String solve(String input) {
        // Your code here
        
        return "result";
    }
}`
            },
            javascript: {
                mode: 'javascript',
                extension: 'js',
                template: `// JavaScript Solution
class Solution {
    solve(input) {
        // Your code here
        
        return "result";
    }
}`
            }
        };

        function getLanguageMeta(languageId) {
            const catalogEntry = languageCatalog[String(languageId)] || languageCatalog[String(defaultLanguageId)] || {
                language_id: String(defaultLanguageId),
                language_name: 'Python',
                language_code: 'python'
            };
            const preset = languagePresets[catalogEntry.language_code] || languagePresets.python;

            return {
                ...catalogEntry,
                mode: preset.mode,
                extension: preset.extension,
                template: preset.template
            };
        }
        
        const editor = CodeMirror.fromTextArea(document.getElementById('codeEditor'), {
            mode: 'python',
            theme: 'monokai',
            lineNumbers: true,
            indentUnit: 4,
            autoCloseBrackets: true,
            matchBrackets: true,
            extraKeys: {
                "Ctrl-Space": "autocomplete",
                "Ctrl-/": "toggleComment",
                "F11": function(cm) { cm.setOption("fullScreen", !cm.getOption("fullScreen")); },
                "Esc": function(cm) { if (cm.getOption("fullScreen")) cm.setOption("fullScreen", false); }
            }
        });

        // Shared confirm helper used by reset/language switch/submit flows.
        const customConfirmBackdrop = document.getElementById('customConfirmBackdrop');
        const customConfirmMessage = document.getElementById('customConfirmMessage');
        const customConfirmOk = document.getElementById('customConfirmOk');
        const customConfirmCancel = document.getElementById('customConfirmCancel');
        let confirmOnOk = null;
        let confirmOnCancel = null;

        function runIfFunction(cb) {
            if (typeof cb === 'function') {
                try {
                    cb();
                } catch (err) {
                    console.error('Confirm callback failed:', err);
                }
            }
        }

        function closeCustomConfirm(action) {
            if (!customConfirmBackdrop) {
                return;
            }

            customConfirmBackdrop.classList.remove('show');
            customConfirmBackdrop.setAttribute('aria-hidden', 'true');

            const okCb = confirmOnOk;
            const cancelCb = confirmOnCancel;
            confirmOnOk = null;
            confirmOnCancel = null;

            if (action === 'ok') {
                runIfFunction(okCb);
            } else {
                runIfFunction(cancelCb);
            }
        }

        function showCustomConfirm(message, onConfirm, onCancel = null, options = {}) {
            const safeMessage = message || 'Are you sure?';

            if (!customConfirmBackdrop || !customConfirmMessage || !customConfirmOk || !customConfirmCancel) {
                if (window.confirm(safeMessage)) {
                    runIfFunction(onConfirm);
                } else {
                    runIfFunction(onCancel);
                }
                return;
            }

            confirmOnOk = onConfirm;
            confirmOnCancel = onCancel;
            customConfirmMessage.textContent = safeMessage;
            customConfirmOk.textContent = options.confirmText || 'Continue';
            customConfirmCancel.textContent = options.cancelText || 'Cancel';
            customConfirmBackdrop.classList.add('show');
            customConfirmBackdrop.setAttribute('aria-hidden', 'false');
            customConfirmOk.focus();
        }
        window.showCustomConfirm = showCustomConfirm;

        if (customConfirmOk && customConfirmCancel && customConfirmBackdrop) {
            customConfirmOk.addEventListener('click', () => closeCustomConfirm('ok'));
            customConfirmCancel.addEventListener('click', () => closeCustomConfirm('cancel'));
            customConfirmBackdrop.addEventListener('click', (event) => {
                if (event.target === customConfirmBackdrop) {
                    closeCustomConfirm('cancel');
                }
            });
            document.addEventListener('keydown', (event) => {
                if (!customConfirmBackdrop.classList.contains('show')) {
                    return;
                }
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeCustomConfirm('cancel');
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    closeCustomConfirm('ok');
                }
            });
        }
        
        // ==============================================
        // SECURE FOCUS MODE SYSTEM
        // ==============================================
        
        const FOCUS_SESSION_ID = <?php echo $sessionId; ?>;
        const PROBLEM_ID = <?php echo $problemId; ?>;
        const DRAFT_STORAGE_KEY = `coding_draft_problem_${PROBLEM_ID}`;
        const FALLBACK_REDIRECT_URL = 'problems.php';
        const MAX_VIOLATIONS = 3;
        let violationCount = 0;
        let isFullscreen = false;
        let focusModeActive = false;

        function ensureDraftStatusNode() {
            const controls = document.querySelector('.editor-controls');
            if (!controls) {
                return null;
            }

            let node = document.getElementById('draftStatus');
            if (!node) {
                node = document.createElement('div');
                node.id = 'draftStatus';
                node.className = 'editor-status';
                controls.appendChild(node);
            }

            return node;
        }

        function normalizeEditorUiText() {
            const fullscreenTitle = document.querySelector('.fullscreen-entry-content h2');
            if (fullscreenTitle) {
                fullscreenTitle.textContent = 'Secure Coding Environment';
            }

            const fullscreenItems = document.querySelectorAll('.fullscreen-entry-content li');
            const normalizedRules = [
                'This is a secure coding session',
                'You must work in FULLSCREEN MODE',
                'Maximum 3 violations allowed',
                'Right-click is disabled',
                'Developer tools are blocked',
                'Tab switching and window blur will be tracked',
                '3 violations triggers auto-submit'
            ];
            fullscreenItems.forEach((item, index) => {
                if (normalizedRules[index]) {
                    item.textContent = normalizedRules[index];
                }
            });

            const fullscreenButton = document.querySelector('.btn-enter-fullscreen');
            if (fullscreenButton) {
                fullscreenButton.textContent = 'Enter Fullscreen and Start Coding';
            }

            const focusWarningTitle = document.querySelector('#focusWarning h2');
            if (focusWarningTitle) {
                focusWarningTitle.textContent = 'Violation Detected';
            }

            const fullscreenNotice = document.getElementById('fullscreenNotice');
            if (fullscreenNotice) {
                fullscreenNotice.textContent = 'Secure mode active. Press ESC to exit fullscreen. That counts as a violation.';
            }

            const violationCounter = document.getElementById('violationCounter');
            if (violationCounter) {
                violationCounter.innerHTML = 'Violations: <span id="violationCount">' + violationCount + '</span>/' + MAX_VIOLATIONS;
            }

            const headerButtons = document.querySelectorAll('.header-buttons button');
            if (headerButtons[0]) {
                headerButtons[0].textContent = 'Save';
            }
            if (headerButtons[1]) {
                headerButtons[1].textContent = 'Run';
            }
            if (headerButtons[2]) {
                headerButtons[2].textContent = 'AI Verify';
            }
            if (headerButtons[3]) {
                headerButtons[3].textContent = 'Submit';
            }

            const controlButtons = document.querySelectorAll('.editor-controls .btn-control');
            if (controlButtons[0]) {
                controlButtons[0].textContent = 'Input';
            }
            if (controlButtons[1]) {
                controlButtons[1].textContent = 'Reset';
            }
            if (controlButtons[2]) {
                controlButtons[2].textContent = 'Keys';
            }

            const toggleIcon = document.querySelector('.toggle-icon');
            if (toggleIcon) {
                toggleIcon.innerHTML = '&#9660;';
            }

            const draftStatus = ensureDraftStatusNode();
            if (draftStatus && draftStatus.textContent.trim() === '') {
                draftStatus.innerHTML = '<strong>Draft:</strong> Stored locally for this problem.';
            }
        }

        normalizeEditorUiText();

        function getRedirectTarget(serverRedirect = '') {
            if (typeof serverRedirect === 'string' && serverRedirect.trim() !== '') {
                return serverRedirect.trim();
            }
            const ref = document.referrer || '';
            if (ref) {
                try {
                    const refUrl = new URL(ref);
                    const currentUrl = new URL(window.location.href);
                    if (refUrl.origin === currentUrl.origin && refUrl.pathname !== currentUrl.pathname) {
                        return refUrl.pathname + refUrl.search + refUrl.hash;
                    }
                } catch (e) {
                    // Ignore invalid referrer and use fallback.
                }
            }
            return FALLBACK_REDIRECT_URL;
        }

        function redirectAfterSubmit(serverRedirect = '') {
            window.location.href = getRedirectTarget(serverRedirect);
        }
        
        // Focus Mode Violation Handler
        function logViolation(type, message) {
            violationCount++;
            updateViolationUI(message);
            
            // Send to server when a valid session exists
            if (FOCUS_SESSION_ID > 0) {
                fetch('../../api/focus-track.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: FOCUS_SESSION_ID,
                        violation_type: type,
                        duration: 0
                    })
                }).catch(() => {
                    // Keep UI responsive even if tracking call fails.
                });
            }
            
            // Auto-submit if max violations reached
            if (violationCount >= MAX_VIOLATIONS) {
                autoSubmitDueToViolations();
            }
        }
        
        function updateViolationUI(message) {
            const counter = document.getElementById('violationCount');
            const warning = document.getElementById('focusWarning');
            const warningMsg = document.getElementById('warningMessage');
            const remaining = document.getElementById('remainingChances');
            
            counter.textContent = violationCount;
            warningMsg.textContent = message;
            remaining.textContent = MAX_VIOLATIONS - violationCount;
            
            // Show warning overlay
            warning.classList.add('show');
            setTimeout(() => {
                warning.classList.remove('show');
            }, 3000);
            
            // Pulse effect on counter
            counter.parentElement.style.animation = 'shake 0.5s';
            setTimeout(() => {
                counter.parentElement.style.animation = '';
            }, 500);
        }
        
        async function autoSubmitDueToViolations() {
            focusModeActive = false;
            const output = document.getElementById('output');
            output.className = 'output-content output-error';
            output.textContent = '❌ AUTO-SUBMITTING: Maximum violations (3) reached!\n\nPlease wait...';
            editor.setOption('readOnly', true);
            
            alert('⚠️ MAXIMUM VIOLATIONS REACHED!\n\nYour solution will be automatically submitted.\n\nRedirecting to problems list...');
            
            try {
                const submitResult = await forceSubmitCode();
                const redirectTarget = getRedirectTarget(submitResult?.redirect_to || '');
                output.textContent += `\n\nSubmission complete.\nRedirecting in 2 seconds to: ${redirectTarget}`;
                setTimeout(() => { redirectAfterSubmit(submitResult?.redirect_to || ''); }, 2000);
            } catch (error) {
                output.className = 'output-content output-error';
                output.textContent = '❌ AUTO-SUBMIT FAILED\n\nError: ' + error.message;
                editor.setOption('readOnly', false);
                focusModeActive = true;
            }
        }
        
        // ==============================================
        // FULLSCREEN ENFORCEMENT
        // ==============================================
        
        function enterFullscreen() {
            const elem = document.documentElement;
            const promise = elem.requestFullscreen ? elem.requestFullscreen() :
                           elem.webkitRequestFullscreen ? elem.webkitRequestFullscreen() :
                           elem.msRequestFullscreen ? elem.msRequestFullscreen() : null;
            
            if (promise) {
                promise.then(() => {
                    console.log('Entered fullscreen successfully');
                    isFullscreen = true;
                }).catch(err => {
                    console.error('Fullscreen request failed:', err);
                    // If fullscreen fails, show error but don't count as violation on first try
                    if (focusModeActive && violationCount > 0) {
                        alert('⚠️ Fullscreen is required for this secure coding environment!');
                    }
                });
            }
        }
        
        // Function called by the entry button
        window.requestFullscreenEntry = function() {
            enterFullscreen();
            
            // Wait a bit for fullscreen to activate, then hide overlay
            setTimeout(() => {
                const overlay = document.getElementById('fullscreenEntryOverlay');
                if (document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement) {
                    overlay.classList.add('hidden');
                    focusModeActive = true;
                } else {
                    alert('⚠️ Please allow fullscreen mode to continue.\n\nClick "Enter Fullscreen" and accept the browser prompt.');
                }
            }, 500);
        };
        
        function checkFullscreen() {
            isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
            
            if (!isFullscreen && focusModeActive) {
                logViolation('fullscreen_exit', '⚠️ You exited fullscreen mode!');
                
                // Show re-entry prompt
                if (violationCount < MAX_VIOLATIONS) {
                    setTimeout(() => {
                        if (!isFullscreen && focusModeActive) {
                            const retry = confirm('⚠️ FULLSCREEN REQUIRED\n\nYou must return to fullscreen mode.\n\nClick OK to re-enter fullscreen (or Cancel to continue at your own risk - will count as additional violations)');
                            if (retry) {
                                enterFullscreen();
                            }
                        }
                    }, 500);
                }
            }
        }
        
        // Monitor fullscreen changes
        document.addEventListener('fullscreenchange', checkFullscreen);
        document.addEventListener('webkitfullscreenchange', checkFullscreen);
        document.addEventListener('msfullscreenchange', checkFullscreen);
        
        // ==============================================
        // DISABLE CONTEXT MENU (Right Click)
        // ==============================================
        
        document.addEventListener('contextmenu', (e) => {
            if (focusModeActive) {
                e.preventDefault();
                logViolation('right_click', '⚠️ Right-click is disabled in secure mode!');
                return false;
            }
        });
        
        // ==============================================
        // BLOCK DANGEROUS KEYBOARD SHORTCUTS
        // ==============================================
        
        const blockedShortcuts = [
            { ctrl: true, key: 'c' },      // Copy (allowed in editor, blocked elsewhere)
            { ctrl: true, key: 'x' },      // Cut
            { ctrl: true, key: 'v' },      // Paste (allowed in editor, blocked elsewhere)
            { ctrl: true, key: 'a' },      // Select All (allowed in editor)
            { ctrl: true, shift: true, key: 'i' },  // DevTools
            { ctrl: true, shift: true, key: 'j' },  // DevTools
            { ctrl: true, shift: true, key: 'c' },  // DevTools
            { key: 'F12' },                 // DevTools
            { ctrl: true, key: 'u' },       // View Source
            { ctrl: true, key: 'p' },       // Print
            { ctrl: true, key: 'w' },       // Close Tab
            { alt: true, key: 'F4' },       // Close Window
            { ctrl: true, key: 't' },       // New Tab
            { ctrl: true, key: 'n' },       // New Window
        ];
        
        document.addEventListener('keydown', (e) => {
            if (!focusModeActive) return;
            
            // Check if in editor - allow some shortcuts
            const isInEditor = e.target.closest('.CodeMirror');
            
            // Block DevTools
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key.toUpperCase()))) {
                e.preventDefault();
                logViolation('devtools_attempt', '⚠️ Developer tools are blocked!');
                return false;
            }
            
            // Block view source
            if (e.ctrlKey && e.key.toLowerCase() === 'u') {
                e.preventDefault();
                logViolation('view_source', '⚠️ View source is blocked!');
                return false;
            }
            
            // Block print
            if (e.ctrlKey && e.key.toLowerCase() === 'p') {
                e.preventDefault();
                logViolation('print_attempt', '⚠️ Printing is blocked!');
                return false;
            }
            
            // Block tab/window management
            if ((e.ctrlKey && ['w', 't', 'n'].includes(e.key.toLowerCase())) ||
                (e.altKey && e.key === 'F4')) {
                e.preventDefault();
                logViolation('tab_management', '⚠️ Tab/Window management is blocked!');
                return false;
            }
        });
        
        // ==============================================
        // TRACK FOCUS LOSS (Tab Switch / Minimize)
        // ==============================================
        
        let focusLostTime = null;
        
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && focusModeActive) {
                focusLostTime = Date.now();
                logViolation('tab_switch', '⚠️ You switched tabs or minimized the window!');
            } else if (!document.hidden && focusLostTime) {
                const duration = Math.floor((Date.now() - focusLostTime) / 1000);
                console.log(`Focus regained after ${duration} seconds`);
                focusLostTime = null;
            }
        });
        
        window.addEventListener('blur', () => {
            if (focusModeActive && !document.hidden) {
                logViolation('window_blur', '⚠️ You switched to another window!');
            }
        });
        
        // ==============================================
        // PREVENT COPY/PASTE FROM OUTSIDE
        // ==============================================
        
        document.addEventListener('paste', (e) => {
            // Only allow paste in the editor
            if (!e.target.closest('.CodeMirror') && focusModeActive) {
                e.preventDefault();
                logViolation('paste_attempt', '⚠️ Paste from external sources is restricted!');
            }
        });
        
        // ==============================================
        // INITIALIZE SECURE MODE
        // ==============================================
        
        // Note: Fullscreen is now user-initiated via the overlay button
        // Focus mode will activate after user clicks "Enter Fullscreen"
        
        // Hide the fullscreen notice after user enters
        document.addEventListener('fullscreenchange', () => {
            if (document.fullscreenElement) {
                setTimeout(() => {
                    const notice = document.getElementById('fullscreenNotice');
                    if (notice) notice.style.display = 'none';
                }, 5000);
            }
        });
        
        // ==============================================
        // END SECURE FOCUS MODE
        // ==============================================
        
        let currentLanguage = document.getElementById('languageSelect').value || String(defaultLanguageId);

        function applyLanguage(lang, replaceWithTemplate) {
            const nextLanguage = String(lang);
            const languageMeta = getLanguageMeta(nextLanguage);
            document.getElementById('languageSelect').value = nextLanguage;
            editor.setOption('mode', languageMeta.mode);
            if (replaceWithTemplate) {
                editor.setValue(languageMeta.template || '');
            }
            currentLanguage = nextLanguage;
            saveDraft();
        }

        function changeLanguage() {
            const languageSelect = document.getElementById('languageSelect');
            const nextLang = languageSelect.value;
            const previousLang = currentLanguage;

            if (nextLang === previousLang) {
                return;
            }

            const currentCode = editor.getValue().trim();
            const previousTemplate = (getLanguageMeta(previousLang).template || '').trim();
            const hasCustomCode = currentCode !== '' && currentCode !== previousTemplate;

            if (hasCustomCode) {
                languageSelect.value = previousLang;
                showCustomConfirm('Switching language will replace current code. Continue?', () => {
                    applyLanguage(nextLang, true);
                    setSubmitState(false, '');
                });
                return;
            }

            applyLanguage(nextLang, true);
            setSubmitState(false, '');
        }

        function getCurrentLang() {
            return String(currentLanguage);
        }

        function changeTheme() {
            const theme = document.getElementById('themeSelect').value;
            editor.setOption('theme', theme);
        }
        
        function changeFontSize() {
            const size = document.getElementById('fontSize').value;
            document.querySelector('.CodeMirror').style.fontSize = size + 'px';
            editor.refresh();
        }
        
        function toggleCustomInput() {
            const panel = document.getElementById('customInputPanel');
            panel.classList.toggle('active');
        }
        
        function resetCode() {
            showCustomConfirm('Reset to default template? This will discard your changes.', () => {
                const lang = getCurrentLang();
                editor.setValue(getLanguageMeta(lang).template);
            });
        }
        
        function showShortcuts() {
            alert(`Keyboard Shortcuts:

Ctrl + '        Run Code
Ctrl + Enter    Quick Submit
Ctrl + R        Reset Code
Ctrl + S        Download Code
F9              Toggle Custom Input
Ctrl + /        Toggle Comment
Ctrl + F        Find
Ctrl + H        Replace
F11             Fullscreen
Tab             Indent
Shift + Tab     Unindent`);
        }

        // Keyboard shortcuts (allowed in focus mode)
        document.addEventListener('keydown', (e) => {
            // Run Code: Ctrl + '
            if (e.ctrlKey && e.key === "'" && !e.shiftKey) {
                e.preventDefault();
                runCode();
            } 
            // Quick Submit: Ctrl + Enter
            else if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                if (violationCount < MAX_VIOLATIONS) {
                    submitCode(true); // Skip confirmation
                }
            } 
            // Toggle Custom Input: F9
            else if (e.key === 'F9') {
                e.preventDefault();
                toggleCustomInput();
            }
        });
        
        function saveCode() {
            const code = editor.getValue();
            const lang = getCurrentLang();
            const blob = new Blob([code], {type: 'text/plain'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `solution.${getLanguageMeta(lang).extension}`;
            a.click();
            URL.revokeObjectURL(url);
        }
        
        function setSubmitState(enabled, modeLabel) {
            const btn = document.getElementById('btnSubmit');
            if (!btn) {
                return;
            }

            btn.disabled = !enabled;
            if (enabled) {
                btn.innerHTML = modeLabel ? `Submit (${modeLabel})` : 'Submit';
                btn.title = 'Ready to submit.';
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            } else {
                btn.innerHTML = 'Submit';
                btn.title = 'Run successfully to enable submission';
                btn.style.opacity = '0.7';
                btn.style.cursor = 'not-allowed';
            }
        }

        async function runCode() {
            const code = editor.getValue();
            const langId = document.getElementById('languageSelect').value;
            const output = document.getElementById('output');
            const customInput = document.getElementById('customInput').value;

            output.textContent = 'Running your code...';
            output.className = 'output-content';
            document.getElementById('outputPanel').classList.remove('collapsed');
            setSubmitState(false, '');

            try {
                const response = await fetch('../../api/compile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        source_code: code,
                        language_id: langId,
                        problem_id: PROBLEM_ID,
                        custom_input: customInput,
                        action: 'run'
                    })
                });

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('Server returned invalid response: ' + text.substring(0, 120));
                }

                const status = String(result.status || '').toLowerCase();
                const hasErrors = Boolean(result.stderr || result.compile_output || status === 'error');

                let outputText = `Status: ${result.status || 'Unknown'}\n`;
                outputText += `Time: ${result.time ?? 0}s\n`;
                outputText += `Memory: ${result.memory ?? 0}KB\n\n`;

                if (result.stdout) {
                    outputText += `Output:\n${result.stdout}`;
                } else {
                    outputText += 'Output:\n(No output)';
                }

                if (result.stderr) {
                    outputText += `\n\nErrors:\n${result.stderr}`;
                }
                if (result.compile_output) {
                    outputText += `\n\nCompile Output:\n${result.compile_output}`;
                }

                output.className = hasErrors ? 'output-content output-error' : 'output-content output-success';
                output.textContent = outputText;

                if (result.success && !hasErrors) {
                    setSubmitState(true, 'Ready');
                } else {
                    setSubmitState(false, '');
                }
            } catch (error) {
                output.className = 'output-content output-error';
                output.textContent = 'System error: ' + error.message;
                setSubmitState(false, '');
                console.error('Execution Error:', error);
            }
        }

        async function verifyWithAI() {
            const code = editor.getValue();
            const langId = document.getElementById('languageSelect').value;
            const output = document.getElementById('output');

            output.textContent = 'AI is analyzing your code...';
            output.className = 'output-content';
            document.getElementById('outputPanel').classList.remove('collapsed');
            setSubmitState(false, '');

            try {
                const response = await fetch('../../api/compile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        source_code: code,
                        language_id: langId,
                        problem_id: PROBLEM_ID,
                        action: 'ai_verify'
                    })
                });

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid server response: ' + text.substring(0, 120));
                }

                if (result.success) {
                    const status = String(result.status || '').toLowerCase();
                    if (status === 'accepted') {
                        output.className = 'output-content output-success';
                        let msg = 'AI verification passed.\n\n';
                        msg += `Generated Input:\n${result.input || '(none)'}\n\n`;
                        msg += `Expected Output:\n${result.expected || '(none)'}\n\n`;
                        msg += `Your Output:\n${result.user_output || '(none)'}\n\n`;
                        msg += `Analysis:\n${result.explanation || 'No explanation.'}`;
                        output.textContent = msg;
                        setSubmitState(true, 'Verified');
                    } else {
                        output.className = 'output-content output-error';
                        let msg = 'AI verification failed.\n\n';
                        msg += `Generated Input:\n${result.input || '(none)'}\n\n`;
                        msg += `Expected Output:\n${result.expected || '(none)'}\n\n`;
                        msg += `Your Output:\n${result.user_output || '(none)'}\n\n`;
                        msg += `Analysis:\n${result.explanation || 'No explanation.'}`;
                        output.textContent = msg;
                        setSubmitState(false, '');
                    }
                } else {
                    output.className = 'output-content output-error';
                    output.textContent = 'AI error: ' + (result.message || 'Verification failed');
                    setSubmitState(false, '');
                }
            } catch (error) {
                output.className = 'output-content output-error';
                output.textContent = 'System error: ' + error.message;
                setSubmitState(false, '');
            }
        }

        // Force submit without confirmation (used for auto-submit after violations)
        async function forceSubmitCode() {
            const code = editor.getValue();
            const langId = document.getElementById('languageSelect').value;
            const output = document.getElementById('output');

            output.textContent = 'Submitting your solution (forced due to violations)...';
            output.className = 'output-content';
            setSubmitState(false, '');

            const response = await fetch('../../api/compile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    source_code: code,
                    language_id: langId,
                    problem_id: PROBLEM_ID,
                    action: 'submit',
                    redirect_to: getRedirectTarget()
                })
            });

            const text = await response.text();
            let result;

            try {
                result = JSON.parse(text);
            } catch (e) {
                throw new Error('Server returned invalid response: ' + text.substring(0, 120));
            }

            if (result.success) {
                if (String(result.status).toLowerCase() === 'accepted') {
                    output.className = 'output-content output-success';
                    let successMsg = `ACCEPTED (Auto-Submitted)\n\n`;
                    successMsg += `Test cases: ${result.passed}/${result.total}\n`;
                    successMsg += `Time: ${result.time}\n`;
                    successMsg += `Memory: ${result.memory}\n`;
                    successMsg += `Time Complexity: ${result.time_complexity}\n`;
                    successMsg += `Space Complexity: ${result.space_complexity}\n`;
                    output.textContent = successMsg;
                } else {
                    output.className = 'output-content output-error';
                    let errorMsg = `${String(result.status || 'error').toUpperCase()} (Auto-Submitted)\n\n`;
                    errorMsg += `Test Cases: ${result.passed}/${result.total} passed\n`;
                    output.textContent = errorMsg;
                }
            } else {
                throw new Error(result.message || 'Submission failed');
            }

            return result;
        }

        async function submitCode(skipConfirm = false) {
            if (editor.getOption('readOnly')) {
                alert('Code has already been auto-submitted due to violations. You cannot submit again.');
                return;
            }

            if (!skipConfirm) {
                showCustomConfirm('Submit your solution? This will run against all test cases.', () => {
                    executeSubmission();
                });
                return;
            }

            executeSubmission();
        }

        async function executeSubmission() {
            const code = editor.getValue();
            const langId = document.getElementById('languageSelect').value;
            const output = document.getElementById('output');

            output.textContent = 'Submitting your solution...';
            output.className = 'output-content';
            setSubmitState(false, '');
            document.getElementById('outputPanel').classList.remove('collapsed');

            try {
                const response = await fetch('../../api/compile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        source_code: code,
                        language_id: langId,
                        problem_id: PROBLEM_ID,
                        action: 'submit',
                        redirect_to: getRedirectTarget()
                    })
                });

                const text = await response.text();
                let result;

                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('Server returned invalid response: ' + text.substring(0, 120));
                }

                if (result.success) {
                    if (String(result.status).toLowerCase() === 'accepted') {
                        focusModeActive = false;
                        output.className = 'output-content output-success';
                        let successMsg = `ACCEPTED\n\n`;
                        successMsg += `All test cases passed: ${result.passed}/${result.total}\n`;
                        successMsg += `Execution Time: ${result.time}\n`;
                        successMsg += `Memory Used: ${result.memory}\n`;
                        successMsg += `Time Complexity: ${result.time_complexity}\n`;
                        successMsg += `Space Complexity: ${result.space_complexity}\n\n`;
                        successMsg += 'Use the buttons below to navigate.';
                        output.textContent = successMsg;
                        showSuccessModal(result.violations_cleared, result.redirect_to || '');
                    } else {
                        output.className = 'output-content output-error';
                        let errorMsg = `${String(result.status || 'error').toUpperCase()}\n\n`;
                        errorMsg += `Test Cases: ${result.passed}/${result.total} passed\n\n`;
                        errorMsg += 'Try again!';
                        output.textContent = errorMsg;
                        setSubmitState(true, 'Retry');
                    }
                } else {
                    output.className = 'output-content output-error';
                    output.textContent = result.message || 'Submission failed';
                    setSubmitState(true, 'Retry');
                }
            } catch (error) {
                output.className = 'output-content output-error';
                output.textContent = 'System error: ' + error.message;
                setSubmitState(true, 'Retry');
                console.error('Submission Error:', error);
            }
        }

        function showSuccessModal(violationsCleared = false, redirectTo = '') {
            // Remove existing modal if any
            const existing = document.getElementById('successModal');
            if (existing) existing.remove();
            
            let modal = document.createElement('div');
            modal.id = 'successModal';
            modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);display:flex;justify-content:center;align-items:center;z-index:10000;backdrop-filter:blur(5px);';
            
            let extraMsg = '';
            if (violationsCleared) {
                extraMsg = '<br><br><span style="color:#ffab00;font-weight:bold;background:rgba(255,171,0,0.1);padding:5px 10px;border-radius:5px;">🛡️ Focus Violations Forgiven!</span>';
            }
            
            const nextTarget = String(getRedirectTarget(redirectTo)).replace(/'/g, '%27');
            modal.innerHTML = `
                <div style="background:#1e1e1e;padding:30px;border-radius:15px;text-align:center;border:1px solid #333;box-shadow:0 10px 30px rgba(0,0,0,0.5);max-width:400px;animation: popIn 0.3s ease-out;">
                    <h2 style="color:#4caf50;margin-bottom:15px;font-size:24px;">🎉 Submission Accepted!</h2>
                    <p style="color:#ddd;margin-bottom:25px;font-size:16px;">You have successfully passed all test cases.${extraMsg}</p>
                    <div style="display:flex;gap:15px;justify-content:center;">
                        <button onclick="window.location.href='${nextTarget}'" style="padding:10px 20px;background:#4caf50;color:white;border:none;border-radius:5px;cursor:pointer;font-weight:bold;transition:transform 0.2s;">Go Back</button>
                        <button onclick="document.getElementById('successModal').remove()" style="padding:10px 20px;background:#333;color:white;border:1px solid #555;border-radius:5px;cursor:pointer;transition:background 0.2s;">Stay Here</button>
                    </div>
                </div>
                <style>@keyframes popIn { from {transform:scale(0.8);opacity:0;} to {transform:scale(1);opacity:1;} }</style>
            `;
            document.body.appendChild(modal);
        }
        
        // Draft persistence per problem
        function saveDraft() {
            try {
                const payload = {
                    language_id: getCurrentLang(),
                    source_code: editor.getValue(),
                    updated_at: Date.now()
                };
                localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(payload));
                const draftStatus = document.getElementById('draftStatus');
                if (draftStatus) {
                    draftStatus.innerHTML = '<strong>Draft:</strong> Saved locally at ' + new Date(payload.updated_at).toLocaleTimeString();
                }
            } catch (err) {
                console.warn('Unable to save draft', err);
                const draftStatus = document.getElementById('draftStatus');
                if (draftStatus) {
                    draftStatus.innerHTML = '<strong>Draft:</strong> Unable to save locally in this browser.';
                }
            }
        }

        function loadDraft() {
            try {
                const raw = localStorage.getItem(DRAFT_STORAGE_KEY);
                if (!raw) {
                    return null;
                }
                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object') {
                    return null;
                }
                return parsed;
            } catch (err) {
                console.warn('Unable to load draft', err);
                return null;
            }
        }

        const savedDraft = loadDraft();
        if (savedDraft && languageCatalog[String(savedDraft.language_id)]) {
            applyLanguage(String(savedDraft.language_id), false);
            editor.setValue(savedDraft.source_code || getLanguageMeta(savedDraft.language_id).template);
            const draftStatus = document.getElementById('draftStatus');
            if (draftStatus) {
                draftStatus.innerHTML = '<strong>Draft:</strong> Restored local draft from ' + new Date(savedDraft.updated_at || Date.now()).toLocaleString();
            }
        } else {
            applyLanguage(String(defaultLanguageId), false);
            editor.setValue(getLanguageMeta(defaultLanguageId).template);
        }

        setSubmitState(false, '');

        // Listen for changes and require a re-run before submit
        editor.on('change', function() {
            saveDraft();
            setSubmitState(false, '');
        });

        // ==============================================
        // NEW FEATURES (Timer, Splitter, Toggle)
        // ==============================================

        // 1. Stopwatch Timer
        let secondsElapsed = 0;
        let timerInterval;

        function startTimer() {
            if (timerInterval) return;
            timerInterval = setInterval(() => {
                secondsElapsed++;
                const h = Math.floor(secondsElapsed / 3600).toString().padStart(2, '0');
                const m = Math.floor((secondsElapsed % 3600) / 60).toString().padStart(2, '0');
                const s = (secondsElapsed % 60).toString().padStart(2, '0');
                const timerParams = document.getElementById('codingTimer');
                if(timerParams) timerParams.innerText = `${h}:${m}:${s}`;
            }, 1000);
        }
        
        // Start timer when page loads (or moved to fullscreen entry if stricter)
        startTimer();

        // 2. Resizable Splitter
        const gutter = document.getElementById('resizeGutter');
        const leftPanel = document.getElementById('problemPanel');
        const layout = document.getElementById('mainLayout');
        let isResizing = false;

        if (gutter && leftPanel && layout) {
            gutter.addEventListener('mousedown', (e) => {
                isResizing = true;
                gutter.classList.add('dragging');
                document.body.style.cursor = 'col-resize';
                e.preventDefault(); // Prevent text selection
            });

            document.addEventListener('mouseup', () => {
                if (isResizing) {
                    isResizing = false;
                    gutter.classList.remove('dragging');
                    document.body.style.cursor = 'default';
                    // Refresh CodeMirror to adjust to new size
                    editor.refresh(); 
                }
            });

            document.addEventListener('mousemove', (e) => {
                if (!isResizing) return;
                
                const containerRect = layout.getBoundingClientRect();
                const newLeftWidth = e.clientX - containerRect.left;
                const totalWidth = containerRect.width;
                const percentage = (newLeftWidth / totalWidth) * 100;
                
                // Min/Max widths (20% to 80%)
                if (percentage > 20 && percentage < 80) {
                    leftPanel.style.width = percentage + '%';
                }
            });
        }

        // 3. Toggle Output Panel
        window.toggleOutput = function() {
            const panel = document.getElementById('outputPanel');
            if(panel) panel.classList.toggle('collapsed');
        };

        // 4. Session Keep-Alive (Ping every 5 minutes)
        setInterval(() => {
            fetch('../../api/keep-alive.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.warn('Session might be expired');
                    }
                })
                .catch(err => console.error('Keep-alive failed', err));
        }, 5 * 60 * 1000); // 5 minutes
    </script>
</body>
</html>


