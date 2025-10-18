<?php
// register.php
include 'include/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Registration</title>
  <link rel="stylesheet" href="style.css"> 
</head>
<body>

  <h2>Register Account</h2>

  <form action="php/register_action.php" method="POST">
    <label for="name">Full Name:</label><br>
    <input type="text" id="name" name="name" required><br><br>

    <label for="email">Email Address:</label><br>
    <input type="email" id="email" name="email" required><br><br>

    <label for="password">Password:</label><br>
    <input type="password" id="password" name="password" required><br><br>

    <label for="role">Register As:</label><br>
    <select id="role" name="role" required>
      <option value="">Select Role</option>
      <option value="student">Student</option>
      <option value="faculty">Faculty</option>
    </select><br><br>

    <button type="submit">Register</button>
  </form>

  <p>Already have an account? <a href="login.php">Login here</a>.</p>

</body>
</html>
