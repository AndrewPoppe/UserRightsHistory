<?php

namespace YaleREDCap\UserRightsHistory;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

class UserRightsHistoryTest extends \ExternalModules\ModuleBaseTest
{

    static $testUsers = ['testUser1', 'testUser2'];

    public function setUp(): void
    {
        parent::setUp();
        $Logger = $this->createStub('\YaleREDCap\UserRightsHistory\Logger');
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

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        foreach (self::$testUsers as $testUser) {
            \ExternalModules\ExternalModules::removeUserFromProject(self::getTestPID(), $testUser);
        }
    }

    public static function getTestPID()
    {
        return \ExternalModules\ExternalModules::getTestPIDs()[0];
    }

    public function test_redcap_module_system_change_version()
    {
        $oldVersion = '1';
        $newVersion = '2';

        $Logger = $this->createMock(Logger::class);
        $Logger->expects($this->once())
            ->method('logEvent')
            ->with(
                $this->equalTo("module version"),
                $this->callback(function ($args) use ($oldVersion, $newVersion) {
                    return $args["version"] === $newVersion && $args["previous"] === json_encode($oldVersion);
                })
            );
        $this->module->Logger = $Logger;

        $this->redcap_module_system_change_version($newVersion, $oldVersion);
    }

    public function test_redcap_module_project_enable()
    {
        $version = '1';
        $project_id = '2';
        $Logger = $this->createMock(Logger::class);
        $Logger->expects($this->once())
            ->method('logEvent')
            ->with(
                $this->equalTo("module project status"),
                $this->callback(function ($args) use ($version, $project_id) {
                    return ($args["version"] === $version &&
                        $args["status"] === 1 &&
                        $args["project_id"] === $project_id &&
                        $args["current"] === json_encode("Enabled") &&
                        $args["previous"] === json_encode("Disabled")
                    );
                })
            );
        $this->module->Logger = $Logger;
        $this->redcap_module_project_enable($version, $project_id);
    }

    public function test_redcap_module_project_disable()
    {
        $version = '1';
        $project_id = '2';
        $Logger = $this->createMock(Logger::class);
        $Logger->expects($this->once())
            ->method('logEvent')
            ->with(
                $this->equalTo("module project status"),
                $this->callback(function ($args) use ($version, $project_id) {
                    return ($args["version"] === $version &&
                        $args["status"] === 0 &&
                        $args["project_id"] === $project_id &&
                        $args["current"] === json_encode("Disabled") &&
                        $args["previous"] === json_encode("Enabled")
                    );
                })
            );
        $this->module->Logger = $Logger;
        $this->redcap_module_project_disable($version, $project_id);
    }

    public function test_redcap_module_system_enable()
    {
        $version = '1';
        $Logger = $this->createMock(Logger::class);
        $Logger->expects($this->once())
            ->method('logEvent')
            ->with(
                $this->equalTo("module system status"),
                $this->callback(function ($args) use ($version) {
                    return ($args["version"] === $version &&
                        $args["status"] === 1
                    );
                })
            );
        $this->module->Logger = $Logger;
        $this->redcap_module_system_enable($version);
    }

    public function test_redcap_module_system_disable()
    {
        $version = '1';
        $Logger = $this->createMock(Logger::class);
        $Logger->expects($this->once())
            ->method('logEvent')
            ->with(
                $this->equalTo("module system status"),
                $this->callback(function ($args) use ($version) {
                    return ($args["version"] === $version &&
                        $args["status"] === 0
                    );
                })
            );
        $this->module->Logger = $Logger;
        $this->redcap_module_system_disable($version);
    }

    public function test_redcap_module_configuration_settings_with_empty_project_id()
    {
        $project_id = null;
        $settings = ["example" => 12];
        $result = $this->redcap_module_configuration_settings($project_id, $settings);
        $this->assertEquals($result, $settings);
    }

