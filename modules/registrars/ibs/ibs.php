<?php

define("IBS_MODULE_VERSION", "1.0.5");

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Domain\TopLevel\ImportItem;

/**
 * live api server url
 */
define('API_SERVER_URL', 'https://api.internet.bs/');
/**
 * api test server url, when $params['TestMode']='on' is used, then this url will be used
 */
define('API_TESTSERVER_URL', 'https://77.247.183.107/');
//define('API_TESTSERVER_URL', 'http://api.internet.devel/');
//define('API_TESTSERVER_URL', 'https://api.internet.bs/');

$ibs_last_error = null;

function ibs_getLastError()
{
    global $ibs_last_error;
    return $ibs_last_error;
}


function ibs_billableOperationErrorHandler($params, $subject, $message)
{
    $dept = $params['NotifyOnError'];
    //get dept id
    if (preg_match('/.+\((\d+)\)/ix', $dept, $regs)) {
        $depId = $regs[1];
        $command = 'OpenTicket';
        $postData = array(
            'deptid' => $depId,
            'subject' => "IBS MODULE ERROR: " . $subject,
            'message' => $message,
            'priority' => 'High',
            'email' => 'info@support.internet.bs',
            'name' => "Internet.bs registrar module"
        );
        localAPI($command, $postData);
    }
}
/**
 * Returns whois status of domain
 * @param $params
 * @return array
 */
function ibs_getwhois($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);
    if (isset($_POST["status"])) {
        if (strtolower($_POST["status"]) == "disabled") {
            $resourcePath = "Domain/PrivateWhois/enable";
        } else {
            $resourcePath = "Domain/PrivateWhois/disable";
        }
    } else {
        $resourcePath = 'Domain/PrivateWhois/Status';
    }

    $commandUrl = $apiServerUrl . $resourcePath;
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $errormessage = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $errormessage = $result ['message'];
        $idStatus = "unknown";
    } elseif (!$result['status']) {
        $errormessage = "Id Protection is not supported";
    } else {
        $idStatus = $result ['privatewhoisstatus'];
        if (strtolower($idStatus) == "disable" || strtolower($idStatus) == "disabled") {
            $idStatus = "disabled";
        } else {
            $idStatus = "enabled";
        }
        if (isset($_POST["status"])) {
            $successmessage = "Data saved successfully";
        }
    }
    $domainid = $params["domainid"];
    if (!$idStatus) {
        if (!$errormessage) {
            $errormessage = "Id Protection is not supported.";
        }
    }
    return array(
        'templatefile' => 'whois',
        'breadcrumb' => array('clientarea.php?action=domaindetails&id=' . $domainid . '&modop=custom&a=whois' => 'whois'),
        'vars' => array(
            'domain' => $domainName,
            'status' => $idStatus,
            'errormessage' => $errormessage,
            'successmessage' => $successmessage,
        ),
    );
}

function ibs_additionalfields($params)
{

    $additionalFieldValue = $params["additionalfields"];
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params["tld"];
    $sld = $params["sld"];
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    /* For whmcs version below 7 has additionaldomain fields at different location*/
    if (file_exists(ROOTDIR . "/includes/additionaldomainfields.php")) {
        include(ROOTDIR . "/includes/additionaldomainfields.php");
    } else {
        include(ROOTDIR . "resources/domains/dist.additionalfields.php");
    }
    include(ROOTDIR . "/modules/registrars/ibs/ibs_additionaldomainfields.php");
    global $additionaldomainfields;
    /* Additional Domain Fields for tld from additionaldimainfields file */
    $additionalfields = $additionaldomainfields["." . $tld];
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    if (isset($_POST) && count($_POST) > 0) {
        $whoisData = $_POST;
        unset($whoisData['token']);
        unset($whoisData['modop']);
        unset($whoisData['a']);
        unset($whoisData['id']);
        foreach ($whoisData as $key => $value) {
            if (strpos($key, 'other_') !== false) {
                $newKey = str_replace("other_", "", $key);
                $whoisData[$newKey] = $whoisData[$key];
                unset($whoisData[$key]);
            }
        }
        if ($tld == "nl") {
            $whoisData['registrant_clientip'] = ibs_getClientIp();
            if ($whoisData['registrant_nlterm'] != '') {
                $whoisData['registrant_nlterm'] = "YES";
            } else {
                $whoisData['registrant_nlterm'] = "NO";
            }
        }
        if ($tld == "us") {
            $usDomainPurpose = $whoisData['registrant_uspurpose'];
        }
        if ($tld == "de") {
            if ($whoisData['registrant_restricted_publication'] == "on") {
                $whoisData["registrant_discloseName"] = $whoisData["registrant_discloseContact"] = $whoisData["registrant_discloseAddress"] = "Yes";
            } else {
                $whoisData["registrant_discloseName"] = $whoisData["registrant_discloseContact"] = $whoisData["registrant_discloseAddress"] = "No";
            }
            unset($whoisData['registrant_restricted_publication']);
            if ($whoisData['admin_restricted_publication'] == "on") {
                $whoisData["admin_discloseName"] = $whoisData["admin_discloseContact"] = $whoisData["admin_discloseAddress"] = "Yes";
            } else {
                $whoisData["admin_discloseName"] = $whoisData["admin_discloseContact"] = $whoisData["admin_discloseAddress"] = "No";
            }
            unset($whoisData['admin_restricted_publication']);
            if ($whoisData['technical_restricted_publication'] == "on") {
                $whoisData["technical_discloseName"] = $whoisData["technical_discloseContact"] = $whoisData["technical_discloseAddress"] = "Yes";
            } else {
                $whoisData["technical_discloseName"] = $whoisData["technical_discloseContact"] = $whoisData["technical_discloseAddress"] = "No";
            }
            unset($whoisData['technical_restricted_publication']);
            if ($whoisData['zone_restricted_publication'] == "on") {
                $whoisData["zone_discloseName"] = $whoisData["zone_discloseContact"] = $whoisData["zone_discloseAddress"] = "Yes";
            } else {
                $whoisData["zone_discloseName"] = $whoisData["zone_discloseContact"] = $whoisData["zone_discloseAddress"] = "No";
            }
            unset($whoisData['zone_restricted_publication']);
            $whoisData['clientip'] = ibs_getClientip();
        }
        if ($tld == "it") {
            $entityTypes = array('1. Italian and foreign natural persons' => 1, '2. Companies/one man companies' => 2, '3. Freelance workers/professionals' => 3, '4. non-profit organizations' => 4, '5. public organizations' => 5, '6. other subjects' => 6, '7. foreigners who match 2 - 6' => 7);
            $whoisData['registrant_dotitentitytype'] = $entityTypes[$whoisData['registrant_dotitentitytype']];
            if (strlen($whoisData['registrant_dotitnationality']) > 2) {
                $whoisData['registrant_dotitnationality'] = ibs_getCountryCodeByName($whoisData['registrant_dotitnationality']);
            }
            if ($whoisData['registrant_itterms'] == "on") {
                $whoisData['registrant_dotitterm1'] = "Yes";
                $whoisData['registrant_dotitterm2'] = "Yes";
                $whoisData['registrant_dotitterm3'] = "Yes";
                $whoisData['registrant_dotitterm4'] = "Yes";
                unset($whoisData['registrant_itterms']);
            } else {
                $whoisData['registrant_dotitterm1'] = "No";
                $whoisData['registrant_dotitterm2'] = "No";
                $whoisData['registrant_dotitterm3'] = "No";
                $whoisData['registrant_dotitterm4'] = "No";
            }
            if ($whoisData['registrant_dotithidewhois'] == "on" && $whoisData['registrant_dotitentitytype'] == 1) {
                $whoisData['registrant_dotithidewhois'] = "Yes";
            } else {
                $whoisData['registrant_dotithidewhois'] = "No";
            }
            if ($whoisData['admin_dotithidewhois'] == "on") {
                $whoisData['admin_dotithidewhois'] = "Yes";
            } else {
                $whoisData['admin_dotithidewhois'] = "No";
            }
            if ($whoisData['technical_dotithidewhois'] == "on") {
                $whoisData['technical_dotithidewhois'] = "Yes";
            } else {
                $whoisData['technical_dotithidewhois'] = "No";
            }
        }
        $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);
        $data = array_merge($data, $whoisData);
        $data['registrant_clientip'] = ibs_getClientIp();
        $commandUrl = $apiServerUrl . "Domain/Update";
        $result = ibs_runCommand($commandUrl, $data);
        $errorMessage = ibs_getLastError();
        # If error, return the error message in the value below
        if ($result === false) {
            $errormessage = ibs_getConnectionErrorMessage($errorMessage);
        } elseif ($result ['status'] == 'FAILURE') {
            $errormessage = $result ['message'];
        } else {
            $successmessage = "Data Saved Successfully";
        }
    }

    //Change the name of the tld specific fields
    switch ($tld) {
        case "fr":
        case "re":
        case "pm":
        case "yt":
        case "wf":
            foreach ($additionalfields as $key => &$value) {
                if ($value["Name"] == "Holder Type") {
                    $value["Name"] = "dotfrcontactentitytype";
                }
                if ($value["Name"] == "Birth Date YYYY-MM-DD") {
                    $value["Name"] = "dotfrcontactentitybirthdate";
                }
                if ($value["Name"] == "Birth Country Code") {
                    $value["Name"] = "dotfrcontactentitybirthplacecountrycode";
                }
                if ($value["Name"] == "Birth City") {
                    $value["Name"] = "dotfrcontactentitybirthcity";
                }
                if ($value["Name"] == "Birth Postal code") {
                    $value["Name"] = "dotfrcontactentitybirthplacepostalcode";
                }
                if ($value["Name"] == "Restricted Publication") {
                    $value["Name"] = "dotfrcontactentityrestrictedpublication";
                }
                if ($value["Name"] == "Siren") {
                    $value["Name"] = "dotfrcontactentitysiren";
                }
                if ($value["Name"] == "Trade Mark") {
                    $value["Name"] = "dotfrcontactentitytradeMark";
                }
                if ($value["Name"] == "Waldec") {
                    $value["Name"] = "dotfrcontactentitywaldec";
                }
                if ($value["Name"] == "Date of Association YYYY-MM-DD") {
                    $value["Name"] = "dotfrcontactentitydateofassociation";
                }
                if ($value["Name"] == "Date of Publication YYYY-MM-DD") {
                    $value["Name"] = "dotfrcontactentitydateofpublication";
                }
                if ($value["Name"] == "Announce No") {
                    $value["Name"] = "dotfrcontactentityannounceno";
                }
                if ($value["Name"] == "Page No") {
                    $value["Name"] = "dotfrcontactentitypageno";
                }
                if ($value["Name"] == "Other Legal Status") {
                    $value["Name"] = "dotfrothercontactentity";
                }
                if ($value["Name"] == "VATNO") {
                    $value["Name"] = "dotfrcontactentityvat";
                }
                if ($value["Name"] == "DUNSNO") {
                    $value["Name"] = "dotfrcontactentityduns";
                }
            }
            break;
        case 'asia':
            foreach ($additionalfields as $key => &$value) {
                if ($value["Name"] == "Locality") {
                    $value["Name"] = "dotasiacedlocality";
                }
                if ($value["Name"] == "Legal Entity Type") {
                    $value["Name"] = "dotasiacedentity";
                }
                if ($value["Name"] == "Identification Form") {
                    $value["Name"] = "dotasiacedidform";
                }
                if ($value["Name"] == "Identification Number") {
                    $value["Name"] = "dotasiacedidnumber";
                }
                if ($value["Name"] == "Other legal entity type") {
                    $value["Name"] = "dotasiacedentityother";
                }
                if ($value["Name"] == "Other identification form") {
                    $value["Name"] = "dotasiacedidformother";
                }
            }
            break;
        case 'us':
            foreach ($additionalfields as $key => &$value) {
                $value["contactType"] = array("registrant");
                if ($value["Name"] == "Nexus Category") {
                    $value["DisplayName"] = $value["Name"];
                    $value["Name"] = "usnexuscategory";
                }
                if ($value["Name"] == "Nexus Country") {
                    $value["DisplayName"] = $value["Name"];
                    $value["Name"] = "usnexuscountry";
                }
                if ($value["Name"] == "Application Purpose") {
                    $value["DisplayName"] = $value["Name"];
                    $value["Name"] = "uspurpose";
                }
            }
            break;
        case 'de':
            foreach ($additionalfields as $key => &$value) {
                if (strtolower($value["Name"]) == "tosagree") {
                    $value["contactType"] = array("other");
                }
                if (strtolower($value["Name"]) == "role") {
                    $value["contactType"] = array("registrant");
                }
                if (strtolower($value["Name"]) == "restricted publication") {
                    $value['contactType'] = array("registrant", "admin");
                }
            }
            array_unshift($additionalfields, array(
                "Name" => "role",
                "DisplayName" => "Role",
                "Type" => "dropdown",
                "Options" => "person|Person",
                "contactType" => array("admin")
            ));
            array_unshift($additionalfields, array(
                "Name" => "role",
                "DisplayName" => "Role",
                "Type" => "dropdown",
                "Options" => "person|Person,role|Role",
                "contactType" => array("technical")
            ));
            array_unshift($additionalfields, array(
                "Name" => "role",
                "DisplayName" => "Role",
                "Type" => "dropdown",
                "Options" => "person|Person,role|Role",
                "contactType" => array("zone")
            ));
            break;
        case 'nl':
            foreach ($additionalfields as $key => &$value) {
                if (strtolower($value["Name"]) == "nlterm") {
                    $value["contactType"] = array("registrant");
                }
            }
            break;
        case 'it':
            foreach ($additionalfields as $key => &$value) {
                if ($value["Name"] == "Legal Entity Type") {
                    $value["Name"] = "dotitentitytype";
                    $value["contactType"] = array("registrant");
                }
                if ($value["Name"] == "Nationality") {
                    $value["Name"] = "dotitnationality";
                    $value["contactType"] = array("registrant");
                }
                if ($value["Name"] == "VATTAXPassportIDNumber") {
                    $value["Name"] = "dotitregcode";
                    $value["contactType"] = array("registrant");
                }
                if ($value["Name"] == "Hide data in public WHOIS") {
                    $value["Name"] = "dotithidewhois";
                }
                if ($value["Name"] == "itterms") {
                    $value["contactType"] = array("registrant");
                }
            }
            break;
        case 'eu':
            foreach ($additionalfields as $key => &$value) {
                if ($value["Name"] == "Language") {
                    $value["Name"] = "language";
                }
            }
            break;
    }
    //Get Domain information
    $commandUrl = $apiServerUrl . "Domain/Info";
    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $errormessage = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $errormessage = $result ['message'];
    } else {
        //assign additional fields to new array to display it to the users
        $contactIndex = 0;
        $contacts = array();
        $contactData = array();
        foreach ($result as $resultKey => $resultValue) {
            foreach ($additionalfields as $extrakey => $extravalue) {
                if (strpos($resultKey, "contacts_") !== false) {
                    $newKey = str_replace("contacts_", "", $resultKey);
                    $newKey = explode("_", $newKey);
                    if (strtolower($extravalue["Name"]) == strtolower($newKey[1])) {
                        $contactData[$newKey[0]][$newKey[1]] = $result[$resultKey];
                        if (!in_array($newKey[0], $contacts)) {
                            $contacts[$contactIndex] = $newKey[0];
                            $contactIndex++;
                        }
                    }
                }
            }
        }
        //tld specific modifications to additional fields values obtained from api
        if ($tld == "de") {
            if (strtolower($result['contacts_registrant_disclosename']) == 'yes' || strtolower($result['contacts_registrant_disclosecontact']) == 'yes' || strtolower($result['contacts_registrant_discloseaddress']) == 'yes') {
                $contactData['registrant']['restricted publication'] = 'Yes';
            } else {
                $contactData['registrant']['restricted publication'] = 'No';
            }
            if (strtolower($result['contacts_admin_disclosename']) == 'yes' || strtolower($result['contacts_admin_disclosecontact']) == 'yes' || strtolower($result['contacts_admin_discloseaddress']) == 'yes') {
                $contactData['admin']['restricted publication'] = 'Yes';
            } else {
                $contactData['admin']['restricted publication'] = 'No';
            }
            $contacts[$contactIndex] = "other";
            $contactData['other']['tosagree'] = "NO";
        }
    }
    $domainid = $params["domainid"];
    return array(
        'templatefile' => 'additionalfields',
        'breadcrumb' => array('clientarea.php?action=domaindetails&id=' . $domainid . '&modop=custom&a=additionalfields' => 'additionalfields'),
        'vars' => array(
            'additionalfields' => $additionalfields,
            'domainName' => $domainName,
            "whoisContacts" => $contacts,
            'additionalFieldValue' => $contactData,
            'errormessage' => $errormessage,
            'successmessage' => $successmessage
        ),
    );
}

function ibs_ClientAreaCustomButtonArray($params)
{
    $params = ibs_get_utf8_parameters($params);

    $buttonArray = array();
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);

    $commandUrl = $apiServerUrl . "Domain/PrivateWhois/Status";
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    /*Code 100014 means feature not supported
      If feature is not supported it is not required to show button Manage id protection*/
    $configButton = array();
    if ($result['code'] != 100014) {
        $whoisButtonArray = array(
            "Manage Id Protection" => 'getwhois'
        );
        $buttonArray = array_merge($whoisButtonArray, $buttonArray);
    }

    /* For Email Verification*/
    $data = ibs_getEmailVerificationDetails($params);
    if (strtoupper($data['currentstatus']) !== "VERIFIED") {
        $button1 = array(
            "Verify Email" => "verify",
            "" => "send",
        );
        $buttonArray = array_merge($buttonArray, $button1);
    }
    if (count($params['additionalfields']) > 0 && $tld !== "tel") {
        $notTmch = false;
        foreach ($params['additionalfields'] as $key => $value) {
            if (!strstr($key, "tmch")) {
                $notTmch = true;
            }
        }
        if ($notTmch) {
            $configButton = array(
                "Domain Configurations" => "additionalfields"
            );
        }
    }


    $buttonArray = array_merge($configButton, $buttonArray);
    $urlForwardArray = array(
        "URL Forwarding" => 'domainurlforwarding'
    );
    $buttonArray = array_merge($urlForwardArray, $buttonArray);
    return $buttonArray;
}

/**
 * runs an api command and returns parsed data
 *
 * @param string $commandUrl
 * @param array $postData
 * @param string $errorMessage if cannot connect to server
 * @return array
 */

function ibs_runCommand($commandUrl, $postData)
{
    //If field starts with '@', escape it
    foreach ($postData as $key => $value) {
        if (substr($value, 0, 1) == "@") {
            $postData = http_build_query($postData);
            break;
        }
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $commandUrl);

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, "IBS WHMCS module V" . IBS_MODULE_VERSION);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $data = curl_exec($ch);

    global $ibs_last_error;
    $ibs_last_error = curl_error($ch);
    $logData["action"] = $commandUrl;
    $logData["requestParam"] = $postData;
    $logData["responseParam"] = $data;
    ibs_debugLog($logData);
    curl_close($ch);

    return (($data === false) ? false : ibs_parseResult($data));
}


