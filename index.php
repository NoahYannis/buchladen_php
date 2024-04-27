<html>
	<head>
		<link rel="stylesheet" href="style.css" type="text/css">
	</head>
	<body>

		
		<form action="" method="post">
			<button type="submit" name="Button" class="button" value="autoren">Autoren</button>
			<button type="submit" name="Button" class="button" value="buecher">Bücher</button>
			<button type="submit" name="Button" class="button" value="lieferanten">Lieferanten</button>
			<button type="submit" name="Button" class="button" value="orte">Orte</button>
			<button type="submit" name="Button" class="button" value="sparten">Sparten</button>
			<button type="submit" name="Button" class="button" value="verlage">Verlage</button>
			
			<button type="submit" name="Button" class="button" value="autoren_has_buecher">Autoren-ID ⇔ Buecher-ID</button>
			<button type="submit" name="Button" class="button" value="buecher_has_sparten">Buch-ID ⇔ Sparten-ID</button>
			<button type="submit" name="Button" class="button" value="buecher_has_lieferanten">Buch-ID ⇔ Lieferanten-ID</button>
		</form>

		<form action="" method="post">
			<label id="sql_statement" for="sql_input">SQL-Statement eingeben:</label><br>
			<input name="sql_input" type="text"/> 	
			<button type="submit" class="button" name="executeUserSql">Ausführen</button>
		</form>

	<?php include 'controller.php'; ?>
	</body>
</html>