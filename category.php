<?php
session_start();

// ---------- DATABASE CONNECTION ----------
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "mypos";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ---------- ADD CATEGORY ----------
if (isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $catdetail     = trim($_POST['catdetail']);
    $status        = trim($_POST['status']);

    if (!empty($category_name) && !empty($catdetail) && !empty($status)) {
        $stmt = $conn->prepare("INSERT INTO category (category_name, catdetail, status) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $category_name, $catdetail, $status);
        if ($stmt->execute()) {
            echo "<script>alert('Category added successfully!'); window.location='category.php';</script>";
        } else {
            echo "<script>alert('Error adding category: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Please fill in all fields!');</script>";
    }
}

// ---------- DELETE CATEGORY ----------
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM category WHERE category_id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();
        // reorder IDs
        $conn->query("SET @num := 0");
        $conn->query("UPDATE category SET category_id = @num := (@num + 1) ORDER BY category_id");
        $conn->query("ALTER TABLE category AUTO_INCREMENT = 1");
        echo "<script>alert('Category deleted successfully!'); window.location='category.php';</script>";
        exit();
    } else {
        echo "<script>alert('Error deleting category: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// ---------- UPDATE CATEGORY ----------
if (isset($_POST['update_category'])) {
    $category_id   = intval($_POST['category_id']);
    $category_name = trim($_POST['category_name']);
    $catdetail     = trim($_POST['catdetail']);
    $status        = trim($_POST['status']);

    $stmt = $conn->prepare("UPDATE category SET category_name=?, catdetail=?, status=? WHERE category_id=?");
    $stmt->bind_param("sssi", $category_name, $catdetail, $status, $category_id);
    if ($stmt->execute()) {
        echo "<script>alert('Category updated successfully!'); window.location='category.php';</script>";
    } else {
        echo "<script>alert('Error updating category: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// ---------- FETCH DATA FOR EDIT ----------
$editData = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $resultEdit = $conn->query("SELECT * FROM category WHERE category_id=$id");
    $editData = $resultEdit->fetch_assoc();
}

// ---------- SEARCH & DISPLAY ----------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search != '') {
    $stmt = $conn->prepare("
        SELECT * FROM category 
        WHERE category_name LIKE ? 
        OR catdetail LIKE ? 
        OR status LIKE ?
        ORDER BY category_id ASC
    ");
    $like = "%$search%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM category ORDER BY category_id ASC");
}

// ---------- CHECK USER TYPE ----------
$isCashier = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'Cashier';
$isStaff   = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'Staff';
$isAdmin   = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'Admin';

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Category - Business Shala</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/business-casual.css" rel="stylesheet">
    <style>
        .navbar-nav > li > a { font-size: 19px; padding-left: 10px; padding-right: 10px; white-space: nowrap; }
        .navbar-nav { flex-wrap: nowrap !important; white-space: nowrap; }
        .navbar .container { display: flex; justify-content: center; align-items: center; }

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

<center><h1 style="color: white;font-size: 70px;">BUSINESS SHALA</h1></center>
<h2 class="address-bar">Hofelenia, Subdivision District 1, Sibalom Antique</h2>

<!-- NAVBAR -->
<nav class="navbar navbar-default" role="navigation">
    <div class="container">
        <div class="collapse navbar-collapse">
            <ul class="nav navbar-nav text-center">
                <li><a href="index.php">HOME</a></li>
                <li><a href="brand.php">Brand</a></li>
                <li><a href="product.php">Product</a></li>
                <li class="active"><a href="category.php">Category</a></li>
                <li><a href="inventory.php">Inventory</a></li>
                <li><a href="sales.php">Sales</a></li>
                <li><a href="account.php">Account</a></li>
            </ul>
        </div>
    </div>
</nav>

<?php
if ($isCashier && $currentPage !== 'sales.php') {
    echo '<center><h4 style="color:red;">⚠️ Access Denied</h4><p style="color:red;">You don’t have permission to access this page.</p></center>';
} elseif ($isStaff && $currentPage !== 'product.php') {
    echo '<center><h4 style="color:red;">⚠️ Access Denied</h4><p style="color:red;">You don’t have permission to access this page.</p></center>';
} else {
?>
<center>
  <?php
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
?>

<!-- 🔍 SEARCH BAR -->
<form method="GET" action="brand.php" 
      style="display: flex; align-items: center; gap: 5px; max-width: 400px; margin:auto; margin-bottom: 15px;">
  
  <input 
    type="text" 
    name="search" 
    placeholder="Search brand, address, company, or status..." 
    value="<?php echo htmlspecialchars($search); ?>" 
    style="flex: 1; padding: 6px; border-radius: 4px; border: 1px solid #ccc;"
  >

  <?php if (!empty($search)): ?>
    <!-- 🔴 Clear Button (when search is active) -->
    <a href="brand.php" 
       style="padding: 6px 12px; background-color: red; color: white; text-decoration: none; border-radius: 4px; text-align: center;">
       Clear
    </a>
  <?php else: ?>
    <!-- 🟢 Search Button (default) -->
    <button type="submit" 
            style="padding: 6px 12px; background-color: #039929ff; color: white; border: none; border-radius: 4px;">
      Search
    </button>
  <?php endif; ?>
</form>


</center>

<!-- CATEGORY FORM -->
<div class="container">
    <div class="box">
        <center><h2 style="color:black;">CATEGORY</h2></center>
        <form method="POST" class="form-inline text-center">
            <input type="hidden" name="category_id" value="<?= isset($editData['category_id']) ? $editData['category_id'] : '' ?>">
            <input type="text" name="category_name" class="form-control" placeholder="Category Name" value="<?= isset($editData['category_name']) ? $editData['category_name'] : '' ?>" required>
            <input type="text" name="catdetail" class="form-control" placeholder="Category Detail" value="<?= isset($editData['catdetail']) ? $editData['catdetail'] : '' ?>" required>
            <input type="text" name="status" class="form-control" placeholder="Status (Active/Inactive)" value="<?= isset($editData['status']) ? $editData['status'] : '' ?>" required>

            <?php if ($editData): ?>
                <button type="submit" name="update_category" class="btn btn-success">Update</button>
                <a href="category.php" class="btn btn-secondary">Cancel</a>
            <?php else: ?>
                <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
            <?php endif; ?>
        </form>

        <br>
        <!-- CATEGORY TABLE -->
        <div class="mt-4">
            <table class="table table-bordered table-hover text-center align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Category Detail</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['category_id'] ?></td>
                                <td><?= htmlspecialchars($row['category_name']) ?></td>
                                <td><?= htmlspecialchars($row['catdetail']) ?></td>
                                <td><?= htmlspecialchars($row['status']) ?></td>
                                <td>
                                    <a href="?edit=<?= $row['category_id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="?delete=<?= $row['category_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this category?')">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-danger">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<footer>
  <div class="container text-center">
    <p style="color:black;">© MyPOS System 2025</p>
  </div>
</footer>
<?php } ?>

<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
