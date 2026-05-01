<?php
require_once 'includes/header.php';

// Data strictly parsed from user requirements
$categories = [
    'Programming & Development' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
    'Web Development' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
    'UI/UX & Design' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r=".5"></circle><circle cx="17.5" cy="10.5" r=".5"></circle><circle cx="8.5" cy="7.5" r=".5"></circle><circle cx="6.5" cy="12.5" r=".5"></circle><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"></path></svg>',
    'Data & Analytics' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>',
    'AI & Machine Learning' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>',
    'Cybersecurity' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
    'Mobile App Development' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>',
    'Soft Skills' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
    'Business & Career Skills' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
    'Tools & Technologies' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>'
];

$raw_skills = [
    // 1. Programming
    ['c' => 'Programming & Development', 't' => 'HTML', 'd' => 'Beginner'], ['c' => 'Programming & Development', 't' => 'CSS', 'd' => 'Beginner'], ['c' => 'Programming & Development', 't' => 'JavaScript', 'd' => 'Intermediate'], ['c' => 'Programming & Development', 't' => 'PHP', 'd' => 'Intermediate'], ['c' => 'Programming & Development', 't' => 'Python', 'd' => 'Beginner'], ['c' => 'Programming & Development', 't' => 'Java', 'd' => 'Advanced'], ['c' => 'Programming & Development', 't' => 'C++', 'd' => 'Advanced'], ['c' => 'Programming & Development', 't' => 'C#', 'd' => 'Intermediate'], ['c' => 'Programming & Development', 't' => 'TypeScript', 'd' => 'Intermediate'], ['c' => 'Programming & Development', 't' => 'Go', 'd' => 'Advanced'],
    
    // 2. Web Dev
    ['c' => 'Web Development', 't' => 'Frontend Development', 'd' => 'Beginner'], ['c' => 'Web Development', 't' => 'Backend Development', 'd' => 'Intermediate'], ['c' => 'Web Development', 't' => 'Full Stack Development', 'd' => 'Advanced'], ['c' => 'Web Development', 't' => 'Responsive Design', 'd' => 'Beginner'], ['c' => 'Web Development', 't' => 'REST API Development', 'd' => 'Intermediate'], ['c' => 'Web Development', 't' => 'Web Security Basics', 'd' => 'Intermediate'], ['c' => 'Web Development', 't' => 'Progressive Web Apps (PWA)', 'd' => 'Advanced'], ['c' => 'Web Development', 't' => 'Web Performance Optimization', 'd' => 'Advanced'], ['c' => 'Web Development', 't' => 'Cross-Browser Compatibility', 'd' => 'Intermediate'], ['c' => 'Web Development', 't' => 'SEO Basics', 'd' => 'Beginner'],
    
    // 3. UI/UX
    ['c' => 'UI/UX & Design', 't' => 'UI Design', 'd' => 'Intermediate'], ['c' => 'UI/UX & Design', 't' => 'UX Design', 'd' => 'Intermediate'], ['c' => 'UI/UX & Design', 't' => 'Figma', 'd' => 'Beginner'], ['c' => 'UI/UX & Design', 't' => 'Adobe XD', 'd' => 'Beginner'], ['c' => 'UI/UX & Design', 't' => 'Photoshop', 'd' => 'Intermediate'], ['c' => 'UI/UX & Design', 't' => 'Illustrator', 'd' => 'Intermediate'], ['c' => 'UI/UX & Design', 't' => 'Wireframing', 'd' => 'Beginner'], ['c' => 'UI/UX & Design', 't' => 'Prototyping', 'd' => 'Intermediate'], ['c' => 'UI/UX & Design', 't' => 'Design Systems', 'd' => 'Advanced'], ['c' => 'UI/UX & Design', 't' => 'User Research', 'd' => 'Advanced'],

    // 4. Data
    ['c' => 'Data & Analytics', 't' => 'Data Analysis', 'd' => 'Intermediate'], ['c' => 'Data & Analytics', 't' => 'Data Visualization', 'd' => 'Intermediate'], ['c' => 'Data & Analytics', 't' => 'Excel', 'd' => 'Beginner'], ['c' => 'Data & Analytics', 't' => 'Power BI', 'd' => 'Intermediate'], ['c' => 'Data & Analytics', 't' => 'Tableau', 'd' => 'Intermediate'], ['c' => 'Data & Analytics', 't' => 'SQL', 'd' => 'Intermediate'], ['c' => 'Data & Analytics', 't' => 'Data Cleaning', 'd' => 'Advanced'], ['c' => 'Data & Analytics', 't' => 'Statistics Basics', 'd' => 'Beginner'], ['c' => 'Data & Analytics', 't' => 'Big Data Fundamentals', 'd' => 'Advanced'], ['c' => 'Data & Analytics', 't' => 'Data Interpretation', 'd' => 'Intermediate'],

    // 5. AI
    ['c' => 'AI & Machine Learning', 't' => 'Machine Learning', 'd' => 'Advanced'], ['c' => 'AI & Machine Learning', 't' => 'Deep Learning', 'd' => 'Advanced'], ['c' => 'AI & Machine Learning', 't' => 'Natural Language Processing (NLP)', 'd' => 'Advanced'], ['c' => 'AI & Machine Learning', 't' => 'Computer Vision', 'd' => 'Advanced'], ['c' => 'AI & Machine Learning', 't' => 'TensorFlow', 'd' => 'Intermediate'], ['c' => 'AI & Machine Learning', 't' => 'PyTorch', 'd' => 'Intermediate'], ['c' => 'AI & Machine Learning', 't' => 'AI Fundamentals', 'd' => 'Beginner'], ['c' => 'AI & Machine Learning', 't' => 'Chatbot Development', 'd' => 'Intermediate'], ['c' => 'AI & Machine Learning', 't' => 'Predictive Analytics', 'd' => 'Advanced'], ['c' => 'AI & Machine Learning', 't' => 'Model Evaluation', 'd' => 'Intermediate'],

    // 6. Security
    ['c' => 'Cybersecurity', 't' => 'Ethical Hacking', 'd' => 'Advanced'], ['c' => 'Cybersecurity', 't' => 'Network Security', 'd' => 'Intermediate'], ['c' => 'Cybersecurity', 't' => 'Cryptography', 'd' => 'Advanced'], ['c' => 'Cybersecurity', 't' => 'Cyber Threat Analysis', 'd' => 'Intermediate'], ['c' => 'Cybersecurity', 't' => 'Penetration Testing', 'd' => 'Advanced'], ['c' => 'Cybersecurity', 't' => 'Security Auditing', 'd' => 'Intermediate'], ['c' => 'Cybersecurity', 't' => 'Risk Management', 'd' => 'Intermediate'], ['c' => 'Cybersecurity', 't' => 'Digital Forensics', 'd' => 'Advanced'], ['c' => 'Cybersecurity', 't' => 'Identity & Access Management', 'd' => 'Intermediate'], ['c' => 'Cybersecurity', 't' => 'Secure Coding', 'd' => 'Beginner'],

    // 7. Mobile
    ['c' => 'Mobile App Development', 't' => 'Android Development', 'd' => 'Intermediate'], ['c' => 'Mobile App Development', 't' => 'iOS Development', 'd' => 'Intermediate'], ['c' => 'Mobile App Development', 't' => 'Flutter', 'd' => 'Beginner'], ['c' => 'Mobile App Development', 't' => 'React Native', 'd' => 'Beginner'], ['c' => 'Mobile App Development', 't' => 'Mobile UI Design', 'd' => 'Intermediate'], ['c' => 'Mobile App Development', 't' => 'App Testing', 'd' => 'Intermediate'], ['c' => 'Mobile App Development', 't' => 'Mobile Security', 'd' => 'Advanced'], ['c' => 'Mobile App Development', 't' => 'App Deployment', 'd' => 'Intermediate'], ['c' => 'Mobile App Development', 't' => 'API Integration', 'd' => 'Intermediate'], ['c' => 'Mobile App Development', 't' => 'Cross-Platform Development', 'd' => 'Advanced'],

    // 8. Soft 
    ['c' => 'Soft Skills', 't' => 'Communication Skills', 'd' => 'Beginner'], ['c' => 'Soft Skills', 't' => 'Teamwork', 'd' => 'Beginner'], ['c' => 'Soft Skills', 't' => 'Problem Solving', 'd' => 'Intermediate'], ['c' => 'Soft Skills', 't' => 'Critical Thinking', 'd' => 'Intermediate'], ['c' => 'Soft Skills', 't' => 'Time Management', 'd' => 'Beginner'], ['c' => 'Soft Skills', 't' => 'Leadership', 'd' => 'Advanced'], ['c' => 'Soft Skills', 't' => 'Creativity', 'd' => 'Intermediate'], ['c' => 'Soft Skills', 't' => 'Adaptability', 'd' => 'Beginner'], ['c' => 'Soft Skills', 't' => 'Decision Making', 'd' => 'Advanced'], ['c' => 'Soft Skills', 't' => 'Conflict Resolution', 'd' => 'Advanced'],

    // 9. Business
    ['c' => 'Business & Career Skills', 't' => 'Project Management', 'd' => 'Advanced'], ['c' => 'Business & Career Skills', 't' => 'Agile & Scrum', 'd' => 'Intermediate'], ['c' => 'Business & Career Skills', 't' => 'Digital Marketing', 'd' => 'Beginner'], ['c' => 'Business & Career Skills', 't' => 'Content Writing', 'd' => 'Beginner'], ['c' => 'Business & Career Skills', 't' => 'Public Speaking', 'd' => 'Intermediate'], ['c' => 'Business & Career Skills', 't' => 'Entrepreneurship', 'd' => 'Advanced'], ['c' => 'Business & Career Skills', 't' => 'Business Analysis', 'd' => 'Intermediate'], ['c' => 'Business & Career Skills', 't' => 'Customer Service', 'd' => 'Beginner'], ['c' => 'Business & Career Skills', 't' => 'Sales Skills', 'd' => 'Intermediate'], ['c' => 'Business & Career Skills', 't' => 'Negotiation', 'd' => 'Advanced'],

    // 10. Tools
    ['c' => 'Tools & Technologies', 't' => 'Git & GitHub', 'd' => 'Beginner'], ['c' => 'Tools & Technologies', 't' => 'Docker', 'd' => 'Intermediate'], ['c' => 'Tools & Technologies', 't' => 'Kubernetes', 'd' => 'Advanced'], ['c' => 'Tools & Technologies', 't' => 'AWS (Cloud Computing)', 'd' => 'Advanced'], ['c' => 'Tools & Technologies', 't' => 'Firebase', 'd' => 'Beginner'], ['c' => 'Tools & Technologies', 't' => 'Linux', 'd' => 'Intermediate'], ['c' => 'Tools & Technologies', 't' => 'DevOps Basics', 'd' => 'Intermediate'], ['c' => 'Tools & Technologies', 't' => 'CI/CD', 'd' => 'Advanced'], ['c' => 'Tools & Technologies', 't' => 'Testing & Debugging', 'd' => 'Intermediate'], ['c' => 'Tools & Technologies', 't' => 'Version Control', 'd' => 'Beginner'],
];

