<?php
declare(strict_types=1);
/**
 * fetch_property.php
 * Fully standalone token + property fetcher:
 *  - Hardcoded DB credentials
 *  - Reads access token from token table (token_type='trestle')
 *  - Fetches listings and upserts into rets_property
 *  - Persists offset in app_state
 */

date_default_timezone_set('UTC');

// ---- Hardcoded Database Credentials ----
$dbHost = 'localhost';
$dbName = '';
$dbUser = '';
$dbPass = '';

// ---- Token table selector ----
$token_type = 'trestle';

// ---- PDO connect ----
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    exit('DB connect error: ' . $e->getMessage());
}

// ---- Ensure app_state table exists ----
$pdo->exec("
CREATE TABLE IF NOT EXISTS app_state (
  k VARCHAR(64) PRIMARY KEY,
  v VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ---- app_state helpers ----
function state_get(PDO $pdo, string $k, $default = null) {
    $st = $pdo->prepare('SELECT v FROM app_state WHERE k = :k');
    $st->execute([':k'=>$k]);
    $v = $st->fetchColumn();
    return ($v === false) ? $default : $v;
}
function state_set(PDO $pdo, string $k, string $v): void {
    $st = $pdo->prepare('INSERT INTO app_state (k, v) VALUES (:k, :v)
                         ON DUPLICATE KEY UPDATE v=VALUES(v)');
    $st->execute([':k'=>$k, ':v'=>$v]);
}

// ---- Verify destination table exists ----
$exists = $pdo->query("SHOW TABLES LIKE 'rets_property'")->fetchColumn();
if (!$exists) {
    http_response_code(500);
    exit('Table not found: rets_property');
}

// ---- Get bearer token from token table ----
$st = $pdo->prepare('SELECT access_token, expires_at FROM token WHERE token_type = :tt LIMIT 1');
$st->execute([':tt'=>$token_type]);
$tokRow = $st->fetch();
if (!$tokRow || empty($tokRow['access_token'])) {
    http_response_code(500);
    exit('No access token found in token table');
}
$access_token = $tokRow['access_token'];

// ---- HTTP GET helper ----
function http_get_json(string $url, string $bearer, int $timeout=30): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer '.$bearer,
            'Accept: application/json'
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) throw new RuntimeException('cURL error: '.$err);
    if ($code !== 200) throw new RuntimeException('HTTP '.$code.': '.substr((string)$body, 0, 500));
    $data = json_decode((string)$body);
    if (!is_object($data) && !is_array($data)) {
        throw new RuntimeException('Bad JSON from API');
    }
    return [$data, $body];
}

// ---- URLs ----
$base = 'https://api-trestle.corelogic.com/trestle/odata/Property';
$q_common = '$orderby=ListingContractDate+desc,ListingKey+desc'
          . '&$filter=PropertyType+eq+\'Residential\'+and+MlsStatus+eq+\'Active\'';

// ---- 1) COUNT ----
$countUrl = $base . '?' . $q_common . '&$top=1&$count=true';
try {
    [$countObj, $_] = http_get_json($countUrl, $access_token, 25);
} catch (Throwable $e) {
    http_response_code(502);
    exit('Count request failed: '.$e->getMessage());
}

if (!isset($countObj->{'@odata.count'}) || (int)$countObj->{'@odata.count'} <= 0) {
    echo "No record found\n";
    exit;
}

$total = (int)$countObj->{'@odata.count'};
$limit = 200;

// ---- Pagination state ----
$updated_offset = (int)(state_get($pdo, 'insert_property_offset', '0') ?? '0');
if ($updated_offset < 0) $updated_offset = 0;
if ($updated_offset >= $total) $updated_offset = 0;

echo "Total records: $total | starting offset: $updated_offset\n";

// ---- 2) PAGE ----
$pageUrl = $base . '?' . $q_common
         . '&$skip=' . $updated_offset
         . '&$top='  . $limit
         . '&$count=true&$expand=Media($orderby=Order)';
try {
    [$pageObj, $_2] = http_get_json($pageUrl, $access_token, 45);
} catch (Throwable $e) {
    http_response_code(502);
    exit('Page request failed: '.$e->getMessage());
}

if (empty($pageObj->value) || !is_array($pageObj->value)) {
    echo "No page results\n";
    $next = $updated_offset + $limit;
    if ($next >= $total) $next = 0;
    state_set($pdo, 'insert_property_offset', (string)$next);
    exit;
}

// ---- small helpers ----
function toDate(?string $s, string $fmt='Y-m-d'): ?string {
    if (empty($s)) return null;
    $t = strtotime($s);
    return $t ? date($fmt, $t) : null;
}
function toDateTime(?string $s): ?string { return toDate($s, 'Y-m-d H:i:s'); }
function yn($v): ?int { return $v === null ? null : ((int)(bool)$v); }

// ---- Dynamic upsert ----
function insert_property(PDO $pdo, array $data): bool {
    $cols  = array_keys($data);
    $place = array_map(function($c) { return ':' . $c; }, $cols); // compatible with older PHP

    $updates = [];
    foreach ($cols as $c) {
        if ($c === 'L_ListingID') continue; // don't update unique key itself
        $updates[] = "`$c`=VALUES(`$c`)";
    }

    $sql = "INSERT INTO rets_property (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $place) . ") "
         . "ON DUPLICATE KEY UPDATE " . implode(',', $updates);

    $st = $pdo->prepare($sql);
    foreach ($data as $k => $v) {
        $st->bindValue(':' . $k, $v);
    }
    return $st->execute();
}

