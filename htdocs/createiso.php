<?php

/* ***************************************
   * Coded by Craig Stephenson           *
   * Arctic Region Supercomputing Center *
   * University of Alaska Fairbanks      *
   * August 2005                         *
   *************************************** */

  require_once("config.php");
  require_once("functions.php");
  session_start();

  // connect to MySQL and select gutenberg database
global $link; $link=NULL;
global $result; $result=NULL;
$link = mysqli_connect("localhost", $mysqli_user, $mysqli_password, $mysqli_database);
  if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
  }

//mysqli_connect("localhost", $mysqli_database, $mysqli_password)
//    or die("Unable to connect to database.");
//mysqli_select_db($mysqli_database);

  // validate user input to prevent system call abuse
$session = '';
// if (isset($_GET['session'])) { $session = $_GET['session']; }
//if (isset($_GET['session'])) { 
//  $session = $_GET['session']; 
//} else {
//  $session = $_POST['session'];
//}
// TODO: figure out why
$session = session_id();

if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $session = $username;
} else {
  if(ereg("^[A-Za-z0-9_]+$", $_GET['user'])) {
    $username = $_GET['user'];
    $session = $username;
  }
}

if(ereg("^[0-9]+$", $_GET['coll']))
  $collection = $_GET['coll'];

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>Create ISO</title>
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
          echo "<a href=\"index.php\">edit iso</a> / ";
          echo "<a href=\"collections.php\">my collections</a> / ";
          echo "<a href=\"published.php\">published collections</a> / ";
          echo "<a href=\"login.php?logout=1\">logout</a>";
        }
        else
        {
          echo "<a href=\"index.php\">edit iso</a> / ";
          echo "<a href=\"published.php\">published collections</a> / ";
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
      if(isset($username) && isset($collection))
      {
        $query = "SELECT coll_name FROM collections WHERE username='" . $username . "' " .
                 "AND coll_id='" . $collection . "'";
        $result = mysqli_query($query);
        $row = mysqli_fetch_array($result);
        $caption = "\"" . $row['coll_name'] . "\"";
      }
      else
      {
        $caption = "Create ISO";
      }

    ?>
    <form method="post" action="createiso.php?user=<?php echo $username; ?>&coll=<?php echo $collection; ?>&session=<?php echo $session; ?>">  
    <div class="caption"><?php echo $caption; ?></div>
    <div class="content"><br />
    <div style="width: 300px; text-align: left;">
    <input type="radio" name="indexformat" value="single" />Single-page index file<br />
    <input type="radio" name="indexformat" value="multi" />Multi-page index in
    <input type="text" name="indexchunk" size="5" value="100" onfocus="if(value == defaultValue) value=''" />-record chunks<br />
    <input type="radio" name="indexformat" checked value="alpha">Alphabetical multi-page index</div><br />
    <input type="text" name="volid" value="Volume ID [Ex: BOOKS]" size="20" onfocus="if(value == defaultValue) value=''" />
    <input type="text" name="filename" value="File Name [Ex: image.iso]" size="20" onfocus="if(value == defaultValue) value=''" /><br /><br />
    An email address is required to create an ISO file.<br />
    You will receive an email containing a URL when your ISO file is ready to download.<br /><br />
    <input type="text" name="email" value="Email Address" size="30" onfocus="if(value == defaultValue) value=''" /><br /><br />
    <input type="submit" name="make_iso" value="Create ISO" />
    <br /><br />
    </div>
  </form>

<?php

  // if user has clicked the "Create ISO' button and entered a valid email address
  if(isset($_POST['make_iso']) && ereg("[@]", $_POST['email']))
  {
    // validate user input to prevent system call abuse
    if(ereg("^[a-z]+$", $_POST['indexformat']))
      $indexformat = $_POST['indexformat'];
    else
      die("Invalid index format.  Please make sure you selected an index format.");

    if(ereg("^[0-9]+$", $_POST['indexchunk']))
      $indexchunk = $_POST['indexchunk'];
    else if($indexformat == "multi")
      die("Invalid index limit");

    if(ereg("^[A-Za-z0-9_]+$", $_POST['volid']))
      $volid = $_POST['volid'];
    else
      die("Invalid character in volume ID.  Allowed characters are A-Z, a-z, 0-9, and _");

    if(ereg("^[A-Za-z0-9_\.]+$", $_POST['filename']))
      $filename = $_POST['filename'];
    else
      die("Invalid character in file name.  Allowed characters are A-Z, a-z, 0-9, _, and .");

    if(ereg("^[-A-Za-z0-9_\.\+@\.]+$", $_POST['email']))
      $email = $_POST['email'];
    else
      die("Invalid email address");

    // if user has selected multi-file index and record limit is invalid or not set, report error
    if($indexformat == "multi" && (ereg("[^0-9]", $indexchunk) || empty($indexchunk)))
    {
      echo "You forgot to specify the number of records per page for the ISO index file.<br /><br />";
    }
    // if user has not specified an index format, report error
    else if(empty($indexformat))
    {
      echo "You forgot to specify a format for the ISO index file.<br /><br />";
    }
    // if all is well, call make_iso.pl to create the index file(s) and the ISO file
    else if(isset($username) && isset($collection))
    {
      if(isset($_SESSION['username']))
        $directory = "isos/" . $_SESSION['username'];
      else
        $directory = "isos/" . session_id();

      $old_umask = umask(0);
      if(!is_dir($directory))
        mkdir($directory, 0777);
      umask($old_umask);

      $webpath = $directory . "/" . $filename;
      $fullpath = "/data/ftp/pgiso/" . $webpath;

      $command = "nice -n 19 /home/gbnewby/pgiso/make_iso.pl -t pgiso_perm -u " . $username . " -c " .
                 $collection . " -f " . $indexformat . " -l " . $indexchunk . " -v " . $volid . " -p " .
                 $fullpath . " -e " . $email;

      $command = addslashes($command);

      $query = "INSERT INTO isoqueue VALUES ('" . date("Y-m-d H:i:s") . "','" . $command . "')";
      mysqli_query($query);
      echo "<font color=\"green\">Successfully queued for processing!</font>  You will be notified via email when your ISO is complete.<br /><br />";
    }
    else if($session)
    {
      $directory = "isos/" . session_id();
      $old_umask = umask(0);
      if(!is_dir($directory))
        mkdir($directory, 0777);
      umask($old_umask);

      $webpath = $directory . "/" . $filename;
      $fullpath = "/data/ftp/pgiso/" . $webpath;

      $command = "nice -n 19 /home/gbnewby/pgiso/make_iso.pl -t pgiso_temp -s " . session_id() .
                 " -f " . $indexformat . " -l " . $indexchunk . " -v " . $volid . " -p " . $fullpath . 
                 " -e " . $email;
      $command = addslashes($command);

      $query = "INSERT INTO isoqueue VALUES ('" . date("Y-m-d H:i:s") . "','" . $command . "')";
      mysqli_query($query);
      echo "<font color=\"green\">Successfully queued for processing!</font>  You will be notified via email when your ISO is complete.<br /><br />";
    }
    else
    {
      echo "An error occurred while trying to create the ISO file.<br /><br />";
    }
  }
  else if(isset($_POST['make_iso']))
  {
    echo "You did not enter a valid email address.<br /><br />";
  }

?>
    <font size="2">Please send questions and feedback to help2014 at pglaf.org, also refer to the <a href="faq.html">FAQ</a>.</font><br />
  </center>
  <br />
  </body>
</html>
