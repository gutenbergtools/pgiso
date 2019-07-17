<?php
/* ***************************************
   * Coded by Craig Stephenson           *
   * Arctic Region Supercomputing Center *
   * May 2005                            *
   *************************************** */

  require_once("config.php");
  require_once("functions.php");
  session_start();

  // connect to MySQL and select gutenberg database
$link = mysqli_connect("localhost", $mysqli_user, $mysqli_password, $mysqli_database);
  if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
  }

//  if(ereg("^[A-Za-z0-9_]+$", $_GET['user']))
if (isset($_GET['user'])) {
    if(preg_match("/^[A-Za-z0-9_]+$/", $_GET['user']))
        $username = $_GET['user'];
}
else {
    echo "Invalid user.<br /><br />"; }

  $collection = (int)$_GET['coll'];

  // if there is no page contained in the URL yet, set to the first page
  if(empty($_GET['page']))
    $currentpage = 1;
  else
    $currentpage = (int)$_GET['page'];

  // if there is no sort order contained in the URL, set to "id"
  // else, add slashes to $_GET['sortby'] to prevent abuse
  if(empty($_GET['sortby']))
    $_GET['sortby'] = "time";
  else
    $_GET['sortby'] = addslashes($_GET['sortby']);

  $query = "SELECT * FROM published WHERE username='" . $username . "' AND coll_id='" . $collection . "'";
  $result = mysqli_query($query);
  if(!mysqli_num_rows($result))
    die("Invalid Collection");

  $query = "SELECT * FROM collections WHERE username='" . $username . "' AND coll_id='" . $collection . "'";
  $result = mysqli_query($query);
  $row = mysqli_fetch_array($result);

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>View Collection</title>
    <link rel="stylesheet" type="text/css" href="style.css" />
  </head>
  <body>
  <center>
  <br />

  <table class="pageinfo" cellspacing="0" cellpadding="0" width="600"><tr>
    <td width="400" align="left">
    <?php
      if(isset($_SESSION['username']))
      {
        echo "<a href=\"published.php\">published collections</a> / ";
        echo "<a href=\"createiso.php?user=" . $username . "&coll=" . $collection . "\">create iso</a> / ";
        echo "<a href=\"collections.php\">my collections</a> / ";
        echo "<a href=\"login.php?logout=1\">logout</a>";
      }
      else
      {
        echo "<a href=\"published.php\">published collections</a> / ";
        echo "<a href=\"index.php\">edit collection</a> / ";
        echo "<a href=\"createiso.php?user=" . $username . "&coll=" . $collection . "\">create iso</a> / ";
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

  // convert collection size to megabytes and add estimated size of index files
  $iso_size_mb = $row['size'] / 1048576;
  $iso_size_mb += $row['etexts'] * 0.00277;

  // convert the datestamp stored in MySQL to a timestamp usable by date() function
  $timestamp = strtotime($row['lastchange']);

  echo "<div class=\"caption\">\"" . $row['coll_name'] . "\"</div>" .
       "<div class=\"collstats\"><strong>Created by:</strong> " . $username . "<br />" .
       "<strong>Last changed:</strong> " . date("F j, Y", $timestamp) . "<br />" .
       "<strong>ETexts:</strong> " . $row['etexts'] . "<br />" .
       "<strong>ISO Size:</strong> " . number_format($iso_size_mb, 2, ".", ",") .
       " mb</div>";

  // retrieve the total number of etexts for this collection
  $query = "SELECT COUNT(*) FROM pgiso_perm WHERE username='" . $username . "' " .
             "AND coll_id='" . $collection . "'";

  $result = mysqli_query($query);
  $row = mysqli_fetch_array($result);

  // determine the total number of pages by dividing by 25 and rounding up
  $etextstotal = $row['COUNT(*)'];
  $pagestotal = $etextstotal / 25;
  $pagestotal = ceil($pagestotal);

  // calculate the record offset for current page
  $offset = ($currentpage - 1) * 25;

  // set the start and end ranges for this page's records
  $startrange = $offset + 1;
  $endrange = $offset + 25;

  // "sort by" arrays, translate "sort by" name from URL to its corresponding table/column
  $sortperm = array(
    "id" => "ORDER BY pgiso_perm.id ASC, pgiso_perm.format ASC",
    "title" => "ORDER BY formats_sizes.title ASC, formats_sizes.author ASC",
    "author" => "ORDER BY formats_sizes.author ASC, formats_sizes.title ASC",
    "size" => "ORDER BY formats_sizes.size DESC, pgiso_perm.id ASC",
    "time" => "ORDER BY pgiso_perm.time DESC, pgiso_perm.id DESC"
  );

  // retrieve the 25 records for this page by supplying select command with an offset and limit
  $query = "SELECT pgiso_perm.id,pgiso_perm.format,formats_sizes.title,formats_sizes.author," .
           "formats_sizes.size FROM pgiso_perm STRAIGHT_JOIN formats_sizes " .
           "WHERE pgiso_perm.id=formats_sizes.id AND pgiso_perm.format=formats_sizes.format " .
           "AND username='" . $username . "' AND coll_id='" . $collection . "' " .
           $sortperm[$_GET['sortby']] . " LIMIT " . $offset . ",25";

  $result = mysqli_query($query);
  $row = mysqli_fetch_array($result);

  // push each record from MySQL result into $collection_array
  while($row = mysqli_fetch_array($result))
  {
    $collection_array[] = $row;
  }

  // if there are no records to display on this page, do not display anything
  if(!mysqli_num_rows($result))
    exit;

  // convert total ISO size in bytes to megabytes
  $iso_size_mb += $_SESSION['iso_size_bytes'] / 1048576;

  // make a rough estimate of the size added to the ISO by index files
  $iso_size_mb += $etextstotal * 0.00277;

  // in some certain cases, these variables would not be made arrays automatically,
  // so declare them as arrays to avoid scalar value error messages
  $prevpages = array();
  $nextpages = array();
  $before = array();
  $after = array();

  // if current page is greater than 1, calculate previous pages
  if($_GET['page'] > 1)
  {
    // create an array of integers ranging from 1 to current page number
    $prevpages = range(1, $_GET['page']);

    // pop the current page off of the array
    array_pop($prevpages);

    // extract the last 5 elements from $prevpages array to display
    $before = array_slice($prevpages, -5);
  }
  // if current page is less than the total number of pages, calculate next pages
  if($_GET['page'] < $pagestotal)
  {
    // create an array of integers ranging from current page to last page
    $nextpages = range($_GET['page'], $pagestotal);

    // shift the current page off of the array
    array_shift($nextpages);

    // extract the first 5 elements from $prevpages array to display
    $after = array_slice($nextpages, 0, 5);
  }

  // create CSS div for page information
    echo "<br /><table class=\"pageinfo\" cellspacing=\"0\" cellpadding=\"0\" width=\"600\"><tr>" .
         "<td align=\"left\">Page " . $_GET['page'] . ": " .
         "ETexts " . $startrange . " - " . $endrange . "<br /></td>" .
         "<td align=\"right\">";

  // if $before does not contain every previous page, create a link for Page 1 and use
  // "..." to indicate that some pages are being excluded
  if(count($before) < count($prevpages))
    echo "<a href=\"view.php?user=" . $username . "&coll=" . $collection . "&page=1\">1</a> ... ";

  // create links for each of the before pages
  foreach($before as $page)
    echo "<a href=\"view.php?user=" . $username . "&coll=" . $collection .
         "&page=" . $page . "\">" . $page . "</a> ";

  // display current page without making it a link
  echo $_GET['page'] . " ";

  // create links for each of the after pages
  foreach($after as $page)
    echo "<a href=\"view.php?user=" . $username . "&coll=" . $collection .
         "&page=" . $page . "\">" . $page . "</a> ";

  // if $after does not contain every next page, create a link for the last page and use
  // "..." to indicate that some pages are being excluded
  if(count($after) < count($nextpages))
    echo " ... <a href=\"view.php?user=" . $username . "&coll=" . $collection .
         "&page=" . $pagestotal . "\">" . $pagestotal . "</a>";

  // end page CSS div and start a table for the record list
  echo "</td></tr></table>";
  echo "<table cellspacing=\"0\" width=\"600\">";

  // display each etext in the 25-record collection chunk
  foreach($collection_array as $etext)
  {
    // increment counter, so the first record will be $counter == 1
    $counter++;

    // convert etext size in bytes to kilobytes
    $size_kb = $etext['size'] / 1024;

    // use CSS classes to make record background light/dark depending if it's even/odd
    if($counter % 2 == 1)
      $class = "darkrow";
    else
      $class = "lightrow";

    // display record info, including title and author, etext number, format, file size,
    // and a checkbox named after the etext number for deletion purposes
    // title and author are truncated to 50 characters each, file size is formatted with commas
    echo "<tr class=\"" . $class . "\">" .
         "<td colspan=3 align=\"center\" width=600><a href=\"http://www.gutenberg.org/etext/" .
         $etext['id'] . "\">" . substr($etext['title'], 0, 48) . " by " . substr($etext['author'], 0, 48) .
         "</a></td></tr>" .
         "<tr class=\"" . $class . "\"><td align=\"center\" width 200>EText-No: " .
         $etext['id'] . "</td>" .
         "<td align=\"center\" width=200>Format: " .
         strtoupper($etext['format']) . "</td>" .
         "<td align=\"center\" width=200>Size: " .
         number_format($size_kb, 0, ".", ",") . " kb</td></tr>";
  }

  // end table for etext list
  echo "</table>";
  echo "<table class=\"pageinfo\"><tr><td align=\"right\"><div class=\"right\">";
  echo "sort by: <a href=\"view.php?user=" . $username . "&coll=" . $collection .
       "&page=" . $_GET['page'] . "&sortby=id\">etext id</a> |" .
       " <a href=\"view.php?user=" . $username . "&coll=" . $collection .
       "&page=" . $_GET['page'] . "&sortby=title\">title</a> |" .
       " <a href=\"view.php?user=" . $username . "&coll=" . $collection .
       "&page=" . $_GET['page'] . "&sortby=author\">author</a> |" .
       " <a href=\"view.php?user=" . $username . "&coll=" . $collection .
       "&page=" . $_GET['page'] . "&sortby=size\">size</a> |" .
       " <a href=\"view.php?user=" . $username . "&coll=" . $collection .
       "&page=" . $_GET['page'] . "&sortby=time\">time added</a>";
  echo "</td></tr></table><br />";

?>

  </form>

      <font size="2">Please send questions and feedback to help2014 at pglaf.org, also refer to the <a href="faq.html">FAQ</a>.</font><br />
  </center>
  <br />
  </body>
</html>
