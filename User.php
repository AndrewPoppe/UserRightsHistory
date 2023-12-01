<?php

namespace YaleREDCap\UserRightsHistory;

class User
{
    function __construct(array $userPermissions, Renderer $renderer)
    {
        $this->userPermissions = $userPermissions;
        $this->renderer = $renderer;
    }

    function getUserText()
    {
        $email = $this->userPermissions["email"];
        $username = $this->userPermissions["username"];
        $name = $this->userPermissions["name"];
        $suspended = $this->userPermissions["suspended"];
        $isSuperUser = $this->userPermissions["isSuperUser"];

        $suspendedText = $suspended ? "<span class='nowrap' style='color:red;font-size:11px;margin-left:8px;'>[account suspended]</span>" : "";
        $superUserText = $isSuperUser ? "<span class='nowrap' style='color:#009000;font-size:11px;margin-left:8px;'>[super user]</span>" : "";

        $nameText = "<span class='popoverspan' style='color:rgb(0, 89, 173);' data-toggle='popover' data-bs-trigger='hover' data-content='${email}' title='Email Address'><strong>${username}</strong> (${name})</span>";
        return $nameText . $suspendedText . $superUserText;
    }

    function getExpirationDate()
    {
        return $this->userPermissions["expiration"];
    }

    function getDagText()
    {
        $dags = $this->renderer->permissions["dags"];
        $currentDagId = $this->userPermissions["group_id"];
        $currentDag = $dags[$currentDagId];
        $additionalDagIds = $this->userPermissions["possibleDags"];
        $result = is_null($currentDag) ? "<span style='color:lightgray;'>—</span>" : "<span style='color:#008000;'>" . $currentDag["group_name"] . "</span>&nbsp;[" . $currentDag["group_id"] . "]";
        $username = $this->userPermissions["username"];
        if (is_array($additionalDagIds) && !empty($additionalDagIds)) {
            $additionalDagText = " <span  class=\"popoverspan\" style='font-size:75%; color:gray;' data-toggle='popover' data-bs-trigger='hover' title='<i class=\"fas fa-cube mr-1\"></i>DAG Switcher' data-content='<div>User <span class=\"text-primary\">${username}</span> may switch to DAGs:<ul>";
            $additionalDagIds = array_diff($additionalDagIds, array($currentDagId));
            foreach ($additionalDagIds as $additionalDagId) {
                $additionalDag = $dags[$additionalDagId];
                $additionalDagName = $additionalDag["group_name"];
                $additionalDagText .= "<li><span><span class=\"text-info\">$additionalDagName</span> [$additionalDagId]</span></li>";
            }
            $nAdditionalDags = count($additionalDagIds);
            $additionalDagText .= "</ul></div>'>&nbsp;&nbsp;(+${nAdditionalDags})</span>";
            $result .= $additionalDagText;
        }
        return '<div style="display:flex; align-items:center; justify-content:center;">' . $result . '</div>';
    }
}
