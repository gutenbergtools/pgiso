<?php

/* ***************************************
   * Coded by Craig Stephenson           *
   * Arctic Region Supercomputing Center *
   * University of Alaska Fairbanks      *
   * July 2005                           *
   *************************************** */

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>User Registration</title>
    <link rel="stylesheet" type="text/css" href="style.css" />
  </head>
  <body>
  <center>
  <br />

<?php
require_once("config.php");
require_once("functions.php");
session_start();

  if(isset($_POST['posted']))
  {
    if(ereg("^[A-Za-z0-9_]+$", $_POST['username']) && $_POST['password'] == $_POST['repassword'])
    {
      mysqli_connect("localhost", $mysqli_database, $mysqli_password)
        or die("Unable to connect to database.");
      mysqli_select_db($mysqli_database);
      $username = strtolower($_POST['username']);
      $passhash = sha1($_POST['password']);

      $query = "SELECT COUNT(*) FROM users WHERE username='" . $username . "'";
      $result = mysqli_query($query);
      $row = mysqli_fetch_array($result);
      if(!$row['COUNT(*)'])
      {
        $query = "INSERT INTO users VALUES ('" . $username . "','" . $passhash . "')";
        $result = mysqli_query($query);
        if($result)
          echo "You are now registered. Please <a href=\"login.php\">login</a>.<br /><br />";
      }
      else
      {
        echo "This username has already been registered.<br /><br />";
      }
    }
    else if(!ereg("^[A-Za-z0-9_]+$", $_POST['username']))
    {
      echo "You have entered an invalid username. Usernames may only " .
           "contain letters, numbers or underscores.<br /><br />";
    }
    else if($_POST['password'] != $_POST['repassword'])
    {
      echo "Your password and re-typed password did not match. Try again.<br /><br />";
    }
  }

?>

  <table class="pageinfo" cellspacing="0" cellpadding="0" width="600"><tr>
    <td width="400" align="left">
      <a href="index.php">edit collection</a> / 
      <a href="published.php">published collections</a> / 
      <a href="login.php">login</a>
    </td>
  </tr></table>

  <div class="caption">Registration</div>
  <div class="content">
    <br />
    <form method="post" action="register.php">
      <table>
      <tr><td>Username: </td><td><input type="text" name="username" /></td></tr>
      <tr><td>Password: </td><td><input type="password" name="password" /></td></tr>
      <tr><td>Re-type Password: </td><td><input type="password" name="repassword" /></td></tr>
      </table><br />
      <input type="submit" name="posted" value="Register" />
    </form>
  </div>

  <br />
    <font size="2">Please send questions and feedback to help2014 at pglaf.org, also refer to the <a href="faq.html">FAQ</a>.</font><br />
  </center>
  </body>
</html>
