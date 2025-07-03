<?php
// pos_app.php

// DB connection
$host = 'localhost';
$db   = 'pos';
$user = 'root';
$pass = '';
$charset = 'utf8';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Messages
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add User
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'] ?? 'staff';

        if ($username && $_POST['password']) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$username, $password, $full_name, $email, $role]);
                $success = "User added successfully.";
            } catch (Exception $e) {
                $error = "Error adding user: " . $e->getMessage();
            }
        } else {
            $error = "Username and Password are required.";
        }
    }

    // Add Client
    if (isset($_POST['add_client'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);

        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO clients (name, phone, email, address) VALUES (?, ?, ?, ?)");
            try {
                $stmt->execute([$name, $phone, $email, $address]);
                $success = "Client added successfully.";
            } catch (Exception $e) {
                $error = "Error adding client: " . $e->getMessage();
            }
        } else {
            $error = "Client name is required.";
        }
    }

    // Add Product
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $stock_qty = intval($_POST['stock_qty']);

        if ($name && $price >= 0) {
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_qty) VALUES (?, ?, ?, ?)");
            try {
                $stmt->execute([$name, $description, $price, $stock_qty]);
                $success = "Product added successfully.";
            } catch (Exception $e) {
                $error = "Error adding product: " . $e->getMessage();
            }
        } else {
            $error = "Product name and valid price are required.";
        }
    }

    // Add Purchase
    if (isset($_POST['add_purchase'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $purchase_price = floatval($_POST['purchase_price']);
        $purchase_date = $_POST['purchase_date'];

        if ($product_id && $quantity > 0 && $purchase_price >= 0 && $purchase_date) {
            $stmt = $pdo->prepare("INSERT INTO purchases (product_id, quantity, purchase_price, purchase_date) VALUES (?, ?, ?, ?)");
            try {
                $pdo->beginTransaction();
                $stmt->execute([$product_id, $quantity, $purchase_price, $purchase_date]);
                // Update stock
                $stmt2 = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
                $stmt2->execute([$quantity, $product_id]);
                $pdo->commit();
                $success = "Purchase recorded and stock updated.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error adding purchase: " . $e->getMessage();
            }
        } else {
            $error = "All purchase fields are required and quantity must be positive.";
        }
    }

    // Add Sale (simplified: no multiple items, just total sale for one client)
    if (isset($_POST['add_sale'])) {
        $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
        $sale_date = $_POST['sale_date'];
        $total_amount = floatval($_POST['total_amount']);

        if ($sale_date && $total_amount >= 0) {
            $stmt = $pdo->prepare("INSERT INTO sales (client_id, sale_date, total_amount) VALUES (?, ?, ?)");
            try {
                $stmt->execute([$client_id, $sale_date, $total_amount]);
                $success = "Sale recorded successfully.";
            } catch (Exception $e) {
                $error = "Error adding sale: " . $e->getMessage();
            }
        } else {
            $error = "Sale date and total amount are required.";
        }
    }

    // Add Stock (direct update)
    if (isset($_POST['add_stock'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);

        if ($product_id && $quantity >= 0) {
            try {
                $pdo->beginTransaction();
                $stmt_check = $pdo->prepare("SELECT id FROM stock WHERE product_id = ?");
                $stmt_check->execute([$product_id]);
                if ($stmt_check->rowCount()) {
                    $stmt_update = $pdo->prepare("UPDATE stock SET quantity = quantity + ? WHERE product_id = ?");
                    $stmt_update->execute([$quantity, $product_id]);
                } else {
                    $stmt_insert = $pdo->prepare("INSERT INTO stock (product_id, quantity) VALUES (?, ?)");
                    $stmt_insert->execute([$product_id, $quantity]);
                }
                // Also update products table stock_qty for consistency
                $stmt_prod = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
                $stmt_prod->execute([$quantity, $product_id]);

                $pdo->commit();
                $success = "Stock updated successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error updating stock: " . $e->getMessage();
            }
        } else {
            $error = "Product and quantity are required.";
        }
    }
}

// Fetch data for tables and dropdowns
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$clients = $pdo->query("SELECT * FROM clients ORDER BY id DESC")->fetchAll();
$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
$purchases = $pdo->query("SELECT p.*, pr.name AS product_name FROM purchases p JOIN products pr ON p.product_id = pr.id ORDER BY p.id DESC")->fetchAll();
$sales = $pdo->query("SELECT s.*, c.name AS client_name FROM sales s LEFT JOIN clients c ON s.client_id = c.id ORDER BY s.id DESC")->fetchAll();
$stock = $pdo->query("SELECT st.*, pr.name AS product_name FROM stock st JOIN products pr ON st.product_id = pr.id ORDER BY st.id DESC")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>POS System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body>
<div class="container my-4">
    <h1 class="mb-4 text-center">Simple POS System</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4" id="posTab" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">Users</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="clients-tab" data-bs-toggle="tab" data-bs-target="#clients" type="button" role="tab">Clients</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">Products</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchases" type="button" role="tab">Purchases</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button" role="tab">Sales</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button" role="tab">Stock</button></li>
    </ul>

    <div class="tab-content" id="posTabContent">

        <!-- Users Tab -->
        <div class="tab-pane fade show active" id="users" role="tabpanel" aria-labelledby="users-tab">
            <h3>Users</h3>
            <form method="POST" class="row g-3 mb-4">
                <input type="hidden" name="add_user" value="1" />
                <div class="col-md-3">
                    <label class="form-label">Username *</label>
                    <input type="text" class="form-control" name="username" required />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password *</label>
                    <input type="password" class="form-control" name="password" required />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="staff" selected>Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="col-md-3 align-self-end">
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>

            <table class="table table-striped">
                <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Created At</th></tr></thead>
                <tbody>
                <?php foreach($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= $u['role'] ?></td>
                        <td><?= $u['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Clients Tab -->
        <div class="tab-pane fade" id="clients" role="tabpanel" aria-labelledby="clients-tab">
            <h3>Clients</h3>
            <form method="POST" class="row g-3 mb-4">
                <input type="hidden" name="add_client" value="1" />
                <div class="col-md-4">
                    <label class="form-label">Name *</label>
                    <input type="text" class="form-control" name="name" required />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" name="phone" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" />
                </div>
                <div class="col-md-8">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="address" rows="2"></textarea>
                </div>
                <div class="col-md-4 align-self-end">
                    <button type="submit" class="btn btn-primary">Add Client</button>
                </div>
            </form>

            <table class="table table-striped">
                <thead><tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>Created At</th></tr></thead>
                <tbody>
                <?php foreach($clients as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td><?= htmlspecialchars($c['name']) ?></td>
                        <td><?= htmlspecialchars($c['phone']) ?></td>
                        <td><?= htmlspecialchars($c['email']) ?></td>
                        <td><?= htmlspecialchars($c['address']) ?></td>
                        <td><?= $c['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Products Tab -->
        <div class="tab-pane fade" id="products" role="tabpanel" aria-labelledby="products-tab">
            <h3>Products</h3>
            <form method="POST" class="row g-3 mb-4">
                <input type="hidden" name="add_product" value="1" />
                <div class="col-md-4">
                    <label class="form-label">Name *</label>
                    <input type="text" class="form-control" name="name" required />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2"></textarea>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Price *</label>
                    <input type="number" step="0.01" class="form-control" name="price" required />
                </div>
                <div class="col-md-2">
                    <label class="form-label">Stock Qty</label>
                    <input type="number" class="form-control" name="stock_qty" value="0" min="0" />
                </div>
                <div class="col-md-2 align-self-end">
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </div>
            </form>

            <table class="table table-striped">
                <thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Stock Qty</th><th>Created At</th></tr></thead>
                <tbody>
                <?php foreach($products as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars($p['description']) ?></td>
                        <td><?= number_format($p['price'], 2) ?></td>
                        <td><?= $p['stock_qty'] ?></td>
                        <td><?= $p['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Purchases Tab -->
        <div class="tab-pane fade" id="purchases" role="tabpanel" aria-labelledby="purchases-tab">
            <h3>Purchases</h3>
            <form method="POST" class="row g-3 mb-4">
                <input type="hidden" name="add_purchase" value="1" />
                <div class="col-md-3">
                    <label class="form-label">Product *</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">Select Product</option>
                        <?php foreach($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity *</label>
                    <input type="number" min="1" name="quantity" class="form-control" required />
                </div>
                <div class="col-md-2">
                    <label class="form-label">Purchase Price *</label>
                    <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" required />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Purchase Date *</label>
                    <input type="date" name="purchase_date" class="form-control" required />
                </div>
                <div class="col-md-2 align-self-end">
                    <button type="submit" class="btn btn-primary">Add Purchase</button>
                </div>
            </form>

            <table class="table table-striped">
                <thead><tr><th>ID</th><th>Product</th><th>Quantity</th><th>Price</th><th>Date</th><th>Created At</th></tr></thead>
                <tbody>
                <?php foreach($purchases as $pu): ?>
                    <tr>
                        <td><?= $pu['id'] ?></td>
                        <td><?= htmlspecialchars($pu['product_name']) ?></td>
                        <td><?= $pu['quantity'] ?></td>
                        <td><?= number_format($pu['purchase_price'], 2) ?></td>
                        <td><?= $pu['purchase_date'] ?></td>
                        <td><?= $pu['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Sales Tab -->
        <div class="tab-pane fade" id="sales" role="tabpanel" aria-labelledby="sales-tab">
            <h3>Sales</h3>
            <form method="POST" class="row g-3 mb-4">
                <input type="hidden" name="add_sale" value="1" />
                <div class="col-md-3">
                    <label class="form-label">Client</label>
                    <select name="client_id" class="form-select">
                        <option value="">Walk-in / No Client</option>
                        <?php foreach($clients as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sale Date *</label>
                    <input type="date" name="sale_date" class="form-control" required />
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total Amount *</label>
                    <input type="number" step="0.01" min="0" name="total_amount" class="form-control" required />
                </div>
                <div class="col-md-3 align-self-end">
                    <button type="submit" class="btn btn-primary">Add Sale</button>
                </div>
            </form>

            <table class="table table-striped">
                <thead><tr><th>ID</th><th>Client</th><th>Sale Date</th><th>Total Amount</th><th>Created At</th></tr></thead>
                <tbody>
                <?php foreach($sales as $s): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td><?= htmlspecialchars($s['client_name'] ?? 'Walk-in') ?></td>
                        <td><?= $s['sale_date'] ?></td>
                        <td><?= number_format($s['total_amount'], 2) ?></td>
                        <td><?= $s['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Stock Tab -->
        <div class="tab-pane fade" id="stock" role="tabpanel" aria-labelledby="stock-tab">
            <h3>Stock</h3>
            <form method="POST" class="row g-3 mb-4">
                <input type="hidden" name="add_stock" value="1" />
                <div class="col-md-6">
                    <label class="form-label">Product *</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">Select Product</option>
                        <?php foreach($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Quantity to Add *</label>
                    <input type="number" name="quantity" min="0" class="form-control" required />
                </div>
                <div class="col-md-2 align-self-end">
                    <button type="submit" class="btn btn-primary">Update Stock</button>
                </div>
            </form>

            <table class="table table-striped">
                <thead><tr><th>ID</th><th>Product</th><th>Quantity</th><th>Last Updated</th></tr></thead>
                <tbody>
                <?php foreach($stock as $st): ?>
                    <tr>
                        <td><?= $st['id'] ?></td>
                        <td><?= htmlspecialchars($st['product_name']) ?></td>
                        <td><?= $st['quantity'] ?></td>
                        <td><?= $st['last_updated'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
