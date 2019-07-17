<?php

/* ***************************************
   * Coded by Craig Stephenson           *
   * Arctic Region Supercomputing Center *
   * University of Alaska Fairbanks      *
   * July 2005                           *
   *************************************** */

  require_once("config.php");
  require_once("functions.php");
  session_start();

  // connect to MySQL and select gutenberg database
mysqli_connect("localhost", $mysqli_database, $mysqli_password)
    or die("Unable to connect to database.");
mysqli_select_db($mysqli_database);

  $collection = (int)$_GET['coll'];
  $query = "SELECT coll_name FROM collections WHERE username='" . $_SESSION['username'] . "' " .
           "AND coll_id='" . $collection . "'";
  $result = mysqli_query($query);
  $row = mysqli_fetch_array($result);

  if(isset($_POST['rename']))
  {
    $query = "UPDATE collections SET coll_name='" . addslashes($_POST['new_name']) . "' " .
             "WHERE username='" . $_SESSION['username'] . "' AND coll_id='" . $collection . "'";
    mysqli_query($query);

    header("Location: " . BASEURL . "/collections.php");
  }

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>Your Collections</title>
    <link rel="stylesheet" type="text/css" href="style.css" />
  </head>
  <body>
  <center>
  <br />
  <form method="post" action="rename.php?coll=<?php echo $collection; ?>">
  <table class="pageinfo" cellspacing="0" cellpadding="0" width="600"><tr>
    <td width="400" align="left">
    <?php
      if(isset($_SESSION['username']))
      {
        echo "<a href=\"collections.php\">my collections</a> / ";
        echo "<a href=\"published.php\">published collections</a> / ";
        echo "<a href=\"login.php?logout=1\">logout</a>";
      }
      else
      {
        echo "<a href=\"published.php\">published collections</a> / ";
        echo "<a href=\"login.php\">login</a> /";
        echo "<a href=\"register.php\">register</a>";
      }
    ?>
    </td>
    <td width="200" align="right">
      <?php if(isset($_SESSION['username'])) echo "logged in as: " . $_SESSION['username']; ?>
    </td>
  </tr></table>

  <div class="caption">Rename Collection</div>
  <div class="content"><br />
    <input type="text" name="new_name" size="60" value="<?php echo $row['coll_name']; ?>" /><br /><br />
    <input type="submit" name="rename" value="Rename This Collection" /><br /><br />
  </div>
  </form>

    <font size="2">Please send questions and feedback to help2014 at pglaf.org, also refer to the <a href="faq.html">FAQ</a>.</font><br />
  </center>
  </body>
</html>
