<?php

/* ***************************************
   * Coded by Craig Stephenson           *
   * Arctic Region Supercomputing Center *
   * University of Alaska Fairbanks      *
   * July 2005                           *
   *************************************** */

  require_once("config.php");
  session_start();

  if($_GET['logout'])
    //if(isset($_POST['logout']))
  {
    unset($_SESSION['username']);
    unset($_SESSION['collection']);
    unset($_SESSION['formats']);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>Logged Out</title>
    <link rel="stylesheet" type="text/css" href="style.css" />
  </head>
  <body>
  <center>
   <p>Thanks, bye.</p>
   <p><a href="index.php">Start again</a></p>
   </body></html>

<?php
   exit;
  }


  if(isset($_POST['posted']) && ereg("^[A-Za-z0-9_]+$", $_POST['username']) && !empty($_POST['password']))
  {
    mysqli_connect("localhost", $mysqli_database, $mysqli_password)
      or die("Unable to connect to database.");
    mysqli_select_db($mysqli_database);
    $username = strtolower($_POST['username']);
    $passhash = sha1($_POST['password']);
    $query = "SELECT * FROM users WHERE username='" . $username . "' AND password='" . $passhash . "'";
    $result = mysqli_query($query);
    if(mysqli_num_rows($result))
    {
      $_SESSION['username'] = $username;
      header("Location: " . BASEURL . "/collections.php");
    }
  }

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>User Login</title>
    <link rel="stylesheet" type="text/css" href="style.css" />
  </head>
  <body>
  <center>
  <br />

<?php

  if(isset($_POST['posted']))
  {
    if(ereg("^[A-Za-z0-9_]+$", $_POST['username']) && !empty($_POST['password']) && !mysqli_num_rows($result))
    {
      echo "The username and password you provided are invalid.<br /><br />";
    }
    else if(!empty($_POST['username']) && !empty($_POST['password']))
    {
      echo "The username and password you provided contain invalid characters.<br /><br />";
    }
    else
    {
      echo "You left one or both of the fields empty.<br /><br />";
    }
  }

?>

  <table class="pageinfo" cellspacing="0" cellpadding="0" width="600"><tr>
    <td width="400" align="left">
      <a href="index.php">edit collection</a> / 
      <a href="published.php">published collections</a> / 
      <a href="register.php">register</a></div>
    </td>
  </tr></table>

  <div class="caption">Login</div>
  <div class="content">
    <br />
    <form method="post" action="login.php">
      <table>
      <tr><td>Username: </td><td><input type="text" name="username" /></td></tr>
      <tr><td>Password: </td><td><input type="password" name="password" /></td></tr>
      </table><br />
      <input type="submit" name="posted" value="Login" />
    </form>
  </div>

  <br />
    <font size="2">Please send questions and feedback to help2014 at pglaf.org, also refer to the <a href="faq.html">FAQ</a>.</font><br />
  </center>
  </body>
</html>
