<?php

session_start(); 

// Server Settings
$servername = "localhost";																		
$username = "root";
$password = "";															

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}


// Check, if a Button was pressed. If true, build a table from database data.
if(!empty($_POST['Button'])){
	$table = $_POST['Button'];
	$_SESSION['current_table'] = $table;
	$tableData = getSelectedTableData($table);											
	$htmlCode = buildHtml($tableData, $table);									
	echo $htmlCode;																	
}

if(!empty($_POST['sql_input'])) {
    $statement = $_POST['sql_input'];    

    /*
    Hier wird mithilfe von Regex (Regular Expressions) der Tabellenname aus dem SQL-Statement
	herausgefiltert, um den zugehörigen Tabellenkopf zu generieren.
    Das Muster beginnt mit "buchladen" (dem Datenbanknamen), gefolgt von einem beliebigen Zeichen (.) und
    einem oder mehreren alphanumerischen Zeichen (dem gesuchten Tabellennamen).
	Mehr Infos: https://www.massiveart.com/blog/regex-zeichenfolgen-die-das-entwickler-leben-erleichtern
    */

    $pattern = '/buchladen\.(\w+)/';
    preg_match($pattern, $statement, $matches);
    $tableName = isset($matches[1]) ? $matches[1] : null;	
    $tableData = executeUserSQL($statement);
    $htmlCode = buildHtml($tableData, $tableName);                                                
    echo $htmlCode;    
}

if (!empty($_POST['addEntry'])) {
    generateNewEntryForm();
}

if (!empty($_POST['deleteButton'])) {
    $tablePrimaryKey = getPrimaryKeyName($_SESSION['current_table']);
    $entry = $_POST['deleteButton'];
    deleteEntry($_SESSION['current_table'], $tablePrimaryKey, $entry);
}

function deleteEntry($table, $tablePrimaryKey, $entry) {
    global $conn;
    $statement = "DELETE FROM buchladen.$table WHERE $tablePrimaryKey = '$entry'";
    $result = $conn->query($statement);

    if ($result) {
        echo "Der Eintrag wurde erfolgreich gelöscht!";
    } else {
        echo "Fehler beim Löschen des Eintrags: " . $conn->error;
    }
}



if(!empty($_POST['updateButton'])) {
    $_SESSION['updateButton'] = $_POST['updateButton'];
    $tableName = $_SESSION['current_table'];
    $columnNames = [];
    $columnValues = [];

    $tableColumns = getTableColumns($tableName);

    while ($row = $tableColumns->fetch_assoc()) {
        $columnNames[] = $row['COLUMN_NAME'];
    }

    $formHtml = "<form method='post'>";
    foreach ($columnNames as $columnName) {
        $formHtml .= "<label for=\"$columnName\">$columnName:</label>";
        $formHtml .= "<input type=\"text\" id=\"$columnName\" name=\"$columnName\"><br>";
    }

    $formHtml .= "<button type='submit' name='confirmUpdateEntry'>Bestätigen</button>";
    $formHtml .= "</form>";

    echo $formHtml;
}

if (isset($_POST['confirmUpdateEntry'])) {
    updateEntry();
}

if (isset($_POST['confirmNewEntry'])) {
    addNewEntry();
}

function updateEntry() {
    global $conn;
    $tableName = $_SESSION['current_table'];
    $columnNames = [];
    $columnValues = [];

    $tableColumns = getTableColumns($tableName);

    while ($row = $tableColumns->fetch_assoc()) {
        $columnNames[] = $row['COLUMN_NAME'];
    }

    // Erzeuge das UPDATE-Statement mit SET-Klausel
    $statement = "UPDATE buchladen.$tableName SET ";

    foreach ($columnNames as $columnName) {
        // Überprüfe, ob der Spaltenname kein reservierter Name wie 'updateButton' ist
        if ($columnName !== 'updateButton') {
            // Überprüfe, ob ein Wert für diesen Spaltennamen im POST-Array vorhanden ist
            if (isset($_POST[$columnName])) {
                // Hole den Wert aus dem POST-Array
                $columnValue = $_POST[$columnName];
                // Füge den Spaltennamen und den Wert der SET-Klausel hinzu
                $statement .= "$columnName = '$columnValue', ";
            }
        }
    }

    // Entferne das letzte Komma und Leerzeichen von der SET-Klausel
    $statement = rtrim($statement, ", ");

    // Füge die WHERE-Klausel hinzu, um die zu aktualisierende Zeile zu identifizieren
    $primaryKey = $columnNames[0];
    $primaryKey = getPrimaryKeyName($_SESSION['current_table']);
    $statement .= " WHERE $primaryKey = '{$_SESSION['updateButton']}'"; // Stellen Sie sicher, dass das schließende Anführungszeichen hinzugefügt wurde
    echo "Statement:" . $statement;
    // Ausführen des SQL-Statements
    $conn->query($statement);
}
 

