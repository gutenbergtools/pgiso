<?php

  // TODO: 
  //  Protect against people putting URLs into collection names
  //  Modularize so that mysql_connect is called centrally, not in every
  //   .php file
  //  Add a "help" link that explains file formats, and repeats the
  //   "help" text that appears in the initial screen
  //  Truncate or periodically clean pgiso_temp and pgiso_perm

  /* ***************************************
   * Coded by Craig Stephenson           *
   * Arctic Region Supercomputing Center *
   * University of Alaska Fairbanks      *
   * August 2005                         *
   *************************************** */

require_once("../config.php");
require_once("../functions.php");

// Languages and codes from languages.dat to display in drop-down menu   
$fp = fopen("../languages.dat", "r");
while($line = fgets($fp)) {
  $lang_array = explode("\t", $line);
  $langcodes[$lang_array[0]] = rtrim($lang_array[1]);
}
fclose($fp);

// translate format as represented in MySQL to something slightly 
// prettier to display in drop-downs
//
// N.B.: These *must* match pgrdf_import.pl, else items will be unfindable
$formats = array(
		 "all" => "Any format",

		 "htm" => "Web: html",
		 "txt" => "Plain text: txt",

		 "epub" => "Book reader: epub",
		 "mobi" => "Book reader: mobi (Kindle)",
		 "plucker" => "Book reader: pluckerb",
		 "pdb" => "Book reader: qioo",

		 "lit" => "Book reader (old): lit",
		 "pdb" => "Book reader (old): pdb",

		 "mp3" => "Audio: mp3",
		 "mp4" => "Audio: mp4",
		 "ogg" => "Audio: ogg",

		 "mid" => "Music: midi",

		 "pdf" => "Print layout: pdf",

		 "rst" => "Markup: Restructured Text",
		 "tex" => "Markup: tex",
		 "xml" => "Markup: xml",

		 "rtf" => "Word processor: rtf",

		 "zip-txt" => "Compressed (zip): txt",
		 "zip-htm" => "Compressed (zip): html",

		 );

// start HTTP session so PHP can set and retrieve session variables
session_start();

// set the character set to UTF-8 to support UTF-8 data coming out of MySQL
header('Content-Type: text/html; charset=utf-8');

// connect to MySQL and select gutenberg database
mysql_connect("localhost", $mysql_database, $mysql_password)
or die("Unable to connect to database.");
mysql_select_db($mysql_database);

// prepare MySQL for UTF-8 data (for both insert and select)
mysql_query("SET NAMES 'utf8'");

// if there is no page contained in the URL, set to the first page
// else, prevent abuse by making sure $_GET['page'] is an integer
if(empty($_GET['page']))
  $_GET['page'] = 1;
else
  $_GET['page'] = (int)$_GET['page'];

// if there is no sort order contained in the URL, set to "id"
// else, add slashes to $_GET['sortby'] to prevent abuse
if(empty($_GET['sortby']))
  $_GET['sortby'] = "time";
else
  $_GET['sortby'] = addslashes($_GET['sortby']);

// If the user wants to create the ISO, redirect to that page:
if(isset($_POST['create_iso'])) {
  if(isset($_SESSION['username']) && isset($_SESSION['collection'])) {
    header ("Location: createiso.php?user=" . $_SESSION['username'] .
	    "&coll=" . $_SESSION['collection']);
  } else {
    header ("Location: createiso.php?session=1");
  }
}


