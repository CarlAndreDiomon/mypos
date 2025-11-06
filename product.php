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

// ---------- FETCH BRANDS ----------
$brands = $conn->query("SELECT brand_id, brand_name FROM brands WHERE status='Active'");

// ---------- FETCH CATEGORIES ----------
$categories = $conn->query("SELECT category_id, category_name FROM category WHERE status='Active'");

// ---------- ADD PRODUCT ----------
if (isset($_POST['add_product'])) {
    $product_code = trim($_POST['product_code']);
    $product_name = trim($_POST['product_name']);
    $price        = floatval($_POST['price']);
    $brand_id     = intval($_POST['brand_id']);
    $category_id  = intval($_POST['category_id']);
    $description  = trim($_POST['description']);
    $status       = trim($_POST['status']);
    $impath       = ""; // no image at add time

    if (!empty($product_name) && !empty($product_code) && $price > 0 && $brand_id > 0 && $category_id > 0) {
        $stmt = $conn->prepare("INSERT INTO product (product_code, product_name, price, brand_id, category_id, description, status, impath) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdiisss", $product_code, $product_name, $price, $brand_id, $category_id, $description, $status, $impath);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Product added successfully!";
        } else {
            $_SESSION['error'] = "Error adding product: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Please fill out all required fields.";
    }
    header("Location: product.php");
    exit();
}

