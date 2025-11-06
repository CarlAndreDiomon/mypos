<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "mypos";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$search = isset($_GET['query']) ? trim($_GET['query']) : '';

$sql = "
    SELECT p.*, b.brand_name, c.category_name
    FROM product p
    LEFT JOIN brands b ON p.brand_id = b.brand_id
    LEFT JOIN category c ON p.category_id = c.category_id
";

if (!empty($search)) {
    $safeSearch = $conn->real_escape_string($search);
    $sql .= " WHERE p.product_name LIKE '%$safeSearch%'
              OR p.product_code LIKE '%$safeSearch%'
              OR b.brand_name LIKE '%$safeSearch%'
              OR c.category_name LIKE '%$safeSearch%'";
}

$sql .= " ORDER BY p.product_id ASC";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['product_id']}</td>
                <td>".htmlspecialchars($row['product_code'])."</td>
                <td>".htmlspecialchars($row['product_name'])."</td>
                <td>".htmlspecialchars($row['brand_name'])."</td>
                <td>".htmlspecialchars($row['category_name'])."</td>
                <td>₱".number_format($row['price'],2)."</td>
                <td><a href='?delete={$row['product_id']}' class='btn btn-danger btn-sm' onclick=\"return confirm('Delete this product?')\">🗑</a></td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='7'>No products found.</td></tr>";
}

$conn->close();
?>
