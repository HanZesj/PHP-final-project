/**
 * Automated Electronic Voting System - JavaScript
 * Handles client-side interactions and security
 */

// Application state
const VotingApp = {
    selectedCandidate: null,
    sessionTimeout: null,
    
    // Initialize application
    init() {
        this.setupSessionTimeout();
        this.setupFormValidation();
        this.setupCandidateSelection();
        this.setupModalHandlers();
        this.setupRealTimeUpdates();
        this.setupSecurityFeatures();
    },
    
    // Session timeout management
    setupSessionTimeout() {
        // Check session status every minute
        setInterval(() => {
            this.checkSessionStatus();
        }, 60000);
        
        // Warn user 5 minutes before timeout
        this.sessionTimeout = setTimeout(() => {
            this.showSessionWarning();
        }, 25 * 60 * 1000); // 25 minutes
    },
    
    checkSessionStatus() {
        fetch('api/session_check.php')
            .then(response => response.json())
            .then(data => {
                if (!data.valid) {
                    this.handleSessionExpired();
                } else if (data.warning) {
                    this.showSessionWarning();
                }
            })
            .catch(error => {
                console.error('Session check failed:', error);
            });
    },
    
    showSessionWarning() {
        const warning = document.createElement('div');
        warning.className = 'alert alert-warning session-warning';
        warning.innerHTML = `
            <strong>Session Warning:</strong> Your session will expire in 5 minutes. 
            <button onclick="VotingApp.extendSession()" class="btn btn-sm btn-primary">Extend Session</button>
        `;
        document.body.insertBefore(warning, document.body.firstChild);
        
        setTimeout(() => {
            warning.remove();
        }, 30000);
    },
    
    extendSession() {
        fetch('api/extend_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelector('.session-warning')?.remove();
                this.showAlert('Session extended successfully', 'success');
            }
        });
    },
    
    handleSessionExpired() {
        this.showAlert('Your session has expired. Please log in again.', 'danger');
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 3000);
    },
    
    // Form validation
    setupFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
            
            // Real-time validation
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('blur', () => {
                    this.validateField(input);
                });
            });
        });
    },
    
    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    },
    
    validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';
        
        // Remove existing error
        this.clearFieldError(field);
        
        // Required validation
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            message = 'This field is required';
        }
        
        // Email validation
        if (field.type === 'email' && value && !this.isValidEmail(value)) {
            isValid = false;
            message = 'Please enter a valid email address';
        }
        
        // Password validation
        if (field.type === 'password' && value && value.length < 6) {
            isValid = false;
            message = 'Password must be at least 6 characters long';
        }
        
        // Username validation
        if (field.name === 'username' && value && !/^[a-zA-Z0-9_]{3,50}$/.test(value)) {
            isValid = false;
            message = 'Username must be 3-50 characters with letters, numbers, and underscores only';
        }
        
        // Confirm password validation
        if (field.name === 'confirm_password' && value) {
            const password = document.querySelector('input[name="password"]');
            if (password && value !== password.value) {
                isValid = false;
                message = 'Passwords do not match';
            }
        }
        
        if (!isValid) {
            this.showFieldError(field, message);
        }
        
        return isValid;
    },
    
    showFieldError(field, message) {
        field.classList.add('is-invalid');
        const error = document.createElement('div');
        error.className = 'invalid-feedback';
        error.textContent = message;
        field.parentNode.appendChild(error);
    },
    
    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const error = field.parentNode.querySelector('.invalid-feedback');
        if (error) {
            error.remove();
        }
    },
    
    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },
    
    // Candidate selection for voting
    setupCandidateSelection() {
        const candidateCards = document.querySelectorAll('.candidate-card');
        candidateCards.forEach(card => {
            card.addEventListener('click', () => {
                this.selectCandidate(card);
            });
        });
        
        // Vote submission
        const voteForm = document.getElementById('voteForm');
        if (voteForm) {
            voteForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitVote();
            });
        }
    },
    
    selectCandidate(card) {
        // Clear previous selection
        document.querySelectorAll('.candidate-card').forEach(c => {
            c.classList.remove('selected');
        });
        
        // Select new candidate
        card.classList.add('selected');
        this.selectedCandidate = card.dataset.candidateId;
        
        // Enable vote button
        const voteButton = document.getElementById('voteButton');
        if (voteButton) {
            voteButton.disabled = false;
            voteButton.textContent = `Vote for ${card.querySelector('.candidate-name').textContent}`;
        }
    },
    
    submitVote() {
        if (!this.selectedCandidate) {
            this.showAlert('Please select a candidate', 'warning');
            return;
        }
        
        if (!confirm('Are you sure you want to cast your vote? This action cannot be undone.')) {
            return;
        }
        
        const voteButton = document.getElementById('voteButton');
        const originalText = voteButton.textContent;
        voteButton.disabled = true;
        voteButton.innerHTML = '<span class="loading"></span> Casting Vote...';
        
        fetch('api/cast_vote.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                election_id: document.getElementById('electionId').value,
                candidate_id: this.selectedCandidate
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert(data.message, 'success');
                setTimeout(() => {
                    window.location.href = 'results.php?id=' + document.getElementById('electionId').value;
                }, 2000);
            } else {
                this.showAlert(data.message, 'danger');
                voteButton.disabled = false;
                voteButton.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Vote submission failed:', error);
            this.showAlert('Failed to submit vote. Please try again.', 'danger');
            voteButton.disabled = false;
            voteButton.textContent = originalText;
        });
    },
    
    // Modal handlers
    setupModalHandlers() {
        // Open modal
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-modal-target]')) {
                const modalId = e.target.getAttribute('data-modal-target');
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'block';
                }
            }
        });
        
        // Close modal
        document.addEventListener('click', (e) => {
            if (e.target.matches('.modal-close') || e.target.matches('.modal')) {
                const modal = e.target.closest('.modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal[style*="block"]');
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
    },
    
    // Real-time updates for active elections
    setupRealTimeUpdates() {
        if (document.querySelector('.election-results') || document.querySelector('.admin-dashboard')) {
            setInterval(() => {
                this.updateResults();
            }, 30000); // Update every 30 seconds
        }
    },
    
    updateResults() {
        const electionId = document.getElementById('electionId')?.value;
        if (!electionId) return;
        
        fetch(`api/get_results.php?id=${electionId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateResultsDisplay(data);
                }
            })
            .catch(error => {
                console.error('Failed to update results:', error);
            });
    },
    
    updateResultsDisplay(data) {
        data.candidates.forEach(candidate => {
            const progressBar = document.querySelector(`[data-candidate-id="${candidate.id}"] .progress-bar`);
            const voteCount = document.querySelector(`[data-candidate-id="${candidate.id}"] .vote-count`);
            const percentage = document.querySelector(`[data-candidate-id="${candidate.id}"] .percentage`);
            
            if (progressBar) {
                progressBar.style.width = `${candidate.percentage}%`;
            }
            if (voteCount) {
                voteCount.textContent = candidate.vote_count;
            }
            if (percentage) {
                percentage.textContent = `${candidate.percentage}%`;
            }
        });
        
        // Update total votes
        const totalVotes = document.querySelector('.total-votes');
        if (totalVotes) {
            totalVotes.textContent = data.total_votes;
        }
    },
    
    // Security features
    setupSecurityFeatures() {
        // Disable right-click in voting areas
        document.querySelectorAll('.voting-area').forEach(area => {
            area.addEventListener('contextmenu', (e) => {
                e.preventDefault();
            });
        });
        
        // Detect tab switching (potential cheating detection)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && document.querySelector('.voting-area')) {
                console.log('User switched tabs during voting');
                // Could log this event for security monitoring
            }
        });
        
        // Prevent form resubmission
        if (performance.navigation.type === 1) {
            // Page was refreshed
            const forms = document.querySelectorAll('form[data-no-refresh]');
            forms.forEach(form => {
                const inputs = form.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (input.type !== 'hidden') {
                        input.value = '';
                    }
                });
            });
        }
    },
    
    // Utility functions
    showAlert(message, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        
        const container = document.querySelector('.main-content') || document.body;
        container.insertBefore(alert, container.firstChild);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    },
    
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    },
    
    // Admin functions
    deleteElection(id) {
        if (!confirm('Are you sure you want to delete this election? This action cannot be undone.')) {
            return;
        }
        
        fetch('api/delete_election.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ election_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert(data.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                this.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Delete failed:', error);
            this.showAlert('Failed to delete election', 'danger');
        });
    },
    
    deleteCandidate(id) {
        if (!confirm('Are you sure you want to delete this candidate?')) {
            return;
        }
        
        fetch('api/delete_candidate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ candidate_id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showAlert(data.message, 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                this.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Delete failed:', error);
            this.showAlert('Failed to delete candidate', 'danger');
        });
    }
};

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    VotingApp.init();
});

// Global functions for HTML onclick handlers
function selectCandidate(card) {
    VotingApp.selectCandidate(card);
}

function submitVote() {
    VotingApp.submitVote();
}

function deleteElection(id) {
    VotingApp.deleteElection(id);
}

function deleteCandidate(id) {
    VotingApp.deleteCandidate(id);
}