function ibs_debugLog($data)
{
    ob_start();
    debug_print_backtrace();
    $backtrace = ob_get_clean();
    logModuleCall("Internet.bs Registrar Module", $data["action"], $data["requestParam"], $data["responseParam"], $backtrace);
}

function ibs_getConnectionErrorMessage($message)
{
    return 'Cannot connect to server. [' . $message . ']';
}

function ibs_getConfigArray()
{
    $results = localAPI('GetSupportDepartments', array());
    $departments = array("-");
    if ($results && count($results) && count($results['departments'])) {
        foreach ($results['departments']['department'] as $dept) {
            $departments[] = $dept['name'] . " (" . $dept['id'] . ")";
        }
    }

    $configarray = array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Internet.bs V' . IBS_MODULE_VERSION
        ),
        "Description" => array(
            "Type" => "System",
            "Value" => "The Official Internet.bs Registrar Module. Get an account here: <a target='blank_' href='https://internetbs.net/newaccount.html'>https://internetbs.net/newaccount.html</a>"
        ),
        "Username" => array("Type" => "text", "Size" => "50", "Description" => "Enter your Internet.bs ApiKey here"),
        "Password" => array("Type" => "password", "Size" => "50", "Description" => "Enter your Internet.bs password here"),
        "TestMode" => array("Type" => "yesno", 'Description' => "Check this checkbox if you want to connect to the test server"),
        "HideWhoisData" => array("Type" => "yesno", 'Description' => "Tick this box if you want to hide the information in the public whois for Admin/Billing/Technical contacts (.it)"),
        "SyncNextDueDate" => array("Type" => "yesno", 'Description' => "Tick this box if you want the expiry date sync script to update both expiry and next due dates (cron must be configured). If left unchecked it will only update the domain expiration date."),
        "RenewAfterTransfer" => array("Type" => "yesno", 'Description' => "Tick this box if you want to add renewal after transferring .de and .nl domain")
    );
    if (count($departments) > 1) {
        $configarray['NotifyOnError'] = array('FriendlyName' => 'Notify department ', "Type" => "dropdown", 'Description' => "Please chose a department, if you want to have a ticket opened in case of errors returned by our API.",
            'Options' => implode(",", $departments));
    }
    return $configarray;
}

/**
 * parse result
 * format: array('name' => value)
 *
 * @param string $data
 * @return array
 */
function ibs_parseResult($data)
{
    $result = array();
    $arr = explode("\n", $data);
    foreach ($arr as $str) {
        list ($varName, $value) = explode("=", $str, 2);
        $varName = trim($varName);
        $value = trim($value);
        $result [$varName] = $value;
    }
    return $result;
}

/**
 * Expiration date sync
 * @param $parameters
 */
function ibs_Sync($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Info';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    $values = array();
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    } else {
        //success
        if ($result["domainstatus"] == "EXPIRED") {
            $values["expired"] = true;
        } elseif ($result["domainstatus"] != 'PENDING TRANSFER') {
            $values["active"] = true;
        }
        if (isset($result['paiduntil']) && $result['paiduntil'] != 'n/a') {
            $values["expirydate"] = str_replace("/", "-", $result['paiduntil']);
        }
    }
    return $values;
}

/**
 * Expiration date sync
 * @param $parameters
 */
function ibs_TransferSync($params)
{
    return ibs_Sync($params);
}

/**
 * gets list of nameservers for a domain
 *
 * @param array $params
 * @return array
 */
function ibs_GetNameservers($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Info';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    } else {
        // possible number of hosts exists
        $i = 0;
        while (isset($result ['nameserver_' . $i])) {
            $values ['ns' . ($i + 1)] = $result ['nameserver_' . $i];
            ++$i;
        }
    }

    return $values;
}

/**
 * attach nameserver to a domain by Domain/Update command
 *
 * @param array $params
 * @return array
 */
function ibs_SaveNameservers($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    # code to save the nameservers
    $nslist = array();
    if (isset($params["original"])) {
        $paramsData = $params["original"];
    } else {
        $paramsData = $params;
    }
    for ($i = 1; $i <= 5; $i++) {
        if (isset($paramsData ["ns$i"])) {
            if (isset($paramsData ['ns' . $i . '_ip']) && strlen($paramsData ['ns' . $i . '_ip'])) {
                $paramsData ["ns$i"] .= ' ' . $paramsData ['ns' . $i . '_ip'];
            }
            array_push($nslist, $paramsData ["ns$i"]);
        }
    }

    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Update';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName, 'ns_list' => trim(implode(',', $nslist), ","));
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    }
    return $values;
}

/**
 * gets registrar lock status of a domain
 *
 * @param array $params
 * @return string
 */
function ibs_GetRegistrarLock($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    # code to get the lock status
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/RegistrarLock/Status';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'SUCCESS') {
        if (strtolower($result ['registrar_lock_status']) == 'locked') {
            $lockstatus = "locked";
        } else {
            $lockstatus = "unlocked";
        }
    }

    return (strlen($lockstatus) ? $lockstatus : $values);
}

/**
 * enable/disable registrar lock for a domain
 *
 * @param array $params
 * @return array
 */
function ibs_SaveRegistrarLock($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    # code to save the registrar lock
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    // if lockenabled is set, we need to run lock enable command
    if (strtolower($params ["lockenabled"]) == "locked") {
        //$lockstatus="locked";
        $resourcePath = 'Domain/RegistrarLock/Enable';
    } else {
        //$lockstatus="unlocked";
        $resourcePath = 'Domain/RegistrarLock/Disable';
    }
    $commandUrl = $apiServerUrl . $resourcePath;

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();

    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    }

    return $values;
}

/**
 * This function is called to toggle Id protection status
 * @param $params
 */

function ibs_IDProtectToggle($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    # code to get the WHOIS status
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    //if protectenable is set, we need to enable whois
    if ($params['protectenable']) {
        $resourcePath = "Domain/PrivateWhois/enable";
    } else {
        $resourcePath = "Domain/PrivateWhois/disable";
    }
    $commandUrl = $apiServerUrl . $resourcePath;
    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    if ($result === false) {
        $values["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values["error"] = $result ['message'];
    }
    return $values;
}

/**
 * gets email forwarding rules list of a domain
 *
 * @param array $params
 * @return array
 */
function ibs_GetEmailForwarding($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    # code to get email forwarding - the result should be an array of prefixes and forward to emails (max 10)
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/EmailForward/List';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    } else {
        $totalRules = $result ['total_rules'];
        for ($i = 1; $i <= $totalRules; $i++) {
            // prefix is the first part before @ at email addrss
            list ($prefix, $domainName) = explode('@', $result ['rule_' . $i . '_source']);
            if (empty($prefix)) {
                $prefix = "@";
            }
            $values [$i] ["prefix"] = $prefix;
            $values [$i] ["forwardto"] = $result ['rule_' . $i . '_destination'];
        }
    }

    return $values;
}

/**
 * saves email forwarding rules of a domain
 *
 * @param array $params
 * @return array
 */
function ibs_SaveEmailForwarding($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    #code to save email forwarders
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;

    $data = array('apikey' => $username, 'password' => $password);

    $errorMessages = '';
    $rules = ibs_GetEmailForwarding($params);
    if (is_array($rules)) {
        foreach ($rules as $rule) {
            $source = trim($rule ["prefix"], '@ ') . "@" . $domainName;
            $source = urlencode($source);
            $cmdData = array("source" => $source);
            $cmdData = array_merge($cmdData, $data);
            $cmd = $apiServerUrl . 'Domain/EmailForward/Remove';
            $error = '';
            ibs_runCommand($cmd, $cmdData);
        }
    }

    if (!isset($params["original"]["prefix"])) {
        $prefix = $params["prefix"];
    } else {
        $prefix = $params["original"]["prefix"];
    }

    if (!isset($params["original"]["forwardto"])) {
        $forwardto = $params["forwardto"];
    } else {
        $forwardto = $params["original"]["forwardto"];
    }

    foreach ($prefix as $key => $value) {
        $from = $prefix [$key];
        $to = $forwardto[$key];
        if (trim($to) == '') {
            continue;
        }

        $data ['source'] = urlencode(trim($from, '@ ') . '@' . $domainName);
        $data ['destination'] = urlencode($to);
        $commandUrl = $apiServerUrl . 'Domain/EmailForward/Add';
        // try to add rule
        $result = ibs_runCommand($commandUrl, $data);
        $errorMessage = ibs_getLastError();
        if ($result === false) {
            $errorMessages .= ibs_getConnectionErrorMessage($errorMessage);
        } elseif ($result['status'] === 'FAILURE') {
            $values ["error"] = $result ['message'];
        }
    }
    // error occurs
    if (strlen($errorMessages)) {
        $values ["error"] = $errorMessages;
    }

    return $values;
}

/**
 * gets DNS Record list of a domain
 *
 * @param array $params
 * @return array
 */
function ibs_GetDNS($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];
    # code here to get the current DNS settings - the result should be an array of hostname, record type, and address
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/DnsRecord/List';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } else {
        if (is_array($result)) {
            $keys = array_keys($result);
            $temp = 0;
            foreach ($keys as $key) {
                if (strpos($key, 'records_') === 0) {
                    $recNo = substr($key, 8);
                    $recNo = substr($recNo, 0, strpos($recNo, "_"));
                    if ($recNo > $temp) {
                        $temp = $recNo;
                    }
                }
            }
        }
        $hostrecords = array();
        $totalRecords = $temp;
        for ($i = 0; $i <= $totalRecords; $i++) {
            $recordType = '';
            if (isset($result ['records_' . $i . '_type'])) {
                $recordType = trim($result ['records_' . $i . '_type']);
            }
            if (!in_array(strtolower($recordType), array("a", "mx", "cname", 'txt', 'aaaa', 'txt'))) {
                continue;
            }
            if (isset($result ['records_' . $i . '_name'])) {
                $recordHostname = $result ['records_' . $i . '_name'];
                $dParts = explode('.', $domainName);
                $hParts = explode('.', $recordHostname);
                $recordHostname = '';
                for ($j = 0; $j < (count($hParts) - count($dParts)); $j++) {
                    $recordHostname .= (empty($recordHostname) ? '' : '.') . $hParts[$j];
                }
            }
            if (isset($result ['records_' . $i . '_value'])) {
                $recordAddress = $result ['records_' . $i . '_value'];
            }
            if (isset($result ['records_' . $i . '_name'])) {
                $hostrecords [] = array("hostname" => $recordHostname, "type" => $recordType, "address" => htmlspecialchars($recordAddress), 'priority' => $result['records_' . $i . '_priority']);
            }
        }
        $commandUrl = $apiServerUrl . "Domain/UrlForward/List";
        $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);
        $result = ibs_runCommand($commandUrl, $data);
        $errorMessage = ibs_getLastError();
        if ($result === false) {
            $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
        } else {
            $totalRecords = (int)$result ['total_rules'];
            for ($i = 1; $i <= $totalRecords; $i++) {
                $recordType = '';
                if (isset($result ['rule_' . $i . '_isframed'])) {
                    $recordType = trim($result ['rule_' . $i . '_isframed']) == 'YES' ? "FRAME" : 'URL';
                }
                if (isset($result ['rule_' . $i . '_source'])) {
                    $recordHostname = $result ['rule_' . $i . '_source'];
                    $dParts = explode('.', $domainName);
                    $hParts = explode('.', $recordHostname);
                    $recordHostname = '';
                    for ($j = 0; $j < (count($hParts) - count($dParts)); $j++) {
                        $recordHostname .= (empty($recordHostname) ? '' : '.') . $hParts[$j];
                    }
                }
                if (isset($result ['rule_' . $i . '_destination'])) {
                    $recordAddress = $result ['rule_' . $i . '_destination'];
                }
                if (isset($result ['rule_' . $i . '_source'])) {
                    $hostrecords [] = array("hostname" => $recordHostname, "type" => $recordType, "address" => htmlspecialchars($recordAddress));
                }
            }
        }
    }
    return (count($hostrecords) ? $hostrecords : $values);
}

/**
 * saves dns records for a domain
 *
 * @param array $params
 * @return array
 */
function ibs_SaveDNS($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;

    $data = array('apikey' => $username, 'password' => $password);

    $errorMessages = '';
    $recs = ibs_GetDNS($params);
    if (is_array($recs)) {
        foreach ($recs as $r) {
            $source = $r ["hostname"] . ".$domainName";
            $source = trim($source, ". ");
            $type = $r ["type"];
            $remParams = array();
            if ($type == "FRAME" || $type == "URL") {
                $cmdPath = "Domain/UrlForward/Remove";
                $remParams ["source"] = $source;
            } else {
                $cmdPath = "Domain/DnsRecord/Remove";
                $remParams ["FullRecordName"] = $source;
                $remParams ["type"] = $type;
            }
            $remParams = array_merge($remParams, $data);
            $cmdPath = $apiServerUrl . $cmdPath;
            ibs_runCommand($cmdPath, $remParams);
        }
    }
    # Loop through the submitted records
    if (!isset($params['original']['dnsrecords'])) {
        $dnsRecords = $params['dnsrecords'];
    } else {
        $dnsRecords = $params['original']['dnsrecords'];
    }

    foreach ($dnsRecords as $key => $values) {
        $hostname = $values ["hostname"];
        $type = $values ["type"];
        $address = $values ["address"];
        if (trim($hostname) === '' && trim($address) == '') {
            continue;
        }

        # code to update the record
        if (($hostname != $domainName) && strpos($hostname, '.' . $domainName) === false) {
            $hostname = $hostname . '.' . $domainName;
        }
        $cmdData = array();
        if (!($type == 'URL' || $type == 'FRAME')) {
            $cmdData ['fullrecordname'] = trim($hostname, ". ");
            $cmdData ['type'] = $type;
            $cmdData ['value'] = $address;
            $cmdData['priority'] = intval($values["priority"]);
            $commandUrl = $apiServerUrl . 'Domain/DnsRecord/Add';
        } else {
            $cmdData ['source'] = trim($hostname, ". ");
            $cmdData ['isFramed'] = $type == 'FRAME' ? 'YES' : 'NO';
            $cmdData ['Destination'] = $address;
            $commandUrl = $apiServerUrl . 'Domain/UrlForward/Add';
        }
        $cmdData = array_merge($data, $cmdData);

        $result = ibs_runCommand($commandUrl, $cmdData);
        $errorMessage = ibs_getLastError();
        if ($result === false) {
            $errorMessages .= ibs_getConnectionErrorMessage($errorMessage);
        }
    }

    # If error, return the error message in the value below
    if (strlen($errorMessages)) {
        $values ["error"] = $errorMessages;
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    }
    return $values;
}

/**
 * registers a domain
 *
 * @param array $params
 * @return array
 */
