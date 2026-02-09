<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Compiler.php';
require_once __DIR__ . '/../../classes/FocusMode.php';

Auth::requireLogin();

$problemId = $_GET['id'] ?? null;
if (!$problemId) {
    header('Location: problems.php');
    exit;
}

$compiler = new Compiler();
$problem = $compiler->getProblem($problemId);

if (!$problem) {
    header('Location: problems.php');
    exit;
}

// Initialize Focus Mode Session
$focusMode = new FocusMode();
$userId = Auth::getUserId();
$focusSession = $focusMode->startSession($userId, 'coding', $problemId);
$sessionId = $focusSession['session_id'];
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
            height: 60px;
        }
        .header h2 { color: #fff; font-size: 1.1rem; display: flex; align-items: center; gap: 15px; }
        
        .timer-badge {
            background: #333;
            padding: 5px 12px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #ffa116;
            font-weight: bold;
            border: 1px solid #444;
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
            gap: 15px;
            align-items: center;
        }
        .control-group { display: flex; align-items: center; gap: 8px; font-size: 0.9rem; }
        .editor-controls select, .editor-controls input {
            background: #3c3c3c; border: 1px solid #444; color: #ddd; padding: 4px 8px; border-radius: 4px; outline: none;
        }
        
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
        
        .focus-warning, .violation-counter, .fullscreen-notice { position: fixed; z-index: 10000; }
        .violation-counter { top: 20px; right: 20px; background: #dc2626; color: white; padding: 8px 16px; border-radius: 6px; font-weight: bold; }
        .focus-warning { top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(220, 38, 38, 0.95); padding: 30px; border-radius: 12px; text-align: center; color: white; display: none; }
        .focus-warning.show { display: block; animation: shake 0.5s; }
        .fullscreen-notice { bottom: 20px; left: 50%; transform: translateX(-50%); background: #667eea; padding: 8px 20px; border-radius: 20px; font-size: 0.9rem; color: white; }
        
        @keyframes shake { 0%, 100% { transform: translate(-50%, -50%); } 25% { transform: translate(-52%, -50%); } 75% { transform: translate(-48%, -50%); } }
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
    
    <!-- Focus Mode Violation Counter -->
    <div class="violation-counter" id="violationCounter">
        ⚠️ Violations: <span id="violationCount">0</span>/3
    </div>
    
    <!-- Focus Mode Warning Overlay -->
    <div class="focus-warning" id="focusWarning">
        <h2>⚠️ VIOLATION DETECTED!</h2>
        <p id="warningMessage">Focus violation detected!</p>
        <p style="font-size: 0.9rem; opacity: 0.9;">Remaining chances: <span id="remainingChances">3</span></p>
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
        <div class="header-buttons">
            <button class="btn-download" onclick="saveCode()">💾 Download</button>
            <button class="btn-run" onclick="runCode()">▶ Run</button>
            <button class="btn-submit" onclick="submitCode()">✓ Submit</button>
            <button class="btn-back" onclick="location.href='problems.php'">Exit</button>
        </div>
    </div>
    
    <div class="layout" id="mainLayout">
        <div class="problem-panel" id="problemPanel">
            <span class="difficulty-badge <?php echo strtolower($problem['difficulty']); ?>">
                <?php echo $problem['difficulty']; ?>
            </span>
            
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
                        <option value="71">Python</option>
                        <option value="54">C++</option>
                        <option value="50">C</option>
                        <option value="62">Java</option>
                        <option value="63">JavaScript</option>
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
        // Language code templates
        const codeTemplates = {
            '71': `# Python Solution
def solution():
    # Read input
    n = int(input())
    
    # Your code here
    
    # Output result
    print(result)

solution()`,
            '54': `// C++ Solution
#include <iostream>
using namespace std;

int main() {
    // Read input
    int n;
    cin >> n;
    
    // Your code here
    
    // Output result
    cout << result << endl;
    return 0;
}`,
            '50': `// C Solution
#include <stdio.h>

int main() {
    // Read input
    int n;
    scanf("%d", &n);
    
    // Your code here
    
    // Output result
    printf("%d\\n", result);
    return 0;
}`,
            '62': `// Java Solution
import java.util.*;

public class Solution {
    public static void main(String[] args) {
        Scanner sc = new Scanner(System.in);
        
        // Read input
        int n = sc.nextInt();
        
        // Your code here
        
        // Output result
        System.out.println(result);
    }
}`,
            '63': `// JavaScript Solution
const readline = require('readline');
const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
});

rl.on('line', (input) => {
    // Read input
    const n = parseInt(input);
    
    // Your code here
    
    // Output result
    console.log(result);
    rl.close();
});`
        };
        
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
        
        // ==============================================
        // SECURE FOCUS MODE SYSTEM
        // ==============================================
        
        const FOCUS_SESSION_ID = <?php echo $sessionId; ?>;
        const MAX_VIOLATIONS = 3;
        let violationCount = 0;
        let isFullscreen = false;
        let focusModeActive = true;
        
        // Focus Mode Violation Handler
        function logViolation(type, message) {
            violationCount++;
            updateViolationUI(message);
            
            // Send to server
            fetch('../../api/focus-track.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: FOCUS_SESSION_ID,
                    violation_type: type,
                    duration: 0
                })
            });
            
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
            output.innerHTML = '❌ AUTO-SUBMITTING: Maximum violations (3) reached!\n\nPlease wait...';
            editor.setOption('readOnly', true);
            
            alert('⚠️ MAXIMUM VIOLATIONS REACHED!\n\nYour solution will be automatically submitted.\n\nRedirecting to dashboard...');
            
            try {
                await forceSubmitCode();
                output.innerHTML += '\n\n✅ Submission complete.\n⏳ Redirecting to dashboard in 2 seconds...';
                setTimeout(() => { window.location.href = '../dashboard/index.php'; }, 2000);
            } catch (error) {
                output.className = 'output-content output-error';
                output.innerHTML = '❌ AUTO-SUBMIT FAILED\n\nError: ' + error.message;
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
        
        function changeLanguage() {
            const lang = document.getElementById('languageSelect').value;
            const modes = {
                '71': 'python',
                '54': 'text/x-c++src',
                '50': 'text/x-csrc',
                '62': 'text/x-java',
                '63': 'javascript'
            };
            
            // Ask before changing if there's content
            if (editor.getValue().trim() && editor.getValue() !== codeTemplates[getCurrentLang()]) {
                if (!confirm('This will replace your current code. Continue?')) {
                    return;
                }
            }
            
            editor.setOption('mode', modes[lang]);
            editor.setValue(codeTemplates[lang]);
        }
        
        function getCurrentLang() {
            return document.getElementById('languageSelect').value;
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
            if (confirm('Reset to default template?')) {
                const lang = getCurrentLang();
                editor.setValue(codeTemplates[lang]);
            }
        }
        
        function showShortcuts() {
            alert(`Keyboard Shortcuts:
            
▶ Ctrl + ' : Run Code
✓ Ctrl + Enter : Quick Submit (No Comfirmation)
🔄 Ctrl + R : Reset Code
💾 Ctrl + S : Save (browser download)
📝 F9: Toggle Custom Input
⌨️ Ctrl + /: Toggle Comment
🔍 Ctrl + F: Find
📏 Ctrl + H: Replace
↔️ F11: Fullscreen
📋 Tab: Indent
⬅️ Shift + Tab: Unindent`);
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
            const extensions = {'71': 'py', '54': 'cpp', '50': 'c', '62': 'java', '63': 'js'};
            const blob = new Blob([code], {type: 'text/plain'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `solution.${extensions[lang]}`;
            a.click();
            URL.revokeObjectURL(url);
        }
        
        async function runCode() {
            const code = editor.getValue();
            const langId = document.getElementById('languageSelect').value;
            const output = document.getElementById('output');
            const customInput = document.getElementById('customInput').value;
            
            output.innerHTML = '⏳ Running your code...';
            output.className = 'output-content';
            
            // Expand output panel if collapsed
            document.getElementById('outputPanel').classList.remove('collapsed');
            
            try {
                const response = await fetch('../../api/compile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        source_code: code,
                        language_id: langId,
                        problem_id: <?php echo $problemId; ?>,
                        custom_input: customInput,
                        action: 'run'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    output.className = 'output-content output-success';
                    let outputText = `✓ Status: ${result.status}\n`;
                    outputText += `⏱️ Time: ${result.time || 0}s\n`;
                    outputText += `💾 Memory: ${result.memory || 0}KB\n\n`;
                    outputText += `📤 Output:\n${result.stdout || '(No output)'}`;
                    
                    if (result.stderr) {
                        outputText += `\n\n⚠️ Errors:\n${result.stderr}`;
                    }
                    if (result.compile_output) {
                        outputText += `\n\n🔧 Compile Output:\n${result.compile_output}`;
                    }
                    
                    output.innerHTML = outputText;
                } else {
                    output.className = 'output-content output-error';
                    output.innerHTML = '❌ ' + (result.message || 'Execution failed');
                }
            } catch (error) {
                output.className = 'output-content output-error';
                output.innerHTML = '❌ Error: ' + error.message;
            }
        }
        
        
        // Force submit without confirmation (used for auto-submit after violations)
        async function forceSubmitCode() {
            const code = editor.getValue();
            const langId = document.getElementById('languageSelect').value;
            const output = document.getElementById('output');
            
            output.innerHTML = '📨 Submitting your solution (forced due to violations)...';
            output.className = 'output-content';
            
            const response = await fetch('../../api/compile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    source_code: code,
                    language_id: langId,
                    problem_id: <?php echo $problemId; ?>,
                    action: 'submit'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                if (result.status === 'accepted') {
                    output.className = 'output-content output-success';
                    let successMsg = `🎉 ACCEPTED (Auto-Submitted) 🎉\n\n`;
                    successMsg += `✓ Test cases: ${result.passed}/${result.total}\n`;
                    successMsg += `⏱️ Time: ${result.time}\n`;
                    successMsg += `💾 Memory: ${result.memory}\n`;
                    successMsg += `📊 Time Complexity: ${result.time_complexity}\n`;
                    successMsg += `📊 Space Complexity: ${result.space_complexity}\n`;
                    output.innerHTML = successMsg;
                } else {
                    output.className = 'output-content output-error';
                    let errorMsg = `❌ ${result.status.toUpperCase()} (Auto-Submitted)\n\n`;
                    errorMsg += `Test Cases: ${result.passed}/${result.total} passed\n`;
                    output.innerHTML = errorMsg;
                }
            } else {
                throw new Error(result.message || 'Submission failed');
            }
        }
        
        async function submitCode(skipConfirm = false) {
            // Prevent submission if editor is read-only (already auto-submitted)
            if (editor.getOption('readOnly')) {
                alert('⚠️ Code has already been auto-submitted due to violations.\n\nYou cannot submit again.');
                return;
            }
            
            if (!skipConfirm) {
                if (!confirm('Submit your solution? This will run against all test cases.')) return;
            }
            
            const code = editor.getValue();
            const langId = document.getElementById('languageSelect').value;
            const output = document.getElementById('output');
            
            output.innerHTML = '📨 Submitting your solution...';
            output.className = 'output-content';

             // Expand output panel
            document.getElementById('outputPanel').classList.remove('collapsed');
            
            try {
                const response = await fetch('../../api/compile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        source_code: code,
                        language_id: langId,
                        problem_id: <?php echo $problemId; ?>,
                        action: 'submit'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (result.status === 'accepted') {
                        output.className = 'output-content output-success';
                        let successMsg = `🎉 ACCEPTED! 🎉\n\n`;
                        successMsg += `✓ All test cases passed: ${result.passed}/${result.total}\n\n`;
                        successMsg += `⏱️ Execution Time: ${result.time}\n`;
                        successMsg += `💾 Memory Used: ${result.memory}\n`;
                        successMsg += `📊 Time Complexity: ${result.time_complexity}\n`;
                        successMsg += `📊 Space Complexity: ${result.space_complexity}\n\n`;
                        successMsg += `Great job! Redirecting to dashboard...`;
                        output.innerHTML = successMsg;
                        setTimeout(() => { window.location.href = '../dashboard/index.php'; }, 3000);
                    } else {
                        output.className = 'output-content output-error';
                        let errorMsg = `❌ ${result.status.toUpperCase()}\n\n`;
                        errorMsg += `Test Cases: ${result.passed}/${result.total} passed\n\n`;
                        errorMsg += `Redirecting to dashboard...`;
                        output.innerHTML = errorMsg;
                        setTimeout(() => { window.location.href = '../dashboard/index.php'; }, 4000);
                    }
                } else {
                    output.className = 'output-content output-error';
                    output.innerHTML = '❌ ' + (result.message || 'Submission failed');
                }
            } catch (error) {
                output.className = 'output-content output-error';
                output.innerHTML = '❌ Error: ' + error.message;
            }
        }
        
        // Initialize with Python template
        editor.setValue(codeTemplates['71']);

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
    </script>
</body>
</html>