// Difficulty Color Mapping
$diff_colors = [
    'Beginner' => '#3b82f6', // blue
    'Intermediate' => '#f59e0b', // orange
    'Advanced' => '#ef4444' // red
];
?>

<style>
    .skills-header {
        text-align: center;
        padding: 4rem 20px;
        position: relative;
    }

    .search-wrapper {
        max-width: 600px;
        margin: 2rem auto;
        position: relative;
    }

    .search-input {
        width: 100%;
        padding: 1.2rem 1.5rem 1.2rem 3rem;
        border-radius: 100px;
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--border-light);
        color: white;
        font-size: 1.1rem;
        outline: none;
        transition: 0.3s;
    }

    .search-input:focus {
        border-color: var(--accent-green);
        box-shadow: 0 0 15px var(--accent-green-glow);
    }

    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
    }

    .filter-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
        margin-bottom: 3rem;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }

    .filter-btn {
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--border-light);
        color: var(--text-secondary);
        padding: 8px 18px;
        border-radius: 100px;
        cursor: pointer;
        transition: 0.3s;
        font-size: 0.9rem;
    }

    .filter-btn:hover, .filter-btn.active {
        background: var(--accent-green-glow);
        color: var(--text-primary);
        border-color: var(--accent-green);
    }

    .skills-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        max-width: 1300px;
        margin: 0 auto;
        padding: 0 20px 4rem 20px;
    }

    .skill-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        border-radius: var(--radius-md);
        padding: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .skill-card:hover {
        transform: translateY(-5px);
        background: var(--bg-card-hover);
        border-color: var(--accent-green);
        box-shadow: 0 10px 25px rgba(0, 229, 153, 0.1);
    }

    .skill-icon-wrap {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: rgba(0, 229, 153, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-green);
        flex-shrink: 0;
    }

    .skill-info h3 {
        font-size: 1.15rem;
        margin-bottom: 5px;
        color: var(--text-primary);
    }

    .skill-category {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin-bottom: 12px;
    }

    .difficulty-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 100px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        border: 1px solid currentColor;
    }
