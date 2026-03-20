<?php
    //establishing connection with the database on phpMyAdmin when we return to campus change the value to connect to server there 
    $db_server = "localhost:3307";
    $db_username = "root";
    $db_password = "";
    $db_name = "fyp_lijan_al_fahissa";

    $conn = mysqli_connect($db_server,$db_username,$db_password,$db_name);
    if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "Connected successfully using Procedural style";

// Close connection
mysqli_close($conn);
?>