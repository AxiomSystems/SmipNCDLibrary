 <?php
/* Purge all NCD Devices from Model */
require_once 'thinkiq_context.php';
$context = new Context();

use \TiqUtilities\Model\Node;
use \TiqUtilities\Model\Type;
use \TiqUtilities\Model\Equipment;
use \TiqUtilities\Model\Attribute;

function findNCDRoot($ncdRootType) {
    $ncdRootType->getInstances();
    foreach ($ncdRootType->instances as $aInstance) {
        $ncdRoot = $aInstance;
        return $ncdRoot;
    }
    return null;
}

$ncdRootType = new Type('ncd_sensors.ncd_sensor_root');
$ncdRoot = findNCDRoot($ncdRootType);

$ncdRoot->getChildren();
foreach($ncdRoot->children as $aChild) {
    $results[] = "Deleting node " .  $aChild->display_name . " " . $aChild->id;
    $aChild->delete();
}

die ('{"operation": "purge", "result": true}');
?>