// ---------- UPDATE IMAGE ----------
if (isset($_POST['update_image'])) {
    $id = intval($_POST['product_id']);
    if (!empty($_FILES['new_image']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileName = time() . "_" . basename($_FILES["new_image"]["name"]);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES["new_image"]["tmp_name"], $targetFile)) {
            $stmt = $conn->prepare("UPDATE product SET impath=? WHERE product_id=?");
            $stmt->bind_param("si", $targetFile, $id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header("Location: product.php");
    exit();
}

// ---------- UPDATE PRODUCT ----------
if (isset($_POST['update_product'])) {
    $id           = intval($_POST['product_id']);
    $product_code = trim($_POST['product_code']);
    $product_name = trim($_POST['product_name']);
    $price        = floatval($_POST['price']);
    $brand_id     = intval($_POST['brand_id']);
    $category_id  = intval($_POST['category_id']);
    $description  = trim($_POST['description']);
    $status       = trim($_POST['status']);

    $stmt = $conn->prepare("UPDATE product 
                            SET product_code=?, product_name=?, price=?, brand_id=?, category_id=?, description=?, status=? 
                            WHERE product_id=?");
    $stmt->bind_param("ssdiissi", $product_code, $product_name, $price, $brand_id, $category_id, $description, $status, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: product.php");
    exit();
}

// ---------- DELETE PRODUCT ----------
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM product WHERE product_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: product.php");
    exit();
}
// ---------- SEARCH ----------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchQuery = "";
if (!empty($search)) {
    $searchQuery = "WHERE p.product_name LIKE '%$search%' 
                    OR p.product_code LIKE '%$search%' 
                    OR b.brand_name LIKE '%$search%' 
                    OR c.category_name LIKE '%$search%'";
}

// ---------- FETCH PRODUCTS (WITH SEARCH SUPPORT) ----------
$sql = "
    SELECT p.*, b.brand_name, c.category_name
    FROM product p
    LEFT JOIN brands b ON p.brand_id = b.brand_id
    LEFT JOIN category c ON p.category_id = c.category_id
    $searchQuery
    ORDER BY p.product_id ASC
";
$result = $conn->query($sql);

// ---------- CHECK USER TYPE ----------
// ---------- CHECK USER TYPE ----------
$isCashier = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'Cashier';
$isStaff   = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'Staff';
$isAdmin   = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'Admin';

// Get current file name (e.g. "sales.php", "product.php")
$currentPage = basename($_SERVER['PHP_SELF']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Product | Business Shala</title>
<link href="css/bootstrap.min.css" rel="stylesheet">
<link href="css/business-casual.css" rel="stylesheet">
<style>
    .navbar-nav > li > a {
        font-size: 19px;
        padding-left: 10px;
        padding-right: 10px;
        white-space: nowrap;
    }
    .navbar-nav { flex-wrap: nowrap !important; white-space: nowrap; }
    .navbar .container { display: flex; justify-content: center; align-items: center; }
    .navbar-product { font-size: 16px; white-space: nowrap; }

    .box {
        background: #fff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        margin-top: 20px;
    }
    body { background-color: #343a40; }
    h2 { color: black; }
    footer p { color: white; }
</style>
</head>
<body>

<center><h1 style="color: white;font-size: 70px;">Business Shala</h1></center>
<h2 class="address-bar">Hofelenia, Subdivision District 1, Sibalom Antique</h2>

<nav class="navbar navbar-default">
    <div class="container">
        <ul class="nav navbar-nav text-center">
            <li><a href="index.php">HOME</a></li>
            <li><a href="brand.php">Brand</a></li>
            <li class="active"><a href="product.php">Product</a></li>
            <li><a href="category.php">Category</a></li>
            <li><a href="inventory.php">Inventory</a></li>
            <li><a href="sales.php">Sales</a></li>
            <li><a href="account.php">Account</a></li>
        </ul>
    </div>
</nav>
<center>
  <?php if (isset($_SESSION['login_message'])): ?>
    <div style="color:lightblue;font-weight: bold;">
        <?= htmlspecialchars($_SESSION['login_message']); ?>
    </div>
    <?php unset($_SESSION['login_message']); ?>
<?php endif; ?>
</center>

<?php
// If Staff tries to open any page except product.php → show Access Denied
$currentPage = basename($_SERVER['PHP_SELF']); // e.g. "sales.php"
if ($isCashier && $currentPage !== 'sales.php') {
?>
    <center>
        <div>
            <h4 style="color:red;">⚠️ Access Denied</h4>
            <p style="color:red;">You don’t have permission to access this page.</p>
        </div>
    </center>
<?php
} elseif ($isStaff && $currentPage !== 'product.php') {
?>
    <center>
        <div>
            <h4 style="color:red;">⚠️ Access Denied</h4>
            <p style="color:red;">You don’t have permission to access this page.</p>
        </div>
    </center>
<?php
} else {
?>


<div class="container mt-4">
<?php if(isset($_SESSION['message'])): ?>
    <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
<?php elseif(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>
</div>
 <?php
// Detect if a search is active
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
?>

<!-- 🔍 SEARCH + ADD BUTTON -->
<form method="GET" class="form-inline text-center mb-3">
  <input type="text" name="search" class="form-control" placeholder="Search product..." 
         value="<?= htmlspecialchars($search) ?>">

  <?php if (!empty($search)): ?>
      <!-- 🔴 Clear button when search is active -->
      <a href="product.php" class="btn btn-danger">Clear</a>
  <?php else: ?>
      <!-- 🟢 Search button when no search -->
      <button type="submit" class="btn btn-success">Search</button>
  <?php endif; ?>

  <!-- 🔵 Add Product button -->
  <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addProductModal">+ Add Product</button>
</form>

<div class="container">
    <div class="box">
        <h2 class="intro-text text-center">PRODUCT</h2>

        <!-- ADD PRODUCT MODAL -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <label>Product Code</label>
                    <input type="text" name="product_code" class="form-control mb-2" required>

                    <label>Product Name</label>
                    <input type="text" name="product_name" class="form-control mb-2" required>

                    <label>Price</label>
                    <input type="number" step="0.01" name="price" class="form-control mb-2" required>

                    <label>Brand</label>
                    <select name="brand_id" class="form-control mb-2" required>
                        <option value="">Select Brand</option>
                        <?php
                        $bresult = $conn->query("SELECT * FROM brands WHERE status='Active'");
                        while($b=$bresult->fetch_assoc()):
                        ?>
                        <option value="<?= $b['brand_id'] ?>"><?= htmlspecialchars($b['brand_name']) ?></option>
                        <?php endwhile; ?>
                    </select>

                    <label>Category</label>
                    <select name="category_id" class="form-control mb-2" required>
                        <option value="">Select Category</option>
                        <?php
                        $cresult = $conn->query("SELECT * FROM category WHERE status='Active'");
                        while($c=$cresult->fetch_assoc()):
                        ?>
                        <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                        <?php endwhile; ?>
                    </select>

                    <label>Description</label>
                    <input type="text" name="description" class="form-control mb-2" required>

                    <label>Status</label>
                    <input type="text" name="status" class="form-control mb-2" value="Active" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
     <br>
        <!-- product TABLE -->
        <table class="table table-bordered table-hover text-center align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Brand</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row=$result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['product_id'] ?></td>
                    <td><?= htmlspecialchars($row['product_code']) ?></td>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><?= htmlspecialchars($row['brand_name']) ?></td>
                    <td><?= htmlspecialchars($row['category_name']) ?></td>
                    <td>₱<?= number_format($row['price'],2) ?></td>
                    <td>
                        <button class="btn btn-info btn-sm" data-toggle="modal" data-target="#viewModal<?= $row['product_id'] ?>">View</button>
                        <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editModal<?= $row['product_id'] ?>">Edit</button>
                        <a href="?delete=<?= $row['product_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this product?')">Delete</a>
                    </td>
                </tr>

                <!-- VIEW MODAL -->
                <div class="modal fade" id="viewModal<?= $row['product_id'] ?>" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Product Details</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body text-center">
                                <img src="<?= (!empty($row['impath']) && file_exists($row['impath']) ? htmlspecialchars($row['impath']) : 'images/no-image.jpg') ?>" 
                                     alt="Product Image" class="img-fluid mb-3" style="max-width:200px; border-radius:10px;">
                                <form method="POST" enctype="multipart/form-data" class="mb-3">
                                    <input type="hidden" name="product_id" value="<?= $row['product_id'] ?>">
                                    <label>Change Product Image</label>
                                    <input type="file" name="new_image" class="form-control mb-2" accept="image/*" required>
                                    <button type="submit" name="update_image" class="btn btn-primary btn-sm">Update Image</button>
                                </form>
                                <hr>
                                <p><strong>Product Code:</strong> <?= htmlspecialchars($row['product_code']) ?></p>
                                <p><strong>Name:</strong> <?= htmlspecialchars($row['product_name']) ?></p>
                                <p><strong>Brand:</strong> <?= htmlspecialchars($row['brand_name']) ?></p>
                                <p><strong>Category:</strong> <?= htmlspecialchars($row['category_name']) ?></p>
                                <p><strong>Price:</strong> ₱<?= number_format($row['price'],2) ?></p>
                                <p><strong>Description:</strong> <?= htmlspecialchars($row['description']) ?></p>
                                <p><strong>Status:</strong> <?= htmlspecialchars($row['status']) ?></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- EDIT MODAL -->
                <div class="modal fade" id="editModal<?= $row['product_id'] ?>" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Product</h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="product_id" value="<?= $row['product_id'] ?>">

                                    <label>Product Code</label>
                                    <input type="text" name="product_code" class="form-control mb-2" value="<?= htmlspecialchars($row['product_code']) ?>" readonly>

                                    <label>Product Name</label>
                                    <input type="text" name="product_name" class="form-control mb-2" value="<?= htmlspecialchars($row['product_name']) ?>" required>

                                    <label>Price</label>
                                    <input type="number" step="0.01" name="price" class="form-control mb-2" value="<?= htmlspecialchars($row['price']) ?>" required>

                                    <label>Brand</label>
                                    <select name="brand_id" class="form-control mb-2" required>
                                        <?php
                                        $brandResult = $conn->query("SELECT brand_id, brand_name FROM brands WHERE status='Active'");
                                        while ($b = $brandResult->fetch_assoc()):
                                            $selected = ($b['brand_id'] == $row['brand_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $b['brand_id'] ?>" <?= $selected ?>><?= htmlspecialchars($b['brand_name']) ?></option>
                                        <?php endwhile; $brandResult->free(); ?>
                                    </select>

                                    <label>Category</label>
                                    <select name="category_id" class="form-control mb-2" required>
                                        <?php
                                        $categoryResult = $conn->query("SELECT category_id, category_name FROM category WHERE status='Active'");
                                        while ($c = $categoryResult->fetch_assoc()):
                                            $selected = ($c['category_id'] == $row['category_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $c['category_id'] ?>" <?= $selected ?>><?= htmlspecialchars($c['category_name']) ?></option>
                                        <?php endwhile; $categoryResult->free(); ?>
                                    </select>

                                    <label>Description</label>
                                    <input type="text" name="description" class="form-control mb-2" value="<?= htmlspecialchars($row['description']) ?>" required>

                                    <label>Status</label>
                                    <input type="text" name="status" class="form-control mb-2" value="<?= htmlspecialchars($row['status']) ?>" required>
                                </div>

                                <div class="modal-footer">
                                    <button type="submit" name="update_product" class="btn btn-success">Save Changes</button>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </tbody>
        </table>
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