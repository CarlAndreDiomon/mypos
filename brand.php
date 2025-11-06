<?php
session_start();

// ---------- DATABASE CONNECTION ----------
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "mypos";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ---------- ADD BRAND ----------
if (isset($_POST['add_brand'])) {
    $brand_name = trim($_POST['brand_name']);
    $address    = trim($_POST['address']);
    $company    = trim($_POST['company']);
    $status     = trim($_POST['status']);

    if (!empty($brand_name) && !empty($address) && !empty($company) && !empty($status)) {
        $stmt = $conn->prepare("INSERT INTO brands (brand_name, address, company, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $brand_name, $address, $company, $status);
        if ($stmt->execute()) {
            echo "<script>alert('Brand added successfully!'); window.location='brand.php';</script>";
        } else {
            echo "<script>alert('Error adding brand: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Please fill in all fields!');</script>";
    }
}

// ---------- DELETE BRAND ----------
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM brands WHERE brand_id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $stmt->close();

        // Optional: reorder IDs
        $conn->query("SET @num := 0");
        $conn->query("UPDATE brands SET brand_id = @num := (@num + 1) ORDER BY brand_id");
        $conn->query("ALTER TABLE brands AUTO_INCREMENT = 1");

        echo "<script>alert('Brand deleted successfully!'); window.location='brand.php';</script>";
        exit();
    } else {
        echo "<script>alert('Error deleting brand: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// ---------- UPDATE BRAND ----------
if (isset($_POST['update_brand'])) {
    $brand_id   = intval($_POST['brand_id']);
    $brand_name = trim($_POST['brand_name']);
    $address    = trim($_POST['address']);
    $company    = trim($_POST['company']);
    $status     = trim($_POST['status']);

    $stmt = $conn->prepare("UPDATE brands SET brand_name=?, address=?, company=?, status=? WHERE brand_id=?");
    $stmt->bind_param("ssssi", $brand_name, $address, $company, $status, $brand_id);
    if ($stmt->execute()) {
        echo "<script>alert('Brand updated successfully!'); window.location='brand.php';</script>";
    } else {
        echo "<script>alert('Error updating brand: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// ---------- FETCH DATA FOR EDIT ----------
$editData = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $resultEdit = $conn->query("SELECT * FROM brands WHERE brand_id=$id");
    $editData = $resultEdit->fetch_assoc();
}

// ---------- SEARCH & DISPLAY ----------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search != '') {
    $stmt = $conn->prepare("
        SELECT * FROM brands 
        WHERE brand_name LIKE ? 
        OR address LIKE ? 
        OR company LIKE ? 
        OR status LIKE ?
        ORDER BY brand_id ASC
    ");
    $like = "%$search%";
    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM brands ORDER BY brand_id ASC");
}

// ---------- CHECK USER TYPE ----------
$isCashier = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'Cashier';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Brand - Business Shala</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/business-casual.css" rel="stylesheet">
    <style>
        .navbar-nav > li > a { font-size: 19px; padding-left: 10px; padding-right: 10px; white-space: nowrap; }
        .navbar-nav { flex-wrap: nowrap !important; white-space: nowrap; }
        .navbar .container { display: flex; justify-content: center; align-items: center; }
        .navbar-brands { font-size: 16px; white-space: nowrap; }

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
                <li class="active"><a href="brand.php">Brand</a></li>
                <li><a href="product.php">Product</a></li>
                <li><a href="category.php">Category</a></li>
                <li><a href="inventory.php">Inventory</a></li>
                <li><a href="sales.php">Sales</a></li>
                <li><a href="account.php">Account</a></li>
            </ul>
        </div>
    </div>
</nav>

<?php if ($isCashier): ?>
    <center>
        <div>
            <h4 style="color:red;">⚠️ Access Denied</h4>
            <p style="color:red;">You don’t have permission to view this table.</p>
        </div>
    </center>
<?php else: ?>

    <center>
         <!-- 🔍 SEARCH BAR -->
            <form method="GET" class="d-flex justify-content-end mb-3" style="max-width: 400px; margin: auto;">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control me-2" 
                    placeholder="Search brand, address, company, or status..." 
                    value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if (isset($_GET['search']) && $_GET['search'] != ''): ?>
                    <a href="brand.php" class="btn btn-secondary ms-2">Clear</a>
                <?php endif; ?>
            </form>
    </center>
<!-- BRAND FORM -->
<div class="container">
    <div class="box">
        <center><h2 style="color:black;">BRAND</h2></center>
        <form method="POST" class="form-inline text-center">
            <input type="hidden" name="brand_id" value="<?= isset($editData['brand_id']) ? $editData['brand_id'] : '' ?>">
            <input type="text" name="brand_name" class="form-control" placeholder="Brand Name" value="<?= isset($editData['brand_name']) ? $editData['brand_name'] : '' ?>" required>
            <input type="text" name="address" class="form-control" placeholder="Address" value="<?= isset($editData['address']) ? $editData['address'] : '' ?>" required>
            <input type="text" name="company" class="form-control" placeholder="Company" value="<?= isset($editData['company']) ? $editData['company'] : '' ?>" required>
            <input type="text" name="status" class="form-control" placeholder="Status (Active/Inactive)" value="<?= isset($editData['status']) ? $editData['status'] : '' ?>" required>

            <?php if ($editData): ?>
                <button type="submit" name="update_brand" class="btn btn-success">Update</button>
                <a href="brand.php" class="btn btn-secondary">Cancel</a>
            <?php else: ?>
                <button type="submit" name="add_brand" class="btn btn-primary">Add Brand</button>
            <?php endif; ?>
        </form>
          <br>
        <!-- BRAND TABLE / ACCESS CONTROL -->
        <div class="mt-4">

            <table class="table table-bordered table-hover text-center align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Brand Name</th>
                        <th>Address</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['brand_id'] ?></td>
                                <td><?= htmlspecialchars($row['brand_name']) ?></td>
                                <td><?= htmlspecialchars($row['address']) ?></td>
                                <td><?= htmlspecialchars($row['company']) ?></td>
                                <td><?= htmlspecialchars($row['status']) ?></td>
                                <td>
                                    <a href="?edit=<?= $row['brand_id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="?delete=<?= $row['brand_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this brand?')">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-danger">No records found.</td></tr>
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
<?php endif; ?>

<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>

</body>
</html>
