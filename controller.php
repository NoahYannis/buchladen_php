<?php

// Informationen über die aktuelle Sitzung speichern (aktive Tabelle, Filterattribut...)
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

$currentTable = $_SESSION['current_table'] ?? null;

// --------Tabelle anzeigen----------------
if (!empty($_POST['displayTableButton'])) {
    $currentTable = $_SESSION['current_table'] = $_POST['displayTableButton'];
    $_SESSION['user_statement_data'] = null; // Daten des Nutzer-Statements
    $_SESSION['explicit_columns'] = null; // Gefilterte Spalten des Nutzer-Statements
    $_SESSION['last_edited_entry'] = null;
    displayTable($currentTable);
}
// ----------------------------------------



// --------Nutzer-SQL-Statements verarbeiten-----
if(!empty($_POST['sql_input'])) {
    $statement = $_POST['sql_input'];   
    $tableData = executeUserSelectStatement($statement);

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

   /* Bedeutung der beiden Suchmuster: 

   // 1: '/buchladen\.(\w+)/' => Hier wird der Tabellen-Name extrahiert.
   // Vor dem Tabellennamen steht "buchladen", gefolgt von einem beliebigen Zeichen (.)
   // Anschließend kommt der gesuchte Tabellenname, der bis zum ersten Leerzeichen geht.
 
   // 2: '/(?<=\bselect\s)[a-z_,\s]+(?=\s+from)/' => Hier werden die ggf. explizit erwähnten
   // Spalten aus dem Nutzer-Statement extrahiert. (z.B SELECT spalte1, spalte2 FROM tabelle)
   */ 

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
    herausgefiltert, um im Anschluss den Tabellenkopf für die korrekte Tabelle mit den korrekten Attributen
    zu generieren. Mehr Infos: https://www.massiveart.com/blog/regex-zeichenfolgen-die-das-entwickler-leben-erleichtern
    */
    
    if (preg_match($pattern, $statement, $matches)) {
        return $matches[$captureGroup] ?? null;
    }
}


/**
 * Teilt den String von Spaltennamen aus dem SQL-Statement
 * in ein Array von einzelnen Spaltennamen auf,
 * woraus der Tabellen-Kopf generiert wird.
 *
 * @param string $columnsString Der String von Spaltennamen, der aufgeteilt werden soll.
 * @return array Das Array von einzelnen Spaltennamen.
 */
function splitColumns($columnsString) {
    $columnsArray = explode(',', trim($columnsString));
    $columnsArray = array_map('trim', $columnsArray);
    return $columnsArray;
}



function deleteEntry($table, $primaryKey, $entry) {
    global $conn;
    $columns = getColumnNames($table);
    $data = getEntryData($table, $entry);

    if (empty($data)) {
        echo "<div class='error-message'>Keine Daten gefunden für den Eintrag mit dem Primärschlüssel $entry.</div>";
        return;
    }

    $values = [];
    foreach ($columns as $column) {
        $columnValue = $data[0][$column]; // Annahme: Es wird nur ein Datensatz zurückgegeben
        $values[] = "$column = '$columnValue'";
    }
    
    $whereClause = implode(' AND ', $values);
    $statement = "DELETE FROM buchladen.$table WHERE $whereClause";
    logStatementToConsole($statement);

    try 
    {
        $conn->query($statement);
    }
    catch (Exception $e) 
    {
        echo "<div class='error-message'>Fehler beim Löschen des Eintrags: {$e->getMessage()}</div>";
    }
}


/**
 * Generiert das Formular für das Hinzufügen oder Bearbeiten von Einträgen in einer Tabelle.
 *
 * @param string $table Der Name der Tabelle.
 * @param string $postButtonName Der Name des Submit-Buttons.
 * @return void
 */
function generateForm($table, $postButtonName) {
    $columnNames = getColumnNames($table);
    $entryData = !empty($_POST['updateButton']) ? getEntryData($table, $_POST['updateButton']) : null;
    
    $formHtml = "<form class='custom-form' method='post'>";
    foreach ($columnNames as $columnName) 
    {
        if($columnName == getPrimaryKeyName($table) && !preg_match('/(?<=_)has(?=_)/', $table)) {
            continue; // Primärschlüssel darf nicht manuell gesetzt oder bearbeitet werden außer bei N-zu-M-Tabellen.
        }
    
        $columnValue = $entryData[0][$columnName] ?? ''; // Wert der aktuellen Spalte aus den abgerufenen Daten
        $formHtml .= "<label for=\"$columnName\">$columnName:</label>";
        $formHtml .= "<input type=\"text\" id=\"$columnName\" value=\"$columnValue\" name=\"$columnName\"><br>";
    }
    
    $formHtml .= "<button type='submit' name=\"$postButtonName\" class='button btn-confirm' style='margin-left: 0;'><i class=\"fa fa-check-circle\"></i>&nbsp;Bestätigen</button>";
    $formHtml .= "</form>";

    echo $formHtml;
}

