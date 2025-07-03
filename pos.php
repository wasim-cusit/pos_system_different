<?php
// ------------------ CONFIGURATION ------------------

try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS pos CHARACTER SET utf8 COLLATE utf8_general_ci");
    $pdo->exec("USE pos");
    echo "Database created or exists.";
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}


// ------------------ CLIENT MODULE ------------------
if (isset($_POST['add_client'])) {
    $stmt = $pdo->prepare("INSERT INTO clients (name, email, phone, address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['name'], $_POST['email'], $_POST['phone'], $_POST['address']]);
    echo "Client added successfully.<br>";
}

// ------------------ USER MODULE ------------------
if (isset($_POST['register_user'])) {
    $hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['username'], $hash, $_POST['role']]);
    echo "User registered successfully.<br>";
}

// ------------------ PRODUCT MODULE ------------------
if (isset($_POST['add_product'])) {
    $stmt = $pdo->prepare("INSERT INTO products (name, price, stock) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['product_name'], $_POST['price'], $_POST['stock']]);
    echo "Product added successfully.<br>";
}

// ------------------ PURCHASE MODULE ------------------
if (isset($_POST['add_purchase'])) {
    $stmt = $pdo->prepare("INSERT INTO purchases (product_id, quantity, total_cost) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['product_id'], $_POST['quantity'], $_POST['total_cost']]);
    $update = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
    $update->execute([$_POST['quantity'], $_POST['product_id']]);
    echo "Purchase recorded.<br>";
}

// ------------------ SALE MODULE ------------------
if (isset($_POST['add_sale'])) {
    $stmt = $pdo->prepare("INSERT INTO sales (client_id, product_id, quantity, total_price) VALUES (?, ?, ?, ?)");
    $stmt->execute([$_POST['client_id'], $_POST['product_id'], $_POST['quantity'], $_POST['total_price']]);
    $update = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    $update->execute([$_POST['quantity'], $_POST['product_id']]);
    echo "Sale recorded.<br>";
}
?>



<!DOCTYPE html>
<html>
<head>
    <title>POS System</title>
</head>
<body>
    <h2>Client</h2>
    <form method="post">
        <input type="text" name="name" placeholder="Name" required><br>
        <input type="email" name="email" placeholder="Email"><br>
        <input type="text" name="phone" placeholder="Phone"><br>
        <textarea name="address" placeholder="Address"></textarea><br>
        <button name="add_client">Add Client</button>
    </form>

    <h2>User</h2>
    <form method="post">
        <input type="text" name="username" placeholder="Username" required><br>
        <input type="password" name="password" placeholder="Password"><br>
        <select name="role">
            <option value="admin">Admin</option>
            <option value="cashier">Cashier</option>
        </select><br>
        <button name="register_user">Register User</button>
    </form>

    <h2>Product</h2>
    <form method="post">
        <input type="text" name="product_name" placeholder="Product Name" required><br>
        <input type="number" step="0.01" name="price" placeholder="Price"><br>
        <input type="number" name="stock" placeholder="Initial Stock"><br>
        <button name="add_product">Add Product</button>
    </form>

    <h2>Purchase</h2>
    <form method="post">
        <input type="number" name="product_id" placeholder="Product ID"><br>
        <input type="number" name="quantity" placeholder="Quantity"><br>
        <input type="number" step="0.01" name="total_cost" placeholder="Total Cost"><br>
        <button name="add_purchase">Add Purchase</button>
    </form>

    <h2>Sale</h2>
    <form method="post">
        <input type="number" name="client_id" placeholder="Client ID"><br>
        <input type="number" name="product_id" placeholder="Product ID"><br>
        <input type="number" name="quantity" placeholder="Quantity"><br>
        <input type="number" step="0.01" name="total_price" placeholder="Total Price"><br>
        <button name="add_sale">Add Sale</button>
    </form>
</body>
</html>
