<?php

namespace YaleREDCap\UserRightsHistory;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

class SettingsTest extends \ExternalModules\ModuleBaseTest
{

    /**
     * @dataProvider provideDagChecks
     */
    public function testShouldDagsBeChecked($settings, $expected)
    {
        $moduleStub = $this->createStub('\YaleREDCap\UserRightsHistory\UserRightsHistory');
        $moduleStub->method('getProjectSetting')
            ->willReturn($settings['restrict-dag']);
        $moduleStub->method('getCurrentDag')
            ->willReturn($settings['current-dag']);

        $mockUser = $this->createStub('\ExternalModules\User');
        $mockUser->method('isSuperUser')
            ->willReturn($settings['is-superuser']);
        $mockUser->method('getUsername')
            ->willReturn('testUser');

        $settings = new Settings($moduleStub);
        $result = $settings->shouldDagsBeChecked($mockUser, 1);
        $this->assertEquals($expected, $result);
    }

    public function provideDagChecks()
    {
        return [
            'Restrict DAG setting is off and user is not in a DAG - user is not an admin' => [
                [
                    'restrict-dag' => '1',
                    'current-dag' => null,
                    'is-superuser' => false
                ],
                false
            ],
            'Restrict DAG setting is off and user is in a DAG - user is not an admin' => [
                [
                    'restrict-dag' => '1',
                    'current-dag' => 1,
                    'is-superuser' => false
                ],
                false
            ],
            'Restrict DAG setting is on and user is not in a DAG - user is not an admin' => [
                [
                    'restrict-dag' => '0',
                    'current-dag' => null,
                    'is-superuser' => false
                ],
                false
            ],
            'Restrict DAG setting is on and user is in a DAG - user is not an admin' => [
                [
                    'restrict-dag' => '0',
                    'current-dag' => 1,
                    'is-superuser' => false
                ],
                true
            ],
            'Restrict DAG setting is off and user is not in a DAG - user is an admin' => [
                [
                    'restrict-dag' => '1',
                    'current-dag' => null,
                    'is-superuser' => true
                ],
                false
            ],
            'Restrict DAG setting is off and user is in a DAG - user is an admin' => [
                [
                    'restrict-dag' => '1',
                    'current-dag' => 1,
                    'is-superuser' => true
                ],
                false
            ],
            'Restrict DAG setting is on and user is not in a DAG - user is an admin' => [
                [
                    'restrict-dag' => '0',
                    'current-dag' => null,
                    'is-superuser' => true
                ],
                false
            ],
            'Restrict DAG setting is on and user is in a DAG - user is an admin' => [
                [
                    'restrict-dag' => '0',
                    'current-dag' => 1,
                    'is-superuser' => true
                ],
                false
            ]
        ];
    }
}
