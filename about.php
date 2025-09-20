<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>About Class - Vocabulary App</title>
  <style>
    body {
      font-family: Arial, sans-serif; margin: 0; padding: 20px; 
      line-height: 1.6; background: #f5f5f5;
    }
    .content {
      background: white; padding: 20px; border-radius: 12px; 
      box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
      max-width: 800px; margin: auto;
    }
    h2 { color: #007BFF; }
  </style>
</head>
<body>
  <?php include __DIR__ . '/partials/nav.php'; ?>
  <div class="content">
    <h2>About the Class</h2>
    <p>This class helps students improve their English vocabulary by learning one new word every day.</p>
    <p>Features:</p>
    <ul>
      <li>Daily vocabulary updates</li>
      <li>Simple registration process</li>
      <li>Mobile-friendly interface</li>
      <li>Installable as an app (PWA)</li>
    </ul>
  </div>
</body>
</html>
