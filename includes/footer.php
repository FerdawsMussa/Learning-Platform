<?php
// Fetch some recent approved courses for the footer
try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../api/db.php';
    }
    $footer_courses_stmt = $pdo->query("SELECT id, title FROM content WHERE record_type = 'course' AND is_deleted = 0 AND is_approved = 1 ORDER BY created_at DESC LIMIT 4");
    $footer_courses = $footer_courses_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $footer_courses = [];
}
?>
<!-- FOOTER -->
<footer class="footer">

    <!-- Scroll to Top -->
    <a href="#" id="scrollTopBtn">↑</a>

    <div class="footer-container">

        <!-- Top Section -->
        <div class="footer-top">


    

        </div>

        <!-- Middle Section -->
        <div class="footer-links">
            <div>
                <h4>Contact Us</h4>
                <p style="font-size: 0.9rem; margin-bottom: 8px; color: #888;">Jimma University, Institute of Technology</p>
                <p style="font-size: 0.9rem; margin-bottom: 8px; color: #888;">Email: julearn.office@gmail.com</p>
                <p style="font-size: 0.9rem; margin-bottom: 8px; color: #888;">Phone: +251 912 345 678</p>
            </div>

            <div>
                <h4>Our Campus</h4>
                <p style="font-size: 0.9rem; margin-bottom: 8px; color: #888;">Jimma, Ethiopia</p>
                <p style="font-size: 0.9rem; margin-bottom: 8px; color: #888;">P.O. Box: 378</p>
            </div>

            <div>
                <h4>Featured Courses</h4>
                <?php if (!empty($footer_courses)): ?>
                    <?php foreach ($footer_courses as $fc): ?>
                        <a href="signup.php?course_id=<?= $fc['id'] ?>"><?= htmlspecialchars($fc['title']) ?></a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="courses.php">Browse All</a>
                <?php endif; ?>
            </div>

            <div>
                <h4>Quick Links</h4>
                <a href="about.php">About Project</a>
                <a href="login.php">Log In</a>
                <a href="signup.php">Register</a>
            </div>
        </div>

        <!-- Bottom -->
        <div class="footer-bottom">
            <p>© <?php echo date("Y"); ?> JULearns. All Rights Reserved</p>

            <div class="socials">
                <a href="#" class="social-btn telegram" title="Telegram">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </a>
                <a href="#" class="social-btn instagram" title="Instagram">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                </a>
                <a href="#" class="social-btn twitter" title="Twitter/X">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"></path></svg>
                </a>
                <a href="#" class="social-btn facebook" title="Facebook">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                </a>
            </div>
        </div>

    </div>
</footer>

<!-- FOOTER CSS -->
<style>
.footer {
    background: linear-gradient(135deg, #020617, #031a12, #020617);
    color: #ccc;
    padding: 60px 8% 20px;
    position: relative;
    font-family: Arial, sans-serif;
}

/* Scroll Button */
#scrollTopBtn {
    position: fixed;
    bottom: 25px;
    right: 25px;
    background: #00ffae;
    color: #000;
    padding: 10px 15px;
    border-radius: 50%;
    display: none;
    font-weight: bold;
    text-decoration: none;
    box-shadow: 0 0 15px #00ffae;
}

/* Layout */
.footer-container {
    max-width: 1200px;
    margin: auto;
}

/* Top */
.footer-top {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 40px;
    border-bottom: 1px solid #222;
    padding-bottom: 40px;
}

.footer-brand h2 {
    color: white;
}

.footer-brand span {
    color: #00ffae;
}

.footer-brand p {
    color: #888;
    max-width: 400px;
}

/* Newsletter */
.footer-newsletter input {
    padding: 12px;
    border: 1px solid #333;
    background: #111;
    color: white;
    border-radius: 6px;
}

.footer-newsletter button {
    padding: 12px 20px;
    background: #00ffae;
    border: none;
    color: black;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.3s;
}

.footer-newsletter button:hover {
    box-shadow: 0 0 10px #00ffae;
}

/* Links */
.footer-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 30px;
    margin: 40px 0;
}

.footer-links h4 {
    color: white;
    margin-bottom: 10px;
}

.footer-links a {
    display: block;
    color: #888;
    text-decoration: none;
    margin-bottom: 8px;
    transition: 0.3s;
}

.footer-links a:hover {
    color: #00ffae;
    transform: translateX(5px);
}

/* Bottom */
.footer-bottom {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    border-top: 1px solid #222;
    padding-top: 20px;
}

.socials {
    display: flex;
    gap: 12px;
    align-items: center;
}

.social-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    color: #888;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.social-btn:hover {
    transform: translateY(-5px) scale(1.1);
    color: white;
}

.social-btn.telegram:hover {
    background: #0088cc;
    border-color: #0088cc;
    box-shadow: 0 5px 15px rgba(0, 136, 204, 0.5);
}

.social-btn.instagram:hover {
    background: #E1306C;
    border-color: #E1306C;
    box-shadow: 0 5px 15px rgba(225, 48, 108, 0.5);
}

.social-btn.twitter:hover {
    background: #1DA1F2;
    border-color: #1DA1F2;
    box-shadow: 0 5px 15px rgba(29, 161, 242, 0.5);
}

.social-btn.facebook:hover {
    background: #1877F2;
    border-color: #1877F2;
    box-shadow: 0 5px 15px rgba(24, 119, 242, 0.5);
}

/* Responsive */
@media (max-width: 768px) {
    .footer-top {
        flex-direction: column;
    }

    .footer-bottom {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<!-- FOOTER JS -->
<script>
const btn = document.getElementById("scrollTopBtn");

window.addEventListener("scroll", () => {
    btn.style.display = window.scrollY > 300 ? "block" : "none";
});

btn.addEventListener("click", (e) => {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: "smooth" });
});
</script>

<!-- Global Animations JS -->
<script src="assets/js/animations.js"></script>