<?php

namespace App\Scenarios;

/**
 * Scenario Topic Selection の選択結果 status。
 */
enum ScenarioTopicSelectionStatus: string
{
    case UsedInScenario = 'used_in_scenario';
    case SelectedNotUsed = 'selected_not_used';
}
