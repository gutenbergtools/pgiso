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

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>Published Collections</title>
    <link rel="stylesheet" type="text/css" href="style.css" />
  </head>
  <body>
  <center>
  <br />
  <form method="post" action="collections.php">

  <table class="pageinfo" cellspacing="0" cellpadding="0" width="600"><tr>
    <td width="400" align="left">
    <?php
      if(isset($_SESSION['username']))
      {
        echo "<a href=\"index.php\">edit iso</a> / ";
        echo "<a href=\"collections.php\">my collections</a> / ";
        echo "<a href=\"login.php?logout=1\">logout</a>";
      }
      else
      {
        echo "<a href=\"index.php\">edit iso</a> / ";
        echo "<a href=\"login.php\">login</a> / ";
        echo "<a href=\"register.php\">register</a>";
      }
    ?>
    </td>
    <td width="200" align="right">
      <?php if(isset($_SESSION['username'])) echo "logged in as: " . $_SESSION['username']; ?>
    </td>
  </tr></table>

<?php

mysqli_connect("localhost", $mysqli_database, $mysqli_password)
    or die("Unable to connect to database.");
mysqli_select_db($mysqli_database);

  // 7/20/06
  // This query is a quick hack to keep published collections in alphabetical order even when
  // users change collection names. A proper fix will require some database table restructuring.
  $query = "SELECT published.username,published.coll_id FROM published,collections WHERE published.username = collections.username AND collections.coll_id = published.coll_id ORDER BY collections.coll_name ASC";

  // old query
  // $query = "SELECT username,coll_id FROM published ORDER BY coll_name ASC";

  $result = mysqli_query($query);

  if(mysqli_num_rows($result))
  {
    echo "<table class=\"collection\" cellspacing=\"0\" width=\"600\">" .
         "<tr class=\"darkrow\"><td class=\"caption\" colspan=\"2\">Published Collections</td></tr>";
  }

  for($i=1; $collection = mysqli_fetch_array($result); $i++)
  {
    $query = "SELECT * FROM collections WHERE username='" . $collection['username'] . "' " .
             "AND coll_id='" . $collection['coll_id'] . "'";
    $etext_result = mysqli_query($query);
    $row = mysqli_fetch_array($etext_result);

    // convert total ISO size in bytes to megabytes
    $iso_size_mb = $row['size'] / 1048576;

    // make a rough estimate of the size added to the ISO by index files
    $iso_size_mb += $row['etexts'] * 0.00277;

    // convert the datestamp stored in MySQL to a timestamp usable by date() function
    $timestamp = strtotime($row['lastchange']);

    if($i % 2 == 1)
      $class = "lightrow";
    else
      $class = "darkrow";

    echo "<tr class=\"" . $class . "\">" .
         "<td class=\"title\" width=\"400\"><strong>" . $row['coll_name'] . "</strong><br />" .
           "<span class=\"smalltext\">" .
             "<strong>ETexts:</strong> " . $row['etexts'] . ", " .
             "<strong>Size:</strong> " . number_format($iso_size_mb, 2, ".", ",") . " mb, " .
             "<strong>Last Change:</strong> " . date("F j, Y", $timestamp) .
             "</span></td>" .
           "<td class=\"actions\" width=\"200\">" .
             "<a href=\"view.php?user=" . $row['username'] . "&coll=" . $row['coll_id'] . "\">view</a> | " .
             "<a href=\"export.php?user=" . $row['username'] . "&coll=" . $row['coll_id'] . "\">export</a><br />" .
             "<a href=\"createiso.php?user=" . $row['username'] . "&coll=" . $row['coll_id'] . "\">create iso</a>" .
           "</td></tr>";
  }

  if(mysqli_num_rows($result))
    echo "</table><br />";

?>
    <font size="2">Please send questions and feedback to help2014 at pglaf.org, also refer to the <a href="faq.html">FAQ</a>.</font><br />
  </center>
  </body>
</html>