    public function test_redcap_module_configuration_settings_with_set_project_id()
    {
        $Project = $this->getProject(self::getTestPID());
        foreach (self::$testUsers as $testUser) {
            $Project->addUser($testUser);
        }
        $project_id = $this->getTestPID();
        $settings = ["example" => 12];
        $result = $this->redcap_module_configuration_settings($project_id, $settings);
        $this->assertEquals(sizeof($result), sizeof($settings) + sizeof(self::$testUsers));
        foreach (self::$testUsers as $index => $testUser) {
            $thisResult = $result[$index];
            $this->assertEquals($thisResult['key'], $testUser . '_access');
            $this->assertEquals($thisResult['name'], "<strong></strong> ({$testUser})");
        }
    }

    public function test_redcap_module_configuration_settings_with_bad_PID()
    {
        $project_id = "BAD";
        $settings = [];
        $Logger = $this->createMock(Logger::class);
        $Logger->expects($this->once())
            ->method('logError')
            ->with($this->equalTo("Error creating configuration"));
        $this->module->Logger = $Logger;
        $this->redcap_module_configuration_settings($project_id, $settings);
    }

    public function test_redcap_module_configuration_settings_with_bad_settings()
    {
        $project_id = $this->getTestPID();
        $settings = "BAD";
        $Logger = $this->createMock(Logger::class);
        $Logger->expects($this->once())
            ->method('logError')
            ->with($this->equalTo("Error creating configuration"));
        $this->module->Logger = $Logger;
        $this->redcap_module_configuration_settings($project_id, $settings);
    }

    /**
     * @dataProvider provide_redcap_module_link_check_display
     */
    public function test_redcap_module_link_check_display($settings, $expected)
    {
        $frameworkStub = $this->createStub('\ExternalModules\Framework');
        $frameworkStub->method('getProjectSetting')
            ->willReturnCallback(function ($key) use ($settings) {
                if ($key === "restrict-access") {
                    return $settings["restrict-access"];
                } else {
                    return $settings["user-access-key"];
                }
            });
        $userStub = $this->createStub('\ExternalModules\User');
        $userStub->method('getUsername')
            ->willReturn(self::$testUsers[0]);
        $userStub->method('isSuperUser')
            ->willReturn($settings["is-superuser"]);
        $frameworkStub->method('getUser')
            ->willReturn($userStub);
        $this->module->framework = $frameworkStub;
        $result = $this->redcap_module_link_check_display(self::getTestPID(), "test_link");
        $this->assertSame($result, $expected);
    }

    public function provide_redcap_module_link_check_display()
    {
        return [
            "Restrict Access setting is off and user has access and user is superuser => show link" => [
                ["restrict-access" => 0, "user-access-key" => 1, "is-superuser" => true], "test_link"
            ],
            "Restrict Access setting is off and user has access and user is not superuser => show link" => [
                ["restrict-access" => 0, "user-access-key" => 1, "is-superuser" => false], "test_link"
            ],
            "Restrict Access setting is off and user does not have access and user is superuser => show link" => [
                ["restrict-access" => 0, "user-access-key" => 0, "is-superuser" => true], "test_link"
            ],
            "Restrict Access setting is off and user does not have access and user is not superuser => show link" => [
                ["restrict-access" => 0, "user-access-key" => 0, "is-superuser" => false], "test_link"
            ],
            "Restrict Access setting is on and user has access and user is superuser => show link" => [
                ["restrict-access" => 1, "user-access-key" => 1, "is-superuser" => true], "test_link"
            ],
            "Restrict Access setting is on and user has access and user is not superuser => show link" => [
                ["restrict-access" => 1, "user-access-key" => 1, "is-superuser" => false], "test_link"
            ],
            "Restrict Access setting is on and user does not have access and user is superuser => show link" => [
                ["restrict-access" => 1, "user-access-key" => 0, "is-superuser" => true], "test_link"
            ],
            "Restrict Access setting is on and user does not have access and user is not superuser => show link" => [
                ["restrict-access" => 1, "user-access-key" => 0, "is-superuser" => false], null
            ]
        ];
    }
}