// ---- Iterate + upsert ----
$inserted = 0;
$failed   = 0;

foreach ($pageObj->value as $row) {
    // Collect media URLs (if any)
    $mediaUrls = [];
    if (!empty($row->Media) && is_array($row->Media)) {
        foreach ($row->Media as $m) {
            if (!empty($m->MediaURL)) $mediaUrls[] = $m->MediaURL;
        }
    }
    $images = $mediaUrls ? json_encode($mediaUrls) : null;

    // --- mapping (from your prior version) ---
    $data = [
        'L_ListingID'                   => $row->ListingKey ?? null,
        'L_DisplayId'                   => $row->ListingKey ?? null,
        'L_Address'                     => $row->UnparsedAddress ?? null,
        'L_Zip'                         => $row->PostalCode ?? null,
        'LM_char10_70'                  => $row->SubdivisionName ?? null,
        'L_AddressStreet'               => $row->StreetName ?? null,
        'L_City'                        => $row->City ?? null,
        'L_State'                       => $row->StateOrProvince ?? null,
        'L_Class'                       => $row->PropertyType ?? null,
        'L_Type_'                       => $row->PropertySubType ?? null,
        'L_Keyword2'                    => $row->BedroomsTotal ?? null,
        'LM_Dec_3'                      => $row->BathroomsTotalInteger ?? null,
        'L_Keyword1'                    => $row->LotSizeArea ?? null,
        'L_Keyword5'                    => $row->GarageSpaces ?? null,
        'L_Keyword7'                    => $row->Levels ?? null,
        'L_SystemPrice'                 => $row->ListPrice ?? null,
        'LM_Int2_3'                     => $row->LivingArea ?? null,
        'ModificationTimestamp'         => toDateTime($row->ModificationTimestamp ?? null),
        'ListingContractDate'           => toDate($row->ListingContractDate ?? null),
        'LMD_MP_Latitude'               => $row->Latitude ?? null,
        'LMD_MP_Longitude'              => $row->Longitude ?? null,
        'LA1_UserFirstName'             => $row->ListAgentFirstName ?? null,
        'LA1_UserLastName'              => $row->ListAgentLastName ?? null,
        'L_Status'                      => $row->MlsStatus ?? null,
        'LO1_OrganizationName'          => $row->ListOfficeName ?? null,
        'L_Remarks'                     => $row->PublicRemarks ?? null,
        'L_Photos'                      => $images,
        'PhotoCount'                    => $row->PhotosCount ?? null,
        'Flooring'                      => $row->Flooring ?? null,
        'ViewYN'                        => yn($row->ViewYN ?? null),
        'PoolPrivateYN'                 => yn($row->PoolPrivateYN ?? null),
        'DaysOnMarket'                  => $row->DaysOnMarket ?? null,
        'AssociationFeeFrequency'       => $row->AssociationFeeFrequency ?? null,
        'AttachedGarageYN'              => yn($row->AttachedGarageYN ?? null),
        'SubdivisionName'               => $row->SubdivisionName ?? null,
        'YearBuilt'                     => $row->YearBuilt ?? null,
        'FireplaceYN'                   => yn($row->FireplaceYN ?? null),
        'MainLevelBedrooms'             => $row->MainLevelBedrooms ?? null,
        'NewConstructionYN'             => yn($row->NewConstructionYN ?? null),
        'HighSchoolDistrict'            => $row->HighSchoolDistrict ?? null,
        'AssociationFee'                => $row->AssociationFee ?? null,
        'Fencing'                       => $row->Fencing ?? null,
        'LivingAreaUnits'                       => $row->LivingAreaUnits ?? null,
        'HumanModifiedYN'                       => yn($row->HumanModifiedYN ?? null),
        'DaysOnMarketReplicationDate'           => toDate($row->DaysOnMarketReplicationDate ?? null),
        'AdditionalParcelsYN'                   => yn($row->AdditionalParcelsYN ?? null),
        'SeniorCommunityYN'                     => yn($row->SeniorCommunityYN ?? null),
        'Cooling'                               => $row->Cooling ?? null,
        'StatusChangeTimestamp'                 => toDateTime($row->StatusChangeTimestamp ?? null),
        'SecurityFeatures'                      => $row->SecurityFeatures ?? null,
        'AssociationName'                       => $row->AssociationName ?? null,
        'HeatingYN'                             => yn($row->HeatingYN ?? null),
        'Appliances'                            => $row->Appliances ?? null,
        'CountyOrParish'                        => $row->CountyOrParish ?? null,
        'AssociationYN'                         => yn($row->AssociationYN ?? null),
        'PropertySubTypeAdditional'             => $row->PropertySubTypeAdditional ?? null,
        'CommonWalls'                           => $row->CommonWalls ?? null,
        'CountrySubdivision'                    => $row->CountrySubdivision ?? null,
        'CommonInterest'                        => $row->CommonInterest ?? null,
        'StandardStatus'                        => $row->StandardStatus ?? null,
        'Roof'                                  => $row->Roof ?? null,
        'ListAgentOfficePhone'                  => $row->ListAgentOfficePhone ?? null,
        'ListingTerms'                          => $row->ListingTerms ?? null,
        'ListOfficeEmail'                       => $row->ListOfficeEmail ?? null,
        'DaysOnMarketReplication'               => $row->DaysOnMarketReplication ?? null,
        'LotSizeAcres'                          => $row->LotSizeAcres ?? null,
        'WaterSource'                           => $row->WaterSource ?? null,
        'StoriesTotal'                          => $row->StoriesTotal ?? null,
        'LandLeaseYN'                           => yn($row->LandLeaseYN ?? null),
        'PreviousListPrice'                     => $row->PreviousListPrice ?? null,
        'OccupantType'                          => $row->OccupantType ?? null,
        'AssociationAmenities'                  => $row->AssociationAmenities ?? null,
        'CumulativeDaysOnMarket'                => $row->CumulativeDaysOnMarket ?? null,
        'PoolFeatures'                          => $row->PoolFeatures ?? null,
        'Heating'                               => $row->Heating ?? null,
        'StructureType'                         => $row->StructureType ?? null,
        'DaysOnMarketReplicationIncreasingYN'   => yn($row->DaysOnMarketReplicationIncreasingYN ?? null),
        'CommunityFeatures'                     => $row->CommunityFeatures ?? null,
        'PreviousStandardStatus'                => $row->PreviousStandardStatus ?? null,
        'UniversalParcelId'                     => $row->UniversalParcelId ?? null,
        'CoolingYN'                             => yn($row->CoolingYN ?? null),
        'ListAgentKey'                          => $row->ListAgentKey ?? null,
        'OnMarketDate'                          => toDate($row->OnMarketDate ?? null),
        'LotSizeUnits'                          => $row->LotSizeUnits ?? null,
        'ListAgentEmail'                        => $row->ListAgentEmail ?? null,
        'MajorChangeTimestamp'                  => toDateTime($row->MajorChangeTimestamp ?? null),
        'ElevationUnits'                        => $row->ElevationUnits ?? null,
        'GarageYN'                              => yn($row->GarageYN ?? null),
        'PostalCity'                            => $row->PostalCity ?? null,
        'PriceChangeTimestamp'                  => toDateTime($row->PriceChangeTimestamp ?? null),
        'ListAgentAOR'                          => $row->ListAgentAOR ?? null,
        'LivingAreaSource'                      => $row->LivingAreaSource ?? null,
        'AssociationFee2Frequency'              => $row->AssociationFee2Frequency ?? null,
        'Disclosures'                           => $row->Disclosures ?? null,
        'EntryLevel'                            => $row->EntryLevel ?? null,
        'ListAgentFullName'                     => $row->ListAgentFullName ?? null,
        'PropertyCondition'                     => $row->PropertyCondition ?? null,
        'ParcelNumber'                          => $row->ParcelNumber ?? null,
        'View'                                  => $row->View ?? null,
        'InteriorFeatures'                      => $row->InteriorFeatures ?? null,
        'PropertyAttachedYN'                    => yn($row->PropertyAttachedYN ?? null),
        'TaxLot'                                => $row->TaxLot ?? null,
        'BackOnMarketDate'                      => toDate($row->BackOnMarketDate ?? null),
        'ListAgentDirectPhone'                  => $row->ListAgentDirectPhone ?? null,
        'RoomType'                              => $row->RoomType ?? null,
        'CoListAgentFullName'                   => $row->CoListAgentFullName ?? null,
        'EntryLocation'                         => $row->EntryLocation ?? null,
        'SpaYN'                                 => yn($row->SpaYN ?? null),
        'SpaFeatures'                           => $row->SpaFeatures ?? null,
        'BathroomsHalf'                         => $row->BathroomsHalf ?? null,
        'LotSizeArea'                           => $row->LotSizeArea ?? null,
        'PatioAndPorchFeatures'                 => $row->PatioAndPorchFeatures ?? null,
        'FireplaceFeatures'                     => $row->FireplaceFeatures ?? null,
        'SpecialListingConditions'              => $row->SpecialListingConditions ?? null,
        'OriginalEntryTimestamp'                => toDateTime($row->OriginalEntryTimestamp ?? null),
        'LotFeatures'                           => $row->LotFeatures ?? null,
        'NumberOfUnitsTotal'                    => $row->NumberOfUnitsTotal ?? null,
        'LotSizeSquareFeet'                     => $row->LotSizeSquareFeet ?? null,
        'ArchitecturalStyle'                    => $row->ArchitecturalStyle ?? null,
        'OpenParkingSpaces'                     => $row->OpenParkingSpaces ?? null,
    ];
    if (!empty($row->PhotosChangeTimestamp)) {
        $data['PhotoTime'] = toDateTime($row->PhotosChangeTimestamp);
    }

    try {
        $ok = insert_property($pdo, $data);
        if ($ok) { $inserted++; } else { $failed++; }
    } catch (Throwable $e) {
        $failed++;
        error_log('rets_property insert failed for '.($row->ListingKey ?? 'NULL').' | '.$e->getMessage());
    }
}

echo "Inserted/Updated: $inserted | Failed: $failed\n";

// ---- advance offset ----
$next = $updated_offset + $limit;
if ($next >= $total) $next = 0;
state_set($pdo, 'insert_property_offset', (string)$next);
echo "Next offset saved: $next\n";
