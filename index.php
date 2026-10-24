<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "atm_system";

try {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    $conn->set_charset("utf8mb4");
} catch (Throwable $e) {
    http_response_code(500);
    die("Database connection failed. Import db.sql and check the database settings in index.php.");
}

$message = "";
$messageType = "success";

function money($amount) {
    return "₹" . number_format((float)$amount, 2);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "login") {
        $pin = trim($_POST["pin"] ?? "");
        $stmt = $conn->prepare("SELECT id FROM users WHERE pin_hash = SHA2(?, 256) LIMIT 1");
        $stmt->bind_param("s", $pin);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = (int)$user["id"];
            header("Location: index.php");
            exit;
        }
        $message = "Incorrect PIN. Please try again.";
        $messageType = "error";
    }

    if (isset($_SESSION["user_id"])) {
        $userId = (int)$_SESSION["user_id"];

        if ($action === "deposit" || $action === "withdraw") {
            $amount = filter_var($_POST["amount"] ?? null, FILTER_VALIDATE_FLOAT);
            if ($amount === false || $amount <= 0 || $amount > 1000000) {
                $message = "Enter a valid amount greater than ₹0.";
                $messageType = "error";
            } else {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    if (!$row) {
                        throw new Exception("Account not found.");
                    }
                    $balance = (float)$row["balance"];
                    if ($action === "withdraw" && $amount > $balance) {
                        $conn->rollback();
                        $message = "Insufficient balance.";
                        $messageType = "error";
                    } else {
                        $newBalance = $action === "deposit" ? $balance + $amount : $balance - $amount;
                        $update = $conn->prepare("UPDATE users SET balance = ? WHERE id = ?");
                        $update->bind_param("di", $newBalance, $userId);
                        $update->execute();

                        $type = $action === "deposit" ? "Deposit" : "Withdrawal";
                        $txn = $conn->prepare("INSERT INTO transactions (user_id, transaction_type, amount, balance_after) VALUES (?, ?, ?, ?)");
                        $txn->bind_param("isdd", $userId, $type, $amount, $newBalance);
                        $txn->execute();
                        $conn->commit();
                        $message = $type . " successful. New balance: " . money($newBalance);
                    }
                } catch (Throwable $e) {
                    $conn->rollback();
                    $message = "Transaction failed. Please try again.";
                    $messageType = "error";
                }
            }
        } elseif ($action === "change_pin") {
            $newPin = trim($_POST["new_pin"] ?? "");
            if (!preg_match('/^[0-9]{4}$/', $newPin)) {
                $message = "PIN must contain exactly 4 digits.";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("UPDATE users SET pin_hash = SHA2(?, 256) WHERE id = ?");
                $stmt->bind_param("si", $newPin, $userId);
                $stmt->execute();
                $message = "PIN changed successfully.";
            }
        } elseif ($action === "logout") {
            $_SESSION = [];
            session_destroy();
            header("Location: index.php");
            exit;
        }
    }
}

$balance = 0;
$transactions = [];
if (isset($_SESSION["user_id"])) {
    $userId = (int)$_SESSION["user_id"];
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user) {
        $balance = (float)$user["balance"];
        $stmt = $conn->prepare("SELECT transaction_type, amount, balance_after, created_at FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 10");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        unset($_SESSION["user_id"]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ATM System</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<main class="container">
  <div class="brand"><div class="brand-icon">₹</div><div><h1>Secure ATM</h1><p>Banking demo system</p></div></div>

  <?php if ($message !== ""): ?>
    <div class="message <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if (!isset($_SESSION["user_id"])): ?>
    <section class="login-panel">
      <h2>Welcome back</h2><p class="muted">Enter your 4-digit PIN to continue.</p>
      <form method="post">
        <input type="hidden" name="action" value="login">
        <label for="pin">ATM PIN</label>
        <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="••••" required autofocus>
        <button class="primary" type="submit">Login securely</button>
      </form>
      <p class="hint">Demo PIN: <strong>1234</strong></p>
    </section>
  <?php else: ?>
    <section class="balance-card">
      <div><p class="muted light">Available balance</p><h2><?= htmlspecialchars(money($balance)) ?></h2></div>
      <span class="status">● Account active</span>
    </section>

    <section class="actions">
      <h2>ATM services</h2>
      <div class="action-grid">
        <form method="post" class="action-form">
          <input type="hidden" name="action" value="deposit">
          <label for="deposit-amount">Deposit money</label>
          <input id="deposit-amount" name="amount" type="number" min="0.01" step="0.01" placeholder="Amount (₹)" required>
          <button class="primary" type="submit">Deposit</button>
        </form>
        <form method="post" class="action-form">
          <input type="hidden" name="action" value="withdraw">
          <label for="withdraw-amount">Withdraw money</label>
          <input id="withdraw-amount" name="amount" type="number" min="0.01" step="0.01" placeholder="Amount (₹)" required>
          <button class="secondary" type="submit">Withdraw</button>
        </form>
        <form method="post" class="action-form">
          <input type="hidden" name="action" value="change_pin">
          <label for="new-pin">Change PIN</label>
          <input id="new-pin" name="new_pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="New 4-digit PIN" required>
          <button class="secondary" type="submit">Update PIN</button>
        </form>
        <form method="post" class="action-form logout-form">
          <input type="hidden" name="action" value="logout">
          <label>Finish session</label>
          <p class="muted small">Log out when you are done.</p>
          <button class="danger" type="submit">Logout</button>
        </form>
      </div>
    </section>

    <section class="history">
      <div class="section-heading"><h2>Recent transactions</h2><span>Last 10</span></div>
      <?php if (!$transactions): ?>
        <p class="muted empty">No transactions yet. Make your first deposit.</p>
      <?php else: ?>
        <div class="table-wrap"><table>
          <thead><tr><th>Type</th><th>Amount</th><th>Balance after</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($transactions as $txn): ?>
            <tr>
              <td><?= htmlspecialchars($txn["transaction_type"]) ?></td>
              <td><?= htmlspecialchars(money($txn["amount"])) ?></td>
              <td><?= htmlspecialchars(money($txn["balance_after"])) ?></td>
              <td><?= htmlspecialchars(date("d M Y, H:i", strtotime($txn["created_at"]))) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
  <footer>ATM System · Educational demo only</footer>
</main>
</body>
</html>
