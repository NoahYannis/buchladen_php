<?php

// Informationen über die aktuelle Sitzung speichern (z.B welche Tabelle aktiv ist).
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

$currentTable = isset($_SESSION['current_table']) ? $_SESSION['current_table'] : null;


// --------Tabelle anzeigen----------------
if (!empty($_POST['displayTableButton'])) {
    $currentTable = $_SESSION['current_table'] = $_POST['displayTableButton'];
    $_SESSION['user_statement_data'] = null;
    $_SESSION['explicit_columns'] = null;
    $_SESSION['last_edited_entry'] = null;
    displayTable($currentTable);
}
// ----------------------------------------



// --------Nutzer-SQL-Statements verarbeiten-----
if(!empty($_POST['sql_input'])) {
    $statement = $_POST['sql_input'];   
    $tableData = executeUserSQL($statement);

    if(isset($tableData)) {
        $_SESSION['user_statement_data'] = $tableData;
        $tableName = findRegexPatternMatch($statement,'/buchladen\.(\w+)/', 1);
        $currentTable = $_SESSION['current_table'] = $tableName;

        $containsExplicitColumns = !preg_match('/\s+\*\s+/', $statement);

        if ($containsExplicitColumns) {
            $explicitColumns = findRegexPatternMatch($statement, '/(?<=\bselect\s)[a-z_,\s]+(?=\s+from)/', 0);
            logStatementToConsole($explicitColumns);
            $_SESSION['explicit_columns'] = splitColumns($explicitColumns);

            $htmlCode = buildHtml($tableData, $tableName, $_SESSION['explicit_columns']);  
            echo $htmlCode;
            return;
        }
        else
        {
            $_SESSION['explicit_columns'] = null;
        }

        $htmlCode = buildHtml($tableData, $tableName);  
        echo $htmlCode;  
        
    }

   // Erklärung des Such-Musters: Hier wird der korrekte Tabellenname gesucht.
   // Vor dem Tabellennamen steht "buchladen", gefolgt von einem beliebigen Zeichen (.)
   // Anschließend kommt der gesuchte Tabellenname, der aus einem oder mehreren alphanumerischen Zeichen besteht,
   // bis zum ersten Leerzeichen.

   // TODO: Erklärung weitere Pattern.

// ----------------------------------------------
}

// --------Tabelle nach Attributen filtern-------
if (isset($_POST['select_sort'])) {
    $filterAttribute = $_POST['selected_column'];
    $unsortedData = $_SESSION['user_statement_data'] ?? $currentTable;
    $sortedData = sortData_SelectionSort($unsortedData, $filterAttribute);
    $htmlCode = buildHtml($sortedData, $currentTable, $_SESSION['explicit_columns']);
    echo $htmlCode;  
  } 
// ----------------------------------------------


// -----------Einträge hinzufügen ---------
if (!empty($_POST['addEntry'])) {
    generateForm($currentTable, 'confirmNewEntry');
}

if (isset($_POST['confirmNewEntry'])) {
    addNewEntry();
    displayTable($currentTable);
}
// ----------------------------------------


// -----------Einträge bearbeiten ---------
if(!empty($_POST['updateButton'])) {
    $_SESSION['updateButton'] = $_POST['updateButton'];
    generateForm($currentTable, 'confirmUpdateEntry');
}

if (isset($_POST['confirmUpdateEntry'])) {
    updateEntry($_SESSION['updateButton']);
    displayTable($currentTable);
}
// ----------------------------------------


// -----------Einträge löschen-------------
if (!empty($_POST['deleteButton'])) {
    $tablePrimaryKey = getPrimaryKeyName($currentTable);
    $entry = $_POST['deleteButton'];
    deleteEntry($currentTable, $tablePrimaryKey, $entry);
    displayTable($currentTable);
}
// ----------------------------------------



function displayTable($table) {
    $tableData = getSelectedTableData($table);
    $htmlCode = buildHtml($tableData, $table);
    echo $htmlCode;
}

function findRegexPatternMatch($statement, $pattern, $captureGroup) {
    /*
    Hier wird mithilfe von Regex (Regular Expressions) ein bestimmtes Muster aus einem SQL-Statement
    herausgefiltert, um  im Anschluss den Tabellenkopf für die korrekte Tabelle mit den korrekten Attributen
    zu generieren.Mehr Infos: https://www.massiveart.com/blog/regex-zeichenfolgen-die-das-entwickler-leben-erleichtern
    */
    
    if (preg_match($pattern, $statement, $matches)) {
        // Wenn ein Ergebnis gefunden wurde, gib das erste Vorkommen zurück
        return $matches[$captureGroup] ?? null;
    }
}


