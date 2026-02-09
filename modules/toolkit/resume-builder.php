<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Toolkit.php';

Auth::requireLogin();
$userId = Auth::getUserId();

$toolkit = new Toolkit();

// Handle resume save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_resume'])) {
    $personalInfo = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'location' => $_POST['location'] ?? '',
        'linkedin' => $_POST['linkedin'] ?? '',
        'github' => $_POST['github'] ?? '',
        'portfolio' => $_POST['portfolio'] ?? '',
        'summary' => $_POST['summary'] ?? '', // Career Objective
        'dob' => $_POST['dob'] ?? '',
        'languages' => $_POST['languages'] ?? '',
        'hobbies' => $_POST['hobbies'] ?? '',
        'achievements' => json_decode($_POST['achievements_json'] ?? '[]', true),
        'declaration' => $_POST['declaration'] ?? '',
        'signature' => $_POST['signature'] ?? ''
    ];

    $resumeData = [
        'personal_info' => $personalInfo,
        'education' => json_decode($_POST['education_json'] ?? '[]', true),
        'experience' => json_decode($_POST['experience_json'] ?? '[]', true),
        'skills' => explode(',', $_POST['skills'] ?? ''),
        'projects' => json_decode($_POST['projects_json'] ?? '[]', true),
        'certifications' => json_decode($_POST['certifications_json'] ?? '[]', true),
        'template_id' => $_POST['template_id'] ?? 1
    ];
    
    $result = $toolkit->saveResume($userId, $resumeData);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// Load existing resume
$resume = $toolkit->getResume($userId);