function addNewEntry() {
    global $conn;
    $tableName = $_SESSION['current_table'];
    $columnNames = [];
    $columnValues = [];

    $tableColumns = getTableColumns($tableName);

    while ($row = $tableColumns->fetch_assoc()) {
        $columnNames[] = $row['COLUMN_NAME'];
    }

    $statement = "INSERT INTO buchladen.$tableName (";
    $values = "VALUES (";
    foreach ($columnNames as $columnName) {
        // Überprüfe, ob der Wert für dieses Feld im POST-Array vorhanden ist
        if (isset($_POST[$columnName])) {
            $columnValue = $_POST[$columnName];
            // Füge den Spaltennamen und den Wert dem SQL-Statement hinzu
            $statement .= "$columnName, ";
            $values .= "'$columnValue', ";
        }
    }
    // Entferne das letzte Komma und Leerzeichen von den Strings
    $statement = rtrim($statement, ", ") . ") ";
    $values = rtrim($values, ", ") . ")";
    // Füge die Spaltennamen und Werte zum endgültigen SQL-Statement hinzu
    $finalStatement = $statement . $values;
    
    // Ausführen des SQL-Statements (falls benötigt)
    $conn->query($finalStatement);
    
    echo $finalStatement; // Zum Testen, Ausgabe des SQL-Statements
}

function generateNewEntryForm() {
    $tableColumns = getTableColumns($_SESSION['current_table']);
    $columnNames = [];
    
    while ($row = $tableColumns->fetch_assoc()) {
        $columnNames[] = $row['COLUMN_NAME'];
    }
    
    $formHtml = "<form method='post'>";
    foreach ($columnNames as $columnName) {
        $formHtml .= "<label for=\"$columnName\">$columnName:</label>";
        $formHtml .= "<input type=\"text\" id=\"$columnName\" name=\"$columnName\"><br>";
    }

    $formHtml .= "<button type='submit' name='confirmNewEntry'>Bestätigen</button>";
    $formHtml .= "</form>";

    echo $formHtml;
}



function getSelectedTableData($selectedTable) {
	global $conn;

	$SQL = "SELECT * FROM buchladen.{$selectedTable};";

	$result = $conn->query($SQL);																	
	
	while($row = $result->fetch_assoc()) {	
		$tableData[] = $row;
	}
  
	return $tableData;	
}



// Get all column names of a table
function getTableColumns($table) {
	global $conn;
	$SQL = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table'";
	$result = $conn->query($SQL);	
	return $result;						
}



// Function to build the html code from the database data
function buildHtml($data, $table){

    $headers = getTableColumns($table);

    $htmlString = '<form method="post">'; // Formular hinzugefügt
    $htmlString .= '<table>'; 
    $htmlString .= '<tr>'; 

    while($row = $headers->fetch_assoc()) {
        $htmlString .= "<th>{$row["COLUMN_NAME"]}</th>"; 
    }
    
    $htmlString .= '</tr>'; 
    
    if($data){
        foreach($data as $row){
            $primaryKey = reset($row);
            $htmlString .= '<tr>';
            foreach($row as $key => $value){
                $htmlString .= '<td>' . $value . '</td>';
            }

            // Den Namen des "Bearbeiten"-Buttons auf den Wert des Primärschlüssels setzen
            $htmlString .= "<td><button type=\"submit\" name=\"updateButton\" value=\"$primaryKey\" class=\"Button\">Bearbeiten</button></td>";
            $htmlString .= "<td><button type=\"submit\" name=\"deleteButton\" value=\"$primaryKey\" class=\"Button\">Löschen</button></td>";
            $htmlString .= '</tr>';
        }
    }
    
    $htmlString .= '</table>'; 
    $htmlString .= '</form>'; // Formular hinzugefügt

    return $htmlString;
}


function editEntry() {
    global $conn;
}


function executeUserSQL($statement) {
	global $conn;

	$result = $conn->query($statement);

	while($row = $result->fetch_assoc()) {	
		$tableData[] = $row;
	}	

	return $tableData;	
}


function getPrimaryKeyName($table) {
    return ($primaryKey = getTableColumns($table)->fetch_assoc()) ? $primaryKey['COLUMN_NAME'] : null;
}

