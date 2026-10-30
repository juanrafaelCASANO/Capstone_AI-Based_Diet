<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle Review / Rating Submission
$review_success = "";
$review_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review_rating'])) {
    $appointment_id_target = (int)($_POST['appointment_id'] ?? 0);
    $nutritionist_id_target = (int)($_POST['nutritionist_id'] ?? 0);
    $rating_val = (int)($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');

    if ($rating_val < 1 || $rating_val > 5) {
        $review_error = "Please select a star rating between 1 and 5.";
    } else {
        try {
            // I-check muna kung nakapag-review na para sa appointment na ito
            $chk = $conn->prepare("SELECT id FROM nutritionist_reviews WHERE appointment_id = ? AND user_id = ?");
            $chk->execute([$appointment_id_target, $user_id]);
            if ($chk->fetch()) {
                $review_error = "You have already submitted a review for this consultation.";
            } else {
                $rev_stmt = $conn->prepare("
                    INSERT INTO nutritionist_reviews (appointment_id, user_id, nutritionist_id, rating, review) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $rev_stmt->execute([$appointment_id_target, $user_id, $nutritionist_id_target, $rating_val, $review_text]);
                $review_success = "Thank you! Your rating and review have been submitted successfully.";
            }
        } catch (Exception $e) {
            $review_error = "Failed to submit review: " . $e->getMessage();
        }
    }
}

// Handle appointment booking submission via modal with past date/time validation
$booking_success = "";
$booking_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment_submit'])) {
    $nutri_id_target = $_POST['nutritionist_id'] ?? 0;
    $app_date = $_POST['appointment_date'] ?? '';
    $app_time = $_POST['appointment_time'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($app_date) || empty($app_time)) {
        $booking_error = "Please select both a preferred date and time.";
    } else {
        $selected_datetime = strtotime($app_date . ' ' . $app_time);
        $current_datetime = time();

        if ($selected_datetime < $current_datetime) {
            $booking_error = "You cannot book an appointment in the past. Please select a valid future date and time.";
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO appointments (user_id, nutritionist_id, appointment_date, appointment_time, notes, status) 
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$user_id, $nutri_id_target, $app_date, $app_time, $notes]);
                $booking_success = "Appointment request sent successfully! Waiting for nutritionist confirmation.";
            } catch (Exception $e) {
                $booking_error = "Failed to book appointment: " . $e->getMessage();
            }
        }
    }
}

// Get user latitude and longitude
$user_lat = 14.3498;
$user_lng = 121.0794;
try {
    $user_loc_stmt = $conn->prepare("SELECT latitude, longitude FROM users WHERE id = ? LIMIT 1");
    $user_loc_stmt->execute([$user_id]);
    $user_data = $user_loc_stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_data && !empty($user_data['latitude']) && !empty($user_data['longitude'])) {
        $user_lat = floatval($user_data['latitude']);
        $user_lng = floatval($user_data['longitude']);
    }
} catch (Exception $e) {}

// Get total unread messages for sidebar badge
$unread_count = 0;
try {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 0");
    $count_stmt->execute([$user_id]);
    $row = $count_stmt->fetch();
    if ($row) {
        $unread_count = (int)$row['total'];
    }
} catch (Exception $e) {
    $unread_count = 0;
}