// if the user has clicked the "Start Over" button
if(isset($_POST['start_over']))
  {
    // delete all records pertaining to username and collection id
    if(isset($_SESSION['username']) && isset($_SESSION['collection']))
      {
	$query = "DELETE FROM pgiso_perm WHERE username='" . $_SESSION['username'] . "' " .
	  "AND coll_id='" . $_SESSION['collection'] . "'";
      }
    // delete all records pertaining to user's session ID
    else
      {
	$query = "DELETE FROM pgiso_temp WHERE session='" . session_id() . "'";
      }
    mysql_query($query);

    // reset the ISO file size stored in $_SESSION
    $_SESSION['iso_size_bytes'] = 0;

    // update the number of etexts, file size and last change for this collection
    if(isset($_POST['collection'])) { 
      $_SESSION['collection'] = $_POST['collection'];
    } else {
      $_SESSION['collection'] = '';
    }
    update_collection($_SESSION['collection'], $_SESSION['iso_size_bytes']);

    // redirect the user with a URL that contains no page number
    header("Location: " . BASEURL . "/index.php");
  }

// Set default values:
if(isset($_POST['format1'])) { 
  $_SESSION['formats']['format1'] = $_POST['format1'];
} else {
  $_SESSION['formats']['format1'] = '';
}
if(isset($_POST['format2'])) { 
  $_SESSION['formats']['format2'] = $_POST['format2'];
} else {
  $_SESSION['formats']['format2'] = '';
}
if(isset($_POST['format3'])) { 
  $_SESSION['formats']['format3'] = $_POST['format3'];
} else {
  $_SESSION['formats']['format3'] = '';
}
if(isset($_POST['format4'])) { 
  $_SESSION['formats']['format4'] = $_POST['format4'];
} else {
  $_SESSION['formats']['format4'] = '';
}
if(isset($_POST['format5'])) { 
  $_SESSION['formats']['format5'] = $_POST['format5'];
} else {
  $_SESSION['formats']['format5'] = '';
}
if(isset($_POST['format6'])) { 
  $_SESSION['formats']['format6'] = $_POST['format6'];
} else {
  $_SESSION['formats']['format6'] = '';
}
if(isset($_POST['languageselect'])) { 
  $_SESSION['languageselect'] = $_POST['languageselect'];
} else {
  $_SESSION['languageselect'] = array();
}


