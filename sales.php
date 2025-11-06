<?php
session_start();
// ---------- DATABASE CONNECTION ----------
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "mypos";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ---------- GENERATE RANDOM UNIQUE OR NUMBER ----------
function generateORNumber($conn) {
    do {
        $or_number = rand(1000, 99999); // Random number between 1000 and 99999
        $check = $conn->prepare("SELECT COUNT(*) FROM sales WHERE or_number = ?");
        $check->bind_param("i", $or_number);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();
    } while ($count > 0); // If exists, generate another one

    return $or_number;
}


// ---------- SESSION CART ----------
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// ---------- ADD ITEM ----------
$message = '';
if (isset($_POST['add_sale'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    $stmt = $conn->prepare("SELECT p.*, i.stock AS stock FROM product p 
                            LEFT JOIN inventory i ON p.product_id=i.product_id 
                            WHERE p.product_id=?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product || $product['stock'] <= 0) {
        $message = "Product is out of stock!";
    } else {
        $exists = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $product_id) {
                if ($item['quantity'] + $quantity > $product['stock']) {
                    $item['quantity'] = $product['stock'];
                    $message = "Quantity adjusted to available stock.";
                } else {
                    $item['quantity'] += $quantity;
                }
                $exists = true;
                break;
            }
        }
        if (!$exists) $_SESSION['cart'][] = ['product_id' => $product_id, 'quantity' => min($quantity, $product['stock'])];
    }

    header("Location: sales.php");
    exit();
}

