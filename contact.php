<?php require_once 'includes/header.php'; ?>

<style>
    .contact-container {
        max-width: 1100px;
        margin: 4rem auto;
        padding: 0 20px;
    }

    .contact-grid {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 2rem;
        align-items: stretch;
    }

    .contact-panel {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: 20px;
        padding: 3.5rem 3rem;
        position: relative;
        overflow: hidden;
    }

    /* Left Side Form Styles */
    .form-header {
        text-align: center;
        margin-bottom: 2.5rem;
    }

    .form-title {
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--accent-cyan);
    }

    .form-subtitle {
        color: var(--text-secondary);
        font-size: 0.95rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-label svg {
        color: var(--accent-blue);
    }

    .form-control {
        width: 100%;
        background: rgba(10, 14, 18, 0.6);
        border: 1px solid var(--border-light);
        border-radius: 8px;
        padding: 12px 16px;
        color: var(--text-primary);
        font-family: inherit;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent-cyan);
        box-shadow: 0 0 0 2px rgba(0, 229, 255, 0.15);
    }

    textarea.form-control {
        resize: vertical;
        min-height: 120px;
    }

    /* Right Side Info Styles */
    .info-panel {
        background: rgba(10, 14, 18, 0.5); /* slightly darker */
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .main-icon-wrapper {
        margin-bottom: 2rem;
    }

    .main-icon-wrapper svg {
        width: 90px;
        height: 90px;
    }
    
    .main-icon-wrapper svg defs linearGradient {
        /* This binds the SVG to our CSS gradients */
    }

    .info-title {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 3rem;
        color: var(--text-primary);
    }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        width: 100%;
        margin-bottom: 2rem;
        text-align: left;
    }

    .contact-item svg {
        width: 45px;
        height: 45px;
        flex-shrink: 0;
    }

    .contact-item span {
        font-size: 1.1rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    @media (max-width: 900px) {
        .contact-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<main style="min-height: 80vh;">
    <div class="contact-container animate-up">
        <div class="contact-grid">
            
            <!-- Left Side: Form -->
            <div class="contact-panel">
                <div class="bg-gradient-spot glow-blue" style="width: 300px; height: 300px; top: -150px; left: -150px; opacity: 0.1;"></div>
                
                <div class="form-header">
                    <h1 class="form-title">Get in Touch</h1>
                    <p class="form-subtitle">We'd love to hear from you</p>
                </div>

                <form action="#" method="POST">
                    <div class="form-group">
                        <label class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            Full Name
                        </label>
                        <input type="text" class="form-control" name="name" placeholder="Enter your name" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                            Email Address
                        </label>
                        <input type="email" class="form-control" name="email" placeholder="Enter your email" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/></svg>
                            Subject
                        </label>
                        <input type="text" class="form-control" name="subject" placeholder="What is this about?" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 11H7V9h2v2zm4 0h-2V9h2v2zm4 0h-2V9h2v2z"/></svg>
                            Message
                        </label>
                        <textarea class="form-control" name="message" placeholder="Your message..." required></textarea>
                    </div>

                    <button type="submit" class="btn-neon" style="width: 100%; margin-top: 1rem; padding: 14px; font-size: 1rem;">Send Message</button>
                </form>
            </div>

            <!-- Right Side: Info -->
            <div class="contact-panel info-panel animate-up animate-delay-1">
                <!-- Large SVG definitions strictly applying Neon Green / Cyan -->
                <svg style="width:0;height:0;position:absolute;">
                    <defs>
                        <linearGradient id="cyan-green-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="var(--accent-cyan)" />
                            <stop offset="100%" stop-color="var(--accent-green)" />
                        </linearGradient>
                    </defs>
                </svg>

                <div class="main-icon-wrapper">
                    <svg viewBox="0 0 24 24" fill="url(#cyan-green-grad)" stroke="none">
                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/>
                    </svg>
                </div>

                <h2 class="info-title">Contact Information</h2>

                <div class="contact-item">
                    <svg viewBox="0 0 24 24" fill="url(#cyan-green-grad)" stroke="none">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    <span>Jimma University, Ethiopia</span>
                </div>

                <div class="contact-item">
                    <svg viewBox="0 0 24 24" fill="url(#cyan-green-grad)" stroke="none">
                        <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                    </svg>
                    <span>+251 123 456 789</span>
                </div>

                <div class="contact-item">
                    <svg viewBox="0 0 24 24" fill="url(#cyan-green-grad)" stroke="none">
                        <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <span>contact@julearns.edu.et</span>
                </div>
            </div>

        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
