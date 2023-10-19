<?php
/* Discover all NCD Devices from tags and add to Model */
require_once 'thinkiq_context.php';
$context = new Context();
use \TiqUtilities\Model\Node;
use \TiqUtilities\Model\Type;
use \TiqUtilities\Model\Equipment;
use \TiqUtilities\Model\Attribute;
use \TiqUtilities\Model\Organization;
use \TiqUtilities\Model\Script;
$results = [];

/* Main Code */

//Find our types
$ncdRootType = new Type('ncd_sensors.ncd_sensor_root');
$ncdGatewayType = new Type('ncd_sensors.ncd_sensor_gateway');
$ncdSensorTypes = [new Type('ncd_sensors.ncd_wireless_sensor'), new Type('ncd_sensors.ncd_environment_sensor'), new Type('ncd_sensors.ncd_wireless_sensor'), new Type('ncd_sensors.ncd_wireless_sensor'), new Type('ncd_sensors.ncd_wireless_sensor'), new Type('ncd_sensors.ncd_wireless_sensor'), new Type('ncd_sensors.ncd_wireless_sensor'), new Type('ncd_sensors.ncd_wireless_sensor'), new Type('ncd_sensors.ncd_wireless_sensor'), new Type('ncd_sensors.ncd_light_sensor')];

//Find which organization to attach objects to
$rootOrg = null;
$orgs = Organization::getNodeSet("organizations")["set"];
$root_orgs = array_filter($orgs, function($aOrg) {
    return $aOrg->part_of_id==null;
});
if (!isset($root_orgs) || count($root_orgs) < 1) {
    die("Could not find the root org!");
}
$rootOrg = $root_orgs[0];
$results[] = "Using root org: " . $rootOrg->display_name;

//Find or create NCD Root
$ncdRoot = findNCDRoot($ncdRootType, $results);
if (!isset($ncdRoot)) {
    $equipment = new Equipment();
    $equipment->display_name = 'NCD Sensors';
    $equipment->description = 'The root node for NCD Sensors';
    $equipment->type_id = $ncdRootType->id;
    $equipment->part_of_id = $root_orgs[0]->id;
    try {
        $equipment->save();
        $results[] = 'Created NCD Sensor root in: ' . $rootOrg->display_name;
        $ncdRoot = $equipment;
    }
    catch (Exception $e) {
        die ("Could not find or create a NCD sensor root: " . $e);
    }
}

//Find existing NCD instance objects
$knownGateways = [];
$knownGatewayIds = [];
$knownSensors = [];
$ncdRoot->getChildren();
foreach($ncdRoot->children as $aChild){
    if ($aChild->type_id == $ncdGatewayType->id) {
        $validGateway = false;
        $aChild->getAttributes();
        foreach($aChild->attributes as $aAttribute){
            if ($aAttribute->display_name == "MAC") {
                array_push($knownGateways, $aAttribute->current_value);
                array_push($knownGatewayIds, $aChild->id);
                $validGateway = true;
                $results[] =  "Model has gateway (" . $aChild->id . ") " . $aChild->display_name . " with MAC " . $aAttribute->current_value;
            }
        }
        if ($validGateway) {
            $aChild->getChildren();
            foreach($aChild->children as $bChild){
                $bChild->getAttributes();
                foreach($bChild->attributes as $bAttribute) {
                    if ($bAttribute->display_name == "MAC") {
                        array_push($knownSensors, $bAttribute->current_value);
                        $results[] = "Model has sensor: " . $bChild->display_name . " with MAC " . $bAttribute->current_value;
                    }
                }
            }
        }
    }
    else {
        $results[] =  $aChild->type_id . " does not match " . $ncdGatewayType->id;
    }
}
$results[] = "Count of known gateways: " . count($knownGateways);
$results[] = "Count of known sensors: " . count($knownSensors);

//Look for NCD-looking tags
$query = "SELECT id, display_name FROM model.tags where display_name like 'gateway%' limit 100;" ;
$ncdTags = Node::GetDb()->run($query)->fetchAll();
foreach ($ncdTags as $ncdTag) {
    $tagParts = explode("/", $ncdTag['display_name']);
    $currentGatewayId = null;
    $currentGatewayMac = null;
    //Look for gateways
    if (count($tagParts) > 0) {
        if ($tagParts[0] == "gateway") {    //Make sure they're valid
            if (isset($tagParts[1]) && str_contains($tagParts[1], ":")) {
                $gatewayMac = $tagParts[1];
                if (!in_array($gatewayMac, $knownGateways)) {   //Create new gateway if match not found in model
                    $results[] = "Need to create a new gateway with MAC " . $gatewayMac;
                    $newId = createNCDGateway($gatewayMac, (count($knownGateways) + 1), $ncdGatewayType->id, $ncdRoot, $results);
                    if (isset($newId)) {         //Remember this newly created gateway so we don't re-create it later
                        array_push($knownGateways, $gatewayMac);
                        array_push($knownGatewayIds, $newId);
                    }
                } else {    //Use existing gateway if match found in model
                    $gwIndex = array_search($gatewayMac, $knownGateways); 
                    $currentGatewayId = $knownGatewayIds[$gwIndex];
                    $currentGatewayMac = $knownGateways[$gwIndex];
                }
            }
        }

        //Look for sensors
        if (count($tagParts) > 6) {
            if ($tagParts[2] == "sensor") {    //Make sure they're valid
                if (isset($tagParts[3]) && str_contains($tagParts[3], ":")) {
                    $sensorMac = $tagParts[5];
                    if (!in_array($sensorMac, $knownSensors)) {     //Create new sensor if match not found in model
                        //Detect sensor type
                        if (isset($tagParts[6]) && ($tagParts[6] == "humidity" || $tagParts[6] == "temperature")) { //Environment sensor type
                            $results[] = "Need to create a new sensor with MAC " . $sensorMac . " of type: environment";
                            createEquipment($sensorMac, "Environment Sensor", $ncdSensorTypes[1]->id, $currentGatewayId, $currentGatewayMac, $results);
                            array_push($knownSensors, $sensorMac);    //Remember this newly created gateway so we don't re-create it later
                        }
                        if (isset($tagParts[6]) && ($tagParts[6] == "lux" || $tagParts[6] == "proximity")) {    //Light sensor type
                            $results[] = "Need to create a new sensor with MAC " . $sensorMac . " of type: light";
                            createEquipment($sensorMac, "Light Sensor", $ncdSensorTypes[9]->id, $currentGatewayId, $currentGatewayMac, $results);
                            array_push($knownSensors, $sensorMac);    //Remember this newly created gateway so we don't re-create it later
                        }
                    }
                }
            }
        }
    } else {
        //Note unusual tag structure and move on
        $results[] = "Discovered tag " . $ncdTag['display_name'] . " does not have a gateway";
    }
}
die (json_encode($results));