?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>Project Gutenberg Custom ISO Creator: Beta Test</title>
    <link rel="stylesheet" type="text/css" href="../style.css" />
  </head>
  <body>
  <center>

  <p><font color="red">Note: due to a coding error, please create
  a collection before making an ISO.  Otherwise, empty ISOs are made.
  We are working on fixing this error.  Please report
  any other anomalies via email to  help2014 AT pglaf.org</p>

    <table class="pageinfo" cellspacing="0" cellpadding="0" width="600"><tr>
      <td width="400" align="left">
      <?php
        if(isset($_SESSION['username']) && isset($_SESSION['collection']))
        {
          echo "<a href=\"index.php\">edit iso</a> / ";
          echo "<a href=\"collections.php\">my collections</a> / ";
          echo "<a href=\"published.php\">published collections</a> / ";
          echo "<a href=\"createiso.php?user=" . $_SESSION['username'] .
               "&coll=" . $_SESSION['collection'] . "\">create iso</a> / ";
          echo "<a href=\"login.php?logout=1\">logout</a>";
        }
        else
        {
          echo "<a href=\"index.php\">edit iso</a> / ";
          echo "<a href=\"published.php\">published collections</a> / ";
          echo "<a href=\"createiso.php?session=1\">create iso</a> / ";
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
      if(isset($_SESSION['username']) && isset($_SESSION['collection']))
      {
        $query = "SELECT coll_name FROM collections WHERE username='" . $_SESSION['username'] . "' " .
                 "AND coll_id='" . $_SESSION['collection'] . "'";
        $result = mysql_query($query);
        $row = mysql_fetch_array($result);
        $caption = "\"" . $row['coll_name'] . "\"";
      }
      else
      {
        $caption = "Add Files";
      }
    ?>
    <form action="index.php?page=<?php echo $_GET['page']; ?>&sortby=<?php echo $_GET['sortby']; ?>" method="post">
    <div class="caption"><?php echo $caption; ?></div>
    <div class="content"><br /> 
      <textarea name="etexts" rows="10" cols="70"
        onfocus="if(value == defaultValue) value=''">List etext numbers, etext ranges, or authors in this box, separated by commas. Example: 11,60-75,5000,Mark Twain,132

Then choose your preferred formats in the order you prefer them. If Format #1 is not found for a given etext, it will look for Format #2, and so on...

Select as many languages as you desire.  Only etexts in those languages will be added. Or choose "ANY" to include every language, plus etexts with no specified language.  The drop-down is ordered by the # of eBooks in each language.</textarea><br /><br />
      <table class="selectors" cellpadding="0" cellspacing="0" border="0">
      <tr>
        <?php
          for($j=0; $j < 2; $j++)
          {
            echo "<td width=\"120\" height=\"85\" align=\"center\">";
            for($i=1; $i <= 3; $i++)
            {
              $id = $i + ($j * 3);
              $format_id = "format" . $id;
              echo "<select name=\"" . $format_id . "\">";
              echo "<option value=\"\"";
              if($_SESSION['formats'][$format_id] == NULL)
                echo " selected=\"selected\"";
              echo ">Format #" . $id . "</option>";
              foreach($formats as $key => $value)
              {
                echo "<option value=\"" . $key . "\"";
                if($_SESSION['formats'][$format_id] == $key)
                  echo " selected=\"selected\"";
                echo ">" . $value . "</option>";
              }
              echo "</select><br />";
            }
            echo "</td>";
          }
        ?>
        <td width="110" rowspan="2" align="center">
          <select name="languageselect[]" size="8" multiple="multiple">
          <?php
            echo "<option value=\"any\"";
            if(isset($_SESSION['languageselect']) && in_array("any", $_SESSION['languageselect']))
              echo " selected=\"selected\"";
            echo ">ANY</option>";

            foreach($langcodes as $langkey => $langvalue)
            {
              echo "<option value=\"" . $langkey . "\"";
              if(isset($_SESSION['languageselect']) && in_array($langkey, $_SESSION['languageselect']))
                echo " selected=\"selected\"";
              echo ">" . $langvalue . "</option>";
            }
          ?>
          </select>
        </td>
      </tr>
      <tr>
        <td colspan="2" align="center">
        <input type="checkbox" name="copyright" value="yes" /> exclude copyrighted etexts
        </td>
      </tr>
      </table><br />
      <input type="submit" name="add_etexts" value="Add These ETexts" />
      <input type="submit" name="update_size" value="Update ISO Size" />
      <input type="submit" name="create_iso" value="Create ISO" />
      <input type="submit" name="start_over" value="Start Over" />
      <br /><br />
    </div>

<?php

  // if the user has clicked the "Delete Checked" button
  if(isset($_POST['delete_etexts']))
  {
    // extract etext number keys for all checkboxes that have been checked
    $keys = array_keys($_POST['delete']);

    // subtract file size of each deleted etext from the total ISO file size,
    // process key names into id/format MySQL condition statements and push them onto $deleteitems[]
    foreach($keys as $key)
    {
      $id_format = explode("+", $key);
      $query = "SELECT size FROM formats_sizes WHERE id='" . $id_format[0] . "' AND format='" . $id_format[1] . "'";
      $result = mysql_query($query);
      $row = mysql_fetch_array($result);
      $_SESSION['iso_size_bytes'] -= $row['size'];
      $deleteitems[] = "(id='" . $id_format[0] . "' AND format='" . $id_format[1] . "')";
    }

    // combine list of id/format deletions with MySQL-ready "OR" condition
    $delete_list = implode(" OR ", $deleteitems);

    // delete checked etexts from user's collection
    if(isset($_SESSION['username']) && isset($_SESSION['collection']))
    {
      $query = "DELETE FROM pgiso_perm WHERE username='" . $_SESSION['username'] . "' " .
               "AND coll_id='" . $_SESSION['collection'] . "' AND (" . $delete_list . ")";
      mysql_query($query);
    }
    else
    {
      $query = "DELETE FROM pgiso_temp WHERE session='" . session_id() . "' AND (" . $delete_list . ")";
      mysql_query($query);
    }

    // update the number of etexts, file size and last change for this collection
    update_collection($_SESSION['collection'], $_SESSION['iso_size_bytes']);

    // display etexts remaining in user's collection
    displaylist();
  }  // if user has clicked the "Add These ETexts" button,
  // the etext entry field is not empty, and the entries are valid
  else if(isset($_POST['add_etexts']) && !empty($_POST['etexts']) && !ereg("List etext numbers", $_POST['etexts']))
  {
    $addcount=0;
    // set format array based on user's three choices, ignoring empty choices
    $format_array = array();
    foreach($_SESSION['formats'] as $format)
    {
      if(ereg("^[A-Za-z0-9\-]+$", $format))
        $format_array[] = $format;
    }

    // make sure the user has selected at least one format
    if(empty($format_array))
    {
      echo "<br />You need to select at least one format.";
      displaylist();
      exit;
    }

    // set the user's selected language
    if(isset($_SESSION['languageselect']))
    {
      foreach($_SESSION['languageselect'] as $language)
      {
        if(ereg("^[a-z]+$", $language))
          $language_array[] = $language;
      }
    }

    // set the path of the Project Gutenberg mirror directory
    $mirrorpath = "/data/ftp/mirrors/gutenberg/";

    // explode etext numbers into an array
    $etexts_raw = explode(",", $_POST['etexts']);

    // declare $etexts_array as an array to prevent PHP error messages if nothing gets pushed onto it
    $etexts_array = array();

    // check for ranges (e.g. "1-10") in the etext list, and add numbers within range to $etexts_array
    foreach($etexts_raw as $etext)
    {
      // delete whitespace characters from the entered-etexts string
      $whitespace = array(" ", "\t", "\n", "\r");
      $etext = str_replace($whitespace, "", $etext);

      // skip this operation if any non-numeric/non-dash characters are encountered
      if(ereg("[^0-9\-]", $etext))
        continue;

      // explode potential range limits by range symbol, "-"
      $limits = explode("-", $etext);

      // if there are two elements in $limits, this must be a range
      if(count($limits) == 2)
      {
        // the first element is the range's start
        $startrange = $limits[0];

        // the second element is the range's end
        $endrange = $limits[1];

        // create an array of the integers within this range
        $range_array = range($startrange, $endrange);

        // add this range of integers to the processed etexts array
        $etexts_array = array_merge($etexts_array, $range_array);
      }
      // if there is only one element in $limits, this must be a single etext number
      else if(count($limits) == 1)
      {
        // push this etext number onto the processed etexts array
        $etexts_array[] = $etext;
      }
      // if $limits does not have 1 or 2 elements, report error to user
      else
      {
        echo $etext . " is an invalid EText number or range.<br />";
      }
    }

    // check for author names in the etext list, add all etexts by author to $etexts_array
    foreach($etexts_raw as $author)
    {
      // skip this operation if there are any non-alphabetic characters
      if(ereg("[^A-Za-z\.\ ]", $author) || ereg("^\ *$", $author) || empty($author))
        continue;

      // add slashes to author name to prevent MySQL abuse
      $author = addslashes($author);

      // replace spaces in author's name with MySQL syntax to prepare for query
      $names = str_replace(" ", "%' AND author LIKE '%", $author);

      // retrieve all etext numbers for this author's works and push them onto $etexts_array
      $query = "SELECT DISTINCT(id) FROM formats_sizes WHERE author LIKE '%" . $names . "%'";

      $result = mysql_query($query);
      while($row = mysql_fetch_array($result))
      {
        $etexts_array[] = $row['id'];
      }
    }

    // process the list of etext numbers, matching each etext with a file preference
    foreach($etexts_array as $etext)
    {
      // if this element is empty, skip to next etext number
      if(empty($etext) || ereg("[^0-9]", $etext))
        continue;

      // for each format selected by user in the etext-format preferences
      foreach($format_array as $format)
      {
        // skip this etext if it has already been entered into the user's collection
        
        if($format != "all" && isset($_SESSION['username']) && isset($_SESSION['collection']))
        {
          $query = "SELECT * FROM pgiso_perm WHERE username='" . $_SESSION['username'] . "' " .
                   "AND coll_id='" . $_SESSION['collection'] . "' AND id='" . $etext . "' " .
                   "AND format='" . $format . "'";
        }
        else if($format != "all")
        {
          $query = "SELECT * FROM pgiso_temp WHERE session='" . session_id() . "' " .
                   "AND id='" . $etext . "' AND format='" . $format . "'";
        }
        else if(isset($_SESSION['username']) && isset($_SESSION['collection']))
        {
          $query = "DELETE FROM pgiso_perm WHERE username='" . $_SESSION['username'] . "' " .
                   "AND coll_id='" . $_SESSION['collection'] . "' AND id='" . $etext . "'";
        }
        else
        {
          $query = "DELETE FROM pgiso_temp WHERE session='" . session_id() . "' " .
                   "AND id='" . $etext . "'";
        }

        // perform query in case of single format, or delete pre-existing records in case
        // all formats are being added
        $result = mysql_query($query);

        // suppress error message from mysql_num_rows() if query was a delete command
	$duplicate = false;
        if(@ mysql_num_rows($result))
        {
          $duplicate = true;
          continue;
        }

        // get information for this etext/format
        $query = "SELECT * FROM formats_sizes WHERE id='" . $etext . "'";

        if($format != "all")
          $query .= " AND format='" . $format . "'";

        // add language condition if one was specified in the form
        if(!empty($language_array) && !in_array("any", $language_array))
        {
          $language_cond = implode("' OR language='", $language_array);
          $query .= " AND (language='" . $language_cond . "')";
        }

        // add "exclude copyrighted etexts" condition if it has been checked
        if(isset ($_POST['copyright'])) { // == "yes")
          $query .= " AND copyright='0'";
	}
        $result = mysql_query($query);

        // skip the remaining format preferences if a match is made for this etext/format
        if(mysql_num_rows($result))
          break;
      }

      // if any files matching format preferences have been found, insert a record for this etext
      // in the user's etext collection, unless it has already been entered
      if(mysql_num_rows($result) && !$duplicate)
      {
        // retrieve id, title, author, format, size and file information to be copied into user's collection
        while($row = mysql_fetch_array($result))
        {
          if(isset($_SESSION['username']) && isset($_SESSION['collection']))
          {
            // INSERT INTO mysql_table VALUES (username, coll_id, etext_id, format);
            $query = "INSERT INTO pgiso_perm VALUES ('" . $_SESSION['username'] . "','" . $_SESSION['collection'] .
                     "','" . $row['id'] . "','" . $row['format'] . "','" . date("Y-m-d H:i:s") . "')";
          }
          else
          {
            // INSERT INTO mysql_table VALUES (session_id, etext_id, format, date);
            $query = "INSERT INTO pgiso_temp VALUES ('" . session_id() . "','" . $row['id'] . "','" .
                     $row['format'] . "','" . date("Y-m-d H:i:s") . "')";
          }
          mysql_query($query);

          // add this etext's file size to the total ISO file size
          $_SESSION['iso_size_bytes'] += $row['size'];

          // count the number of etexts added, to tell user
          $addcount++;
        }
      }
      unset($duplicate);
    }

    // update the number of etexts, file size and last change for this collection
    update_collection($_SESSION['collection'], $_SESSION['iso_size_bytes']);

    if($addcount > 0)
      echo "<br />" . $addcount . " etext(s) have been added.";

    // display collection
    displaylist();
  }
  // if user has clicked the "Update Size" button
  else if(isset($_POST['update_size']))
  {
    // re-calculate the collection ISO size from scratch
    $_SESSION['iso_size_bytes'] = update_iso_size($_SESSION['collection']);
    displaylist();
  }
  // if none of the previous user action conditions have been met, simply display their collection
  else
  {
    // display collection
    displaylist();
  }

  // this function displays a list of the user's collection, separated
  // into pages 25 records in size
  function displaylist()
  {
    // "sort by" arrays, translate "sort by" name from URL to its corresponding table/column
    $sortperm = array(
      "id" => "ORDER BY pgiso_perm.id ASC, pgiso_perm.format ASC",
      "title" => "ORDER BY formats_sizes.title ASC, formats_sizes.author ASC",
      "author" => "ORDER BY formats_sizes.author ASC, formats_sizes.title ASC",
      "size" => "ORDER BY formats_sizes.size DESC, pgiso_perm.id ASC",
      "time" => "ORDER BY pgiso_perm.time DESC, pgiso_perm.id DESC"
    );

    $sorttemp = array(
      "id" => "ORDER BY pgiso_temp.id ASC, pgiso_temp.format ASC",
      "title" => "ORDER BY formats_sizes.title ASC, formats_sizes.author ASC",
      "author" => "ORDER BY formats_sizes.author ASC, formats_sizes.title ASC",
      "size" => "ORDER BY formats_sizes.size DESC, pgiso_temp.id ASC",
      "time" => "ORDER BY pgiso_temp.time DESC, pgiso_temp.id DESC"
    );

    // retrieve the total number of etexts for this collection
    if(isset($_SESSION['username']) && isset($_SESSION['collection']))
    {
      $query = "SELECT * FROM pgiso_perm WHERE username='" . $_SESSION['username'] . "' " .
               "AND coll_id='" . $_SESSION['collection'] . "'";
    }
    else
    {
      $query = "SELECT * FROM pgiso_temp WHERE session='" . session_id() . "'";
    }

    $result = mysql_query($query);

    // determine the total number of pages by dividing by 25 and rounding up
    $etextstotal = mysql_num_rows($result);
    $pagestotal = $etextstotal / 25;
    $pagestotal = ceil($pagestotal);

    // calculate the record offset for current page
    $offset = ($_GET['page'] - 1) * 25;

    // set the start and end ranges for this page's records
    $startrange = $offset + 1;
    if ($etextstotal < 25) { 
      $endrange = $etextstotal; 
    } else {
      $endrange = $offset + 25; // This helps the 1st page, but not subsequent
    }

    // retrieve the 25 records for this page by supplying SELECT command with an offset and limit
    if(isset($_SESSION['username']) && isset($_SESSION['collection']))
    {
      $query = "SELECT pgiso_perm.id,pgiso_perm.format,formats_sizes.title,formats_sizes.author," .
               "formats_sizes.size FROM pgiso_perm STRAIGHT_JOIN formats_sizes " .
               "WHERE pgiso_perm.id=formats_sizes.id AND pgiso_perm.format=formats_sizes.format " .
               "AND username='" . $_SESSION['username'] . "' AND coll_id='" . $_SESSION['collection'] . "' " .
               $sortperm[$_GET['sortby']] . " LIMIT " . $offset . ",25";
// echo "<!-- " . $query . " -->\n";
    }
    else
    {
      $query = "SELECT pgiso_temp.id,pgiso_temp.format,formats_sizes.title,formats_sizes.author," .
               "formats_sizes.size FROM pgiso_temp STRAIGHT_JOIN formats_sizes " .
               "WHERE pgiso_temp.id=formats_sizes.id AND pgiso_temp.format=formats_sizes.format " .
               "AND session='" . session_id() . "' " . $sorttemp[$_GET['sortby']] . " " .
               "LIMIT " . $offset . ",25";
// echo "<!-- " . $query . " -->\n";
    }
    $result = mysql_query($query);

    // if there are no records to display on this page, do not display anything
    if(!mysql_num_rows($result))
      return;

    // push each record from MySQL result into $collection array
    while($id = mysql_fetch_array($result))
    {
      $collection[] = $id;
    }

    // convert total ISO size in bytes to megabytes
    $iso_size_mb = 0;
    $iso_size_mb += $_SESSION['iso_size_bytes'] / 1048576;

    // make a (very) rough estimate of the size added to the ISO by index files
    $iso_size_mb += $etextstotal * 0.00277;

    // in certain cases, these variables will not be made arrays automatically,
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

    echo "<br /><table class=\"pageinfo\"><tr><td align=\"right\"><div class=\"right\">";
    echo "sort by: <a href=\"index.php?page=" . $_GET['page'] . "&sortby=id\">etext id</a> |" .
         " <a href=\"index.php?page=" . $_GET['page'] . "&sortby=title\">title</a> |" .
         " <a href=\"index.php?page=" . $_GET['page'] . "&sortby=author\">author</a> |" .
         " <a href=\"index.php?page=" . $_GET['page'] . "&sortby=size\">size</a> |" .
         " <a href=\"index.php?page=" . $_GET['page'] . "&sortby=time\">time added</a>";
    echo "</td></tr></table>\n";


    // create CSS div for page information
    echo "<table class=\"pageinfo\" cellspacing=\"0\" cellpadding=\"0\" width=\"600\"><tr>" .
         "<td align=\"left\">Page " . $_GET['page'] . ": " .
         "ETexts " . $startrange . " - " . $endrange . "<br /></td>" .
         "<td align=\"right\">";

    // if $before does not contain every previous page, create a link for Page 1 and use
    // "..." to indicate that some pages are being excluded
    if(count($before) < count($prevpages))
      echo "<a href=\"index.php?page=1&sortby=" . $_GET['sortby'] . "\">1</a> ... ";

    // create links for each of the before pages
    foreach($before as $page)
      echo "<a href=\"index.php?page=" . $page . "&sortby=" . $_GET['sortby'] . "\">" . $page . "</a> ";

    // display current page without making it a link
    echo $_GET['page'] . " ";

    // create links for each of the after pages
    foreach($after as $page)
      echo "<a href=\"index.php?page=" . $page . "&sortby=" . $_GET['sortby'] . "\">" . $page . "</a> ";

    // if $after does not contain every next page, create a link for the last page and use
    // "..." to indicate that some pages are being excluded
    if(count($after) < count($nextpages))
      echo " ... <a href=\"index.php?page=" . $pagestotal . "&sortby=" . $_GET['sortby'] . "\">" .
           $pagestotal . "</a>";

    // end page CSS div and start a table for the record list
    echo "</td></tr></table>";
    echo "<table cellspacing=\"0\" width=\"600\">";

    // display each etext in the 25-record collection chunk
    $counter=0;
    foreach($collection as $etext)
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
      // and a checkbox named after the etext number/format for deletion purposes
      // title and author are truncated to fit, file size is formatted with commas
      echo "<tr class=\"" . $class . "\"><td rowspan=\"2\" align=\"center\" valign=\"center\" width=\"32\">" .
           "<input type=\"checkbox\" name=\"delete[" . $etext['id'] . "+" . $etext['format'] . "]\"></td>" .
           "<td colspan=\"3\" align=\"center\" width=\"567\"><a href=\"http://www.gutenberg.org/etext/" .
           $etext['id'] . "\">" . substr($etext['title'], 0, 43) . " by " . substr($etext['author'], 0, 43) .
           "</a></td></tr>" .
           "<tr class=\"" . $class . "\"><td align=\"center\" width=\"183\">EText-No: " .
           $etext['id'] . "</td>" .
           "<td align=\"center\" width=\"183\">Format: " .
           strtoupper($etext['format']) . "</td>" .
           "<td align=\"center\" width=\"201\">Size: " .
           number_format($size_kb, 0, ".", ",") . " kb</td></tr>";
    }

    // end table for etext list
    echo "</table>";

    // create CSS div for page information
    echo "<table class=\"pageinfo\" cellspacing=\"0\" cellpadding=\"0\" width=\"600\"><tr>" .
         "<td align=\"left\">Page " . $_GET['page'] . ": " .
         "ETexts " . $startrange . " - " . $endrange . "<br /></td>" .
         "<td align=\"right\">";

    // if $before does not contain every previous page, create a link for Page 1 and use
    // "..." to indicate that some pages are being excluded
    if(count($before) < count($prevpages))
      echo "<a href=\"index.php?page=1&sortby=" . $_GET['sortby'] . "\">1</a> ... ";

    // create links for each of the before pages
    foreach($before as $page)
      echo "<a href=\"index.php?page=" . $page . "&sortby=" . $_GET['sortby'] . "\">" . $page . "</a> ";

    // display current page without making it a link
    echo $_GET['page'] . " ";

    // create links for each of the after pages
    foreach($after as $page)
      echo "<a href=\"index.php?page=" . $page . "&sortby=" . $_GET['sortby'] . "\">" . $page . "</a> ";

    // if $after does not contain every next page, create a link for the last page and use
    // "..." to indicate that some pages are being excluded
    if(count($after) < count($nextpages))
      echo " ... <a href=\"index.php?page=" . $pagestotal . "&sortby=" . $_GET['sortby'] . "\">" .
           $pagestotal . "</a>";

    // end page CSS div and start a table for the record list
    echo "</td></tr></table>";


    echo "<table class=\"pageinfo\"><tr><td align=\"right\"><div class=\"right\">";
    echo "sort by: <a href=\"index.php?page=" . $_GET['page'] . "&sortby=id\">etext id</a> |" .
         " <a href=\"index.php?page=" . $_GET['page'] . "&sortby=title\">title</a> |" .
         " <a href=\"index.php?page=" . $_GET['page'] . "&sortby=author\">author</a> |" .
         " <a href=\"index.php?page=" . $_GET['page'] . "&sortby=size\">size</a> |" .
         " <a href=\"index.php?page=" . $_GET['page'] . "&sortby=time\">time added</a>";
    echo "</td></tr></table><br />";

    // display total ISO info, including number of etexts and total file size
    // the "Delete Checked" button is also in this box, to delete checked etexts
    echo "<table class=\"total\" cellspacing=\"0\" width=\"600\"><tr class=\"lightrow\">" .
         "<td class=\"left\" width=\"34%\" align=\"left\"><input type=\"submit\" name=\"delete_etexts\" " .
         "value=\"Delete Checked\" /></td><td width=\"33%\" align=\"center\"><b>" . $etextstotal .
         " ETexts Total<br />" . "ISO Size: " . number_format($iso_size_mb, 2, ".", ",") .
         " mb</b></td><td class=\"right\" width=\"33%\" align=\"right\"><b>";

    // calculate and display media capacity percentages
    $cdpercentage = 100 * ($_SESSION['iso_size_bytes'] / 734003200);
    $cdpercentage = number_format($cdpercentage, 2, ".", ",");
    $dvdpercentage = 100 * ($_SESSION['iso_size_bytes'] / 4692251770);
    $dvdpercentage = number_format($dvdpercentage, 2, ".", ",");
    echo $cdpercentage . "% CD capacity<br />" .
         $dvdpercentage . "% DVD capacity" . 
         "<font size=\"6\"></b></td></tr></table>";
  }

?>

    </form>
      <font size="2">Please send questions and feedback to help2014 at pglaf.org, also refer to the <a href="faq.html">FAQ</a>.</font><br />
    </center>
    <br />
  </body>
</html>