// Page Config
$pageTitle = 'Resume Builder - PlacementCode';
$additionalCSS = ''; // Not used here as styles are inline or global
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<style>
    /* Premium Dark Theme Variables */
    :root {
        --primary: #ffa116;
        --primary-hover: #ffb347;
        --bg-dark: #121212;
        --card-bg: #1e1e1e;
        --border-color: #333;
        --text-main: #e4e4e7;
        --text-muted: #a1a1aa;
        --input-bg: #27272a;
    }

    /* Page Layout */
    .container { 
        max-width: 1600px; 
        margin: 0 auto; 
        padding: 40px 30px; 
    }

    .header-section {
        margin-bottom: 40px;
        text-align: center;
    }
    .header-section h1 { 
        font-size: 2.5rem; 
        margin-bottom: 10px; 
        background: linear-gradient(135deg, #fff 0%, #a1a1aa 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 800;
    }
    .header-section p { 
        color: var(--text-muted); 
        font-size: 1.1rem; 
    }

    /* Builder Layout (Split Screen) */
    .builder-container {
        display: flex;
        gap: 40px;
        align-items: flex-start;
    }
    
    .builder-form {
        flex: 1;
        min-width: 0;
        height: calc(100vh - 100px);
        overflow-y: auto;
        padding-right: 15px; /* Space for scrollbar */
    }
    
    /* Custom Scrollbar */
    .builder-form::-webkit-scrollbar { width: 8px; }
    .builder-form::-webkit-scrollbar-track { background: transparent; }
    .builder-form::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
    .builder-form::-webkit-scrollbar-thumb:hover { background: #444; }

    .preview-container {
        flex: 1;
        position: sticky;
        top: 20px;
        background: #2b2b2b; /* Neutral dark grey for contrast */
        padding: 40px;
        border-radius: 16px;
        height: calc(100vh - 40px);
        overflow-y: auto;
        min-width: 600px; /* Ensure A4 fits well */
        display: flex;
        justify-content: center;
        border: 1px solid #444;
        box-shadow: inset 0 0 20px rgba(0,0,0,0.2);
    }
    
    /* Form Cards */
    .form-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 25px;
        margin-bottom: 25px;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .form-card:hover { border-color: #444; }
    .form-card h3 { 
        margin-bottom: 25px; 
        font-size: 1.25rem; 
        color: var(--primary); 
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Form Elements */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { margin-bottom: 20px; }
    .form-group.full-width { grid-column: span 2; }
    
    .form-group label { 
        display: block; 
        margin-bottom: 8px; 
        color: var(--text-muted); 
        font-size: 0.9rem; 
        font-weight: 500; 
    }
    
    .form-control {
        width: 100%;
        background: var(--input-bg);
        border: 1px solid var(--border-color);
        padding: 14px;
        border-radius: 10px;
        color: var(--text-main);
        font-size: 0.95rem;
        font-family: inherit;
        transition: all 0.2s;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 161, 22, 0.1);
        background: #2e2e31;
    }
    textarea.form-control { 
        resize: vertical; 
        min-height: 100px; 
        line-height: 1.5;
    }

    /* Template Selector */
    .template-selector {
        display: flex;
        gap: 20px;
        margin-bottom: 40px;
        flex-wrap: wrap;
        justify-content: center;
    }
    .template-card {
        background: var(--card-bg);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 20px 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        min-width: 180px;
    }
    .template-card:hover { 
        border-color: var(--primary); 
        transform: translateY(-2px); 
        box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    }
    .template-card.active { 
        border-color: var(--primary); 
        background: rgba(255, 161, 22, 0.05); 
    }
    .template-card h3 { margin: 0; color: var(--text-main); font-size: 1.1rem; }

    /* Dynamic Lists */
    .dynamic-section {
        background: #151515;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 15px;
        border: 1px solid #333;
        position: relative;
    }

    /* Buttons */
    .btn-add {
        background: transparent;
        color: var(--primary);
        padding: 12px 20px;
        border: 1px dashed var(--primary);
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        margin-top: 10px;
        transition: all 0.2s;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .btn-add:hover { 
        background: rgba(255, 161, 22, 0.05); 
        transform: translateY(-1px);
    }

    .btn-remove {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        padding: 8px 14px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 500;
        transition: background 0.2s;
        margin-top: 10px;
    }
    .btn-remove:hover { background: rgba(239, 68, 68, 0.2); }

    .action-buttons {
        display: flex;
        gap: 20px;
        margin-top: 30px;
        padding: 20px;
        background: var(--card-bg);
        border-radius: 16px;
        border: 1px solid var(--border-color);
        position: sticky;
        bottom: 20px;
        z-index: 10;
        box-shadow: 0 -10px 30px rgba(0,0,0,0.5);
    }
    .btn-save {
        flex: 2;
        background: var(--primary);
        color: #000;
        padding: 16px;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1.05rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-save:hover { background: var(--primary-hover); transform: translateY(-2px); }

    .btn-preview {
        flex: 1;
        background: #333;
        color: #fff;
        padding: 16px;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 1.05rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-preview:hover { background: #444; }

    /* Alerts */
    .alert {
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 30px;
        border-left: 4px solid;
    }
    .alert.success { background: rgba(34, 197, 94, 0.1); color: #22c55e; border-left-color: #22c55e; }
    .alert.error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border-left-color: #ef4444; }

    /* =========================================
       A4 Resume Paper Styles (The Preview)
       ========================================= */
    .resume-paper {
        background: #fff;
        color: #111;
        width: 210mm; /* Exact A4 Width */
        min-height: 297mm; /* Exact A4 Height */
        padding: 15mm 15mm;
        box-shadow: 0 0 30px rgba(0,0,0,0.3);
        font-family: 'Times New Roman', serif;
        line-height: 1.5;
        font-size: 11pt;
        box-sizing: border-box;
        transform-origin: top center;
        /* Make it fit if container is small */
    }

    /* Template 1: Classic Professional */
    .template-1 { font-family: 'Calibri', 'Arial', sans-serif; }
    .template-1 h1 { 
        font-family: 'Georgia', serif; 
        border-bottom: 2px solid #222; 
        padding-bottom: 10px; 
        margin: 0 0 10px 0; 
        color: #111; 
        text-transform: uppercase; 
        font-size: 24pt;
        letter-spacing: 1px;
    }
    .template-1 .contact-info { 
        margin-bottom: 25px; 
        font-size: 10pt; 
        color: #444;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
    }
    .template-1 .contact-info span { display: inline-block; }
    .template-1 h2 { 
        background: #f4f4f4; 
        padding: 6px 10px; 
        margin: 20px 0 10px 0; 
        font-size: 12pt; 
        font-weight: 700; 
        text-transform: uppercase; 
        color: #222;
        border-bottom: 1px solid #ccc; 
    }
    .template-1 .entry { margin-bottom: 12px; }
    .template-1 .entry-header { 
        display: flex; 
        justify-content: space-between; 
        font-weight: 700; 
        font-size: 11.5pt;
        margin-bottom: 2px;
    }
    .template-1 .entry-sub { 
        font-style: italic; 
        font-size: 10.5pt; 
        color: #444; 
        margin-bottom: 4px;
    }
    .template-1 .entry-desc { font-size: 10.5pt; white-space: pre-wrap; color: #333; }
    .template-1 .personal-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 10.5pt; margin-top: 10px; }
    .template-1 .declaration-sec { margin-top: 40px; border-top: 1px solid #000; padding-top: 15px; font-size: 10pt; }

    /* Template 2: Modern Blue */
    .template-2 { font-family: 'Roboto', sans-serif; color: #333; }
    .template-2 h1 { color: #2563eb; font-weight: 900; font-size: 28pt; margin: 0 0 5px 0; }
    .template-2 .contact-info { color: #555; font-size: 10pt; margin-bottom: 30px; }
    .template-2 h2 { 
        color: #2563eb; 
        border-bottom: 2px solid #2563eb; 
        margin: 25px 0 15px 0; 
        font-size: 14pt; 
        text-transform: uppercase; 
        letter-spacing: 1px; 
        padding-bottom: 5px;
    }
    .template-2 .entry-header { font-weight: 700; color: #000; }
    .template-2 .entry-sub { color: #555; font-weight: 500; }

    /* Template 3: Minimalist */
    .template-3 { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #444; }
    .template-3 h1 { text-align: center; font-weight: 300; letter-spacing: 3px; font-size: 26pt; margin-bottom: 10px; color: #000; }
    .template-3 .contact-info { text-align: center; color: #888; font-size: 9pt; letter-spacing: 1px; margin-bottom: 40px; }
    .template-3 h2 { 
        text-align: center; 
        letter-spacing: 3px; 
        text-transform: uppercase; 
        font-size: 10pt; 
        border-bottom: 1px solid #eee; 
        padding-bottom: 10px; 
        margin: 30px 0 20px 0; 
        color: #000;
    }
    .template-3 .entry-header { justify-content: center; flex-direction: column; text-align: center; margin-bottom: 5px; }
    .template-3 .entry-sub { text-align: center; font-style: normal; color: #666; margin-bottom: 10px; }
    .template-3 .entry-desc { text-align: center; max-width: 90%; margin: 0 auto; }

    @media (max-width: 1200px) {
        .builder-container { flex-direction: column; height: auto; }
        .builder-form { height: auto; overflow: visible; padding-right: 0; }
        .preview-container { 
            position: static; 
            width: 100%; 
            min-width: 0; 
            height: auto; 
            padding: 20px 0;
            overflow-x: auto; /* Allow horizontal scroll for paper */
            display: block;
            text-align: center;
        }
        .resume-paper { display: inline-block; text-align: left; }
    }
    
    @media print {
        body * { visibility: hidden; }
        .resume-paper, .resume-paper * { visibility: visible; }
        .resume-paper { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 15mm; box-shadow: none; border: none; }
        .preview-container { background: white; padding: 0; border: none; }
    }
</style>

<div class="container">
    <div class="header-section">
        <div>
            <h1>📄 Resume Builder</h1>
            <p>Create a comprehensive professional resume</p>
        </div>
    </div>

    <!-- Template Selector -->
    <div class="template-selector">
        <div class="template-card active" onclick="setTemplate(1)" data-template="1">
            <h3>📋 Classic</h3>
        </div>
        <div class="template-card" onclick="setTemplate(2)" data-template="2">
            <h3>🎨 Modern</h3>
        </div>
        <div class="template-card" onclick="setTemplate(3)" data-template="3">
            <h3>⚡ Minimal</h3>
        </div>
    </div>

    <div class="builder-container">
        <!-- LEFT: FORM -->
        <div class="builder-form">
            <form method="POST" id="resumeForm">
                <input type="hidden" name="template_id" id="template_id" value="<?php echo $resume['template_id'] ?? 1; ?>">
                
                <!-- JSON Inputs -->
                <input type="hidden" name="education_json" id="education_json">
                <input type="hidden" name="experience_json" id="experience_json">
                <input type="hidden" name="projects_json" id="projects_json">
                <input type="hidden" name="certifications_json" id="certifications_json">
                <input type="hidden" name="achievements_json" id="achievements_json">
                
                <!-- 1. Personal Information -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3>1. Personal Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" id="inp_name" name="name" class="form-control" oninput="updatePreview()" value="<?php echo htmlspecialchars($resume['personal_info']['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" id="inp_email" name="email" class="form-control" oninput="updatePreview()" value="<?php echo htmlspecialchars($resume['personal_info']['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" id="inp_phone" name="phone" class="form-control" oninput="updatePreview()" value="<?php echo htmlspecialchars($resume['personal_info']['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Location (City, State)</label>
                            <input type="text" id="inp_location" name="location" class="form-control" oninput="updatePreview()" value="<?php echo htmlspecialchars($resume['personal_info']['location'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>LinkedIn URL</label>
                            <input type="text" id="inp_linkedin" name="linkedin" class="form-control" oninput="updatePreview()" value="<?php echo htmlspecialchars($resume['personal_info']['linkedin'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>GitHub / Portfolio URL</label>
                            <input type="text" id="inp_github" name="github" class="form-control" oninput="updatePreview()" value="<?php echo htmlspecialchars($resume['personal_info']['github'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- 2. Career Objective -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3>2. Career Objective</h3>
                    <div class="form-group">
                        <label>Short statement about your career goals</label>
                        <textarea id="inp_summary" name="summary" class="form-control" oninput="updatePreview()" style="height: 80px" placeholder="e.g. Motivated CS student seeking internship..."><?php echo htmlspecialchars($resume['personal_info']['summary'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- 3. Education -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3>3. Educational Qualification</h3>
                    <div id="educationContainer"></div>
                    <button type="button" class="btn-add" onclick="addEducation()">+ Add Education</button>
                </div>

                <!-- 4. Skills -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3>4. Skills</h3>
                    <div class="form-group h-auto">
                        <label>Technical Skills (HTML, Java, Python, etc.)</label>
                        <textarea id="inp_skills" name="skills" class="form-control" oninput="updatePreview()"><?php echo is_array($resume['skills'] ?? null) ? htmlspecialchars(implode(', ', $resume['skills'])) : ''; ?></textarea>
                    </div>
                </div>

                <!-- 5. Projects -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3>5. Projects</h3>
                    <div id="projectsContainer"></div>
                    <button type="button" class="btn-add" onclick="addProject()">+ Add Project</button>
                </div>

                <!-- 6. Work Experience -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3>6. Internship / Work Experience</h3>
                    <div id="experienceContainer"></div>
                    <button type="button" class="btn-add" onclick="addExperience()">+ Add Experience</button>
                </div>

                <!-- 7. Certifications -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3>7. Certifications / Courses</h3>
                    <div id="certificationsContainer"></div>
                    <button type="button" class="btn-add" onclick="addCertification()">+ Add Certification</button>
                </div>

                <!-- 8. Achievements -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3>8. Achievements</h3>
                    <div id="achievementsContainer"></div>
                    <button type="button" class="btn-add" onclick="addAchievement()">+ Add Achievement</button>
                </div>

                <!-- 9. Personal Details -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3>9. Personal Details</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="text" id="inp_dob" name="dob" class="form-control" oninput="updatePreview()" value="<?php echo htmlspecialchars($resume['personal_info']['dob'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Languages Known</label>
                            <input type="text" id="inp_languages" name="languages" class="form-control" oninput="updatePreview()" value="<?php echo htmlspecialchars($resume['personal_info']['languages'] ?? ''); ?>">
                        </div>
                        <div class="form-group full-width">
                            <label>Hobbies</label>
                            <input type="text" id="inp_hobbies" name="hobbies" class="form-control" oninput="updatePreview()" value="<?php echo htmlspecialchars($resume['personal_info']['hobbies'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- 10. Declaration -->
                <div class="form-card" style="margin-bottom: 20px;">
                    <h3>10. Declaration</h3>
                    <div class="form-group">
                        <label>Declaration Statement</label>
                        <textarea id="inp_declaration" name="declaration" class="form-control" oninput="updatePreview()">I hereby declare that the above-mentioned information is true to the best of my knowledge.</textarea>
                    </div>
                    <div class="form-group">
                        <label>Name (for signature)</label>
                        <input type="text" id="inp_signature" name="signature" class="form-control" oninput="updatePreview()" value="<?php echo htmlspecialchars($resume['personal_info']['signature'] ?? $resume['personal_info']['name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="action-buttons">
                    <button type="submit" name="save_resume" class="btn-save" onclick="collectFormData()">💾 Save Resume</button>
                    <button type="button" class="btn-preview" onclick="window.print()">🖨️ Print / Download PDF</button>
                </div>
            </form>
        </div>

        <!-- RIGHT: LIVE PREVIEW -->
        <div class="preview-container">
            <h4 style="color: #fff; margin-bottom: 10px; text-align: center;">Live Preview (A4)</h4>
            <div id="resumePreview" class="resume-paper template-1">
                <!-- Header -->
                <div id="prev_header">
                    <h1 id="prev_name">Your Name</h1>
                    <div class="contact-info">
                        <span id="prev_email">email@example.com</span> | 
                        <span id="prev_phone">Phone</span> | 
                        <span id="prev_location">Location</span>
                        <div id="prev_links" style="margin-top: 4px;"></div>
                    </div>
                </div>

                <!-- Career Objective -->
                <div id="prev_summary_sec">
                    <h2>Career Objective</h2>
                    <p id="prev_summary">Your career goal statement...</p>
                </div>

                <!-- Education -->
                <div id="prev_education_sec">
                    <h2>Education</h2>
                    <div id="prev_education_list"></div>
                </div>

                <!-- Skills -->
                <div id="prev_skills_sec">
                    <h2>Skills</h2>
                    <p id="prev_skills">Your skills list...</p>
                </div>

                <!-- Projects -->
                <div id="prev_projects_sec" style="display:none;">
                    <h2>Projects</h2>
                    <div id="prev_projects_list"></div>
                </div>

                <!-- Experience -->
                <div id="prev_experience_sec" style="display:none;">
                    <h2>Work Experience</h2>
                    <div id="prev_experience_list"></div>
                </div>

                 <!-- Certifications -->
                 <div id="prev_cert_sec" style="display:none;">
                    <h2>Certifications</h2>
                    <div id="prev_cert_list"></div>
                </div>

                <!-- Achievements -->
                <div id="prev_achieve_sec" style="display:none;">
                    <h2>Achievements</h2>
                    <ul id="prev_achieve_list" style="margin-top: 5px; margin-bottom: 5px;"></ul>
                </div>

                <!-- Personal Details -->
                <div id="prev_personal_sec">
                    <h2>Personal Details</h2>
                    <div class="personal-details-grid">
                        <div><strong>Date of Birth:</strong> <span id="prev_dob"></span></div>
                        <div><strong>Languages:</strong> <span id="prev_languages"></span></div>
                        <div style="grid-column: span 2;"><strong>Hobbies:</strong> <span id="prev_hobbies"></span></div>
                    </div>
                </div>

                <!-- Declaration -->
                <div class="declaration-sec" id="prev_declaration_sec">
                    <p id="prev_declaration">I hereby declare that...</p>
                    <br>
                    <p style="text-align: right; font-weight: bold;" id="prev_signature">Signature</p>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    let currentTemplate = 1;

    function setTemplate(id) {
        currentTemplate = id;
        document.getElementById('template_id').value = id;
        document.querySelectorAll('.template-card').forEach(c => c.classList.remove('active'));
        document.querySelector(`.template-card[data-template="${id}"]`).classList.add('active');
        const paper = document.getElementById('resumePreview');
        paper.className = `resume-paper template-${id}`;
    }

    function updatePreview() {
        // Basic Info
        document.getElementById('prev_name').innerText = document.getElementById('inp_name').value || 'Your Name';
        document.getElementById('prev_email').innerText = document.getElementById('inp_email').value || 'email';
        document.getElementById('prev_phone').innerText = document.getElementById('inp_phone').value || 'phone';
        document.getElementById('prev_location').innerText = document.getElementById('inp_location').value || 'location';
        
        const summary = document.getElementById('inp_summary').value;
        document.getElementById('prev_summary').innerText = summary || '...';
        document.getElementById('prev_summary_sec').style.display = summary ? 'block' : 'none';

        // Links
        const linkedin = document.getElementById('inp_linkedin').value;
        const github = document.getElementById('inp_github').value;
        let linksHtml = '';
        if(linkedin) linksHtml += `LinkedIn: ${linkedin} &nbsp; `;
        if(github) linksHtml += `GitHub: ${github}`;
        document.getElementById('prev_links').innerHTML = linksHtml;

        // Skills
        const skills = document.getElementById('inp_skills').value;
        document.getElementById('prev_skills').innerText = skills || 'None';

        // Education
        renderEducationPreview();
        
        // Projects
        renderProjectsPreview();

        // Experience
        renderExperiencePreview();
        
        // Certifications
        renderCertPreview();

        // Achievements
        renderAchievePreview();

        // Personal Details
        const dob = document.getElementById('inp_dob').value;
        const lang = document.getElementById('inp_languages').value;
        const hobbies = document.getElementById('inp_hobbies').value;
        
        document.getElementById('prev_dob').innerText = dob;
        document.getElementById('prev_languages').innerText = lang;
        document.getElementById('prev_hobbies').innerText = hobbies;
        document.getElementById('prev_personal_sec').style.display = (dob || lang || hobbies) ? 'block' : 'none';

        // Declaration
        document.getElementById('prev_declaration').innerText = document.getElementById('inp_declaration').value;
        document.getElementById('prev_signature').innerText = document.getElementById('inp_signature').value;
    }

    // --- Dynamic Lists Renderers ---
    function renderEducationPreview() {
        const container = document.getElementById('prev_education_list');
        container.innerHTML = '';
        document.querySelectorAll('#educationContainer .dynamic-section').forEach(sec => {
            const deg = sec.querySelector('.edu-degree').value;
            const inst = sec.querySelector('.edu-institution').value;
            const year = sec.querySelector('.edu-year').value;
            const grade = sec.querySelector('.edu-grade').value;
            
            if (deg || inst) {
                container.innerHTML += `
                    <div class="entry">
                        <div class="entry-header">
                            <span>${deg}</span>
                            <span>${year}</span>
                        </div>
                        <div class="entry-sub">${inst} ${grade ? `| ${grade}` : ''}</div>
                    </div>
                `;
            }
        });
    }

    function renderProjectsPreview() {
        const container = document.getElementById('prev_projects_list');
        container.innerHTML = '';
        let hasProjects = false;
        document.querySelectorAll('#projectsContainer .dynamic-section').forEach(sec => {
            const title = sec.querySelector('.proj-title').value;
            const tech = sec.querySelector('.proj-tech').value;
            const role = sec.querySelector('.proj-role').value;
            const desc = sec.querySelector('.proj-desc').value;
            
            if (title) {
                hasProjects = true;
                container.innerHTML += `
                    <div class="entry">
                        <div class="entry-header">
                            <span>${title}</span>
                            <span>${role}</span>
                        </div>
                        <div class="entry-sub">Tech: ${tech}</div>
                        <div class="entry-desc">${desc}</div>
                    </div>
                `;
            }
        });
        document.getElementById('prev_projects_sec').style.display = hasProjects ? 'block' : 'none';
    }

    function renderExperiencePreview() {
        const container = document.getElementById('prev_experience_list');
        container.innerHTML = '';
        let hasExp = false;
        document.querySelectorAll('#experienceContainer .dynamic-section').forEach(sec => {
            const company = sec.querySelector('.exp-company').value;
            const duration = sec.querySelector('.exp-duration').value;
            const resp = sec.querySelector('.exp-resp').value;
            
            if (company) {
                hasExp = true;
                container.innerHTML += `
                    <div class="entry">
                        <div class="entry-header">
                            <span>${company}</span>
                            <span>${duration}</span>
                        </div>
                        <div class="entry-desc">${resp}</div>
                    </div>
                `;
            }
        });
        document.getElementById('prev_experience_sec').style.display = hasExp ? 'block' : 'none';
    }

    function renderCertPreview() {
        const container = document.getElementById('prev_cert_list');
        container.innerHTML = '';
        let hasCert = false;
        document.querySelectorAll('#certificationsContainer .dynamic-section').forEach(sec => {
            const name = sec.querySelector('.cert-name').value;
            const inst = sec.querySelector('.cert-inst').value;
            const year = sec.querySelector('.cert-year').value;
            
            if (name) {
                hasCert = true;
                container.innerHTML += `
                    <div class="entry">
                        <div class="entry-header">
                            <span>${name}</span>
                            <span>${year}</span>
                        </div>
                        <div class="entry-sub">${inst}</div>
                    </div>
                `;
            }
        });
        document.getElementById('prev_cert_sec').style.display = hasCert ? 'block' : 'none';
    }

    function renderAchievePreview() {
        const container = document.getElementById('prev_achieve_list');
        container.innerHTML = '';
        let hasAchieve = false;
        document.querySelectorAll('#achievementsContainer .dynamic-section').forEach(sec => {
            const text = sec.querySelector('.achieve-text').value;
            if (text) {
                hasAchieve = true;
                container.innerHTML += `<li>${text}</li>`;
            }
        });
        document.getElementById('prev_achieve_sec').style.display = hasAchieve ? 'block' : 'none';
    }


    // --- Add Remove Helpers ---
    function createField(html) {
        const div = document.createElement('div');
        div.className = 'dynamic-section';
        div.innerHTML = html + '<button type="button" class="btn-remove" onclick="this.parentElement.remove(); updatePreview()">Remove</button>';
        return div;
    }

    function addEducation(data = {}) {
        const html = `
            <input type="text" placeholder="Degree / Course" class="form-control edu-degree" value="${data.degree || ''}" oninput="updatePreview()" style="margin-bottom: 5px;">
            <input type="text" placeholder="College / University" class="form-control edu-institution" value="${data.institution || ''}" oninput="updatePreview()" style="margin-bottom: 5px;">
            <div style="display:flex; gap:10px;">
                <input type="text" placeholder="Year" class="form-control edu-year" value="${data.year || ''}" oninput="updatePreview()">
                 <input type="text" placeholder="CGPA / %" class="form-control edu-grade" value="${data.grade || ''}" oninput="updatePreview()">
            </div>
        `;
        document.getElementById('educationContainer').appendChild(createField(html));
        updatePreview();
    }

    function addProject(data = {}) {
        const html = `
            <input type="text" placeholder="Project Title" class="form-control proj-title" value="${data.title || ''}" oninput="updatePreview()" style="margin-bottom: 5px;">
            <input type="text" placeholder="Technologies Used" class="form-control proj-tech" value="${data.tech || ''}" oninput="updatePreview()" style="margin-bottom: 5px;">
            <input type="text" placeholder="Your Role" class="form-control proj-role" value="${data.role || ''}" oninput="updatePreview()" style="margin-bottom: 5px;">
            <textarea placeholder="Short Description" class="form-control proj-desc" oninput="updatePreview()">${data.desc || ''}</textarea>
        `;
        document.getElementById('projectsContainer').appendChild(createField(html));
        updatePreview();
    }

    function addExperience(data = {}) {
        const html = `
            <input type="text" placeholder="Company Name" class="form-control exp-company" value="${data.company || ''}" oninput="updatePreview()" style="margin-bottom: 5px;">
            <input type="text" placeholder="Duration" class="form-control exp-duration" value="${data.duration || ''}" oninput="updatePreview()" style="margin-bottom: 5px;">
            <textarea placeholder="Work Responsibilities" class="form-control exp-resp" oninput="updatePreview()">${data.resp || ''}</textarea>
        `;
        document.getElementById('experienceContainer').appendChild(createField(html));
        updatePreview();
    }

    function addCertification(data = {}) {
        const html = `
            <input type="text" placeholder="Course Name" class="form-control cert-name" value="${data.name || ''}" oninput="updatePreview()" style="margin-bottom: 5px;">
            <input type="text" placeholder="Platform / Institute" class="form-control cert-inst" value="${data.inst || ''}" oninput="updatePreview()" style="margin-bottom: 5px;">
            <input type="text" placeholder="Year" class="form-control cert-year" value="${data.year || ''}" oninput="updatePreview()">
        `;
        document.getElementById('certificationsContainer').appendChild(createField(html));
        updatePreview();
    }

    function addAchievement(text = '') {
        const html = `
            <input type="text" placeholder="Award / Competition / Hackathon" class="form-control achieve-text" value="${text}" oninput="updatePreview()">
        `;
        document.getElementById('achievementsContainer').appendChild(createField(html));
        updatePreview();
    }

    function collectFormData() {
        // Collect JSONs
        function col(containerId, selectorMap) {
            const arr = [];
            document.querySelectorAll(`#${containerId} .dynamic-section`).forEach(sec => {
                const obj = {};
                for (const [key, cls] of Object.entries(selectorMap)) {
                    obj[key] = sec.querySelector(cls)?.value || '';
                }
                arr.push(obj);
            });
            return JSON.stringify(arr);
        }

        document.getElementById('education_json').value = col('educationContainer', {degree: '.edu-degree', institution: '.edu-institution', year: '.edu-year', grade: '.edu-grade'});
        document.getElementById('projects_json').value = col('projectsContainer', {title: '.proj-title', tech: '.proj-tech', role: '.proj-role', desc: '.proj-desc'});
        document.getElementById('experience_json').value = col('experienceContainer', {company: '.exp-company', duration: '.exp-duration', resp: '.exp-resp'});
        document.getElementById('certifications_json').value = col('certificationsContainer', {name: '.cert-name', inst: '.cert-inst', year: '.cert-year'});
        
        // Achievements (flat array)
        const ach = [];
        document.querySelectorAll('#achievementsContainer .achieve-text').forEach(el => {
            if(el.value) ach.push(el.value);
        });
        document.getElementById('achievements_json').value = JSON.stringify(ach);
    }

    // --- Init Data ---
    // Education
    <?php if (!empty($resume['education'])): ?>
        <?php foreach ($resume['education'] as $e): ?>
            addEducation({
                degree: '<?php echo addslashes($e['degree'] ?? ''); ?>',
                institution: '<?php echo addslashes($e['institution'] ?? ''); ?>',
                year: '<?php echo addslashes($e['year'] ?? ''); ?>',
                grade: '<?php echo addslashes($e['grade'] ?? ''); ?>'
            });
        <?php endforeach; ?>
    <?php else: ?>
        addEducation();
    <?php endif; ?>

     // Projects
     <?php if (!empty($resume['projects'])): ?>
        <?php foreach ($resume['projects'] as $p): ?>
            addProject({
                title: '<?php echo addslashes($p['title'] ?? ''); ?>',
                tech: '<?php echo addslashes($p['tech'] ?? ''); ?>',
                role: '<?php echo addslashes($p['role'] ?? ''); ?>',
                desc: '<?php echo addslashes($p['desc'] ?? ''); ?>'
            });
        <?php endforeach; ?>
    <?php else: ?>
        addProject();
    <?php endif; ?>

     // Experience
     <?php if (!empty($resume['experience'])): ?>
        <?php foreach ($resume['experience'] as $e): ?>
            addExperience({
                company: '<?php echo addslashes($e['company'] ?? ''); ?>',
                duration: '<?php echo addslashes($e['duration'] ?? ''); ?>',
                resp: '<?php echo addslashes($e['resp'] ?? ''); ?>'
            });
        <?php endforeach; ?>
    <?php endif; ?> // No default

    // Certifications
     <?php if (!empty($resume['certifications'])): ?>
        <?php foreach ($resume['certifications'] as $c): ?>
            addCertification({
                name: '<?php echo addslashes($c['name'] ?? ''); ?>',
                inst: '<?php echo addslashes($c['inst'] ?? ''); ?>',
                year: '<?php echo addslashes($c['year'] ?? ''); ?>'
            });
        <?php endforeach; ?>
    <?php endif; ?>

    // Achievements
     <?php 
     $achievements = $resume['personal_info']['achievements'] ?? [];
     if (!empty($achievements)): 
        foreach ($achievements as $a): ?>
            addAchievement('<?php echo addslashes($a); ?>');
        <?php endforeach; ?>
    <?php endif; ?>

    setTemplate(<?php echo $resume['template_id'] ?? 1; ?>);
    updatePreview(); 
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