</style>

<main>
    <section class="skills-header">
        <h1 class="animate-up" style="font-size: 3rem; margin-bottom: 1rem;">
            Resources <span class="text-accent">Directory</span>
        </h1>
        <p class="animate-up animate-delay-1" style="color: var(--text-secondary); max-width: 600px; margin: 0 auto;">
            Explore over 100+ professional competencies categorised by industry domains to accelerate your learning path.
        </p>

        <div class="search-wrapper animate-up animate-delay-2">
            <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="searchInput" class="search-input" placeholder="Search for skills (e.g., Python, React, Cybersecurity)...">
        </div>

        <div class="filter-container animate-up animate-delay-3" id="filterContainer">
            <button class="filter-btn active" data-filter="all">All Resources</button>
            <button class="filter-btn" data-filter="pdf">PDFs</button>
            <button class="filter-btn" data-filter="slide">Slides</button>
            <button class="filter-btn" data-filter="note">Notes</button>
            <button class="filter-btn" data-filter="video">Videos</button>
            <?php foreach($categories as $name => $icon): ?>
                <button class="filter-btn" data-filter="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></button>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="skills-grid" id="skillsGrid">
        <?php foreach($raw_skills as $index => $skill): ?>
            <?php 
                $catName = $skill['c'];
                $svgIcon = $categories[$catName];
                $diffColor = $diff_colors[$skill['d']];
            ?>
            <div class="skill-card animate-up" data-category="<?= htmlspecialchars($catName) ?>" data-title="<?= htmlspecialchars(strtolower($skill['t'])) ?>" style="animation-delay: <?= ($index % 10) * 0.05 ?>s">
                <div class="skill-icon-wrap">
                    <?= $svgIcon ?>
                </div>
                <div class="skill-info">
                    <h3><?= htmlspecialchars($skill['t']) ?></h3>
                    <div class="skill-category"><?= htmlspecialchars($catName) ?></div>
                    <span class="difficulty-badge" style="color: <?= $diffColor ?>; background: <?= $diffColor ?>22;">
                        <?= htmlspecialchars($skill['d']) ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('searchInput');
        const filterBtns = document.querySelectorAll('.filter-btn');
        const skillCards = document.querySelectorAll('.skill-card');

        let currentFilter = 'all';
        let currentSearch = '';

        function runFilters() {
            skillCards.forEach(card => {
                const title = card.getAttribute('data-title');
                const category = card.getAttribute('data-category');
                
                const matchesSearch = title.includes(currentSearch);
                const matchesFilter = currentFilter === 'all' || category === currentFilter;

                if (matchesSearch && matchesFilter) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', (e) => {
            currentSearch = e.target.value.toLowerCase();
            runFilters();
        });

        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Update active state
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Update filter and run
                currentFilter = btn.getAttribute('data-filter');
                runFilters();
            });
        });
    });