function ibs_RegisterDomain($params)
{
    $params = ibs_get_utf8_parameters($params);
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $hideWhoisData = (isset($params ["HideWhoisData"]) && ('on' == strtolower($params ["HideWhoisData"]))) ? 'YES' : 'NO';
    $premiumDomainsEnabled = (bool)$params['premiumEnabled'];
    $premiumDomainsCost = $params['premiumCost'];//this is USD because we only get the price in USD

    $tld = $params ["tld"];
    $sld = $params ["sld"];

    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $regperiod = (int)$params ["regperiod"];

    # Registrant Details
    $RegistrantFirstName = $params ["firstname"];
    $RegistrantLastName = $params ["lastname"];
    $RegistrantCompany = trim($params["companyname"]);
    $RegistrantAddress1 = $params ["address1"];
    $RegistrantAddress2 = $params ["address2"];
    $RegistrantCity = $params ["city"];
    $RegistrantStateProvince = $params ["state"];
    $RegistrantPostalCode = $params ["postcode"];
    $RegistrantCountry = $params ["country"];
    $RegistrantEmailAddress = $params ["email"];
    $RegistrantPhone = ibs_reformatPhone($params ["phonenumber"], $params ["country"]);
    # Admin Details
    $AdminFirstName = $params ["adminfirstname"];
    $AdminLastName = $params ["adminlastname"];
    $AdminCompany = trim($params["admincompanyname"]);
    $AdminAddress1 = $params ["adminaddress1"];
    $AdminAddress2 = $params ["adminaddress2"];
    $AdminCity = $params ["admincity"];
    $AdminStateProvince = $params ["adminstate"];
    $AdminPostalCode = $params ["adminpostcode"];
    $AdminCountry = $params ["admincountry"];
    $AdminEmailAddress = $params ["adminemail"];
    $AdminPhone = ibs_reformatPhone($params ["adminphonenumber"], $params ["admincountry"]);
    #get trade details if assoiciated
    $domainid = $params["domainid"];
    $table = "tbldomainsadditionalfields";
    $fields = "name, value";
    $where = array("domainid" => $domainid);
    $result = select_query($table, $fields, $where);
    while ($response = mysql_fetch_array($result)) {
        if ($response["name"] == "tmchid") {
            $tmchId = $response["value"];
        } elseif ($response["name"] == "tmchnotafter") {
            $tmchNotAfter = $response["value"];
        } elseif ($response["name"] == "tmchaccepteddate") {
            $tmchAcceptedDate = $response["value"];
        }
    }
    # Put your code to register domain here

    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Create';

    $nslist = array();
    if (isset($params["original"])) {
        $paramsData = $params["original"];
    } else {
        $paramsData = $params;
    }
    for ($i = 1; $i <= 5; $i++) {
        if (isset($paramsData ["ns$i"])) {
            array_push($nslist, $paramsData ["ns$i"]);
        }
    }

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName,

        // registrant contact data
        'registrant_firstname' => $RegistrantFirstName, 'registrant_lastname' => $RegistrantLastName, 'registrant_street' => $RegistrantAddress1, 'registrant_street2' => $RegistrantAddress2, 'registrant_city' => $RegistrantCity, 'registrant_state' => $RegistrantStateProvince, 'registrant_countrycode' => $RegistrantCountry, 'registrant_postalcode' => $RegistrantPostalCode, 'registrant_email' => $RegistrantEmailAddress, 'registrant_phonenumber' => $RegistrantPhone,

        // technical contact data
        'technical_firstname' => $AdminFirstName, 'technical_lastname' => $AdminLastName, 'technical_street' => $AdminAddress1, 'technical_street2' => $AdminAddress2, 'technical_city' => $AdminCity, 'technical_state' => $AdminStateProvince, 'technical_countrycode' => $AdminCountry, 'technical_postalcode' => $AdminPostalCode, 'technical_email' => $AdminEmailAddress, 'technical_phonenumber' => $AdminPhone,

        // admin contact data
        'admin_firstname' => $AdminFirstName, 'admin_lastname' => $AdminLastName, 'admin_street' => $AdminAddress1, 'admin_street2' => $AdminAddress2, 'admin_city' => $AdminCity, 'admin_state' => $AdminStateProvince, 'admin_countrycode' => $AdminCountry, 'admin_postalcode' => $AdminPostalCode, 'admin_email' => $AdminEmailAddress, 'admin_phonenumber' => $AdminPhone,

        // billing contact data
        'billing_firstname' => $AdminFirstName, 'billing_lastname' => $AdminLastName, 'billing_street' => $AdminAddress1, 'billing_street2' => $AdminAddress2, 'billing_city' => $AdminCity, 'billing_state' => $AdminStateProvince, 'billing_countrycode' => $AdminCountry, 'billing_postalcode' => $AdminPostalCode, 'billing_email' => $AdminEmailAddress, 'billing_phonenumber' => $AdminPhone);

    if ($premiumDomainsEnabled && $premiumDomainsCost) {
        $data['confirmpricecurrency'] = 'USD';
        $data['confirmpriceamount'] = $premiumDomainsCost;
    }

    if (isset($tmchId) && isset($tmchNotAfter) && isset($tmchAcceptedDate)) {
        $data['tmchid'] = $tmchId;
        $data['tmchnotafter'] = $tmchNotAfter;
        $data['tmchaccepteddate'] = $tmchAcceptedDate;
    }
    if (!empty($RegistrantCompany)) {
        $data["Registrant_Organization"] = $RegistrantCompany;
    }
    if (!empty($AdminCompany)) {
        $data["technical_Organization"] = $AdminCompany;
        $data["admin_Organization"] = $AdminCompany;
        $data["billing_Organization"] = $AdminCompany;
    }
    // ns_list is optional
    if (count($nslist)) {
        $data ['ns_list'] = trim(implode(',', $nslist), ",");
    }
    if ($params ['idprotection']) {
        $data ["privateWhois"] = "FULL";
    }

    $extarr = explode('.', $tld);
    $ext = array_pop($extarr);

    if ($tld == 'eu' || $tld == 'be' || $ext == 'uk') {
        $data ['registrant_language'] = isset($params ['additionalfields'] ['Language']) ? $params ['additionalfields'] ['Language'] : 'en';
    }

    if ($tld == 'eu') {
        $europeanLanguages = array("cs", "da", "de", "el", "en", "es", "et", "fi", "fr", "hu", "it", "lt", "lv", "mt", "nl", "pl", "pt", "sk", "sl", "sv", "ro", "bg", "ga");
        if (!in_array($data ['registrant_language'], $europeanLanguages)) {
            $data ['registrant_language'] = 'en';
        }

        $europianCountries = array('AX', 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'GF', 'DE', 'GI', 'GR', 'GP', 'HU', 'IS', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'MQ', 'NL', 'NO', 'PL', 'PT', 'RE', 'RO', 'SK', 'SI', 'ES', 'SE', 'GB');
        if (!in_array($RegistrantCountry, $europianCountries)) {
            //let the registration fail if the registrant is not from EU
            $values ["error"] = "Registration failed: Registrant must be from the European Union";
        }
        $data['registrant_countrycode'] = $RegistrantCountry;
    }

    if ($tld == 'be') {
        if (!in_array($data ['registrant_language'], array('en', 'fr', 'nl'))) {
            $data ['registrant_language'] = 'en';
        }

        // Same as for .EU
        if (!in_array($RegistrantCountry, array("AF", "AX", "AL", "DZ", "AS", "AD", "AO", "AI", "AQ", "AG", "AR", "AM", "AW", "AU", "AT", "AZ", "BS", "BH", "BD", "BB", "BY", "BE", "BZ", "BJ", "BM", "BT", "BO", "BA", "BW", "BV", "BR", "IO", "VG", "BN", "BG", "BF", "BI", "KH", "CM", "CA", "CV", "KY", "CF", "TD", "CL", "CN", "CX", "CC", "CO", "KM", "CG", "CK", "CR", "HR", "CU", "CY", "CZ", "CD", "DK", "DJ", "DM", "DO", "TL", "EC", "EG", "SV", "GQ", "ER", "EE", "ET", "FK", "FO", "FM", "FJ", "FI", "FR", "GF", "PF", "TF", "GA", "GM", "GE", "DE", "GH", "GI", "GR", "GL", "GD", "GP", "GU", "GT", "GN", "GW", "GY", "HT", "HM", "HN", "HK", "HU", "IS", "IN", "ID", "IR", "IQ", "IE", "IM", "IL", "IT", "CI", "JM", "JP", "JO", "KZ", "KE", "KI", "KW", "KG", "LA", "LV", "LB", "LS", "LR", "LY", "LI", "LT", "LU", "MO", "MK", "MG", "MW", "MY", "MV", "ML", "MT", "MH", "MQ", "MR", "MU", "YT", "MX", "MD", "MC", "MN", "ME", "MS", "MA", "MZ", "MM", "NA", "NR", "NP", "NL", "AN", "NC", "NZ", "NI", "NE", "NG", "NU", "NF", "KP", "MP", "NO", "OM", "PK", "PW", "PS", "PA", "PG", "PY", "PE", "PH", "PN", "PL", "PT", "PR", "QA", "RE", "RO", "RU", "RW", "SH", "KN", "LC", "PM", "VC", "WS", "SM", "ST", "SA", "SN", "RS", "SC", "SL", "SG", "SK", "SI", "SB", "SO", "ZA", "GS", "KR", "ES", "LK", "SD", "SR", "SJ", "SZ", "SE", "CH", "SY", "TW", "TJ", "TZ", "TH", "TG", "TK", "TO", "TT", "TN", "TR", "TM", "TC", "TV", "VI", "UG", "UA", "AE", "GB", "US", "UM", "UY", "UZ", "VU", "VA", "VE", "VN", "WF", "EH", "YE", "ZM", "ZW"))) {
            //let the registration fail if the registrant is not from EU
            $values ["error"] = "Registration failed: Registrant must be from the European Union";
        }
        $data['registrant_countrycode'] = $RegistrantCountry;
    }

    // ADDED FOR .DE //

    if ($tld == 'de') {
        if ($params['additionalfields']['role'] == "ORG") {
            $data['registrant_role'] = $params['additionalfields']['role'];
            $data['admin_role'] = "Person";
            $data['technical_role'] = "Role";
            $data['zone_role'] = "Role";
        } else {
            $data['registrant_role'] = $params['additionalfields']['role'];
            $data['admin_role'] = "Person";
            $data['technical_role'] = "Person";
            $data['zone_role'] = "Person";
        }
        if ($params['additionalfields']['tosAgree'] != '') {
            $data['tosAgree'] = "YES";
        } else {
            $data['tosAgree'] = "NO";
        }
        $data['registrant_sip'] = @$params['additionalfields']['sip'];

        $data['clientip'] = ibs_getClientIp();
        if ($params['additionalfields']['Restricted Publication'] != '') {
            $data['registrant_discloseName'] = "YES";
            $data['registrant_discloseContact'] = "YES";
            $data['registrant_discloseAddress'] = "YES";
        } else {
            $data['registrant_discloseName'] = "NO";
            $data['registrant_discloseContact'] = "NO";
            $data['registrant_discloseAddress'] = "NO";
        }

        $data['zone_firstname'] = $AdminFirstName;
        $data['zone_lastname'] = $AdminLastName;
        $data['zone_email'] = $AdminEmailAddress;
        $data['zone_phonenumber'] = ibs_reformatPhone($params["phonenumber"], $params["country"]);
        $data['zone_postalcode'] = $AdminPostalCode;
        $data['zone_city'] = $AdminCity;
        $data['zone_street'] = $AdminAddress1;
        //$data['zone_countrycode'] = 'DE';
        //we should not explicity set admin country as DE
        $data['zone_countrycode'] = $AdminCountry;

        $data['technical_fax'] = @$params['additionalfields']['fax'];
        $data['zone_fax'] = @$params['additionalfields']['fax'];

        //removing state field for .de
        unset($data['registrant_state']);
        unset($data['admin_state']);
        unset($data['technical_state']);
        unset($data['billing_state']);
    }
    // END OF .DE //

    // ADDED FOR .NL //

    if ($tld == 'nl') {
        if ($params['additionalfields']['nlTerm'] != '') {
            $data['registrant_nlTerm'] = "YES";
        } else {
            $data['registrant_nlTerm'] = "NO";
        }
        $data['registrant_clientip'] = ibs_getClientIp();
        $data['registrant_nlLegalForm'] = $params['additionalfields']['nlLegalForm'];
        $data['registrant_nlRegNumber'] = $params['additionalfields']['nlRegNumber'];
        $data['technical_nlLegalForm'] = $params['additionalfields']['nlLegalForm'];
        $data['technical_nlRegNumber'] = $params['additionalfields']['nlRegNumber'];
        $data['admin_nlLegalForm'] = $params['additionalfields']['nlLegalForm'];
        $data['admin_nlRegNumber'] = $params['additionalfields']['nlRegNumber'];
        $data['billing_nlLegalForm'] = $params['additionalfields']['nlLegalForm'];
        $data['billing_nlRegNumber'] = $params['additionalfields']['nlRegNumber'];
    }
    //END OF .NL //

    if ($tld == 'us') {
        if (isset($params['additionalfields']['Application Purpose'])) {
            $usDomainPurpose = trim($params['additionalfields']['Application Purpose']);

            if (strtolower($usDomainPurpose) == strtolower('Business use for profit')) {
                $data['registrant_uspurpose'] = 'P1';
            } elseif (strtolower($usDomainPurpose) == strtolower('Educational purposes')) {
                $data['registrant_uspurpose'] = 'P4';
            } elseif (strtolower($usDomainPurpose) == strtolower('Personal Use')) {
                $data['registrant_uspurpose'] = 'P3';
            } elseif (strtolower($usDomainPurpose) == strtolower('Government purposes')) {
                $data['registrant_uspurpose'] = 'P5';
            } else {
                $data['registrant_uspurpose'] = 'P2';
            }
        } else {
            $data['registrant_uspurpose'] = $params['additionalfields']['uspurpose'];
        }
        if (isset($params['additionalfields']['Nexus Category'])) {
            $data['registrant_usnexuscategory'] = $params['additionalfields']['Nexus Category'];
        } else {
            $data['registrant_usnexuscategory'] = $params['additionalfields']['usnexuscategory'];
        }
        if (isset($params['additionalfields']['Nexus Country'])) {
            $data['registrant_usnexuscountry'] = $params['additionalfields']['Nexus Country'];
        } else {
            $data['registrant_usnexuscountry'] = $params['additionalfields']['usnexuscountry'];
        }
    }

    if ($ext == 'uk') {
        $legalType = $params ['additionalfields'] ['Legal Type'];
        $dotUKOrgType = $legalType;
        switch ($legalType) {
            case "Individual":
                $dotUKOrgType = "IND";
                break;
            case "UK Limited Company":
                $dotUKOrgType = "LTD";
                break;
            case "UK Public Limited Company":
                $dotUKOrgType = "PLC";
                break;
            case "UK Partnership":
                $dotUKOrgType = "PTNR";
                break;
            case "UK Limited Liability Partnership":
                $dotUKOrgType = "LLP";
                break;
            case "Sole Trader":
                $dotUKOrgType = "STRA";
                break;
            case "Industrial/Provident Registered Company":
                $dotUKOrgType = "IP";
                break;
            case "UK School":
                $dotUKOrgType = "SCH";
                break;
            case "Government Body":
                $dotUKOrgType = "GOV";
                break;
            case "Corporation By Royal Charter":
                $dotUKOrgType = "CRC";
                break;
            case "Uk Statutory Body":
                $dotUKOrgType = "STAT";
                break;
            case "UK Registered Charity":
                $dotUKOrgType = "RCHAR";
                break;
            case "UK Entity (other)":
                $dotUKOrgType = "OTHER";
                break;
            case "Non-UK Individual":
                $dotUKOrgType = "FIND";
                break;
            case "Non-Uk Corporation":
                $dotUKOrgType = "FCORP";
                break;
            case "Other foreign entity":
                $dotUKOrgType = "FOTHER";
                break;
        }

        if (in_array($dotUKOrgType, array('LTD', 'PLC', 'LLP', 'IP', 'SCH', 'RCHAR'))) {
            $data ['registrant_dotUkOrgNo'] = $params ['additionalfields'] ['Company ID Number'];
            $data ['registrant_dotUKRegistrationNumber'] = $params ['additionalfields'] ['Company ID Number'];
        }

        // organization type
        $data ['registrant_dotUKOrgType'] = isset($params ['additionalfields'] ['Legal Type']) ? $dotUKOrgType : 'IND';
        if ($data ['registrant_dotUKOrgType'] == 'IND') {
            // hide data in private whois? (Y/N)
            $data ['registrant_dotUKOptOut'] = 'N';
        }

        $data ['registrant_dotUKLocality'] = $AdminCountry;
    }

    if ($tld == 'asia') {
        if (!isset($params['additionalfields']['Locality'])) {
            $asianCountries = array("AF", "AQ", "AM", "AU", "AZ", "BH", "BD", "BT", "BN", "KH", "CN", "CX", "CC", "CK", "CY", "FJ", "GE", "HM", "HK", "IN", "ID", "IR", "IQ", "IL", "JP", "JO", "KZ", "KI", "KP", "KR", "KW", "KG", "LA", "LB", "MO", "MY", "MV", "MH", "FM", "MN", "MM", "NR", "NP", "NZ", "NU", "NF", "OM", "PK", "PW", "PS", "PG", "PH", "QA", "WS", "SA", "SG", "SB", "LK", "SY", "TW", "TJ", "TH", "TL", "TK", "TO", "TR", "TM", "TV", "AE", "UZ", "VU", "VN", "YE");
            if (!in_array($RegistrantCountry, $asianCountries)) {
                //$RegistrantCountry = 'BD';
                //cannot set country explicitly, let the registration fail
            }
            $data['registrant_countrycode'] = $RegistrantCountry;
            $data ['registrant_dotASIACedLocality'] = $data['registrant_countrycode'];
        } else {
            $data ['registrant_dotASIACedLocality'] = $params['additionalfields']['Locality'];
        }
        $data ['registrant_dotasiacedentity'] = $params ['additionalfields'] ['Legal Entity Type'];
        if ($data ['registrant_dotasiacedentity'] == 'other') {
            $data ['registrant_dotasiacedentityother'] = isset($params ['additionalfields'] ['Other legal entity type']) ? $params ['additionalfields'] ['Other legal entity type'] : 'otheridentity';
        }
        $data ['registrant_dotasiacedidform'] = $params ['additionalfields'] ['Identification Form'];
        if ($data ['registrant_dotasiacedidform'] != 'other') {
            $data ['registrant_dotASIACedIdNumber'] = $params ['additionalfields'] ['Identification Number'];
        }
        if ($data ['registrant_dotasiacedidform'] == 'other') {
            $data ['registrant_dotasiacedidformother'] = isset($params ['additionalfields'] ['Other identification form']) ? $params ['additionalfields'] ['Other identification form'] : 'otheridentity';
        }
    }

    if (in_array($ext, array('fr', 're', 'pm', 'tf', 'wf', 'yt'))) {
        $holderType = isset($params ['additionalfields'] ['Holder Type']) ? $params ['additionalfields'] ['Holder Type'] : 'individual';
        $data ['registrant_dotfrcontactentitytype'] = $holderType;
        $data ['admin_dotfrcontactentitytype'] = $holderType;

        switch ($holderType) {
            case 'individual':
                $data ["registrant_dotfrcontactentitybirthdate"] = $params ['additionalfields'] ['Birth Date YYYY-MM-DD'];
                $data ['registrant_dotfrcontactentitybirthplacecountrycode'] = $params ['additionalfields'] ['Birth Country Code'];
                $data ['admin_dotfrcontactentitybirthdate'] = $params ['additionalfields'] ['Birth Date YYYY-MM-DD'];
                $data ['admin_dotfrcontactentitybirthplacecountrycode'] = $params ['additionalfields'] ['Birth Country Code'];
                if (strtolower($params ['additionalfields'] ['Birth Country Code']) == 'fr') {
                    $data ['registrant_dotFRContactEntityBirthCity'] = $params ['additionalfields'] ['Birth City'];
                    $data ['registrant_dotFRContactEntityBirthPlacePostalCode'] = $params ['additionalfields'] ['Birth Postal code'];
                    $data ['admin_dotFRContactEntityBirthCity'] = $params ['additionalfields'] ['Birth City'];
                    $data ['admin_dotFRContactEntityBirthPlacePostalCode'] = $params ['additionalfields'] ['Birth Postal code'];
                }
                $data ['registrant_dotFRContactEntityRestrictedPublication'] = isset($params ['additionalfields'] ['Restricted Publication']) ? 1 : 0;
                $data ['admin_dotFRContactEntityRestrictedPublication'] = isset($params ['additionalfields'] ['Restricted Publication']) ? 1 : 0;
                break;
            case 'company':
                $data ['registrant_dotFRContactEntitySiren'] = trim($params ['additionalfields'] ['Siren']);
                $data ['admin_dotFRContactEntitySiren'] = trim($params ['additionalfields'] ['Siren']);
                break;
            case 'trademark':
                $data ['registrant_dotFRContactEntityTradeMark'] = $params ['additionalfields'] ['Trade Mark'];
                $data ['admin_dotFRContactEntityTradeMark'] = $params ['additionalfields'] ['Trade Mark'];
                break;
            case 'association':
                if (isset($params ['additionalfields'] ['Waldec']) && $params['additionalfields']['Waldec'] != "") {
                    $data ['registrant_dotFRContactEntityWaldec'] = $params ['additionalfields'] ['Waldec'];
                    $data ['admin_dotFRContactEntityWaldec'] = $params ['additionalfields'] ['Waldec'];
                } else {
                    $data ['registrant_dotfrcontactentitydateofassociation'] = $params ['additionalfields'] ['Date of Association YYYY-MM-DD'];
                    $data ['registrant_dotFRContactEntityDateOfPublication'] = $params ['additionalfields'] ['Date of Publication YYYY-MM-DD'];
                    $data ['registrant_dotfrcontactentityannounceno'] = $params ['additionalfields'] ['Annouce No'];
                    $data ['registrant_dotFRContactEntityPageNo'] = $params ['additionalfields'] ['Page No'];
                    $data ['admin_dotfrcontactentitydateofassociation'] = $params ['additionalfields'] ['Date of Association YYYY-MM-DD'];
                    $data ['admin_dotFRContactEntityDateOfPublication'] = $params ['additionalfields'] ['Date of Publication YYYY-MM-DD'];
                    $data ['admin_dotfrcontactentityannounceno'] = $params ['additionalfields'] ['Annouce No'];
                    $data ['admin_dotFRContactEntityPageNo'] = $params ['additionalfields'] ['Page No'];
                }
                break;
            case 'other':
                $data ['registrant_dotFROtherContactEntity'] = $params ['additionalfields'] ['Other Legal Status'];
                $data ['admin_dotFROtherContactEntity'] = $params ['additionalfields'] ['Other Legal Status'];
                if (isset($params ['additionalfields'] ['Siren'])) {
                    $data ['registrant_dotFRContactEntitySiren'] = $params ['additionalfields'] ['Siren'];
                    $data ['admin_dotFRContactEntitySiren'] = $params ['additionalfields'] ['Siren'];
                } elseif (isset($params ['additionalfields'] ['Trade Mark'])) {
                    $data ['registrant_dotFRContactEntityTradeMark'] = $params ['additionalfields'] ['Trade Mark'];
                    $data ['admin_dotFRContactEntityTradeMark'] = $params ['additionalfields'] ['Trade Mark'];
                }
                break;
        }
        $data ['registrant_dotFRContactEntitySiren'] = trim($params ['additionalfields'] ['Siren']);
        $data ['admin_dotFRContactEntitySiren'] = trim($params ['additionalfields'] ['Siren']);
        $data ['registrant_dotFRContactEntityVat'] = trim($params ['additionalfields'] ['VATNO']);
        $data ['admin_dotFRContactEntityVat'] = trim($params ['additionalfields'] ['VATNO']);
        $data ['registrant_dotFRContactEntityDuns'] = trim($params ['additionalfields'] ['DUNSNO']);
        $data ['admin_dotFRContactEntityDuns'] = trim($params ['additionalfields'] ['DUNSNO']);

        if ($holderType != 'individual') {
            $data ['registrant_dotFRContactEntityName'] = empty($RegistrantCompany) ? $RegistrantFirstName . ' ' . $RegistrantLastName : $RegistrantCompany;
            $data ['admin_dotFRContactEntityName'] = empty($AdminCompany) ? $AdminFirstName . ' ' . $AdminLastName : $AdminCompany;
        }
    }

    if ($tld == 'tel') {
        if (isset($params ['additionalfields']["telhostingaccount"])) {
            $TelHostingAccount = $params ['additionalfields']["telhostingaccount"];
        } else {
            $TelHostingAccount = md5($RegistrantLastName . $RegistrantFirstName . time() . rand(0, 99999));
        }
        if (isset($params ['additionalfields']["telhostingpassword"])) {
            $TelHostingPassword = $params ['additionalfields']["telhostingpassword"];
        } else {
            $TelHostingPassword = 'passwd' . rand(0, 99999);
        }

        $data['telHostingAccount'] = $TelHostingAccount;
        $data['telHostingPassword'] = $TelHostingPassword;
        if ($params['additionalfields']['telhidewhoisdata'] != '') {
            $data['telHideWhoisData'] = "YES";
        } else {
            $data['telHideWhoisData'] = "NO";
        }
    }

    if ($tld == 'it') {
        $EUCountries = array('AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SM', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB', 'VA');
        $EntityTypes = array('1. Italian and foreign natural persons' => 1, '2. Companies/one man companies' => 2, '3. Freelance workers/professionals' => 3, '4. non-profit organizations' => 4, '5. public organizations' => 5, '6. other subjects' => 6, '7. foreigners who match 2 - 6' => 7);
        $legalEntityType = $params['additionalfields']['Legal Entity Type'];
        $et = $EntityTypes[$legalEntityType];
        $data['registrant_dotitentitytype'] = $et;

        $isDotIdAdminAndRegistrantSame = (1 == $et);
        if (strlen($params['additionalfields']['Nationality']) > 2) {
            $nationality = ibs_getCountryCodeByName($params['additionalfields']['Nationality']);
        } else {
            $nationality = $params['additionalfields']['Nationality'];
        }
        if ($et >= 2 && $et <= 6) {
            $data['registrant_countrycode'] = $params['country'];
            $data['registrant_dotitnationality'] = $nationality;
        } elseif ($et == 7) {
            if (!in_array($data['registrant_countrycode'], $EUCountries)) {
                $values['error'] = "Registration failed. Registrant should be from EU.";
            }
            $data['registrant_dotitnationality'] = $data['registrant_countrycode'];
        } else {
            if (!in_array($nationality, $EUCountries) && !in_array($data['registrant_countrycode'], $EUCountries)) {
                //$nationality='IT';
                $values['error'] = "Registration failed. Registrant nationality or country of residence should be from EU.";
            }
            $data['registrant_dotitnationality'] = $nationality;
        }

        if (strtoupper($data['registrant_countrycode']) == 'IT') {
            // Extract province code from input value
            $data['registrant_dotitprovince'] = ibs_get2CharDotITProvinceCode($RegistrantStateProvince);
        } else {
            $data['registrant_dotitprovince'] = $RegistrantStateProvince;
        }
        if (strtoupper($data['admin_countrycode']) == 'IT') {
            $data['admin_dotitprovince'] = ibs_get2CharDotITProvinceCode($AdminStateProvince);
        } else {
            $data['admin_dotitprovince'] = $AdminStateProvince;
        }

        $data['technical_dotitprovince'] = $data['admin_dotitprovince'];

        $data['registrant_dotitregcode'] = $params['additionalfields']['VATTAXPassportIDNumber'];
        $data['registrant_dotithidewhois'] = ($params['additionalfields']['Hide data in public WHOIS'] == 'on' && $et == 1) ? 'YES' : 'NO';
        $data['admin_dotithidewhois'] = $data['registrant_dotithidewhois'];

        // Hide or not data in public whois
        if (!$isDotIdAdminAndRegistrantSame) {
            $data['admin_dotithidewhois'] = $hideWhoisData;
        }
        $data['technical_dotithidewhois'] = $hideWhoisData;
        $data['registrant_clientip'] = ibs_getClientIp();
        $data['registrant_dotitterm1'] = 'yes';
        $data['registrant_dotitterm2'] = 'yes';
        $data['registrant_dotitterm3'] = ($params['additionalfields']['Hide data in public WHOIS'] == 'on' && $et == 1) ? 'no' : 'yes';
        $data['registrant_dotitterm4'] = 'yes';
    }
    if ($tld == 'ro') {
        $data['registrant_identificationnumber'] = $params['additionalfields']['CNPFiscalCode'];

        $EUCountries = array('AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SM', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB', 'VA');
        if (in_array($data['registrant_countrycode'], $EUCountries)) {
            $data['registrant_vatnumber'] = $params['additionalfields']['Registration Number'];
        } else {
            $data['registrant_companynumber'] = $params['additionalfields']['Registration Number'];
        }
    }
    // period is optional
    if (isset($params ["regperiod"]) && $regperiod > 0) {
        $data ['period'] = $regperiod . "Y";
    }
    if (!$values ["error"]) {
        // create domain
        $result = ibs_runCommand($commandUrl, $data);
        $errorMessage = ibs_getLastError();

        # If error, return the error message in the value below
        if ($result === false) {
            $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
        } elseif ($result ['status'] == 'FAILURE') {
            $values ["error"] = $result ['message'];
        } else {
            $values ["success"] = true;
            //add here chaging date of next billing and next due date
        }

        if ($result ['product_0_status'] == 'FAILURE') {
            if (isset($values ["error"])) {
                $values ["error"] .= $result ['product_0_message'];
            } else {
                $values ["error"] = $result ['product_0_message'];
            }
        }
        if (($result ['status'] == 'FAILURE' || $result ['product_0_status'] == 'FAILURE') && (!isset($values ['error']) || empty($values ['error']))) {
            $values ['error'] = 'Error: cannot register domain';
        }
    }

    //There was an error registering the domain
    if ($values ['error']) {
        $data["password"] = "*****";
        $data['apikey'] = '*****';
        $subject = "$domainName registration error";
        $message = "There was an error registering the domain $domainName: " . $values ['error'] . "\n\n\n";
        $message .= "Request parameters: " . print_r($data, true) . "\n\n";
        $message .= "Response data: " . print_r($result, true) . "\n\n";
        ibs_billableOperationErrorHandler($params, $subject, $message);
    }
    return $values;
}


/**
 * This function is called when a domain release is requested (eg. UK IPSTag Changes)
 * @param $params
 */
function ibs_ReleaseDomain($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    # code to renew domain
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/ChangeTagDotUK';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName, 'newtag' => $params['transfertag']);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    }

    return $values;
}

/**
 * initiates transfer for a domain
 *
 * @param unknown_type $params
 * @return unknown
 */
function ibs_TransferDomain($params)
{
    $params = ibs_get_utf8_parameters($params);
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $hideWhoisData = (isset($params ["HideWhoisData"]) && ('on' == strtolower($params ["HideWhoisData"]))) ? 'YES' : 'NO';

    $tld = $params ["tld"];
    $sld = $params ["sld"];

    $transfersecret = $params ["transfersecret"];

    # Registrant Details
    $RegistrantFirstName = $params ["firstname"];
    $RegistrantLastName = $params ["lastname"];
    $RegistrantCompany = trim($params["companyname"]);
    $RegistrantAddress1 = $params ["address1"];
    $RegistrantAddress2 = $params ["address2"];
    $RegistrantCity = $params ["city"];
    $RegistrantStateProvince = $params ["state"];
    $RegistrantPostalCode = $params ["postcode"];
    $RegistrantCountry = $params ["country"];
    $RegistrantEmailAddress = $params ["email"];
    $RegistrantPhone = ibs_reformatPhone($params ["phonenumber"], $params ["country"]);
    # Admin Details
    $AdminFirstName = $params ["adminfirstname"];
    $AdminLastName = $params ["adminlastname"];
    $AdminAddress1 = $params ["adminaddress1"];
    $AdminAddress2 = $params ["adminaddress2"];
    $AdminCity = $params ["admincity"];
    $AdminCompany = $params["admincompanyname"];
    $AdminStateProvince = $params ["adminstate"];
    $AdminPostalCode = $params ["adminpostcode"];
    $AdminCountry = $params ["admincountry"];
    $AdminEmailAddress = $params ["adminemail"];
    $AdminPhone = ibs_reformatPhone($params ["adminphonenumber"], $params ["admincountry"]);

    # code to transfer domain
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }

    $nslist = array();
    for ($i = 1; $i <= 5; $i++) {
        if (isset($params["ns$i"])) {
            array_push($nslist, $params["ns$i"]);
        }
    }

    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Transfer/Initiate';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName, 'transferAuthInfo' => $transfersecret,

        // registrant contact data
        'registrant_firstname' => $RegistrantFirstName, 'registrant_lastname' => $RegistrantLastName, 'registrant_street' => $RegistrantAddress1, 'registrant_street2' => $RegistrantAddress2, 'registrant_city' => $RegistrantCity, 'registrant_state' => $RegistrantStateProvince, 'registrant_countrycode' => $RegistrantCountry, 'registrant_postalcode' => $RegistrantPostalCode, 'registrant_email' => $RegistrantEmailAddress, 'registrant_phonenumber' => $RegistrantPhone,

        // technical contact data
        'technical_firstname' => $AdminFirstName, 'technical_lastname' => $AdminLastName, 'technical_street' => $AdminAddress1, 'technical_street2' => $AdminAddress2, 'technical_city' => $AdminCity, 'technical_state' => $AdminStateProvince, 'technical_countrycode' => $AdminCountry, 'technical_postalcode' => $AdminPostalCode, 'technical_email' => $AdminEmailAddress, 'technical_phonenumber' => $AdminPhone,

        // admin contact data
        'admin_firstname' => $AdminFirstName, 'admin_lastname' => $AdminLastName, 'admin_street' => $AdminAddress1, 'admin_street2' => $AdminAddress2, 'admin_city' => $AdminCity, 'admin_state' => $AdminStateProvince, 'admin_countrycode' => $AdminCountry, 'admin_postalcode' => $AdminPostalCode, 'admin_email' => $AdminEmailAddress, 'admin_phonenumber' => $AdminPhone,

        // billing contact data
        'billing_firstname' => $AdminFirstName, 'billing_lastname' => $AdminLastName, 'billing_street' => $AdminAddress1, 'billing_street2' => $AdminAddress2, 'billing_city' => $AdminCity, 'billing_state' => $AdminStateProvince, 'billing_countrycode' => $AdminCountry, 'billing_postalcode' => $AdminPostalCode, 'billing_email' => $AdminEmailAddress, 'billing_phonenumber' => $AdminPhone);

    if (!empty($RegistrantCompany)) {
        $data["Registrant_Organization"] = $RegistrantCompany;
    }
    if (!empty($AdminCompany)) {
        $data["technical_Organization"] = $AdminCompany;
        $data["admin_Organization"] = $AdminCompany;
        $data["billing_Organization"] = $AdminCompany;
    }
    // ns_list is optional
    if (count($nslist)) {
        $data ['ns_list'] = implode(',', $nslist);
    }

    if ($tld == 'eu' || $tld == 'be' || $tld == 'uk') {
        $data ['registrant_language'] = isset($params ['Language']) ? $params ['Language'] : 'en';
    }


    // ADDED FOR .DE //
    if ($tld == 'de') {
        if (strtolower($params['RenewAfterTransfer']) == "on") {
            $data['RenewAfterTrasnfer'] = "Yes";
        }
        if ($params['additionalfields']['role'] == "ORG") {
            $data['registrant_role'] = $params['additionalfields']['role'];
            $data['admin_role'] = "Person";
            $data['technical_role'] = "Role";
            $data['zone_role'] = "Role";
        } else {
            $data['registrant_role'] = $params['additionalfields']['role'];
            $data['admin_role'] = "Person";
            $data['technical_role'] = "Person";
            $data['zone_role'] = "Person";
        }
        if ($params['additionalfields']['tosAgree'] != '') {
            $data['tosAgree'] = "YES";
        } else {
            $data['tosAgree'] = "NO";
        }
        $data['registrant_sip'] = @$params['additionalfields']['sip'];
        $data['clientip'] = ibs_getClientIp();
        if ($params['additionalfields']['Restricted Publication'] != '') {
            $data['registrant_discloseName'] = "YES";
            $data['registrant_discloseContact'] = "YES";
            $data['registrant_discloseAddress'] = "YES";
        } else {
            $data['registrant_discloseName'] = "NO";
            $data['registrant_discloseContact'] = "NO";
            $data['registrant_discloseAddress'] = "NO";
        }
        $data['zone_firstname'] = $AdminFirstName;
        $data['zone_lastname'] = $AdminLastName;
        $data['zone_email'] = $AdminEmailAddress;
        $data['zone_phonenumber'] = ibs_reformatPhone($params["phonenumber"], $params["country"]);
        $data['zone_postalcode'] = $AdminPostalCode;
        $data['zone_city'] = $AdminCity;
        $data['zone_street'] = $AdminAddress1;
        $data['zone_countrycode'] = $AdminCountry;

        unset($data['registrant_state']);
        unset($data['technical_state']);
        unset($data['admin_state']);
        unset($data['billing_state']);
    }
    // END OF .DE //

    // ADDED FOR .NL //

    if ($tld == 'nl') {
        if (strtolower($params['RenewAfterTransfer']) == "on") {
            $data['renewAfterTrasnfer'] = "Yes";
        }
        if ($params['additionalfields']['nlTerm'] != '') {
            $data['registrant_nlTerm'] = "YES";
        } else {
            $data['registrant_nlTerm'] = "NO";
        }
        $data['registrant_clientip'] = ibs_getClientIp();
        $data['registrant_nlLegalForm'] = $params['additionalfields']['nlLegalForm'];
        $data['registrant_nlRegNumber'] = $params['additionalfields']['nlRegNumber'];
    }
    //END OF .NL //

    if ($tld == 'us') {
        if (isset($params['additionalfields']['Application Purpose'])) {
            $usDomainPurpose = trim($params['additionalfields']['Application Purpose']);

            if (strtolower($usDomainPurpose) == strtolower('Business use for profit')) {
                $data['registrant_uspurpose'] = 'P1';
            } elseif (strtolower($usDomainPurpose) == strtolower('Educational purposes')) {
                $data['registrant_uspurpose'] = 'P4';
            } elseif (strtolower($usDomainPurpose) == strtolower('Personal Use')) {
                $data['registrant_uspurpose'] = 'P3';
            } elseif (strtolower($usDomainPurpose) == strtolower('Government purposes')) {
                $data['registrant_uspurpose'] = 'P5';
            } else {
                $data['registrant_uspurpose'] = 'P2';
            }
        } else {
            $data['registrant_uspurpose'] = $params['additionalfields']['uspurpose'];
        }
        if (isset($params['additionalfields']['Nexus Category'])) {
            $data['registrant_usnexuscategory'] = $params['additionalfields']['Nexus Category'];
        } else {
            $data['registrant_usnexuscategory'] = $params['additionalfields']['usnexuscategory'];
        }
        if (isset($params['additionalfields']['Nexus Country'])) {
            $data['registrant_usnexuscountry'] = $params['additionalfields']['Nexus Country'];
        } else {
            $data['registrant_usnexuscountry'] = $params['additionalfields']['usnexuscountry'];
        }
    }


    if ($tld == 'asia') {
        $data ['registrant_dotASIACedLocality'] = $AdminCountry;
        $data ['registrant_dotasiacedentity'] = $params['additionalfields']['Legal Entity Type'];
        if ($data ['registrant_dotasiacedentity'] == 'other') {
            $data ['registrant_dotasiacedentityother'] = isset($params['additionalfields'] ['Other legal entity type']) ? $params['additionalfields']['Other legal entity type'] : 'otheridentity';
        }
        $data ['registrant_dotasiacedidform'] = $params['additionalfields'] ['Identification Form'];
        if ($data ['registrant_dotasiacedidform'] != 'other') {
            $data ['registrant_dotASIACedIdNumber'] = $params['additionalfields'] ['Identification Number'];
        }
        if ($data ['registrant_dotasiacedidform'] == 'other') {
            $data ['registrant_dotasiacedidformother'] = isset($params['additionalfields'] ['Other identification form']) ? $params['additionalfields'] ['Other identification form'] : 'otheridentity';
        }
    }

    if (in_array($tld, array('fr', 're', 'pm', 'tf', 'wf', 'yt'))) {
        $holderType = isset($params ['additionalfields']['Holder Type']) ? $params['additionalfields']['Holder Type'] : 'individual';

        if ($tld == 'fr') {
            $holderType = isset($params ['additionalfields'] ['Holder Type']) ? $params ['additionalfields'] ['Holder Type'] : 'individual';
            //$data['admin_countrycode'] = 'FR';
            if ($data['admin_countrycode'] != 'FR') {
                $values['error'] = "Registration failed. Administrator should be from France.";
                return $values;
            }
        } elseif ($tld == 're') {
            $holderType = isset($params ['additionalfields'] ['Holder Type']) ? $params ['additionalfields'] ['Holder Type'] : 'other';
            //$data['registrant_countrycode'] = 'RE';
            if ($data['registrant_countrycode'] = 'RE') {
                $values['error'] = "Registration failed. Registrant should be from Reunion.";
                return $values;
            }
            $frenchTerritoryCountries = array("GP", "MQ", "GF", "RE", "FR", "PF", "MQ", "YT", "NC", "PM", "WF", "MF", "BL", "TF");
            if (!in_array($data['admin_countrycode'], $frenchTerritoryCountries)) {
                //$data['admin_countrycode']='RE';
                $values['error'] = "Registration failed. Administrator should be from Reunion.";
                return $values;
            }
        }

        $data ['registrant_dotfrcontactentitytype'] = $holderType;
        $data ['admin_dotfrcontactentitytype'] = $holderType;

        switch ($holderType) {
            case 'individual':
                $data ["registrant_dotfrcontactentitybirthdate"] = $params ['additionalfields'] ['Birth Date YYYY-MM-DD'];
                $data ['registrant_dotfrcontactentitybirthplacecountrycode'] = $params ['additionalfields']['Birth Country Code'];
                $data ['admin_dotfrcontactentitybirthdate'] = $params ['additionalfields'] ['Birth Date YYYY-MM-DD'];
                $data ['admin_dotfrcontactentitybirthplacecountrycode'] = $params ['additionalfields']['Birth Country Code'];
                $data ['registrant_dotFRContactEntityBirthCity'] = $params ['additionalfields']['Birth City'];
                $data ['registrant_dotFRContactEntityBirthPlacePostalCode'] = $params ['additionalfields']['Birth Postal code'];
                $data ['admin_dotFRContactEntityBirthCity'] = $params ['additionalfields']['Birth City'];
                $data ['admin_dotFRContactEntityBirthPlacePostalCode'] = $params ['additionalfields']['Birth Postal code'];

                $data ['registrant_dotFRContactEntityRestrictedPublication'] = isset($params ['additionalfields']['Restricted Publication']) ? 1 : 0;
                $data ['admin_dotFRContactEntityRestrictedPublication'] = isset($params ['additionalfields']['Restricted Publication']) ? 1 : 0;
                break;
            case 'company':
                $data ['registrant_dotFRContactEntitySiren'] = $params ['additionalfields']['Siren'];
                $data ['admin_dotFRContactEntitySiren'] = $params ['additionalfields']['Siren'];
                break;
            case 'trademark':
                $data ['registrant_dotFRContactEntityTradeMark'] = $params ['additionalfields']['Trade Mark'];
                $data ['admin_dotFRContactEntityTradeMark'] = $params ['additionalfields']['Trade Mark'];
                break;
            case 'association':
                if (isset($params ['Waldec'])) {
                    $data ['registrant_dotFRContactEntityWaldec'] = $params ['additionalfields']['Waldec'];
                    $data ['admin_dotFRContactEntityWaldec'] = $params ['additionalfields']['Waldec'];
                } else {
                    $data ['registrant_dotfrcontactentitydateofassociation'] = $params ['additionalfields']['Date of Association YYYY-MM-DD'];
                    $data ['registrant_dotFRContactEntityDateOfPublication'] = $params ['additionalfields']['Date of Publication YYYY-MM-DD'];
                    $data ['registrant_dotfrcontactentityannounceno'] = $params ['additionalfields']['Annouce No'];
                    $data ['registrant_dotFRContactEntityPageNo'] = $params ['additionalfields']['Page No'];
                    $data ['admin_dotfrcontactentitydateofassociation'] = $params ['additionalfields']['Date of Association YYYY-MM-DD'];
                    $data ['admin_dotFRContactEntityDateOfPublication'] = $params ['additionalfields']['Date of Publication YYYY-MM-DD'];
                    $data ['admin_dotfrcontactentityannounceno'] = $params ['additionalfields']['Annouce No'];
                    $data ['admin_dotFRContactEntityPageNo'] = $params ['additionalfields']['Page No'];
                }

                break;
            case 'other':
                $data ['registrant_dotFROtherContactEntity'] = $params ['additionalfields']['Other Legal Status'];
                $data ['admin_dotFROtherContactEntity'] = $params ['additionalfields']['Other Legal Status'];
                if (isset($params ['additionalfields']['Siren'])) {
                    $data ['registrant_dotFRContactEntitySiren'] = $params ['additionalfields']['Siren'];
                    $data ['admin_dotFRContactEntitySiren'] = $params ['additionalfields']['Siren'];
                } elseif (isset($params['additionalfields']['Trade Mark'])) {
                    $data ['registrant_dotFRContactEntityTradeMark'] = $params ['additionalfields']['Trade Mark'];
                    $data ['admin_dotFRContactEntityTradeMark'] = $params ['additionalfields']['Trade Mark'];
                }
                break;
        }
        $data ['registrant_dotFRContactEntitySiren'] = trim($params ['additionalfields'] ['Siren']);
        $data ['admin_dotFRContactEntitySiren'] = trim($params ['additionalfields'] ['Siren']);
        $data ['registrant_dotFRContactEntityVat'] = trim($params ['additionalfields'] ['VATNO']);
        $data ['admin_dotFRContactEntityVat'] = trim($params ['additionalfields'] ['VATNO']);
        $data ['registrant_dotFRContactEntityDuns'] = trim($params ['additionalfields'] ['DUNSNO']);
        $data ['admin_dotFRContactEntityDuns'] = trim($params ['additionalfields'] ['DUNSNO']);

        if ($holderType != 'individual') {
            $data ['registrant_dotFRContactEntityName'] = empty($RegistrantCompany) ? $RegistrantFirstName . ' ' . $RegistrantLastName : $RegistrantCompany;
            $data ['admin_dotFRContactEntityName'] = empty($AdminCompany) ? $AdminFirstName . ' ' . $AdminLastName : $AdminCompany;
        }
    }

    // Same as for .IT
    if ($tld == 'tel') {
        if (isset($params ['additionalfields']["telhostingaccount"])) {
            $TelHostingAccount = $params ['additionalfields']["telhostingaccount"];
        } else {
            $TelHostingAccount = md5($RegistrantLastName . $RegistrantFirstName . time() . rand(0, 99999));
        }
        if (isset($params ['additionalfields']["telhostingpassword"])) {
            $TelHostingPassword = $params ['additionalfields']["telhostingpassword"];
        } else {
            $TelHostingPassword = 'passwd' . rand(0, 99999);
        }

        $data['telHostingAccount'] = $TelHostingAccount;
        $data['telHostingPassword'] = $TelHostingPassword;
        if ($params['additionalfields']['telhidewhoisdata'] != '') {
            $data['telHideWhoisData'] = "YES";
        } else {
            $data['telHideWhoisData'] = "NO";
        }
        //$data['telHostingAccount'] = md5($RegistrantLastName.$RegistrantFirstName.time().rand(0,99999));
        //$data['telHostingPassword'] = 'passwd'.rand(0,99999);
    }
    // ADDED FOR .DE/.NL //

    if ($tld == 'de' || $tld == 'nl') {
        $data['registrant_clientip'] = ibs_getClientIp();
    }

    if ($tld == 'it') {
        $EUCountries = array('AT', 'BE', 'BG', 'CZ', 'CY', 'DE', 'DK', 'ES', 'EE', 'FI', 'FR', 'GR', 'GB', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SK', 'SI');
        $EntityTypes = array('1. Italian and foreign natural persons' => 1, '2. Companies/one man companies' => 2, '3. Freelance workers/professionals' => 3, '4. non-profit organizations' => 4, '5. public organizations' => 5, '6. other subjects' => 6, '7. foreigners who match 2 - 6' => 7);
        $legalEntityType = $params['additionalfields']['Legal Entity Type'];
        $et = $EntityTypes[$legalEntityType];
        $data['registrant_dotitentitytype'] = $et;
        $isDotIdAdminAndRegistrantSame = (1 == $et);
        $nationality = $data[$params['additionalfields']['Nationality']];
        if ($et >= 2 && $et <= 6) {
            //$data['registrant_dotitnationality']='IT';
            //$data['registrant_countrycode']='IT';
            //we cannot fource the country code to be IT
            $data['registrant_countrycode'] = $params['country'];
            $data['registrant_dotitnationality'] = $nationality;
        } elseif ($et == 7) {
            if (!in_array($data['registrant_countrycode'], $EUCountries)) {
                //$data['registrant_countrycode']='FR';
                $values['error'] = "Registration failed. Registrant should be from EU.";
                return $values;
            }
            $data['registrant_dotitnationality'] = $data['registrant_countrycode'];
        } else {
            $nationality = ibs_getCountryCodeByName($params['additionalfields']['Nationality']);
            if (!in_array($nationality, $EUCountries) && !in_array($data['registrant_countrycode'], $EUCountries)) {
                //$nationality='IT';
                $values['error'] = "Registration failed. Registrant country of residence of nationality should be from EU.";
                return $values;
            }
            $data['registrant_dotitnationality'] = $nationality;
        }

        if (strtoupper($data['registrant_countrycode']) == 'IT') {
            // Extract province code from input value
            $data['registrant_dotitprovince'] = ibs_get2CharDotITProvinceCode($RegistrantStateProvince);
        } else {
            $data['registrant_dotitprovince'] = $RegistrantStateProvince;
        }
        if (strtoupper($data['admin_countrycode']) == 'IT') {
            $data['admin_dotitprovince'] = ibs_get2CharDotITProvinceCode($AdminStateProvince);
        } else {
            $data['admin_dotitprovince'] = $AdminStateProvince;
        }
        $data['technical_dotitprovince'] = $data['admin_dotitprovince'];
        $data['registrant_dotitregcode'] = $params['additionalfields']['VATTAXPassportIDNumber'];
        $data['registrant_dotithidewhois'] = ($params['additionalfields']['Hide data in public WHOIS'] == 'on' && $et == 1) ? 'YES' : 'NO';
        $data['admin_dotithidewhois'] = $data['registrant_dotithidewhois'];

        // Hide or not data in public whois
        if (!$isDotIdAdminAndRegistrantSame) {
            $data['admin_dotithidewhois'] = $hideWhoisData;
        }
        $data['technical_dotithidewhois'] = $hideWhoisData;


        $data['registrant_clientip'] = ibs_getClientIp();
        $data['registrant_dotitterm1'] = 'yes';
        $data['registrant_dotitterm2'] = 'yes';
        $data['registrant_dotitterm3'] = ($params['additionalfields']['Hide data in public WHOIS'] == 'on' && $et == 1) ? 'no' : 'yes';
        $data['registrant_dotitterm4'] = 'yes';
    }
    if ($params ['idprotection']) {
        $data ["privateWhois"] = "FULL";
    }
    // create domain

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    }
    if ($result ['product_0_status'] == 'FAILURE') {
        if (isset($values ["error"])) {
            $values ["error"] .= $result ['product_0_message'];
        } else {
            $values ["error"] = $result ['product_0_message'];
        }
    }
    if (($result ['status'] == 'FAILURE' || $result ['product_0_status'] == 'FAILURE') && (!isset($values ['error']) || empty($values ['error']))) {
        $values ['error'] = 'Error: cannot start transfer domain';
    }
    //There was an error transferring the domain
    if ($values ['error']) {
        $data["password"] = "*****";
        $data['apikey'] = '*****';
        $subject = "$domainName transfer error";
        $message = "There was an error starting transfer for $domainName: " . $values ['error'] . "\n\n\n";
        $message .= "Request parameters: " . print_r($data, true) . "\n\n";
        $message .= "Response data: " . print_r($result, true) . "\n\n";
        ibs_billableOperationErrorHandler($params, $subject, $message);
    }

    return $values;
}

