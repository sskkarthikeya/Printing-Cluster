<?php
include '../database/db_connect.php'; 

$customer_name = $phone_number = $job_name = $total_charges = $payment_status = "";
$paper_subcategory = $type = $quantity = $striking = $machine = $ryobi_type = $web_type = $web_size = "";
$ctp_plate = $ctp_quantity = $plating_charges = $paper_charges = $printing_charges = "";
$lamination_charges = $pinning_charges = $binding_charges = $finishing_charges = $other_charges = $discount = "";
$status = "Draft";
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'add';
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Load job sheet details if in view or edit mode
if ($job_id > 0 && ($mode === 'view' || $mode === 'edit')) {
    $sql = "SELECT * FROM job_sheets WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $customer_name = $row['customer_name'];
        $phone_number = $row['phone_number'];
        $job_name = $row['job_name'];
        $paper_subcategory = $row['paper_subcategory'];
        $type = $row['type'];
        $quantity = $row['quantity'];
        $striking = $row['striking'];
        $machine = $row['machine'];
        $ryobi_type = $row['ryobi_type'];
        $web_type = $row['web_type'];
        $web_size = $row['web_size'];
        $ctp_plate = $row['ctp_plate'];
        $ctp_quantity = $row['ctp_quantity'];
        $plating_charges = $row['plating_charges'];
        $paper_charges = $row['paper_charges'];
        $printing_charges = $row['printing_charges'];
        $lamination_charges = $row['lamination_charges'];
        $pinning_charges = $row['pinning_charges'];
        $binding_charges = $row['binding_charges'];
        $finishing_charges = $row['finishing_charges'];
        $other_charges = $row['other_charges'];
        $discount = $row['discount'];
        $total_charges = $row['total_charges'];
        $status = $row['status'];
    }
    $stmt->close();
}

$subcategories = [];
$items = [];
$sql = "SELECT id, subcategory_name FROM inventory_subcategories WHERE category_id=(SELECT id FROM inventory_categories WHERE category_name='Paper')";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $subcategories[] = $row;
}
$sql = "SELECT id, item_name, subcategory_id FROM inventory_items";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $items[$row['subcategory_id']][] = $row;
}

if (isset($_POST['get_customer_type']) && isset($_POST['customer_name'])) {
    $customer_name = $_POST['customer_name'];
    $query = "SELECT is_member FROM customers WHERE customer_name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $customer_name);
    $stmt->execute();
    $stmt->bind_result($is_member);
    $response = ["success" => false];
    if ($stmt->fetch()) {
        $response["success"] = true;
        $response["customer_type"] = $is_member ? "member" : "non_member";
    }
    $stmt->close();
    echo json_encode($response);
    exit;
}

if (isset($_POST['subcategory_id'], $_POST['item_id'])) {
    $subcategory_id = $_POST['subcategory_id'];
    $item_id = $_POST['item_id'];

    $sql = "SELECT selling_price FROM sales_prices WHERE item_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $response = ["success" => false];
    if ($row = $result->fetch_assoc()) {
        $response["success"] = true;
        $response["selling_price"] = $row["selling_price"];
    }
    echo json_encode($response);
    exit;
}

