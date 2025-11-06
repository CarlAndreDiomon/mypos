<?php
session_start();

// ---------- DATABASE CONNECTION ----------
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mypos";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

// ---------- LOGIN PROCESS ----------
if (isset($_POST['login'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $usertype = isset($_POST['usertype']) ? trim($_POST['usertype']) : '';

    if (empty($username) || empty($password) || empty($usertype)) {
        $error = "⚠️ All fields are required!";
    } else {
        $sql = "SELECT * FROM users WHERE username=? AND password=? AND usertype=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $username, $password, $usertype);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] == 'Active') {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['usertype'] = $user['usertype'];

                // Redirect based on user type
                switch (strtolower($user['usertype'])) {
                    case 'administrator':
                        header("Location: index.php");
                        exit;
                    case 'cashier':
                        header("Location: sales.php");
                        exit;
                    case 'staff':
                        header("Location: product.php");
                        exit;
                    case 'customer':
                        header("Location: product.php");
                        exit;
                    default:
                        $error = "Unknown user type.";
                }
            } else {
                $error = "Your account is inactive. Please contact the administrator.";
            }
        } else {
            $error = "❌ Incorrect username, password, or user type!";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MyPOS Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container" style="margin-top: 100px; max-width: 400px;">
  <div class="card shadow">
    <div class="card-body">
      <h4 class="text-center mb-4">🔐 MyPOS Login</h4>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger text-center"><?php echo $error; ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group mb-3">
          <label>Username:</label>
          <input type="text" name="username" class="form-control" required>
        </div>

        <div class="form-group mb-3">
          <label>Password:</label>
          <div class="input-group">
            <input type="password" name="password" class="form-control password-field" required>
            <div class="input-group-append">
              <span class="input-group-text toggle-password" style="cursor:pointer;">
                <i class="fa fa-eye"></i>
              </span>
            </div>
          </div>
        </div>

        <div class="form-group mb-4">
          <label>User Type:</label>
          <select name="usertype" class="form-control" required>
            <option value="">-- Select User Type --</option>
            <option value="Administrator">Administrator</option>
            <option value="Cashier">Cashier</option>
            <option value="Staff">Staff</option>
            <option value="Customer">Customer</option>
          </select>
        </div>

       <center> <button type="submit" name="login" class="btn btn-primary btn-block">Login</button></center>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".toggle-password").forEach(btn => {
      btn.addEventListener("click", function() {
        const input = this.closest(".input-group").querySelector(".password-field");
        const icon = this.querySelector("i");
        if (input.type === "password") {
          input.type = "text";
          icon.classList.remove("fa-eye");
          icon.classList.add("fa-eye-slash");
        } else {
          input.type = "password";
          icon.classList.remove("fa-eye-slash");
          icon.classList.add("fa-eye");
        }
      });
    });
  });
</script>

</body>
</html>
