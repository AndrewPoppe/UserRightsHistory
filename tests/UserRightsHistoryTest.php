<?php

namespace YaleREDCap\UserRightsHistory;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

class UserRightsHistoryTest extends \ExternalModules\ModuleBaseTest
{

    function setUp(): void
    {
        parent::setUp();
        $Logger = $this->getMockBuilder('\YaleREDCap\UserRightsHistory\Logger')
            ->disableOriginalConstructor()
            ->setMethods(['logEvent', 'logError'])
            ->getMock();
        $Logger->method('logEvent')
            ->will($this->returnCallback(function ($message, $args) {
                return ["message" => $message, "args" => $args];
            }));
        $Logger->method('logError')
            ->will($this->returnCallback(function ($message, $args) {
                return ["message" => $message, "args" => $args];
            }));
        $this->module->Logger = $Logger;
    }

    function getTestPID()
    {
        return \ExternalModules\ExternalModules::getTestPIDs()[0];
    }

    function testRedcap_module_system_change_version()
    {
        $oldVersion = '1';
        $newVersion = '2';
        $logParams = $this->redcap_module_system_change_version($newVersion, $oldVersion);
        $this->assertEquals("module version", $logParams["message"]);
        $this->assertEquals($newVersion, $logParams["args"]["version"]);
        $this->assertEquals($oldVersion, json_decode($logParams["args"]["previous"]));
    }


    /**
     * @dataProvider provideDagChecks
     */
    function testShouldDagsBeChecked($settings, $expected)
    {
        $project_id = $this->getTestPID();
        $_GET['pid'] = $project_id;
        //var_dump($this->getProject()->getUsers());

        $mock = $this->getMockBuilder('\YaleREDCap\UserRightsHistory\UserRightsHistory')
            ->onlyMethods(['getProjectSetting', 'getCurrentDag'])
            ->addMethods(['getProject', 'getUser'])
            ->getMock();
        $mock->method('getProject')
            ->will($this->returnValue(null));
        $mock->method('getProjectSetting')
            ->will($this->returnValue($settings['restrictDagSetting']));
        $mock->method('getCurrentDag')
            ->will($this->returnValue($settings['currentDag']));

        $result = $mock->shouldDagsBeChecked();
        $this->assertSame($expected, $result);
    }

    function provideDagChecks()
    {
        return [
            'Restrict DAG setting is off and user is not in a DAG' => [
                [
                    'restrict-dag' => '0',
                    'current-dag' => null
                ],
                false
            ],
            'Restrict DAG setting is off and user is in a DAG' => [
                [
                    'restrict-dag' => '0',
                    'current-dag' => 1
                ],
                false
            ],
            'Restrict DAG setting is on and user is not in a DAG' => [
                [
                    'restrict-dag' => '0',
                    'current-dag' => null
                ],
                false
            ],
        ];
    }
}
