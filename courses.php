<?php
require_once 'api/db.php';

// Fetch all content_courses for the browse page
$content_courses_stmt = $pdo->query("SELECT content_courses.*, users.full_name AS instructor_name FROM (SELECT id, user_id AS creator_id, title, description, file_path AS thumbnail_path, category, generic_value AS level, meta_text AS tags, is_resource, is_deleted, deleted_at, created_at, is_approved FROM content WHERE record_type = 'course') content_courses LEFT JOIN users ON content_courses.creator_id = users.id WHERE content_courses.is_deleted = 0 AND content_courses.is_approved = 1 ORDER BY content_courses.created_at DESC");
$db_content_courses = $content_courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// We exclusively use content_courses precisely from the database to maintain perfect sync
$all_content_courses = $db_content_courses;
?>
<?php require_once 'includes/header.php'; ?>
    <style>
        .filter-tabs {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 4rem;
        }
        .filter-tab {
            background: rgba(255,255,255,0.05); /* Glassmorphic */
            color: var(--text-secondary);
            border: 1px solid var(--border-light);
            padding: 10px 24px;
            border-radius: 100px;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .filter-tab.active, .filter-tab:hover {
            border-color: rgba(255,255,255,0.2);
            color: var(--text-primary);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .filter-tab.active {
            background: rgba(0, 229, 153, 0.1);
            color: var(--accent-green);
            border-color: var(--accent-green);
        }
        
        .course-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        @media (max-width: 900px) {
            .course-grid-3 { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .course-grid-3 { grid-template-columns: 1fr; }
        }
        
        .custom-card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            opacity: 1;
            transform: scale(1);
            transform-origin: center;
        }
        .custom-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 30px rgba(0, 229, 153, 0.15);
            border-color: rgba(0, 229, 153, 0.3);
            z-index: 2;
        }
        .custom-card.filtered-out {
            /* Instead of completely hiding, gracefully fade them out so every course is technically still "on screen" as requested */
            opacity: 0.15;
            filter: grayscale(100%);
            transform: scale(0.95);
            pointer-events: none;
            box-shadow: none;
        }
    </style>
    <main style="max-width: 1200px; margin: 0 auto; padding: 4rem 20px;">
        
        <!-- Search Area -->
        <div class="search-wrapper animate-on-scroll stagger-1" style="position: relative; max-width: 700px; margin: 0 auto 3rem auto;">
            <svg style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="courseSearch" class="search-input" placeholder="Search content_courses..." style="width: 100%; padding: 18px 20px 18px 50px; border-radius: 100px; border: none; background: transparent; color: var(--text-primary); font-size: 1rem; outline: none; transition: all 0.3s;">
        </div>

        <div class="filter-tabs animate-on-scroll stagger-2">
            <a href="#" class="filter-tab filter-btn active" data-filter="all">All Recommended</a>
            <a href="#" class="filter-tab filter-btn" data-filter="web programming">Web Programming</a>
            <a href="#" class="filter-tab filter-btn" data-filter="mobile programming">Mobile Programming</a>
            <a href="#" class="filter-tab filter-btn" data-filter="ui design">UI Design</a>
            <a href="#" class="filter-tab filter-btn" data-filter="backend development">Backend Development</a>
            <a href="#" class="filter-tab filter-btn" data-filter="adobe illustrator">Adobe Illustrator</a>
        </div>

        <div class="course-grid-3">
            <?php foreach($all_content_courses as $i => $course): 
                $level = isset($course['level']) ? $course['level'] : 'Beginner';
                $badge_text = $level;
                
                // Color mapping strictly to Neon Green, Cyan, Deep Blue, and Teal as requested
                $bg_color = '#00E599'; // Neon Green for Beginner default
                $lvl_lower = strtolower($level);
                if ($lvl_lower === 'intermediate') {
                    $bg_color = '#00e5ff'; // Cyan
                } elseif ($lvl_lower === 'advanced') {
                    $bg_color = '#0284c7'; // Deep Blue
                } elseif ($lvl_lower === 'trending') {
                    $bg_color = '#0d9488'; // Teal
                }
                $bg_grad = "linear-gradient(135deg, " . $bg_color . "CC, " . $bg_color . ")";
                
                // Real category directly from database (no overriding)
                $cat = isset($course['category']) ? $course['category'] : 'web programming';
            ?>
            <div class="custom-card card-hover animate-on-scroll stagger-3" data-category="<?= htmlspecialchars($cat) ?>">
                <!-- Card Top with Gradients -->
                <div style="height: 180px; position:relative; background: <?= $bg_grad ?>; display:flex; align-items:center; padding: 20px;">
                    <!-- Badge -->
                    <span style="position: absolute; top: 12px; left: 12px; background: white; color: black; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                        <?= $badge_text ?>
                    </span>
                    
                    <!-- Top Right Actions -->
                    <div style="position: absolute; top: 10px; right: 10px; display: flex; gap: 8px; z-index: 5;">
                        <a href="#" class="course-action-btn download icon-hover-scale" title="Download Material" style="color: white; opacity: 0.8;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        </a>
                        <a href="#" class="course-action-btn play icon-hover-scale" title="Watch Video" style="color: white; opacity: 0.8;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>
                        </a>
                    </div>
                    
                    <h2 style="font-size:1.3rem; max-width: 85%; color: #fff; line-height: 1.3; text-transform: uppercase; font-weight: 800; margin: 0; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                        <?= htmlspecialchars(strtoupper(isset($course['title']) ? $course['title'] : 'COURSE')) ?>
                    </h2>
                </div>
                
                <!-- Card Bottom -->
                <div style="padding: 1.5rem; display: flex; flex-direction: column; flex-grow: 1;">
                    <h3 class="course-title" style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; text-transform: uppercase; color: var(--text-primary);"><?= htmlspecialchars($course['title']) ?></h3>
                    
                    <div style="color: var(--text-secondary); display: flex; align-items: center; gap: 8px; font-size: 0.85rem; margin-bottom: 1rem; font-weight: 500;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <?= htmlspecialchars($course['instructor_name'] ?? 'Instructor Name') ?>
                    </div>
                    <?php /* current_field removed from schema */ ?>
                    
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem; line-height: 1.6;">
                        Master the principles of <?= htmlspecialchars($course['title']) ?> with expert-led instructions and real-world practical projects.
                    </p>
                    
                    <div style="color: #fbbf24; font-size: 0.8rem; margin-bottom: 1rem; letter-spacing: 2px;">★★★★★</div>
                    
                    <div style="margin-top: auto;">
                       <a href="signup.php?course_id=<?= $course['id'] ?>" class="btn-neon" style="color:#000; padding: 10px 20px; font-size:0.85rem; display:block; text-align:center; border-color:transparent; background-color: var(--accent-green); border-radius: 100px; font-weight: 600; transition: transform 0.2s;">Enroll Now</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($all_content_courses)): ?>
                <div style="grid-column: 1/-1; text-align: center; color: var(--text-secondary); padding: 4rem;">
                    No content_courses found in the database.
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.querySelectorAll('.filter-tab');
            const searchInput = document.getElementById('courseSearch');
            const container = document.querySelector('.course-grid-3');
            
            // Use efficient Javascript array filtering
            const allCards = Array.from(document.querySelectorAll('.custom-card'));
            
            // Build the "No content_courses found" state message dynamically
            let noResultsMsg = document.createElement('div');
            noResultsMsg.id = 'no-results-msg';
            noResultsMsg.style.cssText = 'grid-column: 1/-1; text-align: center; color: var(--text-secondary); padding: 4rem; display: none; font-size: 1.2rem;';
            noResultsMsg.innerText = 'No content_courses found';
            container.appendChild(noResultsMsg);

            let debounceTimer;

            function applyFilters() {
                const activeTab = document.querySelector('.filter-tab.active');
                const filterCategory = activeTab ? activeTab.dataset.filter : 'all';
                const searchTerm = searchInput.value.toLowerCase().trim();

                // Efficient .filter() methodology 
                const filteredCards = allCards.filter(card => {
                    const cardCategory = card.dataset.category || '';
                    const courseTitle = card.querySelector('.course-title').innerHTML.toLowerCase().replace(/<[^>]*>?/gm, ''); // strip potential html for clean match
                    
                    const matchesCategory = (filterCategory === 'all' || cardCategory === filterCategory);
                    const matchesSearch = courseTitle.includes(searchTerm);

                    return matchesCategory && matchesSearch;
                });

                // Clear previous results before showing new ones
                container.innerHTML = '';

                // Dynamically update the DOM container
                if (filteredCards.length > 0) {
                    noResultsMsg.style.display = 'none';
                    filteredCards.forEach(card => {
                        // Ensure cards are fully visible (overriding any historical filtered-out CSS configs)
                        card.classList.remove('filtered-out');
                        card.style.display = 'flex';
                        
                        // Optional: Highlight matched text
                        const titleElement = card.querySelector('.course-title');
                        const originalText = titleElement.innerText;
                        if (searchTerm !== "") {
                            // Find bounds for case-insensitive match
                            const index = originalText.toLowerCase().indexOf(searchTerm);
                            if (index >= 0) {
                                const matchedText = originalText.substring(index, index + searchTerm.length);
                                const beforeText = originalText.substring(0, index);
                                const afterText = originalText.substring(index + searchTerm.length);
                                titleElement.innerHTML = `${beforeText}<span style="background-color: rgba(0, 229, 153, 0.3); color: var(--accent-green); padding: 0 2px; border-radius: 4px;">${matchedText}</span>${afterText}`;
                            }
                        } else {
                            titleElement.innerHTML = originalText;
                        }

                        container.appendChild(card);
                    });
                } else {
                    noResultsMsg.style.display = 'block';
                    container.appendChild(noResultsMsg);
                }
            }

            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    applyFilters();
                });
            });

            // Debouncing event listener for optimal performance
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        applyFilters();
                    }, 300);
                });
            }
        });
    </script>
<?php require_once 'includes/footer.php'; ?>