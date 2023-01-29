<?php

namespace YaleREDCap\UserRightsHistory;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

class UserRightsHistoryTest extends \ExternalModules\ModuleBaseTest
{
    function testYourMethod()
    {
        $expected = 'expected value';
        $actual2 = $this->yourMethod();

        $this->assertSame($expected, $actual2);
    }
}
