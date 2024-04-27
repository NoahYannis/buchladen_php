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

	$htmlString = '<table>'; 
	
	$htmlString .= '<tr>'; 
	while($row = $headers->fetch_assoc()) {
		$htmlString .= "<th>{$row["COLUMN_NAME"]}</th>"; 
	}

	$htmlString .= '</tr>'; 
    
    if($data){
        foreach($data as $row){
            $htmlString .= '<tr>';
            foreach($row as $value){
                $htmlString .= '<td>' . $value . '</td>';
            }
            $htmlString .= '</tr>';
        }
    }

    $htmlString .= '</table>'; 

    return $htmlString;
}


function executeUserSQL($statement) {
	global $conn;

	$result = $conn->query($statement);

	while($row = $result->fetch_assoc()) {	
		$tableData[] = $row;
	}	

	return $tableData;	
}
