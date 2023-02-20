<?php

namespace YaleREDCap\UserRightsHistory;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

class SystemTest extends \ExternalModules\ModuleBaseTest
{

    /**
     * @dataProvider provideUpdateEnabledByDefaultStatus
     */
    public function testUpdateEnabledByDefaultStatus($settings, $expected)
    {
        $statementResultStub = $this->createStub('\ExternalModules\StatementResult');
        $statementResultStub->method('fetch_assoc')
            ->willReturn(['status' => $settings["previous"]]);
        $moduleStub = $this->createStub('\YaleREDCap\UserRightsHistory\UserRightsHistory');
        $moduleStub->method('queryLogs')
            ->willReturn($statementResultStub);
        $loggerMock = $this->createMock(Logger::class);
        if (is_null($expected)) {
            $loggerMock->expects($this->never())
                ->method('logEvent');
        } else {
            $loggerMock->expects($this->once())
                ->method('logEvent')
                ->with(
                    $this->equalTo("module enabled by default status"),
                    $this->callback(function ($args) use ($expected) {
                        return ($args["status"] === $expected);
                    })
                );
        }
        $system = new System($moduleStub);
        $system->logger = $loggerMock;
        $system->updateEnabledByDefaultStatus($settings["current"]);
    }

    public function provideUpdateEnabledByDefaultStatus()
    {
        return [
            "Currently disabled and previously disabled produces no change" => [
                ["current" => 0, "previous" => 0], NULL
            ],
            "Currently disabled and previously enabled produces log of 0" => [
                ["current" => 0, "previous" => 1], 0
            ],
            "Currently enabled and previously disabled produces log of 1" => [
                ["current" => 1, "previous" => 0], 1
            ],
            "Currently enabled and previously enabled produces no change" => [
                ["current" => 1, "previous" => 1], NULL
            ],
        ];
    }


    public function testLogVersionIfNeeded_notNeeded()
    {
        $moduleStub = $this->createStub('\YaleREDCap\UserRightsHistory\UserRightsHistory');
        $moduleStub->method('getModuleDirectoryName')
            ->willReturn('99');
        $statementResultStub = $this->createStub('\ExternalModules\StatementResult');
        $statementResultStub->method('fetch_assoc')
            ->willReturn(['version' => '99']);
        $moduleStub->method('queryLogs')
            ->willReturn($statementResultStub);
        $loggerMock = $this->createMock(Logger::class);
        $loggerMock->expects($this->never())
            ->method('logEvent');

        $system = new System($moduleStub);
        $system->logger = $loggerMock;
        $system->logVersionIfNeeded();
    }

    public function testLogVersionIfNeeded_isNeeded()
    {
        $moduleStub = $this->createStub('\YaleREDCap\UserRightsHistory\UserRightsHistory');
        $moduleStub->method('getModuleDirectoryName')
            ->willReturn('99');
        $statementResultStub = $this->createStub('\ExternalModules\StatementResult');
        $statementResultStub->method('fetch_assoc')
            ->willReturn(null);
        $moduleStub->method('queryLogs')
            ->willReturn($statementResultStub);
        $loggerMock = $this->createMock(Logger::class);
        $loggerMock->expects($this->once())
            ->method('logEvent')
            ->with(
                $this->equalTo('module version'),
                $this->callback(function ($args) {
                    return ($args['version'] === '99');
                })
            );

        $system = new System($moduleStub);
        $system->logger = $loggerMock;
        $system->logVersionIfNeeded();
    }
}
