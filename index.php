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

    <form action="" method="post">
        <button type="submit" name="displayTableButton" class="button" value="autoren">autoren</button>
        <button type="submit" name="displayTableButton" class="button" value="buecher">buecher</button>
        <button type="submit" name="displayTableButton" class="button" value="lieferanten">lieferanten</button>
        <button type="submit" name="displayTableButton" class="button" value="orte">orte</button>
        <button type="submit" name="displayTableButton" class="button" value="sparten">sparten</button>
        <button type="submit" name="displayTableButton" class="button" value="verlage">verlage</button>

        <button type="submit" name="displayTableButton" class="button" value="autoren_has_buecher">autoren_has_buecher</button>
        <button type="submit" name="displayTableButton" class="button" value="buecher_has_sparten">buecher_has_sparten</button>
        <button type="submit" name="displayTableButton" class="button" value="buecher_has_lieferanten">buecher_has_lieferanten</button>
    </form>

    <form action="" method="post">
        <input name="sql_input" type="text" placeholder="SQL-Statement eingeben..." />
        <button type="submit" class="button btn-confirm" name="executeUserSql"><i class="fa fa-check-circle-o" aria-hidden="true"></i>&nbsp; Ausführen</button>
        <button type="submit" name="addEntry" class="button btn-add" value="addEntry"><i class="fa fa-plus" aria-hidden="true"></i></button>
    </form>
    <?php include 'controller.php'; ?>
</body>

</html>