function splitColumns($columnsString) {
    // Entferne Leerzeichen am Anfang und Ende des Strings und trenne die Spalten
    $columnsArray = explode(',', trim($columnsString));
    // Entferne Leerzeichen aus den Spaltennamen
    $columnsArray = array_map('trim', $columnsArray);
    return $columnsArray;
}

function deleteEntry($table, $primaryKey, $entry) {
    global $conn;
    $statement = "DELETE FROM buchladen.$table WHERE $primaryKey = '$entry'";
    logStatementToConsole($statement);

    try 
    {
        $result = $conn->query($statement);
    }
    catch (Exception $e) 
    {
        echo "Der Eintrag konnte nicht gelöscht werden: " . $e->getMessage();
    }
    
    
    if ($result) {
        echo "Der Eintrag wurde erfolgreich gelöscht!";
    } else {
        echo "Fehler beim Löschen des Eintrags: " . $conn->error;
    }
}


function generateForm($table, $postButtonName) {
    $columnNames = getColumnNames($table);
    $entryData = !empty($_POST['updateButton']) ? getEntryData($table, $_POST['updateButton']) : null;
    
    $formHtml = "<form method='post'>";
    foreach ($columnNames as $columnName) 
    {
        if($columnName == getPrimaryKeyName($table))
        continue; // Primärschlüssel darf nicht manuell gesetzt oder bearbeitet werden.
    
        $columnValue = isset($entryData[0][$columnName]) ? $entryData[0][$columnName] : ''; // Wert der aktuellen Spalte aus den abgerufenen Daten
        $formHtml .= "<label for=\"$columnName\">$columnName:</label>";
        $formHtml .= "<input type=\"text\" id=\"$columnName\" value=\"$columnValue\" name=\"$columnName\"><br>";
    }
    
    $formHtml .= "<button type='submit' name=\"$postButtonName\" class='button btn-confirm'><i class=\"fa fa-check-circle-o\"></i> Bestätigen</button>";
    $formHtml .= "</form>";

    echo $formHtml;
}

function generateFilterForm($attributes) {
    if(!isset($_SESSION['filterAttribute']))
        $_SESSION['filterAttribute'] = "";

    $formHtml = "<form method='post'>";
    $formHtml .= "<br/><br>";
    $formHtml .= "<label for='select_column'>Sortieren nach:</label>";
    $formHtml .= "<select name='selected_column' id='select_column'>";
    
    foreach ($attributes as $attribute) {
        $selected = ($attribute == $_SESSION['filterAttribute']) ? "selected" : "";
        $formHtml .= "<option value='$attribute' $selected>$attribute</option>";
    }
    
    $formHtml .= "</select>";
    $formHtml .= "<button type='submit' name='select_sort' class='button btn-confirm'><i class=\"fa fa-check-circle-o\"></i> Bestätigen</button>";
    $formHtml .= "</form>";
  
    echo $formHtml;
}


function getEntryData($table, $entryPrimaryKey) {
    global $conn;

    $SQL = "SELECT * FROM buchladen.$table WHERE " . getPrimaryKeyName($table) . " = '$entryPrimaryKey'";

    try 
    {
        $result = $conn->query($SQL);	

        while($row = $result->fetch_assoc()) {	
            $tableData[] = $row;
        }
      
        return $tableData;
            
    } 
    catch (Exception $e) 
    {
        echo "Fehler beim Abrufen des Eintrags: {$e->getMessage()}";
    }
    
    return $tableData;
}



function getColumnNames($tableName) {
    global $conn;
    $columnNames = [];
    
    $SQL = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$tableName'";

    try 
    {
        $result = $conn->query($SQL);	

        while ($row = $result->fetch_assoc()) {             
           $columnNames[] = $row['COLUMN_NAME'];
         }
            
    } 
    catch (Exception $e) 
    {
        echo "Fehler beim Abrufen der Spaltennamen: {$e->getMessage()}";
    }
    
    return $columnNames;
}


function updateEntry($entryPrimaryKey) {
    global $conn;
    global $currentTable;

    $columnNames = getColumnNames($currentTable);
    $statement = "UPDATE buchladen.$currentTable SET ";

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

    $primaryKey = getPrimaryKeyName($currentTable);
    $statement .= " WHERE $primaryKey = '$entryPrimaryKey'";
    logStatementToConsole($statement);
 
    try 
    {
        $conn->query($statement);
        $_SESSION['last_edited_entry'] = $entryPrimaryKey;
    }
    catch (Exception $e)
    {
        echo "Beim Bearbeiten des Eintrags ist ein Fehler aufgetreten: {$e->getMessage()}";
        return null;
    }
}
 

