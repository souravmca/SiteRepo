<?php

/**
 * API For Ajax Calls from a valid authenticated session.
 *
 * @ todo Return appropriate flags of action taken on each record
 * @ todo in ajax reply acording to which the selected rows can get unseleted
 *
 * The JSON Object will Contain Four Top Level Nodes
 * 1. $DataResp['AjaxToken'] => Token for preventing atacks like CSRF and Sesion Hijack
 * 2. $DataResp['Data']
 * 3. $DataResp['Mail']
 * 4. $DataResp['Msg']
 * 5. $DataResp['RT'] => Response Time of the Script
 *
 * @example ($_POST=array(
 *              'CallAPI'=>'GetData',
 *              'AjaxToken'=>'$$$$')
 *
 * @return json
 *

 */
require_once ( __DIR__ . '/../lib.inc.php');
require_once ( __DIR__ . '/../class.MySQLiDBHelper.php');
require_once ( __DIR__ . '/../php-mailer/GMail.lib.php');
if (!isset($_SESSION))
  session_start();
//@ todo Enable AjaxToken currently disabled
$CSRF = (WebLib::GetVal($_POST, 'AjaxToken') === WebLib::GetVal($_SESSION, 'Token'));
if ((WebLib::CheckAuth() === 'Valid') && $CSRF) {
  $_SESSION['LifeTime'] = time();
  $_SESSION['RT'] = microtime(TRUE);
  $_SESSION['CheckAuth'] = 'Valid';
  $DataResp['Data'] = array();
  $DataResp['Mail'] = array();
  $DataResp['Msg'] = '';
  switch (WebLib::GetVal($_POST, 'CallAPI')) {

    case 'GetOffice':
      $Query = 'Select * FROM `' . MySQL_Pre . 'PP_Offices`'
              . ' Where `OfficeSL`=?';
      doQuery($DataResp, $Query, WebLib::GetVal($_POST, 'Params', FALSE, FALSE));
      break;
  }
  $_SESSION['Token'] = md5($_SERVER['REMOTE_ADDR'] . session_id() . $_SESSION['ET']);
  $_SESSION['LifeTime'] = time();
  $DataResp['AjaxToken'] = $_SESSION['Token'];
  $DataResp['RT'] = '<b>Response Time:</b> '
          . round(microtime(TRUE) - WebLib::GetVal($_SESSION, 'RT'), 6) . ' Sec';
  //PHP 5.4+ is required for JSON_PRETTY_PRINT
  //@todo Remove PRETTY_PRINT for Production
  if (strnatcmp(phpversion(), '5.4') >= 0) {
    $AjaxResp = json_encode($DataResp, JSON_PRETTY_PRINT);
  } else {
    $AjaxResp = json_encode($DataResp); //WebLib::prettyPrint(json_encode($DataResp));
  }
  unset($DataResp);

  header('Content-Type: application/json');
  header('Content-Length: ' . strlen($AjaxResp));
  echo $AjaxResp;
  exit();
}
header("HTTP/1.1 404 Not Found");
exit();

/**
 * Perfroms Select Query to the database
 *
 * @param ref     $DataResp
 * @param string  $Query
 * @param array   $Params
 * @example GetData(&$DataResp, "Select a,b,c from Table Where c=? Order By b LIMIT ?,?", array('1',30,10))
 */
function doQuery(&$DataResp, $Query, $Params = NULL) {
  $Data = new MySQLiDBHelper(HOST_Name, MySQL_User, MySQL_Pass, MySQL_DB);
  $Result = $Data->rawQuery($Query, $Params);
  $DataResp['Data'] = $Result;
  $DataResp['Msg'] = 'Total Rows: ' . count($Result);
  unset($Result);
  unset($Data);
}

/**
 * Performs Insert, Update & Delete Query
 *
 * @param ref $DataResp
 * @param string $tableName
 * @param array $saveData
 */
function SaveData(&$DataResp, $tableName, $saveData, $RowIndex = NULL) {
  $Data = new MySQLiDBHelper(HOST_Name, MySQL_User, MySQL_Pass, MySQL_DB);
  $Saved = FALSE;
  $Result['Index'] = $saveData['Index'];
  $Action = NULL;
  unset($saveData['Index']);
  if (is_array($saveData)) {
    if ($saveData['RowID'] === "") {
      $Saved = $Data->insert($tableName, $saveData);
      if ($Saved > 0) {
        $Result['Saved'] = TRUE;
        $Result['RowID'] = $Data->getInsertId();
      } else {
        $Result['Saved'] = FALSE;
        $Result['RowID'] = NULL;
        $Action = 'not Added[' . $saveData['SlNo'] . ']';
      }
    } else {
      if ($saveData['SlNo'] !== "") {
        $Data->where('RowID', $saveData['RowID']);
        $Saved = $Data->update($tableName, $saveData);
        if ($Saved > 0) {
          $Result['Saved'] = TRUE;
          $Result['RowID'] = $saveData['RowID'];
        } else {
          $Result['Saved'] = FALSE;
          $Result['RowID'] = $saveData['RowID'];
          $Action = 'not Updated[' . $saveData['SlNo'] . ']';
        }
      } else {
        $Data->where('RowID', $saveData['RowID']);
        $Saved = $Data->delete($tableName);
        if ($Saved > 0) {
          $Result['Saved'] = TRUE;
          $Result['RowID'] = '';
        } else {
          $Result['Saved'] = FALSE;
          $Result['RowID'] = $saveData['RowID'];
          $Action = 'not Deleted[' . $saveData['SlNo'] . ']';
        }
      }
    }
    unset($saveData['RowID']);
    //$saveData['SlNo'] = '*' . $saveData['SlNo'];
    $Result['Data'] = $saveData;
    $DataResp['Data'][$RowIndex] = $Result;
    unset($Result);
    unset($saveData);
    if ($Action !== NULL)
      $DataResp['Msg'] .= ' | ' . $Action;
  } else {
    $DataResp['Msg'] .= ' But nothing to Save!';
  }
  unset($Data);
}

?>
