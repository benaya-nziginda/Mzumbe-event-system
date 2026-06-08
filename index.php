<?php
// Mzumbe Event System - Toleo la 5 (Uboreshaji wa Muundo na Navigation)
// Hifadhi file hili kama 'index.php' kwenye folder lako la XAMPP htdocs

session_start();

// 1. DATABASE CONNECTION & PERMANENT STORAGE SETUP
$database_file = 'mzumbe_smart_db.sq3';

try {
    $db = new PDO("sqlite:" . $database_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA foreign_keys = ON;");
    
    // TABLES SETUP
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        registration_number TEXT UNIQUE,
        fullname TEXT,
        password TEXT,
        role TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        description TEXT,
        event_date TEXT,    
        event_year TEXT,    
        event_time TEXT,    
        venue TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS registrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        event_id INTEGER,
        ticket_number TEXT UNIQUE,
        registration_date TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE
    )");

    // Akaunti za msingi (Zinasajiliwa kiotomatiki mara ya kwanza tu)
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (registration_number, fullname, password, role) VALUES ('ADMIN-01', 'Mzumbe Admin', '$admin_pass', 'admin')");
        
        $student_pass = password_hash('mwanafunzi123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (registration_number, fullname, password, role) VALUES ('31001234', 'John Joseph', '$student_pass', 'student')");
    }
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

$error = '';
$success = '';

// 2. LOGIC HANDLING
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // A. LOGIN PROCESS
    if (isset($_POST['login'])) {
        $reg_num = trim($_POST['registration_number']);
        $password = trim($_POST['password']);
        
        $stmt = $db->prepare("SELECT * FROM users WHERE registration_number = ?");
        $stmt->execute([$reg_num]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['reg_num'] = $user['registration_number'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = "Namba ya Usajili au Password si sahihi!";
        }
    }
    
    // B. SIGNUP PROCESS
    if (isset($_POST['signup'])) {
        $reg_num = trim($_POST['registration_number']);
        $fullname = trim($_POST['fullname']);
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        
        if(!empty($reg_num) && !empty($fullname) && !empty($_POST['password'])) {
            try {
                $stmt = $db->prepare("INSERT INTO users (registration_number, fullname, password, role) VALUES (?, ?, ?, 'student')");
                $stmt->execute([$reg_num, $fullname, $password]);
                $success = "Hongera! Akaunti imetengenezwa kikamilifu. Sasa unaweza ku-login.";
            } catch (PDOException $e) {
                $error = "Namba hii ya usajili tayari imeshatumika kwenye mfumo!";
            }
        } else {
            $error = "Tafadhali jaza fomu yote ya usajili!";
        }
    }

    // C. ADMIN: POST EVENT
    if (isset($_POST['post_event']) && isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
        $title = trim($_POST['title']);
        $desc = trim($_POST['description']);
        $date = trim($_POST['event_date']);
        $year = trim($_POST['event_year']);
        $time = trim($_POST['event_time']);
        $venue = trim($_POST['venue']);
        
        if (!empty($title) && !empty($date) && !empty($year) && !empty($time) && !empty($venue)) {
            $stmt = $db->prepare("INSERT INTO events (title, description, event_date, event_year, event_time, venue) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $desc, $date, $year, $time, $venue]);
            $success = "Event mpya imehifadhiwa kwenye database kikamilifu!";
        } else {
            $error = "Tafadhali jaza nafasi zote kabla ya kupost!";
        }
    }

    // ADMIN: DELETE EVENT
    if (isset($_POST['delete_event']) && isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
        $event_id = $_POST['event_id'];
        $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $success = "Event imefutwa kwenye database kikamilifu!";
    }

    // D. STUDENT: BOOK EVENT
    if (isset($_POST['book_event']) && isset($_SESSION['role']) && $_SESSION['role'] == 'student') {
        $event_id = $_POST['event_id'];
        $user_id = $_SESSION['user_id'];
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = ? AND event_id = ?");
        $stmt->execute([$user_id, $event_id]);
        
        if ($stmt->fetchColumn() > 0) {
            $error = "Tayari ulishajisajili kwenye tukio hili!";
        } else {
            $ticket = "MU-TKT-" . date('Y') . "-" . rand(100, 999) . "-" . strtoupper(substr(md5(uniqid()), 0, 4));
            $reg_date = date('d-m-Y H:i');
            
            $stmt = $db->prepare("INSERT INTO registrations (user_id, event_id, ticket_number, registration_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $event_id, $ticket, $reg_date]);
            $success = "Usajili wako umekamilika na umehifadhiwa kwenye database ya Chuo!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mzumbe Smart Event System</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
        body { background-color: #f4f6f9; color: #333; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; background: #fff; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-radius: 8px; }
        header { border-bottom: 3px solid #800000; padding-bottom: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        header h1 { color: #800000; font-size: 26px; }
        .btn { background: #800000; color: white; padding: 11px 18px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; font-weight: bold; text-align: center; display: inline-block;}
        .btn:hover { background: #a00000; }
        .btn-secondary { background: #555; }
        .btn-secondary:hover { background: #777; }
        .btn-success { background: #28a745; margin-top: 10px; width: 100%;}
        .btn-success:hover { background: #218838; }
        .btn-danger { background: #dc3545; padding: 6px 12px; font-size: 12px; }
        .btn-danger:hover { background: #bd2130; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 4px; font-size: 14px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        
        /* Fomu ya katikati kwa ajili ya mageti ya kuingia */
        .auth-wrapper { max-width: 450px; margin: 60px auto; }
        .card { background: #fafafa; border: 1px solid #ddd; padding: 25px; border-radius: 6px; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .card h3 { color: #800000; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media(max-width: 850px) { .grid { grid-template-columns: 1fr; } }
        
        form { display: flex; flex-direction: column; gap: 12px; }
        input, textarea, select { padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; width: 100%; background: #fff; }
        label { font-weight: bold; font-size: 13px; color: #444; margin-bottom: -5px; }
        .row-inputs { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #800000; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .badge { display: inline-block; padding: 4px 8px; background: #28a745; color: white; font-size: 11px; border-radius: 4px; font-weight: bold; }
        
        .link-toggle { display: block; text-align: center; margin-top: 15px; font-size: 14px; color: #800000; text-decoration: none; font-weight: bold; cursor: pointer; }
        .link-toggle:hover { text-decoration: underline; color: #a00000; }
        
        .ticket-box { background: #fff; border: 2px dashed #800000; border-left: 8px solid #28a745; padding: 15px; border-radius: 4px; margin-bottom: 15px; position: relative; }
        .ticket-header { font-size: 11px; color: #28a745; font-weight: bold; text-transform: uppercase; display: flex; justify-content: space-between; }
        .ticket-title { font-size: 16px; font-weight: bold; color: #800000; margin: 6px 0; }
        .ticket-details { font-size: 12px; color: #555; line-height: 1.5; margin-bottom: 10px; border-top: 1px solid #eee; padding-top: 6px; }
        .ticket-number { font-size: 14px; font-weight: bold; background: #e9ecef; padding: 5px 8px; display: inline-block; border-radius: 4px; color: #333; font-family: monospace; border: 1px solid #ccc;}

        @media print {
            body * { visibility: hidden; background: none; }
            .printable-ticket, .printable-ticket * { visibility: visible; }
            .printable-ticket { position: absolute; left: 0; top: 0; width: 100%; border: 3px solid #800000; padding: 30px; }
            .no-print { display: none !important; }
        }
    </style>
    <script>
        function downloadTicket(ticketId) {
            var originalContent = document.body.innerHTML;
            var ticketContent = document.getElementById(ticketId).innerHTML;
            document.body.innerHTML = '<div class="printable-ticket">' + ticketContent + '</div>';
            window.print();
            document.body.innerHTML = originalContent;
            window.location.reload();
        }

        // JavaScript ya kubadili kurasa bila kurefresh (Toggle Login and Signup)
        function toggleForms(showSignup) {
            if (showSignup) {
                document.getElementById('login_card').style.display = 'none';
                document.getElementById('signup_card').style.display = 'block';
            } else {
                document.getElementById('login_card').style.display = 'block';
                document.getElementById('signup_card').style.display = 'none';
            }
        }
    </script>
</head>
<body>

<div class="container">
    <header class="no-print">
        <div>
            <h1>Mzumbe Event System</h1>
            <p style="font-size: 12px; color: #666;">Chuo Kikuu cha Mzumbe - Smart Event Portal</p>
        </div>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div style="text-align: right; font-size: 14px;">
                Mtumiaji: <strong><?php echo htmlspecialchars($_SESSION['fullname']); ?></strong> (<?php echo strtoupper($_SESSION['role']); ?>)<br>
                <a href="?action=logout" class="btn btn-secondary" style="margin-top: 5px; display: inline-block; padding: 4px 10px; font-size: 12px;">Logout</a>
            </div>
        <?php endif; ?>
    </header>

    <!-- Taarifa za Feedback -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-error no-print">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success no-print">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <!-- ================= KURASA ZA STRATEGIC AUTH (LOGIN / SIGNUP) ================= -->
        <div class="auth-wrapper">
            
            <!-- A. CARD YA LOGIN -->
            <div class="card" id="login_card">
                <h3>Ingia Kwenye Mfumo (Login)</h3>
                <form action="" method="POST">
                    <label>Registration Number / Admin ID</label>
                    <input type="text" name="registration_number" placeholder="Ingiza namba ya usajili..." required>
                    
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Ingiza neno la siri..." required>
                    
                    <button type="submit" name="login" class="btn">Ingia</button>
                </form>
                
                <!-- Link ya Usajili iliyoboreshwa chini ya login -->
                <span onclick="toggleForms(true)" class="link-toggle">Huna akaunti? Jisajili hapa</span>
            </div>

            <!-- B. CARD YA KUJISAJILI (Imefichwa Kiotomatiki hadi mtumiaji akibonyeza link) -->
            <div class="card" id="signup_card" style="display: none;">
                <h3>Sajili Akaunti Mpya ya Mwanafunzi</h3>
                <form action="" method="POST">
                    <label>Jina Kamili</label>
                    <input type="text" name="fullname" placeholder="Mfano: Mary Jackson" required>
                    
                    <label>Registration Number</label>
                    <input type="text" name="registration_number" placeholder="Mfano: 31009999" required>
                    
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Unda password yako..." required>
                    
                    <button type="submit" name="signup" class="btn">Tengeneza Akaunti</button>
                </form>
                
                <!-- Button ya kurudi nyuma kwenye Login -->
                <span onclick="toggleForms(false)" class="link-toggle" style="color: #555; font-size:13px; margin-top:20px;">⬅️ Rudi Kwenye Login</span>
            </div>
            
        </div>

    <?php else: ?>
        <!-- ================= KURASA ZA DASHBOARD (ZINAONEKANA BAADA YA LOGIN) ================= -->
        
        <?php if ($_SESSION['role'] == 'admin'): ?>
            <!-- ================= UPANDE WA ADMIN ================= -->
            <div class="grid">
                <div class="card">
                    <h3>Post Event Mpya</h3>
                    <form action="" method="POST">
                        <label>Jina la Event / Tukio</label>
                        <input type="text" name="title" placeholder="Mfano: Mzumbe Carrier Day" required>
                        
                        <label>Maelezo ya Event</label>
                        <textarea name="description" rows="3" placeholder="Andika mambo yatakayohusika..." required></textarea>
                        
                        <div class="row-inputs">
                            <div>
                                <label>Tarehe na Mwezi</label>
                                <input type="text" name="event_date" placeholder="Mfano: 12 June" required>
                            </div>
                            <div>
                                <label>Mwaka</label>
                                <input type="number" name="event_year" value="2026" min="2026" max="2030" required>
                            </div>
                            <div>
                                <label>Muda (Saa)</label>
                                <input type="text" name="event_time" placeholder="Mfano: 09:00 AM" required>
                            </div>
                        </div>
                        
                        <label>Ukumbi (Venue)</label>
                        <input type="text" name="venue" placeholder="Mfano: Main Assembly Hall" required>
                        
                        <button type="submit" name="post_event" class="btn">Post Event (Save)</button>
                    </form>

                    <h3 style="margin-top: 25px; margin-bottom: 10px; color: #800000;">Dhibiti Matukio (Delete Events)</h3>
                    <?php
                    $query = $db->query("SELECT * FROM events ORDER BY id DESC");
                    $admin_events = $query->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($admin_events) > 0):
                        foreach ($admin_events as $ev):
                    ?>
                            <div style="background: #fff; border: 1px solid #ddd; padding: 10px; margin-bottom: 8px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong style="font-size: 14px; color:#333;"><?php echo htmlspecialchars($ev['title']); ?></strong>
                                    <p style="font-size: 11px; color:#666;"><?php echo htmlspecialchars($ev['event_date']); ?> | Ukumbi: <?php echo htmlspecialchars($ev['venue']); ?></p>
                                </div>
                                <form action="" method="POST" onsubmit="return confirm('Je, una uhakika unataka kufuta tukio hili?');">
                                    <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                    <button type="submit" name="delete_event" class="btn btn-danger">Futa 🗑️</button>
                                </form>
                            </div>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <p style="color: #999; font-style: italic; font-size: 12px;">Hakuna event yoyote sokoni kwa sasa.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3>Wanafunzi Waliojisajili (Attendees Report)</h3>
                    <p style="font-size: 12px; color: #777; margin-bottom: 10px;">Ripoti ya wanafunzi wote waliochukua tiketi kwenye mfumo:</p>
                    
                    <?php
                    $sql = "SELECT r.ticket_number, u.fullname, u.registration_number, e.title AS event_title 
                            FROM registrations r
                            JOIN users u ON r.user_id = u.id
                            JOIN events e ON r.event_id = e.id
                            ORDER BY r.id DESC";
                    $query = $db->query($sql);
                    $attendees = $query->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($attendees) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Mwanafunzi</th>
                                    <th>Reg No</th>
                                    <th>Tukio / Event</th>
                                    <th>Ticket No</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendees as $row): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['fullname']); ?></strong></td>
                                        <td><code><?php echo htmlspecialchars($row['registration_number']); ?></code></td>
                                        <td><?php echo htmlspecialchars($row['event_title']); ?></td>
                                        <td><span class="badge"><?php echo htmlspecialchars($row['ticket_number']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #999; font-style: italic; font-size: 13px; margin-top:20px;">Hakuna mwanafunzi aliyejisajili bado kwenye database wetu.</p>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- UPANDE WA MWANAFUNZI-->
            <div class="grid" style="grid-template-columns: 1.7fr 1.3fr;">
                
                <div>
                    <h3 style="margin-bottom: 15px; color: #800000;">Matukio ya Chuo Yaliyopo</h3>
                    <?php
                    $query = $db->query("SELECT * FROM events ORDER BY id DESC");
                    $events = $query->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($events) > 0):
                        foreach ($events as $event):
                    ?>
                            <div class="card" style="background:#fff;">
                                <h3 style="border:none; padding:0; margin-bottom:5px; color:#800000;"><?php echo htmlspecialchars($event['title']); ?></h3>
                                <p style="font-size: 14px; color: #555; margin-bottom: 12px; line-height: 1.5;">
                                    <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                                </p>
                                <div style="font-size: 13px; color: #666; background: #f8f9fa; padding: 10px; border-radius: 4px;">
                                    📅 <strong>Tarehe:</strong> <?php echo htmlspecialchars($event['event_date']); ?>, <?php echo htmlspecialchars($event['event_year']); ?> <br>
                                    ⏰ <strong>Muda:</strong> <?php echo htmlspecialchars($event['event_time']); ?> <br>
                                    📍 <strong>Ukumbi:</strong> <?php echo htmlspecialchars($event['venue']); ?>
                                </div>
                                <form action="" method="POST">
                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <button type="submit" name="book_event" class="btn" style="width: 100%;">Jisajili Kwenye Event Hii</button>
                                </form>
                            </div>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <p style="color: #999; font-style: italic;">Hakuna matukio yaliyopostiwa na Admin kwa sasa.</p>
                    <?php endif; ?>
                </div>

                <div>
                    <h3 style="margin-bottom: 15px; color: #800000;">Ticket Zangu (Zilizopo Kwenye DB)</h3>
                    <?php
                    $stmt = $db->prepare("SELECT r.id AS reg_id, r.ticket_number, e.title, e.event_date, e.event_year, e.event_time, e.venue 
                                          FROM registrations r 
                                          JOIN events e ON r.event_id = e.id 
                                          WHERE r.user_id = ? ORDER BY r.id DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $my_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($my_tickets) > 0):
                        foreach ($my_tickets as $tkt):
                            $box_id = "tkt_box_" . $tkt['reg_id'];
                    ?>
                            <div class="card" style="background:#fff; padding:10px;">
                                <div id="<?php echo $box_id; ?>" class="ticket-box">
                                    <div class="ticket-header">
                                        <span>MZUMBE UNIVERSITY</span>
                                        <span style="color:#800000;">OFFICIAL TICKET</span>
                                    </div>
                                    <div class="ticket-title"><?php echo htmlspecialchars($tkt['title']); ?></div>
                                    <div class="ticket-details">
                                        Msajiliwa: <strong><?php echo htmlspecialchars($_SESSION['fullname']); ?></strong><br>
                                        Reg No: <strong><?php echo htmlspecialchars($_SESSION['reg_num']); ?></strong><br>
                                        Muda: <strong><?php echo htmlspecialchars($tkt['event_time']); ?></strong><br>
                                        Siku: <strong><?php echo htmlspecialchars($tkt['event_date']); ?>, <?php echo htmlspecialchars($tkt['event_year']); ?></strong><br>
                                        Ukumbi: <strong><?php echo htmlspecialchars($tkt['venue']); ?></strong>
                                    </div>
                                    <div class="ticket-number">Ticket No: <?php echo htmlspecialchars($tkt['ticket_number']); ?></div>
                                </div>
                                <button onclick="downloadTicket('<?php echo $box_id; ?>')" class="btn btn-success no-print">⬇️ Download Ticket (PDF)</button>
                            </div>
                    <?php 
                        endforeach;
                    else: 
                    ?>
                        <p style="color: #999; font-size: 13px; font-style: italic;">Hujajisajili kwenye event yoyote bado.</p>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>
