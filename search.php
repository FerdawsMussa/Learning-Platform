<?php
require_once 'includes/header.php';
require_once 'api/db.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$q_lower = strtolower($query);

// 1. Fetch from Database Courses
$matched_content_courses = [];
if (!empty($query)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at, is_approved FROM content WHERE record_type = 'course') content_courses WHERE (title LIKE ? OR description LIKE ? OR category LIKE ?) AND is_approved = 1");
        $wildcard = "%" . $query . "%";
        $stmt->execute([$wildcard, $wildcard, $wildcard]);
        $matched_content_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $matched_content_courses = [];
    }
}

// 2. Fetch from Local Resources Array (Mirrors content_resources.php dataset)
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
    ['c' => 'Tools & Technologies', 't' => 'Git & GitHub', 'd' => 'Beginner'], ['c' => 'Tools & Technologies', 't' => 'Docker', 'd' => 'Intermediate'], ['c' => 'Tools & Technologies', 't' => 'Kubernetes', 'd' => 'Advanced'], ['c' => 'Tools & Technologies', 't' => 'AWS (Cloud Computing)', 'd' => 'Advanced'], ['c' => 'Tools & Technologies', 't' => 'Firebase', 'd' => 'Beginner'], ['c' => 'Tools & Technologies', 't' => 'Linux', 'd' => 'Intermediate'], ['c' => 'Tools & Technologies', 't' => 'DevOps Basics', 'd' => 'Intermediate'], ['c' => 'Tools & Technologies', 't' => 'CI/CD', 'd' => 'Advanced'], ['c' => 'Tools & Technologies', 't' => 'Testing & Debugging', 'd' => 'Intermediate'], ['c' => 'Tools & Technologies', 't' => 'Version Control', 'd' => 'Beginner']
];

$matched_content_resources = [];
if (!empty($query)) {
    foreach ($raw_skills as $skill) {
        if (strpos(strtolower($skill['t']), $q_lower) !== false || strpos(strtolower($skill['c']), $q_lower) !== false) {
            $matched_content_resources[] = $skill;
        }
    }
}

$empty_search = empty($matched_content_courses) && empty($matched_content_resources);
?>

<style>
    .search-hero {
        padding: 4rem 20px 2rem;
        text-align: center;
        background: linear-gradient(180deg, rgba(10,14,18,0) 0%, rgba(0, 229, 153, 0.05) 100%);
        border-bottom: 1px solid var(--border-light);
        margin-bottom: 3rem;
    }

    .badge-diff {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 100px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .resource-card {
        background: var(--bg-card);
        border: 1px solid var(--border-light);
        padding: 1.5rem;
        border-radius: var(--radius-md);
        transition: 0.3s;
    }
    
    .resource-card:hover {
        transform: translateY(-5px);
        background: var(--bg-card-hover);
        border-color: var(--accent-cyan);
        box-shadow: 0 10px 25px rgba(0, 229, 255, 0.1);
    }
</style>

<main style="min-height: 70vh;">
    <section class="search-hero animate-up">
        <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Search Results for "<span class="text-accent"><?= htmlspecialchars($query) ?></span>"</h1>
        <p style="color: var(--text-secondary);">
            <?php if(empty($query)): ?>
                Please enter a search term in the navigation bar.
            <?php else: ?>
                Found <?= count($matched_content_courses) ?> Courses and <?= count($matched_content_resources) ?> Resources.
            <?php endif; ?>
        </p>
    </section>

    <div style="max-width: 1300px; margin: 0 auto; padding: 0 20px 4rem;">
        
        <?php if($empty_search && !empty($query)): ?>
            <div style="text-align: center; padding: 4rem; background: var(--bg-card); border: 1px dashed var(--border-light); border-radius: var(--radius-lg);">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" style="margin-bottom: 1rem;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <h3 style="font-size: 1.5rem; margin-bottom: 0.5rem;">No results found</h3>
                <p style="color: var(--text-secondary);">We couldn't find any content_courses or content_resources matching your criteria. Try adjusting your keywords.</p>
            </div>
        <?php endif; ?>

        <!-- Courses Section -->
        <?php if(count($matched_content_courses) > 0): ?>
            <h2 style="font-size: 1.8rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 10px;" class="animate-up">
                Related <span class="text-gradient-cyan">Courses</span>
            </h2>
            <div class="course-grid" style="margin-top: 0; margin-bottom: 4rem;">
                <?php foreach($matched_content_courses as $course): 
                    $level = isset($course['level']) ? $course['level'] : 'Beginner';
                    
                    // Brand-strict color map
                    $bg_color = '#00E599'; // Default Green
                    $lvl_lower = strtolower($level);
                    if ($lvl_lower === 'intermediate') $bg_color = '#00e5ff'; // Cyan
                    elseif ($lvl_lower === 'advanced') $bg_color = '#0284c7'; // Deep Blue
                    elseif ($lvl_lower === 'trending') $bg_color = '#0d9488'; // Teal
                    
                    $bg_grad = "linear-gradient(135deg, " . $bg_color . "CC, " . $bg_color . ")";
                ?>
                    <div class="course-card animate-up">
                        <div class="course-thumbnail" style="background: <?= $bg_grad ?>">
                            <span class="course-badge" style="color: <?= $bg_color ?>;"><?= htmlspecialchars($level) ?></span>
                            <div style="position: absolute; width: 100%; height: 100%; top:0; left:0; overflow:hidden;">
                                <div class="bg-gradient-spot" style="background:white; width:200px; height:200px; opacity:0.1; top:-50px; left:-50px;"></div>
                            </div>
                        </div>
                        <div class="course-content">
                            <div style="font-size: 0.75rem; font-weight: 700; color: <?= $bg_color ?>; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">
                                <?= htmlspecialchars($course['category'] ?? 'General') ?>
                            </div>
                            <h3 class="course-title"><?= htmlspecialchars($course['title']) ?></h3>
                            <div class="course-instructor">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                JU Learns Faculty
                            </div>
                            
                            <div style="display: flex; gap: 8px; margin-top: auto; padding-top: 15px; border-top: 1px solid var(--border-light)">
                                <a href="content_courses.php" class="btn-outline-neon" style="flex:1; text-align: center; padding: 6px 0; font-size: 0.85rem;">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Resources Section -->
        <?php if(count($matched_content_resources) > 0): ?>
            <h2 style="font-size: 1.8rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-light); padding-bottom: 10px;" class="animate-up">
                Related <span class="text-accent">Resources</span> & Skills
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 4rem;">
                <?php foreach($matched_content_resources as $index => $res): 
                    $diffColor = '#00E599'; // Default Green
                    $d = strtolower($res['d']);
                    if($d == 'intermediate') $diffColor = '#00e5ff'; // Cyan
                    if($d == 'advanced') $diffColor = '#0284c7'; // Deep Blue
                ?>
                    <div class="resource-card animate-up" style="animation-delay: <?= ($index % 10) * 0.05 ?>s">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                            <h3 style="font-size: 1.15rem; color: var(--text-primary);"><?= htmlspecialchars($res['t']) ?></h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?= $diffColor ?>" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="3" x2="9" y2="21"></line></svg>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 12px;">
                            <?= htmlspecialchars($res['c']) ?>
                        </div>
                        <span class="badge-diff" style="color: <?= $diffColor ?>; background: <?= $diffColor ?>22; border: 1px solid <?= $diffColor ?>">
                            <?= htmlspecialchars($res['d']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
