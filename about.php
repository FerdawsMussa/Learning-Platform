<?php require_once 'includes/header.php'; ?>

<style>
    .about-section {
        padding: 5rem 20px;
        max-width: 1200px;
        margin: 0 auto;
        position: relative;
    }
    
    .section-label {
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: var(--accent-blue);
        background: rgba(62, 139, 255, 0.1);
        padding: 6px 16px;
        border-radius: 100px;
        display: inline-block;
        margin-bottom: 1.5rem;
        border: 1px solid rgba(62, 139, 255, 0.2);
    }

    .section-title {
        font-size: 2.5rem;
        margin-bottom: 1.5rem;
        font-weight: 700;
    }

    /* Hero / Who We Are */
    .who-we-are-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        align-items: center;
    }

    .stat-list {
        display: flex;
        flex-direction: column;
        gap: 1.2rem;
    }

    .stat-list-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        padding: 1.2rem;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        gap: 1.5rem;
        transition: 0.3s;
    }

    .stat-list-card:hover {
        transform: translateX(-10px);
        background: rgba(0, 229, 153, 0.05);
        border-color: var(--accent-green);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-shrink: 0;
    }
    
    .stat-content h4 {
        margin-bottom: 0.2rem;
        font-size: 1.1rem;
    }
    .stat-content p {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    /* Mission & Vision */
    .mission-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-top: 3rem;
    }

    .mission-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-lg);
        padding: 3rem 2rem;
        transition: 0.3s;
        height: 100%;
    }

    .mission-card:hover {
        transform: translateY(-10px);
        border-color: var(--accent-cyan);
        box-shadow: 0 10px 40px rgba(0, 229, 255, 0.1);
    }

    .mission-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 2rem;
    }

    /* Complete Environment */
    .env-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-top: 3rem;
    }

    .env-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-md);
        padding: 2.5rem 1.5rem;
        text-align: center;
        transition: 0.3s;
    }

    .env-card:hover {
        transform: translateY(-8px);
        border-color: var(--accent-blue);
        box-shadow: 0 15px 30px rgba(62, 139, 255, 0.15);
    }

    .env-icon {
        margin: 0 auto 1.5rem;
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Team Section */
    .team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-top: 3rem;
    }

    .team-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-md);
        padding: 2.5rem 1.5rem;
        text-align: center;
        transition: 0.3s;
    }

    .team-card:hover {
        transform: translateY(-8px);
        border-color: var(--accent-green);
        box-shadow: 0 15px 30px rgba(0, 229, 153, 0.15);
    }

    .team-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        margin: 0 auto 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        font-weight: 700;
        color: #fff;
    }

    .team-role {
        font-size: 0.85rem;
        color: var(--accent-blue);
        margin-bottom: 1rem;
    }

    .team-tag {
        display: inline-block;
        padding: 4px 12px;
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--border-light);
        border-radius: 100px;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    /* Learning Matters Block */
    .learning-matters {
        background: rgba(10, 14, 18, 0.6);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-lg);
        padding: 4rem 2rem;
        text-align: center;
        margin-top: 5rem;
    }

    @media(max-width: 900px) {
        .who-we-are-grid, .mission-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<main>
    <!-- About Header -->
    <div style="text-align: center; padding: 6rem 20px 2rem; position: relative;">
        <div class="bg-gradient-spot glow-blue" style="width: 400px; height: 400px; top: -100px; left: 50%; transform: translateX(-50%); opacity: 0.15;"></div>
        <h1 class="animate-up" style="font-size: 3.5rem; font-weight: 800; margin-bottom: 1rem;">About <span class="text-gradient">JU Learns</span></h1>
        <p class="animate-up animate-delay-1" style="color: var(--text-secondary); max-width: 700px; margin: 0 auto; font-size: 1.15rem; line-height: 1.8;">
            Organized learning materials, offline access, and progress tracking — designed by students, for students.
        </p>
    </div>

    <!-- Who We Are Section -->
    <section class="about-section who-we-are-grid">
        <div class="animate-up">
            <span class="section-label">Who We Are</span>
            <h2 class="section-title">A Platform Built from Real Student Struggles</h2>
            <p style="color: var(--text-secondary); line-height: 1.8; margin-bottom: 1.5rem;">
                JU Learns was born from a simple observation: students struggle to find organized, accessible learning materials in one place. As IT students ourselves, we experienced firsthand the frustration of unstable internet, scattered notes across multiple messaging apps, and no clear way to track what we've learned.
            </p>
            <p style="color: var(--text-secondary); line-height: 1.8; margin-bottom: 1.5rem;">
                Our team came together to solve this problem. Under the guidance of our advisors, we designed a platform that puts learners first — providing a centralized space where learners can access organized academic materials, download content for offline study, and monitor their learning journey through an intuitive dashboard.
            </p>
            <p style="color: var(--text-secondary); line-height: 1.8;">
                Whether you're a student seeking structured content_resources or an instructor wanting to share knowledge, our platform bridges the gap between traditional learning and digital accessibility.
            </p>
        </div>
        
        <div class="stat-list animate-up animate-delay-1">
            <div class="stat-list-card">
                <div class="stat-icon" style="background: rgba(0, 229, 255, 0.15); color: var(--accent-cyan); border: 1px solid rgba(0, 229, 255, 0.3);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <div class="stat-content">
                    <h4>5,000+ Students</h4>
                    <p>Active learners on the platform</p>
                </div>
            </div>

            <div class="stat-list-card">
                <div class="stat-icon" style="background: rgba(0, 229, 153, 0.15); color: var(--accent-green); border: 1px solid var(--accent-green-glow);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                </div>
                <div class="stat-content">
                    <h4>150+ Courses</h4>
                    <p>Across multiple categories</p>
                </div>
            </div>

            <div class="stat-list-card">
                <div class="stat-icon" style="background: rgba(62, 139, 255, 0.15); color: var(--accent-blue); border: 1px solid rgba(62, 139, 255, 0.3);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12.55a11 11 0 0 1 14.08 0"></path><path d="M1.42 9a16 16 0 0 1 21.16 0"></path><path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path><line x1="12" y1="20" x2="12.01" y2="20"></line></svg>
                </div>
                <div class="stat-content">
                    <h4>Offline Access</h4>
                    <p>Learn without internet connectivity</p>
                </div>
            </div>

            <div class="stat-list-card">
                <div class="stat-icon" style="background: rgba(13, 148, 136, 0.15); color: var(--accent-teal); border: 1px solid rgba(13, 148, 136, 0.3);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                </div>
                <div class="stat-content">
                    <h4>Rewards System</h4>
                    <p>Points, badges & leaderboards</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission & Vision -->
    <section class="about-section" style="text-align: center;">
        <span class="section-label animate-up">Our Purpose</span>
        <h2 class="section-title animate-up">Mission & Vision</h2>
        
        <div class="mission-grid">
            <div class="mission-card animate-up animate-delay-1" style="text-align: left;">
                <div class="mission-icon" style="background: linear-gradient(135deg, var(--accent-blue), var(--accent-green));">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Our Mission</h3>
                <p style="color: var(--text-secondary); line-height: 1.8;">
                    To empower learners with flexible, accessible, and organized educational content_resources that support continuous learning regardless of internet connectivity.
                </p>
            </div>
            
            <div class="mission-card animate-up animate-delay-2" style="text-align: left;">
                <div class="mission-icon" style="background: linear-gradient(135deg, var(--accent-cyan), var(--accent-blue));">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                </div>
                <h3 style="font-size: 1.5rem; margin-bottom: 1rem;">Our Vision</h3>
                <p style="color: var(--text-secondary); line-height: 1.8;">
                    To become a trusted learning companion that helps students build knowledge, track growth, and achieve their academic goals through simple, effective technology.
                </p>
            </div>
        </div>
    </section>

    <!-- What We Do -->
    <section class="about-section" style="text-align: center; margin-bottom: 4rem;">
        <span class="section-label animate-up">What We Do</span>
        <h2 class="section-title animate-up">A Complete Learning Environment</h2>
        <p style="color: var(--text-secondary); margin: 0 auto; max-width: 600px;" class="animate-up">
            We provide a web-based learning environment built around the real needs of students and instructors.
        </p>

        <div class="env-grid">
            <div class="env-card animate-up animate-delay-1">
                <div class="env-icon" style="background: rgba(0, 229, 153, 0.1); color: var(--accent-green);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                </div>
                <h4 style="margin-bottom: 0.8rem;">Organized Content</h4>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Categorized content_courses, notes, and materials — easy to find, easy to follow.</p>
            </div>
            
            <div class="env-card animate-up animate-delay-2">
                <div class="env-icon" style="background: rgba(0, 229, 255, 0.1); color: var(--accent-cyan);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                </div>
                <h4 style="margin-bottom: 0.8rem;">Offline Downloads</h4>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Download content and study anywhere, even without an internet connection.</p>
            </div>

            <div class="env-card animate-up animate-delay-3">
                <div class="env-icon" style="background: rgba(62, 139, 255, 0.1); color: var(--accent-blue);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                </div>
                <h4 style="margin-bottom: 0.8rem;">Progress Tracking</h4>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Dashboards that show completed topics, progress_streaks, and achievements.</p>
            </div>

            <div class="env-card animate-up animate-delay-4">
                <div class="env-icon" style="background: rgba(13, 148, 136, 0.1); color: var(--accent-teal);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                </div>
                <h4 style="margin-bottom: 0.8rem;">Points & Badges</h4>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Gamified rewards that motivate consistent, goal-driven learning.</p>
            </div>

            <div class="env-card animate-up animate-delay-5">
                <div class="env-icon" style="background: rgba(0, 229, 153, 0.1); color: var(--accent-green);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path></svg>
                </div>
                <h4 style="margin-bottom: 0.8rem;">Instructor Tools</h4>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Creators can easily upload, organize, and track the performance of their content.</p>
            </div>

            <div class="env-card animate-up animate-delay-6">
                <div class="env-icon" style="background: rgba(62, 139, 255, 0.1); color: var(--accent-blue);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                </div>
                <h4 style="margin-bottom: 0.8rem;">Role-Based Access</h4>
                <p style="font-size: 0.85rem; color: var(--text-secondary);">Separate experiences for students, instructors, and administrators.</p>
            </div>
        </div>
    </section>

    <!-- Why It Matters -->
    <section class="about-section learning-matters animate-up">
        <span class="section-label">Why It Matters</span>
        <h2 class="section-title" style="margin-bottom: 2rem;">Learning Should Never Stop</h2>
        <p style="color: var(--text-secondary); line-height: 1.8; max-width: 800px; margin: 0 auto 1.5rem;">
            In regions where internet is expensive or unreliable, digital learning often fails. Our platform ensures that a weak connection never means the end of learning. By combining offline access with structured content and progress tracking, we make self-directed learning practical and achievable.
        </p>
        <p style="color: var(--text-secondary); line-height: 1.8; max-width: 800px; margin: 0 auto 3rem;">
            We are committed to continuous improvement, user-centered design, and educational accessibility. JU Learns is more than a project — it's our contribution to making education work for every student, everywhere.
        </p>
        <div style="display: flex; gap: 15px; justify-content: center;">
            <a href="content_courses.php" class="btn-neon" style="background: linear-gradient(90deg, var(--accent-blue), var(--accent-green)); color: #fff; padding: 12px 28px;">Explore Courses ➜</a>
            <a href="signup.php" class="btn-outline-neon" style="padding: 12px 28px; color: var(--accent-cyan); border-color: var(--accent-cyan);">Get Started Free</a>
        </div>
    </section>

    <!-- Meet The Team -->
    <section class="about-section" style="text-align: center; margin-bottom: 4rem;">
        <span class="section-label animate-up">The People Behind It</span>
        <h2 class="section-title animate-up">Meet the Team</h2>
        <p style="color: var(--text-secondary); margin: 0 auto; max-width: 600px;" class="animate-up">
            Five IT students from Jimma Institute of Technology who turned a shared frustration into a working solution.
        </p>

        <div class="team-grid">
            <div class="team-card animate-up animate-delay-1">
                <div class="team-avatar" style="background: var(--accent-blue);">FM</div>
                <h4 style="margin-bottom: 0.2rem;">Feridaws Mussa</h4>
                <div class="team-role">Team Lead / Developer</div>
                <div class="team-tag">Leadership</div>
            </div>
            
            <div class="team-card animate-up animate-delay-2">
                <div class="team-avatar" style="background: var(--accent-cyan);">EA</div>
                <h4 style="margin-bottom: 0.2rem;">Elham Abdu</h4>
                <div class="team-role">Frontend Developer</div>
                <div class="team-tag">HTML · CSS · JS</div>
            </div>

            <div class="team-card animate-up animate-delay-3">
                <div class="team-avatar" style="background: var(--accent-green);">BT</div>
                <h4 style="margin-bottom: 0.2rem;">Beimnet Teshome</h4>
                <div class="team-role">Backend Developer</div>
                <div class="team-tag">Logic · APIs</div>
            </div>

            <div class="team-card animate-up animate-delay-4">
                <div class="team-avatar" style="background: var(--accent-teal);">MZ</div>
                <h4 style="margin-bottom: 0.2rem;">Mira Zeynu</h4>
                <div class="team-role">Database Designer</div>
                <div class="team-tag">Data · Storage</div>
            </div>

            <div class="team-card animate-up animate-delay-5">
                <div class="team-avatar" style="background: rgba(62, 139, 255, 0.8);">SM</div>
                <h4 style="margin-bottom: 0.2rem;">Sebrin Mahmud</h4>
                <div class="team-role">UI/UX Designer</div>
                <div class="team-tag">Design · UX</div>
            </div>
        </div>
    </section>

</main>

<?php require_once 'includes/footer.php'; ?>