/**
 * renews a domain
 *
 * @param array $params
 * @return array
 */
function ibs_RenewDomain($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];
    $regperiod = (int)$params ["regperiod"];

    # code to renew domain
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }

    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Renew';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);

    // period is optional
    if (isset($params ['regperiod']) && $regperiod > 0) {
        $data ['period'] = $regperiod . 'Y';
    }

    $table = 'tbldomains';
    $fields = 'expirydate';
    $where = array("domain" => $domainName);
    $result = select_query($table, $fields, $where);
    $response = mysql_fetch_array($result);
    $expirydate = trim($response['expirydate']);
    // Normally we expect from mysql to get result like YYYY-MM-DD, if it's not then we try to autofix it
    if (!preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/is', $expirydate)) {
        $expirydate = date('Y-m-d', strtotime($expirydate));
    }

    $data['currentexpiration'] = $expirydate;
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    }
    //There was an error renewing the domain
    if ($values ['error']) {
        $data["password"] = "*****";
        $data['apikey'] = '*****';
        $subject = "$domainName renewal error";
        $message = "There was an error renewing the domain $domainName: " . $values ['error'] . "\n\n\n";
        $message .= "Request parameters: " . print_r($data, true) . "\n\n";
        $message .= "Response data: " . print_r($result, true) . "\n\n";
        ibs_billableOperationErrorHandler($params, $subject, $message);
    }
    return $values;
}