function addNewEntry() {
    global $conn;
    global $currentTable;

    $columnNames = getColumnNames($currentTable);
    $statement = "INSERT INTO buchladen.$currentTable (";
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
    logStatementToConsole($finalStatement);

    try 
    {
        $conn->query($finalStatement);
    }
    catch (Exception $e)
    {
        echo "Beim Hinzufügen des Eintrags ist ein Fehler aufgetreten: {$e->getMessage()}";
        return null;
    }
}


// Ausgeführte SQL-Statements in der Konsole logen.
function logStatementToConsole($statement) {
    echo '<script>';
    echo 'console.log("' . $statement . '");';
    echo '</script>';
}


// Liefert alle Einträge einer Tabelle
function getSelectedTableData($selectedTable) {
	global $conn;

	$SQL = "SELECT * FROM buchladen.{$selectedTable};";
    logStatementToConsole($SQL);

    try
    {
        $result = $conn->query($SQL);																	
    }
    catch (Exception $e)
    {
        echo "Die ausgewählte Tabelle konnte nicht geladen werden: {$e->getMessage()}";
        return null;
    }
	
	while($row = $result->fetch_assoc()) {	
		$tableData[] = $row;
	}
  
	return $tableData;	
}

function sortData_SelectionSort($table, $filterAttribute) {
    $_SESSION['filterAttribute'] = $filterAttribute;
    $unsortedData = $_SESSION['user_statement_data'] ?? getSelectedTableData($table);

    foreach($unsortedData as $row) {
        logStatementToConsole(implode(" ", $row));
    }
    
    for($i = 0; $i < sizeof($unsortedData); $i++){
		$min = $i;
		$check = false;
		for($j = $i; $j < sizeof($unsortedData); $j++){														
			if($unsortedData[$j][$filterAttribute] < $unsortedData[$min][$filterAttribute]){	
				$check = true;
				$min = $j;
			} 
		}

		if($check){																			
		$dreieck = $unsortedData[$min];
		$unsortedData[$min] = $unsortedData[$i];
		$unsortedData[$i] = $dreieck;
		}
	}
	return $unsortedData;	
}


// Liefert alle Attribute einer Tabelle 
function getTableColumns($table) {
	global $conn;
	$SQL = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table'";

    try 
    {
        $result = $conn->query($SQL);
    }
    catch (Exception $e)
    {
        echo "Beim Laden der Attribute von $table ist ein Fehler aufgetreten: {$e->getMessage()}";
    }

	return $result;						
}



function buildHtml($data, $table, $explicitColumns = null){
    $columnNames = isset($explicitColumns) ? $explicitColumns : getColumnNames($table);
    generateFilterForm($columnNames);      

    $htmlString = '<form method="post">';
    $htmlString .= '<table class="styled-table">';
    $htmlString .= '<thead>';
    $htmlString .= '<tr>'; 

    foreach ($columnNames as $columnName) {
        $htmlString .= "<th>{$columnName}</th>"; 
    }

    $htmlString .= '</tr>'; 
    $htmlString .= '</thead>';

    if($data){
        foreach($data as $row){
            $primaryKey = reset($row);
            $htmlString .= '<tr>';
            foreach($row as $key => $value){
                $htmlString .= '<td>' . $value . '</td>';
            }

            $htmlString .= "<td><button type=\"submit\" name=\"updateButton\" value=\"$primaryKey\" class=\"button btn-edit\"><i class=\"fa fa-edit\"></i></button></td>";
            $htmlString .= "<td><button type=\"submit\" name=\"deleteButton\" value=\"$primaryKey\" class=\"button btn-delete\"><i class=\"fa fa-trash\"></i></button></td>";

            $htmlString .= '</tr>';
        }
    }

    $htmlString .= '</table>'; 
    $htmlString .= '</form>';

    return $htmlString;
}



function executeUserSQL($statement) {
    global $conn;
    logStatementToConsole($statement);

    try 
    {
        $result = $conn->query($statement);
        $tableData = [];
        while ($row = $result->fetch_assoc()) {
            $tableData[] = $row;
        }

        return $tableData;
    }
    catch (Exception $e)
    {
        echo "Beim Ausführen des Statements ist ein Fehler aufgetreten: {$e->getMessage()}";
        return null;
    }
}


function getPrimaryKeyName($table) {
    return ($primaryKey = getTableColumns($table)->fetch_assoc()) ? $primaryKey['COLUMN_NAME'] : null;
}

