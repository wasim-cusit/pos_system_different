<?php
session_start();

// DB connection
$host = 'localhost';
$db   = 'pos';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: pos_app.php");
    exit;
}

// Check login
$logged_in = false;
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        $logged_in = true;
    } else {
        $error = "Invalid username or password!";
    }
} elseif (isset($_SESSION['user'])) {
    $logged_in = true;
}

// Only proceed with the rest if logged in
if ($logged_in) {
    $current_user = $_SESSION['user'];
    
    // Messages
    $success = '';
    $error = '';

    // Handle delete operations
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Delete User
        if (isset($_GET['delete_user'])) {
            $id = intval($_GET['delete_user']);
            if ($id != $current_user['id']) { // Prevent deleting yourself
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $success = "User deleted successfully!";
            } else {
                $error = "You cannot delete your own account!";
            }
        }

        // Delete Client
        if (isset($_GET['delete_client'])) {
            $id = intval($_GET['delete_client']);
            // Check if client has sales before deleting
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE client_id = ?");
            $stmt->execute([$id]);
            $sales_count = $stmt->fetchColumn();
            
            if ($sales_count == 0) {
                $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Client deleted successfully!";
            } else {
                $error = "Cannot delete client with existing sales!";
            }
        }

        // Delete Product
        if (isset($_GET['delete_product'])) {
            $id = intval($_GET['delete_product']);
            // Check if product has sales or purchases before deleting
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE product_id = ?");
            $stmt->execute([$id]);
            $sales_count = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE product_id = ?");
            $stmt->execute([$id]);
            $purchases_count = $stmt->fetchColumn();
            
            if ($sales_count == 0 && $purchases_count == 0) {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Product deleted successfully!";
            } else {
                $error = "Cannot delete product with existing sales or purchases!";
            }
        }

        // Delete Purchase
        if (isset($_GET['delete_purchase'])) {
            $id = intval($_GET['delete_purchase']);
            try {
                $pdo->beginTransaction();
                // Get purchase details first
                $stmt = $pdo->prepare("SELECT product_id, quantity FROM purchases WHERE id = ?");
                $stmt->execute([$id]);
                $purchase = $stmt->fetch();
                
                if ($purchase) {
                    // Update product stock
                    $stmt = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
                    $stmt->execute([$purchase['quantity'], $purchase['product_id']]);
                    
                    // Delete the purchase
                    $stmt = $pdo->prepare("DELETE FROM purchases WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $pdo->commit();
                    $success = "Purchase deleted and stock adjusted!";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error deleting purchase: " . $e->getMessage();
            }
        }

        // Delete Sale
        if (isset($_GET['delete_sale'])) {
            $id = intval($_GET['delete_sale']);
            $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Sale deleted successfully!";
        }
    }

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
                    $success = "User added successfully!";
                } catch (Exception $e) {
                    $error = "Error adding user: " . $e->getMessage();
                }
            } else {
                $error = "Username and Password are required!";
            }
        }

        // Edit User
        if (isset($_POST['edit_user'])) {
            $id = intval($_POST['id']);
            $username = trim($_POST['username']);
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $role = $_POST['role'] ?? 'staff';

            if ($id && $username) {
                // Check if password is being updated
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $password, $full_name, $email, $role, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$username, $full_name, $email, $role, $id]);
                }
                $success = "User updated successfully!";
            } else {
                $error = "Username is required!";
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
                    $success = "Client added successfully!";
                } catch (Exception $e) {
                    $error = "Error adding client: " . $e->getMessage();
                }
            } else {
                $error = "Client name is required!";
            }
        }

        // Edit Client
        if (isset($_POST['edit_client'])) {
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email']);
            $address = trim($_POST['address']);

            if ($id && $name) {
                $stmt = $pdo->prepare("UPDATE clients SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $email, $address, $id]);
                $success = "Client updated successfully!";
            } else {
                $error = "Client name is required!";
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
                    $success = "Product added successfully!";
                } catch (Exception $e) {
                    $error = "Error adding product: " . $e->getMessage();
                }
            } else {
                $error = "Product name and valid price are required!";
            }
        }

        // Edit Product
        if (isset($_POST['edit_product'])) {
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $price = floatval($_POST['price']);

            if ($id && $name && $price >= 0) {
                $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ? WHERE id = ?");
                $stmt->execute([$name, $description, $price, $id]);
                $success = "Product updated successfully!";
            } else {
                $error = "Product name and valid price are required!";
            }
        }

        // Add Purchase
        if (isset($_POST['add_purchase'])) {
            $product_id = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            $purchase_price = floatval($_POST['purchase_price']);
            $purchase_date = $_POST['purchase_date'];

            if ($product_id && $quantity > 0 && $purchase_price >= 0 && $purchase_date) {
                $stmt = $pdo->prepare("INSERT INTO purchases (product_id, quantity, purchase_price, purchase_date, user_id) VALUES (?, ?, ?, ?, ?)");
                try {
                    $pdo->beginTransaction();
                    $stmt->execute([$product_id, $quantity, $purchase_price, $purchase_date, $current_user['id']]);
                    // Update stock
                    $stmt2 = $pdo->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
                    $stmt2->execute([$quantity, $product_id]);
                    $pdo->commit();
                    $success = "Purchase recorded and stock updated!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Error adding purchase: " . $e->getMessage();
                }
            } else {
                $error = "All purchase fields are required and quantity must be positive!";
            }
        }

        // Add Sale
        if (isset($_POST['add_sale'])) {
            $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
            $sale_date = $_POST['sale_date'];
            $total_amount = floatval($_POST['total_amount']);

            if ($sale_date && $total_amount >= 0) {
                $stmt = $pdo->prepare("INSERT INTO sales (client_id, sale_date, total_amount, user_id) VALUES (?, ?, ?, ?)");
                try {
                    $stmt->execute([$client_id, $sale_date, $total_amount, $current_user['id']]);
                    $success = "Sale recorded successfully!";
                } catch (Exception $e) {
                    $error = "Error adding sale: " . $e->getMessage();
                }
            } else {
                $error = "Sale date and total amount are required!";
            }
        }

        // Add Stock
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
                    $success = "Stock updated successfully!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Error updating stock: " . $e->getMessage();
                }
            } else {
                $error = "Product and quantity are required!";
            }
        }
    }

    // Handle invoice generation
    if (isset($_GET['invoice'])) {
        $sale_id = intval($_GET['invoice']);
        $stmt = $pdo->prepare("SELECT s.*, c.name as client_name, c.address as client_address, 
                              u.username as cashier FROM sales s 
                              LEFT JOIN clients c ON s.client_id = c.id 
                              LEFT JOIN users u ON s.user_id = u.id 
                              WHERE s.id = ?");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch();
        
        if ($sale) {
            header('Content-Type: text/html');
            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Invoice #'.$sale_id.'</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                    .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); }
                    .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
                    .title { font-size: 24px; font-weight: bold; }
                    .details { margin: 20px 0; }
                    table { width: 100%; border-collapse: collapse; }
                    table td, table th { padding: 12px; border: 1px solid #ddd; text-align: left; }
                    table th { background-color: #f5f5f5; }
                    .total { font-weight: bold; font-size: 18px; }
                    .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #777; }
                </style>
            </head>
            <body>
                <div class="invoice-box">
                    <div class="header">
                        <div>
                            <div class="title">INVOICE</div>
                            <div>POS System</div>
                        </div>
                        <div style="text-align: right;">
                            <div>Invoice #'.$sale_id.'</div>
                            <div>Date: '.date('M d, Y', strtotime($sale["sale_date"])).'</div>
                        </div>
                    </div>
                    
                    <div class="details">
                        <div><strong>Bill To:</strong></div>
                        <div>'.htmlspecialchars($sale['client_name'] ?? 'Walk-in Customer').'</div>
                        '.($sale['client_address'] ? '<div>'.htmlspecialchars($sale['client_address']).'</div>' : '').'
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Sale #'.$sale_id.'</td>
                                <td>$'.number_format($sale['total_amount'], 2).'</td>
                            </tr>
                            <tr class="total">
                                <td>Total</td>
                                <td>$'.number_format($sale['total_amount'], 2).'</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="footer">
                        <div>Thank you for your business!</div>
                        <div>Processed by: '.htmlspecialchars($sale['cashier']).'</div>
                    </div>
                </div>
            </body>
            </html>';
            exit;
        }
    }

    // Fetch data for tables and dropdowns
    $users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
    $clients = $pdo->query("SELECT * FROM clients ORDER BY id DESC")->fetchAll();
    $products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
    $purchases = $pdo->query("SELECT p.*, pr.name AS product_name FROM purchases p JOIN products pr ON p.product_id = pr.id ORDER BY p.id DESC")->fetchAll();
    $sales = $pdo->query("SELECT s.*, c.name AS client_name FROM sales s LEFT JOIN clients c ON s.client_id = c.id ORDER BY s.id DESC")->fetchAll();
    $stock = $pdo->query("SELECT st.*, pr.name AS product_name FROM stock st JOIN products pr ON st.product_id = pr.id ORDER BY st.id DESC")->fetchAll();

    // Get dashboard stats
    $total_sales = $pdo->query("SELECT SUM(total_amount) as total FROM sales")->fetch()['total'] ?? 0;
    $total_products = $pdo->query("SELECT COUNT(*) as total FROM products")->fetch()['total'] ?? 0;
    $total_clients = $pdo->query("SELECT COUNT(*) as total FROM clients")->fetch()['total'] ?? 0;
    $low_stock = $pdo->query("SELECT COUNT(*) as total FROM products WHERE stock_qty < 5")->fetch()['total'] ?? 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>POS System | Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.35rem;
            font-weight: 600;
        }
        
        .nav-tabs {
            border-bottom: 1px solid #e3e6f0;
        }
        
        .nav-tabs .nav-link {
            color: #6e707e;
            font-weight: 600;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background-color: transparent;
        }
        
        .stat-card {
            border-left: 0.25rem solid;
            border-radius: 0.35rem;
        }
        
        .stat-card.primary {
            border-left-color: var(--primary-color);
        }
        
        .stat-card.success {
            border-left-color: var(--success-color);
        }
        
        .stat-card.info {
            border-left-color: var(--info-color);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.3;
        }
        
        .form-control, .form-select {
            border-radius: 0.35rem;
            padding: 0.75rem 1rem;
        }
        
        .btn {
            border-radius: 0.35rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }
        
        .table {
            color: #5a5c69;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            color: #858796;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .badge {
            font-weight: 600;
            padding: 0.35em 0.65em;
            border-radius: 0.25rem;
        }
        
        .action-btns .btn {
            padding: 0.375rem 0.75rem;
        }
    </style>
</head>
<body>
<?php if (!$logged_in): ?>
<div class="login-container">
    <div class="text-center mb-4">
        <i class="bi bi-shop" style="font-size: 2.5rem; color: var(--primary-color);"></i>
        <h3>POS System Login</h3>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" required>
        </div>
        <button type="submit" name="login" class="btn btn-primary w-100">
            <i class="bi bi-box-arrow-in-right me-2"></i> Login
        </button>
    </form>
</div>
<?php else: ?>
<div class="container-fluid p-0">
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand navbar-dark bg-dark">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="pos_app.php">
                <i class="bi bi-shop me-2"></i>POS System
            </a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($current_user['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="pos_app.php?logout=1"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <!-- Dashboard Stats -->
        <div class="row mt-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card primary h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="text-uppercase mb-0">Total Sales</h6>
                                <h2 class="mb-0">$<?= number_format($total_sales, 2) ?></h2>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-currency-dollar stat-icon text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card success h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="text-uppercase mb-0">Products</h6>
                                <h2 class="mb-0"><?= $total_products ?></h2>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-box-seam stat-icon text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card info h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="text-uppercase mb-0">Clients</h6>
                                <h2 class="mb-0"><?= $total_clients ?></h2>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-people stat-icon text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card warning h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="text-uppercase mb-0">Low Stock</h6>
                                <h2 class="mb-0"><?= $low_stock ?></h2>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-exclamation-triangle stat-icon text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="posTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                            <i class="bi bi-people me-1"></i> Users
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="clients-tab" data-bs-toggle="tab" data-bs-target="#clients" type="button" role="tab">
                            <i class="bi bi-person-lines-fill me-1"></i> Clients
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                            <i class="bi bi-box-seam me-1"></i> Products
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchases" type="button" role="tab">
                            <i class="bi bi-cart-plus me-1"></i> Purchases
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button" role="tab">
                            <i class="bi bi-cash-stack me-1"></i> Sales
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock" type="button" role="tab">
                            <i class="bi bi-boxes me-1"></i> Stock
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="posTabContent">

                    <!-- Users Tab -->
                    <div class="tab-pane fade show active" id="users" role="tabpanel" aria-labelledby="users-tab">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="bi bi-people me-2"></i>User Management</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="bi bi-plus-lg me-1"></i> Add User
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($users as $u): ?>
                                    <tr>
                                        <td><?= $u['id'] ?></td>
                                        <td><?= htmlspecialchars($u['username']) ?></td>
                                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $u['role'] === 'admin' ? 'primary' : 'secondary' ?>">
                                                <?= ucfirst($u['role']) ?>
                                            </span>
                                        </td>
                                        <td><span class="badge bg-success">Active</span></td>
                                        <td class="action-btns">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($u['id'] != $current_user['id']): ?>
                                            <a href="pos_app.php?delete_user=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <!-- Edit User Modal -->
                                    <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Edit User</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="edit_user" value="1" />
                                                        <input type="hidden" name="id" value="<?= $u['id'] ?>" />
                                                        <div class="mb-3">
                                                            <label class="form-label">Username *</label>
                                                            <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($u['username']) ?>" required />
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Password (leave blank to keep current)</label>
                                                            <input type="password" class="form-control" name="password" />
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Full Name</label>
                                                            <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($u['full_name']) ?>" />
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Email</label>
                                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($u['email']) ?>" />
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Role</label>
                                                            <select class="form-select" name="role">
                                                                <option value="staff" <?= $u['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                                                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Update User</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Clients Tab -->
                    <div class="tab-pane fade" id="clients" role="tabpanel" aria-labelledby="clients-tab">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Client Management</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
                                <i class="bi bi-plus-lg me-1"></i> Add Client
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Address</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($clients as $c): ?>
                                    <tr>
                                        <td><?= $c['id'] ?></td>
                                        <td><?= htmlspecialchars($c['name']) ?></td>
                                        <td><?= htmlspecialchars($c['phone']) ?></td>
                                        <td><?= htmlspecialchars($c['email']) ?></td>
                                        <td><?= htmlspecialchars(substr($c['address'], 0, 30)) . (strlen($c['address']) > 30 ? '...' : '') ?></td>
                                        <td class="action-btns">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editClientModal<?= $c['id'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="pos_app.php?delete_client=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this client?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    
                                    <!-- Edit Client Modal -->
                                    <div class="modal fade" id="editClientModal<?= $c['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Edit Client</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="edit_client" value="1" />
                                                        <input type="hidden" name="id" value="<?= $c['id'] ?>" />
                                                        <div class="mb-3">
                                                            <label class="form-label">Name *</label>
                                                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($c['name']) ?>" required />
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Phone</label>
                                                            <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($c['phone']) ?>" />
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Email</label>
                                                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($c['email']) ?>" />
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Address</label>
                                                            <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($c['address']) ?></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Update Client</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Products Tab -->
                    <div class="tab-pane fade" id="products" role="tabpanel" aria-labelledby="products-tab">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="bi bi-box-seam me-2"></i>Product Management</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="bi bi-plus-lg me-1"></i> Add Product
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($products as $p): ?>
                                    <tr>
                                        <td><?= $p['id'] ?></td>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td><?= htmlspecialchars(substr($p['description'], 0, 30)) . (strlen($p['description']) > 30 ? '...' : '') ?></td>
                                        <td>$<?= number_format($p['price'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $p['stock_qty'] > 10 ? 'success' : ($p['stock_qty'] > 0 ? 'warning' : 'danger') ?>">
                                                <?= $p['stock_qty'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">Active</span>
                                        </td>
                                        <td class="action-btns">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProductModal<?= $p['id'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="pos_app.php?delete_product=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this product?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    
                                    <!-- Edit Product Modal -->
                                    <div class="modal fade" id="editProductModal<?= $p['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Edit Product</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="edit_product" value="1" />
                                                        <input type="hidden" name="id" value="<?= $p['id'] ?>" />
                                                        <div class="mb-3">
                                                            <label class="form-label">Name *</label>
                                                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($p['name']) ?>" required />
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <textarea class="form-control" name="description" rows="3"><?= htmlspecialchars($p['description']) ?></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Price *</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">$</span>
                                                                <input type="number" step="0.01" class="form-control" name="price" value="<?= $p['price'] ?>" required />
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn btn-primary">Update Product</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Purchases Tab -->
                    <div class="tab-pane fade" id="purchases" role="tabpanel" aria-labelledby="purchases-tab">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="bi bi-cart-plus me-2"></i>Purchase Management</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPurchaseModal">
                                <i class="bi bi-plus-lg me-1"></i> Add Purchase
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($purchases as $pu): ?>
                                    <tr>
                                        <td><?= $pu['id'] ?></td>
                                        <td><?= htmlspecialchars($pu['product_name']) ?></td>
                                        <td><?= $pu['quantity'] ?></td>
                                        <td>$<?= number_format($pu['purchase_price'], 2) ?></td>
                                        <td>$<?= number_format($pu['quantity'] * $pu['purchase_price'], 2) ?></td>
                                        <td><?= date('M d, Y', strtotime($pu['purchase_date'])) ?></td>
                                        <td class="action-btns">
                                            <a href="pos_app.php?delete_purchase=<?= $pu['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this purchase? Stock will be adjusted.')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Sales Tab -->
                    <div class="tab-pane fade" id="sales" role="tabpanel" aria-labelledby="sales-tab">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Sales Management</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSaleModal">
                                <i class="bi bi-plus-lg me-1"></i> Add Sale
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Client</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($sales as $s): ?>
                                    <tr>
                                        <td><?= $s['id'] ?></td>
                                        <td><?= htmlspecialchars($s['client_name'] ?? 'Walk-in') ?></td>
                                        <td><?= date('M d, Y', strtotime($s['sale_date'])) ?></td>
                                        <td>$<?= number_format($s['total_amount'], 2) ?></td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                        <td class="action-btns">
                                            <a href="pos_app.php?invoice=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-receipt"></i> Invoice
                                            </a>
                                            <a href="pos_app.php?delete_sale=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this sale?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Stock Tab -->
                    <div class="tab-pane fade" id="stock" role="tabpanel" aria-labelledby="stock-tab">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="bi bi-boxes me-2"></i>Stock Management</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStockModal">
                                <i class="bi bi-plus-lg me-1"></i> Update Stock
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Status</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($stock as $st): ?>
                                    <tr>
                                        <td><?= $st['id'] ?></td>
                                        <td><?= htmlspecialchars($st['product_name']) ?></td>
                                        <td><?= $st['quantity'] ?></td>
                                        <td>
                                            <span class="badge bg-<?= $st['quantity'] > 10 ? 'success' : ($st['quantity'] > 0 ? 'warning' : 'danger') ?>">
                                                <?= $st['quantity'] > 10 ? 'In Stock' : ($st['quantity'] > 0 ? 'Low Stock' : 'Out of Stock') ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($st['last_updated'])) ?></td>
                                        <td class="action-btns">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addStockModal">
                                                <i class="bi bi-arrow-repeat"></i> Restock
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_user" value="1" />
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-control" name="username" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" name="password" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="staff" selected>Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_client" value="1" />
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" name="name" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_product" value="1" />
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" name="name" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control" name="price" required />
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Initial Stock</label>
                            <input type="number" class="form-control" name="stock_qty" value="0" min="0" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Purchase Modal -->
<div class="modal fade" id="addPurchaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cart-plus me-2"></i>Add New Purchase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_purchase" value="1" />
                    <div class="mb-3">
                        <label class="form-label">Product *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product</option>
                            <?php foreach($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity *</label>
                            <input type="number" min="1" name="quantity" class="form-control" required />
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Purchase Price *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" required />
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purchase Date *</label>
                        <input type="date" name="purchase_date" class="form-control" required value="<?= date('Y-m-d') ?>" />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Purchase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Sale Modal -->
<div class="modal fade" id="addSaleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cash-stack me-2"></i>Add New Sale</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_sale" value="1" />
                    <div class="mb-3">
                        <label class="form-label">Client</label>
                        <select name="client_id" class="form-select">
                            <option value="">Walk-in / No Client</option>
                            <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sale Date *</label>
                        <input type="date" name="sale_date" class="form-control" required value="<?= date('Y-m-d') ?>" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0" name="total_amount" class="form-control" required />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Sale</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Stock Modal -->
<div class="modal fade" id="addStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-boxes me-2"></i>Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="add_stock" value="1" />
                    <div class="mb-3">
                        <label class="form-label">Product *</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select Product</option>
                            <?php foreach($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity to Add *</label>
                        <input type="number" name="quantity" min="0" class="form-control" required />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
</script>
<?php endif; ?>
</body>
</html>