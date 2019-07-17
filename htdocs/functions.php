<?php

/* ***************************************
   * Coded by Craig Stephenson           *
   * Arctic Region Supercomputing Center *
   * University of Alaska Fairbanks      *
   * July 2005                           *
   *************************************** */

  // update the size of the collection by adding up the file sizes of each etext from the master table
function update_iso_size($link, $collection)
  {
      $iso_size_bytes = 0;
    if(isset($_SESSION['username']))
    {
      $query = "SELECT id,format FROM pgiso_perm WHERE username='" . $_SESSION['username'] . "' " .
               "AND coll_id='" . $collection . "'";
    }
    else
    {
      $query = "SELECT id,format FROM pgiso_temp WHERE session='" . session_id() . "'";
    }

    $ids = mysqli_query($link, $query);
    while($id = mysqli_fetch_array($ids))
    {
      $query = "SELECT size FROM formats_sizes WHERE id='" . $id['id'] . "' AND format='" . $id['format'] . "'";
      $result = mysqli_query($link, $query);
      $row = mysqli_fetch_array($result);
      $iso_size_bytes += $row['size'];
    }

    // update the number of etexts, file size and last change for this collection
    update_collection($collection, $iso_size_bytes);

    return $iso_size_bytes;
  }

  // update the number of etexts, file size and last change date for a collection
  function update_collection($collection, $size)
  {
    if(!isset($_SESSION['username']))
      return;

    $query = "SELECT * FROM pgiso_perm WHERE username='" . $_SESSION['username'] . "' " .
             "AND coll_id='" . $collection . "'";
    $result = mysqli_query($link, $query);
    if ($result != NULL) {
        $etexts = mysqli_num_rows($result);
    }

    $query = "UPDATE collections SET etexts='" . $etexts . "',size='" . $size . "'," .
             "lastchange='" . date("Y-m-d") . "' WHERE username='" . $_SESSION['username'] . "' " .
             "AND coll_id='" . $collection . "'";
    mysqli_query($link, $query);
  }

?>
