<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index');
    exit;
}

$db = get_db_connection();
$page_title = 'User Management';

// Handle user addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];
    
    try {
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $role]);
    } catch (Exception $e) {
        $error = "Error adding user: " . $e->getMessage();
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'];
    $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
    
    try {
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password, $user_id]);
        $message = "Password reset successfully.";
    } catch (Exception $e) {
        $error = "Error resetting password: " . $e->getMessage();
    }
}


// Handle user deletion
if (isset($_GET['delete_id']) && $_SESSION['role'] === 'admin') {
    if ($_GET['delete_id'] != $_SESSION['user_id']) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        header('Location: users');
        exit;
    }
}

// Fetch users
$users = $db->query("SELECT id, username, email, role, created_at FROM users ORDER BY username ASC")->fetchAll();

include 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem;">User Management</h1>
    <button class="btn btn-primary" onclick="document.getElementById('addUserModal').style.display='flex'">
        <i data-lucide="user-plus"></i> Add User
    </button>
</div>

<div class="card">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border);">
                    <th style="padding: 1rem; color: var(--text-muted);">Username</th>
                    <th style="padding: 1rem; color: var(--text-muted);">Role</th>
                    <th style="padding: 1rem; color: var(--text-muted);">Created At</th>
                    <th style="padding: 1rem; color: var(--text-muted); text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="background: var(--surface-light); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; color: var(--primary);">
                                    <?php echo strtoupper($u['username'][0]); ?>
                                </div>
                                <?php echo $u['username']; ?>
                            </div>
                        </td>
                        <td style="padding: 1rem;">
                            <span style="font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; background: rgba(59, 130, 246, 0.1); color: var(--primary); text-transform: uppercase; font-weight: 600;">
                                <?php echo $u['role']; ?>
                            </span>
                        </td>
                        <td style="padding: 1rem; font-size: 0.875rem; color: var(--text-muted);">
                            <?php echo $u['created_at']; ?>
                        </td>
                        <td style="padding: 1rem; text-align: right;">
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <button class="btn" style="padding: 4px 8px; background: rgba(59, 130, 246, 0.1); color: var(--primary);" onclick="openResetModal(<?php echo $u['id']; ?>, '<?php echo $u['username']; ?>')">
                                    <i data-lucide="key" style="width: 14px;"></i>
                                </button>
                                <a href="?delete_id=<?php echo $u['id']; ?>" class="btn" style="padding: 4px 8px; background: rgba(239, 68, 68, 0.1); color: var(--danger);" onclick="return confirm('Are you sure?')">
                                    <i data-lucide="trash-2" style="width: 14px;"></i>
                                </a>
                            <?php else: ?>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">Current User</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000;">
    <div class="card" style="width: 100%; max-width: 400px; padding: 2rem;">
        <h3 style="margin-bottom: 1.5rem;">Add New User</h3>
        <form method="POST">
            <input type="hidden" name="add_user" value="1">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" class="input-control" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" class="input-control" required>
            </div>
            <div class="input-group">
                <label>Role</label>
                <select name="role" class="input-control" style="appearance: none;">
                    <option value="admin">Administrator (Full Access)</option>
                    <option value="viewer">Viewer (Read-only)</option>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="button" class="btn" style="flex: 1; justify-content: center; background: var(--surface-light);" onclick="document.getElementById('addUserModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Create User</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

