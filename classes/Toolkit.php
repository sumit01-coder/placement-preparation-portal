<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Toolkit {
    private $db;
    private $uploadDir;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->uploadDir = __DIR__ . '/../uploads/documents/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Save resume data
     */
    public function saveResume($userId, $resumeData) {
        try {
            // Check if resume exists
            $existing = $this->db->fetchOne(
                "SELECT resume_id FROM resumes WHERE user_id = :user_id",
                ['user_id' => $userId]
            );
            
            if ($existing) {
                // Update existing resume
                $query = "UPDATE resumes SET 
                          personal_info = :personal_info,
                          education = :education,
                          experience = :experience,
                          skills = :skills,
                          projects = :projects,
                          certifications = :certifications,
                          template_id = :template_id,
                          updated_at = NOW()
                          WHERE user_id = :user_id";
            } else {
                // Insert new resume
                $query = "INSERT INTO resumes 
                          (user_id, personal_info, education, experience, skills, projects, certifications, template_id, created_at)
                          VALUES (:user_id, :personal_info, :education, :experience, :skills, :projects, :certifications, :template_id, NOW())";
            }
            
            $this->db->query($query, [
                'user_id' => $userId,
                'personal_info' => json_encode($resumeData['personal_info']),
                'education' => json_encode($resumeData['education']),
                'experience' => json_encode($resumeData['experience']),
                'skills' => json_encode($resumeData['skills']),
                'projects' => json_encode($resumeData['projects']),
                'certifications' => json_encode($resumeData['certifications']),
                'template_id' => $resumeData['template_id'] ?? 1
            ]);
            
            return ['success' => true, 'message' => 'Resume saved successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to save resume'];
        }
    }
    
    /**
     * Get user resume
     */
    public function getResume($userId) {
        $resume = $this->db->fetchOne(
            "SELECT * FROM resumes WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        
        if ($resume) {
            // Decode JSON fields
            $resume['personal_info'] = json_decode($resume['personal_info'], true);
            $resume['education'] = json_decode($resume['education'], true);
            $resume['experience'] = json_decode($resume['experience'], true);
            $resume['skills'] = json_decode($resume['skills'], true);
            $resume['projects'] = json_decode($resume['projects'], true);
            $resume['certifications'] = json_decode($resume['certifications'], true);
        }
        
        return $resume;
    }
    
    /**
     * Upload document
     */
    public function uploadDocument($userId, $file, $category, $description = '') {
        try {
            $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedTypes)) {
                return ['success' => false, 'message' => 'Invalid file type'];
            }
            
            // Generate unique filename
            $fileName = time() . '_' . $userId . '_' . uniqid() . '.' . $fileExtension;
            $filePath = $this->uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Save to database
                $query = "INSERT INTO user_documents 
                          (user_id, file_name, file_path, file_size, category, description, uploaded_at)
                          VALUES (:user_id, :file_name, :file_path, :file_size, :category, :description, NOW())";
                
                $this->db->query($query, [
                    'user_id' => $userId,
                    'file_name' => $file['name'],
                    'file_path' => $fileName,
                    'file_size' => $file['size'],
                    'category' => $category,
                    'description' => $description
                ]);
                
                return ['success' => true, 'message' => 'Document uploaded successfully'];
            }
            
            return ['success' => false, 'message' => 'Failed to upload file'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get user documents
     */
    public function getUserDocuments($userId, $category = null) {
        $whereClause = "WHERE user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($category) {
            $whereClause .= " AND category = :category";
            $params['category'] = $category;
        }
        
        $query = "SELECT * FROM user_documents $whereClause ORDER BY uploaded_at DESC";
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Delete document
     */
    public function deleteDocument($documentId, $userId) {
        try {
            // Get file info
            $doc = $this->db->fetchOne(
                "SELECT file_path FROM user_documents WHERE document_id = :doc_id AND user_id = :user_id",
                ['doc_id' => $documentId, 'user_id' => $userId]
            );
            
            if ($doc) {
                // Delete physical file
                $filePath = $this->uploadDir . $doc['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Delete from database
                $this->db->delete('user_documents', 'document_id = :doc_id', ['doc_id' => $documentId]);
                
                return ['success' => true, 'message' => 'Document deleted successfully'];
            }
            
            return ['success' => false, 'message' => 'Document not found'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to delete document'];
        }
    }
    
    /**
     * Get document stats
     */
    public function getDocumentStats($userId) {
        return $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN category = 'resume' THEN 1 ELSE 0 END) as resumes,
                SUM(CASE WHEN category = 'certificate' THEN 1 ELSE 0 END) as certificates,
                SUM(CASE WHEN category = 'other' THEN 1 ELSE 0 END) as others,
                SUM(file_size) as total_size
             FROM user_documents
             WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
    }
}
