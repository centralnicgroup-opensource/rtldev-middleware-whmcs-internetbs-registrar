<?php
if (file_exists(__DIR__ . '/../../../dbconnect.php')) {
    require __DIR__ . '/../../../dbconnect.php';
} else {
    require __DIR__ . '/../../../init.php';
}
require ROOTDIR . '/includes/functions.php';
require ROOTDIR . '/includes/registrarfunctions.php';

$cronreport = 'Internet.bs Domain Sync Report<br>
---------------------------------------------------<br>
';
/**
 * gets expiration date from domain list command
 * @param string $data - command TEXT response
 * @return array - associative array having as key the domain name and as value the expiration date
 */
function parseResult($data)
{
    $result = array ();
    $data = strtolower($data);
    $arr = explode("\n", $data);
    $totalDomains = 0;
    $assocArr = array ();
    foreach ($arr as $str) {
        list ( $varName, $value ) = explode("=", $str);
        $varName = trim($varName);
        $value = trim($value);
        if ($varName == "domaincount") {
            $totalDomains = intval($value);
        }
        $assocArr [$varName] = $value;
    }
    if ($assocArr ["status"] != "success") {
        return false;
    }

    for ($i = 0; $i < $totalDomains; $i++) {
        list ( $y, $m, $d ) = explode("/", $assocArr ["domain_" . $i . "_expiration"]);
        $status = strtolower($assocArr ["domain_" . $i . "_status"]);
        if (!is_numeric($y) || !is_numeric($m) || !is_numeric($d)) {
            $ddat = array ("expiry" => null, "status" => $status );
        } else {
            $ddat = array ("expiry" => mktime(0, 0, 0, $m, $d, $y), "status" => $status );
        }
        $result [strtolower($assocArr ["domain_" . $i . "_name"])] = $ddat;
        if (isset($assocArr ["domain_" . $i . "_punycode"])) {
            $result [strtolower($assocArr ["domain_" . $i . "_punycode"])] = $ddat;
        }
    }
    return $result;
}

$params = getregistrarconfigoptions('ibs');

$postfields = array ();
$postfields ['ApiKey'] = $params ['Username'];
$postfields ['Password'] = $params ['Password'];
$postfields ['ResponseFormat'] = 'TEXT';
$postfields ['returnfields'] = 'paiduntil';
$testMode = trim(strtolower($params ['TestMode'])) === "on";
$SyncNextDueDate = trim(strtolower($params ["SyncNextDueDate"])) === "on";

if ($testMode) {
    $url = 'https://testapi.internet.bs/domain/list';
} else {
    $url = 'https://api.internet.bs/domain/list';
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);

curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_USERAGENT, "WHMCS Internet.bs Corp. Expiry Sync Robot");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);

$data = curl_exec($ch);
$curl_err = false;
if (curl_error($ch)) {
    $curl_err = 'CURL Error: ' . curl_errno($ch) . ' - ' . curl_error($ch);
    exit('CURL Error: ' . curl_errno($ch) . ' - ' . curl_error($ch));
}
curl_close($ch);
if ($curl_err) {
    $cronreport .= "Error connecting to API: $curl_err";
} else {
    $result = parseResult($data);
    if (! $result) {
        $cronreport .= "Error connecting to API:<br>" . nl2br($data) . "<br>";
    } else {
        $queryresult = select_query("tbldomains", "*", "registrar='ibs' AND (status='Pending Transfer' OR status='Active')");
        while ($data = mysql_fetch_array($queryresult)) {
            $domainname = trim(strtolower($data ['domain']));
            if (isset($result [$domainname])) {
                if (!is_null($result [$domainname] ["expiry"])) {
                    $expirydate = date("Y-m-d", $result [$domainname] ["expiry"]);
                } else {
                    $expirydate = false;
                }
                $status = $result [$domainname] ["status"];
                if ($status == 'ok') {
                    update_query("tbldomains", array ("status" => "Active" ), array ('id' => $data['id'] ));
                    if ($data['status'] == 'Pending Transfer') {
                        logactivity($domainname . ' set to active by internet.bs sync script');
                        $command = 'SendEmail';
                        $postData = array(
                            'messagename' => 'Domain Transfer Completed',
                            'id' => $data['id'],
                        );
                        $results = localAPI($command, $postData, $adminUsername);
                        if ($results['result'] == 'success') {
                            $cronreport .= $domainname . ' - Transfer confirmation email sent<br>';
                        }
                    }
                }
                if ($expirydate) {
                    update_query("tbldomains", array ("expirydate" => $expirydate ), array ('id' => $data['id']  ));
                    if ($SyncNextDueDate) {
                        update_query("tbldomains", array ("nextduedate" => $expirydate ), array ('id' => $data['id']  ));
                    }
                    $cronreport .= '' . 'Updated ' . $domainname . ' expiry to ' . frommysqldate($expirydate) . '<br>';
                }
            } else {
                $cronreport .= '' . 'ERROR: ' . $domainname . ' -  Domain does not appear in the account at Internet.bs.<br>';
            }
        }
    }
}
logactivity('Internet.bs Domain Sync Run');
sendadminnotification('system', 'WHMCS Internet.bs Domain Syncronisation Report', $cronreport);
