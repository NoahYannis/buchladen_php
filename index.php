<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Datenbankanwendung Buchladen">
    <title>Buchladen PHP</title>
    <link rel="stylesheet" href="style.css" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>


<body>

    <nav>
        <form style="margin: auto"  action="" method="post">
            <button type="submit" name="displayTableButton" class="nav-button" value="autoren">autoren</button>
            <button type="submit" name="displayTableButton" class="nav-button" value="buecher">buecher</button>
            <button type="submit" name="displayTableButton" class="nav-button" value="lieferanten">lieferanten</button>
            <button type="submit" name="displayTableButton" class="nav-button" value="orte">orte</button>
            <button type="submit" name="displayTableButton" class="nav-button" value="sparten">sparten</button>
            <button type="submit" name="displayTableButton" class="nav-button" value="verlage">verlage</button>
    
            <button type="submit" name="displayTableButton" class="nav-button" value="autoren_has_buecher">autoren_has_buecher</button>
            <button type="submit" name="displayTableButton" class="nav-button" value="buecher_has_sparten">buecher_has_sparten</button>
            <button type="submit" name="displayTableButton" class="nav-button" value="buecher_has_lieferanten">buecher_has_lieferanten</button>
        </form>
    </nav>

    <form class='centered-container' style='margin-top: 40px; margin-bottom: 40px;' action='' method='post'>
        <input name="sql_input" type="text" placeholder="SQL-Statement eingeben..." />
        <button type="submit" class="button btn-confirm" name="executeUserSql" style="width: 37.78px; height: 37.78px;"><i class="fa fa-search"></i></button>
    </form>
    <?php include 'controller.php'; ?>
</body>

</html>