// Main Application JavaScript
class TicketSystem {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadUserSession();
        this.animateElements();
    }

    bindEvents() {
        // Category selection
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', (e) => {
                const category = e.currentTarget.getAttribute('data-category');
                this.selectCategory(category);
            });
        });

        // Form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit(form);
            });
        });

        // Navigation
        this.setupNavigation();
    }

    selectCategory(category) {
        // Add visual feedback
        document.querySelectorAll('.category-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        event.target.closest('.category-card').classList.add('selected');
        
        // Redirect to specific form
        setTimeout(() => {
            this.redirectToForm(category);
        }, 300);
    }

    redirectToForm(category) {
        const formPages = {
            'PADE': 'forms/pade-form.html',
            'META': 'forms/meta-form.html',
            'ENCARTEIRAMENTO': 'forms/encarteiramento-form.html'
        };
        
        if (formPages[category]) {
            window.location.href = formPages[category];
        }
    }

    handleFormSubmit(form) {
        const formData = new FormData(form);
        const formType = form.getAttribute('data-form-type');
        
        // Show loading state
        this.showLoading(form);
        
        // Submit to backend
        this.submitForm(formData, formType)
            .then(response => {
                if (response.success) {
                    this.showSuccess('Ticket submitted successfully!');
                    this.redirectToTicket(response.ticketId);
                } else {
                    this.showError('Error submitting ticket. Please try again.');
                }
            })
            .catch(error => {
                this.showError('Network error. Please check your connection.');
            })
            .finally(() => {
                this.hideLoading(form);
            });
    }

    async submitForm(formData, formType) {
        try {
            const response = await fetch('api/submit-ticket.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    formType: formType,
                    data: Object.fromEntries(formData)
                })
            });
            
            return await response.json();
        } catch (error) {
            throw new Error('Network error');
        }
    }

    showLoading(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        }
    }

    hideLoading(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Ticket';
        }
    }

    showSuccess(message) {
        this.showAlert(message, 'success');
    }

    showError(message) {
        this.showAlert(message, 'danger');
    }

    showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        alertDiv.style.zIndex = '9999';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    }

    redirectToTicket(ticketId) {
        setTimeout(() => {
            window.location.href = `ticket-detail.html?id=${ticketId}`;
        }, 2000);
    }

    loadUserSession() {
        const user = localStorage.getItem('currentUser');
        if (user) {
            const userData = JSON.parse(user);
            this.updateUserInterface(userData);
        }
    }

    updateUserInterface(userData) {
        const userElements = document.querySelectorAll('[data-user-info]');
        userElements.forEach(element => {
            const info = element.getAttribute('data-user-info');
            if (userData[info]) {
                element.textContent = userData[info];
            }
        });

        // Show/hide elements based on user role
        const roleElements = document.querySelectorAll('[data-role]');
        roleElements.forEach(element => {
            const allowedRoles = element.getAttribute('data-role').split(',');
            if (allowedRoles.includes(userData.role)) {
                element.style.display = 'block';
            } else {
                element.style.display = 'none';
            }
        });
    }

    setupNavigation() {
        // Handle navigation links
        document.querySelectorAll('[data-nav]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const target = e.target.getAttribute('data-nav');
                this.navigate(target);
            });
        });
    }

    navigate(target) {
        const pages = {
            'home': 'index.html',
            'dashboard': 'dashboard.html',
            'tickets': 'tickets.html',
            'profile': 'profile.html',
            'admin': 'admin.html'
        };
        
        if (pages[target]) {
            window.location.href = pages[target];
        }
    }

    animateElements() {
        // Add animation classes to elements as they become visible
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in-up');
                }
            });
        });

        document.querySelectorAll('.category-card, .glass-card, .form-glass').forEach(el => {
            observer.observe(el);
        });
    }

    // Utility methods
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    validateForm(form) {
        const required = form.querySelectorAll('[required]');
        let isValid = true;
        
        required.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, 'This field is required');
                isValid = false;
            } else {
                this.clearFieldError(field);
            }
        });
        
        return isValid;
    }

    showFieldError(field, message) {
        this.clearFieldError(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback d-block';
        errorDiv.textContent = message;
        
        field.classList.add('is-invalid');
        field.parentNode.appendChild(errorDiv);
    }

    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const errorDiv = field.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
}

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new TicketSystem();
});

// Export for use in other files
window.TicketSystem = TicketSystem;