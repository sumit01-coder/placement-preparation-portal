/* Focus Mode Tracker - Detects tab switching and distractions */

class FocusTracker {
    constructor(sessionId) {
        this.sessionId = sessionId;
        this.violations = [];
        this.isActive = false;
        this.init();
    }
    
    init() {
        // Track tab visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.isActive) {
                this.logViolation('tab_switch');
            }
        });
        
        // Track window blur (switching to another window)
        window.addEventListener('blur', () => {
            if (this.isActive) {
                this.logViolation('window_blur');
            }
        });
        
        // Track copy/paste attempts
        document.addEventListener('copy', (e) => {
            if (this.isActive) {
                this.logViolation('copy_paste');
            }
        });
        
        document.addEventListener('paste', (e) => {
            if (this.isActive) {
                this.logViolation('copy_paste');
            }
        });
        
        // Track right-click
        document.addEventListener('contextmenu', (e) => {
            if (this.isActive) {
                this.logViolation('right_click');
                e.preventDefault();
            }
        });
        
        // Track devtools open (F12)
        document.addEventListener('keydown', (e) => {
            if (this.isActive && e.keyCode === 123) { // F12
                this.logViolation('devtools');
                e.preventDefault();
            }
        });
    }
    
    start() {
        this.isActive = true;
        this.showNotification('Focus mode activated!', 'success');
    }
    
    stop() {
        this.isActive = false;
        this.showNotification(`Focus session ended. Violations: ${this.violations.length}`, 'info');
    }
    
    async logViolation(type) {
        this.violations.push({
            type: type,
            timestamp: new Date().toISOString()
        });
        
        this.showNotification(`⚠️ Distraction detected: ${type.replace('_', ' ')}`, 'warning');
        
        // Send to server
        try {
            await fetch('../../api/focus-track.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    session_id: this.sessionId,
                    violation_type: type
                })
            });
        } catch (error) {
            console.error('Failed to log violation:', error);
        }
    }
    
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `focus-notification ${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${type === 'warning' ? '#f59e0b' : type === 'success' ? '#10b981' : '#667eea'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    getViolationCount() {
        return this.violations.length;
    }
    
    getFocusScore() {
        const maxViolations = 20;
        const violationPenalty = 5; // 5% per violation
        const score = Math.max(0, 100 - (this.violations.length * violationPenalty));
        return score.toFixed(2);
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
