<?php

namespace YaleREDCap\UserRightsHistory;

class Settings
{
    private $module;
    public function __construct(UserRightsHistory $module)
    {
        $this->module = $module;
    }

    function shouldDagsBeChecked($User, $projectId)
    {
        $isSuperUser = $User->isSuperUser();
        $username = $User->getUsername();
        $DAGsSetting = $this->module->getProjectSetting("restrict-dag");
        $currentDAG = $this->module->getCurrentDag(
            $projectId,
            $username
        );
        return !$isSuperUser && $DAGsSetting != "1" && !is_null($currentDAG);
    }

    function isDagRestricted($dag, $User, $projectId)
    {
        $username = $User->getUsername();
        $current_dag = $this->module->getCurrentDag($projectId, $username);
        return $this->shouldDagsBeChecked($User, $projectId) && $dag != $current_dag;
    }
}
