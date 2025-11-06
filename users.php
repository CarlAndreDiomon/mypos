<?php
session_start();

// --- DATABASE CONNECTION ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mypos";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- ADD NEW USER ---
if (isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = $_POST['password']; // plain password (no hash)
    $usertype = $_POST['usertype'];
    $status = $_POST['status'];

    $insert = $conn->prepare("INSERT INTO users (username, password, usertype, status) VALUES (?, ?, ?, ?)");
    $insert->bind_param("ssss", $username, $password, $usertype, $status);
    $insert->execute();
    header("Location: account.php");
    exit();
}

// --- UPDATE USER ---
if (isset($_POST['update_user'])) {
    $id = $_POST['user_id'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $usertype = $_POST['usertype'];
    $status = $_POST['status'];

    $update = $conn->prepare("UPDATE users SET username=?, password=?, usertype=?, status=? WHERE user_id=?");
    $update->bind_param("ssssi", $username, $password, $usertype, $status, $id);
    $update->execute();
    header("Location: account.php");
    exit();
}

// --- DELETE USER ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM users WHERE user_id=$id");
    header("Location: account.php");
    exit();
}

// --- FETCH USERS ---
$sql = "SELECT * FROM users ORDER BY user_id ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Accounts</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white text-center">
            <h4 class="mb-0">USER ACCOUNTS</h4>
        </div>
        <div class="card-body">
            <div class="mb-3 text-right">
                <button class="btn btn-success" data-toggle="modal" data-target="#addUserModal">Add User</button>
            </div>

            <table class="table table-bordered table-hover text-center">
                <thead class="thead-light">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>User Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['user_id']; ?></td>
                        <td><?= htmlspecialchars($row['username']); ?></td>
                        <td><?= htmlspecialchars($row['password']); ?></td>
                        <td><?= htmlspecialchars($row['usertype']); ?></td>
                        <td>
                            <?php if ($row['status'] == 'Active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary editBtn"
                                    data-id="<?= $row['user_id']; ?>"
                                    data-username="<?= htmlspecialchars($row['username']); ?>"
                                    data-password="<?= htmlspecialchars($row['password']); ?>"
                                    data-usertype="<?= htmlspecialchars($row['usertype']); ?>"
                                    data-status="<?= htmlspecialchars($row['status']); ?>"
                                    data-toggle="modal"
                                    data-target="#editUserModal">
                                Edit
                            </button>
                            <a href="?delete=<?= $row['user_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?');">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD USER MODAL -->
<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title">Add New User</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Username:</label>
            <input type="text" name="username" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Password:</label>
            <input type="text" name="password" class="form-control" required>
          </div>
          <div class="form-group">
            <label>User Type:</label>
            <select name="usertype" class="form-control" required>
              <option value="Administrator">Administrator</option>
              <option value="Staff">Staff</option>
              <option value="Customer">Customer</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status:</label>
            <select name="status" class="form-control" required>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="add_user" class="btn btn-success">Add</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Edit User</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="user_id" id="edit_user_id">
          <div class="form-group">
            <label>Username:</label>
            <input type="text" name="username" id="edit_username" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Password:</label>
            <input type="text" name="password" id="edit_password" class="form-control" required>
          </div>
          <div class="form-group">
            <label>User Type:</label>
            <select name="usertype" id="edit_usertype" class="form-control" required>
              <option value="Administrator">Administrator</option>
              <option value="Staff">Staff</option>
              <option value="Customer">Customer</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status:</label>
            <select name="status" id="edit_status" class="form-control" required>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" name="update_user" class="btn btn-primary">Update</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    $('.editBtn').on('click', function() {
        $('#edit_user_id').val($(this).data('id'));
        $('#edit_username').val($(this).data('username'));
        $('#edit_password').val($(this).data('password'));
        $('#edit_usertype').val($(this).data('usertype'));
        $('#edit_status').val($(this).data('status'));
    });
});
</script>

</body>
</html>