// ---------- PLACE ORDER ----------
$receipt = null;
if (isset($_POST['place_order']) && !empty($_SESSION['cart'])) {
    $conn->begin_transaction();
    try {
        $or_number = generateORNumber($conn);
        $date = date("Y-m-d H:i:s");
        $status = 'Completed';
        $receipt_items = [];

        foreach ($_SESSION['cart'] as $cart_item) {
            $pid = (int)$cart_item['product_id'];
            $qty = (int)$cart_item['quantity'];

            // Lock product row for update
            $stmt = $conn->prepare("SELECT p.product_name, p.price, i.stock 
                                    FROM product p 
                                    LEFT JOIN inventory i ON p.product_id=i.product_id 
                                    WHERE p.product_id=? FOR UPDATE");
            $stmt->bind_param("i", $pid);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product || $product['stock'] < $qty) {
                throw new Exception("Insufficient stock for " . $product['product_name']);
            }

            $total = $product['price'] * $qty;

            // Insert sale record
            $stmt = $conn->prepare("INSERT INTO sales (product_id, quantity, total_amount, sale_date, or_number, status) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iidsis", $pid, $qty, $total, $date, $or_number, $status);
            $stmt->execute();
            $stmt->close();

            // Decrease stock in inventory (use 'stock', not 'quantity')
            $stmt = $conn->prepare("UPDATE inventory 
                                    SET stock = stock - ? 
                                    WHERE product_id=? AND stock >= ?");
            $stmt->bind_param("iii", $qty, $pid, $qty);
            $stmt->execute();
            $stmt->close();

            // Decrease stock in product
            $stmt = $conn->prepare("UPDATE product 
                                    SET quantity = quantity - ? 
                                    WHERE product_id=? AND quantity >= ?");
            $stmt->bind_param("iii", $qty, $pid, $qty);
            $stmt->execute();
            $stmt->close();

            $receipt_items[] = [
                'product_name' => $product['product_name'],
                'quantity' => $qty,
                'price' => $product['price'],
                'total' => $total
            ];
        }

        $conn->commit();

        $receipt = [
            'or_number' => $or_number,
            'sale_date' => $date,
            'items' => $receipt_items,
            'status' => $status
        ];

        $_SESSION['cart'] = [];
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='alert alert-danger text-center'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// ---------- DELETE OR (RESTORE STOCK) ----------
if (isset($_GET['delete_or'])) {
    $or_number = (int)$_GET['delete_or'];

    $conn->begin_transaction();
    try {
        $sales = $conn->prepare("SELECT product_id, quantity FROM sales WHERE or_number=?");
        $sales->bind_param("i", $or_number);
        $sales->execute();
        $result = $sales->get_result();

        while ($row = $result->fetch_assoc()) {
            $pid = (int)$row['product_id'];
            $qty = (int)$row['quantity'];

            // Restore stock in inventory
            $updateInv = $conn->prepare("UPDATE inventory SET stock = stock + ? WHERE product_id=?");
            $updateInv->bind_param("ii", $qty, $pid);
            $updateInv->execute();
            $updateInv->close();

            // Restore product quantity
            $updateProd = $conn->prepare("UPDATE product SET quantity = quantity + ? WHERE product_id=?");
            $updateProd->bind_param("ii", $qty, $pid);
            $updateProd->execute();
            $updateProd->close();
        }

        // Delete sales records
        $del = $conn->prepare("DELETE FROM sales WHERE or_number=?");
        $del->bind_param("i", $or_number);
        $del->execute();
        $del->close();

        $conn->commit();
        header("Location: sales.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='alert alert-danger text-center'>Error deleting OR: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// ---------- FETCH PRODUCTS ----------
$products = $conn->query("SELECT p.*, i.stock AS stock FROM product p 
                          LEFT JOIN inventory i ON p.product_id=i.product_id");
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sales Management</title>
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/business-casual.css">
<style>
.navbar-nav > li > a { font-size: 19px; padding-left: 10px; padding-right: 10px; white-space: nowrap; }
        .navbar-nav { flex-wrap: nowrap !important; white-space: nowrap; }
        .navbar .container { display: flex; justify-content: center; align-items: center; }
        .navbar-brands { font-size: 16px; white-space: nowrap; }
.table td, .table th { text-align:center; vertical-align:middle; }
.receipt-box, .history-box { background:#fff; padding:20px; border-radius:12px; box-shadow:0 0 10px rgba(0,0,0,0.2); margin-top:25px; }
.cart-box { background:#f8f9fa; padding:15px; border-radius:12px; margin-top:15px; }
.status-completed { color:green; font-weight:bold; }
.out-of-stock { color:red; font-weight:bold; }
.print-floating { 
    position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); 
    width:80%; max-height:90%; overflow-y:auto; background:#fff; padding:20px; 
    border-radius:12px; box-shadow:0 0 20px rgba(247, 242, 242, 0.5); z-index:9999;
}
@media print {
    body * { visibility: hidden; }
    .print-floating, .print-floating * { visibility: visible; }
    .print-floating { position:absolute; left:0; top:0; width:100%; }
    .page-break { page-break-after: always; }
}
</style>
</head>

<body>
<div class="brand">BUSINESS SHALA</div>
<div class="address-bar">Hofilenia Subdivision District 1, Sibalom Antique</div>

<nav class="navbar navbar-default">
<div class="container">
<ul class="nav navbar-nav">
<li><a href="index.php">Dashboard</a></li>
<li><a href="brand.php">Brand</a></li>
<li><a href="product.php">Product</a></li>
<li><a href="category.php">Category</a></li>
<li><a href="inventory.php">Inventory</a></li>
<li class="active"><a href="sales.php">Sales</a></li>
<li><a href="account.php">Account</a></li>
</ul>
</div>
</nav>
<?php if (isset($access_denied) && $access_denied): ?>
    <div class="container text-center mt-5">
        <div class="alert alert-danger">
            <h3>Access Denied</h3>
            <p>You do not have permission to access this page.</p>
        </div>
    </div>
<?php else: ?>
    <!-- Your normal page content or tables go here -->
<?php endif; ?>
s
<div class="container">

<!-- ADD ITEM FORM -->
<form method="POST" class="form-inline text-center mb-3">
<div class="form-group mb-2">
<label style= "color:white;">Product:</label>
<select name="product_id" class="form-control ml-2" required>
<option value="">Select Product</option>
<?php
while($row = $products->fetch_assoc()):
    $style = ($row['stock'] <= 0) ? "color:red;font-weight:bold;" : "";
?>
<option value="<?= $row['product_id'] ?>" style="<?= $style ?>" <?= $row['stock'] <= 0 ? 'disabled' : '' ?>>
<?= htmlspecialchars($row['product_name']) ?> - ₱<?= number_format($row['price'],2) ?> <?= $row['stock'] <= 0 ? '(Out of Stock)' : '' ?>
</option>
<?php endwhile; ?>
</select>
</div>
<div class="form-group mb-2 ml-3">
<label style= "color:white;">Quantity:</label>
<input type="number" name="quantity" class="form-control ml-2" min="1" required>
</div>
<button type="submit" name="add_sale" class="btn btn-success mb-2 ml-3">Add Item</button>
</form>

<!-- DISPLAY CART -->
<?php if(!empty($_SESSION['cart'])): ?>
<div class="cart-box">
<h4>Items in Cart:</h4>
<table class="table table-bordered">
<thead><tr><th>Product</th><th>Quantity</th></tr></thead>
<tbody>
<?php
foreach($_SESSION['cart'] as $item){
    $stmt = $conn->prepare("SELECT product_name FROM product WHERE product_id=?");
    $stmt->bind_param("i",$item['product_id']);
    $stmt->execute();
    $product_name = $stmt->get_result()->fetch_assoc()['product_name'];
    $stmt->close();
    echo "<tr><td>".htmlspecialchars($product_name)."</td><td>".$item['quantity']."</td></tr>";
}
?>
</tbody>
</table>
<form method="POST" class="text-center mt-2">
<button type="submit" name="place_order" class="btn btn-primary">Print Receipt</button>
<a href="sales.php" class="btn btn-secondary">Close</a>
</form>
</div>
<?php endif; ?>

<!-- RECEIPT BOX -->
<?php if($receipt): ?>
<div class="receipt-box print-area">
<h4 class="text-center text-primary"><strong>Business Shala</strong></h4>
<p class="text-center">Official Sales Receipt</p>
<hr>
<center><p><strong>OR Number:</strong> <?= $receipt['or_number'] ?></p></center>
<center><p><strong>Date:</strong> <?= $receipt['sale_date'] ?></p></center>
<table class="table table-bordered">
<thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th></tr></thead>
<tbody>
<?php foreach($receipt['items'] as $item): ?>
<tr>
<td><?= htmlspecialchars($item['product_name']) ?></td>
<td><?= $item['quantity'] ?></td>
<td>₱<?= number_format($item['price'],2) ?></td>
<td>₱<?= number_format($item['total'],2) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p><strong>Status:</strong> <?= $receipt['status'] ?></p>
<div class="text-center mt-3">
<a href="sales.php" class="btn btn-secondary">Close</a>
</div>
</div>
<?php endif; ?>

<!-- SALES HISTORY -->
<div class="history-box table-responsive mt-4">
<h3 class="intro-text text-center">Sales <strong>History</strong></h3>
<table class="table table-bordered table-striped">
<thead class="bg-primary text-white">
<tr>
<th>OR Number</th>
<th>Product</th>
<th>Quantity</th>
<th>Total</th>
<th>Date</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php
$sales_history = $conn->query("SELECT s.*, p.product_name FROM sales s LEFT JOIN product p ON s.product_id = p.product_id ORDER BY s.or_number ASC");
if($sales_history && $sales_history->num_rows>0):
    while($row = $sales_history->fetch_assoc()):
        $status_color = ($row['status'] == 'Completed') ? 'color:green;font-weight:bold;' : '';
?>
<tr>
<td><?= $row['or_number'] ?></td>
<td><?= htmlspecialchars($row['product_name'] ?? 'N/A') ?></td>
<td><?= $row['quantity'] ?></td>
<td>₱<?= number_format($row['total_amount'],2) ?></td>
<td><?= $row['sale_date'] ?></td>
<td style="<?= $status_color ?>"><?= $row['status'] ?></td>
<td>
<form method="GET" action="sales.php">
<input type="hidden" name="delete_or" value="<?= $row['or_number'] ?>">
<button type="submit" class="btn btn-danger btn-sm">Delete</button>
</form>
</td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="7">No sales found.</td></tr>
<?php endif; ?>
</tbody>
</table>
<div class="text-center mt-2">
<button class="btn btn-info" onclick="showPrintHistory()">Print Full Sales History</button>
</div>
</div>

<!-- FLOATING PRINT HISTORY -->
<div id="printHistoryModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
  <div id="printHistoryContent" style="background:white; padding:25px; width:90%; max-height:90%; overflow-y:auto; border-radius:10px;">
  <center>  <h2 style="color:brown;">Business Shala</h2></center>
    <h5 class="text-center mb-4">Full Sales History Report</h5>
    <table class="table table-bordered">
      <thead class="bg-primary text-white">
        <tr >
          <th>OR Number</th>
          <th>Product</th>
          <th>Quantity</th>
          <th>Total</th>
          <th>Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sales_data = $conn->query("SELECT s.*, p.product_name FROM sales s LEFT JOIN product p ON s.product_id=p.product_id ORDER BY s.or_number ASC");
        if ($sales_data && $sales_data->num_rows>0):
            $count=0; $pageTotal=0; $grandTotal=0;
            while($row = $sales_data->fetch_assoc()):
                $count++;
                $pageTotal += $row['total_amount'];
                $grandTotal += $row['total_amount'];
        ?>
        <tr>
          <td><?= $row['or_number'] ?></td>
          <td><?= htmlspecialchars($row['product_name']) ?></td>
          <td><?= $row['quantity'] ?></td>
          <td>₱<?= number_format($row['total_amount'],2) ?></td>
          <td><?= $row['sale_date'] ?></td>
          <td class="<?= $row['status']=='Completed'?'status-completed':'' ?>"><?= $row['status'] ?></td>
        </tr>
        <?php if($count % 10 == 0): ?>
        <tr class="table-secondary">
          <td colspan="6" class="text-end"><strong>Page Total: ₱<?= number_format($pageTotal,2) ?></strong></td>
        </tr>
        <tr><td colspan="6"><div class="page-break"></div></td></tr>
        <?php $pageTotal=0; endif; endwhile; ?>
        <tr class="table-secondary">
          <td colspan="6" class="text-end"><strong>Final Total: ₱<?= number_format($grandTotal,2) ?></strong></td>
        </tr>
        <?php else: ?>
        <tr><td colspan="6">No sales history found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="text-center mt-3 no-print">
      <button class="btn btn-primary" onclick="window.print()">Print</button>
      <button class="btn btn-secondary" onclick="closePrintHistory()">Close</button>
    </div>
  </div>
</div>

<script>
function showPrintHistory() {
  document.getElementById('printHistoryModal').style.display = 'flex';
}
function closePrintHistory() {
  document.getElementById('printHistoryModal').style.display = 'none';
}

// ----- PRINT STYLES -----
const style = document.createElement('style');
style.innerHTML = `
@media print {
  body * { visibility: hidden; }
  #printHistoryContent, #printHistoryContent * { visibility: visible; }
  #printHistoryContent {
      position: absolute; 
      left: 0; 
      top: 0; 
      width: 100%;
      background: white;
      box-shadow: none;
  }
  #printHistoryModal { background: none !important; }
  .no-print { display: none !important; }
}
`;
document.head.appendChild(style);
</script>


<script>
function showPrintHistory() {
  document.getElementById('printHistoryModal').style.display = 'flex';
}
function closePrintHistory() {
  document.getElementById('printHistoryModal').style.display = 'none';
}
const style = document.createElement('style');
style.innerHTML = `
@media print {
  body * { visibility: hidden; }
  #printHistoryContent, #printHistoryContent * { visibility: visible; }
  #printHistoryContent { position: absolute; left: 0; top: 0; width: 100%; }
}`;
document.head.appendChild(style);
</script>

<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>
</body>
</html>