</script>


        <!-- The Restored Original Resource Cards -->
        <section id="content_resources" class="animate-up animate-delay-2" style="max-width: 1200px; margin: 4rem auto; position: relative;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <!-- Card 1 -->
                <div class="glass-panel" style="padding: 2.5rem; transition: transform 0.3s, box-shadow 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-10px)'; this.style.boxShadow='0 10px 30px rgba(62, 139, 255, 0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <div style="width: 50px; height: 50px; background: rgba(62, 139, 255, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; border: 1px solid rgba(62, 139, 255, 0.3); color: var(--accent-blue);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    </div>
                    <h3 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.3rem;">Documentation</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem; line-height: 1.6;">Comprehensive guides and toolkits to help you maximize your offline learning experience and sync data efficiently.</p>
                    <a href="#" class="btn-outline-neon" style="border-color: var(--accent-blue); color: var(--accent-blue); width: 100%; text-align: center; display: block;">Read Docs</a>
                </div>
                
                <!-- Card 2 -->
                <div class="glass-panel" style="padding: 2.5rem; transition: transform 0.3s, box-shadow 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-10px)'; this.style.boxShadow='0 10px 30px rgba(0, 229, 153, 0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <div style="width: 50px; height: 50px; background: rgba(0, 229, 153, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; border: 1px solid var(--accent-green-glow); color: var(--accent-green);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                    <h3 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.3rem;">Community Forums</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem; line-height: 1.6;">Connect with peers, share insights, and get unparalleled help from expert instructors in our dynamic community.</p>
                    <a href="#" class="btn-outline-neon" style="width: 100%; text-align: center; display: block;">Join Community</a>
                </div>
                
                <!-- Card 3 -->
                <div class="glass-panel" style="padding: 2.5rem; transition: transform 0.3s, box-shadow 0.3s; cursor: pointer;" onmouseover="this.style.transform='translateY(-10px)'; this.style.boxShadow='0 10px 30px rgba(155, 81, 224, 0.2)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <div style="width: 50px; height: 50px; background: rgba(155, 81, 224, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; border: 1px solid rgba(155, 81, 224, 0.3); color: var(--accent-purple);">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    </div>
                    <h3 style="color: var(--text-primary); margin-bottom: 1rem; font-size: 1.3rem;">Webinars & Events</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem; line-height: 1.6;">Access recorded workshop sessions and register for upcoming live events designed for rapid skill acceleration.</p>
                    <a href="#" class="btn-outline-neon" style="border-color: var(--accent-purple); color: var(--accent-purple); width: 100%; text-align: center; display: block;">View Events</a>
                </div>
            </div>
        </section>
    </main>
<?php require_once 'includes/footer.php'; ?>

<?php require_once 'includes/footer.php'; ?>

