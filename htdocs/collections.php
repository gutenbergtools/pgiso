<?php

  require_once("config.php");
  require_once("functions.php");
  session_start();

  // connect to MySQL and select gutenberg database
mysqli_connect("localhost", $mysqli_database, $mysqli_password)

    or die("Unable to connect to database.");
mysqli_select_db($mysqli_database);

  if(isset($_GET['edit']) || isset($_GET['publish']) || isset($_GET['unpublish']) || isset($_GET['delete'])
     || isset($_GET['update']) || isset($_GET['rename']) || isset($_GET['export']) )
  {
    foreach($_GET as $key => $id)
    {
      if(isset($id))
      {
        $action = $key;
        $collection = (int)$id;
        break;
      }
    }

    switch($action)
    {
      case "edit":
        $_SESSION['collection'] = $collection;
        $query = "SELECT size FROM collections WHERE username='" . $_SESSION['username'] . "' " .
                 "AND coll_id='" . $_SESSION['collection'] . "'";
        $result = mysqli_query($query);
        $row = mysqli_fetch_array($result);
        $_SESSION['iso_size_bytes'] = $row['size'];
        header("Location: " . BASEURL . "/index.php");
        break;
      case "publish":
        $query = "SELECT coll_name FROM collections WHERE username='" . $_SESSION['username'] . "' " .
                 "AND coll_id='" . $collection . "'";
        $result = mysqli_query($query);
        $row = mysqli_fetch_array($result);
        $query = "INSERT INTO published VALUES ('" . $_SESSION['username'] . "','" . 
                 $collection . "','" . addslashes($row['coll_name']) . "')";
        mysqli_query($query);
        break;
      case "unpublish":
        $query = "DELETE FROM published WHERE username='" . $_SESSION['username'] . "' " .
                 "AND coll_id='" . $collection . "'";
        mysqli_query($query);
        break;
      case "delete":
        $query = "DELETE FROM pgiso_perm WHERE username='" . $_SESSION['username'] . "' " .
                 "AND coll_id='" . $collection . "'";
        mysqli_query($query);
        $query = "DELETE FROM collections WHERE username='" . $_SESSION['username'] . "' " .
                 "AND coll_id='" . $collection . "'";
        mysqli_query($query);
        $query = "DELETE FROM published WHERE username='" . $_SESSION['username'] . "' " .
                 "AND coll_id='" . $collection . "'";
        mysqli_query($query);
        break;
      case "update":
        update_iso_size($collection);
        break;
    }
  }

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>My Collections</title>
    <link rel="stylesheet" type="text/css" href="style.css" />
  </head>
  <body>
  <center>
  <br />
  <form method="post" action="collections.php">

  <table class="pageinfo" cellspacing="0" cellpadding="0" width="600"><tr>
    <td width="400" align="left">
      <a href="index.php">edit iso</a> /
      <a href="published.php">published collections</a>
      <?php if(isset($_SESSION['username'])) echo " / <a href=\"login.php?logout=1\">logout</a>"; ?>
    </td>
    <td width="200" align="right">
      <?php if(isset($_SESSION['username'])) echo "logged in as: " . $_SESSION['username']; ?>
    </td>
  </tr></table>

<?php

  if(isset($_SESSION['username']))
  {
    mysqli_connect("localhost", $mysqli_database, $mysqli_password)
      or die("Unable to connect to database.");
      mysqli_select_db($mysqli_database);

    if(isset($_POST['create']) && !empty($_POST['new_coll']))
    {
      $coll_name = addslashes($_POST['coll_name']);
      mysqli_connect("localhost", $mysqli_database, $mysqli_password)

        or die("Unable to connect to database.");
      mysqli_select_db($mysqli_database);

      $query = "SELECT coll_id FROM collections " .
               "WHERE username='" . $_SESSION['username'] . "' ORDER BY coll_id DESC LIMIT 1";
      $result = mysqli_query($query);
      $row = mysqli_fetch_array($result);
      $new_id = $row['coll_id'] + 1;

      $query = "INSERT INTO collections VALUES ('" . $_SESSION['username'] . "','" . $new_id . "','" .
               $_POST['new_coll'] . "','0','0','" . date("Y-m-d") . "')";
      mysqli_query($query);
    }
    else if(isset($_POST['delete']))
    {
      $id_array = array_keys($_POST['delete']);
      $id = (int)array_pop($id_array);
      $query = "DELETE FROM collections " .
               "WHERE username='" . $_SESSION['username'] . "' AND coll_id='" . $id . "'";
      mysqli_query($query);
      $query = "DELETE FROM pgiso_perm " .
               "WHERE username='" . $_SESSION['username'] . "' AND coll_id='" . $id . "'";
    }

    $query = "SELECT coll_id,coll_name,etexts,size,lastchange FROM collections " .
             "WHERE username='" . $_SESSION['username'] . "' ORDER BY coll_name ASC";
    $result = mysqli_query($query);

    if(mysqli_num_rows($result))
    {
      echo "<table class=\"collection\" cellspacing=\"0\" width=\"600\">" .
           "<tr class=\"darkrow\"><td class=\"caption\" colspan=\"2\">My Collections</td></tr>";
    }

    for($i=1; $row = mysqli_fetch_array($result); $i++)
    {
      // convert total ISO size in bytes to megabytes
      $iso_size_mb = $row['size'] / 1048576;

      // make a rough estimate of the size added to the ISO by index files
      $iso_size_mb += $row['etexts'] * 0.00277;

      // convert the datestamp stored in MySQL to a timestamp usable by date() function
      $timestamp = strtotime($row['lastchange']);

      $query = "SELECT COUNT(*) FROM published WHERE username='" . $_SESSION['username'] . "' " .
               "AND coll_id='" . $row['coll_id'] . "'";
      $pub_result = mysqli_query($query);
      $published = mysqli_fetch_array($pub_result);

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
             "</span>" .
           "</td>" .
           "<td class=\"actions\" width=\"200\">" .
             "<a href=\"collections.php?edit=" . $row['coll_id'] . "\">edit</a> | ";

      if($published['COUNT(*)'])
        echo "<a href=\"collections.php?unpublish=" . $row['coll_id'] . "\">unpublish</a> | ";
      else
        echo "<a href=\"collections.php?publish=" . $row['coll_id'] . "\">publish</a> | ";

      echo   "<a href=\"collections.php?update=" . $row['coll_id'] . "\">update</a> | " .
             "<a href=\"collections.php?delete=" . $row['coll_id'] . "\">delete</a><br />" .
             "<a href=\"rename.php?coll=" . $row['coll_id'] . "\">rename</a> | " .
             "<a href=\"export.php?user=" . $_SESSION['username'] . "&coll=" .
               $row['coll_id'] . "\">export</a> | " .
             "<a href=\"createiso.php?user=" . $_SESSION['username'] . "&coll=" .
               $row['coll_id'] . "\">create iso</a>" .
           "</td></tr>";
    }

    if(mysqli_num_rows($result))
      echo "</table><br />";
  }
  else
  {
    echo "Please <a href=\"login.php\">login</a> or <a href=\"register.php\">register</a>.<br />" .
         "</center></body></html>";
    exit;
  }

?>
  <div class="caption">Create New Collection</div>
  <div class="content"><br />
    <input type="text" name="new_coll" size="60" /><br /><br />
    <input type="submit" name="create" value="Create Collection" /><br /><br />
  </div>
  </form>
   <font size="2">Please send questions and feedback to help2014 at pglaf.org, also refer to the <a href="faq.html">FAQ</a>.</font><br />
  </center>
  </body>
</html>
