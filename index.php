<html>
	<head>
		<link rel="stylesheet" href="style.css" type="text/css">
        <link rel="stylesheet"
        href= "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
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
    <label id="sql_statement" for="sql_input">SQL-Statement eingeben:</label><br>
    <input name="sql_input" type="text"/>     
    <button type="submit" class="button" name="executeUserSql">Ausführen</button>
    <button type="submit" name="addEntry" class="button btn-primary" value="addEntry">Eintrag hinzufügen</button>
</form>


	<?php include 'controller.php'; ?>
	</body>
</html>