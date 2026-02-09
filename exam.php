<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Coding Exam Platform</title>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #0f172a;
            --surface: #1e293b;
            --surface-light: #334155;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --code-bg: #0d1117;
            --gutter-bg: #161b22;
            --gutter-text: #4b5563;
            --console-bg: #000000;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            margin: 0; padding: 0;
            background-color: var(--bg); color: var(--text);
            height: 100vh; overflow: hidden;
            display: flex; flex-direction: column;
            user-select: none; /* Global selection block */
        }

        /* --- Screens --- */
        .screen {
            display: none; flex-direction: column;
            align-items: center; justify-content: center;
            height: 100%; width: 100%;
            animation: fadeIn 0.3s ease-in-out;
        }
        .active-screen { display: flex; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* --- Start Screen --- */
        .start-card {
            background: var(--surface);
            padding: 40px;
            border-radius: 16px;
            border: 1px solid var(--surface-light);
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        .start-card h1 { margin-top: 0; color: var(--primary); }
        .instruction-list { text-align: left; background: rgba(255,255,255,0.05); padding: 20px; border-radius: 8px; margin: 20px 0; }
        .instruction-list li { margin-bottom: 8px; color: var(--text-muted); }

        /* --- UI Components --- */
        button {
            padding: 10px 20px; font-size: 0.95rem; cursor: pointer;
            color: white; border: none; border-radius: 6px; 
            transition: all 0.2s; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px;
        }
        
        .btn-primary { background-color: var(--primary); box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3); }
        .btn-primary:hover { background-color: var(--primary-hover); transform: translateY(-1px); }
        
        .btn-run { background-color: var(--surface-light); border: 1px solid #475569; }
        .btn-run:hover { background-color: #475569; }
        
        .btn-submit { background-color: var(--success); }
        .btn-submit:hover { background-color: #059669; }

        .btn-finish { background-color: var(--danger); }

        .header-bar {
            width: 100%; height: 60px; padding: 0 20px; background-color: var(--surface);
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--surface-light);
            flex-shrink: 0;
        }

        .status-badge {
            background-color: rgba(255,255,255,0.1); padding: 6px 14px;
            border-radius: 20px; font-size: 0.85rem; border: 1px solid var(--surface-light); margin-right: 15px;
        }

        /* --- Layout: Resizable Split Pane --- */
        .main-container { display: flex; width: 100%; height: calc(100vh - 60px); position: relative; }
        
        .question-panel {
            width: 35%; 
            min-width: 300px;
            background: var(--surface); 
            padding: 25px; 
            overflow-y: auto; 
            border-right: 1px solid var(--surface-light);
        }

        /* Resizer Handle */
        .resizer {
            width: 5px;
            background: var(--bg);
            cursor: col-resize;
            transition: background 0.2s;
            z-index: 10;
        }
        .resizer:hover { background: var(--primary); }

        .ide-panel {
            flex: 1; /* Takes remaining space */
            min-width: 400px;
            display: flex; flex-direction: column;
            background: var(--bg);
            padding: 10px;
        }

        /* --- Typography & Elements --- */
        .question-panel h2 { margin-top: 0; font-size: 1.5rem; color: white; }
        .badge-difficulty { 
            background: rgba(16, 185, 129, 0.2); color: var(--success); padding: 4px 10px; 
            border-radius: 4px; font-size: 0.75rem; font-weight: bold; border: 1px solid rgba(16, 185, 129, 0.4);
        }
        .section-title { font-weight: bold; margin: 25px 0 10px 0; color: #e2e8f0; border-bottom: 1px solid #334155; padding-bottom: 5px;}
        
        .example-box {
            background: #0f1520; padding: 15px; border-radius: 8px;
            border: 1px solid #334155; font-family: 'Consolas', monospace; font-size: 0.9rem;
            color: #d1d5db; margin-bottom: 15px;
        }
        code { background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; font-family: monospace; color: #bfdbfe; }
        
        /* --- Editor --- */
        .ide-toolbar { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 10px; background: var(--surface); padding: 8px; border-radius: 6px; border: 1px solid var(--surface-light);
        }
        select { 
            background: var(--bg); color: white; padding: 8px 12px; 
            border: 1px solid #475569; border-radius: 4px; outline: none; cursor: pointer;
        }

        .editor-container {
            display: flex; position: relative; flex-grow: 1;
            border: 1px solid #334155; border-radius: 6px;
            background-color: var(--code-bg); overflow: hidden;
            margin-bottom: 10px;
        }

        .line-numbers {
            width: 40px; background-color: var(--gutter-bg); color: var(--gutter-text);
            text-align: right; padding: 15px 5px; font-family: 'Consolas', monospace;
            font-size: 14px; line-height: 1.5; border-right: 1px solid #334155;
        }

        .code-wrapper { position: relative; flex-grow: 1; height: 100%; }

        textarea.code-editor {
            width: 100%; height: 100%; background-color: transparent; color: #e6edf3;
            font-family: 'Consolas', monospace; font-size: 14px; line-height: 1.5;
            padding: 15px; border: none; resize: none; outline: none;
            user-select: text; /* Allow typing */
            white-space: pre; overflow: auto; position: relative; z-index: 2;
        }
        
        /* Focus state for editor */
        .editor-container:focus-within { border-color: var(--primary); }

        /* Intellisense & Console */
        .suggestion-box {
            position: absolute; background-color: #1c2128; border: 1px solid #444c56;
            width: 200px; max-height: 200px; overflow-y: auto; display: none; z-index: 20;
            border-radius: 6px; box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        }
        .suggestion-item { padding: 8px 12px; cursor: pointer; color: #adbac7; font-family: monospace; font-size: 13px; }
        .suggestion-item:hover, .suggestion-item.active { background-color: var(--primary); color: white; }

        .console-output {
            height: 150px; background-color: var(--console-bg); color: #4ade80;
            font-family: 'Consolas', monospace; padding: 15px; border-radius: 6px;
            overflow-y: auto; font-size: 0.85rem; border: 1px solid #334155;
            white-space: pre-wrap;
        }

        /* --- Modals --- */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.95); display: none;
            justify-content: center; align-items: center; z-index: 100;
        }
        .modal-content {
            background: var(--surface); padding: 40px; border-radius: 12px;
            border: 2px solid var(--danger); text-align: center; max-width: 450px;
            animation: shake 0.5s;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* --- Result Card --- */
        .result-card {
            background: var(--surface); padding: 40px; border-radius: 12px;
            border: 1px solid var(--surface-light); text-align: center; width: 400px;
        }
        .result-stats { display: flex; justify-content: space-between; margin: 20px 0; border-top: 1px solid #334155; border-bottom: 1px solid #334155; padding: 15px 0; }
        .stat-item { flex: 1; }
        .stat-label { font-size: 0.8rem; color: var(--text-muted); display: block; }
        .stat-val { font-size: 1.2rem; font-weight: bold; }

        .text-warn { color: var(--warning); }
        .text-danger { color: var(--danger); }
        .text-success { color: var(--success); }
    </style>
</head>
<body>

    <div id="start-screen" class="screen active-screen">
        <div class="start-card">
            <h1>Secure Coding Exam</h1>
            <p style="color: var(--text-muted);">Please read the instructions carefully before starting.</p>
            
            <div class="instruction-list">
                <ul>
                    <li><strong>Duration:</strong> 45 Minutes</li>
                    <li><strong>Environment:</strong> Fullscreen Mode Enforced</li>
                    <li><strong>Proctoring:</strong> Tab switching & resizing are tracked</li>
                    <li><strong>Languages:</strong> Python, C, Java</li>
                </ul>
            </div>
            
            <p style="font-size: 0.9rem; color: #ef4444; margin-bottom: 25px;">
                ⚠️ <strong>Warning:</strong> Switching tabs more than 3 times will auto-submit the exam.
            </p>
            
            <button class="btn-primary" onclick="startExam()">
                Start Assessment <span>&rarr;</span>
            </button>
        </div>
    </div>

    <div id="exam-screen" class="screen">
        <div class="header-bar">
            <div style="display:flex; align-items:center;">
                <h3 style="margin:0 20px 0 0;">CodePortal</h3>
                <span class="status-badge">⏱ <span id="timer">00:00</span></span>
                <span class="status-badge">⚠️ <span id="violation-count" class="text-success">0</span>/3</span>
            </div>
            <div>
                 <span class="status-badge" id="problem-status" style="border-color: #64748b;">Status: Unsolved</span>
                <button class="btn-finish" onclick="finishExam()">Finish Exam</button>
            </div>
        </div>

        <div class="main-container" id="main-container">
            <div class="question-panel" id="question-panel">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h2>1. Linear Search</h2>
                    <span class="badge-difficulty">Easy</span>
                </div>
                <p style="color: var(--text-muted); margin-top: -10px;">10 Points</p>
                
                <hr style="border:0; border-bottom:1px solid #334155; margin: 20px 0;">

                <p>Given an array of integers <code>arr</code> and an integer <code>target</code>, write a function to search for <code>target</code> in <code>arr</code>.</p>
                <p>If the target exists, return its <strong>index</strong>. Otherwise, return <strong>-1</strong>.</p>
                
                <div class="section-title">Input Format</div>
                <ul>
                    <li>First argument: An integer array <code>arr</code>.</li>
                    <li>Second argument: An integer <code>target</code>.</li>
                </ul>

                <div class="section-title">Output Format</div>
                <ul>
                    <li>Return an integer representing the index or -1.</li>
                </ul>

                <div class="section-title">Example 1</div>
                <div class="example-box">
                    <strong>Input:</strong> arr = [10, 20, 30, 40], target = 30<br>
                    <strong>Output:</strong> 2
                </div>

                <div class="section-title">Example 2</div>
                <div class="example-box">
                    <strong>Input:</strong> arr = [10, 20], target = 50<br>
                    <strong>Output:</strong> -1
                </div>

                <div class="section-title">Constraints</div>
                <ul style="font-size: 0.9rem; color: #94a3b8; padding-left: 20px;">
                    <li><code>1 <= arr.length <= 10^5</code></li>
                    <li><code>-10^9 <= arr[i] <= 10^9</code></li>
                </ul>
            </div>

            <div class="resizer" id="dragMe"></div>

            <div class="ide-panel">
                <div class="ide-toolbar">
                    <select id="language-select" onchange="changeTemplate()">
                        <option value="python">Python 3</option>
                        <option value="c">C (GCC)</option>
                        <option value="java">Java (JDK)</option>
                    </select>
                    <div style="display:flex; gap:10px;">
                        <button class="btn-run" onclick="runCode('sample')">▶ Run Code</button>
                        <button class="btn-submit" onclick="runCode('submit')">☁ Submit</button>
                    </div>
                </div>
                
                <div class="editor-container">
                    <div id="line-numbers" class="line-numbers">1</div>
                    <div class="code-wrapper">
                        <textarea id="code-editor" class="code-editor" spellcheck="false"></textarea>
                        <div id="cursor-tracker" style="position:absolute; top:0; left:0; visibility:hidden; font-family:'Consolas',monospace; font-size:14px; white-space:pre-wrap;"></div>
                        <div id="suggestion-box" class="suggestion-box"></div>
                    </div>
                </div>
                
                <div class="console-output" id="console">
                    > Console ready...
                </div>
            </div>
        </div>
    </div>

    <div id="result-screen" class="screen">
        <div class="result-card">
            <h1 style="margin-top:0;">Exam Submitted</h1>
            <p style="color: var(--text-muted);">Your session has been recorded.</p>
            
            <div class="result-stats">
                <div class="stat-item">
                    <span class="stat-label">Violations</span>
                    <span class="stat-val" id="final-violations" style="color:var(--danger)">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Attempts</span>
                    <span class="stat-val" id="final-attempts">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Time</span>
                    <span class="stat-val" id="final-time">00:00</span>
                </div>
            </div>

            <div style="margin: 20px 0;">
                <span style="font-size: 0.9rem; color: var(--text-muted);">Final Status:</span><br>
                <span id="final-status" style="font-size: 1.5rem; font-weight: bold; color: var(--text-muted);">Unsolved</span>
            </div>

            <button class="btn-primary" onclick="window.close()">Close Window</button>
        </div>
    </div>

    <div id="warning-modal" class="modal-overlay">
        <div class="modal-content">
            <h1 style="font-size: 3rem; margin: 0;">⚠️</h1>
            <h2 class="text-danger" style="margin-top: 10px;">SECURITY ALERT</h2>
            <p id="warning-text" style="color: var(--text-muted); font-size: 1.1rem;">Violation Detected</p>
            <button class="btn-primary" onclick="dismissWarning()" style="margin-top: 20px; width: 100%; justify-content: center;">I Understand & Resume</button>
        </div>
    </div>

    <script>
        // --- Variables ---
        let violations = 0;
        let attempts = 0;
        let secondsElapsed = 0;
        let timerInterval;
        let isExamActive = false;
        let solved = false;
        
        // --- Elements ---
        const editor = document.getElementById('code-editor');
        const lineNumbers = document.getElementById('line-numbers');
        const suggestionBox = document.getElementById('suggestion-box');
        const consoleDiv = document.getElementById('console');
        const violationDisplay = document.getElementById('violation-count');
        const warningModal = document.getElementById('warning-modal');
        const statusBadge = document.getElementById('problem-status');
        
        // --- Keywords Data ---
        const keywords = {
            python: ['def', 'return', 'print', 'range', 'len', 'if', 'else', 'elif', 'for', 'while', 'import', 'class', 'break', 'True', 'False'],
            c: ['int', 'float', 'double', 'char', 'return', 'void', 'printf', 'scanf', 'include', 'for', 'while', 'if', 'else', 'switch', 'case'],
            java: ['public', 'static', 'void', 'main', 'class', 'int', 'String', 'System.out.println', 'return', 'if', 'else', 'for', 'new']
        };

        const templates = {
            python: "def linear_search(arr, target):\n    # Write your code here\n    pass",
            c: "#include <stdio.h>\n\nint linearSearch(int arr[], int n, int target) {\n    // Write your code here\n    return -1;\n}",
            java: "public class Main {\n    public static int linearSearch(int[] arr, int target) {\n        // Write your code here\n        return -1;\n    }\n}"
        };

        // --- 1. Initialization ---
        function startExam() {
            // Attempt fullscreen
            document.documentElement.requestFullscreen().then(() => {
                isExamActive = true;
                document.getElementById('start-screen').classList.remove('active-screen');
                document.getElementById('exam-screen').classList.add('active-screen');
                window.addEventListener('beforeunload', confirmExit);
                
                changeTemplate();
                setupEditor();
                setupResizer();
                startMonitoring();
                startTimer();
            }).catch(err => {
                alert("Fullscreen is required to take this exam. Please allow it.");
            });
        }

        function changeTemplate() {
            const lang = document.getElementById('language-select').value;
            editor.value = templates[lang];
            updateLineNumbers();
        }

        // --- 2. Editor Logic ---
        function setupEditor() {
            editor.addEventListener('input', function() {
                updateLineNumbers();
                handleIntellisense(this);
            });
            editor.addEventListener('scroll', function() {
                lineNumbers.scrollTop = this.scrollTop;
            });
            editor.addEventListener('keydown', function(e) {
                // Tab support
                if (e.key === 'Tab') {
                    e.preventDefault();
                    insertTextAtCursor("    ");
                }
                // Auto-close brackets
                const pairs = { '(': ')', '{': '}', '[': ']', '"': '"', "'": "'" };
                if (pairs[e.key]) {
                    e.preventDefault();
                    const start = this.selectionStart;
                    const end = this.selectionEnd;
                    const text = this.value;
                    this.value = text.substring(0, start) + e.key + pairs[e.key] + text.substring(end);
                    this.selectionStart = this.selectionEnd = start + 1;
                    updateLineNumbers(); 
                }
            });
        }

        function updateLineNumbers() {
            const lines = editor.value.split('\n').length;
            lineNumbers.innerHTML = Array.from({length: lines}, (_, i) => i + 1).join('<br>');
        }

        function insertTextAtCursor(textToInsert) {
            const start = editor.selectionStart;
            const end = editor.selectionEnd;
            const text = editor.value;
            editor.value = text.substring(0, start) + textToInsert + text.substring(end);
            editor.selectionStart = editor.selectionEnd = start + textToInsert.length;
        }

        // --- 3. Intellisense ---
        function handleIntellisense(elem) {
            const text = elem.value;
            const cursorPos = elem.selectionStart;
            // Basic regex to find the word being typed
            const lastWordMatch = text.substring(0, cursorPos).match(/(\w+)$/);

            if (lastWordMatch) {
                const currentWord = lastWordMatch[0];
                showSuggestions(currentWord, cursorPos);
            } else {
                suggestionBox.style.display = 'none';
            }
        }

        function showSuggestions(word, cursorPos) {
            const lang = document.getElementById('language-select').value;
            const matches = keywords[lang].filter(k => k.startsWith(word) && k !== word);

            if (matches.length === 0) {
                suggestionBox.style.display = 'none';
                return;
            }
            suggestionBox.innerHTML = '';
            matches.forEach(match => {
                const div = document.createElement('div');
                div.className = 'suggestion-item';
                div.innerText = match;
                div.onclick = () => insertSuggestion(match, word);
                suggestionBox.appendChild(div);
            });
            
            // Basic positioning (Improvements require calculating text coordinates)
            // For this snippet, we keep it simple relative to editor container or fixed for now
            // To make it follow cursor perfectly, we need a "mirror" div logic which makes code heavy.
            // Simplified: Showing top-left of editor for demo stability
            suggestionBox.style.display = 'block';
            suggestionBox.style.left = '60px'; // Offset for line numbers
            suggestionBox.style.top = '40px'; 
        }

        function insertSuggestion(fullWord, typedPart) {
            const cursorPos = editor.selectionStart;
            const text = editor.value;
            const before = text.substring(0, cursorPos - typedPart.length);
            const after = text.substring(cursorPos);
            editor.value = before + fullWord + after;
            suggestionBox.style.display = 'none';
            editor.focus();
            editor.selectionStart = editor.selectionEnd = before.length + fullWord.length;
        }

        // --- 4. Resizer Logic (Drag Bar) ---
        function setupResizer() {
            const resizer = document.getElementById('dragMe');
            const leftSide = document.getElementById('question-panel');
            const rightSide = leftSide.nextElementSibling; // The resizer itself
            const container = document.getElementById('main-container');

            let x = 0;
            let leftWidth = 0;

            const mouseDownHandler = function(e) {
                x = e.clientX;
                leftWidth = leftSide.getBoundingClientRect().width;
                document.addEventListener('mousemove', mouseMoveHandler);
                document.addEventListener('mouseup', mouseUpHandler);
                resizer.style.background = 'var(--primary)';
            };

            const mouseMoveHandler = function(e) {
                const dx = e.clientX - x;
                const newLeftWidth = ((leftWidth + dx) * 100) / container.getBoundingClientRect().width;
                leftSide.style.width = `${newLeftWidth}%`;
                // Prevent panel from disappearing
                if(newLeftWidth < 15) leftSide.style.width = '15%';
                if(newLeftWidth > 70) leftSide.style.width = '70%';
            };

            const mouseUpHandler = function() {
                resizer.style.removeProperty('background');
                document.removeEventListener('mousemove', mouseMoveHandler);
                document.removeEventListener('mouseup', mouseUpHandler);
            };

            resizer.addEventListener('mousedown', mouseDownHandler);
        }

        // --- 5. Run & Submit Logic ---
        function runCode(mode) {
            if(!isExamActive) return;
            const code = editor.value;
            const lang = document.getElementById('language-select').value;
            
            // Basic heuristic check for logic
            const hasLogic = code.includes("for") || code.includes("while");
            const hasReturn = code.includes("return");

            if (mode === 'sample') {
                consoleDiv.innerHTML = `> Compiling ${lang} code...\n`;
                setTimeout(() => {
                    consoleDiv.innerHTML += `> Running Sample Case...\n`;
                    consoleDiv.innerHTML += `> Input: [10, 20, 30], Target: 20\n`;
                    if (hasLogic && hasReturn) {
                        consoleDiv.innerHTML += `> Output: 1 [Success]\n`;
                        consoleDiv.innerHTML += `> Execution Time: 0.05s\n`;
                    } else {
                        consoleDiv.innerHTML += `> Output: -1 [Fail] (Logic seems incomplete)\n`;
                    }
                    consoleDiv.scrollTop = consoleDiv.scrollHeight;
                }, 600);

            } else if (mode === 'submit') {
                attempts++;
                consoleDiv.innerHTML = `> Submitting solution to server...\n`;
                
                setTimeout(() => {
                    if (hasLogic && hasReturn) {
                        consoleDiv.innerHTML += `> Test Case 1: Passed\n`;
                        consoleDiv.innerHTML += `> Test Case 2: Passed (Hidden)\n`;
                        consoleDiv.innerHTML += `> Test Case 3: Passed (Hidden)\n`;
                        consoleDiv.innerHTML += `> Result: ACCEPTED\n`;
                        
                        solved = true;
                        statusBadge.innerText = "Status: Solved";
                        statusBadge.style.background = "#064e3b";
                        statusBadge.style.color = "#34d399";
                        statusBadge.style.borderColor = "#34d399";
                    } else {
                        consoleDiv.innerHTML += `> Test Case 1: Failed\n`;
                        consoleDiv.innerHTML += `> Result: WRONG ANSWER\n`;
                        statusBadge.innerText = "Status: Attempted";
                        statusBadge.style.background = "#451a03";
                        statusBadge.style.color = "#fbbf24";
                        statusBadge.style.borderColor = "#fbbf24";
                    }
                    consoleDiv.scrollTop = consoleDiv.scrollHeight;
                }, 1200);
            }
        }

        // --- 6. Security & Timer ---
        function startTimer() {
            timerInterval = setInterval(() => {
                secondsElapsed++;
                const m = Math.floor(secondsElapsed / 60).toString().padStart(2, '0');
                const s = (secondsElapsed % 60).toString().padStart(2, '0');
                document.getElementById('timer').innerText = `${m}:${s}`;
            }, 1000);
        }

        function startMonitoring() {
            // Tab Switch detection
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) handleViolation("Tab switch detected!");
            });
            // Focus lost (Alt+Tab)
            window.addEventListener('blur', () => {
                setTimeout(() => {
                    if (document.activeElement.tagName === "IFRAME") return;
                    handleViolation("Window focus lost!");
                }, 500);
            });
            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                if (!isExamActive) return;
                const k = e.key.toLowerCase();
                const ctrl = e.ctrlKey || e.metaKey;
                if ((ctrl && ['c','v','x','r','w','p'].includes(k)) || k === 'f5' || k === 'f12') {
                    e.preventDefault();
                    handleViolation(`Blocked Shortcut: ${e.key}`);
                }
            });
        }

        function handleViolation(msg) {
            if (!isExamActive) return;
            // Debounce violations
            if (window.lastViolation && Date.now() - window.lastViolation < 1000) return;
            window.lastViolation = Date.now();
            
            violations++;
            violationDisplay.innerText = violations;
            
            if (violations >= 2) {
                violationDisplay.classList.remove('text-success');
                violationDisplay.classList.add('text-danger');
            }

            document.getElementById('warning-text').innerText = msg;
            warningModal.style.display = 'flex';
            
            if (violations >= 3) {
                 setTimeout(() => finishExam(), 1500); // Give user a moment to see why
            }
        }

        function dismissWarning() {
            warningModal.style.display = 'none';
            editor.focus();
        }

        function confirmExit(e) {
            if (isExamActive) { e.preventDefault(); e.returnValue = ''; }
        }

        function finishExam() {
            isExamActive = false;
            clearInterval(timerInterval);
            window.removeEventListener('beforeunload', confirmExit);
            document.exitFullscreen().catch(e => {});
            
            document.getElementById('exam-screen').classList.remove('active-screen');
            document.getElementById('result-screen').classList.add('active-screen');
            
            document.getElementById('final-violations').innerText = violations;
            document.getElementById('final-attempts').innerText = attempts;
            document.getElementById('final-time').innerText = document.getElementById('timer').innerText;
            document.getElementById('final-status').innerText = solved ? "Solved" : "Unsolved";
            
            if(solved) document.getElementById('final-status').style.color = "#4ade80";
        }
    </script>
</body>
</html>