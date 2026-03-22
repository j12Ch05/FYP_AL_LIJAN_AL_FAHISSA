?php
    session_start();
    echo $_SESSION["email"]."<br>";
    for($i = 0 ; $i < 6 ; $i++){
        echo $_SESSION["code"][$i];
    }
?>