/**
 * gets contact details for a domain
 *
 * @param array $params
 * @return array
 */
function ibs_GetContactDetails($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    # code to get WHOIS data
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Info';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    } else {
        # Data should be returned in an array as follows
        $values ["Registrant"] ["First Name"] = $result ['contacts_registrant_firstname'];
        $values ["Registrant"] ["Last Name"] = $result ['contacts_registrant_lastname'];
        $values ["Registrant"] ["Company"] = $result ['contacts_registrant_organization'];
        $values ["Registrant"] ["Email"] = $result ['contacts_registrant_email'];
        $values ["Registrant"] ["Phone Number"] = $result ['contacts_registrant_phonenumber'];
        $values ["Registrant"] ["Address1"] = $result ['contacts_registrant_street'];
        $values ["Registrant"] ["Address2"] = $result ['contacts_registrant_street1'];
        $values ["Registrant"] ["State"] = $result ['contacts_registrant_state'];
        $values ["Registrant"] ["Postcode"] = $result ['contacts_registrant_postalcode'];
        $values ["Registrant"] ["City"] = $result ['contacts_registrant_city'];
        $values ["Registrant"] ["Country"] = $result ['contacts_registrant_country'];
        $values ["Registrant"] ["Country Code"] = $result ['contacts_registrant_countrycode'];

        $values ["Admin"] ["First Name"] = $result ['contacts_admin_firstname'];
        $values ["Admin"] ["Last Name"] = $result ['contacts_admin_lastname'];
        $values ["Admin"] ["Company"] = $result ['contacts_admin_organization'];
        $values ["Admin"] ["Email"] = $result ['contacts_admin_email'];
        $values ["Admin"] ["Phone Number"] = $result ['contacts_admin_phonenumber'];
        $values ["Admin"] ["Address1"] = $result ['contacts_admin_street'];
        $values ["Admin"] ["Address2"] = $result ['contacts_admin_street1'];
        $values ["Admin"] ["State"] = $result ['contacts_admin_state'];
        $values ["Admin"] ["Postcode"] = $result ['contacts_admin_postalcode'];
        $values ["Admin"] ["City"] = $result ['contacts_admin_city'];
        $values ["Admin"] ["Country"] = $result ['contacts_admin_country'];
        $values ["Admin"] ["Country Code"] = $result ['contacts_admin_countrycode'];

        if (isset($result ['contacts_technical_email'])) {
            $values ["Tech"] ["First Name"] = $result ['contacts_technical_firstname'];
            $values ["Tech"] ["Last Name"] = $result ['contacts_technical_lastname'];
            $values ["Tech"] ["Company"] = $result ['contacts_technical_organization'];
            $values ["Tech"] ["Email"] = $result ['contacts_technical_email'];
            $values ["Tech"] ["Phone Number"] = $result ['contacts_technical_phonenumber'];
            $values ["Tech"] ["Address1"] = $result ['contacts_technical_street'];
            $values ["Tech"] ["Address2"] = $result ['contacts_technical_street1'];
            $values ["Tech"] ["State"] = $result ['contacts_technical_state'];
            $values ["Tech"] ["Postcode"] = $result ['contacts_technical_postalcode'];
            $values ["Tech"] ["City"] = $result ['contacts_technical_city'];
            $values ["Tech"] ["Country"] = $result ['contacts_technical_country'];
            $values ["Tech"] ["Country Code"] = $result ['contacts_technical_countrycode'];
        }
    }

    return $values;
}

/**
 * saves contact details
 *
 * @param array $params
 * @return array
 */
