<?php
require_once dirname(__DIR__) . '/api/db.php';

echo "Running Seed Script...\n";

// Ensure we have at least one creator
$stmt = $pdo->query("SELECT id FROM content_creators LIMIT 1");
$creator = $stmt->fetch();
if (!$creator) {
    // Insert a dummy creator if none exists
    $pdo->exec("INSERT INTO content_creators (full_name, email, password, is_approved) VALUES ('Admin Creator', 'adminc@lms.com', 'dummy', 1)");
    $creator_id = $pdo->lastInsertId();
} else {
    $creator_id = $creator['id'];
}

$content_courses_to_seed = [
    // Web Programming
    ['HTML & CSS Fundamentals', 'Beginner', 'web programming'],
    ['JavaScript for Beginners', 'Beginner', 'web programming'],
    ['Responsive Web Design Bootcamp', 'Beginner', 'web programming'],
    ['Web Development Basics (Frontend Intro)', 'Beginner', 'web programming'],
    ['Git & GitHub for Developers', 'Beginner', 'web programming'],

    ['Advanced JavaScript (ES6+)', 'Intermediate', 'web programming'],
    ['React.js Complete Course', 'Intermediate', 'web programming'],
    ['Frontend Development with Bootstrap & Tailwind', 'Intermediate', 'web programming'],
    ['Node.js & Express.js Fundamentals', 'Intermediate', 'web programming'],
    ['Building REST APIs', 'Intermediate', 'web programming'],

    ['Full Stack Web Development (MERN Stack)', 'Advanced', 'web programming'],
    ['PHP & MySQL Advanced Development', 'Advanced', 'web programming'],
    ['Laravel Framework Masterclass', 'Advanced', 'web programming'],
    ['Next.js & Server-Side Rendering', 'Advanced', 'web programming'],
    ['Web Security & Authentication (JWT, OAuth)', 'Advanced', 'web programming'],

    ['React.js Masterclass', 'Trending', 'web programming'],
    ['Full Stack Web Developer Bootcamp', 'Trending', 'web programming'],
    ['JavaScript Deep Dive', 'Trending', 'web programming'],
    ['Node.js API Development', 'Trending', 'web programming'],
    ['Modern Web Projects (Real-world Apps)', 'Trending', 'web programming'],

    // Mobile Programming
    ['Android Development Basics (Java/Kotlin)', 'Beginner', 'mobile programming'],
    ['Introduction to Flutter (Dart)', 'Beginner', 'mobile programming'],
    ['Mobile App UI Fundamentals', 'Beginner', 'mobile programming'],
    ['React Native Basics', 'Beginner', 'mobile programming'],

    ['Flutter App Development (Projects)', 'Intermediate', 'mobile programming'],
    ['React Native Full Course', 'Intermediate', 'mobile programming'],
    ['Android Development with APIs', 'Intermediate', 'mobile programming'],
    ['Mobile App State Management', 'Intermediate', 'mobile programming'],

    ['Advanced Flutter (Animations & Firebase)', 'Advanced', 'mobile programming'],
    ['Scalable Mobile App Architecture', 'Advanced', 'mobile programming'],
    ['iOS Development with Swift (Advanced)', 'Advanced', 'mobile programming'],
    ['Cross-Platform App Optimization', 'Advanced', 'mobile programming'],

    // UI / UX Design
    ['Introduction to UI/UX Design', 'Beginner', 'ui design'],
    ['Design Principles & Color Theory', 'Beginner', 'ui design'],
    ['Figma Basics', 'Beginner', 'ui design'],
    ['User Interface Fundamentals', 'Beginner', 'ui design'],

    ['UX Research & Wireframing', 'Intermediate', 'ui design'],
    ['Figma Advanced Design', 'Intermediate', 'ui design'],
    ['Prototyping & Interaction Design', 'Intermediate', 'ui design'],
    ['Mobile App UI Design', 'Intermediate', 'ui design'],

    ['UX Strategy & Product Design', 'Advanced', 'ui design'],
    ['Design Systems & Components', 'Advanced', 'ui design'],
    ['Advanced Prototyping', 'Advanced', 'ui design'],
    ['User Testing & Usability Analysis', 'Advanced', 'ui design'],

    // Backend Development
    ['Introduction to Backend Development', 'Beginner', 'backend development'],
    ['PHP Basics', 'Beginner', 'backend development'],
    ['Database Fundamentals (MySQL)', 'Beginner', 'backend development'],
    ['Server & Hosting Basics', 'Beginner', 'backend development'],

    ['Node.js & Express.js', 'Intermediate', 'backend development'],
    ['REST API Development', 'Intermediate', 'backend development'],
    ['Authentication Systems (Login/Register)', 'Intermediate', 'backend development'],
    ['Django Framework Basics', 'Intermediate', 'backend development'],

    ['Laravel Advanced Development', 'Advanced', 'backend development'],
    ['Scalable Backend Architecture', 'Advanced', 'backend development'],
    ['Microservices & API Design', 'Advanced', 'backend development'],
    ['Web Security (JWT, OAuth, Encryption)', 'Advanced', 'backend development'],

    // Graphics & Creative 
    ['Adobe Photoshop Basics', 'Beginner', 'adobe illustrator'],
    ['Canva Design for Beginners', 'Beginner', 'adobe illustrator'],
    ['Graphic Design Fundamentals', 'Beginner', 'adobe illustrator'],
    ['Introduction to Digital Art', 'Beginner', 'adobe illustrator'],

    ['Adobe Illustrator (Vector Design)', 'Intermediate', 'adobe illustrator'],
    ['Video Editing with Premiere Pro', 'Intermediate', 'adobe illustrator'],
    ['Social Media Content Design', 'Intermediate', 'adobe illustrator'],
    ['Branding & Logo Design', 'Intermediate', 'adobe illustrator'],

    ['After Effects Motion Graphics', 'Advanced', 'adobe illustrator'],
    ['Advanced Photo Manipulation', 'Advanced', 'adobe illustrator'],
    ['Professional Video Editing', 'Advanced', 'adobe illustrator'],
    ['Animation & Visual Effects', 'Advanced', 'adobe illustrator'],

    // Trending / Advanced Technologies (Mapped to 'all' or specific if preferred, I will just assign them dynamically or map to backend)
];

// We can clear existing content_courses or just append. Since user wants exact db connection, we delete all then insert.
// To avoid dropping foreign keys constraints, let's just delete the content_courses:
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("TRUNCATE TABLE `content_courses`;");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

$stmt = $pdo->prepare("INSERT INTO `content_courses` (`creator_id`, `title`, `description`, `category`, `level`) VALUES (?, ?, ?, ?, ?)");

$count = 0;
foreach ($content_courses_to_seed as $c) {
    if (count($c) !== 3) continue;
    $title = $c[0];
    $level = $c[1];
    $category = $c[2]; // Using exactly the matching tab data-filter values
    $desc = "Master $title in this comprehensive course.";
    
    $stmt->execute([$creator_id, $title, $desc, $category, $level]);
    $count++;
}

echo "Seeded $count content_courses successfully.\n";
