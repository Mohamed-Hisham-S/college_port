// Main JavaScript for the College Portal

document.addEventListener('DOMContentLoaded', function() {
    // Toggle mobile sidebar
    const sidebarToggle = document.createElement('button');
    sidebarToggle.innerHTML = '☰';
    sidebarToggle.className = 'sidebar-toggle';
    sidebarToggle.style.cssText = `
        display: none;
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1000;
        background: var(--primary);
        color: white;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        font-size: 18px;
        cursor: pointer;
    `;
    
    if (window.innerWidth <= 992) {
        document.body.appendChild(sidebarToggle);
        const sidebar = document.querySelector('.sidebar');
        sidebar.style.left = '-250px';
        sidebar.style.position = 'fixed';
        sidebar.style.zIndex = '999';
        sidebar.style.height = '100vh';
        sidebar.style.transition = 'left 0.3s';
        
        sidebarToggle.addEventListener('click', function() {
            if (sidebar.style.left === '-250px') {
                sidebar.style.left = '0';
            } else {
                sidebar.style.left = '-250px';
            }
        });
    }
    
    // Message read status
    const messageItems = document.querySelectorAll('.message-item');
    messageItems.forEach(item => {
        item.addEventListener('click', function() {
            if (this.classList.contains('unread')) {
                this.classList.remove('unread');
                // In real implementation, this would send an AJAX request to mark as read
            }
        });
    });
    
    // Shortlist student functionality
    const shortlistButtons = document.querySelectorAll('.shortlist-btn');
    shortlistButtons.forEach(button => {
        button.addEventListener('click', function() {
            const studentId = this.getAttribute('data-student-id');
            this.textContent = 'Shortlisted';
            this.disabled = true;
            this.classList.remove('btn-primary');
            this.classList.add('btn-outline');
            alert('Student has been shortlisted!');
        });
    });
    
    // Evaluate task functionality
    const evaluateButtons = document.querySelectorAll('.evaluate-btn');
    evaluateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const taskId = this.getAttribute('data-task-id');
            alert('Opening evaluation for task ID: ' + taskId);
        });
    });
    
    // Form validation for due dates
    const dueDateInputs = document.querySelectorAll('input[type="date"]');
    dueDateInputs.forEach(input => {
        const today = new Date().toISOString().split('T')[0];
        input.setAttribute('min', today);
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (window.innerWidth > 992 && sidebarToggle) {
        sidebarToggle.style.display = 'none';
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.style.left = '0';
        }
    } else if (window.innerWidth <= 992 && sidebarToggle) {
        sidebarToggle.style.display = 'block';
    }
});