function ibs_SaveContactDetails($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];
    # Data is returned as specified in the GetContactDetails() function
    $firstname = $params ["contactdetails"] ["Registrant"] ["First Name"];
    $lastname = $params ["contactdetails"] ["Registrant"] ["Last Name"];
    $company = $params ["contactdetails"] ["Registrant"] ["Company"];
    $email = $params ["contactdetails"] ["Registrant"] ["Email"];
    $address1 = $params ["contactdetails"] ["Registrant"] ["Address1"];
    if (!$address1) {
        $address1 = $params ["contactdetails"] ["Registrant"] ["Address 1"];
    }
    $address2 = $params ["contactdetails"] ["Registrant"] ["Address2"];
    if (!$address2) {
        $address2 = $params ["contactdetails"] ["Registrant"] ["Address 2"];
    }
    $state = $params ["contactdetails"] ["Registrant"] ["State"];
    $postalcode = $params ["contactdetails"] ["Registrant"] ["Postcode"];
    $city = $params ["contactdetails"] ["Registrant"] ["City"];
    $country = $params ["contactdetails"] ["Registrant"] ["Country"];
    $countrycode = $params ["contactdetails"] ["Registrant"] ["Country Code"];
    if (!$countrycode) {
        if (strlen($country) == 2) {
            $countrycode = $country;
        } else {
            $countrycode = ibs_getCountryCodeByName(strtoupper($country));
        }
    }
    $phonenumber = ibs_reformatPhone($params ["contactdetails"] ["Registrant"] ["Phone Number"], $countrycode);

    $adminfirstname = $params ["contactdetails"] ["Admin"] ["First Name"];
    $adminlastname = $params ["contactdetails"] ["Admin"] ["Last Name"];
    $adminCompany = $params ["contactdetails"] ["Admin"] ["Company"];
    $adminemail = $params ["contactdetails"] ["Admin"] ["Email"];
    $adminaddress1 = $params ["contactdetails"] ["Admin"] ["Address1"];
    if (!$adminaddress1) {
        $adminaddress1 = $params ["contactdetails"] ["Admin"] ["Address 1"];
    }
    $adminaddress2 = $params ["contactdetails"] ["Admin"] ["Address2"];
    if (!$adminaddress2) {
        $adminaddress2 = $params ["contactdetails"] ["Admin"] ["Address 2"];
    }
    $adminstate = $params ["contactdetails"] ["Admin"] ["State"];
    $adminpostalcode = $params ["contactdetails"] ["Admin"] ["Postcode"];
    $admincity = $params ["contactdetails"] ["Admin"] ["City"];
    $admincountry = $params ["contactdetails"] ["Admin"] ["Country"];
    $admincountrycode = $params ["contactdetails"] ["Admin"] ["Country Code"];
    if (!$admincountrycode) {
        if (strlen($admincountry) == 2) {
            $admincountrycode = $admincountry;
        } else {
            $admincountrycode = ibs_getCountryCodeByName(strtoupper($admincountry));
        }
    }
    $adminphonenumber = ibs_reformatPhone($params ["contactdetails"] ["Admin"] ["Phone Number"], $admincountrycode);

    $techfirstname = $params ["contactdetails"] ["Tech"] ["First Name"];
    $techlastname = $params ["contactdetails"] ["Tech"] ["Last Name"];
    $techCompany = $params ["contactdetails"] ["Tech"] ["Company"];
    $techemail = $params ["contactdetails"] ["Tech"] ["Email"];
    $techaddress1 = $params ["contactdetails"] ["Tech"] ["Address1"];
    if (!$techaddress1) {
        $techaddress1 = $params ["contactdetails"] ["Tech"] ["Address 1"];
    }
    $techaddress2 = $params ["contactdetails"] ["Tech"] ["Address2"];
    if (!$techaddress2) {
        $techaddress2 = $params ["contactdetails"] ["Tech"] ["Address 2"];
    }
    $techstate = $params ["contactdetails"] ["Tech"] ["State"];
    $techpostalcode = $params ["contactdetails"] ["Tech"] ["Postcode"];
    $techcity = $params ["contactdetails"] ["Tech"] ["City"];
    $techcountry = $params ["contactdetails"] ["Tech"] ["Country"];
    $techcountrycode = $params ["contactdetails"] ["Tech"] ["Country Code"];
    if (!$techcountrycode) {
        if (strlen($techcountry) == 2) {
            $techcountrycode = $techcountry;
        } else {
            $techcountrycode = ibs_getCountryCodeByName(strtoupper($techcountry));
        }
    }
    $techphonenumber = ibs_reformatPhone($params ["contactdetails"] ["Tech"] ["Phone Number"], $techcountrycode);

    # Put your code to save new WHOIS data here


    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Update';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName,

        // registrant contact data
        'registrant_firstname' => $firstname, 'registrant_lastname' => $lastname, 'registrant_organization' => $company, 'registrant_street' => $address1, 'registrant_street2' => $address2, 'registrant_city' => $city, 'registrant_state' => $state, 'registrant_countrycode' => $countrycode, 'registrant_postalcode' => $postalcode, 'registrant_email' => $email, 'registrant_phonenumber' => $phonenumber,

        // technical contact data
        'technical_firstname' => $techfirstname, 'technical_lastname' => $techlastname, 'technical_organization' => $techCompany, 'technical_street' => $techaddress1, 'technical_street2' => $techaddress2, 'technical_city' => $techcity, 'technical_state' => $techstate, 'technical_countrycode' => $techcountrycode, 'technical_postalcode' => $techpostalcode, 'technical_email' => $techemail, 'technical_phonenumber' => $techphonenumber,

        // admin contact data
        'admin_firstname' => $adminfirstname, 'admin_lastname' => $adminlastname, 'admin_organization' => $adminCompany, 'admin_street' => $adminaddress1, 'admin_street2' => $adminaddress2, 'admin_city' => $admincity, 'admin_state' => $adminstate, 'admin_countrycode' => $admincountrycode, 'admin_postalcode' => $adminpostalcode, 'admin_email' => $adminemail, 'admin_phonenumber' => $adminphonenumber,

        // billing contact data
        'billing_firstname' => $adminfirstname, 'billing_lastname' => $adminlastname, 'billing_organization' => $adminCompany, 'billing_street' => $adminaddress1, 'billing_street2' => $adminaddress2, 'billing_city' => $admincity, 'billing_state' => $adminstate, 'billing_countrycode' => $admincountrycode, 'billing_postalcode' => $adminpostalcode, 'billing_email' => $adminemail, 'billing_phonenumber' => $adminphonenumber);

    $extarr = explode('.', $tld);
    $ext = array_pop($extarr);


    // Unset params which is not possible update for domain
    if ('it' == $ext) {
        $data['registrant_clientip'] = ibs_getClientIp();
        $data['registrant_dotitterm1'] = 'yes';
        $data['registrant_dotitterm2'] = 'yes';
        $data['registrant_dotitterm3'] = $params['additionalfields']['Hide data in public WHOIS'] == 'on' ? 'no' : 'yes';
        $data['registrant_dotitterm4'] = 'yes';
        unset($data['registrant_countrycode']);
        unset($data['registrant_organization']);
        unset($data['registrant_countrycode']);
        unset($data['registrant_country']);
        unset($data['registrant_dotitentitytype']);
        unset($data['registrant_dotitnationality']);
        unset($data['registrant_dotitregcode']);
    }

    if ($ext == 'eu' || $ext == 'be') {
        if (!strlen(trim($data['registrant_organization']))) {
            unset($data['registrant_firstname']);
            unset($data['registrant_lastname']);
        }
        unset($data['registrant_organization']);
    }

    if ($ext == "co.uk" || $ext == "org.uk" || $ext == "me.uk" || $ext == 'uk') {
        unset($data['registrant_firstname']);
        unset($data['registrant_lastname']);
    }

    if ($ext == "fr" || $ext == "re" || $ext == "tf" || $ext == "pm" || $ext == "yt" || $ext == "wf") {
        unset($data['registrant_firstname']);
        unset($data['registrant_lastname']);
        unset($data['registrant_countrycode']);
        unset($data['registrant_countrycode']);

        if (!strlen(trim($data['admin_dotfrcontactentitysiren']))) {
            unset($data['admin_dotfrcontactentitysiren']);
        }

        if (trim(strtolower($data['admin_dotfrcontactentitytype'])) == 'individual') {
            unset($data['admin_countrycode']);
        }
    }

    if ($ext == "de") {
        unset($data['registrant_state']);
        unset($data['admin_state']);
        unset($data['technical_state']);
        unset($data['billing_state']);
        $data['zone_firstname'] = $adminfirstname;
        $data['zone_lastname'] = $adminlastname;
        $data['zone_email'] = $adminemail;
        $data['zone_phonenumber'] = $adminphonenumber;
        $data['zone_postalcode'] = $adminpostalcode;
        $data['zone_city'] = $admincity;
        $data['zone_street'] = $adminaddress1;
        //$data['zone_countrycode'] = 'DE';
        //we should not explicity set admin country as DE
        $data['zone_countrycode'] = $admincountrycode;
        $data['tosagree'] = "Yes";
    }
    if ($ext == "nl") {
        $data['registrant_clientip'] = ibs_getClientIp();
        $data['registrant_nlterm'] = "Yes";
    }
    $data['clientip'] = ibs_getClientIp();
    $data['registrant_clientip'] = ibs_getClientIp();
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    }
    return $values;
}

/**
 * gets domain secret/ transfer auth info of a domain
 *
 * @param array $params
 * @return array
 */
function ibs_GetEPPCode($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    # code to request the EPP code - if the API returns it, pass back as below - otherwise return no value and it will assume code is emailed


    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Info';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();

    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    } else {
        $values ["eppcode"] = $result ['transferauthinfo'];
    }

    return $values;
}

/**
 * creates a host for a domain
 *
 * @param array $params
 * @return array
 */
function ibs_RegisterNameserver($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    if (!isset($params["original"]["nameserver"])) {
        $nameserver = $params ["nameserver"];
    } else {
        $nameserver = $params["original"]["nameserver"];
    }
    $ipaddress = $params ["ipaddress"];

    # code to register the nameserver
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }

    if (($nameserver != $domainName) && strpos($nameserver, '.' . $domainName) === false) {
        $nameserver = $nameserver . '.' . $domainName;
    }

    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Host/Create';

    $data = array('apikey' => $username, 'password' => $password, 'host' => $nameserver, 'ip_list' => $ipaddress);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        if (isset($result["message"])) {
            $values ["error"] = $result ['message'];
        } else {
            $values["error"] = "Due to some technical issue nameserver cannot be registered.";
        }
    }
    return $values;
}

/**
 * updates host of a domain
 *
 * @param array $params
 * @return array
 */
function ibs_ModifyNameserver($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    if (!isset($params["original"]["nameserver"])) {
        $nameserver = $params ["nameserver"];
    } else {
        $nameserver = $params["original"]["nameserver"];
    }
    $currentipaddress = $params ["currentipaddress"];
    $newipaddress = $params ["newipaddress"];

    # code to update the nameserver
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    if (($nameserver != $domainName) && strpos($nameserver, '.' . $domainName) === false) {
        $nameserver = $nameserver . '.' . $domainName;
    }

    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Host/Update';

    $data = array('apikey' => $username, 'password' => $password, 'host' => $nameserver, 'ip_list' => $newipaddress);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    }

    return $values;
}

/**
 * deletes a host
 *
 * @param array $params
 * @return array
 */
function ibs_DeleteNameserver($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    if (!isset($params["original"]["nameserver"])) {
        $nameserver = $params ["nameserver"];
    } else {
        $nameserver = $params["original"]["nameserver"];
    }

    # code to delete the nameserver
    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    if (($nameserver != $domainName) && strpos($nameserver, '.' . $domainName) === false) {
        $nameserver = $nameserver . '.' . $domainName;
    }

    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Host/Delete';

    $data = array('apikey' => $username, 'password' => $password, 'host' => $nameserver);

    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    }

    return $values;
}

function ibs_mapCountry($countryCode)
{

    $mapc = array('US' => 1, 'CA' => 1, 'AI' => 1, 'AG' => 1, 'BB' => 1, 'BS' => 1, 'VG' => 1, 'VI' => 1, 'KY' => 1, 'BM' => 1, 'GD' => 1, 'TC' => 1, 'MS' => 1, 'MP' => 1, 'GU' => 1, 'LC' => 1, 'DM' => 1, 'VC' => 1, 'PR' => 1, 'DO' => 1, 'TT' => 1, 'KN' => 1, 'JM' => 1, 'EG' => 20, 'MA' => 212, 'DZ' => 213, 'TN' => 216, 'LY' => 218, 'GM' => 220, 'SN' => 221, 'MR' => 222, 'ML' => 223, 'GN' => 224, 'CI' => 225, 'BF' => 226, 'NE' => 227, 'TG' => 228, 'BJ' => 229, 'MU' => 230, 'LR' => 231, 'SL' => 232, 'GH' => 233, 'NG' => 234, 'TD' => 235, 'CF' => 236, 'CM' => 237, 'CV' => 238, 'ST' => 239, 'GQ' => 240, 'GA' => 241, 'CG' => 242, 'CD' => 243, 'AO' => 244, 'GW' => 245, 'IO' => 246, 'AC' => 247, 'SC' => 248, 'SD' => 249, 'RW' => 250, 'ET' => 251, 'SO' => 252, 'DJ' => 253, 'KE' => 254, 'TZ' => 255, 'UG' => 256, 'BI' => 257, 'MZ' => 258, 'ZM' => 260, 'MG' => 261, 'RE' => 262, 'ZW' => 263, 'NA' => 264, 'MW' => 265, 'LS' => 266, 'BW' => 267, 'SZ' => 268, 'KM' => 269, 'YT' => 269, 'ZA' => 27, 'SH' => 290, 'ER' => 291, 'AW' => 297, 'FO' => 298, 'GL' => 299, 'GR' => 30, 'NL' => 31, 'BE' => 32, 'FR' => 33, 'ES' => 34, 'GI' => 350, 'PT' => 351, 'LU' => 352, 'IE' => 353, 'IS' => 354, 'AL' => 355, 'MT' => 356, 'CY' => 357, 'FI' => 358, 'BG' => 359, 'HU' => 36, 'LT' => 370, 'LV' => 371, 'EE' => 372, 'MD' => 373, 'AM' => 374, 'BY' => 375, 'AD' => 376, 'MC' => 377, 'SM' => 378, 'VA' => 379, 'UA' => 380, 'CS' => 381, 'YU' => 381, 'HR' => 385, 'SI' => 386, 'BA' => 387, 'EU' => 388, 'MK' => 389, 'IT' => 39, 'RO' => 40, 'CH' => 41, 'CZ' => 420, 'SK' => 421, 'LI' => 423, 'AT' => 43, 'GB' => 44, 'DK' => 45, 'SE' => 46, 'NO' => 47, 'PL' => 48, 'DE' => 49, 'FK' => 500, 'BZ' => 501, 'GT' => 502, 'SV' => 503, 'HN' => 504, 'NI' => 505, 'CR' => 506, 'PA' => 507, 'PM' => 508, 'HT' => 509, 'PE' => 51, 'MX' => 52, 'CU' => 53, 'AR' => 54, 'BR' => 55, 'CL' => 56, 'CO' => 57, 'VE' => 58, 'GP' => 590, 'BO' => 591, 'GY' => 592, 'EC' => 593, 'GF' => 594, 'PY' => 595, 'MQ' => 596, 'SR' => 597, 'UY' => 598, 'AN' => 599, 'MY' => 60, 'AU' => 61, 'CC' => 61, 'CX' => 61, 'ID' => 62, 'PH' => 63, 'NZ' => 64, 'SG' => 65, 'TH' => 66, 'TL' => 670, 'AQ' => 672, 'NF' => 672, 'BN' => 673, 'NR' => 674, 'PG' => 675, 'TO' => 676, 'SB' => 677, 'VU' => 678, 'FJ' => 679, 'PW' => 680, 'WF' => 681, 'CK' => 682, 'NU' => 683, 'AS' => 684, 'WS' => 685, 'KI' => 686, 'NC' => 687, 'TV' => 688, 'PF' => 689, 'TK' => 690, 'FM' => 691, 'MH' => 692, 'RU' => 7, 'KZ' => 7, 'XF' => 800, 'XC' => 808, 'JP' => 81, 'KR' => 82, 'VN' => 84, 'KP' => 850, 'HK' => 852, 'MO' => 853, 'KH' => 855, 'LA' => 856, 'CN' => 86, 'XS' => 870, 'XE' => 871, 'XP' => 872, 'XI' => 873, 'XW' => 874, 'XU' => 878, 'BD' => 880, 'XG' => 881, 'XN' => 882, 'TW' => 886, 'TR' => 90, 'IN' => 91, 'PK' => 92, 'AF' => 93, 'LK' => 94, 'MM' => 95, 'MV' => 960, 'LB' => 961, 'JO' => 962, 'SY' => 963, 'IQ' => 964, 'KW' => 965, 'SA' => 966, 'YE' => 967, 'OM' => 968, 'PS' => 970, 'AE' => 971, 'IL' => 972, 'BH' => 973, 'QA' => 974, 'BT' => 975, 'MN' => 976, 'NP' => 977, 'XR' => 979, 'IR' => 98, 'XT' => 991, 'TJ' => 992, 'TM' => 993, 'AZ' => 994, 'GE' => 995, 'KG' => 996, 'UZ' => 998);

    if (isset($mapc [$countryCode])) {
        return ($mapc [$countryCode]);
    } else {
        return (1);
    }
}

function ibs_mapCountryCode($countryCode)
{
    $mapc = array('US' => 1, 'CA' => 1, 'AI' => 1, 'AG' => 1, 'BB' => 1, 'BS' => 1, 'VG' => 1, 'VI' => 1, 'KY' => 1, 'BM' => 1, 'GD' => 1, 'TC' => 1, 'MS' => 1, 'MP' => 1, 'GU' => 1, 'LC' => 1, 'DM' => 1, 'VC' => 1, 'PR' => 1, 'DO' => 1, 'TT' => 1, 'KN' => 1, 'JM' => 1, 'EG' => 20, 'MA' => 212, 'DZ' => 213, 'TN' => 216, 'LY' => 218, 'GM' => 220, 'SN' => 221, 'MR' => 222, 'ML' => 223, 'GN' => 224, 'CI' => 225, 'BF' => 226, 'NE' => 227, 'TG' => 228, 'BJ' => 229, 'MU' => 230, 'LR' => 231, 'SL' => 232, 'GH' => 233, 'NG' => 234, 'TD' => 235, 'CF' => 236, 'CM' => 237, 'CV' => 238, 'ST' => 239, 'GQ' => 240, 'GA' => 241, 'CG' => 242, 'CD' => 243, 'AO' => 244, 'GW' => 245, 'IO' => 246, 'AC' => 247, 'SC' => 248, 'SD' => 249, 'RW' => 250, 'ET' => 251, 'SO' => 252, 'DJ' => 253, 'KE' => 254, 'TZ' => 255, 'UG' => 256, 'BI' => 257, 'MZ' => 258, 'ZM' => 260, 'MG' => 261, 'RE' => 262, 'ZW' => 263, 'NA' => 264, 'MW' => 265, 'LS' => 266, 'BW' => 267, 'SZ' => 268, 'KM' => 269, 'YT' => 269, 'ZA' => 27, 'SH' => 290, 'ER' => 291, 'AW' => 297, 'FO' => 298, 'GL' => 299, 'GR' => 30, 'NL' => 31, 'BE' => 32, 'FR' => 33, 'ES' => 34, 'GI' => 350, 'PT' => 351, 'LU' => 352, 'IE' => 353, 'IS' => 354, 'AL' => 355, 'MT' => 356, 'CY' => 357, 'FI' => 358, 'BG' => 359, 'HU' => 36, 'LT' => 370, 'LV' => 371, 'EE' => 372, 'MD' => 373, 'AM' => 374, 'BY' => 375, 'AD' => 376, 'MC' => 377, 'SM' => 378, 'VA' => 379, 'UA' => 380, 'CS' => 381, 'YU' => 381, 'HR' => 385, 'SI' => 386, 'BA' => 387, 'EU' => 388, 'MK' => 389, 'IT' => 39, 'RO' => 40, 'CH' => 41, 'CZ' => 420, 'SK' => 421, 'LI' => 423, 'AT' => 43, 'GB' => 44, 'DK' => 45, 'SE' => 46, 'NO' => 47, 'PL' => 48, 'DE' => 49, 'FK' => 500, 'BZ' => 501, 'GT' => 502, 'SV' => 503, 'HN' => 504, 'NI' => 505, 'CR' => 506, 'PA' => 507, 'PM' => 508, 'HT' => 509, 'PE' => 51, 'MX' => 52, 'CU' => 53, 'AR' => 54, 'BR' => 55, 'CL' => 56, 'CO' => 57, 'VE' => 58, 'GP' => 590, 'BO' => 591, 'GY' => 592, 'EC' => 593, 'GF' => 594, 'PY' => 595, 'MQ' => 596, 'SR' => 597, 'UY' => 598, 'AN' => 599, 'MY' => 60, 'AU' => 61, 'CC' => 61, 'CX' => 61, 'ID' => 62, 'PH' => 63, 'NZ' => 64, 'SG' => 65, 'TH' => 66, 'TL' => 670, 'AQ' => 672, 'NF' => 672, 'BN' => 673, 'NR' => 674, 'PG' => 675, 'TO' => 676, 'SB' => 677, 'VU' => 678, 'FJ' => 679, 'PW' => 680, 'WF' => 681, 'CK' => 682, 'NU' => 683, 'AS' => 684, 'WS' => 685, 'KI' => 686, 'NC' => 687, 'TV' => 688, 'PF' => 689, 'TK' => 690, 'FM' => 691, 'MH' => 692, 'RU' => 7, 'KZ' => 7, 'XF' => 800, 'XC' => 808, 'JP' => 81, 'KR' => 82, 'VN' => 84, 'KP' => 850, 'HK' => 852, 'MO' => 853, 'KH' => 855, 'LA' => 856, 'CN' => 86, 'XS' => 870, 'XE' => 871, 'XP' => 872, 'XI' => 873, 'XW' => 874, 'XU' => 878, 'BD' => 880, 'XG' => 881, 'XN' => 882, 'TW' => 886, 'TR' => 90, 'IN' => 91, 'PK' => 92, 'AF' => 93, 'LK' => 94, 'MM' => 95, 'MV' => 960, 'LB' => 961, 'JO' => 962, 'SY' => 963, 'IQ' => 964, 'KW' => 965, 'SA' => 966, 'YE' => 967, 'OM' => 968, 'PS' => 970, 'AE' => 971, 'IL' => 972, 'BH' => 973, 'QA' => 974, 'BT' => 975, 'MN' => 976, 'NP' => 977, 'XR' => 979, 'IR' => 98, 'XT' => 991, 'TJ' => 992, 'TM' => 993, 'AZ' => 994, 'GE' => 995, 'KG' => 996, 'UZ' => 998);

    if (in_array($countryCode, $mapc)) {
        return true;
    } else {
        return false;
    }
}