// Kunin ang mga appointments ng user kasama ang kung may review na ba ito
$user_appointments = [];
try {
    $user_app_stmt = $conn->prepare("
        SELECT 
            a.id, 
            a.nutritionist_id,
            a.appointment_date, 
            a.appointment_time, 
            a.status, 
            a.notes, 
            a.created_at,
            n.fullname AS nutritionist_name,
            (SELECT COUNT(*) FROM nutritionist_reviews r WHERE r.appointment_id = a.id AND r.user_id = ?) AS has_reviewed
        FROM appointments a
        JOIN nutritionist n ON a.nutritionist_id = n.id
        WHERE a.user_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $user_app_stmt->execute([$user_id, $user_id]);
    $user_appointments = $user_app_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user_appointments = [];
}

// Query to get the user's latest conversations with different nutritionists
$inbox_sql = "
    WITH conversations AS (
        SELECT 
            CASE 
                WHEN sender_id = :user_id THEN receiver_id 
                ELSE sender_id 
            END AS nutritionist_id,
            message,
            created_at,
            sender_id,
            ROW_NUMBER() OVER(
                PARTITION BY CASE WHEN sender_id = :user_id THEN receiver_id ELSE sender_id END 
                ORDER BY created_at DESC
            ) as rn
        FROM messages
        WHERE sender_id = :user_id OR receiver_id = :user_id
    ),
    unread_counts AS (
        SELECT sender_id AS nutritionist_id, COUNT(*) as unread_count
        FROM messages
        WHERE receiver_id = :user_id AND status = 0
        GROUP BY sender_id
    )
    SELECT 
        n.id AS nutritionist_id,
        n.fullname AS nutritionist_name,
        n.profile_pic,
        n.status AS account_status,
        n.availability_status,
        n.last_active_at,
        c.message AS last_message,
        c.created_at AS last_message_time,
        c.sender_id AS last_sender_id,
        COALESCE(uc.unread_count, 0) AS unread_count
    FROM conversations c
    JOIN nutritionist n ON n.id = c.nutritionist_id
    LEFT JOIN unread_counts uc ON uc.nutritionist_id = c.nutritionist_id
    WHERE c.rn = 1
    ORDER BY c.created_at DESC
";

$inbox_stmt = $conn->prepare($inbox_sql);
$inbox_stmt->execute(['user_id' => $user_id]);
$conversations = $inbox_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active nutritionists with lat/lng
$nutri_list_sql = "
    SELECT n.id, n.fullname, n.profile_pic, n.certification, n.latitude, n.longitude, n.status AS account_status, n.availability_status, n.last_active_at, p.specialty AS specialization 
    FROM nutritionist n
    LEFT JOIN nutritionists_profile p ON n.fullname = p.name
    WHERE n.status = 'active' OR n.status = 'approved' OR n.status IS NULL
";
$nutri_list_stmt = $conn->query($nutri_list_sql);
$all_nutritionists = $nutri_list_stmt->fetchAll(PDO::FETCH_ASSOC);

function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 999999; 
    $earthRadius = 6371; 
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

foreach ($all_nutritionists as &$nutri) {
    $nutri['distance'] = calculateDistance($user_lat, $user_lng, $nutri['latitude'], $nutri['longitude']);
}
unset($nutri);

usort($all_nutritionists, function($a, $b) {
    return $a['distance'] <=>$b['distance'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI-Based Diet & Nutritional Planner - Inbox & Bookings</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥗</text></svg>">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
:root{
  --navy:#173b32;
  --navy-2:#123027;
  --green:#3b8f63;
  --green-soft:#e8f5ed;
  --cream:#f7faf6;
  --card:#fff;
  --text:#193028;
  --muted:#6c7b75;
  --border:#e3ebe6;
  --shadow:0 10px 30px rgba(27,61,48,.08);
  --radius:18px;
  --danger:#dc2626;
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif}
body{background:var(--cream);color:var(--text)}
a{color:inherit;text-decoration:none}
.app{min-height:100vh;display:flex}

.sidebar{
  width:260px;flex:0 0 260px;min-height:100vh;position:sticky;top:0;align-self:flex-start;
  background:linear-gradient(180deg,var(--navy),var(--navy-2));color:#fff;padding:24px 18px;
  display:flex;flex-direction:column;z-index:20;
}
.brand{display:flex;align-items:center;gap:12px;padding:6px 10px 28px}
.brand-icon{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;background:rgba(255,255,255,.12);font-size:23px}
.brand strong{display:block;font-size:17px}
.brand span{display:block;color:#b9d5ca;font-size:12px;margin-top:2px}
.nav-label{padding:0 12px 8px;color:#9fc2b4;text-transform:uppercase;letter-spacing:.11em;font-size:10px;font-weight:800}
.nav{display:grid;gap:6px}
.nav a{display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;border-radius:12px;color:#d9ebe4;font-size:14px;font-weight:650;transition:background .2s,transform .2s;position:relative}
.nav a:hover{background:rgba(255,255,255,.09);transform:translateX(2px)}
.nav a.active{background:rgba(255,255,255,.14);color:#fff}
.nav-icon{width:24px;text-align:center;font-size:17px}
.nav-badge{
  margin-left:auto; background:var(--danger); color:#fff; font-size:11px; font-weight:800; padding:2px 8px; border-radius:999px;
}
.sidebar-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.1);padding-top:16px}
.sidebar-bottom a.logout{
  display:flex;align-items:center;gap:12px;text-decoration:none;padding:13px 12px;
  border-radius:12px;color:#f8d7da!important;font-size:14px;font-weight:650;transition:background .2s;
}
.sidebar-bottom a.logout:hover{background:rgba(220,53,69,.2)}

.main{min-width:0;flex:1;padding:26px clamp(15px,4vw,48px) 40px}
.topbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}
.eyebrow{font-size:12px;font-weight:800;color:var(--green);text-transform:uppercase;letter-spacing:.1em}
.topbar h1{font-size:clamp(25px,3vw,34px);line-height:1.15;margin:5px 0 0;letter-spacing:-.7px}

/* TABS NAVIGATION */
.inbox-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
.tab-btn { background: transparent; border: none; font-size: 14px; font-weight: 700; color: var(--muted); cursor: pointer; padding: 8px 16px; border-radius: 10px; transition: all .2s; }
.tab-btn.active { background: var(--navy); color: #fff; }
.tab-content { display: none; }
.tab-content.active { display: block; }

/* SEARCH & FILTER TOOLBAR */
.inbox-toolbar { display: flex; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
.search-box { position: relative; flex: 1; min-width: 220px; }
.search-box input {
  width: 100%; padding: 11px 16px 11px 40px; border-radius: 12px; border: 1px solid var(--border);
  background: var(--card); font-size: 13.5px; color: var(--text); outline: none; box-shadow: var(--shadow);
}
.search-box input:focus { border-color: var(--green); }
.filter-pills { display: flex; gap: 6px; flex-wrap: wrap; }
.pill-btn {
  padding: 8px 14px; border-radius: 99px; border: 1px solid var(--border); background: var(--card);
  font-size: 12px; font-weight: 700; color: var(--muted); cursor: pointer; transition: all .2s; box-shadow: var(--shadow);
}
.pill-btn:hover { border-color: var(--green); color: var(--text); }
.pill-btn.active { background: var(--navy); color: #fff; border-color: var(--navy); }

/* INBOX LAYOUT GRID */
.inbox-layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; align-items: start; }
.inbox-list { display: flex; flex-direction: column; gap: 12px; }
.inbox-card {
  background: var(--card); border: 1px solid var(--border); border-radius: 16px;
  padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;
  transition: transform .2s, box-shadow .2s; box-shadow: var(--shadow);
}
.inbox-card:hover { transform: translateY(-2px); border-color: var(--green); }
.nutritionist-info { display: flex; align-items: center; gap: 16px; min-width: 0; }
.avatar-container { position: relative; flex-shrink: 0; display: inline-block; }
.avatar {
  width: 48px; height: 48px; border-radius: 50%; background: var(--green-soft);
  color: var(--green); font-weight: 700; display: grid; place-items: center; font-size: 16px; overflow: hidden;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.status-dot {
  position: absolute; bottom: 1px; right: 1px; width: 11px; height: 11px;
  border-radius: 50%; border: 2px solid #fff; z-index: 2;
}
.status-dot.available { background: #10b981; }
.status-dot.on_leave { background: #f59e0b; }
.status-dot.unavailable { background: #ef4444; }
.status-dot.offline { background: #94a3b8; }
.details { min-width: 0; }
.name-status-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.details strong { font-size: 16px; color: var(--text); }
.status-text-badge { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px; text-transform: uppercase; }
.status-text-badge.available { background: #d1fae5; color: #065f46; }
.status-text-badge.on_leave { background: #fef3c7; color: #92400e; }
.status-text-badge.unavailable { background: #fee2e2; color: #991b1b; }
.status-text-badge.offline { background: #f1f5f9; color: #475569; }
.details p { font-size: 13px; color: var(--muted); margin: 4px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* APPOINTMENTS TRACKER LIST STYLES */
.appointments-tracker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.user-appointment-card {
  background: var(--card); border: 1px solid var(--border); border-radius: 16px;
  padding: 20px; box-shadow: var(--shadow); display: flex; flex-direction: column; justify-content: space-between;
}
.user-app-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.user-app-header strong { font-size: 15px; color: var(--text); }
.appointment-status-badge { font-size: 10.5px; font-weight: 800; padding: 3px 10px; border-radius: 99px; text-transform: uppercase; letter-spacing: .05em; }
.appointment-status-badge.pending { background: #fef3c7; color: #b45309; }
.appointment-status-badge.confirmed { background: #d1fae5; color: #065f46; }
.appointment-status-badge.completed { background: #e0f2fe; color: #0369a1; }
.appointment-status-badge.cancelled { background: #fee2e2; color: #991b1b; }

.user-app-details { font-size: 13px; color: var(--muted); display: grid; gap: 6px; margin-bottom: 12px; }
.user-app-details i { color: var(--green); width: 16px; text-align: center; }
.user-app-notes { background: var(--cream); padding: 10px; border-radius: 10px; font-size: 12.5px; color: var(--muted); border: 1px solid var(--border); }

/* RATE & REVIEW BUTTON */
.btn-rate-review {
  margin-top: 14px; width: 100%; height: 38px; background: #fef3c7; color: #b45309; border: 1px solid #fde68a;
  border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: background .2s;
}
.btn-rate-review:hover { background: #fde68a; }
.btn-rated-badge {
  margin-top: 14px; width: 100%; height: 38px; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;
  border-radius: 10px; font-weight: 700; font-size: 13px; display: flex; align-items: center; justify-content: center; gap: 6px;
}

/* RECOMMENDED NUTRITIONISTS SIDEBAR CARD */
.available-card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 20px; box-shadow: var(--shadow); position: sticky; top: 26px; }
.available-card h3 { font-size: 16px; margin: 0 0 4px; color: var(--text); font-weight: 700; display: flex; align-items: center; gap: 8px; }
.sidebar-subtitle { font-size: 11px; color: var(--muted); margin-bottom: 14px; display: block; }
.nutri-sidebar-list { display: flex; flex-direction: column; gap: 12px; max-height: calc(100vh - 200px); overflow-y: auto; padding-right: 2px; }
.nutri-sidebar-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px; border-radius: 14px; background: #f8faf8; border: 1px solid var(--border); transition: all .2s; }
.nutri-sidebar-item:hover { background: #f1f8f4; border-color: #b9d5ca; }
.nutri-meta { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; }
.nutri-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--green-soft); color: var(--green); display: grid; place-items: center; font-size: 13px; font-weight: 700; flex-shrink: 0; overflow: hidden; }
.nutri-avatar img { width: 100%; height: 100%; object-fit: cover; }
.nutri-info-text { min-width: 0; flex: 1; }
.nutri-info-text strong { display: block; font-size: 13px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.certified-label { display: inline-block; font-size: 10px; font-weight: 700; color: var(--green); margin-right: 4px; }
.nutri-status-mini { font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 99px; display: inline-block; }
.nutri-status-mini.available { background: #d1fae5; color: #065f46; }
.nutri-status-mini.on_leave { background: #fef3c7; color: #92400e; }
.nutri-status-mini.unavailable { background: #fee2e2; color: #991b1b; }
.nutri-status-mini.offline { background: #f1f5f9; color: #475569; }
.license-sub { display: block; font-size: 10px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.distance-badge { font-size: 10px; font-weight: 700; background: #e2f0e9; color: #08734a; padding: 2px 6px; border-radius: 6px; display: inline-block; margin-top: 3px; }
.nutri-actions-mini { display: flex; gap: 6px; flex-shrink: 0; }
.action-btn-mini { width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; font-size: 13px; text-decoration: none; cursor: pointer; border: none; transition: background .2s, transform .2s; }
.action-btn-mini.chat { background: var(--green); color: #fff; }
.action-btn-mini.chat:hover { background: #2e714e; transform: translateY(-1px); }
.action-btn-mini.book { background: var(--navy); color: #fff; }
.action-btn-mini.book:hover { background: #0f2620; transform: translateY(-1px); }

/* STAR RATING MODAL STYLES */
.rating-modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 32, 25, 0.55); backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
  opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease; padding: 16px;
}
.rating-modal-overlay.active { opacity: 1; visibility: visible; }
.rating-modal-card {
  background: #ffffff; width: 100%; max-width: 400px; border-radius: 24px; padding: 30px; text-align: center;
  box-shadow: 0 25px 60px rgba(10, 30, 20, 0.22); transform: scale(0.9); transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.rating-modal-overlay.active .rating-modal-card { transform: scale(1); }
.star-rating-container { display: flex; justify-content: center; gap: 8px; font-size: 28px; color: #cbd5e1; cursor: pointer; margin: 16px 0; }
.star-rating-container i.selected, .star-rating-container i:hover, .star-rating-container i:hover ~ i { color: #f59e0b; }

@media(max-width: 992px) {
  .inbox-layout { grid-template-columns: 1fr; }
  .available-card { position: static; }
}

/* APPOINTMENT & LOGOUT MODALS */
.appointment-modal-overlay, .logout-modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 32, 25, 0.55); backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
  opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease; padding: 16px;
}
.appointment-modal-overlay.active, .logout-modal-overlay.active { opacity: 1; visibility: visible; }
.appointment-modal-card, .logout-modal-card {
  background: #ffffff; width: 100%; max-width: 440px; border-radius: 24px; padding: 30px;
  box-shadow: 0 25px 60px rgba(10, 30, 20, 0.22); transform: scale(0.9);
  transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.appointment-modal-overlay.active .appointment-modal-card, .logout-modal-overlay.active .logout-modal-card { transform: scale(1); }
.modal-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.modal-title-wrap h3 { font-size: 22px; font-weight: 800; color: var(--navy); margin: 0; }
.modal-close-btn { background: var(--cream); border: 1px solid var(--border); width: 34px; height: 34px; border-radius: 50%; font-size: 16px; cursor: pointer; color: var(--muted); display: grid; place-items: center; }
.modal-subtitle { font-size: 13.5px; color: var(--muted); margin-bottom: 24px; }
.modal-form-group { margin-bottom: 16px; text-align: left; }
.modal-form-group label { display: block; font-size: 12.5px; font-weight: 700; margin-bottom: 6px; color: var(--text); }
.modal-input-wrap { position: relative; }
.modal-input-wrap i { position: absolute; top: 50%; left: 14px; transform: translateY(-50%); color: var(--muted); font-size: 14px; }
.modal-input-wrap input, .modal-input-wrap textarea {
  width: 100%; padding: 12px 14px 12px 42px; border-radius: 12px; border: 1px solid var(--border);
  background: var(--cream); font-size: 14px; color: var(--text); outline: none; font-family: inherit;
}
.modal-input-wrap textarea { resize: vertical; min-height: 95px; }
.modal-actions { display: flex; gap: 10px; margin-top: 24px; }
.btn-modal-cancel { flex: 1; height: 46px; background: var(--cream); color: var(--text); border: 1px solid var(--border); border-radius: 12px; font-weight: 700; cursor: pointer; }
.btn-modal-submit { flex: 1.3; height: 46px; background: var(--green); color: #fff; border: none; border-radius: 12px; font-weight: 700; cursor: pointer; }

.alert-banner { padding: 12px 16px; border-radius: 12px; font-size: 13.5px; font-weight: 600; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
.alert-banner.success { background: #e6fffa; color: #0f766e; border: 1px solid #b2f5ea; }
.alert-banner.error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.empty-box { text-align: center; padding: 40px; color: var(--muted); font-size: 14px; background: #fff; border-radius: 16px; border: 1px solid var(--border); }
</style>
</head>
<body>

<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-icon">🥗</div>
      <div><strong>AI Diet Planner</strong><span>Personal nutrition assistant</span></div>
    </div>
    <div class="nav-label">Menu</div>
    <nav class="nav">
      <a href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
      <a href="generate_weekly.php"><span class="nav-icon">▦</span>Weekly Meal Plan</a>
      <a href="leaderboard.php"><span class="nav-icon">🏆</span>Leaderboard</a>
      <a href="user_inbox.php" class="active">
        <span class="nav-icon">📥</span>Inbox & Bookings
        <?php if($unread_count > 0): ?>
          <span class="nav-badge"><?php echo $unread_count; ?></span>
        <?php endif; ?>
      </a>
      <a href="profile.php"><span class="nav-icon">👤</span>My Profile</a>
    </nav>
    <div class="sidebar-bottom">
      <a class="logout" href="javascript:void(0);" onclick="showLogoutModal();"><span class="nav-icon">↪</span>Logout</a>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div>
        <div class="eyebrow">Communications & Schedule</div>
        <h1>Consultation Hub</h1>
      </div>
    </header>

    <?php if (!empty($booking_success) || !empty($review_success)): ?>
      <div class="alert-banner success"><i class="fa-solid fa-circle-check"></i> <?php echo $booking_success .$review_success; ?></div>
    <?php endif; ?>
    <?php if (!empty($booking_error) || !empty($review_error)): ?>
      <div class="alert-banner error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $booking_error .$review_error; ?></div>
    <?php endif; ?>

    <!-- NAVIGATION TABS -->
    <div class="inbox-tabs">
      <button type="button" class="tab-btn active" onclick="switchTab('messages', this)">
        <i class="fa-solid fa-comments"></i> Messages (<?php echo count($conversations); ?>)
      </button>
      <button type="button" class="tab-btn" onclick="switchTab('appointments', this)">
        <i class="fa-solid fa-calendar-check"></i> My Appointments (<?php echo count($user_appointments); ?>)
      </button>
    </div>

    <!-- TAB 1: MESSAGES / INBOX -->
    <div id="tab-messages" class="tab-content active">
      <div class="inbox-toolbar">
        <div class="search-box">
          <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 14px;"></i>
          <input type="text" id="inboxSearch" placeholder="Search nutritionist by name..." onkeyup="filterInbox()">
        </div>
        <div class="filter-pills">
          <button type="button" class="pill-btn active" onclick="filterByStatus('all', this)">All</button>
          <button type="button" class="pill-btn" onclick="filterByStatus('available', this)">Available</button>
          <button type="button" class="pill-btn" onclick="filterByStatus('on_leave', this)">On Leave</button>
          <button type="button" class="pill-btn" onclick="filterByStatus('unavailable', this)">Unavailable</button>
          <button type="button" class="pill-btn" onclick="filterByStatus('offline', this)">Offline</button>
        </div>
      </div>

      <div class="inbox-layout">
        <div class="inbox-list" id="inboxListContainer">
          <?php if (count($conversations) > 0): ?>
            <?php foreach ($conversations as$chat): ?>
              <?php 
                $raw_availability = trim(strtolower($chat['availability_status'] ?? 'available'));
                $account_status = trim(strtolower($chat['account_status'] ?? 'active'));
                $last_active =$chat['last_active_at'] ?? null;
                $is_offline_by_time = ($last_active && (time() - strtotime($last_active) <= 900)) ? false : true;

                if ($account_status !== 'active' && $account_status !== 'approved' ||$is_offline_by_time) {
                    $status_class = 'offline';$status_label = 'Offline';
                } else {
                    $status_class = in_array($raw_availability, ['available', 'on_leave', 'unavailable']) ?$raw_availability : 'available';
                    $status_label = ucwords(str_replace('_', ' ',$status_class));
                }
              ?>
              <a href="chat.php?nutritionist_id=<?php echo $chat['nutritionist_id']; ?>" class="inbox-card" data-name="<?php echo strtolower(htmlspecialchars($chat['nutritionist_name'])); ?>" data-status="<?php echo $status_class; ?>">
                <div class="nutritionist-info">
                  <div class="avatar-container">
                    <div class="avatar">
                      <?php if (!empty($chat['profile_pic']) && $chat['profile_pic'] !== 'default_avatar.png' && file_exists("../uploads/profile_pics/" . $chat['profile_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($chat['profile_pic']); ?>" alt="">
                      <?php else: ?>
                        <?php echo getInitials($chat['nutritionist_name']); ?>
                      <?php endif; ?>
                    </div>
                    <span class="status-dot <?php echo $status_class; ?>"></span>
                  </div>
                  <div class="details">
                    <div class="name-status-row">
                      <strong><?php echo htmlspecialchars($chat['nutritionist_name']); ?></strong>
                      <span class="status-text-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                    </div>
                    <p><?php echo htmlspecialchars($chat['last_message']); ?></p>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="empty-box">No messages yet. Select a recommended nutritionist on the right to start a conversation.</div>
          <?php endif; ?>
        </div>

        <!-- RECOMMENDED NUTRITIONISTS SIDEBAR -->
        <aside class="available-card">
          <h3><i class="fa-solid fa-user-doctor" style="color:var(--green);"></i> Recommended Nutritionists</h3>
          <div class="nutri-sidebar-list">
            <?php foreach ($all_nutritionists as$nutri): ?>
              <div class="nutri-sidebar-item">
                <div class="nutri-meta">
                  <div class="nutri-avatar"><?php echo getInitials($nutri['fullname']); ?></div>
                  <div class="nutri-info-text">
                    <strong><?php echo htmlspecialchars($nutri['fullname']); ?></strong>
                    <span class="license-sub"><?php echo htmlspecialchars($nutri['specialization'] ?? 'Nutritionist'); ?></span>
                  </div>
                </div>
                <div class="nutri-actions-mini">
                  <button type="button" class="action-btn-mini book" onclick="openBookModal(<?php echo $nutri['id']; ?>, '<?php echo htmlspecialchars($nutri['fullname'], ENT_QUOTES); ?>')">
                    <i class="fa-regular fa-calendar-plus"></i>
                  </button>
                  <a href="chat.php?nutritionist_id=<?php echo $nutri['id']; ?>" class="action-btn-mini chat"><i class="fa-solid fa-comment"></i></a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </aside>
      </div>
    </div>

    <!-- TAB 2: MY APPOINTMENTS TRACKER & RATE BUTTON -->
    <div id="tab-appointments" class="tab-content">
      <div class="appointments-tracker-grid">
        <?php if (count($user_appointments) > 0): ?>
          <?php foreach ($user_appointments as$app): ?>
            <div class="user-appointment-card">
              <div>
                <div class="user-app-header">
                  <strong><?php echo htmlspecialchars($app['nutritionist_name']); ?></strong>
                  <span class="appointment-status-badge <?php echo $app['status']; ?>"><?php echo $app['status']; ?></span>
                </div>
                <div class="user-app-details">
                  <div><i class="fa-regular fa-calendar"></i> <strong>Date:</strong> <?php echo date('F d, Y', strtotime($app['appointment_date'])); ?></div>
                  <div><i class="fa-regular fa-clock"></i> <strong>Time:</strong> <?php echo date('g:i A', strtotime($app['appointment_time'])); ?></div>
                </div>
                <?php if (!empty($app['notes'])): ?>
                  <div class="user-app-notes"><strong>Notes:</strong> <?php echo htmlspecialchars($app['notes']); ?></div>
                <?php endif; ?>
              </div>