<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Register - Vocabulary App</title>
  <style>
    body {
      display: flex; justify-content: center; align-items: center; 
      min-height: 100vh; min-height: 100dvh; margin: 0; font-family: Arial, sans-serif; 
      background: #f5f5f5; padding: 20px;
    }
    .card {
      background: white; padding: 20px; border-radius: 12px; 
      box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
      width: 100%; max-width: 400px;
    }
    .card input, .card button {
      width: 100%; padding: 10px; margin: 8px 0; 
      font-size: 1em; border-radius: 8px; border: 1px solid #ccc;
    }
    .card button {
      background: #007BFF; color: white; border: none; cursor: pointer;
    }
    .card button:hover { background: #0056b3; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <div class="card">
    <h2>Register</h2>
    <form>
      <input type="text" placeholder="Full Name" required>
      <input type="email" placeholder="Email Address" required>
      <input type="password" placeholder="Password" required>
      <button type="submit">Register</button>
    </form>
  </div>
</body>
</html>