function ibs_chekPhone($phoneNumber)
{
    $phoneNumber = str_replace(" ", "", $phoneNumber);
    $phoneNumber = str_replace("\t", "", $phoneNumber);

    return (bool)preg_match('/^\+[0-9]{1,4}\.[0-9 ]+$/is', $phoneNumber);
}

function ibs_reformatPhone($phoneNumber, $countryCode)
{
//check if phoneNumber has more than 10 characters, get last 10 characters and use characters before it as country code.
    /*  if(strlen($phoneNumber) > 10 && count(explode('.',$phoneNumber)) <= 1){
        $inputPhone = substr($phoneNumber, 0, strlen($phoneNumber)-10);
        $icountryCode = $inputPhone;
        //*If country code exist, use it otherwise return as it is
        if(ibs_mapCountryCode($icountryCode)){
            $phoneNumber = substr($phoneNumber, strlen($phoneNumber) - 10);
            $phoneNumber = '+' . $icountryCode.".".$phoneNumber;
        }
    }
*/
    $countryPhoneCode = ibs_mapCountry($countryCode);
    $plus = 0;
    $country = "";

    $scontrol = trim($phoneNumber);
    $l = strlen($scontrol);
    /* check if first character is + */
    if ($scontrol[0] == '+') {
        $plus = true;
        $phoneExplode = explode(".", $scontrol);
        if (count($phoneExplode) > 1) {
            /* IF country code is added in phone numnber*/
            $countryPhoneCode = ltrim($phoneExplode[0], "+");
            $scontrol = $phoneExplode[1];
            $plus = 0;
        }
    }

    /* Remove non-digit character from string */
    $scontrol = preg_replace('#\D*#si', "", $scontrol);

    /*if original phone number has + sign, add it again*/
    if ($plus) {
        $scontrol = "+" . $scontrol;
    }

    /* If empty phone number, return as it is*/
    if (!$l) {
        return $phoneNumber;
    }
    /* check if first 2 digit is 00, replace 00 with +*/
    if (strncmp($scontrol, "00", 2) == 0) {
        $scontrol = "+" . substr($scontrol, 2);
        /* If only 00 is entered, pass it to api and it will return invalid */
        if (strlen($scontrol) == 1) {
            return $phoneNumber;
        }
    }

    $rphone = "";
    /* If first digit is +, find countrycode from that string, and prepend it in phone number */
    if ($scontrol[0] == '+') {
        for ($i = 2; $i < strlen($scontrol); $i++) {
            $first = substr($scontrol, 1, $i - 1);

            if ($first == $countryPhoneCode) {
                $scontrol = "+" . $first . "." . substr($scontrol, $i);
                return $scontrol;
            }
        }
        $scontrol = trim($scontrol, "+");
        $rphone = "+" . $countryPhoneCode . "." . $scontrol;
    } else {
        $rphone = "+" . $countryPhoneCode . "." . $scontrol;
    }

    if (ibs_chekPhone($rphone)) {
        //New code Start here *******************************************************

        $countryCodeLength = strlen($countryPhoneCode);

        if (substr($phoneNumber, 0, $countryCodeLength) == $countryPhoneCode) {
            $myPhoneNumber = substr($phoneNumber, $countryCodeLength);
            $myPhoneNumber = preg_replace("/[^0-9,.]/", "", $myPhoneNumber);
            $rphone = '+' . $countryPhoneCode . '.' . $myPhoneNumber;
        }

        //New code End here  *********************************************************
        return $rphone;
    } else {
        //New code Start here *******************************************************

        $countryCodeLength = strlen($countryPhoneCode);

        if (substr($phoneNumber, 0, $countryCodeLength) == $countryPhoneCode) {
            $myPhoneNumber = substr($phoneNumber, $countryCodeLength);
            $myPhoneNumber = preg_replace("/[^0-9,.]/", "", $myPhoneNumber);
            //$formatPhone = '+'.$mycountryCode.'.'.$myPhoneNumber;
        }

        //New code End here  *********************************************************

        return $phoneNumber;
    }
}


function ibs_get_utf8_parameters($params)
{
    $config = array();
    $result = full_query("SELECT setting, value FROM tblconfiguration;");
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
        $config[strtolower($row['setting'])] = $row['value'];
    }
    if ((strtolower($config["charset"]) != "utf-8") && (strtolower($config["charset"]) != "utf8")) {
        return $params;
    }

    $result = full_query("SELECT orderid FROM tbldomains WHERE id='" . mysql_real_escape_string($params["domainid"]) . "' LIMIT 1;");
    if (!($row = mysql_fetch_array($result, MYSQL_ASSOC))) {
        return $params;
    }

    $result = full_query("SELECT userid,contactid FROM tblorders WHERE id='" . mysql_real_escape_string($row['orderid']) . "' LIMIT 1;");
    if (!($row = mysql_fetch_array($result, MYSQL_ASSOC))) {
        return $params;
    }

    if ($row['contactid']) {
        $result = full_query("SELECT firstname, lastname, companyname, email, address1, address2, city, state, postcode, country, phonenumber FROM tblcontacts WHERE id='" . mysql_real_escape_string($row['contactid']) . "' LIMIT 1;");
        if (!($row = mysql_fetch_array($result, MYSQL_ASSOC))) {
            return $params;
        }
        foreach ($row as $key => $value) {
            $params[$key] = html_entity_decode($value);
        }
    } elseif ($row['userid']) {
        $result = full_query("SELECT firstname, lastname, companyname, email, address1, address2, city, state, postcode, country, phonenumber FROM tblclients WHERE id='" . mysql_real_escape_string($row['userid']) . "' LIMIT 1;");
        if (!($row = mysql_fetch_array($result, MYSQL_ASSOC))) {
            return $params;
        }
        foreach ($row as $key => $value) {
            $params[$key] = html_entity_decode($value);
        }
    }


    $resultad = full_query("SELECT `name`,`value` FROM tbldomainsadditionalfields WHERE domainid='" . mysql_real_escape_string($params["domainid"]) . "';");
    if (!isset($params['additionalfields'])) {
        $params['additionalfields'] = array();
    }
    while ($row = mysql_fetch_array($resultad)) {
        $name = $row["name"];
        $value = $row["value"];
        $params['additionalfields'][$name] = html_entity_decode($value);
    }

    if ($config['registraradminuseclientdetails']) {
        $params['adminfirstname'] = $params['firstname'];
        $params['adminlastname'] = $params['lastname'];
        $params['admincompanyname'] = $params['companyname'];
        $params['adminemail'] = $params['email'];
        $params['adminaddress1'] = $params['address1'];
        $params['adminaddress2'] = $params['address2'];
        $params['admincity'] = $params['city'];
        $params['adminstate'] = $params['state'];
        $params['adminpostcode'] = $params['postcode'];
        $params['admincountry'] = $params['country'];
        $params['adminphonenumber'] = $params['phonenumber'];
    } else {
        $params['adminfirstname'] = html_entity_decode($config['registraradminfirstname']);
        $params['adminlastname'] = html_entity_decode($config['registraradminlastname']);
        $params['admincompanyname'] = html_entity_decode($config['registraradmincompanyname']);
        $params['adminemail'] = html_entity_decode($config['registraradminemailaddress']);
        $params['adminaddress1'] = html_entity_decode($config['registraradminaddress1']);
        $params['adminaddress2'] = html_entity_decode($config['registraradminaddress2']);
        $params['admincity'] = html_entity_decode($config['registraradmincity']);
        $params['adminstate'] = html_entity_decode($config['registraradminstateprovince']);
        $params['adminpostcode'] = html_entity_decode($config['registraradminpostalcode']);
        $params['admincountry'] = html_entity_decode($config['registraradmincountry']);
        $params['adminphonenumber'] = html_entity_decode($config['registraradminphone']);
    }

    return $params;
}

/*function ibs_getCountryCodeByName($countryName) {
    $country = array("AFGHANISTAN"=>"AF","ALAND ISLANDS"=>"AX","ALBANIA"=>"AL","ALGERIA"=>"DZ","AMERICAN SAMOA"=>"AS","ANDORRA"=>"AD","ANGOLA"=>"AO","ANGUILLA"=>"AI","ANTARCTICA"=>"AQ","ANTIGUA AND BARBUDA"=>"AG","ARGENTINA"=>"AR","ARMENIA"=>"AM","ARUBA"=>"AW","AUSTRALIA"=>"AU","AUSTRIA"=>"AT","AZERBAIJAN"=>"AZ","BAHAMAS"=>"BS","BAHRAIN"=>"BH","BANGLADESH"=>"BD","BARBADOS"=>"BB","BELARUS"=>"BY","BELGIUM"=>"BE","BELIZE"=>"BZ","BENIN"=>"BJ","BERMUDA"=>"BM","BHUTAN"=>"BT","BOLIVIA"=>"BO","BOSNIA AND HERZEGOVINA"=>"BA","BOTSWANA"=>"BW","BOUVET ISLAND"=>"BV","BRAZIL"=>"BR","BRITISH INDIAN OCEAN TERRITORY"=>"IO","BRITISH VIRGIN ISLANDS"=>"VG","BRUNEI"=>"BN","BULGARIA"=>"BG","BURKINA FASO"=>"BF","BURUNDI"=>"BI","CAMBODIA"=>"KH","CAMEROON"=>"CM","CANADA"=>"CA","CAPE VERDE"=>"CV","CAYMAN ISLANDS"=>"KY","CENTRAL AFRICAN REPUBLIC"=>"CF","CHAD"=>"TD","CHILE"=>"CL","CHINA"=>"CN","CHRISTMAS ISLAND"=>"CX","COCOS (KEELING) ISLANDS"=>"CC","COLOMBIA"=>"CO","COMOROS"=>"KM","CONGO"=>"CG","COOK ISLANDS"=>"CK","COSTA RICA"=>"CR","CROATIA"=>"HR","CUBA"=>"CU","CYPRUS"=>"CY","CZECH REPUBLIC"=>"CZ","DEMOCRATIC REPUBLIC OF CONGO"=>"CD","DENMARK"=>"DK","DISPUTED TERRITORY"=>"XX","DJIBOUTI"=>"DJ","DOMINICA"=>"DM","DOMINICAN REPUBLIC"=>"DO","EAST TIMOR"=>"TL","ECUADOR"=>"EC","EGYPT"=>"EG","EL SALVADOR"=>"SV","EQUATORIAL GUINEA"=>"GQ","ERITREA"=>"ER","ESTONIA"=>"EE","ETHIOPIA"=>"ET","FALKLAND ISLANDS"=>"FK","FAROE ISLANDS"=>"FO","FEDERATED STATES OF MICRONESIA"=>"FM","FIJI"=>"FJ","FINLAND"=>"FI","FRANCE"=>"FR","FRENCH GUYANA"=>"GF","FRENCH POLYNESIA"=>"PF","FRENCH SOUTHERN TERRITORIES"=>"TF","GABON"=>"GA","GAMBIA"=>"GM","GEORGIA"=>"GE","GERMANY"=>"DE","GHANA"=>"GH","GIBRALTAR"=>"GI","GREECE"=>"GR","GREENLAND"=>"GL","GRENADA"=>"GD","GUADELOUPE"=>"GP","GUAM"=>"GU","GUATEMALA"=>"GT","GUERNSEY"=>"GG","GUINEA"=>"GN","GUINEA-BISSAU"=>"GW","GUYANA"=>"GY","HAITI"=>"HT","HEARD ISLAND AND MCDONALD ISLANDS"=>"HM","HONDURAS"=>"HN","HONG KONG"=>"HK","HUNGARY"=>"HU","ICELAND"=>"IS","INDIA"=>"IN","INDONESIA"=>"ID","IRAN"=>"IR","IRAQ"=>"IQ","IRAQ-SAUDI ARABIA NEUTRAL ZONE"=>"XE","IRELAND"=>"IE","ISRAEL"=>"IL","ISLE OF MAN"=>"IM","ITALY"=>"IT","IVORY COAST"=>"CI","JAMAICA"=>"JM","JAPAN"=>"JP","JERSEY"=>"JE","JORDAN"=>"JO","KAZAKHSTAN"=>"KZ","KENYA"=>"KE","KIRIBATI"=>"KI","KUWAIT"=>"KW","KYRGYZSTAN"=>"KG","LAOS"=>"LA","LATVIA"=>"LV","LEBANON"=>"LB","LESOTHO"=>"LS","LIBERIA"=>"LR","LIBYA"=>"LY","LIECHTENSTEIN"=>"LI","LITHUANIA"=>"LT","LUXEMBOURG"=>"LU","MACAU"=>"MO","MACEDONIA"=>"MK","MADAGASCAR"=>"MG","MALAWI"=>"MW","MALAYSIA"=>"MY","MALDIVES"=>"MV","MALI"=>"ML","MALTA"=>"MT","MARSHALL ISLANDS"=>"MH","MARTINIQUE"=>"MQ","MAURITANIA"=>"MR","MAURITIUS"=>"MU","MAYOTTE"=>"YT","MEXICO"=>"MX","MOLDOVA"=>"MD","MONACO"=>"MC","MONGOLIA"=>"MN","MONTSERRAT"=>"MS","MOROCCO"=>"MA","MOZAMBIQUE"=>"MZ","MYANMAR"=>"MM","NAMIBIA"=>"NA","NAURU"=>"NR","NEPAL"=>"NP","NETHERLANDS"=>"NL","NETHERLANDS ANTILLES"=>"AN","NEW CALEDONIA"=>"NC","NEW ZEALAND"=>"NZ","NICARAGUA"=>"NI","NIGER"=>"NE","NIGERIA"=>"NG","NIUE"=>"NU","NORFOLK ISLAND"=>"NF","NORTH KOREA"=>"KP","NORTHERN MARIANA ISLANDS"=>"MP","NORWAY"=>"NO","OMAN"=>"OM","PAKISTAN"=>"PK","PALAU"=>"PW","PALESTINIAN OCCUPIED TERRITORIES"=>"PS","PANAMA"=>"PA","PAPUA NEW GUINEA"=>"PG","PARAGUAY"=>"PY","PERU"=>"PE","PHILIPPINES"=>"PH","PITCAIRN ISLANDS"=>"PN","POLAND"=>"PL","PORTUGAL"=>"PT","PUERTO RICO"=>"PR","QATAR"=>"QA","REUNION"=>"RE","ROMANIA"=>"RO","RUSSIA"=>"RU","RWANDA"=>"RW","SAINT HELENA AND DEPENDENCIES"=>"SH","SAINT KITTS AND NEVIS"=>"KN","SAINT LUCIA"=>"LC","SAINT PIERRE AND MIQUELON"=>"PM","SAINT VINCENT AND THE GRENADINES"=>"VC","SAMOA"=>"WS","SAN MARINO"=>"SM","SAO TOME AND PRINCIPE"=>"ST","SAUDI ARABIA"=>"SA","SENEGAL"=>"SN","SEYCHELLES"=>"SC","SIERRA LEONE"=>"SL","SINGAPORE"=>"SG","SLOVAKIA"=>"SK","SLOVENIA"=>"SI","SOLOMON ISLANDS"=>"SB","SOMALIA"=>"SO","SOUTH AFRICA"=>"ZA","SOUTH GEORGIA AND SOUTH SANDWICH ISLANDS"=>"GS","SOUTH KOREA"=>"KR","SPAIN"=>"ES","SPRATLY ISLANDS"=>"PI","SRI LANKA"=>"LK","SUDAN"=>"SD","SURINAME"=>"SR","SVALBARD AND JAN MAYEN"=>"SJ","SWAZILAND"=>"SZ","SWEDEN"=>"SE","SWITZERLAND"=>"CH","SYRIA"=>"SY","TAIWAN"=>"TW","TAJIKISTAN"=>"TJ","TANZANIA"=>"TZ","THAILAND"=>"TH","TOGO"=>"TG","TOKELAU"=>"TK","TONGA"=>"TO","TRINIDAD AND TOBAGO"=>"TT","TUNISIA"=>"TN","TURKEY"=>"TR","TURKMENISTAN"=>"TM","TURKS AND CAICOS ISLANDS"=>"TC","TUVALU"=>"TV","UGANDA"=>"UG","UKRAINE"=>"UA","UNITED ARAB EMIRATES"=>"AE","UNITED KINGDOM"=>"GB","UNITED NATIONS NEUTRAL ZONE"=>"XD","UNITED STATES"=>"US","UNITED STATES MINOR OUTLYING ISLANDS"=>"UM","URUGUAY"=>"UY","US VIRGIN ISLANDS"=>"VI","UZBEKISTAN"=>"UZ","VANUATU"=>"VU","VATICAN CITY"=>"VA","VENEZUELA"=>"VE","VIETNAM"=>"VN","WALLIS AND FUTUNA"=>"WF","WESTERN SAHARA"=>"EH","YEMEN"=>"YE","ZAMBIA"=>"ZM","ZIMBABWE"=>"ZW","SERBIA"=>"RS","MONTENEGRO"=>"ME","SAINT MARTIN"=>"MF","SAINT BARTHELEMY"=>"BL");
    return $country[$countryName];
}*/

function ibs_getClientIp()
{
    return (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null));
}

