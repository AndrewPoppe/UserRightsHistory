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
        return "<span class='popoverspan' data-toggle='popover' data-content='${email}' title='Email Address'><strong>${username}</strong> (${name})</span>";
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
            $additionalDagText = " <span  class=\"popoverspan\" style='font-size:75%; color:gray;' data-toggle='popover' title='<i class=\"fas fa-cube mr-1\"></i>DAG Switcher' data-content='<div>User <span class=\"text-primary\">${username}</span> may switch to DAGs:<ul>";
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
//<div class="popover fade bs-popover-right" role="tooltip" id="popover785046" style="position: absolute; transform: translate3d(941px, 551px, 0px); top: 0px; left: 0px; will-change: transform;" x-placement="right"><div class="arrow" style="top: 53px;"></div><h3 class="popover-header"><i class="fas fa-cube mr-1"></i>DAG Switcher</h3><div class="popover-body"><div>User <span class="text-primary">alice</span> may switch to DAGs:<ul><li><span class="text-info">dag2</span></li></ul></div></div></div>