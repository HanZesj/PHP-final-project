<?php
/**
 * Candidate Management Class
 * Handles candidate registration and management for elections
 */

class Candidate {
    
    private $db;
    
    public function __construct($connection = null) {
        $this->db = $connection ?: getDB();
    }
    
    /**
     * Add candidate to election (Admin only)
     */
    public function addCandidate($data) {
        try {
            if (!Security::isAdmin()) {
                throw new Exception("Access denied. Admin privileges required.");
            }
            
            // Validate required fields
            if (empty($data['election_id']) || empty($data['name'])) {
                throw new Exception("Election ID and candidate name are required.");
            }
            
            // Sanitize inputs
            $electionId = (int)$data['election_id'];
            $name = Security::sanitizeInput($data['name']);
            $party = Security::sanitizeInput($data['party'] ?? '');
            $description = Security::sanitizeInput($data['description'] ?? '');
            $photoUrl = Security::sanitizeInput($data['photo_url'] ?? '');
            
            // Validate election exists
            $stmt = $this->db->prepare("SELECT id FROM elections WHERE id = ?");
            $stmt->execute([$electionId]);
            if (!$stmt->fetch()) {
                throw new Exception("Election not found.");
            }
            
            // Check if candidate name already exists in this election
            $stmt = $this->db->prepare("SELECT id FROM candidates WHERE election_id = ? AND name = ?");
            $stmt->execute([$electionId, $name]);
            if ($stmt->fetch()) {
                throw new Exception("A candidate with this name already exists in this election.");
            }
            
            // Insert candidate
            $stmt = $this->db->prepare("INSERT INTO candidates (election_id, name, party, description, photo_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$electionId, $name, $party, $description, $photoUrl]);
            
            $candidateId = $this->db->lastInsertId();
            
            Security::logEvent('CANDIDATE_ADDED', "Candidate added: {$name} to election {$electionId}");
            
            return [
                'success' => true,
                'message' => 'Candidate added successfully.',
                'candidate_id' => $candidateId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get candidates for an election
     */
    public function getCandidatesByElection($electionId) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, COUNT(v.id) as vote_count
                FROM candidates c
                LEFT JOIN votes v ON c.id = v.candidate_id
                WHERE c.election_id = ?
                GROUP BY c.id
                ORDER BY c.name ASC
            ");
            $stmt->execute([$electionId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting candidates: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get candidate by ID
     */
    public function getCandidateById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, e.title as election_title, COUNT(v.id) as vote_count
                FROM candidates c
                LEFT JOIN elections e ON c.election_id = e.id
                LEFT JOIN votes v ON c.id = v.candidate_id
                WHERE c.id = ?
                GROUP BY c.id
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error getting candidate: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update candidate information (Admin only)
     */
    public function updateCandidate($id, $data) {
        try {
            if (!Security::isAdmin()) {
                throw new Exception("Access denied. Admin privileges required.");
            }
            
            // Validate candidate exists
            $candidate = $this->getCandidateById($id);
            if (!$candidate) {
                throw new Exception("Candidate not found.");
            }
            
            // Sanitize inputs
            $name = Security::sanitizeInput($data['name'] ?? $candidate['name']);
            $party = Security::sanitizeInput($data['party'] ?? $candidate['party']);
            $description = Security::sanitizeInput($data['description'] ?? $candidate['description']);
            $photoUrl = Security::sanitizeInput($data['photo_url'] ?? $candidate['photo_url']);
            
            // Check if name already exists (excluding current candidate)
            $stmt = $this->db->prepare("SELECT id FROM candidates WHERE election_id = ? AND name = ? AND id != ?");
            $stmt->execute([$candidate['election_id'], $name, $id]);
            if ($stmt->fetch()) {
                throw new Exception("A candidate with this name already exists in this election.");
            }
            
            // Update candidate
            $stmt = $this->db->prepare("UPDATE candidates SET name = ?, party = ?, description = ?, photo_url = ? WHERE id = ?");
            $stmt->execute([$name, $party, $description, $photoUrl, $id]);
            
            Security::logEvent('CANDIDATE_UPDATED', "Candidate {$id} updated");
            
            return [
                'success' => true,
                'message' => 'Candidate updated successfully.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete candidate (Admin only)
     */
    public function deleteCandidate($id) {
        try {
            if (!Security::isAdmin()) {
                throw new Exception("Access denied. Admin privileges required.");
            }
            
            // Check if candidate has votes
            $stmt = $this->db->prepare("SELECT COUNT(*) as vote_count FROM votes WHERE candidate_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            
            if ($result['vote_count'] > 0) {
                throw new Exception("Cannot delete candidate with existing votes.");
            }
            
            $stmt = $this->db->prepare("DELETE FROM candidates WHERE id = ?");
            $stmt->execute([$id]);
            
            Security::logEvent('CANDIDATE_DELETED', "Candidate {$id} deleted");
            
            return [
                'success' => true,
                'message' => 'Candidate deleted successfully.'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get candidate statistics
     */
    public function getCandidateStats($electionId = null) {
        try {
            $query = "
                SELECT 
                    e.title as election_title,
                    c.name as candidate_name,
                    c.party,
                    COUNT(v.id) as vote_count,
                    ROUND((COUNT(v.id) / (SELECT COUNT(*) FROM votes WHERE election_id = c.election_id)) * 100, 2) as percentage
                FROM candidates c
                LEFT JOIN elections e ON c.election_id = e.id
                LEFT JOIN votes v ON c.id = v.candidate_id
            ";
            
            $params = [];
            if ($electionId) {
                $query .= " WHERE c.election_id = ?";
                $params[] = $electionId;
            }
            
            $query .= " GROUP BY c.id ORDER BY vote_count DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Error getting candidate stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if candidate belongs to election
     */
    public function belongsToElection($candidateId, $electionId) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM candidates WHERE id = ? AND election_id = ?");
            $stmt->execute([$candidateId, $electionId]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("Error checking candidate election: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get election results
     */
    public function getElectionResults($electionId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.id,
                    c.name,
                    c.party,
                    c.description,
                    c.vote_count,
                    COUNT(v.id) as actual_votes
                FROM candidates c
                LEFT JOIN votes v ON c.id = v.candidate_id
                WHERE c.election_id = ?
                GROUP BY c.id
                ORDER BY c.vote_count DESC, c.name ASC
            ");
            $stmt->execute([$electionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting election results: " . $e->getMessage());
            return [];
        }
    }
}
?>