function ibs_get2CharDotITProvinceCode($province)
{

    $provinceFiltered = trim($province);

    $provinceNamesInPossibleVariants = array(
        'Agrigento' => 'AG',
        'Alessandria' => 'AL',
        'Ancona' => 'AN',
        'Aosta, Aoste (fr)' => 'AO',
        'Aosta, Aoste' => 'AO',
        'Aosta' => 'AO',
        'Aoste' => 'AO',
        'Arezzo' => 'AR',
        'Ascoli Piceno' => 'AP',
        'Ascoli-Piceno' => 'AP',
        'Asti' => 'AT',
        'Avellino' => 'AV',
        'Bari' => 'BA',
        'Barletta-Andria-Trani' => 'BT',
        'Barletta Andria Trani' => 'BT',
        'Belluno' => 'BL',
        'Benevento' => 'BN',
        'Bergamo' => 'BG',
        'Biella' => 'BI',
        'Bologna' => 'BO',
        'Bologna (bo)' => 'BO',
        'Bolzano, Bozen (de)' => 'BZ',
        'Bolzano, Bozen' => 'BZ',
        'Bolzano' => 'BZ',
        'Bozen' => 'BZ',
        'Brescia' => 'BS',
        'Brindisi' => 'BR',
        'Cagliari' => 'CA',
        'Caltanissetta' => 'CL',
        'Campobasso' => 'CB',
        'Carbonia-Iglesias' => 'CI',
        'Carbonia Iglesias' => 'CI',
        'Carbonia' => 'CI',
        'Caserta' => 'CE',
        'Catania' => 'CT',
        'Catanzaro' => 'CZ',
        'Chieti' => 'CH',
        'Como' => 'CO',
        'Cosenza' => 'CS',
        'Cremona' => 'CR',
        'Crotone' => 'KR',
        'Cuneo' => 'CN',
        'Enna' => 'EN',
        'Fermo' => 'FM',
        'Ferrara' => 'FE',
        'Firenze' => 'FI',
        'Foggia' => 'FG',
        'Forli-Cesena' => 'FC',
        'Forli Cesena' => 'FC',
        'Forli' => 'FC',
        'Frosinone' => 'FR',
        'Genova' => 'GE',
        'Gorizia' => 'GO',
        'Grosseto' => 'GR',
        'Imperia' => 'IM',
        'Isernia' => 'IS',
        'La Spezia' => 'SP',
        'L\'Aquila' => 'AQ',
        'LAquila' => 'AQ',
        'L-Aquila' => 'AQ',
        'L Aquila' => 'AQ',
        'Latina' => 'LT',
        'Lecce' => 'LE',
        'Lecco' => 'LC',
        'Livorno' => 'LI',
        'Lodi' => 'LO',
        'Lucca' => 'LU',
        'Macerata' => 'MC',
        'Mantova' => 'MN',
        'Massa-Carrara' => 'MS',
        'Massa Carrara' => 'MS',
        'Massa' => 'MS',
        'Matera' => 'MT',
        'Medio Campidano' => 'VS',
        'Medio-Campidano' => 'VS',
        'Medio' => 'VS',
        'Messina' => 'ME',
        'Milano' => 'MI',
        'Modena' => 'MO',
        'Monza e Brianza' => 'MB',
        'Monza-e-Brianza' => 'MB',
        'Monza-Brianza' => 'MB',
        'Monza Brianza' => 'MB',
        'Monza' => 'MB',
        'Napoli' => 'NA',
        'Novara' => 'NO',
        'Nuoro' => 'NU',
        'Ogliastra' => 'OG',
        'Olbia-Tempio' => 'OT',
        'Olbia Tempio' => 'OT',
        'Olbia' => 'OT',
        'Oristano' => 'OR',
        'Padova' => 'PD',
        'Palermo' => 'PA',
        'Parma' => 'PR',
        'Pavia' => 'PV',
        'Perugia' => 'PG',
        'Pesaro e Urbino' => 'PU',
        'Pesaro-e-Urbino' => 'PU',
        'Pesaro-Urbino' => 'PU',
        'Pesaro Urbino' => 'PU',
        'Pesaro' => 'PU',
        'Pescara' => 'PE',
        'Piacenza' => 'PC',
        'Pisa' => 'PI',
        'Pistoia' => 'PT',
        'Pordenone' => 'PN',
        'Potenza' => 'PZ',
        'Prato' => 'PO',
        'Ragusa' => 'RG',
        'Ravenna' => 'RA',
        'Reggio Calabria' => 'RC',
        'Reggio-Calabria' => 'RC',
        'Reggio' => 'RC',
        'Reggio Emilia' => 'RE',
        'Reggio-Emilia' => 'RE',
        'Reggio' => 'RE',
        'Rieti' => 'RI',
        'Rimini' => 'RN',
        'Roma' => 'RM',
        'Rovigo' => 'RO',
        'Salerno' => 'SA',
        'Sassari' => 'SS',
        'Savona' => 'SV',
        'Siena' => 'SI',
        'Siracusa' => 'SR',
        'Sondrio' => 'SO',
        'Taranto' => 'TA',
        'Teramo' => 'TE',
        'Terni' => 'TR',
        'Torino' => 'TO',
        'Trapani' => 'TP',
        'Trento' => 'TN',
        'Treviso' => 'TV',
        'Trieste' => 'TS',
        'Udine' => 'UD',
        'Varese' => 'VA',
        'Venezia' => 'VE',
        'Verbano-Cusio-Ossola' => 'VB',
        'Verbano Cusio Ossola' => 'VB',
        'Verbano' => 'VB',
        'Verbano-Cusio' => 'VB',
        'Verbano-Ossola' => 'VB',
        'Vercelli' => 'VC',
        'Verona' => 'VR',
        'Vibo Valentia' => 'VV',
        'Vibo-Valentia' => 'VV',
        'Vibo' => 'VV',
        'Vicenza' => 'VI',
        'Viterbo' => 'VT',
    );


    // Check if we need to search province code
    if (strlen($provinceFiltered) == 2) {
        // Looks we already have 2 char province code
        return strtoupper($provinceFiltered);
    } else {
        $provinceFiltered = strtolower(preg_replace('/[^a-z]/i', '', $provinceFiltered));

        foreach ($provinceNamesInPossibleVariants as $name => $code) {
            if (strtolower(preg_replace('/[^a-z]/i', '', $name)) == $provinceFiltered) {
                return $code;
            }
        }

        return $province;
    }
}

function ibs_getItProvinceCode($inputElementValue)
{

    $code = 'RM';

    preg_match('/\[\s*([a-z]{2})\s*\]$/i', $inputElementValue, $matches);

    if (isset($matches[1])) {
        $code = $matches[1];
    }

    return $code;
}

function ibs_GetDomainSuggestions($params)
{
    return new ResultsList();
}

function ibs_CheckAvailability($params)
{
    $tlds = $params['tldsToInclude'];
    $results = new ResultsList();

    foreach ($tlds as $tld) {
        $params["domainname"] = $params["searchTerm"] . $tld;
        $res = ibs_domainCheck($params);
        $sld = $params["searchTerm"];//$params ["sld"];
        $result = new SearchResult($sld, $tld);
        if ($res['status'] === 'AVAILABLE') {
            $result->setStatus(SearchResult::STATUS_NOT_REGISTERED);
            if ($res['price_ispremium'] == 'YES') {
                $result->setPremiumDomain(true);
                $result->setPremiumCostPricing(
                    array(
                        'register' => $res['price_registration_1'],
                        'renew' => $res['price_renewal_1'],
                        'CurrencyCode' => $res['price_currency'],
                    )
                );
            }
        } elseif (isset($res ["error"])) {
            $result->setStatus(SearchResult::STATUS_TLD_NOT_SUPPORTED);
        } else {
            $result->setStatus(SearchResult::STATUS_REGISTERED);
        }
        $results->Append($result);
    }
    return $results;
}

function ibs_domainCheck($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Check';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName, 'currency' => 'USD');
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    } else {
        $values = $result;
    }
    return $values;
}

/* Custom function for email verification*/
function ibs_verify($params)
{
    $domainid = $params["domainid"];
    $data = ibs_getEmailVerificationDetails($params);
    $email = $data["email"];
    $currentStatus = $data["currentstatus"];
    return array(
        'templatefile' => 'verify',
        'breadcrumb' => array('clientarea.php?action=domaindetails&domainid=' . $domainid . '&modop=custom&a=verify' => 'Verify Email'),
        'vars' => array(
            'email' => $email,
            'status' => $currentStatus,
            'domainid' => $domainid
        ),
    );
}

function ibs_getEmailVerificationDetails($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $domainid = $params['domainid'];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/RegistrantVerification/Info';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    if ($result == false) {
        $values["error"] = ibs_getconnectionErrorMessage($errorMessage);
    } elseif ($result['status'] == "FAILURE") {
        $values ["error"] = $result ["message"];
    } else {
        $values = $result;
        //if(isset($result['currentstatus']) && $result['currentstatus'] == "PENDING"){
        //$email = $result['email'];
        //}
    }
    return $values;
}

/* Custom function for email verification*/
function ibs_send($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $domainid = $params['domainid'];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/RegistrantVerification/Send';

    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    if ($result == false) {
        $errormessage = ibs_getconnectionErrorMessage($errorMessage);
        if (empty($errrormessage)) {
            $errormessage = "Due to some technical reason email cannot be sent.";
        }
    } elseif ($result['status'] == "FAILURE") {
        $errormessage = $result ["message"];
    } elseif ($result['status'] == "SUCCESS") {
        $values = $result;
        $operation = $values["operation"];
        $successmessage = "Verification email has been " . $operation . ". Please check your mail box within a couple of minutes. Make sure you also check the spam folder.";
    } else {
        $errormessage = "Due to some technical reason email cannot be sent.";
    }
    return array(
        'templatefile' => 'send',
        'breadcrumb' => array('clientarea.php?action=domaindetails&domainid=' . $domainid . '&modop=custom&a=send' => 'Resend Email'),
        'vars' => array(
            'status' => $result['currentstatus'],
            'domainid' => $domainid,
            'errormessage' => $errormessage,
            'successmessage' => $successmessage
        ),
    );
}

/*Custom Url Forwarding*/
function ibs_domainurlforwarding($params)
{
    $domainid = $params['domainid'];
    $tld = $params ["tld"];
    $sld = $params ["sld"];
    $error = "";

    if (!isset($params["domainname"])) {
        $domainName = $params["domainName"] = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainName"] = $params["domainname"];
    } else {
        $domainName = $params["domainName"] = $params["original"]["domainname"];
    }
    $data = ibs_GetUrlForwarding($params);
    if (isset($_POST) && count($_POST) > 0) {
        for ($i = 0, $iMax = count($data); $i < $iMax; $i++) {
            $params["source"] = trim(trim($data[$i]["hostname"], " .") . "." . $domainName, " .");
            $result = ibs_RemoveUrlForwarding($params);
        }

        for ($i = 0, $iMax = count($_POST["dnsrecordaddress"]); $i < $iMax; $i++) {
            $params["hostName"] = $_POST["dnsrecordhost"][$i];
            $params["type"] = $_POST["dnsrecordtype"][$i];
            $params["address"] = $_POST["dnsrecordaddress"][$i];
            $result = ibs_SaveUrlForwarding($params);
            if ($result) {
                $error .= $result . "\n";
            }
        }
    }
    $data = ibs_GetUrlForwarding($params);
    return array(
        'templatefile' => 'domainurlforwarding',
        'breadcrumb' => array('clientarea.php?action=domaindetails&domainid=' . $domainid . '&modop=custom&a=domainurlforwarding' => 'URL Forwarding'),
        'vars' => array(
            'status' => $result['currentstatus'],
            'domainName' => $domainName,
            'domainid' => $domainid,
            'data' => $data,
            'errormessage' => $error,
            'successmessage' => ''
        ),
    );
}


function ibs_GetUrlForwarding($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $domainName = $params["domainName"];

    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . "Domain/UrlForward/List";
    $data = array('apikey' => $username, 'password' => $password, 'domain' => $domainName);
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();

    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result['status'] == "FAILURE") {
        $errormessage = $result ["message"];
    } else {
        $totalRecords = (int)$result ['total_rules'];
        for ($i = 1; $i <= $totalRecords; $i++) {
            $recordType = '';
            if (isset($result ['rule_' . $i . '_isframed'])) {
                $recordType = trim($result ['rule_' . $i . '_isframed']) == 'YES' ? "FRAME" : 'URL';
            }
            if (isset($result ['rule_' . $i . '_source'])) {
                $recordHostname = $result ['rule_' . $i . '_source'];

                $dParts = explode('.', $domainName);
                $hParts = explode('.', $recordHostname);
                $recordHostname = '';
                for ($j = 0; $j < (count($hParts) - count($dParts)); $j++) {
                    $recordHostname .= (empty($recordHostname) ? '' : '.') . $hParts[$j];
                }
            }
            if (isset($result ['rule_' . $i . '_destination'])) {
                $recordAddress = $result ['rule_' . $i . '_destination'];
            }
            if (isset($result ['rule_' . $i . '_source'])) {
                $hostrecords [] = array("hostname" => $recordHostname, "type" => $recordType, "address" => htmlspecialchars($recordAddress));
            }
        }
    }
    return (count($hostrecords) ? $hostrecords : $values);
}

function ibs_SaveUrlForwarding($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $domainName = $params["domainName"];

    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . "Domain/UrlForward/Add";

    $data = array('apikey' => $username, 'password' => $password);
    $data['source'] = trim(trim($params["hostName"], ". ") . "." . $domainName, '.');
    $data ['isFramed'] = $params["type"] == 'FRAME' ? 'YES' : 'NO';
    $destination = trim($params["address"], " .");
    $data ['Destination'] = $destination;
    if (empty($destination)) {
        return false;
    }
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    $errorMessages = '';
    if ($result === false) {
        $errorMessages .= ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result['status'] == "FAILURE") {
        $errorMessages .= $result ["message"];
    }
    if ($errorMessages) {
        return $errorMessages;
    } else {
        return false;
    }
}

function ibs_RemoveUrlForwarding($params)
{
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $domainName = $params["domainName"];

    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . "Domain/UrlForward/Remove";

    $data = array('apikey' => $username, 'password' => $password);
    $data ["source"] = $params["source"];
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessages = '';
    $errorMessage = ibs_getLastError();
    if ($result === false) {
        $errorMessages .= ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result['status'] == "FAILURE") {
        $errorMessages .= $result ["message"];
    }
    if ($errorMessages) {
        return $errorMessages;
    } else {
        return false;
    }
}


function ibs_GetTldPricing(array $params)
{
    $command = 'GetCurrencies';
    $postData = array();

    $results = localAPI($command, $postData);
    $defaultCurrency = $results['currencies']['currency'][0]['code'];
    $currency = 'USD';
    if (in_array($defaultCurrency, array('USD', 'CAD', 'AUD', 'JPY', 'EUR', 'GBP'))) {
        $currency = $defaultCurrency;
    }
    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . "/Account/PriceList/Get";

    $data = array('apikey' => $username, 'password' => $password, "version" => '5', 'currency' => $currency);
    $r = ibs_runCommand($commandUrl, $data);
    ibs_debugLog(array("action" => "raw response", "requestParam" => "", "responseParam" => $r));
    $i = 0;
    $extensionData = array();
    while ($r['product_' . $i . '_tld']) {
        list($tld, $product) = explode(' ', $r['product_' . $i . '_name']);
        $tld = $r['product_' . $i . '_tld'];
        if (!$extensionData[$tld]) {
            $extensionData[$tld] = array();
        }
        $extensionData[$tld]['currencyCode'] = $r['product_' . $i . '_currency'];
        $extensionData[$tld]['registrationPrice'] = $r['product_' . $i . '_registration'];
        $extensionData[$tld]['renewalPrice'] = $r['product_' . $i . '_renewal'];
        $extensionData[$tld]['transferPrice'] = $r['product_' . $i . '_transfer'];
        $extensionData[$tld]['redemptionFee'] = $r['product_' . $i . '_restore'];
        $extensionData[$tld]['redemptionDays'] = ((int)trim($r['product_' . $i . '_rgp'])) / 24;
        $extensionData[$tld]['transferSecretRequired'] = (strtolower($r['product_' . $i . '_authinforequired']) === 'yes');
        $extensionData[$tld]['minPeriod'] = $r['product_' . $i . '_minperiod'];
        $extensionData[$tld]['maxPeriod'] = $r['product_' . $i . '_maxperiod'];
        $extensionData[$tld]['inc'] = $r['product_' . $i . '_inc'];
        $i++;
    }
    // Perform API call to retrieve extension information
    // A connection error should return a simple array with error key and message
    // return ['error' => 'This error occurred',];

    $results = new ResultsList();
    ibs_debugLog(array("action" => "parsed data", "requestParam" => "", "responseParam" => $extensionData));
    foreach ($extensionData as $tld => $extension) {
        // All the set methods can be chained and utilised together.
        $item = (new ImportItem())
            ->setExtension($tld)
            ->setMinYears($extension['minPeriod'])
            ->setMaxYears($extension['maxPeriod'])
            ->setRegisterPrice($extension['registrationPrice'])
            ->setRenewPrice($extension['renewalPrice'])
            ->setTransferPrice($extension['transferPrice'])
            ->setRedemptionFeeDays($extension['redemptionDays'])
            ->setRedemptionFeePrice($extension['redemptionFee'])
            ->setCurrency($extension['currencyCode'])
            ->setEppRequired($extension['transferSecretRequired']);

        $results[] = $item;
    }
    return $results;
}

/*Get TMCH details*/
function ibs_TmchInfo($lookupkey)
{
    $params = ibs_getapiDetails();

    $username = $params ["Username"];
    $password = $params ["Password"];
    $testmode = $params ["TestMode"];
    $tld = $params ["tld"];
    $sld = $params ["sld"];

    if (!isset($params["domainname"])) {
        $domainName = $sld . '.' . $tld;
    } elseif (!isset($params["original"]["domainname"])) {
        $domainName = $params["domainname"];
    } else {
        $domainName = $params["original"]["domainname"];
    }
    $apiServerUrl = ($testmode == "on") ? API_TESTSERVER_URL : API_SERVER_URL;
    $commandUrl = $apiServerUrl . 'Domain/Tmch/Info';

    $data = array('apikey' => $username, 'password' => $password, 'lookupkey' => $lookupkey, 'domain' => $domainName);
    $result = ibs_runCommand($commandUrl, $data);
    $errorMessage = ibs_getLastError();
    # If error, return the error message in the value below
    if ($result === false) {
        $values ["error"] = ibs_getConnectionErrorMessage($errorMessage);
    } elseif ($result ['status'] == 'FAILURE') {
        $values ["error"] = $result ['message'];
    } else {
        $values = $result;
    }
    return $values;
}

/*Get Registrar details*/
function ibs_getapiDetails()
{
    //Get Admin Detail
    $table = "tbladmins";
    $fields = "id";
    $result = select_query($table, $fields, '', '', '', 1);
    while ($data = mysql_fetch_array($result)) {
        $adminId = $data["id"];
    }

    $table = 'tblregistrars';
    $fields = 'setting, value';
    $where = array("registrar" => "ibs");
    $result = select_query($table, $fields, $where);
    while ($response = mysql_fetch_array($result)) {
        if ($response['setting'] == "Password") {
            $response = ibs_decryptData($response['value'], $adminId);

            $params["Password"] = $response['password'];
        }
        if ($response['setting'] == "Username") {
            $response = ibs_decryptData($response['value'], $adminId);

            $params["Username"] = $response['password'];
        }
        if ($response['setting'] == "TestMode") {
            $response = ibs_decryptData($response['value'], $adminId);

            $params["TestMode"] = $response['password'];
        }
    }
    return $params;
}

/* Decrypt WHMCS encrypted details */
function ibs_decryptData($input, $admin)
{
    $command = 'decryptpassword';
    $values['password2'] = $input;
    $response = localAPI($command, $values, $admin);
    return $response;
}
