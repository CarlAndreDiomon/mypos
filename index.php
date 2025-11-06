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

// ---------- FETCH SALES DATA ----------
$query = "SELECT sale_date, SUM(total_amount) AS total_sales FROM sales GROUP BY sale_date ORDER BY sale_date ASC";
$result = $conn->query($query);

$sale_dates = [];
$total_sales = [];

if ($result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $sale_dates[] = $row['sale_date'];
    $total_sales[] = $row['total_sales'];
  }
}

// ---------- SALES SUMMARY ----------
$summary_query = "SELECT 
  COUNT(*) AS total_transactions, 
  SUM(total_amount) AS total_revenue,
  AVG(total_amount) AS avg_sales
  FROM sales";
$summary_result = $conn->query($summary_query);
$summary = $summary_result->fetch_assoc();
$isCashier = isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'Cashier';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sales Dashboard | MyPOS</title>
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/business-casual.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    body { background-color: #343a40; color: white; }
    .brand { color: white; }
    .address-bar { color: #ddd; }

    /* --- RESTORED ORIGINAL NAVBAR DESIGN --- */
    .navbar-nav > li > a {
      font-size: 19px;
      padding-left: 10px;
      padding-right: 10px;
      color: black !important;
      white-space: nowrap;
    }
    .navbar-nav { flex-wrap: nowrap !important; white-space: nowrap; }
    .navbar .container { display: flex; justify-content: center; align-items: center; }
    .navbar-brand { font-size: 16px; white-space: nowrap; }
    .navbar-default {
      background-color: #ffffff;
      border: none;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .navbar-nav > li.active > a {
      background-color: #f8f9fa !important;
      border-radius: 6px;
    }

    /* --- DASHBOARD BOX --- */
    .box {
      background: #fff;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.3);
      margin-top: 20px;
      color: black;
    }
    canvas {
      background: white;
      border-radius: 10px;
      padding: 10px;
    }

    /* --- SUMMARY BOXES --- */
    .summary-card {
      background: #f8f9fa;
      color: #212529;
      border-radius: 10px;
      padding: 20px;
      margin-top: 15px;
      text-align: center;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    footer p { color: white; }
  </style>
</head>

<body>

<div class="brand">BUSINESS SHALA</div>
<div class="address-bar">Hofelenia Subdivision, District 1, Sibalom Antique</div>

<!-- NAVBAR -->
<nav class="navbar navbar-default" role="navigation">
  <div class="container">
    <div class="collapse navbar-collapse">
      <ul class="nav navbar-nav text-center">
        <li class="active"><a href="index.php">HOME</a></li>
        <li><a href="brand.php">Brand</a></li>
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
                    <div">
                    <h4 style="color:red;">⚠️ Access Denied</h4>
                    <p style="color:red;">You don’t have permission to view this table.</p>
                </div>
                </center>
<?php else: ?>

<!-- MAIN DASHBOARD -->
<div class="container">
  <div class="box">
    <center><h2>📊 Sales Performance Overview</h2></center>
    <canvas id="salesChart" height="120"></canvas>
  </div>

  <!-- SALES SUMMARY -->
  <div class="row mt-4">
    <div class="col-md-4">
      <div class="summary-card">
        <h4>Total Transactions</h4>
        <h2><?= number_format($summary['total_transactions']) ?></h2>
      </div>
    </div>
    <div class="col-md-4">
      <div class="summary-card">
        <h4>Total Revenue</h4>
        <h2>₱<?= number_format($summary['total_revenue'], 2) ?></h2>
      </div>
    </div>
    <div class="col-md-4">
      <div class="summary-card">
        <h4>Average Sale</h4>
        <h2>₱<?= number_format($summary['avg_sales'], 2) ?></h2>
      </div>
    </div>
  </div>
</div>
<br>
<footer>
  <div class="container text-center">
    <p style="color:black;">© MyPOS System 2025</p>
  </div>
</footer>

<script src="js/jquery.js"></script>
<script src="js/bootstrap.min.js"></script>
<script>
const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?php echo json_encode($sale_dates); ?>,
    datasets: [{
      label: 'Total Sales (₱)',
      data: <?php echo json_encode($total_sales); ?>,
      borderColor: '#007bff',
      backgroundColor: 'rgba(0, 123, 255, 0.15)',
      fill: true,
      tension: 0.4,
      borderWidth: 3,
      pointBackgroundColor: '#007bff',
      pointRadius: 4
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: true, position: 'top' },
      title: { display: true, text: 'Daily Total Sales', font: { size: 16 } }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          callback: function(value) { return '₱' + value.toLocaleString(); }
        }
      }
    }
  }
});
</script>
<?php endif; ?>
</body>
</html>

