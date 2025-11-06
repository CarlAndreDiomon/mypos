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
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "⚠️ All fields are required!";
    } else {
        $sql = "SELECT * FROM users WHERE username=? AND password=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] == 'Active') {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['usertype'] = $user['usertype'];

                // Redirect based on user type WITH session message
                switch (strtolower($user['usertype'])) {
                    case 'administrator':
                        $_SESSION['login_message'] = "Welcome, you logged in as Administrator!";
                        header("Location: index.php");
                        exit;

                    case 'cashier':
                        $_SESSION['login_message'] = "Welcome, you logged in as Cashier!";
                        header("Location: sales.php");
                        exit;

                    case 'staff':
                        $_SESSION['login_message'] = "Welcome, you logged in as Staff!";
                        header("Location: product.php");
                        exit;

                    case 'customer':
                        $_SESSION['login_message'] = "Welcome, you logged in as Customer!";
                        header("Location: product.php");
                        exit;

                    default:
                        $error = "Unknown user type.";
                }
            } else {
                $error = "Your account is inactive. Please contact the administrator.";
            }
        } else {
            $error = "❌ Incorrect username or password!";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Business Shala | Login</title>
<link href="css/bootstrap.min.css" rel="stylesheet">
<link href="css/business-casual.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {
    background-color: #343a40;
}
h1 {
    color: white;
    font-size: 70px;
}
.address-bar {
    color: white;
    text-align: center;
}
.login-box {
    background: #fff;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    margin-top: 50px;
    max-width: 400px;
}
.btn-primary {
    width: 100%;
}
.toggle-password {
    cursor: pointer;
}
footer p {
    color: white;
}
</style>
</head>
<body>

<center><h1>Business Shala</h1></center>
<h2 class="address-bar">Hofelenia, Subdivision District 1, Sibalom Antique</h2>

<center>
  <div class="container d-flex justify-content-center align-items-center">
    <div class="login-box">
        <h3 class="text-center mb-4">🔐 MyPOS Login</h3>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group mb-3">
                <label><b>Username:</b></label>
                <input type="text" name="username" class="form-control" required>
            </div>

            <div class="form-group mb-4">
                <label><b>Password:</b></label>
                <div class="input-group">
                    <input type="password" name="password" class="form-control password-field" required>
                    <div class="input-group-append">
                        <span class="input-group-text toggle-password">
                            <i class="fa fa-eye"></i>
                        </span>
                    </div>
                </div>
            </div>

            <button type="submit" name="login" class="btn btn-primary">Login</button>
        </form>
    </div>
</div>
</center>
<br>
<footer class="text-center mt-5">
  <p>© MyPOS System 2025</p>
</footer>

<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>
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