/* Helper Functions */

//Search in model for a NCD Root node
function findNCDRoot($ncdRootType, &$results) {
    $ncdRootType->getInstances();
    foreach ($ncdRootType->instances as $aInstance) {
        $ncdRoot = $aInstance;
        return $ncdRoot;
    }
    return null;
}

//Create an instance of an NCD Gateway equipment type in the model
function createNCDGateway($mac, $suffix, $typeId, $parentObj, &$results) {
    $equipment = new Equipment();
    $equipment->display_name = 'NCD Gateway ' . $suffix ;
    $equipment->type_id = $typeId;
    $equipment->part_of_id = $parentObj->id;
    $equipment->description = 'Discovered automatically at ' . (new DateTime())->format(DateTimeInterface::RFC3339_EXTENDED);
    try {
        $equipment->save();
        $equipment->getAttributes();
        $equipment->attributes['mac']->string_value = $mac;
        $equipment->attributes['mac']->save();
        array_push($results, 'Created new gateway with MAC: ' . $mac . ", ID: ". $equipment->id);
        return $equipment->id;
    }
    catch (Exception $e) {
        array_push($results, 'Failed to create new gateway with MAC: ' . $mac . $e);
        return null;
    }
}

//Create an instance of an NCD Sensor equipment type in the model, using a sub-type for the appropriate NCD sensor
function createEquipment($mac, $suffix, $typeId, $parentId, $parentMac, &$results) {
    // global $results;
    $equipment = new Equipment();
    $equipment->display_name = 'NCD ' . $suffix;
    $equipment->type_id = $typeId;
    $equipment->part_of_id = $parentId;
    $equipment->description = "Discovered automatically at " . (new DateTime())->format(DateTimeInterface::RFC3339_EXTENDED);

    try {
        $equipment->save();
        array_push($results, "Created new equipment with MAC: " . $mac . ", ID:" . $equipment->id . ", child of Gateway with MAC: " . $parentMac);
        $equipment->getAttributes();
        $equipment->attributes['mac']->string_value = $mac;
        $equipment->attributes['mac']->save();
        bindEquipmentTags($equipment, $mac, $suffix, $parentMac, $results);
        return $equipment->id;
    }
    catch (Exception $e) {
        array_push($results, "Failed to create Equipment. It may already exist in the model! " . $e);
    }
}

//Logic to parse MQTT topic/payload naming to attribute-to-tag mappings
//  This should be a WoT file
function bindEquipmentTags($equipmentNode, $sensorMac, $ncdTypeName, $parentMac, &$results) {
    // global $results;
    $tagRoot = "gateway/" . $parentMac . "/sensor/" . $sensorMac . "/:/" . $sensorMac . "/";
    $tags = [
        "firmware_version",
        "battery_level",
        "rssi"
    ];
    $attribs = [
        "firmware_version",
        "battery_level",
        "rssi"
    ];
    switch ($ncdTypeName) {
        case "Environment Sensor": {
            array_push($tags, "humidity");
            array_push($attribs, "humidity");
            array_push($tags, "temperature");
            array_push($attribs, "temperature");
            break;
        }
        case "Light Sensor": {
            array_push($tags, "proximity");
            array_push($attribs, "proximity");
            array_push($tags, "lux");
            array_push($attribs, "brightness");
            break;
        }
    }
    $i = 0;
    foreach ($tags as $tag) {
        array_push($results, "Binding tag: " . $tagRoot . $tag . " to: " . $equipmentNode->id . ":" . $attribs[$i]);
        $query = "SELECT * FROM model.tags where display_name='" . $tagRoot . $tag . "';" ;
        $result = Node::GetDb()->run($query)->fetch();
        if($result != false) {
            $equipmentNode->getAttributes();
            $equipmentNode->attributes[$attribs[$i]]->data_source = "tag";
            $equipmentNode->attributes[$attribs[$i]]->tag_id = $result["id"];
            $equipmentNode->attributes[$attribs[$i]]->save();
            try{
                $equipmentNode->save();
                array_push($results, "Success: Tag bound to instance attribute!");
            } catch (Exception $e) {
                array_push($results, "Failure: Could not bind tag to instance attribute: ",  $e->getMessage());
            }
        } else {
            array_push($results, "Could not find tag to bind to instance attribute!");
        }
        $i++;
    }
}
?>