$customer_type = "non_member";
if (isset($_POST['customer_name']) && !isset($_POST['get_customer_type'])) {
    $customer_name = $_POST['customer_name'];
    $query = "SELECT is_member FROM customers WHERE customer_name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $customer_name);
    $stmt->execute();
    $stmt->bind_result($is_member);
    if ($stmt->fetch()) {
        $customer_type = $is_member ? "member" : "non_member";
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['subcategory_id']) && !isset($_POST['get_customer_type'])) {
    $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    $customer_name = $_POST['customer_name'];
    $phone_number = $_POST['phone_number'];
    $job_name = $_POST['job_name'];
    $paper_subcategory = $_POST['paper'];
    $type = $_POST['type'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $striking = $_POST['striking'];
    $machine = $_POST['machine'];
    $ryobi_type = $_POST['ryobi_type'] ?? NULL;
    $web_type = $_POST['web_type'] ?? NULL;
    $web_size = isset($_POST['web_size']) ? (int)$_POST['web_size'] : NULL;
    $ctp_plate = $_POST['ctp_plate'] ?? NULL;
    $ctp_quantity = isset($_POST['ctp_quantity']) ? (int)$_POST['ctp_quantity'] : 0;
    $plating_charges = $_POST['plating_charges'];
    $paper_charges = $_POST['paper_charges'];
    $printing_charges = $_POST['printing_charges'];
    $lamination_charges = $_POST['lamination_charges'];
    $pinning_charges = $_POST['pinning_charges'];
    $binding_charges = $_POST['binding_charges'];
    $finishing_charges = $_POST['finishing_charges'];
    $other_charges = $_POST['other_charges'];
    $discount = $_POST['discount'];
    $total_charges = $_POST['total_charges'];
    $status = $_POST['status'] ?? 'Draft';

    // Debug: Log the status values
    error_log("POST status: " . (isset($_POST['status']) ? $_POST['status'] : 'Not set'));
    error_log("Status variable: " . $status);

    if ($job_id > 0) {
        // Update existing job sheet
        $sql = "UPDATE job_sheets SET 
            customer_name=?, phone_number=?, job_name=?, paper_subcategory=?, type=?, quantity=?, striking=?, machine=?, 
            ryobi_type=?, web_type=?, web_size=?, ctp_plate=?, ctp_quantity=?, plating_charges=?, paper_charges=?, 
            printing_charges=?, lamination_charges=?, pinning_charges=?, binding_charges=?, finishing_charges=?, 
            other_charges=?, discount=?, total_charges=?, status=?
            WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisssssssisddddddddddsi", 
            $customer_name, $phone_number, $job_name, $paper_subcategory, $type, $quantity, 
            $striking, $machine, $ryobi_type, $web_type, $web_size, $ctp_plate, $ctp_quantity, 
            $plating_charges, $paper_charges, $printing_charges, $lamination_charges, 
            $pinning_charges, $binding_charges, $finishing_charges, $other_charges, 
            $discount, $total_charges, $status, $job_id
        );
    } else {
        // Insert new job sheet
        $sql = "INSERT INTO job_sheets (
            customer_name, phone_number, job_name, paper_subcategory, type, quantity, striking, machine, 
            ryobi_type, web_type, web_size, ctp_plate, ctp_quantity, plating_charges, paper_charges, 
            printing_charges, lamination_charges, pinning_charges, binding_charges, finishing_charges, 
            other_charges, discount, total_charges, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisssssssisdddddddddds", 
            $customer_name, $phone_number, $job_name, $paper_subcategory, $type, $quantity, 
            $striking, $machine, $ryobi_type, $web_type, $web_size, $ctp_plate, $ctp_quantity, 
            $plating_charges, $paper_charges, $printing_charges, $lamination_charges, 
            $pinning_charges, $binding_charges, $finishing_charges, $other_charges, 
            $discount, $total_charges, $status
        );
    }

    if ($stmt->execute()) {
        if ($job_id == 0) {
            $job_id = $conn->insert_id;
        }
        error_log("Database operation successful. Status: " . $status);
        if ($status === 'Finalized') {
            error_log("Redirecting to finalize_order.php?id=$job_id");
            header("Location: finalize_order.php?id=$job_id");
            exit;
        } else {
            error_log("Redirecting to view_order.php");
            header("Location: view_order.php");
            exit;
        }
    } else {
        $error = "Error: " . $stmt->error;
        error_log($error);
        echo $error;
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Dashboard - New Order</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0f4f8, #d9e2ec);
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
        }

        .navbar {
            background: linear-gradient(90deg, #007bff, #0056b3);
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar .brand {
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .nav-buttons button {
            background: #ffffff;
            color: #007bff;
            border: none;
            padding: 10px 20px;
            margin-left: 10px;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nav-buttons button:hover {
            background: #0056b3;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transform: translateY(-2px);
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        h3 {
            color: #555;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            color: #333;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 2px solid #ccc;
            border-radius: 10px;
            background: #fff;
            transition: all 0.3s ease;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group select:focus {
            border-color: #007bff;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.2);
            outline: none;
        }

        .form-group input[type="radio"],
        .form-group input[type="checkbox"] {
            margin-right: 10px;
            accent-color: #007bff;
        }

        .customer-container {
            display: flex;
            gap: 20px;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .machine-selection,
        .plate-sizes,
        .customer-selection {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .machine-selection label,
        .plate-sizes label,
        .customer-selection label {
            background: #e9f1ff;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .machine-selection label:hover,
        .plate-sizes label:hover,
        .customer-selection label:hover {
            background: #d0e0ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .job-sheet-btn {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .job-sheet-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d82);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
        }

        .dialog-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .dialog-box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: popIn 0.3s ease;
        }

        @keyframes popIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .dialog-box p {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
        }

        .dialog-box button {
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .yes-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
        }

        .yes-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .no-btn {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .no-btn:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .search-input {
            width: 70%;
            padding: 12px;
            border: 2px solid #007bff;
            border-radius: 25px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #0056b3;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.2);
            outline: none;
        }

        .search-btn {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d82);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .vendor-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 15px;
        }

        .vendor-name {
            font-size: 18px;
            color: #333;
            font-weight: bold;
        }

        .edit-btn {
            background: linear-gradient(135deg, #28a745, #218838);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .edit-btn:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Reception Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='New_Order.php'">New Order</button>
            <button onclick="location.href='view_order.php'">View Order</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="container">
        <h2>Customer Management</h2>
        <h3 style="padding-left:20px;">Select Customer</h3>
        <form method="GET" id="searchForm">
            <input type="text" name="search_query" class="search-input" placeholder="Search Customer...">
            <button type="submit" class="search-btn">Search</button>
        </form>
    </div>

    <?php if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search_query'])): ?>
    <div class="container" id="searchResults">
        <?php
        $search = $conn->real_escape_string($_GET['search_query']);
        $sql = "SELECT customer_name, phone_number FROM customers WHERE customer_name LIKE ?";
        $stmt = $conn->prepare($sql);
        $search_param = "%$search%";
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $customerJson = htmlspecialchars(json_encode([
                    'customer_name' => $row['customer_name'],
                    'phone_number' => $row['phone_number']
                ]), ENT_QUOTES, 'UTF-8');
                echo "<div class='vendor-card'>
                    <strong class='vendor-name'>" . htmlspecialchars($row['customer_name']) . "</strong>
                    <div class='vendor-actions'>
                        <button class='edit-btn' onclick='selectCustomer($customerJson)'>Select</button>
                    </div>
                    <p>Phone: " . htmlspecialchars($row['phone_number']) . "</p>
                </div>";
            }
        } else {
            echo "<p>No customers found.</p>";
        }
        $stmt->close();
        ?>
    </div>
    <?php endif; ?>

    <form action="" method="POST" id="jobForm" class="job-sheet-form">
        <input type="hidden" id="status" name="status" value="Draft">
        <input type="hidden" name="job_id" value="<?= $job_id ?>">
        <div class="container" style="width:70%">
            <h2>Customer Details</h2>
            <div class="customer-container" style="display:flex;justify-content:space-evenly;">
                <div class="form-group">
                    <label>Customer Name:</label>
                    <input type="text" name="customer_name" id="customer_name" placeholder="Enter Customer Name" value="<?= htmlspecialchars($customer_name) ?>" required <?= $mode === 'view' ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Phone Number:</label>
                    <input type="text" name="phone_number" id="phone_number" placeholder="Enter Phone Number" value="<?= htmlspecialchars($phone_number) ?>" required <?= $mode === 'view' ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Job Name:</label>
                    <input type="text" name="job_name" placeholder="Enter Job Name" value="<?= htmlspecialchars($job_name) ?>" required <?= $mode === 'view' ? 'readonly' : '' ?>>
                </div>
            </div>
            
            <h2>Paper Section</h2>
            <div class="customer-container" style="display:flex;gap:20px;justify-content:space-between;">
                <div class="form-group">
                    <label>Paper (Subcategory):</label>
                    <select name="paper" id="paper" onchange="updateTypeDropdown()" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="">Select Paper</option>
                        <?php foreach ($subcategories as $subcategory): ?>
                            <option value="<?= $subcategory['id'] ?>" <?= $paper_subcategory == $subcategory['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subcategory['subcategory_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="type-container" style="display:<?= $paper_subcategory ? 'block' : 'none' ?>;">
                    <label>Type:</label>
                    <select name="type" id="type" onchange="fetchSellingPrice()" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="">Select Type</option>
                        <?php if ($paper_subcategory && isset($items[$paper_subcategory])): ?>
                            <?php foreach ($items[$paper_subcategory] as $item): ?>
                                <option value="<?= $item['id'] ?>" <?= $type == $item['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['item_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity:</label>
                    <input type="number" id="quantity" name="quantity" min="1" value="<?= $quantity ?: 1 ?>" oninput="calculateStrikingAndUpdate()" required <?= $mode === 'view' ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Printing Type:</label>
                    <select id="printingType" name="striking" onchange="calculateStrikingAndUpdate()" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="select" <?= $striking == 'select' ? 'selected' : '' ?>>Select striking type</option>
                        <option value="Customer" <?= $striking == 'Customer' ? 'selected' : '' ?>>One Side</option>
                        <option value="Company" <?= $striking == 'Company' ? 'selected' : '' ?>>Back and Back</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Striking:</label>
                    <input id="striking" name="plates" value="<?= $striking ?>" readonly>
                </div>
                <input type="hidden" id="selling_price" value="">
            </div>

            <h2>Machine Selection</h2>
            <div class="customer-container" style="display:flex;gap:20px;justify-content:space-between;">
                <div class="machine-selection">
                    <label><input type="radio" name="machine" value="DD" onchange="updateMachineOptions()" <?= $machine == 'DD' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> D/D</label>
                    <label><input type="radio" name="machine" value="SDD" onchange="updateMachineOptions()" <?= $machine == 'SDD' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> S/D</label>
                    <label><input type="radio" name="machine" value="DC" onchange="updateMachineOptions()" <?= $machine == 'DC' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> D/C</label>
                    <label><input type="radio" name="machine" value="RYOBI" onchange="updateMachineOptions()" <?= $machine == 'RYOBI' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> RYOBI</label>
                    <label><input type="radio" name="machine" value="Web" onchange="updateMachineOptions()" <?= $machine == 'Web' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> WEB</label>
                    <label><input type="radio" name="machine" value="Digital" onchange="updateMachineOptions()" <?= $machine == 'Digital' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> Digital</label>
                    <label><input type="radio" name="machine" value="CTP" onchange="updateMachineOptions()" <?= $machine == 'CTP' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> CTP</label>
                    <label><input type="checkbox" name="machine" value="re-print" id="re-print" <?= $mode === 'view' ? 'disabled' : '' ?>>RePrint</label>
                </div>

                <div class="form-group" id="ryobi-options" style="display:<?= $machine == 'RYOBI' ? 'block' : 'none' ?>;">
                    <label>RYOBI Type:</label>
                    <select name="ryobi_type" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="">Select RYOBI Type</option>
                        <option value="black" <?= $ryobi_type == 'black' ? 'selected' : '' ?>>Black</option>
                        <option value="color" <?= $ryobi_type == 'color' ? 'selected' : '' ?>>Color</option>
                    </select>
                </div>

                <div class="form-group" id="web-options" style="display:<?= $machine == 'Web' ? 'block' : 'none' ?>;">
                    <label>Web Type:</label>
                    <select name="web_type" id="webType" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="">Select web color</option>
                        <option value="black" <?= $web_type == 'black' ? 'selected' : '' ?>>Black</option>
                        <option value="color" <?= $web_type == 'color' ? 'selected' : '' ?>>Color</option>
                    </select>
                </div>

                <div class="form-group" id="web-sub-options" style="display:<?= $machine == 'Web' ? 'block' : 'none' ?>;">
                    <label>No of Papers:</label>
                    <select name="web_size" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="">Select Pages</option>
                        <option value="8" <?= $web_size == 8 ? 'selected' : '' ?>>8</option>
                        <option value="16" <?= $web_size == 16 ? 'selected' : '' ?>>16</option>
                    </select>
                </div>

                <div class="ctp-section" style="display:<?= $machine == 'CTP' ? 'none' : 'block' ?>;" id="ctp-section">
                    <h3>CTP Plate Sizes</h3>
                    <div class="plate-sizes">
                        <label><input type="radio" name="ctpPlate" value="700x945" <?= $ctp_plate == '700x945' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> 700 × 945</label>
                        <label><input type="radio" name="ctpPlate" value="335x485" <?= $ctp_plate == '335x485' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> 335 × 485</label>
                        <label><input type="radio" name="ctpPlate" value="560x670" <?= $ctp_plate == '560x670' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> 560 × 670</label>
                        <label><input type="radio" name="ctpPlate" value="610x890" <?= $ctp_plate == '610x890' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> 610 × 890</label>
                        <label><input type="radio" name="ctpPlate" value="605x60" <?= $ctp_plate == '605x60' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> 605 × 60</label>
                    </div>
                    <label>Enter the quantity:</label>
                    <input type="number" id="ctpQuantity" name="ctp_quantity" placeholder="Enter Quantity" value="<?= $ctp_quantity ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                </div>

                <div class="ctp-plate-selection" id="ctpPlateSection" style="display:<?= $machine == 'CTP' ? 'block' : 'none' ?>;">
                    <h3>Select Plate Size</h3>
                    <select id="plateSize" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="Select">Select Plate Size</option>
                        <option value="700x945" <?= $ctp_plate == '700x945' ? 'selected' : '' ?>>700 × 945</option>
                        <option value="335x485" <?= $ctp_plate == '335x485' ? 'selected' : '' ?>>335 × 485</option>
                        <option value="560x670" <?= $ctp_plate == '560x670' ? 'selected' : '' ?>>560 × 670</option>
                        <option value="610x890" <?= $ctp_plate == '610x890' ? 'selected' : '' ?>>610 × 890</option>
                        <option value="605x60" <?= $ctp_plate == '605x60' ? 'selected' : '' ?>>605 × 60</option>
                    </select>
                    <input type="number" id="plateQuantity" placeholder="Quantity" value="<?= $ctp_quantity ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    <button type="button" onclick="addPlate()" class="edit-btn" style="margin-left:250px;" <?= $mode === 'view' ? 'disabled' : '' ?>>ADD</button>
                    <div class="container" id="plateList"></div>
                </div>
            </div>
            <div class="customer-container" style="display:flex;gap:20px;justify-content:space-between;">
                <div class="customer-selection">
                    <label><input type="radio" name="customerType" value="Customer" onchange="updateCharges()" <?= $mode === 'view' ? 'disabled' : '' ?> checked> Customer</label>
                    <label><input type="radio" name="customerType" value="Publication" onchange="updateCharges()" <?= $mode === 'view' ? 'disabled' : '' ?>> Publication</label>
                </div>
            </div>
            <h2>Bill Details</h2>
            <div class="customer-container" style="display:flex;gap:20px;justify-content:space-between;">
                <div class="container">
                    <div class="form-group">
                        <label>Printing Charges:</label>
                        <input type="number" name="paper_charges" id="paper_charges" placeholder="Enter Printing Charges" value="<?= $paper_charges ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Plating Charges:</label>
                        <input type="number" name="plating_charges" placeholder="Enter Plating Charges" value="<?= $plating_charges ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Lamination Charges:</label>
                        <input type="number" name="lamination_charges" placeholder="Enter Lamination Charges" value="<?= $lamination_charges ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>Pinning Charges:</label>
                        <input type="number" name="pinning_charges" placeholder="Enter Pinning Charges" value="<?= $pinning_charges ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                </div>
                <div class="container">
                    <div class="form-group">
                        <label>Binding Charges:</label>
                        <input type="number" name="binding_charges" placeholder="Enter Binding Charges" value="<?= $binding_charges ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>Finishing Charges:</label>
                        <input type="number" name="finishing_charges" placeholder="Enter Finishing Charges" value="<?= $finishing_charges ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>Other Charges:</label>
                        <input type="number" name="other_charges" placeholder="Enter Other Charges" value="<?= $other_charges ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>Discount:</label>
                        <input type="number" name="discount" placeholder="Enter Discount" value="<?= $discount ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                </div>
            </div>

            <div class="customer-container" style="display:flex;gap:20px;justify-content:space-evenly;">
                <div class="form-group">
                    <label>Paper Charges:</label>
                    <input type="number" name="printing_charges" id="printing_charges" placeholder="Enter Paper Charges" value="<?= $printing_charges ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Total Charges:</label>
                    <input type="number" name="total_charges" placeholder="Total Charges" value="<?= $total_charges ?>" readonly>
                </div>
            </div>

            <?php if ($mode !== 'view'): ?>
            <div class="customer-container" style="display:flex;justify-content:space-between;">
                <button type="submit" class="job-sheet-btn" onclick="setStatus('Draft')">Save</button>
                <button type="button" class="job-sheet-btn" onclick="confirmFinalize()">Finalize</button>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <!-- Custom dialog for finalize -->
    <div id="finalizeDialog" class="dialog-overlay">
        <div class="dialog-box">
            <p>Are you sure you want to finalize this job sheet?</p>
            <button class="yes-btn" id="finalizeYesBtn">Yes</button>
            <button class="no-btn" id="finalizeNoBtn">No</button>
        </div>
    </div>

    <script>
        var itemsData = <?= json_encode($items) ?>;
        var mode = '<?= $mode ?>';

        function selectCustomer(customer) {
            if (mode !== 'view' && mode !== 'edit') {
                document.getElementById("customer_name").value = customer.customer_name;
                document.getElementById("phone_number").value = customer.phone_number;
                fetchCustomerType(customer.customer_name);
                document.querySelector('.job-sheet-form').scrollIntoView({ behavior: "smooth" });
            }
        }

        function fetchCustomerType(customerName) {
            let xhr = new XMLHttpRequest();
            xhr.open("POST", "", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    let response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        window.customerType = response.customer_type;
                        updateCharges();
                    }
                }
            };
            xhr.send("customer_name=" + encodeURIComponent(customerName) + "&get_customer_type=true");
        }

        function updateTypeDropdown() {
            if (mode === 'view') return;
            var subcategoryId = document.getElementById("paper").value;
            var typeDropdown = document.getElementById("type");
            var typeContainer = document.getElementById("type-container");

            typeDropdown.innerHTML = '<option value="">Select Type</option>';
            if (subcategoryId && itemsData[subcategoryId]) {
                itemsData[subcategoryId].forEach(item => {
                    var option = document.createElement("option");
                    option.value = item.id;
                    option.textContent = item.item_name;
                    typeDropdown.appendChild(option);
                });
                typeContainer.style.display = "block";
            } else {
                typeContainer.style.display = "none";
            }
        }

        function fetchSellingPrice() {
            if (mode === 'view') return;
            let subcategoryId = document.getElementById("paper").value;
            let itemId = document.getElementById("type").value;

            if (subcategoryId && itemId) {
                let xhr = new XMLHttpRequest();
                xhr.open("POST", "", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        let response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            document.getElementById("selling_price").value = response.selling_price;
                            calculatePrintingCharges();
                        } else {
                            alert("Selling price not found!");
                        }
                    }
                };
                xhr.send("subcategory_id=" + subcategoryId + "&item_id=" + itemId);
            }
        }

        function calculatePrintingCharges() {
            let quantity = parseFloat(document.getElementById("quantity").value) || 0;
            let sellingPrice = parseFloat(document.getElementById("selling_price").value) || 0;
            let printingCharges = quantity * sellingPrice;
            document.getElementById("paper_charges").value = printingCharges.toFixed(2);
            calculateTotalCharges();
        }

        function calculateStriking() {
            let quantity = parseFloat(document.getElementById("quantity").value) || 0;
            let printingType = document.getElementById("printingType").value;
            let strikingField = document.getElementById("striking");

            if (quantity <= 0) {
                strikingField.value = 0;
                return;
            }

            if (printingType === "Company") {
                strikingField.value = quantity * 2;
            } else {
                strikingField.value = quantity;
            }
            calculatePrintingCharges();
        }

        function calculateStrikingAndUpdate() {
            if (mode === 'view') return;
            calculateStriking();
            let customerType = document.querySelector('input[name="customerType"]:checked').value;
            if (customerType === "Publication") {
                fetchAndCalculate();
            } else {
                updateCharges();
            }
        }

        function updateMachineOptions() {
            if (mode === 'view') return;
            hideAllMachineOptions();
            let selectedMachine = document.querySelector('input[name="machine"]:checked');
            if (selectedMachine) {
                if (selectedMachine.value === "RYOBI") {
                    document.getElementById("ryobi-options").style.display = "block";
                } else if (selectedMachine.value === "Web") {
                    document.getElementById("web-options").style.display = "block";
                    document.getElementById("web-sub-options").style.display = "block";
                } else if (selectedMachine.value === "CTP") {
                    document.querySelector(".ctp-section").style.display = "none";
                    document.getElementById("ctpPlateSection").style.display = "block";
                }
            }
            updateCharges();
        }

        function updateCharges() {
            if (mode === 'view') return;
            let customerType = document.querySelector('input[name="customerType"]:checked').value;
            if (customerType === "Publication") {
                fetchAndCalculate();
            } else {
                document.getElementById("printing_charges").value = 0;
                calculateTotalCharges();
            }
        }

        async function fetchAndCalculate() {
            if (mode === 'view') return;
            calculateStriking();
            let isMember = window.customerType || "<?php echo $customer_type; ?>";
            let striking = parseFloat(document.getElementById("striking").value) || 0;
            let selectedMachine = document.querySelector('input[name="machine"]:checked');

            if (!selectedMachine) {
                alert("Please select a machine type.");
                return;
            }

            if (!striking || striking <= 0) {
                alert("Please ensure the quantity and printing type are set to calculate striking.");
                return;
            }

            let machineType = selectedMachine.value;
            if (machineType === "RYOBI") {
                let ryobiType = document.querySelector('select[name="ryobi_type"]').value;
                if (ryobiType === "color") {
                    machineType = "RYOBI_COLOR";
                }
            }

            try {
                const response = await fetch('fetch_pricing.php');
                if (!response.ok) {
                    throw new Error('Failed to fetch pricing data');
                }
                const pricingTable = await response.json();

                if (!pricingTable[machineType]) {
                    alert("Invalid machine type selected.");
                    return;
                }

                let rates = pricingTable[machineType][isMember];
                let adjustedQuantity = Math.max(0, Math.ceil((striking - 299) / 1000)) - 1;
                let price = rates['first'] + (adjustedQuantity * rates['next']);
                document.getElementById("printing_charges").value = price.toFixed(2);
                calculateTotalCharges();
            } catch (error) {
                console.error("Error fetching pricing data:", error);
                alert("An error occurred while fetching pricing data. Please try again.");
            }
        }

        function calculateTotalCharges() {
            let printingCharges = parseFloat(document.getElementById("paper_charges").value) || 0;
            let platingCharges = parseFloat(document.getElementsByName("plating_charges")[0].value) || 0;
            let laminationCharges = parseFloat(document.getElementsByName("lamination_charges")[0].value) || 0;
            let pinningCharges = parseFloat(document.getElementsByName("pinning_charges")[0].value) || 0;
            let bindingCharges = parseFloat(document.getElementsByName("binding_charges")[0].value) || 0;
           

 let finishingCharges = parseFloat(document.getElementsByName("finishing_charges")[0].value) || 0;
            let otherCharges = parseFloat(document.getElementsByName("other_charges")[0].value) || 0;
            let discount = parseFloat(document.getElementsByName("discount")[0].value) || 0;

            let total = printingCharges + platingCharges + laminationCharges + pinningCharges + 
                        bindingCharges + finishingCharges + otherCharges - discount;
            total = Math.max(total, 0);
            document.getElementsByName("total_charges")[0].value = total.toFixed(2);
        }

        document.addEventListener("DOMContentLoaded", function () {
            if (mode === 'edit' || mode === 'add') {
                document.getElementById("striking").addEventListener("input", function() {
                    updateCharges();
                });
                document.getElementById("customer_name").addEventListener("change", function() {
                    fetchCustomerType(this.value);
                });
                let fieldsToWatch = ["paper_charges", "printing_charges", "plating_charges", "lamination_charges", "pinning_charges", 
                                    "binding_charges", "finishing_charges", "other_charges", "discount", "total_charges"];
                fieldsToWatch.forEach(fieldName => {
                    document.getElementsByName(fieldName)[0].addEventListener("input", calculateTotalCharges);
                });

                document.querySelectorAll('input[name="machine"]').forEach(machine => {
                    machine.addEventListener("change", function() {
                        updateMachineOptions();
                    });
                });

                let ryobiTypeSelect = document.querySelector('select[name="ryobi_type"]');
                if (ryobiTypeSelect) {
                    ryobiTypeSelect.addEventListener("change", function() {
                        updateCharges();
                    });
                }

                document.querySelectorAll('input[name="customerType"]').forEach(radio => {
                    radio.addEventListener("change", function() {
                        updateCharges();
                    });
                });
            }
        });

        function hideAllMachineOptions() {
            document.getElementById("ryobi-options").style.display = "none";
            document.getElementById("web-options").style.display = "none";
            document.getElementById("web-sub-options").style.display = "none";
            document.getElementById("ctpPlateSection").style.display = "none";
            document.querySelector(".ctp-section").style.display = "block";
        }

        let platecharges = <?= $plating_charges ?: 0 ?>;
        const platePrices = {
            '700x945': 350,
            '610x890': 300,
            '560x670': 250,
            '335x485': 150
        };

        function addPlate() {
            if (mode === 'view') return;
            let plateSize = document.getElementById("plateSize").value;
            let quantity = parseInt(document.getElementById("plateQuantity").value);
            let plateList = document.getElementById("plateList");

            if (!plateSize || plateSize === "Select" || isNaN(quantity) || quantity <= 0) {
                alert("Please select a plate size and enter a valid quantity.");
                return;
            }

            platecharges += platePrices[plateSize] * quantity;
            let div = document.createElement("div");
            div.className = "plate-item";
            div.innerHTML = `${plateSize} - ${quantity}  
                            <button onclick="removePlate(this, ${platePrices[plateSize] * quantity})">Delete</button>`;
            plateList.appendChild(div);
            document.querySelector("input[name='plating_charges']").value = platecharges;
            calculateTotalCharges();
        }

        function removePlate(button, plateCost) {
            if (mode === 'view') return;
            button.parentElement.remove();
            platecharges -= plateCost;
            document.querySelector("input[name='plating_charges']").value = platecharges;
            calculateTotalCharges();
        }

        function setStatus(status) {
            document.getElementById("status").value = status;
        }

        function confirmFinalize() {
            let dialog = document.getElementById('finalizeDialog');
            dialog.style.display = 'flex';

            document.getElementById('finalizeYesBtn').onclick = function() {
                dialog.style.display = 'none';
                document.getElementById("status").value = 'Finalized';
                console.log("Form status set to: " + document.getElementById("status").value);
                console.log("Submitting form...");
                document.getElementById("jobForm").submit();
            };

            document.getElementById('finalizeNoBtn').onclick = function() {
                dialog.style.display = 'none';
            };
        }
    </script>
</body>
</html>