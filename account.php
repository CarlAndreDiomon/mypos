<?php
session_start();

// ---------- DATABASE CONNECTION ----------
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "mypos";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ---------- CREATE USERS TABLE IF NOT EXISTS ----------
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check->num_rows == 0) {
    $create_table_sql = "
    CREATE TABLE users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        usertype ENUM('Administrator','Staff','Customer','Cashier') NOT NULL,
        status ENUM('Active','Inactive') NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
    ";
    $conn->query($create_table_sql);
}

// ---------- ADD USER ----------
if (isset($_POST['add_user'])) {
    $username_in = trim($_POST['username']);
    $password_in = trim($_POST['password']);
    $usertype_in = trim($_POST['usertype']);
    $status_in   = trim($_POST['status']);

    if ($username_in !== '' && $password_in !== '' && $usertype_in !== '' && $status_in !== '') {
        $stmt = $conn->prepare("INSERT INTO users (username, password, usertype, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username_in, $password_in, $usertype_in, $status_in);
        if ($stmt->execute()) $_SESSION['success'] = "New user added successfully.";
        else $_SESSION['error'] = "Insert failed: " . $stmt->error;
        $stmt->close();
    } else {
        $_SESSION['error'] = "Please fill all fields.";
    }
    header('Location: account.php');
    exit;
}

// ---------- UPDATE USER ----------
if (isset($_POST['update_user'])) {
    $userid = intval($_POST['userid']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $usertype = trim($_POST['usertype']);
    $status   = trim($_POST['status']);

    $stmt = $conn->prepare("UPDATE users SET username=?, password=?, usertype=?, status=? WHERE user_id=?");
    $stmt->bind_param("ssssi", $username, $password, $usertype, $status, $userid);
    if ($stmt->execute()) $_SESSION['success'] = "User updated successfully.";
    else $_SESSION['error'] = "Update failed: " . $stmt->error;
    $stmt->close();
    header('Location: account.php');
    exit;
}

// --- DELETE USER ---
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM users WHERE user_id=$id");

    // Reorder IDs so they remain sequential
    $conn->query("SET @count = 0");
    $conn->query("UPDATE users SET user_id = @count:=@count+1 ORDER BY user_id");
    $conn->query("ALTER TABLE users AUTO_INCREMENT = 1");

    header("Location: account.php");
    exit();
}


// ---------- FETCH USERS ----------
$sql = "SELECT * FROM users ORDER BY user_id ASC";
$result = $conn->query($sql);

$isCashier = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'Cashier';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>User Accounts Business Shala </title>
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/business-casual.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,700|Josefin+Slab:400,700" rel="stylesheet">
  <style>
    .navbar-nav > li > a { font-size: 19px; padding-left: 10px; padding-right: 10px; white-space: nowrap; }
    .navbar-nav { flex-wrap: nowrap !important; white-space: nowrap; }
    .navbar .container { display: flex; justify-content: center; align-items: center; }
    .navbar-brand { font-size: 16px; white-space: nowrap; }
    .box {
        background: #fff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        margin-top: 20px;
    }
  </style>
</head>
<body>
  <div class="brand">BUSINESS SHALA</div>
  <div class="address-bar">3481 Melrose Place | Beverly Hills, CA 90210 | 123.456.7890</div>

  <!-- Navigation -->
  <nav class="navbar navbar-default" role="navigation">
    <div class="container">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
          <span class="icon-bar"></span><span class="icon-bar"></span><span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="index.php">Business Shala</a>
      </div>
      <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
        <ul class="nav navbar-nav">
          <li><a href="index.php">HOME</a></li>
          <li><a href="brand.php">Brand</a></li>
          <li><a href="product.php">Product</a></li>
          <li><a href="category.php">Category</a></li>
          <li><a href="inventory.php">Inventory</a></li>
          <li><a href="sales.php">Sales</a></li>
          <li class="active"><a href="account.php">Account</a></li>
        </ul>
      </div>
    </div>
  </nav>
 <?php if ($isCashier): ?>
   <center>
      <div">
       <h4 style="color:red;">⚠️ Access Denied</h4>
       <p style="color:red;">You don’t have permission to view this table.</p>
      </div>
   </center>
  <?php else: ?>
  <!-- Main Content -->
  <div class="container">
    <div class="row">
      <div class="box">
        <div class="col-lg-12 text-center">
          <hr>
          <h2 style="color:black;">User <strong>Accounts</strong></h2>
          <hr>
        </div>

        <?php if (!empty($_SESSION['success'])): ?>
          <div class="alert alert-success text-center"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
          <div class="alert alert-danger text-center"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="col-lg-12">
          <table class="table table-bordered table-striped text-center">
            <thead>
              <tr>
                <th>User ID</th>
                <th>Username</th>
                <th>Password</th>
                <th>User Type</th>
                <th>Status</th>
                <th width="180">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                  <tr>
                    <td><?php echo $row['user_id']; ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['password']); ?></td>
                    <td><?php echo ucfirst($row['usertype']); ?></td>
                    <td>
                      <?php if ($row['status'] == "Active"): ?>
                        <span class="label label-success">Active</span>
                      <?php else: ?>
                        <span class="label label-danger">Inactive</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editModal<?php echo $row['user_id']; ?>">Edit</button>
                      <a href="?delete=<?php echo $row['user_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this user?')">Delete</a>
                    </td>
                  </tr>

                  <!-- Edit Modal -->
                  <div class="modal fade" id="editModal<?php echo $row['user_id']; ?>" tabindex="-1" role="dialog">
                    <div class="modal-dialog" role="document">
                      <div class="modal-content">
                        <form method="POST" action="account.php">
                          <div class="modal-header">
                            <h4 class="modal-title">Edit User</h4>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                          </div>
                          <div class="modal-body">
                            <input type="hidden" name="userid" value="<?php echo $row['user_id']; ?>">
                            <div class="form-group">
                              <label>Username</label>
                              <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($row['username']); ?>" required>
                            </div>
                            <div class="form-group">
                              <label>Password</label>
                              <input type="text" name="password" class="form-control" value="<?php echo htmlspecialchars($row['password']); ?>" required>
                            </div>
                            <div class="form-group">
                              <label>User Type</label>
                              <select name="usertype" class="form-control" required>
                                <option value="Administrator" <?php if ($row['usertype'] == 'Administrator') echo 'selected'; ?>>Administrator</option>
                                <option value="Staff" <?php if ($row['usertype'] == 'Staff') echo 'selected'; ?>>Staff</option>
                                <option value="Customer" <?php if ($row['usertype'] == 'Customer') echo 'selected'; ?>>Customer</option>
                                <option value="Customer" <?php if ($row['usertype'] == 'Cashier') echo 'selected'; ?>>Cashier</option>
                              </select>
                            </div>
                            <div class="form-group">
                              <label>Status</label>
                              <select name="status" class="form-control" required>
                                <option value="Active" <?php if ($row['status'] == 'Active') echo 'selected'; ?>>Active</option>
                                <option value="Inactive" <?php if ($row['status'] == 'Inactive') echo 'selected'; ?>>Inactive</option>
                              </select>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="submit" name="update_user" class="btn btn-success">Save Changes</button>
                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="6">No users found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="col-lg-12 text-center" style="margin-top: 15px; display: flex; justify-content: center; gap: 10px;">
             <button class="btn btn-primary" data-toggle="modal" data-target="#addUserModal">Add New User</button>
             <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>


      </div>
    </div>
  </div>

  <!-- Add User Modal -->
  <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form method="POST" action="account.php">
          <div class="modal-header">
            <h4 class="modal-title">Add New User</h4>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label>Username</label>
              <input type="text" name="username" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="text" name="password" class="form-control" required>
            </div>
            <div class="form-group">
              <label>User Type</label>
              <select name="usertype" class="form-control" required>
                <option value="Administrator">Administrator</option>
                <option value="Staff">Staff</option>
                <option value="Customer">Customer</option>
                <option value="Cashier">Cashier</option>
              </select>
            </div>
            <div class="form-group">
              <label>Status</label>
              <select name="status" class="form-control" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" name="add_user" class="btn btn-success">Save</button>
            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <footer>
  <div class="container text-center">
    <p style="color:black;">© MyPOS System 2025</p>
  </div>
</footer>
<?php endif; ?>

  <script src="js/jquery.js"></script>
  <script src="js/bootstrap.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
