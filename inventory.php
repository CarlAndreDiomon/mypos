<?php 
session_start();
// ---------- DATABASE CONNECTION ----------
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "mypos";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ---------- FETCH PRODUCTS FOR DROPDOWN ----------
$product_query = "SELECT product_id, product_code, product_name FROM product ORDER BY product_name ASC";
$products_dropdown_result = $conn->query($product_query);

// ---------- ADD STOCK ----------
$message = "";
$editData = null;

if (isset($_POST['add_stock'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);

    if ($product_id && $quantity > 0) {
        $conn->begin_transaction();
        try {
            // ✅ Update product quantity
            $stmt = $conn->prepare("UPDATE product SET quantity = quantity + ? WHERE product_id = ?");
            $stmt->bind_param("ii", $quantity, $product_id);
            $stmt->execute();
            $stmt->close();

            // ✅ Update or insert inventory stock
            $check = $conn->prepare("SELECT inventory_id FROM inventory WHERE product_id = ?");
            $check->bind_param("i", $product_id);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows > 0) {
                $update = $conn->prepare("
                    UPDATE inventory 
                    SET stock = stock + ?, last_updated = NOW() 
                    WHERE product_id = ?
                ");
                $update->bind_param("ii", $quantity, $product_id);
                $update->execute();
                $update->close();
                $message = "<div class='alert alert-success text-center'>Stock added successfully!</div>";
            } else {
                $insert = $conn->prepare("
                    INSERT INTO inventory (product_id, stock, last_updated) 
                    VALUES (?, ?, NOW())
                ");
                $insert->bind_param("ii", $product_id, $quantity);
                $insert->execute();
                $insert->close();
                $message = "<div class='alert alert-success text-center'>New stock added successfully!</div>";
            }

            $check->close();
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='alert alert-danger text-center'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning text-center'>Please select a product and enter a valid quantity.</div>";
    }
}

// ---------- UPDATE STOCK ----------
if (isset($_POST['update_stock'])) {
    $inventory_id = intval($_POST['inventory_id']);
    $product_id   = intval($_POST['product_id']);
    $new_quantity = intval($_POST['quantity']);

    $conn->begin_transaction();
    try {
        // ✅ Update product quantity
        $stmt = $conn->prepare("UPDATE product SET quantity = ? WHERE product_id=?");
        $stmt->bind_param("ii", $new_quantity, $product_id);
        $stmt->execute();
        $stmt->close();

        // ✅ Update inventory stock
        $stmt = $conn->prepare("
            UPDATE inventory 
            SET stock = ?, last_updated = NOW() 
            WHERE inventory_id = ?
        ");
        $stmt->bind_param("ii", $new_quantity, $inventory_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $message = "<div class='alert alert-success text-center'>Stock updated successfully!</div>";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='alert alert-danger text-center'>Error updating stock: " . $e->getMessage() . "</div>";
    }
}

// ---------- DELETE STOCK ----------
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pre_delete_q = $conn->query("SELECT product_id FROM inventory WHERE inventory_id = {$id}");
    $pre_delete_data = $pre_delete_q->fetch_assoc();
    $pid = $pre_delete_data['product_id'] ?? 0;

    $stmt = $conn->prepare("DELETE FROM inventory WHERE inventory_id=?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($pid > 0) {
            // Reset product quantity to 0 if inventory deleted
            $product_reset = $conn->prepare("UPDATE product SET quantity=0 WHERE product_id=?");
            $product_reset->bind_param("i", $pid);
            $product_reset->execute();
            $product_reset->close();
        }
        echo "<div class='alert alert-warning text-center'>Stock deleted successfully!</div>";
        echo "<script>window.location.href = 'inventory.php';</script>";
    } else {
        echo "<div class='alert alert-danger text-center'>Error deleting stock: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// ---------- FETCH DATA FOR EDIT ----------
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $edit = $conn->query("SELECT * FROM inventory WHERE inventory_id=$id");
    if ($edit && $edit->num_rows > 0) $editData = $edit->fetch_assoc();
}

// ---------- FETCH INVENTORY DATA ----------
$sql = "SELECT 
    i.inventory_id, 
    i.stock, 
    i.last_updated AS date_purchased, 
    p.product_code, 
    p.product_name 
    FROM inventory i 
    JOIN product p ON i.product_id = p.product_id 
    ORDER BY i.inventory_id ASC";

$result = $conn->query($sql);
$isCashier = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'Cashier';
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"> 
<title>Inventory Business Shala </title>
<link href="css/bootstrap.min.css" rel="stylesheet">
<link href="css/business-casual.css" rel="stylesheet">
<style>
.navbar-nav > li > a { font-size: 19px; padding-left: 10px; padding-right: 10px; white-space: nowrap; }
.navbar-nav { flex-wrap: nowrap !important; white-space: nowrap; }
.navbar .container { display: flex; justify-content: center; align-items: center; }
.navbar-brands { font-size: 16px; white-space: nowrap; }
.box { background:#fff; padding:25px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.2); margin-top:20px; }
h2 { color:black; text-align:center; font-weight:bold; }
body { background-color:#343a40; }
.table th, .table td { text-align:center; vertical-align:middle !important; }
.label-success { background-color:#5cb85c; color:white; padding:5px; border-radius:4px; }
.label-danger { background-color:#d9534f; color:white; padding:5px; border-radius:4px; }
.table thead tr { background-color:#007bff; color:white; }
</style>
</head>
<body>

<div class="brand"> BUSINESS SHALA</div>
<div class="address-bar">3481 HOFELENIA SUBDIVISION | SIBALOM, ANTIQUE | 123.456.7890</div>

<nav class="navbar navbar-default">
<div class="container">
<ul class="nav navbar-nav text-center">
<li><a href="index.php">HOME</a></li>
<li><a href="brand.php">BRAND</a></li>
<li><a href="product.php">PRODUCT</a></li>
<li><a href="category.php">CATEGORY</a></li>
<li class="active"><a href="inventory.php">INVENTORY</a></li>
<li><a href="sales.php">SALES</a></li>
<li><a href="account.php">ACCOUNTS</a></li>
</ul>
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
<div class="container">
<div class="box">
<h2><?= $editData ? "EDIT STOCK" : "ADD STOCK" ?></h2>
<?= $message ?>
<form method="POST" class="form-inline text-center mb-3">
<input type="hidden" name="inventory_id" value="<?= $editData['inventory_id'] ?? '' ?>">
<div class="form-group">
<select name="product_id" class="form-control" required>
<option value="">-- Select Product --</option>
<?php
if ($products_dropdown_result && $products_dropdown_result->num_rows > 0) {
    $products_dropdown_result->data_seek(0);
    while ($p = $products_dropdown_result->fetch_assoc()) {
        $selected = ($editData && $p['product_id'] == $editData['product_id']) ? "selected" : "";
        echo "<option value='{$p['product_id']}' $selected>" . htmlspecialchars($p['product_name']) . " (" . htmlspecialchars($p['product_code']) . ")</option>";
    }
}
?>
</select>
</div>
<div class="form-group">
<input type="number" name="quantity" class="form-control" placeholder="Quantity" min="1" value="<?= $editData['stock'] ?? '' ?>" required>
</div>
<?php if ($editData): ?>
<button type="submit" name="update_stock" class="btn btn-warning">Update Stock</button>
<a href="inventory.php" class="btn btn-secondary">Cancel</a>
<?php else: ?>
<button type="submit" name="add_stock" class="btn btn-success">
<span class="glyphicon glyphicon-plus"></span> Add Stock
</button>
<?php endif; ?>
</form>

<h2>INVENTORY LIST</h2>
<div class="table-responsive">
<table class="table table-bordered table-hover text-center">
<thead>
<tr>
<th>Stock ID</th>
<th>Product Code</th>
<th>Product Name</th>
<th>Stock</th>
<th>Date Purchased</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $status = $row['stock'] > 0
            ? "<span class='label-success'>In Stock</span>"
            : "<span class='label-danger'>Out of Stock</span>";
        echo "
        <tr>
        <td>{$row['inventory_id']}</td>
        <td>{$row['product_code']}</td>
        <td>" . htmlspecialchars($row['product_name']) . "</td>
        <td>{$row['stock']}</td>
        <td>" . date('Y-m-d H:i:s', strtotime($row['date_purchased'])) . "</td>
        <td>{$status}</td>
        <td>
        <a href='?edit={$row['inventory_id']}' class='btn btn-warning btn-sm'>Edit</a>
        <a href='?delete={$row['inventory_id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Delete this stock?\")'>Delete</a>
        </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='7'>No inventory data found.</td></tr>";
}
?>
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