// Generiert das Filter-Element für die Sortierung der Tabelle
function generateFilterForm($attributes) {
    if(!isset($_SESSION['filterAttribute']))
        $_SESSION['filterAttribute'] = "";

    $formHtml = "<form method='post'>";
    $formHtml .= "<div style='text-align: center;' class='centered-container'>";
    $formHtml .= "<label for='select_column'>Filtern nach:</label>";
    $formHtml .= "<select name='selected_column' id='select_column'>";
    
    foreach ($attributes as $attribute) {
        $selected = ($attribute == $_SESSION['filterAttribute']) ? "selected" : "";
        $formHtml .= "<option value='$attribute' $selected>$attribute</option>";
    }
    
    $formHtml .= "</select>";
    $formHtml .= "<button type='submit' name='select_sort' class='button btn-confirm' style='margin-right: 14px;  width: 35px; height: 35px;'><i class=\"fa fa-check\"></i></button>";
    $formHtml .= "<button type='submit' name='addEntry' class='button btn-add' value='addEntry'><i class='fa fa-plus' aria-hidden='true'>&nbsp;Eintrag hinzufügen</i></button>";
    $formHtml .= "</div>"; 
    $formHtml .= "</form>";

    echo $formHtml;
}


// Liefert die Daten eines Eintrags aus einer Tabelle
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
        echo "<div class='error-message'>Fehler beim Abrufen des Eintrags: {$e->getMessage()}</div>";
    }
    
    return $tableData;
}


// Liefert die Spaltennamen einer Tabelle
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
        echo "<div class='error-message'>Fehler beim Abrufen des Spaltennamens: {$e->getMessage()}</div>";
    }
    
    return $columnNames;
}


function updateEntry($entryPrimaryKey) {
    global $conn;
    global $currentTable;

    $columnNames = getColumnNames($currentTable);
    $statement = "UPDATE buchladen.$currentTable SET ";

    foreach ($columnNames as $columnName) {
        if ($columnName !== 'updateButton') {
            if (isset($_POST[$columnName])) {
                $columnValue = $_POST[$columnName];
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
        echo "<div class='error-message'>Fehler beim Bearbeiten des Eintrags: {$e->getMessage()}</div>";
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
        if (isset($_POST[$columnName])) {
            $columnValue = $_POST[$columnName];
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
        echo "<div class='error-message'>Fehler beim Hinzufügen des Eintrags: {$e->getMessage()}</div>";
        return null;
    }
}


// Ausgeführte SQL-Statements in der Konsole loggen.
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
        echo "<div class='error-message'>Fehler beim Laden der Tabelle: {$e->getMessage()}</div>";
        return null;
    }
	
	while($row = $result->fetch_assoc()) {	
		$tableData[] = $row;
	}
  
	return $tableData ?? null;	
}


// Sortiert die Daten einer Tabelle nach einem bestimmten Attribut
function sortData_SelectionSort($table, $filterAttribute) {
    $_SESSION['filterAttribute'] = $filterAttribute;

    // SQL-Statement-Daten sortieren falls vorhanden, ansonsten die gesamte Tabelle.
    $unsortedData = $_SESSION['user_statement_data'] ?? getSelectedTableData($table);

    if(!$unsortedData) {
        return null;
    }

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
        echo "<div class='error-message'>Fehler beim Laden der Attribute von $table: {$e->getMessage()}</div>";
    }

	return $result;						
}


/**
 * Erstellt die HTML-Tabelle zum Anzeigen von Daten.
 *
 * @param array $data Die Daten, die in der Tabelle angezeigt werden sollen.
 * @param string $table Der Name der Tabelle.
 * @param array|null $explicitColumns Im SQL-Statement explizit angegebene Spalten falls vorhanden.
 * @return string Der HTML-String, der die Tabelle repräsentiert.
 */
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

    $htmlString .= "<th style='width: 40px'></th>"; 
    $htmlString .= "<th style='width: 40px'></th>"; 

    $htmlString .= '</tr>'; 
    $htmlString .= '</thead>';

    if($data){
        foreach($data as $row){
            $primaryKey = reset($row);
            $htmlString .= '<tr>';
            foreach($row as $key => $value){
                $htmlString .= '<td>' . $value . '</td>';
            }

            $htmlString .= "<td><button type=\"submit\" name=\"updateButton\" value=\"$primaryKey\" class=\"button btn-edit\"><i class=\"fa fa-pencil fa-fw\"></i></button></td>";
            $htmlString .= "<td><button type=\"submit\" name=\"deleteButton\" value=\"$primaryKey\" class=\"button btn-delete\"><i class=\"fa fa-trash\"></i></button></td>";

            $htmlString .= '</tr>';
        }
    }

    $htmlString .= '</table>'; 
    $htmlString .= '</form>';

    return $htmlString;
}


function executeUserSelectStatement($statement) {
    global $conn;
    logStatementToConsole($statement);

    // Überprüfe, ob das Benutzer-Statement UPDATE oder DELETE enthält => es dürfen hier keine Einträge gelöscht oder verändert werden
    if (strpos(strtoupper($statement), 'UPDATE') !== false || strpos(strtoupper($statement), 'DELETE') !== false) {
        echo "<div class='error-message'>Das Statement enthält UPDATE oder DELETE und kann daher nicht ausgeführt werden.</div>";
        return null;
    }

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
        echo "<div class='error-message'>Beim Ausführen des Statements ist ein Fehler aufgetreten: {$e->getMessage()}</div>";
        return null;
    }
}

// Liefert den Primärschlüssel einer Tabelle
function getPrimaryKeyName($table) {
    return ($primaryKey = getTableColumns($table)->fetch_assoc()) ? $primaryKey['COLUMN_NAME'] : null;
}

