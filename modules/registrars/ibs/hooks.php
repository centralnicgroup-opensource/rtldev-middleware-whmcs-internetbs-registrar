<?php

/**
 * Validate Domain Additional Fields while filling domain related additional fields
 */

use WHMCS\Database\Capsule;

include_once("ibs.php");
function hook_ibs_validateAdditionalFields($params)
{
    $errors = array();
    $domains = $_SESSION['cart']['domains'];
    for ($i = 0, $iMax = count($domains); $i < $iMax; $i++) {
        $domainName = $domains[$i]['domain'];
        $tld = ibs_getTld($domainName);
        $additionalFields = $domains[$i]['fields'];
        if ($tld == ".fr" || $tld == ".re" || $tld == ".pm" || $tld == ".yt" || $tld == ".wf" || $tld == ".tf") {
            switch ($additionalFields['holdertype']) {
                case "individual":
                    if (!$additionalFields['birthdate']) {
                        $errors[] = "Date Of Birth is required (" . $domainName . ")";
                    } else {
                        $date = explode("-", $additionalFields['birthdate']);
                        if (!checkdate($date[1], $date[2], $date[0])) {
                            $errors[] = "Please enter proper birth date [Date format YYYY-MM-DD] (" . $domainName . ")";
                        }
                    }
                    if (!$additionalFields['birthcountry']) {
                        $errors[] = "Birth Country is required (" . $domainName . ")";
                    }
                    if ($additionalFields['birthcountry'] == "FR") {
                        if (!$additionalFields['birthcity']) {
                            $errors[] = "Birth City is required (" . $domainName . ")";
                        }
                        if (!$additionalFields['birthpostalcode']) {
                            $errors[] = "Birth Postal Code is required (" . $domainName . ")";
                        }
                    }
                    break;
                case "company":
                    break;
                case "trademark":
                    if (!$additionalFields['trademark']) {
                        $errors[] = "Trademark is required (" . $domainName . ")";
                    }
                    break;
                case "association":
                    if (!$additionalFields['waldec'] && ! $additionalFields['dateofassociation']) {
                        $errors[] = "Waldec or Date Of Association is required (" . $domainName . ")";
                    }
                    if ($additionalFields['dateofassociation']) {
                        if (!$additionalFields['dateofpublication']) {
                            $errors[] = "Date of Publication is required (" . $domainName . ")";
                        }
                    }
                    break;
                case "other":
                    if (!$additionalFields['otherlegalstatus']) {
                        $errors[] = "Other legal status is required (" . $domainName . ")";
                    }
                    break;
            }
        } elseif ($tld == ".asia") {
            if ($additionalFields['legalentity'] == "other") {
                if (!$additionalFields['otherlegalentity']) {
                    $errors[] = "Other Legal Entity Type is required (" . $domainName . ")";
                }
            }
            if ($additionalFields['identificationform'] == "other") {
                if (!$additionalFields['otheridentificationform']) {
                    $errors[] = "Other identification form is required (" . $domainName . ")";
                }
            }
        } elseif ($tld == ".it") {
            if ($additionalFields['legalentity'] == "7. foreigners who match 2 - 6") {
                $euroCountries = array('AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SM', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB', 'VA');
                if (!in_array($additionalFields['nationality'], $euroCountries)) {
                    $errors[] = "Nationality must be from European Countries (" . $domainName . ")";
                }
            } elseif (isset($additionalFields['legalentity']) && $additionalFields['legalentity'] !== "1. Italian and foreign natural persons") {
                if ($additionalFields["nationality"] !== "IT" && $additionalFields["nationality"] !== "ITALY") {
                    $errors[] = "Nationality must be Italy (" . $domainName . ")";
                }
            }
            if (isset($additionalFields['legalentity']) && $additionalFields['legalentity'] !== "1. Italian and foreign natural persons" && $additionalFields['whois'] == 'on') {
                $errors[] = "Whois information can be hidden for Italian and foreign natural persons entity type only (" . $domainName . ")";
            }
        } elseif ($tld == ".co.uk" || $tld == ".me.uk" || $tld == ".org.uk" || $tld == ".uk") {
            if ($additionalFields['legaltype']  == "LTD" || $additionalFields['legaltype']  == "PLC" || $additionalFields['legaltype']  == "LLP" || $additionalFields['legaltype']  == "RCHAR" || $additionalFields['legaltype'] == "IP" || $additionalFields['legaltype'] == "SCH") {
                if (!$additionalFields['registrationnumber']) {
                    $errors[] = "Registration Number is required (" . $domainName . ")";
                }
            }
        }
    }
    return($errors);
}
add_hook("ShoppingCartValidateDomainsConfig", 1, "hook_ibs_validateAdditionalFields");

/**
    Validate fields when user perform final checkout.
    Validation regarding user's country.
**/
function hook_ibs_validateData($params)
{
    $errors = [];
    $cart = $_SESSION['cart']['domains'];

    if (!is_array($cart)) {
        return $errors;
    }

    /* get admin id*/
    if (isset($_SESSION["adminid"])) {
        $adminid = $_SESSION["adminid"];
    } else {
        $table = "tbladmins";
        $admin = Capsule::table($table)->first();
        $adminid = $admin->id;
    }

    if ($params['custtype'] == "new") {
        $countryCode = $params['country'];
        $phoneNumber = $params['phonenumber'];
        $formattedPhoneNumber = ibs_reformatPhone($phoneNumber, $countryCode);
        $isValidPhone = ibs_validatePhone($formattedPhoneNumber);
        if (!$isValidPhone) {
            $errors[] = "Invalid phone Number " . $phoneNumber;
        }
    } else {
        if (strtolower($params['contact']) === "addingnew") {
            $countryCode = $params['domaincontactcountry'];
            $phoneNumber = $params['domaincontactphonenumber'];
            $formattedPhoneNumber = ibs_reformatPhone($phoneNumber, $countryCode);
            $isValidPhone = ibs_validatePhone($formattedPhoneNumber);
            if (!$isValidPhone) {
                $errors[] = "Invalid phone Number " . $phoneNumber;
            }
        } elseif ($params['contact']) {
            $value['userid'] = $_SESSION['uid'];
            $registrantDetails = localAPI("getcontacts", $value, $adminid);
            $contacts = $registrantDetails['contacts']['contact'];
            //Get selected contact details from available contacts
            for ($i = 0, $iMax = count($contacts); $i < $iMax; $i++) {
                if ($contacts[$i]['id'] == $params['contact']) {
                    $countryCode = $contacts[$i]['country'];
                    $phoneNumber = $contacts[$i]['phonenumber'];
                    $formattedPhoneNumber = ibs_reformatPhone($phoneNumber, $countryCode);
                    $isValidPhone = ibs_validatePhone($formattedPhoneNumber);
                    if (!$isValidPhone) {
                        $errors[] = "Invalid phone Number " . $phoneNumber;
                    }
                }
            }
        } else {
            $value['clientid'] = $_SESSION["uid"];
            $registrantDetails = localAPI("getclientsdetails", $value, $adminid);
            $countryCode = $registrantDetails['countrycode'];
            $phoneNumber = $registrantDetails["phonenumber"];
            $countryCode = $registrantDetails['countrycode'];

            $formattedPhoneNumber = ibs_reformatPhone($phoneNumber, $countryCode);
            $isValidPhone = ibs_validatePhone($formattedPhoneNumber);
            if (!$isValidPhone) {
                $errors[] = "Invalid phone Number " . $phoneNumber;
            }
        }
    }
    $value['userid'] = $_SESSION["uid"];


    $euroCountries = array('AX', 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'GF', 'DE', 'GI', 'GR', 'GP', 'HU', 'IS', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'MQ', 'NL', 'NO', 'PL', 'PT', 'RE', 'RO', 'SK', 'SI', 'ES', 'SE', 'GB');
    for ($i = 0, $iMax = count($cart); $i < $iMax; $i++) {
        $domainName = $cart[$i]['domain'];
        $tld = ibs_getTld($domainName);
        // Check Country Code for EU
        if ($tld == ".eu" || $tld == ".fr" || $tld == ".re" || $tld == ".pm" || $tld == ".yt" || $tld == ".wf" || $tld == ".tf") {
            if (!in_array($countryCode, $euroCountries)) {
                $errors[] = "Registrant must be from European union (" . $domainName . "). Provided $countryCode";
            }
        //Check Country Code for AFNIC tlds
        } elseif ($tld == ".de") {
            $adminCountryCode = ibs_getAdminCountryCode($countryCode);
            if ($countryCode !== "DE" && $adminCountryCode !== "DE") {
                $errors = "Registrant or Admin must be from Germany (" . $domainName . ")";
            }
            //Check Admin Country must be Guernsey, Isle Of Man, Jersey, United Kingdom for UK
        } elseif ($tld == ".uk") {
            $adminCountryCode = ibs_getAdminCountryCode($countryCode);
            if ($adminCountryCode !== "GG" && $adminCountryCode !== "IM" && $adminCountryCode !== "JE" && $adminCountryCode !== "GB") {
                $errors = "Invalid Admin country (" . $domainName . ")";
            }
        //Check Country code and nationality for IT
        } elseif ($tld == ".it") {
            $additionalfields = $cart[$i]['fields'];
            if (strlen($additionalfields['nationality']) > 2) {
                $nationality = ibs_getCountryCodeByName($additionalfields['nationality']);
            } else {
                $nationality = $additionalfields['nationality'];
            }
            if ($additionalfields['legalentity'] == "1. Italian and foreign natural persons") {
                if (!in_array($nationality, $euroCountries) && !in_array($countryCode, $euroCountries)) {
                    $errors[] = "Registrant Nationality or Country has to be from European union (" . $domainName . ")";
                }
            } elseif ($additionalfields['legalentity'] == "7. foreigners who match 2 - 6") {
                if (!in_array($nationality, $euroCountries) || $nationality !== $countryCode) {
                    $errors[] = "Registrant Nationality and Country have to be same and have to be from European union (" . $domainName . ")";
                }
            } else {
                if ($countryCode !== "IT" && $countryCode !== "ITALY") {
                    $errors[] = "Registrant Country must be Italy (" . $domainName . ")";
                }
            }
        }
    }
    return ($errors);
}
add_hook("ShoppingCartValidateCheckout", 1, "hook_ibs_validateData");


/**
    Check if domain is under claim or not.
**/
/* disable trademark claim checking
function hook_ibs_check_trademark_claim($vars)
{
    //Call domain check api to know if domain is under TMCH or not.
    $domains = $vars['domains'];
    for ($domainCnt = (count($domains) - 1); $domainCnt >= 0; $domainCnt--) {
        $domain = $domains[$domainCnt];
        if ($domain['type'] == "register") {
            $params = ibs_getapiDetails();
            $domainName = $domain['domain'];
            $params["domainname"] = $domainName;

            if ((!isset($_SESSION["cart"]["domains"][$domainCnt]["TMCH"][$domainName])) || (isset($_SESSION["cart"]["domains"][$domainCnt]["TMCH"][$domainName]) && !isset($_SESSION["cart"]["domains"][$domainCnt]["TMCH"][$domainName]["tcnAccept"]))) {
                $response = ibs_domainCheck($params);
                if (isset($response["tmchlookupkey"]) && $response["tmchlookupkey"] !== "") {
                    $_SESSION["cart"]["domains"][$domainCnt]["TMCH"][$domainName]["lookupkey"] = $response["tmchlookupkey"];
                    $_SESSION["currentdomainKey"] = $domainCnt;
                    header("Location: trademarkClaim.php");
                }
            }
        }
    }
    $_SESSION['domain_cart'] = $_SESSION['cart'];
}

add_hook("PreCalculateCartTotals", 1, "hook_ibs_check_trademark_claim");

function hook_ibs_save_tmch_value($vars)
{
    //Call domain check api to know if domain is under TMCH or not.
    $sessionDomains = $_SESSION["domain_cart"]["domains"];
    $domains = $vars["DomainIDs"];
    for ($domainCnt = 0; $domainCnt < count($domains); $domainCnt++) {
        $table = 'tbldomains';
//      $field = 'domain';
//      $where = array("id" => $domains[$domainCnt]);

        $response = Capsule::table($table)->where("id", $domains[$domainCnt])->first();


//        $result = select_query($table,$field,$where);
//      $response = mysql_fetch_array($result);

        for ($cartcnt = 0; $cartcnt < count($sessionDomains); $cartcnt++) {
            if ($sessionDomains[$cartcnt]['domain'] == $response->domain && isset($sessionDomains[$cartcnt]['TMCH'])) {
                $table = 'tbldomainsadditionalfields';
                $values = array('domainid' => $domains[$domainCnt], 'name' => 'tmchid', 'value' => $sessionDomains[$cartcnt]['tmchid']);
                Capsule::table($table)->insert($values);
                //$newId = insert_query($table, $values);

                $values = array('domainid' => $domains[$domainCnt], 'name' => 'tmchnotafter', 'value' => $sessionDomains[$cartcnt]['tmchnotafterdate']);
                Capsule::table($table)->insert($values);
                //$newId = insert_query($table, $values);

                $values = array('domainid' => $domains[$domainCnt], 'name' => 'tmchaccepteddate', 'value' => $sessionDomains[$cartcnt]['accepteddate']);
                Capsule::table($table)->insert($values);
                //$newId = insert_query($table, $values);
            }
        }
        $domainName = $response->domain;
    }
}

add_hook("AfterShoppingCartCheckout", 1, "hook_ibs_save_tmch_value");

*/
function ibs_getTld($domainName)
{
    $tldList = array(".com",".eu",".fr",".re",".pm",".yt",".wf",".tf",".uk",".co.uk",".org.uk",".me.uk",".nl",".asia",".de",".it",".nyc",".tel");
    usort($tldList, "ibs_cmp_domainLength");
    $tldList = array_reverse($tldList);

    for ($cnt = 0; $cnt < count($tldList); $cnt++) {
        $position = strpos($domainName, $tldList[$cnt]);
        if ($position) {
            $domainLength = strlen($domainName);
            $tldLength = strlen($tldList[$cnt]);
            if (($position + $tldLength) == $domainLength) {
                return $tldList[$cnt];
                break;
            }
        }
    }
    return null;
}

function ibs_cmp_domainLength($tld1, $tld2)
{
    $tld1Length = strlen($tld1);
    $tld2Length = strlen($tld2);

    if ($tld1Length < $tld2Length) {
        return -1;
    } elseif ($tld1Length == $tld2Length) {
        return 0;
    } elseif ($tld1Length > $tld2Length) {
        return 1;
    }
}

function ibs_getCountryCodeByName($countryName)
{
    $country = array("AFGHANISTAN" => "AF","ALAND ISLANDS" => "AX","ALBANIA" => "AL","ALGERIA" => "DZ","AMERICAN SAMOA" => "AS","ANDORRA" => "AD","ANGOLA" => "AO","ANGUILLA" => "AI","ANTARCTICA" => "AQ","ANTIGUA AND BARBUDA" => "AG","ARGENTINA" => "AR","ARMENIA" => "AM","ARUBA" => "AW","AUSTRALIA" => "AU","AUSTRIA" => "AT","AZERBAIJAN" => "AZ","BAHAMAS" => "BS","BAHRAIN" => "BH","BANGLADESH" => "BD","BARBADOS" => "BB","BELARUS" => "BY","BELGIUM" => "BE","BELIZE" => "BZ","BENIN" => "BJ","BERMUDA" => "BM","BHUTAN" => "BT","BOLIVIA" => "BO","BOSNIA AND HERZEGOVINA" => "BA","BOTSWANA" => "BW","BOUVET ISLAND" => "BV","BRAZIL" => "BR","BRITISH INDIAN OCEAN TERRITORY" => "IO","BRITISH VIRGIN ISLANDS" => "VG","BRUNEI" => "BN","BULGARIA" => "BG","BURKINA FASO" => "BF","BURUNDI" => "BI","CAMBODIA" => "KH","CAMEROON" => "CM","CANADA" => "CA","CAPE VERDE" => "CV","CAYMAN ISLANDS" => "KY","CENTRAL AFRICAN REPUBLIC" => "CF","CHAD" => "TD","CHILE" => "CL","CHINA" => "CN","CHRISTMAS ISLAND" => "CX","COCOS (KEELING) ISLANDS" => "CC","COLOMBIA" => "CO","COMOROS" => "KM","CONGO" => "CG","COOK ISLANDS" => "CK","COSTA RICA" => "CR","CROATIA" => "HR","CUBA" => "CU","CYPRUS" => "CY","CZECH REPUBLIC" => "CZ","DEMOCRATIC REPUBLIC OF CONGO" => "CD","DENMARK" => "DK","DISPUTED TERRITORY" => "XX","DJIBOUTI" => "DJ","DOMINICA" => "DM","DOMINICAN REPUBLIC" => "DO","EAST TIMOR" => "TL","ECUADOR" => "EC","EGYPT" => "EG","EL SALVADOR" => "SV","EQUATORIAL GUINEA" => "GQ","ERITREA" => "ER","ESTONIA" => "EE","ETHIOPIA" => "ET","FALKLAND ISLANDS" => "FK","FAROE ISLANDS" => "FO","FEDERATED STATES OF MICRONESIA" => "FM","FIJI" => "FJ","FINLAND" => "FI","FRANCE" => "FR","FRENCH GUYANA" => "GF","FRENCH POLYNESIA" => "PF","FRENCH SOUTHERN TERRITORIES" => "TF","GABON" => "GA","GAMBIA" => "GM","GEORGIA" => "GE","GERMANY" => "DE","GHANA" => "GH","GIBRALTAR" => "GI","GREECE" => "GR","GREENLAND" => "GL","GRENADA" => "GD","GUADELOUPE" => "GP","GUAM" => "GU","GUATEMALA" => "GT","GUERNSEY" => "GG","GUINEA" => "GN","GUINEA-BISSAU" => "GW","GUYANA" => "GY","HAITI" => "HT","HEARD ISLAND AND MCDONALD ISLANDS" => "HM","HONDURAS" => "HN","HONG KONG" => "HK","HUNGARY" => "HU","ICELAND" => "IS","INDIA" => "IN","INDONESIA" => "ID","IRAN" => "IR","IRAQ" => "IQ","IRAQ-SAUDI ARABIA NEUTRAL ZONE" => "XE","IRELAND" => "IE","ISRAEL" => "IL","ISLE OF MAN" => "IM","ITALY" => "IT","IVORY COAST" => "CI","JAMAICA" => "JM","JAPAN" => "JP","JERSEY" => "JE","JORDAN" => "JO","KAZAKHSTAN" => "KZ","KENYA" => "KE","KIRIBATI" => "KI","KUWAIT" => "KW","KYRGYZSTAN" => "KG","LAOS" => "LA","LATVIA" => "LV","LEBANON" => "LB","LESOTHO" => "LS","LIBERIA" => "LR","LIBYA" => "LY","LIECHTENSTEIN" => "LI","LITHUANIA" => "LT","LUXEMBOURG" => "LU","MACAU" => "MO","MACEDONIA" => "MK","MADAGASCAR" => "MG","MALAWI" => "MW","MALAYSIA" => "MY","MALDIVES" => "MV","MALI" => "ML","MALTA" => "MT","MARSHALL ISLANDS" => "MH","MARTINIQUE" => "MQ","MAURITANIA" => "MR","MAURITIUS" => "MU","MAYOTTE" => "YT","MEXICO" => "MX","MOLDOVA" => "MD","MONACO" => "MC","MONGOLIA" => "MN","MONTSERRAT" => "MS","MOROCCO" => "MA","MOZAMBIQUE" => "MZ","MYANMAR" => "MM","NAMIBIA" => "NA","NAURU" => "NR","NEPAL" => "NP","NETHERLANDS" => "NL","NETHERLANDS ANTILLES" => "AN","NEW CALEDONIA" => "NC","NEW ZEALAND" => "NZ","NICARAGUA" => "NI","NIGER" => "NE","NIGERIA" => "NG","NIUE" => "NU","NORFOLK ISLAND" => "NF","NORTH KOREA" => "KP","NORTHERN MARIANA ISLANDS" => "MP","NORWAY" => "NO","OMAN" => "OM","PAKISTAN" => "PK","PALAU" => "PW","PALESTINIAN OCCUPIED TERRITORIES" => "PS","PANAMA" => "PA","PAPUA NEW GUINEA" => "PG","PARAGUAY" => "PY","PERU" => "PE","PHILIPPINES" => "PH","PITCAIRN ISLANDS" => "PN","POLAND" => "PL","PORTUGAL" => "PT","PUERTO RICO" => "PR","QATAR" => "QA","REUNION" => "RE","ROMANIA" => "RO","RUSSIA" => "RU","RWANDA" => "RW","SAINT HELENA AND DEPENDENCIES" => "SH","SAINT KITTS AND NEVIS" => "KN","SAINT LUCIA" => "LC","SAINT PIERRE AND MIQUELON" => "PM","SAINT VINCENT AND THE GRENADINES" => "VC","SAMOA" => "WS","SAN MARINO" => "SM","SAO TOME AND PRINCIPE" => "ST","SAUDI ARABIA" => "SA","SENEGAL" => "SN","SEYCHELLES" => "SC","SIERRA LEONE" => "SL","SINGAPORE" => "SG","SLOVAKIA" => "SK","SLOVENIA" => "SI","SOLOMON ISLANDS" => "SB","SOMALIA" => "SO","SOUTH AFRICA" => "ZA","SOUTH GEORGIA AND SOUTH SANDWICH ISLANDS" => "GS","SOUTH KOREA" => "KR","SPAIN" => "ES","SPRATLY ISLANDS" => "PI","SRI LANKA" => "LK","SUDAN" => "SD","SURINAME" => "SR","SVALBARD AND JAN MAYEN" => "SJ","SWAZILAND" => "SZ","SWEDEN" => "SE","SWITZERLAND" => "CH","SYRIA" => "SY","TAIWAN" => "TW","TAJIKISTAN" => "TJ","TANZANIA" => "TZ","THAILAND" => "TH","TOGO" => "TG","TOKELAU" => "TK","TONGA" => "TO","TRINIDAD AND TOBAGO" => "TT","TUNISIA" => "TN","TURKEY" => "TR","TURKMENISTAN" => "TM","TURKS AND CAICOS ISLANDS" => "TC","TUVALU" => "TV","UGANDA" => "UG","UKRAINE" => "UA","UNITED ARAB EMIRATES" => "AE","UNITED KINGDOM" => "GB","UNITED NATIONS NEUTRAL ZONE" => "XD","UNITED STATES" => "US","UNITED STATES MINOR OUTLYING ISLANDS" => "UM","URUGUAY" => "UY","US VIRGIN ISLANDS" => "VI","UZBEKISTAN" => "UZ","VANUATU" => "VU","VATICAN CITY" => "VA","VENEZUELA" => "VE","VIETNAM" => "VN","WALLIS AND FUTUNA" => "WF","WESTERN SAHARA" => "EH","YEMEN" => "YE","ZAMBIA" => "ZM","ZIMBABWE" => "ZW","SERBIA" => "RS","MONTENEGRO" => "ME","SAINT MARTIN" => "MF","SAINT BARTHELEMY" => "BL");
    return $country[$countryName];
}

function ibs_getAdmincountryCode($countryCode)
{
    //get admin countrycode from database if different details has to be use for admin
    $useClientDetails = false;
    $table = "tblconfiguration";
    $fields = "setting,value";
    $result = Capsule::table($table)->where('setting', 'RegistrarAdminCountry')->orWhere('setting', 'RegistrarAdminUseClientDetails')->get();
    foreach ($result as $data) {
        if ($data->setting == "RegistrarAdminCountry") {
            $adminCountryCode = $data->value;
        }
        if ($data->setting == "RegistrarAdminUseClientDetails") {
            if ($data->value == 'on') {
                $useClientDetails = true;
            }
        }
    }
//    $result = select_query($table,$fields);
//  while($data = mysql_fetch_array($result)){
//      if($data['setting'] == "RegistrarAdminCountry"){
//          $adminCountryCode = $data['value'];
//      }
//      if($data['setting'] == "RegistrarAdminUseClientDetails"){
//          if($data['value'] == 'on'){
//              $useClientDetails = true;
//          }
//      }
//  }
    if ($useClientDetails) {
        $adminCountryCode = $countryCode;
    }
    return $adminCountryCode;
}
function ibs_validatePhone($phoneNumber)
{

    $phone = explode(".", $phoneNumber);


    if (count($phone) > 1) {
        $phone = $phone[1];
    } else {
        $phone = $phoneNumber;
    }
    if ((strlen($phone) < 4) || strlen($phone) >  13) {
        return false;
    } else {
        return true;
    }
}


function hook_ibs_syncExpDate($params)
{
    ibs_Sync($params);
}

add_hook('AfterRegistrarRegistration', 1, 'hook_ibs_syncExpDate');
