<?php
    session_start();
    echo $_SESSION["email"]."<br>";
    echo $_SESSION["password"]."<br>";
    echo $_SESSION["asAdmin"]."<br>";
?>